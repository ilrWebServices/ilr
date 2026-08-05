<?php

declare(strict_types=1);

namespace Drupal\ilr_unified_programs;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @todo Add class description.
 */
final class UnifiedProgramsManager implements UnifiedProgramsManagerInterface {

  /**
   * Constructs an UnifiedProgramsManager object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function doSomething(): void {
    // @todo Place your code here.
  }

}
