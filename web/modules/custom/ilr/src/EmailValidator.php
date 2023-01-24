<?php

namespace Drupal\ilr;

use Egulias\EmailValidator\EmailValidator as EmailValidatorUtility;
use Drupal\Component\Utility\EmailValidatorInterface;
use Egulias\EmailValidator\Validation\EmailValidation;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;

/**
 * Validates email addresses.
 *
 * Replaces \Drupal\Component\Utility\EmailValidator.
 */
class EmailValidator extends EmailValidatorUtility implements EmailValidatorInterface {

  /**
   * Validates an email address.
   *
   * @param string $email
   *   A string containing an email address.
   * @param \Egulias\EmailValidator\Validation\EmailValidation|null $email_validation
   *   This argument is ignored. If it is supplied an error will be triggered.
   *   See https://www.drupal.org/node/2997196.
   *
   * @return bool
   *   TRUE if the address is valid.
   */
  public function isValid($email, EmailValidation $email_validation = NULL) {
    if ($email_validation) {
      throw new \BadMethodCallException('Calling \Drupal\ilr\EmailValidator::isValid() with the second argument is not supported. See https://www.drupal.org/node/2997196');
    }

    // This is where we differ from Drupal\Component\Utility\EmailValidator.
    // This is done because the `RFCValidation` that core uses allows addresses
    // like `test@example` which, while valid, cause issues with internet-routed
    // email. In addition to `RFCValidation`, the `DNSCheckValidation` is used.
    $validations = new MultipleValidationWithAnd([
      new RFCValidation(),
      new DNSCheckValidation(),
    ]);

    return parent::isValid($email, $validations);
  }

}
