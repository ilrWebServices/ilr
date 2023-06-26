<?php

namespace Drupal\ilr\Entity;

use Drupal\node\NodeInterface;

interface CertificateNodeInterface extends NodeInterface {

  /**
   * Get the course_certificate nodes for this certificate.
   *
   * These are used in a computed field to get a list of courses for this
   * certificate.
   *
   * @param string $required
   *   The requirement status of the course for this certificate. Can be either
   *   'Required' or 'Elective'.
   */
  public function getCourseCertificates($required = '');

}
