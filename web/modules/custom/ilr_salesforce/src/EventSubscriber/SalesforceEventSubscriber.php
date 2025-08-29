<?php

namespace Drupal\ilr_salesforce\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\ilr_salesforce\CourseToTopicsTrait;

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
      $query->fields[] = "Class_Number__c";
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
    # Including an eventid below sends these registrations to Salesforce.
    variant:
    eventid:
    post_button_text:
    outreach_marketing_personas:
    EOT;

    // Check if this is a Learning Series.
    if (str_ends_with($sf->field('Class_Number__c'), 'CLSR')) {
      // Suppress from event listings.
      $event_landing_page->behavior_suppress_listing->value = 1;
      $event_variant = 'cahrs_learning_series';
    }
    else {
      $event_variant = 'cahrs_event';
    }

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
      # Including an eventid below sends these registrations to Salesforce.
      variant: $event_variant
      eventid: $sfid
      post_button_text:
      outreach_marketing_personas:
      # Class number: {$sf->field('Class_Number__c')}
      EOT;

      if ($event_variant === 'cahrs_event') {
        $event_landing_page->field_registration_form->default_data .= <<<EOT
        # CONFIG (DO NOT DELETE) {"cahrs_session_detail_options":[]}
        EOT;
      }
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
      $event_landing_page->body->format = 'standard_formatting';
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
