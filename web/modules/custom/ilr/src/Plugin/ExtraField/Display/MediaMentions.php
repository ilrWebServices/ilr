<?php

namespace Drupal\ilr\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\person\PersonaManager;

/**
 * 'Media mentions' pseudo field for employee profiles.
 *
 * @ExtraFieldDisplay(
 *   id = "media_mentions",
 *   label = @Translation("Media mentions"),
 *   bundles = {
 *     "persona.ilr_employee",
 *   },
 *   visible = true
 * )
 */
class MediaMentions extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PersonaManager $personaManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('persona.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * $entity is always a persona, Jeff.
   */
  public function view(ContentEntityInterface $entity) {
    $elements = [];
    $media_mentions = [];

    if ($entity->hasField('field_media_mentions')) {
      $media_mentions = $entity->field_media_mentions->referencedEntities();
    }

    $media_mentions_needed = 3 - count($media_mentions);

    if ($media_mentions_needed > 0) {
      $personas = $this->personaManager->getPersonas($entity->person->entity);

      $media_mention_query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->condition('type', 'media_mention')
        ->range(0, $media_mentions_needed)
        ->condition('field_experts', array_keys($personas), 'IN')
        ->sort('sticky', 'DESC')
        ->sort('created', 'DESC');

      $current_media_mentions = $media_mention_query->execute();

      foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($current_media_mentions) as $current_media_mention) {
        $media_mentions[$current_media_mention->id()] = $current_media_mention;
      }
    }

    if (empty($media_mentions)) {
      return [];
    }

    $elements['media_mentions'] = [
      '#theme' => 'item_list__media_mentions',
      '#title' => $entity->getDisplayName() . ' ' . $this->t('In the News'),
      '#items' => [],
      '#attributes' => ['class' => 'media-mentions'],
      '#context' => ['persona' => $entity],
      '#cache' => [
        'tags' => ['node_list:media_mention'],
        'context' => ['url'],
      ],
    ];

    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    foreach ($media_mentions as $media_mention) {
      $render_element = $view_builder->view($media_mention, 'teaser_compact');
      $elements['media_mentions']['#items'][] = $render_element;
    }
    return $elements;
  }

}
