<?php

namespace Drupal\collegenet2sf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\sftp_client\SftpClientInterface;
use Drupal\sftp_client\Exception\SftpException;
use Drupal\sftp_client\Exception\SftpLoginException;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetches remote CollegeNET data and passes it to a queue.
 */
class CollegeNetDataFetcher extends ControllerBase {

  use LoggerChannelTrait;

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
   * User defined exception to prevent items from being added to the queue.
   */
  const INTERNAL_EXCEPTION = 1;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs this SftpCsvProcessor controller.
   *
   * @param \Drupal\sftp_client\SftpClientInterface $sftp_client
   *   The sftp_client service.
   */
  public function __construct(SftpClientInterface $sftp_client) {
    $this->sftpClient = $sftp_client;
    $this->logger = $this->getLogger('collegenet lead');
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
    try {
      $csv_data = $this->loadData();
    }
    catch (\Exception $e) {
      if ($e->getCode() === self::INTERNAL_EXCEPTION) {
        $this->logger->info('CollegeNet file not processed: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
      else {
        $this->logger->error('CollegeNet data load error: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
      return new Response('', 204);
    }

    try {
      $reader = Reader::createFromString($csv_data);
      $reader->setHeaderOffset(0);

      // @todo Should we also skip rejects?
      $records = Statement::create()
        ->where(fn(array $record) => !empty($record['XACT_ID']))
        ->process($reader);
    }
    catch (\Exception $e) {
      $this->logger->error('CollegeNet data CSV read error: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new Response('', 204);
    }

    $queue = \Drupal::queue('collegenet_lead_queue');

    if ($records->count() === 0) {
      $this->logger->info('CollegeNet record count is zero after filtering.');
    }

    foreach ($records->getRecords() as $data) {
      // Store the data in a QueueWorker for processing on the next cron run.
      $queue->createItem($data);
    }

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
    // return file_get_contents('/Users/jeff/ILR/Webteam/collegenet import/3004_Graduate_Application___Prospect_20210720070227.csv');
    $this->sftpClient->setSettings(self::SFTP_CONNECTION_NAME);

    $files = $this->sftpClient->listFiles(self::REPORT_DIRECTORY);

    if ($most_recent_export_file = $this->getMostRecent($this->filter($files))) {
      // Log this data file fetch.
      $this->logger->info('CollegeNet data fetch successful for %filename.', [
        '%filename' => $most_recent_export_file->getFilename(),
      ]);

      $data = $this->sftpClient->readFile(self::REPORT_DIRECTORY . '/' . $most_recent_export_file->getFilename());

      if (empty($data)) {
        throw new \Exception('Empty file retrieved from CollegeNet: ' . $most_recent_export_file->getFilename(), self::INTERNAL_EXCEPTION);
      }

      return $data;
    }
    else {
      throw new \Exception('Unable to retrieve most recent file from CollegeNet.', self::INTERNAL_EXCEPTION);
    }
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
