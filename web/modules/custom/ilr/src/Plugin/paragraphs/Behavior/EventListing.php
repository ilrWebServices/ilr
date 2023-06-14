<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
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

  const LOCALIST_HOSTNAME = 'events.cornell.edu';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates a new PostListing behavior.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   This plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['events_shown'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of events'),
      '#size' => 5,
      '#max' => 100,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'events_shown') ?? '',
      '#description' => $this->t('Use 0 (zero) to show all events (max 100).'),
      '#required' => TRUE,
    ];

    $keyword_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(
      array_keys($paragraph->getBehaviorSetting($this->getPluginId(), 'keywords') ?? [])
    );

    $form['keywords'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Keywords'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['event_keywords'],
      ],
      '#default_value' => $keyword_terms,
      '#description' => $this->t('Try "ILR". Separate multiple keywords with commas. Events with any of the keywords will be returned.'),
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
      '#description' => $this->t('TBD.'),
      '#options' => $event_node_bundles,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'sources') ?? '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $filtered_values = $this->filterBehaviorFormSubmitValues($paragraph, $form, $form_state);

    // Simplify the keyword array to `$tid => label()`. The values from the
    // entity_autocomplete are initially `0 => [ 'target_id' => $tid ]`.
    $keyword_data = [];

    foreach ($filtered_values['keywords'] as $keyword_term_info) {
      if ($term = $this->entityTypeManager->getStorage('taxonomy_term')->load($keyword_term_info['target_id'])) {
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
    $keywords = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'keywords') ?? [];
    $sources = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'sources') ?? [];
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $list_style = $paragraphs_entity->getBehaviorSetting('list_styles', 'list_style');
    $view_mode = $paragraphs_entity->type->entity->getBehaviorPlugin('list_styles')->getViewModeForListStyle($list_style);

    if (empty($sources)) {
      return;
    }

    // Get Localist events, if set as source and any are found.
    if (array_key_exists('_localist', $sources) && $localist_data = $this->getLocalistEvents($keywords)) {
      foreach ($localist_data['events'] as $localist_event) {
        $events[] = new IlrEvent(
          $localist_event['event']['title'],
          $localist_event['event']['event_instances'][0]['event_instance']['start'],
          $localist_event['event']['event_instances'][0]['event_instance']['end'],
          $localist_event['event']
        );
      }
    }

    // Get a date string suitable for use with entity query.
    $date_today = new DrupalDateTime();
    $date_today->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    // Append local node events, if any are set as source.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->accessCheck(TRUE); // Check if this is default.
    $query->condition('type', $sources, 'IN');
    $query->condition('event_date.value', $date_today->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=');
    $keywords_group = $query->orConditionGroup();

    foreach ($keywords as $keyword_tid => $keyword) {
      $keywords_group->condition('field_keywords', $keyword_tid);
    }

    $query->condition($keywords_group);
    $query->sort('event_date.value', 'DESC');

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
    usort($events, function($a, $b) {
      return $a->event_start <=> $b->event_start;
    });

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
          $event->object['ilr_image'] = [
            '#theme' => 'imagecache_external__localist_event',
            '#uri' => $event->object['photo_url'],
            '#style_name' => 'medium_3_2',
            '#alt' => 'Localist event image for ' . $event->object['title'],
          ];
        }

        $items[] = [
          '#theme' => 'localist_event__' . $view_mode,
          '#event' => $event->object,
        ];
      }
    }

    $build['event_listing'] = [
      '#theme' => 'item_list__minimal',
      '#title' => $this->t('Events'),
      '#items' => $items,
      '#empty' => $this->t('There are no events to display.'),
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
  protected function getLocalistEvents(array $keywords): array {
    $cid = 'localist_events:' . implode(',', $keywords);
    $json_cache_item = \Drupal::cache()->get($cid);

    if ($json_cache_item) {
      return $json_cache_item->data;
    }

    $query_params = new QueryString();
    $query_params->add('days', 364);
    $query_params->add('pp', 100);
    // $query_params->add('distinct', true);

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
      \Drupal::cache()->set($cid, $data, time() + (60 * 60 * 2));
    }
    catch (Exception $e) {
      $data = [];
    }

    restore_error_handler();

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    // If it's a wide section, display the summary.
    if ($events_shown = $paragraph->getBehaviorSetting($this->getPluginId(), 'events_shown')) {
      $summary[] = [
        'label' => 'Events shown',
        'value' => $events_shown,
      ];
    }

    // Display the frame position.
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
    return strpos($paragraphs_type->id(), 'event') !== FALSE;
  }

}
