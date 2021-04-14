<?php

namespace Drupal\ilr;

use Drupal\layout_builder\LayoutEntityHelperTrait;

/**
 * A simple class that exposes some LayoutEntityHelperTrait methods publicly.
 */
class IlrLayoutEntityHelper {
  use LayoutEntityHelperTrait {
    getEntitySections as public;
  }
}
