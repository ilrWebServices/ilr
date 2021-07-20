<?php

namespace Drupal\collegenet2sf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sftp_client\SftpClientInterface;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetches remote CollegeNET data and passes it to a queue.
 */
class CollegeNetDataFetcher extends ControllerBase {

  /**
   * Name of the sftp.client connection.
   *
   * @see settings.php
   */
  const SFTP_CONNECTION_NAME = 'collegenet';

  /**
   * Rexex pattern for matching CollegeNET CSV export file names.
   *
   * @see https://regex101.com/r/Rdt87u/1
   */
  const FILENAME_PATTERN = '/^3004_Graduate_Application___Prospect_\d+\.csv$/';

  /**
   * Max age of files to consider, in case the number of files grows too large.
   */
  const FILE_MAX_AGE_DAYS = 365;

  /**
   * Remote directory where reports are stored.
   */
  const REPORT_DIRECTORY = '/ILR_CRM_Reports';

  /**
   * Constructs this SftpCsvProcessor controller.
   *
   * @param \Drupal\sftp_client\SftpClientInterface $sftp_client
   *   The sftp_client service.
   */
  public function __construct(SftpClientInterface $sftp_client) {
    $this->sftpClient = $sftp_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sftp_client')
    );
  }

  /**
   * Callback for /collegenet2sf/milr/{key}.
   *
   * @todo Throttle this or store processed filenames and prevent duplicate
   * runs.
   */
  public function milrEndpoint() {
    $csv_data = $this->loadData();

    $reader = Reader::createFromString($csv_data);
    $reader->setHeaderOffset(0);

    // @todo Should we also skip rejects?
    $records = Statement::create()
      ->where(fn(array $record) => strpos($record['MJR_PROGRAM_NAME'], 'M.I.L.R.') !== FALSE)
      ->where(fn(array $record) => !empty($record['XACT_ID']))
      ->process($reader);

    $queue = \Drupal::queue('collegenet_lead_queue');

    foreach ($records->getRecords() as $data) {
      // Store the data in a QueueWorker for processing on the next cron run.
      $queue->createItem($data);
    }

    // @todo Log this data file fetch.
    // HTTP 204 is 'No content'.
    return new Response('', 204);
  }

  /**
   * Load CSV data from the SFTP connection.
   *
   * @return string
   *   Unparsed CSV data from the remote file.
   */
  protected function loadData() {
    // Return file_get_contents('/Users/jeff/ILR/Webteam/collegenet import/3004_Graduate_Application___Prospect_20210720070227.csv');
    $this->sftpClient->setSettings(self::SFTP_CONNECTION_NAME);

    // @todo try/catch this
    $files = $this->sftpClient->listFiles(self::REPORT_DIRECTORY);

    $most_recent_export_file = $this->getMostRecent($this->filter($files));

    return $this->sftpClient->readFile(self::REPORT_DIRECTORY . '/' . $most_recent_export_file->getFilename());
  }

  /**
   * Filter CSV files by file name and age.
   *
   * @param \Traversable $files
   *   A list of files from SftpClientInterface::listFiles().
   *
   * @return \Drupal\sftp_client\SftpResource[]|\Generator
   *   The list of files filtered by name and age.
   */
  protected function filter(\Traversable $files) {
    foreach ($files as $path => $resource) {
      if ($resource->isFile() && preg_match(self::FILENAME_PATTERN, $resource->getFilename())) {
        $age_days = (time() - $resource->getModificationTime()) / 60 / 60 / 24;

        if ($age_days < self::FILE_MAX_AGE_DAYS) {
          yield $path => $resource;
        }
      }
    }
  }

  /**
   * Reduce the files array to the most recent file resource.
   *
   * @param \Traversable $files
   *   A list of files from SftpClientInterface::listFiles().
   *
   * @return \Drupal\sftp_client\SftpResource[]|\Generator
   *   The list of files filtered by name and age.
   */
  protected function getMostRecent(\Traversable $files) {
    $high_mark = 0;
    $newest_file = NULL;

    foreach ($files as $path => $resource) {
      if ($resource->getModificationTime() > $high_mark) {
        $high_mark = $resource->getModificationTime();
        $newest_file = $resource;
      }
    }

    return $newest_file;
  }

}
