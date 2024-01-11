<?php

declare(strict_types = 1);

namespace Drupal\helfi_api_base\ApiClient;

/**
 * A value object to store API responses.
 */
final readonly class ApiResponse {

  /**
   * Constructs a new instance.
   *
   * @param array|object $data
   *   The response.
   */
  public function __construct(
    public array|object $data
  ) {
  }

}
