<?php

namespace Drupal\localist_paragraph_behavior\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\ilr\QueryString;

/**
 * Provides a Union section settings behavior.
 *
 * @ParagraphsBehavior(
 *   id = "localist_events",
 *   label = @Translation("Events from Localist - DEPRECATED"),
 *   description = @Translation("Display a listing of events from the Cornell Localist instance at events.cornell.edu, restricted by tags or keywords."),
 *   weight = 1
 * )
 */
class LocalistEvents extends ParagraphsBehaviorBase {

  const LOCALIST_HOSTNAME = 'events.cornell.edu';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'events_shown' => 5,
      'keywords' => 'ILR',
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
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'events_shown') ?? '',
      '#description' => $this->t('Use 0 (zero) to show all events.'),
      '#required' => TRUE,
    ];

    $form['keywords'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keywords'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'keywords') ?? '',
      '#description' => $this->t('Try "ILR". Separate multiple keywords with commas.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @see https://developer.localist.com/doc/api
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    $items = [];
    $events_shown = (int) $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'events_shown');
    $keywords = $this->getKeywords($paragraphs_entity);
    $cid = 'localist_events:' . implode(',', $keywords);
    $json_cache_item = \Drupal::cache()->get($cid);

    if ($json_cache_item) {
      $data = $json_cache_item->data;
    }
    else {
      $query_params = new QueryString();
      $query_params->add('days', 364);
      $query_params->add('pp', 100);

      foreach ($keywords as $keyword) {
        $query_params->add('keyword[]', $keyword);
      }

      // Add a random string to avoid the realpath cache.
      $query_params->add('rand', mt_rand());
      $url = 'https://' . self::LOCALIST_HOSTNAME . '/api/2/events?' . $query_params;
      $json_response = file_get_contents($url);
      $data = json_decode($json_response, TRUE);
      \Drupal::cache()->set($cid, $data, time() + (60 * 60 * 2));
    }

    if (empty($data)) {
      return $build;
    }

    // @todo consider using double underscore template suggestions here if
    // different list styles need to be supported.
    foreach ($data['events'] as $item) {
      // If there is an image for this event, run it through an image style.
      if (!empty($item['event']['photo_url'])) {
        $item['event']['ilr_image'] = [
          '#theme' => 'imagecache_external__localist_event',
          '#uri' => $item['event']['photo_url'],
          '#style_name' => 'medium_3_2',
          '#alt' => 'Localist event image for ' . $item['event']['title'],
        ];
      }

      $items[] = [
        '#theme' => 'localist_event',
        '#event' => $item['event'],
      ];

      if ($events_shown !== 0 && count($items) >= $events_shown) {
        break;
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
   * Split keywords on `,` and return them as an array.
   */
  protected function getKeywords(Paragraph $paragraph) {
    if ($keywords = $paragraph->getBehaviorSetting($this->getPluginId(), 'keywords')) {
      // Remove empty keywords and trim any spaces around keywords.
      return array_filter(array_map('trim', explode(',', $keywords)), 'strlen');
    }

    return [];
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
        'value' => $keywords,
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

}
