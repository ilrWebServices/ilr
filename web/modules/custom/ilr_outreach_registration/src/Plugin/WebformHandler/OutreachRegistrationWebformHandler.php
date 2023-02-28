<?php

namespace Drupal\ilr_outreach_registration\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform handler to submit registration submissions to Salesforce webhook.
 *
 * @WebformHandler(
 *   id = "outreach_registration_submitter",
 *   label = @Translation("ILR Outreach registration order submitter"),
 *   description = @Translation("Submits registration form submissions as orders to Salesforce."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class OutreachRegistrationWebformHandler extends WebformHandlerBase {

  /**
   * The Drupal queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Symfony\Component\HttpFoundation\Request definition.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->queue = $container->get('queue')->get('serialized_order_to_salesforce');
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->logger = $container->get('logger.factory')->get('serialized_order_to_salesforce');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $data = $webform_submission->getData();

    if (empty($data['eventid'])) {
      // @todo Log this.
      return;
    }

    // Only submit new form submissions to SF, not edits to existing ones.
    if ($update) {
      return;
    }

    // Add some submission data to the serialized order.
    $serialized_order = [
      'point_of_sale' => $this->request->getHost() . ' : ' . $webform_submission->webform_id->target_id . ' webform : ILR Drupal quick signup',
      'response_webhook_url' => $this->request->getSchemeAndHttpHost() . '/hooks/v1/salesforce-pingback/webform/' . $webform_submission->webform_id->target_id,
      'order_id' => $webform_submission->id(),
      'payments' => [],
      'customer' => [
        'company' => $data['company'] ?? 'NONE PROVIDED',
        'job_title' => $data['title'] ?? 'NONE PROVIDED',
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'email' => $data['email'],
        'address_line1' => $data['address'] ?? 'NONE PROVIDED',
        'address_line2' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country_code' => '',
        'additional_fields' => [],
      ],
      'order_total' => 0,
      'order_items' => [
        [
          // 'name' => $item->getTitle(),
          'discounts' => [],
          'price' => 0,
          'discounted_price' => 0,
          'quantity' => 1,
          'total' => 0,
          'discounted_total' => 0,
          'product' => [
            // 'product_type' => 'registration',
            // 'course_id' => $item->getData('sf_course_id'),
            'class_id' => $data['eventid'],
            'additional_fields' => [],
            'participants' => [
              [
                'participant_id' => $webform_submission->id(),
                // 'contact_sfid' => NULL,
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? NULL,
                'last_name' => $data['last_name'],
                'company' => $data['company'] ?? 'NONE PROVIDED',
                'address_line1' => $data['address'] ?? 'NONE PROVIDED',
                'address_line2' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country_code' => '',
                'job_title' => $data['title'] ?? 'NONE PROVIDED',
                'industry' => $data['industry'] ?? NULL,
                'phone' => $data['phone'] ?? NULL,
                'dietary_restrictions' => $data['dietary_restrictions'] ?? NULL,
                'accessible_accommodation' => $data['accessible_accommodation'] ?? NULL,
                'is_cornell_employee' => $data['is_cornell_employee'] ?? FALSE,
                'additional_fields' => [
                  [
                    'name' => 'Pass_Additional_Data__c',
                    'value' => ($data['opt_in'] && $data['outreach_marketing_personas']) ? 'persona:' . $data['outreach_marketing_personas'] : '',
                  ],
                ],
              ]
            ],
          ],
        ]
      ],
    ];

    // Add any stored UTM codes if they exist in the submission data.
    if (!empty($data['utm_values'])) {
      foreach ($data['utm_values'] as $utm_name => $utm_code) {
        $utm_name = ($utm_name === 'utm_term') ? 'utm_keyword': $utm_name;
        $serialized_order['customer']['additional_fields'][] = [
          'name' => strtoupper(substr($utm_name, 0, 5)).substr($utm_name, 5) . '__c',
          'value' => $utm_code,
        ];
      }
    }

    // Queue the serialized order for submission to the WebReg webhook on
    // Salesforce.
    $queue_item_id = $this->queue->createItem($serialized_order);

    $this->logger->notice('Outgoing Salesforce WebReg webhook request queued for webform submission @webform_submission', [
      '@webform_submission' => $webform_submission->id(),
    ]);
  }

}
