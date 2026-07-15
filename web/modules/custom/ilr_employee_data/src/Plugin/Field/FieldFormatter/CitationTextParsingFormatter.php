<?php

namespace Drupal\ilr_employee_data\Plugin\Field\FieldFormatter;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Query;
use RenanBr\BibTexParser\Listener;
use RenanBr\BibTexParser\Parser;
use RenanBr\BibTexParser\Processor\NamesProcessor;
use RenanBr\BibTexParser\Processor\TagNameCaseProcessor;
use RenanBr\BibTexParser\Processor\TrimProcessor;

/**
 * Displays a long text field when enabled by a field third-party setting.
 */
#[FieldFormatter(
  id: 'ilr_employee_data_citation_parsing_formatter',
  label: new TranslatableMarkup('Citation parsing formatter'),
  field_types: ['string_long'],
)]
class CitationTextParsingFormatter extends FormatterBase {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected ClientInterface $httpClient
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    if (!$field_definition instanceof ThirdPartySettingsInterface) {
      return FALSE;
    }

    return (bool) $field_definition->getThirdPartySetting('ilr_employee_data', 'enable_citation_parsing_formatter', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements($items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      // Split the value by newlines.
      $citation_text_lines = preg_split("(\r\n?|\n)", trim($item->value));
      dump($item->value);
      dump($citation_text_lines);
      $citations_multipart = [];

      dump(array_filter($citation_text_lines));

      // foreach ($citation_text_lines as $citation_text) {
      //   $citations_multipart[] = [
      //     'name' => 'citations',
      //     'contents' => $citation_text,
      //   ];
      // }

      $params = Query::build([
        'citations' => array_filter($citation_text_lines),
        // 'consolidateCitations' => '2',
        // 'includeRawCitations' => '1'
      ], PHP_QUERY_RFC3986);

      dump($params);
      // die();

      $response = $this->httpClient->request('POST', 'http://localhost:8070/api/processCitationList', [
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded',
          'Accept' => 'application/x-bibtex',
        ],
        'body' => $params,
      ]);

      // dump($citations_text[0]);

      // $response = $this->httpClient->request('POST', 'http://localhost:8070/api/processCitation', [
      //   'form_params' => [
      //     'citations' => $citations_text[0],
      //     'consolidateCitations' => '2',
      //     'includeRawCitations' => '1'
      //   ],
      //   // 'multipart' => $citations_multipart,
      //   'headers' => [
      //     'Accept' => 'application/x-bibtex',
      //   ]
      // ]);

      $bibtex = $response->getBody()->getContents();
      dump($bibtex);

      $listener = new Listener();
      $listener->addProcessor(new TagNameCaseProcessor(CASE_LOWER));
      $listener->addProcessor(new NamesProcessor());
      $listener->addProcessor(new TrimProcessor());

      $parser = new Parser();
      $parser->addListener($listener);

      $parser->parseString($bibtex); // or parseFile('/path/to/file.bib')
      $data = $listener->export();
      dump($data);

      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $item->value,
        '#langcode' => $item->getLangcode(),
      ];
    }

    return $elements;
  }

}
