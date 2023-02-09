<?php

namespace Drupal\ilr\Plugin\paragraphs\Behavior;

use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides a Card paragraph behavior plugin.
 *
 * @ParagraphsBehavior(
 *   id = "ilr_topic_list",
 *   label = @Translation("Topical listing"),
 *   description = @Translation("Configure Topical Listings."),
 *   weight = 3
 * )
 */
class TopicList extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    // There is no #parents key in $form, but this may be OK hardcoded.
    $parents = $form['#parents'];
    $parents_input_name = array_shift($parents);
    $parents_input_name .= '[' . implode('][', $parents) . ']';

    $form['use_media_aspect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve media aspect ratio'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect') ?? FALSE,
      '#description' => $this->t('Enable this setting to display the entire media item. When disabled, the media will be cropped to a 5:7 (portrait) aspect ratio.'),
    ];

    $form['teaser_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display links as inline teasers'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'teaser_links') ?? FALSE,
      '#description' => $this->t('Enable this setting to display links to posts and pages as small, inline teasers. Otherwise, only the link title will be displayed.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    $has_media = !$variables['paragraph']->field_media->isEmpty();

    if ($has_media && $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
      $variables['content']['field_media'][0]['#image_style'] = 'large_preserve_aspect';
    }

    $variables['items'] = [];
    $post_types = \Drupal::service('extended_post.manager')->getPostTypes();
    $post_types[] = 'page';

    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
    foreach ($variables['paragraph']->field_links as $link) {
      $url = $link->getUrl();
      $content = $link->title;

      if ($url->isRouted() && $url->getRouteName() === 'entity.node.canonical' && $variables['paragraph']->getBehaviorSetting($this->getPluginId(), 'teaser_links')) {
        $route_params = $url->getRouteParameters();
        $nid = $route_params['node'] ?? 0;

        if ($nid) {
          $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

          if ($node && in_array($node->bundle(), $post_types)) {
            $url = NULL;

            if ($link->title !== $node->getTitle()) {
              $node->setTitle($link->title);
            }

            $content = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'inline');

            // Add a cache tag for the paragraph to this node render array.
            // That way if the title is changed in the link field, which is part
            // of the paragraph, the node will be re-rendered.
            $content['#cache']['tags'][] = 'paragraph:' . $variables['paragraph']->id();
          }
        }
      }

      $variables['items'][] = [
        'url' => $url,
        'content' => $content,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraphs_entity, EntityViewDisplayInterface $display, $view_mode) {}

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph) {
    $summary = [];

    if ($paragraph->getBehaviorSetting($this->getPluginId(), 'use_media_aspect')) {
      $summary[] = [
        'label' => $this->t('Aspect ratio'),
        'value' => 'preserved',
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This behavior is only applicable to topic_list paragraphs.
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    return in_array($paragraphs_type->id(), ['topic_list']);
  }

}
