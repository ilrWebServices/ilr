<?php

declare(strict_types=1);

namespace Drupal\ilr_neutrals\EventSubscriber;

use Drupal\views\ResultRow;
use Drupal\views_remote_data\Events\RemoteDataQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fetch data for the Views Remote Data based Neutrals view.
 */
final class RemoteNeutralsSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ?\SplFileObject $file = null
  ){}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RemoteDataQueryEvent::class => 'onQuery',
    ];
  }

  /**
   * Subscribes to populate the view results.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataQueryEvent $event
   *   The event.
   */
  public function onQuery(RemoteDataQueryEvent $event): void {
    $supported_bases = ['ilr_neutrals_remote_data'];
    $base_tables = array_keys($event->getView()->getBaseTables());

    if (count(array_intersect($supported_bases, $base_tables)) > 0) {
      $url = 'https://neutrals.app.ilr.cornell.edu/neutrals.csv';
      $conditions = $event->getConditions();
      $filters = [];

      if (!empty($conditions[1]['conditions'])) {
        foreach ($conditions[1]['conditions'] as $filter) {
          $filters[$filter['field'][0]] = $filter['value'];
        }
      }

      // Fetch the remote csv data into an \SplFileObject. Caching is
      // implemented in the view by the Views Remote Data module.
      try {
        $this->file = new \SplFileObject($url, 'r');
        $this->file->setFlags(\SplFileObject::READ_CSV);
        $headers = $this->file->fgetcsv();
      } catch (\Exception $e) {
        return;
      }

      while (!$this->file->eof()) {
        $next_row = $this->file->fgetcsv();
        $current = array_combine($headers, $next_row);

        if (!empty($filters)) {
          foreach ($filters as $key => $value) {
            // TODO: This is where the operator
            if ($current[$key] === $value) {
              $event->addResult(new ResultRow($current));
            }
          }

          continue;
        }

        $event->addResult(new ResultRow($current));
      }
    }
  }

}
