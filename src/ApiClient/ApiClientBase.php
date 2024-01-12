<?php

declare(strict_types=1);

namespace Drupal\helfi_api_base\ApiClient;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\helfi_api_base\Cache\CacheKeyTrait;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Utils;
use Psr\Log\LoggerInterface;

/**
 * Base class for services that fetch data from HTTP API.
 *
 * Provided functionality include caching and fixtures for local usage.
 */
abstract class ApiClientBase {

  use CacheKeyTrait;

  /**
   * Whether to bypass cache or not.
   *
   * @var bool
   */
  private bool $bypassCache = FALSE;

  /**
   * The previous exception.
   *
   * @var \Exception|null
   */
  private ?\Exception $previousException = NULL;

  /**
   * Construct an instance.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param array $defaultOptions
   *   Default request options.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    protected readonly TimeInterface $time,
    protected readonly EnvironmentResolverInterface $environmentResolver,
    protected readonly LoggerInterface $logger,
    private readonly array $defaultOptions = [],
  ) {
  }

  /**
   * Allow cache to be bypassed.
   *
   * @return $this
   *   The self.
   */
  public function withBypassCache() : self {
    $instance = clone $this;
    $instance->bypassCache = TRUE;
    return $instance;
  }

  /**
   * Gets the default request options.
   *
   * @param string $environmentName
   *   Environment name.
   * @param array $options
   *   The optional options.
   *
   * @return array
   *   The request options.
   */
  protected function getRequestOptions(string $environmentName, array $options = []) : array {
    // Hardcode cURL options.
    // Curl options are keyed by PHP constants so there is no easy way to
    // define them in yaml files yet. See: https://www.drupal.org/node/3403883
    $default = $this->defaultOptions + [
      'curl' => [CURLOPT_TCP_KEEPALIVE => TRUE],
    ];

    if ($environmentName === 'local') {
      // Disable SSL verification in local environment.
      $default['verify'] = FALSE;
    }

    return array_merge_recursive($options, $default);
  }

  /**
   * Makes the HTTP request internally.
   *
   * @param string $method
   *   Request method.
   * @param string $url
   *   The endpoint in the instance.
   * @param array $options
   *   Body for requests.
   * @param string|null $fixture
   *   Replace failed response from this file in local environment.
   *
   * @return \Drupal\helfi_api_base\ApiClient\ApiResponse
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function makeRequest(
    string $method,
    string $url,
    array $options = [],
    string $fixture = NULL,
  ): ApiResponse {
    $activeEnvironmentName = $this->environmentResolver
      ->getActiveEnvironment()
      ->getEnvironmentName();

    $options = $this->getRequestOptions($activeEnvironmentName, $options);

    try {
      if ($this->previousException instanceof \Exception) {
        // Fail any further request instantly after one failed request, so we
        // don't block the rendering process and cause the site to time-out.
        throw $this->previousException;
      }

      $response = $this->httpClient->request($method, $url, $options);
    }
    catch (\Exception $e) {
      if ($e instanceof GuzzleException) {
        $this->previousException = $e;
      }

      // Serve mock data in local environments if requests fail.
      if (
        $fixture &&
        ($e instanceof ClientException || $e instanceof ConnectException) &&
        $activeEnvironmentName === 'local'
      ) {
        $this->logger->warning(
          sprintf('Request failed: %s. Mock data is used instead.', $e->getMessage())
        );

        return ApiFixture::requestFromFile($fixture);
      }

      $this->logger->error('Request failed with error: ' . $e->getMessage());

      throw $e;
    }

    return new ApiResponse(Utils::jsonDecode($response->getBody()->getContents()));
  }

  /**
   * Gets the cached data for given response.
   *
   * @param string $key
   *   The  cache key.
   * @param callable $callback
   *   The callback to handle requests.
   *
   * @return \Drupal\helfi_api_base\ApiClient\CacheValue|null
   *   The cache or null.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function cache(string $key, callable $callback) : ?CacheValue {
    $exception = new TransferException();
    $value = ($cache = $this->cache->get($key)) ? $cache->data : NULL;

    // Attempt to re-fetch the data in case cache does not exist, cache has
    // expired, or bypass cache is set to true.
    if (
      ($value instanceof CacheValue && $value->hasExpired($this->time->getRequestTime())) ||
      $this->bypassCache ||
      $value === NULL
    ) {
      try {
        $value = $callback();
        $this->cache->set($key, $value, tags: $value->tags);
        return $value;
      }
      catch (GuzzleException $e) {
        // Request callback failed. Catch the exception, so we can still use
        // stale cache if it exists.
        $exception = $e;
      }
    }

    if ($value instanceof CacheValue) {
      return $value;
    }

    // We should only reach this if:
    // 1. Cache does not exist ($value is NULL).
    // 2. API request fails, and we cannot re-populate the cache (caught the
    // exception).
    throw $exception;
  }

}
