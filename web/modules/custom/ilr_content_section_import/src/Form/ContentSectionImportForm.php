<?php

namespace Drupal\ilr_content_section_import\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\ilr_content_section_import\SectionImportBatch;
use Drupal\ilr_content_section_import\SectionMenuLinkBatch;

class ContentSectionImportForm extends FormBase {

  /**
   * @var $entityTypeManager \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var $fileSystem \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilr_content_section_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [
      '#attributes' => ['enctype' => 'multipart/form-data'],
    ];

    $options = [];
    $collection_storage = $this->entityTypeManager->getStorage('collection');
    $content_section_collections = $collection_storage->getQuery()
      ->condition('type', 'content_section')
      ->execute();

    foreach ($collection_storage->loadMultiple($content_section_collections) as $collection) {
      $options[$collection->id()] = $collection->label();
    }

    $form['content_section'] = [
      '#type' => 'select',
      '#title' => $this->t('Content section'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['json_data_file'] = array(
      '#type' => 'managed_file',
      '#name' => 'json_data_file',
      '#title' => t('Data file'),
      '#size' => 20,
      '#description' => t('Select a JSON file in the proper format.'),
      '#upload_validators' => [
        'file_validate_extensions' => ['json'],
      ],
      '#upload_location' => 'public://json_import/',
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $import_file = $this->entityTypeManager->getStorage('file')->load($form_state->getValue('json_data_file')[0]);
    $import_file_path = $this->fileSystem->realpath($import_file->getFileUri());
    $rows = json_decode(file_get_contents($import_file_path));
    $content_section = $this->entityTypeManager->getStorage('collection')->load($form_state->getValue('content_section'));

    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Importing content'))
      ->setFinishCallback([SectionImportBatch::class, 'finish'])
      ->setInitMessage(t('Starting import.'))
      ->setProgressMessage(t('Processed node @current of @total.'))
      ->setErrorMessage(t('Section content import has encountered an error'));

    foreach ($rows as $row) {
      $batch_builder->addOperation([SectionImportBatch::class, 'process'], [$row, $content_section]);
      // $batch_builder->addOperation([SectionImportBatch::class, 'process'], [$this->entityTypeManager, $row]);
    }

    batch_set($batch_builder->toArray());

    // Create a second batch to map the menu link stubs.
    $menu_link_batch_builder = (new BatchBuilder())
      ->setTitle(t('Updating menu links'))
      ->setFinishCallback([SectionMenuLinkBatch::class, 'finish'])
      ->setInitMessage(t('Starting update.'))
      ->setProgressMessage(t('Processed node @current of @total.'))
      ->setErrorMessage(t('Section menu link content import has encountered an error'));

    foreach ($rows as $row) {
      $menu_link_batch_builder->addOperation([SectionMenuLinkBatch::class, 'process'], [$row]);
    }

    batch_set($menu_link_batch_builder->toArray());
  }

}
