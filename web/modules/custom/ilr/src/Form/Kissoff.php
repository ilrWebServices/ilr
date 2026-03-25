<?php

namespace Drupal\ilr\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\externalauth\ExternalAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides form for searching NetIDs for ILR-related cleanup of users.
 *
 * @internal
 */
class Kissoff extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Authmap service.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected $externalAuth;

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('externalauth.externalauth'),
      $container->get('tempstore.private')
    );
  }

  /**
   * Kissoff form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\externalauth\ExternalAuthInterface $external_auth
   *   The externalauth service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExternalAuthInterface $external_auth, PrivateTempStoreFactory $temp_store_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->externalAuth = $external_auth;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'kissoff';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['netid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NetID'),
      '#description' => $this->t("The employee's NetID."),
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $netid = $form_state->getValue('netid');
    // Look for a User that has the NetID value:
    if ($account = $this->externalAuth->load($netid, 'samlauth')) {
      $this->tempStoreFactory->get('ilr_user_kissoff_action')->set($this->currentUser()->id(), [$account]);
    }
    // Look for a persona that has the NetID value:
    elseif ($employee_persona = $this->entityTypeManager->getStorage('persona')->loadByProperties(['field_netid' => $netid])) {
      $this->tempStoreFactory->get('ilr_user_kissoff_action')->set($this->currentUser()->id(), $employee_persona);
    }
    else {
      $this->messenger()->addError($this->t('No users or personas were found associated with the NetID %netid.', [
        '%netid' => $netid,
      ]));

      return $form_state->setRedirect('ilr.kissoff');
    }
    $form_state->setRedirect('ilr.kissoff_confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $netid = $form_state->getValue('netid');

    if (!preg_match('/^[a-z]{2,3}\d{1,5}$/i', $netid)) {
      $form_state->setError($form['netid'], $this->t('This does not appear to be a vaild NetID.'));
    }
  }

}
