<?php

namespace Drupal\ilr_instagram\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\ilr_instagram\InstagramScraper;
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
   * The instagram scraper service.
   *
   * @var \Drupal\ilr_instagram\InstagramScraper
   */
  protected $instagramScraper;

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
   * @param \Drupal\ilr_instagram\InstagramScraper $instagram_scraper
   *   The instagram scraper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, InstagramScraper $instagram_scraper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->instagramScraper = $instagram_scraper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('ilr_instagram.scraper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'ig_username' => '',
      'item_limit' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['ig_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram username'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'ig_username') ?? $this->defaultConfiguration()['ig_username'],
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
    $username = $paragraphs_entity->getBehaviorSetting($this->getPluginId(), 'ig_username');

    if (!$username) {
      return;
    }

    $posts = $this->instagramScraper->getPosts($username);

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

    foreach ($posts as $post_id => $post) {
      $post_item = [
        '#theme' => 'container__instagram_post',
        '#children' => [],
        '#attributes' => [
          'class' => ['instagram-post']
        ],
      ];

      $post_item['#children']['image'] = [
        '#markup' => sprintf('<img src="%s" alt="%s" class="cu-image">',
          $post['node']['thumbnail_src'],
          $post['node']['accessibility_caption'],
        ),
      ];

      $post_item['#children']['url'] = [
        '#markup' => Url::fromUri('https://www.instagram.com/p/' . $post['node']['shortcode'])->toString(),
      ];

      if (isset($post['node']['edge_media_to_caption']['edges'][0]['node']['text'])) {
        $caption = $post['node']['edge_media_to_caption']['edges'][0]['node']['text'];
        $post_item['#children']['caption'] = [
          '#markup' => '<p>' . Unicode::truncate(trim($caption), 300, TRUE, TRUE, 5) . '</p>',
        ];
      }

      $build['posts']['#children'][$post_id] = $post_item;
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

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'ig_username')) {
      $username = $paragraph->getBehaviorSetting($this->getPluginId(), 'ig_username');
    }

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'item_limit')) {
      $item_limit = $paragraph->getBehaviorSetting($this->getPluginId(), 'item_limit');
    }

    $summary[] = [
      'label' => 'Username',
      'value' => $username,
    ];

    $summary[] = [
      'label' => 'Post count',
      'value' => $item_limit,
    ];

    return $summary;
  }

}
