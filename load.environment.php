<?php

use Symfony\Component\Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
  $dotenv = new Dotenv();
  $dotenv->usePutenv();
  $dotenv->load(__DIR__ . '/.env');
}
