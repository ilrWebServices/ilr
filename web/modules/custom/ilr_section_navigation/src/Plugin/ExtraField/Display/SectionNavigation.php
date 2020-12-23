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
 *     "collection.subsite_blog",
 *     "node.page",
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

    foreach ($entity->field_sections as $section) {
      if ($section_link = $section->entity->getBehaviorSetting('in_page_nav', 'title')) {
        $behavior = $section->entity->type->entity->getBehaviorPlugin('in_page_nav');

        $links[] = [
          '#type' => 'link',
          '#title' => $section_link,
          '#url' => $entity->toUrl('canonical', ['fragment' => $behavior->getFragment($section->entity)]),
        ];
      }
    }

    if ($links) {
      $elements['ilr_section_navigation'] = [
        '#theme' => 'item_list__section_navigation',
        '#title' => $this->t('Explore'),
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
