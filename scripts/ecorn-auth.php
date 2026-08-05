<?php

/**
 * Test script for eCornell API.
 *
 * Example use:
 *
 * $ php ./scripts/ecorn-auth.php "catalog?include=titles"
 *
 * $ php ./scripts/ecorn-auth.php "certificate/ILRHRC18?include=sections&include=titles"
 */

require __DIR__ . '/../vendor/autoload.php';

$url_base = getenv('ECORNELL_API_URL_BASE');
$url = $url_base . $argv[1];
$client_code = getenv('ECORNELL_API_CLIENT_CODE');
$secret = getenv('ECORNELL_API_SECRET');
$timestamp = time() * 1000;
$auth_hash = md5(preg_replace('|\?.*$|', '', $argv[1]) . $client_code . $timestamp . $secret);

echo <<<EOL
@url = $url
@timestamp = $timestamp
@auth_hash = $auth_hash

EOL;

// Create context with headers
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            "Authorization: $client_code.$timestamp.$auth_hash",
            'Content-Type: application/json',
            // 'User-Agent: PHP HTTP Client'
        ]
    ]
]);

// Make the request
$response = file_get_contents($url, false, $context);

// Check for errors
if ($response === false) {
    echo "Error: Unable to fetch data\n";
} else {
    // Process the response
    $data = json_decode($response, true);
    print_r($data);
}
