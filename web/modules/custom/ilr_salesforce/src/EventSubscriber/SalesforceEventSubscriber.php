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
  public static function getSubscribedEvents() {
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

    if ($event->getMapping()->id() === 'certificate_node') {
      // Add time fields the `certificate_node` mapping, to be used in
      // self::pullPresaveCertificateNode().
      $query->fields[] = "Active__c";
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

    if ($event->getMapping()->id() === 'certificate_node') {
      $this->pullPresaveCertificateNode($event);
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
   * Pull presave event callback for certificate nodes.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   *
   *   Assumptions
   *   - Times are stored in America/New_York but do not account for DST
   *   - End times are the same date as the start times.
   */
  private function pullPresaveCertificateNode(SalesforcePullEvent $event) {
    $certificate = $event->getEntity();
    $sf = $event->getMappedObject()->getSalesforceRecord();
    $sf_status = $sf->field('Active__c');
    $certificate->published = ($sf_status == 'active') ? 1 : 0;
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
