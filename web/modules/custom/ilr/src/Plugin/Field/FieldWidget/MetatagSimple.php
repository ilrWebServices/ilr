<?php

namespace Drupal\ilr\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\metatag\MetatagManagerInterface;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\metatag\Plugin\Field\FieldWidget\MetatagFirehose;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Advanced widget for metatag field.
 *
 * @FieldWidget(
 *   id = "metatag_simple",
 *   label = @Translation("ILR simple meta tags form"),
 *   field_types = {
 *     "metatag"
 *   }
 * )
 */
class MetatagSimple extends MetatagFirehose implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('metatag.manager'),
      $container->get('plugin.manager.metatag.tag'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, MetatagManagerInterface $manager, MetatagTagPluginManager $plugin_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $manager, $plugin_manager, $config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];

    // Retrieve the values for each metatag from the serialized array.
    $values = [];

    if (!empty($item->value)) {
      $values = $this->dataDecode($item->value);
    }

    // Make sure that this variable is always an array to avoid problems when
    // unserializing didn't work correctly and it as returned as FALSE.
    // @see https://www.php.net/unserialize
    if (!is_array($values)) {
      $values = [];
    }

    $element = [
      "#title" => "Metatags",
      "#title_display" => "before",
      "#description" => "",
      "#field_parents" => [],
      "#required" => false,
      "#delta" => 0,
      "#weight" => 0,
      "#type" => "details",
    ];

    $element['advanced'] = [
      "#type" => "container",
    ];

    $element['advanced']['robots'] = [
      '#type' => 'checkboxes',
      '#options' => [
        'noindex' => $this->t("Prevent search engines from indexing this content"),
      ],
      '#required' => FALSE,
      '#default_value' => ['noindex' => isset(($values['robots'])) ? $values['robots'] : ''],
    ];

    $element['#group'] = 'advanced';

    return $element;
  }

  /**
   * Decode the different versions of encoded values supported by Metatag.
   *
   * @see metatag_data_decode().
   *
   * @param string $string
   *   The string to decode.
   *
   * @return array
   *   A Metatag values array.
   */
  protected function dataDecode($string): array {
    $data = [];

    // Serialized arrays from Metatag v1.
    if (substr($string, 0, 2) === 'a:') {
      $data = @unserialize($string, ['allowed_classes' => FALSE]);
    }

    // Encoded JSON from Metatag v2.
    elseif (substr($string, 0, 2) === '{"') {
      // @todo Handle non-array responses.
      $data = Json::decode($string);
    }

    // This is expected to be an array, so if it isn't then convert it to one.
    if (!is_array($data)) {
      $data = [];
    }

    return $data;
  }
}
