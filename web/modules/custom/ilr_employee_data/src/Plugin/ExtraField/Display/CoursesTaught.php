<?php

namespace Drupal\ilr_employee_data\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\extra_field\Attribute\ExtraFieldDisplay;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[ExtraFieldDisplay(
  id: 'ilr_courses_taught',
  label: new TranslatableMarkup('Courses taught'),
  bundles: ['persona.ilr_employee'],
  visible: TRUE,
)]
class CoursesTaught extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  protected const string BASE_URI = 'https://classes.cornell.edu/api/2.0/';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ClientInterface $client,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $build = [];
    $netid = $entity->field_netid->value;

    if (empty($netid)) {
      return $build;
    }

    $active_rosters = $this->getActiveRosters();
    $course_count = 0;

    if (empty($active_rosters)) {
      return $build;
    }

    $build['rosters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile__class-rosters']],
      '#cache' => ['max-age' => 60 * 60 * 24],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Courses Taught'),
        '#attributes' => ['class' => ['cu-heading']],
      ]
    ];

    foreach ($active_rosters as $roster_id => $roster_name) {
      try {
        $roster_class_data = $this->apiCall("search/classes.json?roster=$roster_id&instructor=$netid");
      }
      catch (\Exception $e) {
        return $build;
      }

      $roster_classes = $roster_class_data['data']['classes'] ?? [];

      if ($roster_classes) {
        $build['rosters'][$roster_id] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['profile__class-roster'],
          ],
        ];

        $build['rosters'][$roster_id]['items'] = [
          '#theme' => 'item_list__class_roster',
          '#title' => $roster_name,
          '#items' => [],
        ];

        // Example https://classes.cornell.edu/browse/roster/FA26/class/AMST/3800.
        $url_template = 'https://classes.cornell.edu/browse/roster/:roster/class/:subject/:catalog_number';

        foreach ($roster_classes as $roster_class) {
          $build['rosters'][$roster_id]['items']['#items'][] = [
            '#type' => 'inline_template',
            '#template' => '<a href="{{ url }}" class="profile__class-roster-item-link"><span class="subject">{{ subject }}</span> <span class="catalog-number">{{ catalog_number }}</span> <span class="title">{{ title }}</span></a>',
            '#context' => [
              'url' => strtr($url_template, [
                ':roster' => $roster_id,
                ':subject' => $roster_class['subject'],
                ':catalog_number' => $roster_class['catalogNbr'],
              ]),
              'subject' => $roster_class['subject'],
              'catalog_number' => $roster_class['catalogNbr'],
              'title' => $roster_class['titleLong'],
            ],
          ];

          $course_count++;
        }
      }
    }

    return $course_count ? $build : [];
  }

  protected function apiCall(string $path): string|array {
    try {
      $response = $this->client->request('GET', $path, ['base_uri' => self::BASE_URI]);
    }
    catch (\Exception $e) {
      return [];
    }

    $data = $response->getBody()->getContents();

    if ($response->getHeaderLine('Content-Type') !== 'application/json') {
      throw new \Exception('Non-JSON response.');
    }

    return json_decode($data, TRUE);
  }

  protected function getActiveRosters(): array {
    $cid = 'ilr_courses_taught:rosters';

    if ($cache_data = \Drupal::cache()->get($cid)) {
      return $cache_data->data;
    }

    try {
      $roster_data = $this->apiCall('config/rosters.json');
    }
    catch (\Exception $e) {
      return [];
    }

    $rosters = [];

    if (isset($roster_data['data']['rosters'])) {
      foreach ($roster_data['data']['rosters'] as $roster) {
        if ($roster['archiveMode'] === 'N') {
          $rosters[$roster['slug']] = $roster['descr'];
        }
      }
    }

    \Drupal::cache()->set($cid, $rosters, time() + 60 * 60 * 24);
    return $rosters;
  }

}
