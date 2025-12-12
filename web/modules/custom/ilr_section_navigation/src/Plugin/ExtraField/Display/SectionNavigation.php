<?php

namespace Drupal\ilr_section_navigation\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Example Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "ilr_section_navigation",
 *   label = @Translation("In page navigation"),
 *   bundles = {
 *     "collection.blog",
 *     "collection.subsite",
 *     "collection.subsite_blog",
 *     "collection.content_section",
 *     "node.page",
 *     "node.report_summary",
 *     "taxonomy_term.*",
 *   },
 *   visible = true
 * )
 */
class SectionNavigation extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Instead of hard-coding field name below, consider looping through
   * fields and building the element based on the presence of the behavior
   * setting.
   */
  public function view(ContentEntityInterface $entity) {
    $elements = [];
    $links = [];

    if (!$entity->hasField('field_sections')) {
      return $elements;
    }

    foreach ($entity->field_sections as $section) {
      if (!$section->entity->isPublished()) {
        continue;
      }

      $in_page_title = $section->entity->getBehaviorSetting('in_page_nav', 'title');
      $in_page_fragment = $section->entity->getBehaviorSetting('in_page_nav', 'fragment');

      if ($in_page_title && $in_page_fragment) {
        $links[] = [
          '#type' => 'link',
          '#title' => $in_page_title,
          '#url' => $entity->toUrl('canonical', ['fragment' => $in_page_fragment]),
        ];
      }
    }

    if ($links) {
      $elements['ilr_section_navigation'] = [
        '#theme' => 'item_list__section_navigation',
        '#items' => $links,
        '#attributes' => ['class' => 'section-navigation'],
        '#context' => ['collection' => $entity],
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

}
