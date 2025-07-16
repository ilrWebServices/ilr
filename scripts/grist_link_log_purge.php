<?php

use GuzzleHttp\Client;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client([
  'base_uri' => getenv('GRIST_LINK_LOG_DOCUMENT_URL') . '/',
  'headers' => [
    'Authorization' => 'Bearer ' . getenv('GRIST_LINK_LOG_API_TOKEN'),
    'Content-Type' => 'application/json',
  ],
]);

try {
  $expired_logs_sql = [
    'sql' => 'select id from Logs where Date < unixepoch() - 604800',
    'timeout' => 500,
  ];
  $expired_logs_response = $client->post('sql', ['json' => $expired_logs_sql]);
  $expired_logs_result = json_decode($expired_logs_response->getBody()->getContents());
  $expired_log_ids = [];

  foreach ($expired_logs_result->records as $record) {
    $expired_log_ids[] = $record->fields->id;
  }

  $delete_result = $client->post('tables/Logs/data/delete', [
    'json' => $expired_log_ids
  ]);

  echo count($expired_log_ids) . ' log entries removed.' . PHP_EOL;
}
catch (\Exception $e) {
  echo $e->getMessage();
  exit(1);
}
