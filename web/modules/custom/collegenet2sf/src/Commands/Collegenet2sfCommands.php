<?php

namespace Drupal\collegenet2sf\Commands;

use Drush\Commands\DrushCommands;
use Drupal\collegenet2sf\CollegeNetToSalesforceProcessor;

/**
 * Drush commandfile for collegenet2sf.
 */
class Collegenet2sfCommands extends DrushCommands {

  /**
   * {@inheritdoc}
   */
  public function __construct(CollegeNetToSalesforceProcessor $collegenet2sf_processor) {
    parent::__construct();
    $this->collegenet2sfProcessor = $collegenet2sf_processor;
  }

  /**
   * Run the CollegeNET to Salesforce Lead processor.
   *
   * @command collegenet2sf:run
   * @aliases cnet2sf
   */
  public function run() {
    $result = $this->collegenet2sfProcessor->run();
    $this->logger()->success(dt('CollegeNET to Salesforce Lead processor was run @result. Check logs for results.', [
      '@result' => $result ? 'successfully' : 'with issues',
    ]));
  }

}
