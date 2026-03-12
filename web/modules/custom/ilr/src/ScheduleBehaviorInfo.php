<?php

namespace Drupal\ilr;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * A small class to hold info about scheduled components.
 */
class ScheduleBehaviorInfo {

  const int PAST = -1;
  const int PRESENT = 0;
  const int FUTURE = 1;

  public int $secondsTillShowOrHide = 0;
  public bool $status = TRUE;
  public int $reason = self::PRESENT;

  public function __construct(
    public readonly ?DrupalDateTime $showOn = NULL,
    public readonly ?DrupalDateTime $hideOn = NULL,
  ) {
    $today = new DrupalDateTime();

    if ($showOn) {
      if ($showOn > $today) {
        $this->secondsTillShowOrHide = $showOn->getTimestamp() - $today->getTimestamp();
        $this->status = FALSE;
        $this->reason = self::FUTURE;
      }
    }

    if (!$this->secondsTillShowOrHide && $hideOn > $today) {
      $this->secondsTillShowOrHide = $hideOn->getTimestamp() - $today->getTimestamp();
    }

    if ($hideOn) {
      if ($hideOn < $today) {
        $this->status = FALSE;
        $this->reason = self::PAST;
      }
    }
  }

}
