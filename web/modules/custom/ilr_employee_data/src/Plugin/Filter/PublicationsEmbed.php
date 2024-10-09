<?php

declare(strict_types=1);

namespace Drupal\ilr_employee_data\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\filter\Attribute\Filter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ilr_employee_data\RemoteDataHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Spatie\SchemaOrg\BaseType;

/**
 * Provides a filter to embed publication items using a custom tag.
 *
 * <ilr-publications data-netid="foo123" data-citation-format="mla" />
 *
 * @internal
 *
 * @under-construction
 */
#[Filter(
  id: "ilr_publications_embed",
  title: new TranslatableMarkup("Embed publications"),
  description: new TranslatableMarkup("Embeds publication items using a custom tag, <code>&lt;ilr-publications&gt;</code>."),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 100,
  settings: [
    "allowed_citation_formats" => [],
  ],
)]
class PublicationsEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RemoteDataHelper $remoteDataHelper,
    protected RendererInterface $renderer,
    protected LoggerChannelFactoryInterface $loggerFactory
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
      $container->get('ilr_employee_data.remote_data_helper'),
      $container->get('renderer'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<ilr-publications') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//ilr-publications[normalize-space(@data-netid)!=""]') as $node) {
      /** @var \DOMElement $node */
      $netid = $node->getAttribute('data-netid');
      $citation_format = $node->getAttribute('data-citation-format') ?: 'mla';
      $publications_data = $this->remoteDataHelper->getPublications($netid);
      $build = [];

      if (empty($publications_data)) {
        $this->loggerFactory->get('ilr_employee_data')->info('No publications data found for: %netid.', [
          '%netid' => $netid,
        ]);

        continue;
      }

      foreach ($publications_data as $pubgroup => $items) {
        $clean_pubgroup = HTML::cleanCssIdentifier($pubgroup);

        $build[$clean_pubgroup] = [
          '#theme' => 'item_list',
          '#title' => $pubgroup,
          '#items' => [],
        ];

        /** @var BaseType $item */
        foreach ($items as $item) {
          $build[$clean_pubgroup]['#items'][] = [
            '#type' => 'component',
            '#component' => 'ilr_employee_data:' . strtolower($item->getType()),
            '#props' => $item->getProperties(),
          ];
        }
      }

      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE): string {
    return (string) $this->t('@todo Provide filter tips here.');
  }

  /**
   * Renders the given render array into the given DOM node.
   *
   * @param array $build
   *   The render array to render in isolation.
   * @param \DOMNode $node
   *   The DOM node to render into.
   * @param \Drupal\filter\FilterProcessResult $result
   *   The accumulated result of filter processing, updated with the metadata
   *   bubbled during rendering.
   */
  protected function renderIntoDomNode(array $build, \DOMNode $node, FilterProcessResult &$result) {
    // We need to render the embedded publications list:
    // - without replacing placeholders, so that the placeholders are
    //   only replaced at the last possible moment. Hence we cannot use
    //   either renderInIsolation() or renderRoot(), so we must use render().
    // - without bubbling beyond this filter, because filters must
    //   ensure that the bubbleable metadata for the changes they make
    //   when filtering text makes it onto the FilterProcessResult
    //   object that they return ($result). To prevent that bubbling, we
    //   must wrap the call to render() in a render context.
    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });
    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, (string) $markup);
  }

  /**
   * Replaces the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected static function replaceNodeContent(\DOMNode &$node, $content) {
    if (strlen($content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

}
