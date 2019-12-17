<?php
/**
 * @file
 * Platform.sh settings.
 */

$platformsh = new \Platformsh\ConfigReader\Config();

if (!$platformsh->inRuntime()) {
  return;
}

// Configure the database.
$creds = $platformsh->credentials('database');
$databases['default']['default'] = [
  'driver' => $creds['scheme'],
  'database' => $creds['path'],
  'username' => $creds['username'],
  'password' => $creds['password'],
  'host' => $creds['host'],
  'port' => $creds['port'],
  'pdo' => [PDO::MYSQL_ATTR_COMPRESS => !empty($creds['query']['compression'])]
];

// Configure the legacy d7 database.
$creds = $platformsh->credentials('drupal7');
$databases['drupal7']['default'] = [
  'driver' => $creds['scheme'],
  'database' => $creds['path'],
  'username' => $creds['username'],
  'password' => $creds['password'],
  'host' => $creds['host'],
  'port' => $creds['port'],
  'pdo' => [PDO::MYSQL_ATTR_COMPRESS => !empty($creds['query']['compression'])]
];

// Enable Redis caching.
if ($platformsh->hasRelationship('redis') && !drupal_installation_attempted() && extension_loaded('redis') && class_exists('Drupal\redis\ClientFactory')) {
  $redis = $platformsh->credentials('redis');

  // Set Redis as the default backend for any cache bin not otherwise specified.
  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['redis.connection']['host'] = $redis['host'];
  $settings['redis.connection']['port'] = $redis['port'];

  // Apply changes to the container configuration to better leverage Redis.
  // This includes using Redis for the lock and flood control systems, as well
  // as the cache tag checksum. Alternatively, copy the contents of that file
  // to your project-specific services.yml file, modify as appropriate, and
  // remove this line.
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

  // Allow the services to work before the Redis module itself is enabled.
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

  // Manually add the classloader path, this is required for the container cache bin definition below
  // and allows to use it without the redis module being enabled.
  $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');

  // Use redis for container cache.
  // The container cache is used to load the container definition itself, and
  // thus any configuration stored in the container itself is not available
  // yet. These lines force the container cache to use Redis rather than the
  // default SQL cache.
  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'redis.factory' => [
        'class' => 'Drupal\redis\ClientFactory',
      ],
      'cache.backend.redis' => [
        'class' => 'Drupal\redis\Cache\CacheBackendFactory',
        'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
      ],
      'cache.container' => [
        'class' => '\Drupal\redis\Cache\PhpRedis',
        'factory' => ['@cache.backend.redis', 'get'],
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
        'arguments' => ['@redis.factory'],
      ],
      'serialization.phpserialize' => [
        'class' => 'Drupal\Component\Serialization\PhpSerialize',
      ],
    ],
  ];
}

// Configure private and temporary file paths.
if (!isset($settings['file_private_path'])) {
  $settings['file_private_path'] = $platformsh->appDir . '/private';
}
if (!isset($settings['file_temp_path'])) {
  $settings['file_temp_path'] = $platformsh->appDir . '/tmp';
}

// Configure the default PhpStorage and Twig template cache directories.
if (!isset($settings['php_storage']['default'])) {
  $settings['php_storage']['default']['directory'] = $settings['file_private_path'];
}
if (!isset($settings['php_storage']['twig'])) {
  $settings['php_storage']['twig']['directory'] = $settings['file_private_path'];
}

// The 'trusted_hosts_pattern' setting allows an admin to restrict the Host header values
// that are considered trusted.  If an attacker sends a request with a custom-crafted Host
// header then it can be an injection vector, depending on how the Host header is used.
// However, Platform.sh already replaces the Host header with the route that was used to reach
// Platform.sh, so it is guaranteed to be safe.  The following line explicitly allows all
// Host headers, as the only possible Host header is already guaranteed safe.
$settings['trusted_host_patterns'] = ['.*'];

// Import variables prefixed with 'd8settings:' into $settings
// and 'd8config:' into $config.
foreach ($platformsh->variables() as $name => $value) {
  $parts = explode(':', $name);
  list($prefix, $key) = array_pad($parts, 3, null);
  switch ($prefix) {
    // Variables that begin with `d8settings` or `drupal` get mapped
    // to the $settings array verbatim, even if the value is an array.
    // For example, a variable named d8settings:example-setting' with
    // value 'foo' becomes $settings['example-setting'] = 'foo';
    case 'd8settings':
    case 'drupal':
      $settings[$key] = $value;
      break;
    // Variables that begin with `d8config` get mapped to the $config
    // array.  Deeply nested variable names, with colon delimiters,
    // get mapped to deeply nested array elements. Array values
    // get added to the end just like a scalar. Variables without
    // both a config object name and property are skipped.
    // Example: Variable `d8config:conf_file:prop` with value `foo` becomes
    // $config['conf_file']['prop'] = 'foo';
    // Example: Variable `d8config:conf_file:prop:subprop` with value `foo` becomes
    // $config['conf_file']['prop']['subprop'] = 'foo';
    // Example: Variable `d8config:conf_file:prop:subprop` with value ['foo' => 'bar'] becomes
    // $config['conf_file']['prop']['subprop']['foo'] = 'bar';
    // Example: Variable `d8config:prop` is ignored.
    case 'd8config':
      if (count($parts) > 2) {
        $temp = &$config[$key];
        foreach (array_slice($parts, 2) as $n) {
          $prev = &$temp;
          $temp = &$temp[$n];
        }
        $prev[$n] = $value;
      }
      break;
  }
}


// Set the project-specific entropy value, used for generating one-time
// keys and such.
$settings['hash_salt'] = $settings['hash_salt'] ?? $platformsh->projectEntropy;

// Set the deployment identifier, which is used by some Drupal cache systems.
$settings['deployment_identifier'] = $settings['deployment_identifier'] ?? $platformsh->treeId;

if ($platformsh->onProduction()) {
  // Enable the config split for production-only modules.
  $config['config_split.config_split.production']['status'] = TRUE;

  // Configure samlauth with production SP settings.
  $config['samlauth.authentication']['idp_entity_id'] = 'https://shibidp.cit.cornell.edu/idp/shibboleth';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://shibidp.cit.cornell.edu/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_x509_certificate'] = 'MIIDSDCCAjCgAwIBAgIVAOZ8NfBem6sHcI7F39sYmD/JG4YDMA0GCSqGSIb3DQEBBQUAMCIxIDAeBgNVBAMTF3NoaWJpZHAuY2l0LmNvcm5lbGwuZWR1MB4XDTA5MTEyMzE4NTI0NFoXDTI5MTEyMzE4NTI0NFowIjEgMB4GA1UEAxMXc2hpYmlkcC5jaXQuY29ybmVsbC5lZHUwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCTURo990uuODo/5ju3GZThcT67K3RXW69jwlBwfn3png75Dhyw9Xa50RFv0EbdfrojH1P19LyfCjubfsm9Z7FYkVWSVdPSvQ0BXx7zQxdTpE9137qj740tMJr7Wi+iWdkyBQS/bCNhuLHeNQor6NXZoBgX8HvLy4sCUb/4v7vbp90HkmP3FzJRDevzgr6PVNqWwNqptZ0vQHSF5D3iBNbxq3csfRGQQyVi729XuWMSqEjPhhkf1UjVcJ3/cG8tWbRKw+W+OIm71k+99kOgg7IvygndzzaGDVhDFMyiGZ4njMzEJT67sEq0pMuuwLMlLE/86mSvuGwO2Qacb1ckzjodAgMBAAGjdTBzMFIGA1UdEQRLMEmCF3NoaWJpZHAuY2l0LmNvcm5lbGwuZWR1hi5odHRwczovL3NoaWJpZHAuY2l0LmNvcm5lbGwuZWR1L2lkcC9zaGliYm9sZXRoMB0GA1UdDgQWBBSQgitoP2/rJMDepS1sFgM35xw19zANBgkqhkiG9w0BAQUFAAOCAQEAaFrLOGqMsbX1YlseO+SM3JKfgfjBBL5TP86qqiCuq9a1J6B7Yv+XYLmZBy04EfV0L7HjYX5aGIWLDtz9YAis4g3xTPWe1/bjdltUq5seRuksJjybprGI2oAv/ShPBOyrkadectHzvu5K6CL7AxNTWCSXswtfdsuxcKo65tO5TRO1hWlr7Pq2F+Oj2hOvcwC0vOOjlYNe9yRE9DjJAzv4rrZUg71R3IEKNjfOF80LYPAFD2Spp36uB6TmSYl1nBmS5LgWF4EpEuODPSmy4sIV6jl1otuyI/An2dOcNqcgu7tYEXLXC8N6DXggDWPtPRdpk96UW45huvXudpZenrcd7A==';
  $config['samlauth.authentication']['strict'] = TRUE;
  $config['samlauth.authentication']['security_authn_requests_sign'] = TRUE;
  $config['samlauth.authentication']['security_assertions_encrypt'] = TRUE;
}
