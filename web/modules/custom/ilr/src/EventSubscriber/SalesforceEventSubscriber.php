<?php

namespace Drupal\ilr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\ilr\CourseToTopicsTrait;

/**
 * Class SalesforceEventSubscriber.
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
    // Add an additional field to the `course_node` mapping, to be used in
    // self::pullPresave().
    if ($event->getMapping()->id() === 'course_node') {
      $query = $event->getQuery();
      $query->fields[] = "Cornell_Department__c";
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

  private function getTopicsForCourseNumber($course_number) {
    // `course_to_topics_tsv` is set in CourseToTopicsTrait.
    $mapping_records = preg_split('/$\R?^/m', $this->course_to_topics_tsv);

    foreach ($mapping_records as $mapping_record) {
      if (preg_match('/^' . $course_number . '\t([^\t]+)\t?([^\t]+)?/', $mapping_record, $matches)) {
        array_shift($matches);
        return $matches;
      }
    }

    return [];
  }

}
