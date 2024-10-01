<?php

namespace Drupal\ilr_employee_data;

use GuzzleHttp\Client;
use SimpleXMLElement;
use Spatie\SchemaOrg\Schema;

/**
 * Fetches and transforms remote data from external services.
 */
class RemoteDataHelper {

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
    if (!$this->validateNetid($netid)) {
      return [];
    }

    return $this->getActivityInsightPublications($netid, $published_only, $public_only);
  }

  /**
   * Get awards and honors for a given person via their netid.
   *
   * @param string $netid
   * @param boolean $public_only
   *
   * @return \Spatie\SchemaOrg\EducationalOccupationalCredential[]
   *   An array of \Spatie\SchemaOrg\EducationalOccupationalCredential objects.
   */
  public function getAwards(string $netid, bool $public_only = TRUE): array {
    if (!$this->validateNetid($netid)) {
      return [];
    }

    return $this->getActivityInsightAwards($netid, $public_only);
  }

  /**
   * Get professional activities for a given person via their netid.
   *
   * @param string $netid
   * @param boolean $public_only
   *
   * @return \Spatie\SchemaOrg\Event[]
   *   An array of \Spatie\SchemaOrg\Event objects.
   */
  public function getActivities(string $netid, bool $public_only = TRUE): array {
    if (!$this->validateNetid($netid)) {
      return [];
    }

    return $this->getActivityInsightActivities($netid, $public_only);
  }

  protected function validateNetid(string $netid): bool {
    // Do basic validation of NetID.
    return (bool) preg_match('/^[a-z]{2,4}\d{1,5}$/', $netid);
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
          $doi = preg_replace('/https?:\/\/dx\.doi\.org\//', '', (string) $publication->DOI);

          $schemaObject = Schema::scholarlyArticle()
            ->publisher(Schema::organization()->name((string) $publication->JOURNAL->JOURNAL_NAME))
            ->pagination((string) $publication->PAGENUM)
            ->setProperty('x_arxivnum', (string) $publication->ARXIVNUM)
            ->setProperty('x_doi', $doi)
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

  /**
   * Get awards and honors from Activity Insight for a given netid.
   *
   * @param string $netid
   * @param boolean $public_only
   *
   * @return \Spatie\SchemaOrg\EducationalOccupationalCredential[]
   *   An array of \Spatie\SchemaOrg\EducationalOccupationalCredential objects.
   */
  protected function getActivityInsightAwards(string $netid, bool $public_only): array {
    $cid = 'ai_award_data:' . $netid;

    if ($cache_data = \Drupal::cache()->get($cid)) {
      $remote_data = $cache_data->data;
    }
    else {
      // Try to fetch award data from Activity Insight/Watermark/Digital
      // Measures.
      $url = sprintf('https://webservices.digitalmeasures.com/login/service/v4/SchemaData/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations/USERNAME:%s/AWARDHONOR', $netid);

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

    foreach ($ai_person_xml->Record->AWARDHONOR as $award) {
      if ($public_only && (string) $award->PUBLIC_VIEW !== 'Yes') {
        continue;
      }

      $data[] = Schema::educationalOccupationalCredential()
        ->name((string) $award->NAME)
        ->publisher(Schema::organization()->name((string) $award->ORG))
        ->datePublished(new \DateTime((string) $award->START_START));
    }

    return $data;
  }

  /**
   * Get activities from Activity Insight for a given netid.
   *
   * @param string $netid
   * @param boolean $public_only
   *
   * @return \Spatie\SchemaOrg\Event[]
   *   An array of \Spatie\SchemaOrg\Event objects.
   */
  protected function getActivityInsightActivities(string $netid, bool $public_only): array {
    $cid = 'ai_activity_data:' . $netid;

    if ($cache_data = \Drupal::cache()->get($cid)) {
      $remote_data = $cache_data->data;
    }
    else {
      // Try to fetch activity data from Activity Insight/Watermark/Digital
      // Measures.
      $url = sprintf('https://webservices.digitalmeasures.com/login/service/v4/SchemaData/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations/USERNAME:%s/PRESENT', $netid);

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
    $tz = new \DateTimeZone('UTC');

    foreach ($ai_person_xml->Record->PRESENT as $activity) {
      if ($public_only && (string) $activity->PUBLIC_VIEW !== 'Yes') {
        continue;
      }

      $data[] = Schema::event()
        ->name((string) $activity->TITLE)
        ->organizer(Schema::organization()->name((string) $activity->ORG))
        ->superEvent(Schema::event()->name((string) $activity->NAME))
        ->location((string) $activity->LOCATION)
        ->startDate(new \DateTime((string) $activity->DATE_START, $tz))
        ->endDate(new \DateTime((string) $activity->DATE_END, $tz));
    }

    return $data;
  }

}
