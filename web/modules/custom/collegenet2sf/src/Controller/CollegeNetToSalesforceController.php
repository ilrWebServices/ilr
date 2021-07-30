<?php

namespace Drupal\collegenet2sf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\collegenet2sf\CollegeNetToSalesforceProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetches remote CollegeNET data and passes it to a queue.
 */
class CollegeNetToSalesforceController extends ControllerBase {

  /**
   * CollegeNET to Salesforce Lead processing service.
   *
   * @var \Drupal\collegenet2sf\CollegeNetToSalesforceProcessor
   */
  protected $collegenet2sfProcessor;

  /**
   * Constructs this CollegeNetToSalesforceController controller.
   *
   * @param \Drupal\collegenet2sf\CollegeNetToSalesforceProcessor $collegenet2sf_processor
   *   The collegenet2sf_processor service.
   */
  public function __construct(CollegeNetToSalesforceProcessor $collegenet2sf_processor) {
    $this->collegenet2sfProcessor = $collegenet2sf_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('collegenet2sf.processor')
    );
  }

  /**
   * Callback for /collegenet2sf/endpoint/{key}.
   */
  public function endpoint() {
    $this->collegenet2sfProcessor->run();

    // HTTP 204 is 'No content'.
    return new Response('', 204);
  }

}
