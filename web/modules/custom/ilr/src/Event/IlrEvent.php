<?php

namespace Drupal\ilr\Event;

class IlrEvent {

  public function __construct(
    public string $title,
    public string|\DateTime $event_start,
    public string|\DateTime $event_end,
    public array|object $object
  ) {}

}
