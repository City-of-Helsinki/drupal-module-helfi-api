<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_api_base\Unit\Azure\PubSub;

use Drupal\Tests\UnitTestCase;
use Drupal\helfi_api_base\Azure\PubSub\Settings;
use Drupal\helfi_api_base\Azure\PubSub\SettingsFactory;
use Drupal\helfi_api_base\Vault\Json;
use Drupal\helfi_api_base\Vault\VaultManager;

/**
 * @coversDefaultClass \Drupal\helfi_api_base\Azure\PubSub\SettingsFactory
 * @group helfi_api_base
 */
class SettingsTest extends UnitTestCase {

  /**
   * @covers \Drupal\helfi_api_base\Azure\PubSub\Settings::__construct
   * @covers ::create
   * @covers ::__construct
   * @covers \Drupal\helfi_api_base\Vault\VaultManager::__construct
   * @covers \Drupal\helfi_api_base\Vault\VaultManager::get
   * @covers \Drupal\helfi_api_base\Vault\Json::__construct
   * @covers \Drupal\helfi_api_base\Vault\Json::data
   * @dataProvider settingsData
   */
  public function testSettings(array $values, array $expectedValues) : void {
    $vaultManager = new VaultManager([
      new Json('pubsub', json_encode($values)),
    ]);
    $sut = new SettingsFactory($vaultManager);
    $settings = $sut->create();
    $this->assertInstanceOf(Settings::class, $settings);
    $this->assertSame($expectedValues['hub'], $settings->hub);
    $this->assertSame($expectedValues['group'], $settings->group);
    $this->assertSame($expectedValues['endpoint'], $settings->endpoint);
    $this->assertSame($expectedValues['access_key'], $settings->accessKey);
  }

  /**
   * @covers \Drupal\helfi_api_base\Azure\PubSub\Settings::__construct
   * @covers ::create
   * @covers ::__construct
   * @covers \Drupal\helfi_api_base\Vault\VaultManager::__construct
   * @covers \Drupal\helfi_api_base\Vault\VaultManager::get
   */
  public function testEmptySettings() : void {
    $vaultManager = new VaultManager([]);
    $sut = new SettingsFactory($vaultManager);
    $settings = $sut->create();
    $this->assertInstanceOf(Settings::class, $settings);
    $this->assertSame('', $settings->hub);
    $this->assertSame('', $settings->group);
    $this->assertSame('', $settings->endpoint);
    $this->assertSame('', $settings->accessKey);
  }

  /**
   * A data provider.
   *
   * @return array[]
   *   The data.
   */
  public function settingsData() : array {
    $values = [
      [
        [
          'hub' => 'hub',
          'group' => 'group',
          'endpoint' => 'endpoint',
          'access_key' => '123',
          'random_key' => '321',
        ],
        [
          'hub' => 'hub',
          'group' => 'group',
          'endpoint' => 'endpoint',
          'access_key' => '123',
        ],
      ],
    ];
    // Make sure invalid values fallback to empty string.
    foreach ([FALSE, NULL, ''] as $value) {
      $values[] = [
        [
          'hub' => $value,
          'group' => $value,
          'endpoint' => $value,
          'access_key' => $value,
        ],
        [
          'hub' => '',
          'group' => '',
          'endpoint' => '',
          'access_key' => '',
        ],
      ];
    }
    return $values;
  }

}
