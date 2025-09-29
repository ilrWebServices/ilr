<?php declare(strict_types=1);

namespace Drupal\ilr\Logger\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class GristLinkLog extends AbstractProcessingHandler {

    public function __construct(
      private string $gristDocument,
      private string $gristApiToken
    ) {}

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void {
      $data = [];
      $data['records'][]['fields'] = [
        "Type" => $record->channel,
        "Severity" => $record->level->value,
        "Date" => (string) $record->datetime,
        "Message" => $record->message,
      ];

      try {
        \Drupal::httpClient()->request('POST', $this->gristDocument . '/tables/Logs/records', [
          'headers' => [
            'Authorization' => 'Bearer ' . $this->gristApiToken
          ],
          'json' => $data
        ]);
      }
      catch (\Exception $e) {
        // Do nothing.
      }
    }

}
