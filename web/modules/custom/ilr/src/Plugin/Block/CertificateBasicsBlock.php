<?php

namespace Drupal\ilr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'CertificateBasicsBlock' block.
 *
 * @Block(
 *  id = "certificate_basics_block",
 *  admin_label = @Translation("Certificate basics"),
 * )
 */
class CertificateBasicsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new BookNavigationBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    if (!$node = $this->requestStack->getCurrentRequest()->get('node')) {
      return $build;
    }

    if ($node->bundle() != 'certificate') {
      return $build;
    }

    if ($node->field_required_courses_text->isEmpty()) {
      $course_count = 0;

      foreach ($node->field_course->referencedEntities() as $course_node) {
        if ($course_node->isPublished()) {
          $course_count++;
        }
      }

      $required_courses_text = $this->t(<<<EOL
      <p><strong>@course_count Focused Workshops</strong><br/>
      Register for individual workshops to fit your schedule</p>
      EOL, [
        '@course_count' => $course_count
      ]);
    }
    else {
      $required_courses_text = $node->field_required_courses_text->value;
    }

    $build = [
      '#theme' => 'ilr_certificate_basics_block',
      '#node' => $node,
      '#completion_time' => $node->field_completion_time->value,
      '#required_courses_text' => $required_courses_text,
    ];
    return $build;
  }

}
