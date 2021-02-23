<?php
namespace Drupal\ilr\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ProblemInternalLink constraint.
 */
class ProblemInternalLinkValidator extends ConstraintValidator {

  // See also FilterInternalLinks::internalHosts.
  protected $internalHosts = [
    'www.ilr.cornell.edu',
    'd8-edit.ilr.cornell.edu',
    'edit.ilr.cornell.edu',
  ];

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value->isEmpty()) {
      return;
    }

    foreach ($value as $delta => $item) {
      // Parse the raw uri for this item. In testing this can result in several
      // results, documented here to elicidate the tests below. User entered
      // links like '/node/123' or '/current-students/foo' are given the scheme
      // 'internal'. User entered paths beginning with the 'http' or 'https'
      // scheme will include a 'host' key (such as 'd8-edit.ilr.cornell.edu')
      // which can be validated. Finally, linkit values, where the user sees
      // something like 'Unions Role in Lives of Workers (365)', are given the
      // 'entity' scheme and an internal path like '/node/365'. Fortunately for
      // us, those entity paths will be displayed using their path aliases.
      $parts = parse_url($item->uri);

      // URLs with internal (or editor-only) hosts are not allowed.
      if (isset($parts['host']) && in_array($parts['host'], $this->internalHosts)) {
        $this->context->addViolation($constraint->messageInternalHost, [
          '@uri' => $value->uri,
          '%host' => $parts['host'],
        ]);
      }

      // Internal, but not 'entity', links using /node/ID paths are not allowed.
      if (isset($parts['scheme']) && $parts['scheme'] === 'internal' && preg_match('|^/node/\d+|', $parts['path'])) {
        $this->context->addViolation($constraint->messageNodePath, [
          '@uri' => $value->uri,
          '%path' => $parts['path'],
        ]);
      }
    }
  }

}
