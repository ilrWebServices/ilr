#!/usr/bin/env php

<?php
// This will run the load.environment.php from the composer.json `scripts/files`
// section.
require __DIR__ . '/vendor/autoload.php';

try {
  $dbh = new PDO('mysql:host=' . getenv('MYSQL_HOSTNAME') . ';dbname=' . getenv('MYSQL_DATABASE'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'));
} catch (PDOException $e) {
  print "Error!: " . $e->getMessage() . "<br/>";
  die();
}

$definer = '`' . getenv('MYSQL_USER') . '`@`' . getenv('MYSQL_HOSTNAME') . '`';

$get_triggers_stmt = $dbh->prepare('SELECT * FROM INFORMATION_SCHEMA.TRIGGERS WHERE DEFINER = ?');
$get_triggers_stmt->execute(['mysql@%']);

while ($row = $get_triggers_stmt->fetchObject()) {
  $result = $dbh->query('SHOW CREATE TRIGGER ' . $row->TRIGGER_NAME, PDO::FETCH_ASSOC);
  $trigger_def_row = $result->fetch();
  $drop = 'DROP TRIGGER ' . $row->TRIGGER_NAME;
  $create = preg_replace('|^CREATE DEFINER="mysql"@"%"|', "CREATE DEFINER=$definer", $trigger_def_row["SQL Original Statement"]);
  $dbh->exec($drop);
  $dbh->exec($create);
}
