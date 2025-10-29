<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\ilr\Entity\EventNodeInterface;
use Drupal\ilr\Event\IlrEvent;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\ilr\QueryString;
use ErrorException;
use Exception;
use GuzzleHttp\ClientInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Union section settings behavior.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_event_listing",
 *   label = @Translation("Events from EventNode content and/or Localist"),
 *   description = @Translation("Display a listing of events from EventNode entities and the Cornell Localist instance at events.cornell.edu, restricted by tags or keywords."),
 *   weight = 1
 * )
 */
class EventListing extends ParagraphsBehaviorBase {

  use LoggerChannelTrait;

  const LOCALIST_HOSTNAME = 'events.cornell.edu';

  /**
   * Creates a new PostListing behavior.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityFieldManagerInterface $entity_field_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClientInterface $httpClient
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'events_shown' => 6,
      'keywords' => 'ILR',
      'sources' => '_localist',
      'daterange_start' => NULL,
      'daterange_end' => NULL,
      'reverse' => NULL,
      'past_only' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['events_shown'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of events'),
      '#size' => 5,
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'events_shown') ?? 3,
      // '#description' => $this->t('If using a pager, this is the number of events per page.'),
      '#states' => [
        'enabled' => [
          [':input[name="' . $parents_input_name . '[daterange_start]"]' => ['filled' => FALSE]],
          'or',
          [':input[name="' . $parents_input_name . '[daterange_end]"]' => ['filled' => FALSE]],
        ],
        'required' => [
          [':input[name="' . $parents_input_name . '[daterange_start]"]' => ['filled' => FALSE]],
          'or',
          [':input[name="' . $parents_input_name . '[daterange_end]"]' => ['filled' => FALSE]],
        ],
        'disabled' => [
          [':input[name="' . $parents_input_name . '[past_only]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    $form['daterange_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Start date'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'daterange_start') ?? '',
    ];

    $form['daterange_end'] = [
      '#type' => 'date',
      '#title' => $this->t('End date'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'daterange_end') ?? '',
      '#description' => $this->t('Note that localist events can only span 365 days.'),
      '#states' => [
        'invisible' => [
          [':input[name="' . $parents_input_name . '[past_only]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    $form['past_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the current date as the end date'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'past_only') ?? FALSE,
      '#states' => [
        'invisible' => [
          [':input[name="' . $parents_input_name . '[daterange_start]"]' => ['filled' => FALSE]],
          'or',
          [':input[name="' . $parents_input_name . '[daterange_end]"]' => ['filled' => TRUE]],
        ],
      ],
    ];

    $form['reverse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show this listing in reverse chronological order'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'reverse') ?? FALSE,
      '#description' => $this->t('This can be useful when displaying a list of events in the past with the most recent showing first.'),
    ];

    $keyword_terms_options = [];

    if ($keyword_terms_ids = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'event_keywords'])) {
      $keyword_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_keys($keyword_terms_ids));

      foreach ($keyword_terms as $keyword_tid => $keyword_term) {
        $keyword_terms_options[$keyword_tid] = $keyword_term->label();
      }
    }

    $form['keywords'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Keywords'),
      '#options' => $keyword_terms_options,
      '#default_value' => array_keys($paragraph->getBehaviorSetting($this->getPluginId(), 'keywords') ?? []),
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    $event_node_bundles = [
      '_localist' => $this->t('Localist'),
    ];
    // Get bundle names of eventalicious node types, e.g. any node bundle with a
    // bundle class that extends EventNodeBase.
    foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo('node') as $bundle => $bundle_info) {
      if (isset($bundle_info['class'])) {
        $class = new ReflectionClass($bundle_info['class']);

        if ($class->isSubclassOf('\Drupal\ilr\Entity\EventNodeBase')) {
          $event_node_bundles[$bundle] = $bundle_info['label'];
        }
      }
    }

    $form['sources'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Sources'),
      '#options' => $event_node_bundles,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'sources') ?? '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    if ((!$form_state->getValue('daterange_start') || !$form_state->getValue('daterange_end')) && (empty($form_state->getValue('events_shown')) && !$form_state->getValue('past_only'))) {
      $form_state->setError($form['events_shown'], $this->t('Number of events is required unless both start and end are specified.'));
    }

    if (in_array('_localist', $form_state->getValue('sources')) && $form_state->getValue('past_only')) {
      $form_state->setError($form['past_only'], $this->t('The current date cannot be used as the end date with Localist due to API limitations.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $filtered_values = $this->filterBehaviorFormSubmitValues($paragraph, $form, $form_state);

    // Unset the events_shown limit when both the start and end date are specified.
    if (!empty($filtered_values['daterange_start']) && (!empty($filtered_values['daterange_end']) || !empty($filtered_values['past_only']))) {
      $filtered_values['events_shown'] = '';
    }

    // Change the keyword array to `$tid => label()`. The values from the
    // checkboxes are initially `$tid => $tid`.
    $keyword_data = [];

    foreach ($filtered_values['keywords'] as $keyword_tid) {
      if ($term = $this->entityTypeManager->getStorage('taxonomy_term')->load($keyword_tid)) {
        $keyword_data[$term->id()] = $term->label();
      }
    }

    $filtered_values['keywords'] = $keyword_data;
    $paragraph->setBehaviorSettings($this->getPluginId(), $filtered_values);
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    $items = [];
    $events = [];
    $events_shown = (int) $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'events_shown');
    $daterange_start = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'daterange_start') ?? '';
    $daterange_end = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'daterange_end') ?? '';
    $keywords = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'keywords') ?? [];
    $reverse = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'reverse') ?? FALSE;
    $past_only = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'past_only') ?? FALSE;
    $sources = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'sources') ?? [];
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $list_style = $paragraphs_entity->getBehaviorSetting('list_styles', 'list_style');
    $view_mode = $paragraphs_entity->type->entity->getBehaviorPlugin('list_styles')->getViewModeForListStyle($list_style);

    if (empty($sources)) {
      return;
    }

    // Get Localist events, if set as source and any are found.
    if (array_key_exists('_localist', $sources) && $localist_data = $this->getLocalistEvents($keywords, $daterange_start, $daterange_end, $events_shown)) {
      foreach ($localist_data['events'] as $localist_event) {
        $events[] = new IlrEvent(
          $localist_event['event']['title'],
          $localist_event['event']['event_instances'][0]['event_instance']['start'],
          $localist_event['event']['event_instances'][0]['event_instance']['end'] ?? '',
          $localist_event['event']
        );
      }
    }

    // Get a date string suitable for use with entity query.
    $date_today = new DrupalDateTime();
    $date_today->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    // Append local node events, if any are set as source.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('type', $sources, 'IN');
    $query->condition('status', 1);

    $zeroOrNullGroup = $query->orConditionGroup()
      ->condition('behavior_suppress_listing', 1, '!=')
      ->condition('behavior_suppress_listing', NULL, 'IS NULL');
    $query->condition($zeroOrNullGroup);

    if ($daterange_start) {
      $query->condition('event_date.value', $daterange_start, '>=');

      if ($past_only) {
        $query->condition('event_date.value', $date_today, '<');
      }
    }
    else {
      $query->condition('event_date.value', $date_today->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=');
    }

    if ($daterange_end) {
      $query->condition('event_date.value', $daterange_end, '<=');
    }

    $keywords_group = $query->orConditionGroup();

    foreach ($keywords as $keyword_tid => $keyword) {
      $keywords_group->condition('field_keywords', $keyword_tid);
    }

    $query->condition($keywords_group);
    $query->sort('event_date.value', 'ASC');

    // If a limit was set, limit the query. This may be limited further if
    // localist events are selected, too, but we'll never need _more_ than the
    // limit.
    if ($events_shown) {
      $query->range(0, $events_shown);
    }

    $event_node_ids = $query->execute();

    if (!empty($event_node_ids)) {
      foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($event_node_ids) as $node) {
        // @todo Check to see if dates are UTC and if that's OK.
        $events[] = new IlrEvent(
          $node->label(),
          $node->event_date->value,
          $node->event_date->end_value,
          $node
        );
      }
    }

    // Sort all events by start date.
    if ($reverse) {
      usort($events, function($a, $b) {
        return $b->event_start <=> $a->event_start;
      });
    }
    else {
      usort($events, function($a, $b) {
        return $a->event_start <=> $b->event_start;
      });
    }


    // Shorten the array to the limit.
    if ($events_shown) {
      array_splice($events, $events_shown);
    }

    foreach ($events as $event) {
      // This is a node that implements EventNodeInterface, so render it as a
      // teaser.
      if ($event->object instanceof EventNodeInterface) {
        $items[] = $node_view_builder->view($event->object, $view_mode);
      }
      // This is a localist event, so use a custom theme template.
      else {
        // If there is an image for this event, run it through an image style.
        if (!empty($event->object['photo_url'])) {
          // See if the undocumented 'card' variation of the image exists.
          $card_photo_url = str_replace('/huge/', '/card/', $event->object['photo_url']);

          try {
            $card_photo_check_req = $this->httpClient->request('HEAD', $card_photo_url, [
              'timeout' => 1,
            ]);

            if ($card_photo_check_req->getStatusCode() == 200) {
              $photo_url = $card_photo_url;
            }
          }
          catch (\Exception $e) {
            $photo_url = $event->object['photo_url'];
          }

          try {
            $photo_check_req = $this->httpClient->request('HEAD', $photo_url, [
              'timeout' => 1,
            ]);

            if ($photo_check_req->getStatusCode() != 200) {
              $photo_url = $this->getStaticImageData();
            }
          }
          catch (\Exception $e) {
            $photo_url = $this->getStaticImageData()  ;
          }

          $event->object['ilr_image'] = [
            '#theme' => 'image',
            '#uri' => $photo_url,
            '#width' => '720',
            '#height' => '480',
            '#alt' => 'Localist event image for ' . $event->object['title'],
          ];
        }

        $items[] = [
          '#theme' => 'localist_event__' . $view_mode,
          '#event' => $event->object,
        ];
      }
    }

    $build['listing'] = [
      'items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Get Localist events for given keywords
   *
   * @see https://developer.localist.com/doc/api
   *
   * @param array $keywords
   *   An array of Localist keywords.
   *
   * @return array
   *   An array of Localist events.
   */
  protected function getLocalistEvents(array $keywords, string $daterange_start = 'now', string $daterange_end = '', string $events_shown = NULL): array {
    $cid = 'localist_events:' . implode(',', $keywords) . ':' . $daterange_start . ':' . $daterange_end . ':' . $events_shown;
    $json_cache_item = \Drupal::cache()->get($cid);

    if ($json_cache_item) {
      return $json_cache_item->data;
    }

    $date_start = new \DateTime($daterange_start);
    $date_start->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    $query_params = new QueryString();
    $query_params->add('start', $date_start->format(DateTimeItemInterface::DATE_STORAGE_FORMAT));

    if ($daterange_end) {
      $date_end = new \DateTime($daterange_end);
      $date_end->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

      $interval = new \DateInterval('P1Y');
      $date_year = clone $date_start;
      $date_year->add($interval);

      if ($date_end > $date_year) {
        $query_params->add('end', $date_year->format(DateTimeItemInterface::DATE_STORAGE_FORMAT));
      }
      else {
        $query_params->add('end', $date_end->format(DateTimeItemInterface::DATE_STORAGE_FORMAT));
      }
    }
    else {
      $query_params->add('days', 364);
    }

    // If a limit was set, limit the results per-page (pp). This may be limited
    // further if node events are selected, too, but we'll never need _more_
    // than the limit.
    $query_params->add('pp', $events_shown ?: 100);

    // Multiple keywords appear to be OR'd.
    foreach ($keywords as $keyword) {
      $query_params->add('keyword[]', $keyword);
    }

    // Add a random string to avoid the realpath cache.
    $query_params->add('rand', mt_rand());
    $url = 'https://' . self::LOCALIST_HOSTNAME . '/api/2/events?' . $query_params;

    // @todo Swap this for a Guzzle implementation that includes better exception handling.
    set_error_handler(
      function($severity, $message, $file, $line) {
        throw new ErrorException($message, $severity, $severity, $file, $line);
      }
    );

    try {
      $json_response = file_get_contents($url);
      $data = json_decode($json_response, TRUE);

      // Log a warning if there are additional pages in the response and the
      // page limit is at the max of 100.
      if ($data['page']['size'] === 100 && $data['page']['total'] > 1) {
        $this->getLogger('event listing')->warning('There was more than one page of results for %url.', ['%url' => $url]);
      }

      \Drupal::cache()->set($cid, $data, time() + (60 * 60 * 2));
    }
    catch (Exception $e) {
      $data = [];
      $this->getLogger('event listing')->error($e->getMessage());
    }

    restore_error_handler();

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($events_shown = $paragraph->getBehaviorSetting($this->getPluginId(), 'events_shown')) {
      $summary[] = [
        'label' => 'Events shown',
        'value' => $events_shown,
      ];
    }

    if ($daterange_start = $paragraph->getBehaviorSetting($this->getPluginId(), 'daterange_start')) {
      $summary[] = [
        'label' => 'Start',
        'value' => $daterange_start,
      ];
    }

    if ($daterange_end = $paragraph->getBehaviorSetting($this->getPluginId(), 'daterange_end')) {
      $summary[] = [
        'label' => 'End',
        'value' => $daterange_end,
      ];
    }

    if ($keywords = $paragraph->getBehaviorSetting($this->getPluginId(), 'keywords')) {
      $summary[] = [
        'label' => 'Keywords',
        'value' => implode(', ', $keywords),
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to paragraphs that include 'event' in the
   * machine name.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return !$paragraphs_type->isNew() && strpos($paragraphs_type->id(), 'event') !== FALSE;
  }

  public function getStaticImageData() {
    return 'data:image/jpeg;base64,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';
  }

}
