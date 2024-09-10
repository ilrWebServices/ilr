<?php

namespace Drupal\ilr_employee_data;

use GuzzleHttp\Client;
use SimpleXMLElement;
use Spatie\SchemaOrg\Schema;

/**
 * Fetches remote publications data from external services.
 */
class PublicationsFetcher {

  public function __construct(
    protected Client $client
  ) {}

  /**
   * Get publications for a given person via their netid.
   *
   * @param string $netid
   * @param boolean $published_only
   * @param boolean $public_only
   * @return array
   *   An array of schema.org objects keyed by publication categories (e.g.
   *   News Articles, Books).
   */
  public function getPublications(string $netid, bool $published_only = TRUE, bool $public_only = TRUE): array {
    // Do basic validation of NetID.
    if (preg_match('/^[a-z]{2,4}\d{1,5}$/', $netid) === FALSE) {
      // @todo throw error to prevent stuff?
      return [];
    }

    return $this->getActivityInsightPublications($netid, $published_only, $public_only);
  }

  /**
   * Get publications from Activity Insight for a given netid.
   *
   * @param string $netid
   * @param boolean $published_only
   * @param boolean $public_only
   * @return array
   *   An array of schema.org objects keyed by publication categories (e.g.
   *   News Articles, Books).
   */
  protected function getActivityInsightPublications(string $netid, bool $published_only, bool $public_only): array {
    $cid = 'ai_publication_data:' . $netid;

    if ($cache_data = \Drupal::cache()->get($cid)) {
      $remote_data = $cache_data->data;
    }
    else {
      // Try to fetch publication data from Activity Insight/Watermark/Digital
      // Measures.
      $url = sprintf('https://webservices.digitalmeasures.com/login/service/v4/SchemaData/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations/USERNAME:%s/INTELLCONT', $netid);

      try {
        // @todo gzip this request.
        $response = $this->client->get($url, ['auth' => [getenv('AI_USER'), getenv('AI_PASS')]]);
      }
      catch (\Exception $e) {
        // @todo deal with this. Log it? Throw an error?
        return [];
      }

      $remote_data = $response->getBody()->getContents();
      \Drupal::cache()->set($cid, $remote_data, time() + 60 * 60);
    }

    $ai_person_xml = new SimpleXMLElement($remote_data);
    $data = [];

    foreach ($ai_person_xml->Record->INTELLCONT as $publication) {
      if ($published_only && (string) $publication->STATUS !== 'Published') {
        continue;
      }

      if ($public_only && (string) $publication->PUBLIC_VIEW !== 'Yes') {
        continue;
      }

      if ((string) $publication->CONTYPE === 'Other') {
        continue;
      }

      $publication_group = match((string) $publication->CONTYPE) {
        'Journal Article' => 'Journal Articles',
        'Book, Scholarly' => 'Books',
        'Book Chapter' => 'Book Chapters',
        'Book, Textbook' => 'Textbooks',
        'Book Section' => 'Book Sections',
        'Newspaper' => 'Newspapers',
        'Instructor\'s Manual' => 'Instructor\'s Manuals',
        'Book Review' => 'Book Reviews',
        'Magazine Publication' => 'Magazine Publications',
        'Written Case' => 'Written Cases',
        'Cited Research' => 'Cited Research',
        'Conference Proceeding' => 'Conference Proceedings',
        'Abstract' => 'Abstracts',
        'Research Report' => 'Research Reports',
        'Research Bulletin' => 'Research Bulletins',
        default => (string) $publication->CONTYPE,
      };

      switch ($publication_group) {
        case 'Journal Articles':
          $schemaObject = Schema::scholarlyArticle()
            ->publisher(Schema::organization()->name((string) $publication->JOURNAL->JOURNAL_NAME))
            ->pagination((string) $publication->PAGENUM)
            ->setProperty('x_arxivnum', (string) $publication->ARXIVNUM)
            ->setProperty('x_doi', (string) $publication->DOI)
            ->setProperty('x_pmid', (string) $publication->PMID)
            ->setProperty('x_pmcid', (string) $publication->PMCID);

          if ((string) $publication->PAGENUM && (string) $publication->VOLUME) {
            $schemaObject->isPartOf(
              Schema::publicationIssue()
                ->issueNumber((string) $publication->ISSUE)
                ->isPartOf(
                  Schema::publicationVolume()
                    ->volumeNumber((string) $publication->VOLUME)
                )
            );
          }
          break;

        case 'Books':
        case 'Textbooks':
          $schemaObject = Schema::book()
            ->publisher(Schema::organization()->name((string) $publication->PUBLISHER));
          break;

        case 'Book Chapters':
          $schemaObject = Schema::chapter()
            ->publisher(Schema::organization()->name((string) $publication->PUBLISHER))
            ->title((string) $publication->BOOK_TITLE)
            ->pagination((string) $publication->PAGENUM);
          break;

        case 'Newspapers':
          $schemaObject = Schema::newsArticle()
            ->publisher(Schema::organization()->name((string) $publication->PUBLISHER));
          break;

        case 'Book Reviews':
          $schemaObject = Schema::reviewNewsArticle();
          break;

        case 'Magazine Publications':
          $schemaObject = Schema::article()
            ->publisher(Schema::organization()->name((string) $publication->PUBLISHER))
            ->pagination((string) $publication->PAGENUM);

          if ((string) $publication->PAGENUM && (string) $publication->VOLUME) {
            $schemaObject->isPartOf(
              Schema::publicationIssue()
                ->issueNumber((string) $publication->ISSUE)
                ->isPartOf(
                  Schema::publicationVolume()
                    ->volumeNumber((string) $publication->VOLUME)
                )
            );
          }
          break;

        default:
          $schemaObject = Schema::creativeWork();
      }

      $data[$publication_group][] = $schemaObject;

      $authors = [];
      foreach ($publication->INTELLCONT_AUTH as $author) {
        $authors[] = Schema::person()
          ->givenName((string) $author->FNAME)
          ->familyName((string) $author->LNAME)
          ->additionalName((string) $author->MNAME);
      }

      $publication_date = (string) $publication->PUB_START ?: (string) $publication->SUB_START;

      /** @var \Spatie\SchemaOrg\CreativeWork $schemaObject */
      $schemaObject
        ->name((string) $publication->TITLE[0])
        ->author($authors)
        ->datePublished(new \DateTime($publication_date))
        ->url((string) $publication->WEB_ADDRESS)
        ->setProperty('ai_contype', (string) $publication->CONTYPE)
        ->setProperty('ai_public_view', (string) $publication->PUBLIC_VIEW);
    }

    return $data;
  }

}
