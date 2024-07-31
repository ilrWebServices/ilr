<?php

namespace Drupal\ilr_outreach_registration;

class EventOption {

  public function __construct(
    public $label,
    public $suffix = '',
    public $disabled = FALSE
  ) {}

}
