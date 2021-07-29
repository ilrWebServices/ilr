<?php

namespace Drupal\collegenet2sf;

use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\Rest\RestException;
use GuzzleHttp\Exception\RequestException;

/**
 * Enhanced Salesforce REST API client.
 *
 * This extends the Salesforce RestClient to:
 *
 * - Allow content-types other than application/json. text/csv is an example.
 * - Returns RestResponseEnhanced objects, which don't automatically try to
 *   convert the response from JSON.
 */
class RestClientEnhanced extends RestClient {

  /**
   * {@inheritdoc}
   */
  public function apiCall($path, $params = [], $method = 'GET', $returnObject = FALSE, $contentType = 'application/json') {
    if (!$this->isInit()) {
      throw new RestException(NULL, $this->t('RestClient is not initialized.'));
    }

    if (strpos($path, '/') === 0) {
      $url = $this->authProvider->getInstanceUrl() . $path;
    }
    else {
      $url = $this->authProvider->getApiEndpoint() . $path;
    }

    try {
      $this->response = new RestResponseEnhanced($this->apiHttpRequest($url, $params, $method, $contentType));
    }
    catch (RequestException $e) {
      // RequestException gets thrown for any response status but 2XX.
      $this->response = $e->getResponse();

      // Any exceptions besides 401 get bubbled up.
      if (!$this->response || $this->response->getStatusCode() != 401) {
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if ($this->response->getStatusCode() == 401) {
      // The session ID or OAuth token used has expired or is invalid: refresh
      // token. If refresh_token() throws an exception, or if apiHttpRequest()
      // throws anything but a RequestException, let it bubble up.
      $this->authToken = $this->authManager->refreshToken();
      try {
        $this->response = new RestResponseEnhanced($this->apiHttpRequest($url, $params, $method, $contentType));
      }
      catch (RequestException $e) {
        $this->response = $e->getResponse();
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if (empty($this->response)
    || ((int) floor($this->response->getStatusCode() / 100)) != 2) {
      throw new RestException($this->response, $this->t('Unknown error occurred during API call "@call": status code @code : @reason', [
        '@call' => $path,
        '@code' => $this->response->getStatusCode(),
        '@reason' => $this->response->getReasonPhrase(),
      ]));
    }

    $this->updateApiUsage($this->response);

    if ($returnObject) {
      return $this->response;
    }
    else {
      return $this->response->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function apiHttpRequest($url, $params, $method, $contentType = 'application/json') {
    if (!$this->authToken) {
      throw new \Exception($this->t('Missing OAuth Token'));
    }

    $headers = [
      'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
      'Content-type' => $contentType,
    ];
    $data = NULL;
    if (!empty($params)) {
      if (is_array($params)) {
        $data = $this->json->encode($params);
      }
      else {
        $data = $params;
      }
    }
    return $this->httpRequest($url, $data, $headers, $method);
  }

}
