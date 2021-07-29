<?php

namespace Drupal\collegenet2sf;

use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Rest\RestException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use League\Csv\Reader;

/**
 * Enhanced Salesforce REST API response.
 *
 * This extends the Salesforce RestResponse set the data from the response body
 * based on content type:
 *
 * - Only decode from JSON if application/json.
 * - Parse CSV data if text/csv. This returns a League\Csv\Reader.
 * - Set the data to the raw response body if the content type is something
 *   else.
 */
class RestResponseEnhanced extends RestResponse {

  /**
   * {@inheritdoc}
   */
  public function __construct(ResponseInterface $response) {
    $this->response = $response;

    // Note that the grandparent constructor is called here, since the parent,
    // RestResponse, always calls handleJsonResponse and that should be run only
    // if the response content-type is application/json. If the inheritance
    // chain of RestResponse changes in the future, this will need to be fixed.
    Response::__construct($response->getStatusCode(), $response->getHeaders(), $response->getBody(), $response->getProtocolVersion(), $response->getReasonPhrase());

    if (strpos($response->getHeaderLine('Content-Type'), 'application/json') === 0) {
      $this->handleJsonResponse();
    }
    elseif (strpos($response->getHeaderLine('Content-Type'), 'text/csv') === 0) {
      $this->handleCsvResponse();
    }
    else {
      $this->handleRawResponse();
    }
  }

  /**
   * Helper function to set data to the csv response.
   *
   * @return $this
   *
   * @throws \Drupal\salesforce\Rest\RestException
   */
  protected function handleCsvResponse() {
    $this->data = '';
    $response_body = $this->getBody()->getContents();
    if (empty($response_body)) {
      return NULL;
    }

    // Allow any exceptions here to bubble up:
    try {
      $reader = Reader::createFromString($response_body);
    }
    catch (\Exception $e) {
      throw new RestException($this, $e->getMessage(), $e->getCode(), $e);
    }

    $this->data = $reader;

    return $this;
  }

  /**
   * Helper function to set data to the raw response.
   *
   * @return $this
   *
   * @throws \Drupal\salesforce\Rest\RestException
   */
  protected function handleRawResponse() {
    $this->data = $this->getBody()->getContents();
    return $this;
  }

}
