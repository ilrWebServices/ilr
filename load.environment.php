<?php

/**
 * This file is included very early. See autoload.files in composer.json and
 * https://getcomposer.org/doc/04-schema.md#files
 */

// This file is now included in scripts and plugins. Since the Dotenv class may
// not be installed in some of those cases, we detect and bail here if
// necessary. See https://github.com/drupal-composer/drupal-project/issues/608
if (!class_exists('Dotenv\Dotenv')) {
  return;
}

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

/**
 * Load any .env file. See /.env.example.
 */
$dotenv = Dotenv::createImmutable(__DIR__);
try {
  $dotenv->load();
}
catch (InvalidPathException $e) {
  // Do nothing. Production environments rarely use .env files.
}
