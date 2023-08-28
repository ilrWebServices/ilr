<?php

// $d7_host = 'd7-edit.ilr.cornell.edu';
$d7_host = 'd7.ilr.test';
// $d7_host = 'html.test';

$request_path = $_SERVER['REQUEST_URI'];
$request_scheme = $_SERVER['REQUEST_SCHEME'];
$request_headers = getallheaders();
$request_headers_curl = [
  'Host: ' . $d7_host,
];

foreach ($request_headers as $header_key => $header_val) {
  if (in_array($header_key, ['Content-Type', 'Content-Length', 'Host'])) {
    continue;
  }

  $request_headers_curl[] = $header_key . ': ' . $header_val;
}

// print_r($request_headers); die();
// print_r($request_headers_curl); die();

$d7_paths = [
  '/people',
  '/sites/default/files',
  '/proxytest.php'
];

foreach ($d7_paths as $d7_path) {
  if (strpos($request_path, $d7_path) === 0) {
    $url = $request_scheme . '://' . $d7_host . $request_path;
    // print_r($url);
    // die();
    // header("Content-Type: text/plain");
    // $opts = [
    //   'http' => [
    //     'method' => "GET",
    //     'header' => "Accept-language: en\r\n" .
    //                 "Cookie: foo=bar\r\n"
    //   ]
    // ];

    // $context = stream_context_create($opts);

    // $resource = fopen($url, 'r', false, $context);
    // fpassthru($resource);
    // fclose($resource);
    // exit;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 30,
    ]);

    // header("Content-Type: text/html");
    // header("Content-Description: File Transfer");
    // header("Content-Disposition: attachment; filename=\"$song_name\"");

    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers_curl);

    // curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    //   // if (strpos($header, 'Transfer-Encoding:') === 0) {
    //   //   return 0;
    //   // }
    //   header($header);
    //   return strlen($header);
    // });

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $body) {
      echo $body;
      return strlen($body);
    });

    $response = curl_exec($ch);
    curl_close($ch);

    exit;
  }
}
