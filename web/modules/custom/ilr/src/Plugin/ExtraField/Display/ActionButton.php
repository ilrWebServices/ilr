<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Extra field display for action buttons.
 *
 * @ExtraFieldDisplay(
 *   id = "ilr_action_button",
 *   label = @Translation("Action button"),
 *   bundles = {
 *     "node.event_landing_page",
 *     "node.landing_page",
 *   },
 *   visible = true
 * )
 */
class ActionButton extends ExtraFieldDisplayBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $build = [];

    $build['ilr_action_button'] = [
      '#type' => 'inline_template',
      '#template' => '<ilr-action-button class="cu-button cu-colorscheme--vibrant" type="{{ entity_type }}.{{ bundle }}"></ilr-actionbutton>',
      '#context' => [
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
      ],
      '#attached' => [
        'library' => [
          'union_organizer/button',
          'ilr/action_button',
        ],
      ],
    ];

    return $build;
  }

}
