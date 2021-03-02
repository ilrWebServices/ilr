<?php

namespace Drupal\ilr_instagram\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\ilr_instagram\InstagramFeedProcessor;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Instagram Listing paragraph behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "instagram_listing",
 *   label = @Translation("Instagram Listing"),
 *   description = @Translation("Display a configurable number of Instagram posts for a given username."),
 *   weight = 3
 * )
 */
class InstagramListing extends ParagraphsBehaviorBase {

  /**
   * The instagram feed processor service.
   *
   * @var \Drupal\ilr_instagram\InstagramFeedProcessor
   */
  protected $instagramProcessor;

  /**
   * Constructs a ParagraphsBehaviorBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\ilr_instagram\InstagramFeedProcessor $instagram_processor
   *   The instagram feed processor service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, InstagramFeedProcessor $instagram_processor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->instagramProcessor = $instagram_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('ilr_instagram.feed_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'feed_url' => '',
      'item_limit' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['feed_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram feed URL'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'feed_url') ?? $this->defaultConfiguration()['feed_url'],
      '#description' => $this->t('Zapier can create feeds for Instagram accounts.')
    ];

    $form['item_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of posts'),
      '#min' => 0,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'item_limit') ?? $this->defaultConfiguration()['item_limit'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {
    $username = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'feed_url');

    if (!$username) {
      return;
    }

    $posts = $this->instagramProcessor->getPosts($username);

    if (!$posts) {
      return;
    }

    $post_count = 0;
    $item_limit = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'item_limit') ?? $this->defaultConfiguration()['item_limit'];

    $build['posts'] = [
      '#theme' => 'container__instagram_listing',
      '#children' => [],
      '#attributes' => [
        'class' => ['instagram-listing']
      ],
    ];

    foreach ($posts as $post_url => $post) {
      $post_item = [
        '#theme' => 'container__instagram_post',
        '#children' => [],
        '#attributes' => [
          'class' => ['instagram-post']
        ],
      ];

      if (isset($post['description'])) {
        $caption = $post['description'];
        $post_item['#children']['caption'] = [
          '#markup' => '<p>' . Unicode::truncate(trim($caption), 300, TRUE, TRUE, 5) . '</p>',
        ];
      }

      $post_item['#children']['image'] = [
        '#theme' => 'imagecache_external__instagram_post',
        '#uri' => $post['enclosure']->url,
        '#style_name' => 'thumbnail',
        '#alt' => 'Instagram image for post at ' . $post['link'],
      ];

      $post_item['#children']['url'] = [
        '#markup' => $post['link'],
      ];

      $build['posts']['#children'][$post_url] = $post_item;
      $post_count++;

      if ($item_limit && $post_count >= $item_limit) {
        break;
      }
    }

    // See ilr_instagram_cron().
    $build['#cache']['tags'] = ['instagram_posts'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];
    $username = '';
    $item_limit = 'All';

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'feed_url')) {
      $username = $paragraph->getBehaviorSetting($this->getPluginId(), 'feed_url');
    }

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'item_limit')) {
      $item_limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'item_limit');
    }

    $summary[] = [
      'label' => 'URL',
      'value' => $username,
    ];

    $summary[] = [
      'label' => 'Post count',
      'value' => $item_limit,
    ];

    return $summary;
  }

}
