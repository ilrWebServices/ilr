<?php

namespace Drupal\ilr_salesforce\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\ilr_salesforce\CourseToTopicsTrait;
use Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Subscriber for SalesForce events.
 */
class SalesforceEventSubscriber implements EventSubscriberInterface {

  use CourseToTopicsTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SalesforceEventSubscriber service object.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SalesforceEvents::PULL_QUERY => 'pullQueryAlter',
      SalesforceEvents::PULL_PRESAVE => 'pullPresave',
      SalesforceEvents::PUSH_ALLOWED => 'pushAllowed',
      SalesforceEvents::PUSH_PARAMS => 'pushParams',
    ];
  }

  /**
   * SalesforceQueryEvent pull query alter event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforceQueryEvent $event
   *   The event.
   */
  public function pullQueryAlter(SalesforceQueryEvent $event) {
    $query = $event->getQuery();

    if ($event->getMapping()->id() === 'course_node') {
      // Add an additional field to the `course_node` mapping, to be used in
      // self::pullPresaveCourseNode().
      $query->fields[] = "Cornell_Department__c";
    }

    if ($event->getMapping()->id() === 'class_session') {
      // Add time fields the `class_session` mapping, to be used in
      // self::pullPresaveClassSession().
      $query->fields[] = "End_Time__c";
      $query->fields[] = "Start_Time__c";
    }

    if ($event->getMapping()->id() === 'cahrs_event_node') {
      $query->fields[] = "Close_Web_Registration__c";
      $query->fields[] = "Class_Description__c";
    }

    if ($event->getMapping()->id() === 'course_certificate_node') {
      // Add time fields the `course_certificate_node` mapping, to be used in
      // self::pullPresaveCourseCertificateNode().
      $query->fields[] = "Status__c";
    }
  }

  /**
   * Pull presave event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   */
  public function pullPresave(SalesforcePullEvent $event) {
    if ($event->getMapping()->id() === 'course_node') {
      $this->pullPresaveCourseNode($event);
    }

    if ($event->getMapping()->id() === 'class_session') {
      $this->pullPresaveClassSession($event);
    }

    if ($event->getMapping()->id() === 'cahrs_event_node') {
      $this->pullPresaveCahrsEventNode($event);
    }

    if ($event->getMapping()->id() === 'course_certificate_node') {
      $this->pullPresaveCourseCertificateNode($event);
    }
  }

  /**
   * Push allowed event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent $event
   *   The event.
   */
  public function pushAllowed(SalesforcePushAllowedEvent $event) {

    if ($event->getMapping()->id() === 'event_registration_touchpoint') {
      /** @var \Drupal\webform\WebformSubmissionInterface $submission */
      $submission = $event->getEntity();
      $submission_data = $submission->getData();

      // Ensure that there is an eventid present.
      if (empty($submission_data['eventid'])) {
        $event->disallowPush();
      }
    }
  }

  /**
   * Push params event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent $event
   *   The event.
   */
  public function pushParams(SalesforcePushParamsEvent $event) {
    $entity = $event->getEntity();
    $params = $event->getParams();
    $sf_object_name = $event->getMapping()->getSalesforceObjectType() ?? '';

    if ($entity instanceof WebformSubmissionInterface) {
      /** @var \Drupal\webform\WebformSubmissionInterface $entity */
      $data = $entity->getData();
      $webform = $entity->getWebform();

      // This is a hard-coded list of SF objects that have the fields
      // `Custom1_Question__c`, `Custom1_Answer__c`, `Custom2_Question__c`, and
      // `Custom2_Answer__c`.
      $custom_q_and_a_objects = ['Touchpoint__c'];

      if (in_array($sf_object_name, $custom_q_and_a_objects)) {
        // Add any custom questions.
        $custom_1_element = $webform->getElement('custom_1_answer');
        $custom_2_element = $webform->getElement('custom_2_answer');

        if ($custom_1_element && $custom_1_element['#access'] && isset($data['custom_1_answer'])) {
          $custom_1_question = $custom_1_element['#title'] ?? 'Custom question 1';
          $custom_1_answer = is_array($data['custom_1_answer']) ? implode(';', $data['custom_1_answer']) : $data['custom_1_answer'];
          $params->setParam('Custom1_Question__c', substr($custom_1_question, 0, 255));
          $params->setParam('Custom1_Answer__c', substr($custom_1_answer, 0, 255));
        }

        if ($custom_2_element && $custom_2_element['#access'] && isset($data['custom_2_answer'])) {
          $custom_2_question = $custom_2_element['#title'] ?? 'Custom question 2';
          $custom_2_answer = is_array($data['custom_2_answer']) ? implode(';', $data['custom_2_answer']) : $data['custom_2_answer'];
          $params->setParam('Custom2_Question__c', substr($custom_2_question, 0, 255));
          $params->setParam('Custom2_Answer__c', substr($custom_2_answer, 0, 255));
        }
      }

      // Send address info to Touchpoints. We can't map these values because
      // different variants can use different address fields.
      if ($sf_object_name === 'Touchpoint__c') {
        $address_variant = $data['variant_address'] ?? '';

        // Default the address values to the basic address field.
        if (($address_variant === 'basic_address' || $data['variant'] === 'cahrs_event') && !empty($data['address'])) {
          $params->setParam('Street_Address__c', $data['address']['address'] ?: '');
          $params->setParam('City__c', $data['address']['city'] ?: '');
          $params->setParam('State__c', $data['address']['state_province'] ?: '');
          $params->setParam('Zip_Postal_Code__c', $data['address']['postal_code'] ?: '');
          $params->setParam('Country__c', $data['address']['Country__c'] ?: '');
        }
        // If there is international address info, use those values instead.
        elseif ($address_variant === 'international_address' && !empty($data['address_intl'])) {
          $params->setParam('Street_Address__c', $data['address_intl']['address_line1'] ?: '');
          $params->setParam('City__c', $data['address_intl']['locality'] ?: '');
          $params->setParam('State__c', $data['address_intl']['administrative_area'] ?: '');
          $params->setParam('Zip_Postal_Code__c', $data['address_intl']['postal_code'] ?: '');
          $params->setParam('Country__c', $data['address_intl']['country_code'] ?: '');
        }
      }
    }
  }

  /**
   * Pull presave event callback for course nodes.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   */
  private function pullPresaveCourseNode(SalesforcePullEvent $event) {
    $course_node = $event->getEntity();
    $sf = $event->getMappedObject()->getSalesforceRecord();
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Assign a course_sponsor term, creating one if necessary. Use the course
    // department value from Salesforce.
    $course_sponsor_term = $term_storage->loadByProperties([
      'name' => $sf->field('Cornell_Department__c'),
      'vid' => 'course_sponsor',
    ]);
    $course_sponsor_term = reset($course_sponsor_term);

    // If there is no term for this sponsor, create a new one.
    if (empty($course_sponsor_term)) {
      $course_sponsor_term = $term_storage->create([
        'name' => $sf->field('Cornell_Department__c'),
        'vid' => 'course_sponsor',
      ]);
      $course_sponsor_term->save();
    }

    $course_node->set('field_sponsor', $course_sponsor_term->id());

    if ($course_node->field_topics->isEmpty()) {
      // Assign a topic term from the mapping.
      $topics_for_course = $this->getTopicsForCourseNumber($course_node->get('field_course_number')->value);
      $tids_for_course = [];

      foreach ($topics_for_course as $topic) {
        $course_topic_term = $term_storage->loadByProperties([
          'name' => $topic,
          'vid' => 'topics',
        ]);
        $course_topic_term = reset($course_topic_term);

        // If there is no term for this topic, create a new one.
        if (empty($course_topic_term)) {
          $course_topic_term = $term_storage->create([
            'name' => $topic,
            'vid' => 'topics',
          ]);
          $course_topic_term->save();
        }

        $tids_for_course[] = $course_topic_term->id();
      }

      $course_node->set('field_topics', $tids_for_course);
    }
  }

  /**
   * Maps courses to their topics based on spreadsheet data.
   */
  private function getTopicsForCourseNumber($course_number) {
    // `courseToTopicsTsv` is set in CourseToTopicsTrait.
    $mapping_records = preg_split('/$\R?^/m', $this->courseToTopicsTsv);

    foreach ($mapping_records as $mapping_record) {
      if (preg_match('/^' . $course_number . '\t([^\t]+)\t?([^\t]+)?/', $mapping_record, $matches)) {
        array_shift($matches);
        return $matches;
      }
    }

    return [];
  }

  /**
   * Pull presave event callback for class sessions.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   *
   *   Assumptions
   *   - Times are stored in America/New_York but do not account for DST
   *   - End times are the same date as the start times.
   */
  private function pullPresaveClassSession(SalesforcePullEvent $event) {
    $class_session = $event->getEntity();
    $sf = $event->getMappedObject()->getSalesforceRecord();
    $session_date = $sf->field('Session_Date__c');
    $start_time = substr($sf->field('Start_Time__c'), 0, 8);
    $end_time = substr($sf->field('End_Time__c'), 0, 8);

    $start_datetime = new DrupalDateTime("$session_date $start_time", 'America/New_York');
    $start_datetime->setTimezone(new \DateTimeZone('UTC'));
    $class_session->session_date->value = $start_datetime->format('Y-m-d\TH:i:s');

    $end_datetime = new DrupalDateTime("$session_date $end_time", 'America/New_York');
    $end_datetime->setTimezone(new \DateTimeZone('UTC'));
    $class_session->session_date->end_value = $end_datetime->format('Y-m-d\TH:i:s');
  }

  /**
   * Pull presave event callback for CAHRS event nodes.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   */
  private function pullPresaveCahrsEventNode(SalesforcePullEvent $event) {
    $event_landing_page = $event->getEntity();
    $sf = $event->getMappedObject()->getSalesforceRecord();
    $sfid = $sf->id();
    $sf_close_date = $sf->field('Close_Web_Registration__c') ? $sf->field('Close_Web_Registration__c') : $sf->field('Start__c');

    $close_datetime = new DrupalDateTime($sf_close_date, 'UTC');
    // @todo Revisit this if we really want to store the time as UTC.
    $close_datetime->setTimezone(new \DateTimeZone('America/New_York'));
    $event_landing_page->field_registration_form->status = 'scheduled';
    $event_landing_page->field_registration_form->close = $close_datetime->format('Y-m-d\TH:i:s');

    // @todo Determine this from the field itself.
    $default_default_data = <<<EOT
    # eventid is required.
    variant:
    eventid:
    post_button_text:
    outreach_marketing_personas:
    EOT;

    /**
     * The default data has trailing spaces, but our editors are configured to
     * remove them, so we calculate the similarity of the default value and the
     * actual value to see if users have modified it. We only want to update the
     * default data if it hasn't been edited already.
     *
     * Another option:
     * similar_text($default_default_data, $event_landing_page->field_registration_form->default_data, $percent);
     */
    if (levenshtein($default_default_data, $event_landing_page->field_registration_form->default_data) < 10) {
      $event_landing_page->field_registration_form->default_data = <<<EOT
      # eventid is required.
      variant: cahrs_event
      eventid: $sfid
      post_button_text:
      outreach_marketing_personas: CAHRS Quarterly
      # CONFIG (DO NOT DELETE) {"cahrs_session_detail_options":[]}
      EOT;
    }

    // Tag these event landing pages with 'CAHRS Partner Event'.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $cahrs_term_id = $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'event_keywords')
      ->condition('name', 'CAHRS Partner Event')
      ->execute();

    if (!empty($cahrs_term_id)) {
      $cahrs_term_id = reset($cahrs_term_id);
      $cahrs_term = $term_storage->load($cahrs_term_id);
    }
    else {
      $cahrs_term = $term_storage->create([
        'vid' => 'event_keywords',
        'name' => 'CAHRS Partner Event',
      ]);
      $cahrs_term->save();
    }

    // Ensure that the CAHRS keyword is added to any existing ones.
    $keywords = $event_landing_page->field_keywords->getValue();
    if (array_search($cahrs_term->id(), array_column($keywords, 'target_id')) === FALSE) {
      $keywords[] = ['target_id' => $cahrs_term->id()];
    }
    $event_landing_page->field_keywords = $keywords;

    // Add the description if it is empty. A text with summary field is only
    // considered empty if both the summary and value are blank.
    if ($event_landing_page->body->isEmpty()) {
      $event_landing_page->body->value = $sf->field('Class_Description__c');
      $event_landing_page->body->format = 'basic_formatting_with_media';
    }
  }

  /**
   * Pull presave event callback for course certificate nodes.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   */
  private function pullPresaveCourseCertificateNode(SalesforcePullEvent $event) {
    $course_certificate = $event->getEntity();
    $sf = $event->getMappedObject()->getSalesforceRecord();
    $course_certificate->status = ($sf->field('Status__c') == 'Active') ? 1 : 0;

    if (empty($sf->field('Web_Rank__c'))) {
      $course_certificate->field_weight = 1000;
    }
  }

}
