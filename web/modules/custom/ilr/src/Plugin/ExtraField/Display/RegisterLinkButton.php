<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Example Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "register_link_button",
 *   label = @Translation("Register link"),
 *   bundles = {
 *     "node.event_landing_page",
 *   },
 *   visible = true
 * )
 */
class RegisterLinkButton extends ExtraFieldDisplayBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $build = [];

    if (!$entity->field_registration_form->isEmpty() || !$entity->field_url->isEmpty()) {
      $url = !$entity->field_url->isEmpty()
        ? Url::fromUri($entity->field_url->uri)
        : Url::fromUserInput('#', [
          'fragment' => 'register',
        ]);

      $build['register_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Register Today'), // From current mockup. Should this be configurable?
        '#url' => $url,
        '#attributes' => [
          'class' => 'cu-button',
        ],
        '#attached' => [
          'library' => [
            'union_organizer/button',
          ],
        ],
        '#prefix' => '<div class="field--location-link">', // @see union_marketing_generic_event_registration_form_link_trigger().
        '#suffix' => '</div>',
      ];
    }

    if (!$entity->field_registration_form->isEmpty() && $default_data = $entity->field_registration_form->default_data) {
      $default_data_decoded = Yaml::decode($default_data);

      if (array_key_exists('post_button_text', $default_data_decoded)) {
        $post_button_text = $default_data_decoded['post_button_text'];

        $build['additional_info'] = [
          '#markup' => '<div class="post-button-text"><p class="cu-text">' . $post_button_text . '</p></div>',
        ];
      }
    }

    return $build;
  }

}
