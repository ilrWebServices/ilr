<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;

/**
 * Edit this file to reflect your organization's needs.
 */
class PolicyCommands extends DrushCommands {

  /**
   * Prevent catastrophic braino. Note that this file has to be local to the
   * machine that initiates the sql:sync command.
   *
   * @hook validate sql:sync
   *
   * @throws \Exception
   */
  public function sqlSyncValidate(CommandData $commandData) {
    if ($commandData->input()->getArgument('target') == '@prod') {
      throw new \Exception(dt('Per !file, you may never overwrite the production database.', ['!file' => __FILE__]));
    }
  }

  /**
   * Limit rsync operations to production site.
   *
   * @hook validate core:rsync
   *
   * @throws \Exception
   */
  public function rsyncValidate(CommandData $commandData) {
    if (preg_match("/^@prod/", $commandData->input()->getArgument('target'))) {
      throw new \Exception(dt('Per !file, you may never rsync to the production site.', ['!file' => __FILE__]));
    }
  }

  /**
   * Add a policy to require `--partial` to `config-import`.
   *
   * @hook validate config:import
   */
  public function configImportValidate(CommandData $commandData) {
    if (getenv('DRUSH_POLICY_IGNORE') === '1') {
      $this->policyIgnoreMessage();
      return;
    }

    if ($commandData->input()->getOption('partial') === FALSE) {
      throw new \Exception(dt('Per !file, you must use `--partial` when importing configuration. This will prevent the deletion of configuration added to production (e.g. custom menus).', ['!file' =>  __FILE__]));
    }
  }

  protected function policyIgnoreMessage() {
    $this->output()->writeln(PHP_EOL . '<bg=yellow;fg=black;options=bold> NOTE: Drush policy is being ignored via the DRUSH_POLICY_IGNORE environment variable. Proceed with caution! </>'. PHP_EOL);
  }

}
