<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_api_base\Functional;

use Drupal\Core\Url;

/**
 * Tests menu link form.
 *
 * @group helfi_api_base
 */
class MenuLinkFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'menu_link_content',
    'menu_ui',
    'remote_entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->placeBlock('system_menu_block:main', ['id' => 'main-menu']);

    // Enable translation for the given entity type and ensure the change is
    // picked up.
    foreach (['menu_link_content', 'remote_entity_test'] as $type) {
      \Drupal::service('content_translation.manager')->setEnabled($type, $type, TRUE);
    }
    $this->rebuildContainer();
  }

  /**
   * Gets the langcode.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $id
   *   The entity id.
   *
   * @return \Drupal\Core\Url
   *   The edit route.
   */
  private function getEditRoute(string $langcode, string $id) : Url {
    return Url::fromRoute('entity.remote_entity_test.edit_form', [
      'remote_entity_test' => $id,
    ], ['query' => ['language' => $langcode]]);
  }

  /**
   * Asserts that fields have expected values.
   *
   * @param string $langcode
   *   The langcode.
   */
  private function assertFieldValues(string $langcode) : void {
    $this->assertSession()->fieldValueEquals('menu[enabled]', '1');
    $this->assertSession()->fieldValueEquals('menu[title]', 'Test title ' . $langcode);
    $this->assertSession()->fieldValueEquals('menu[published]', '1');
  }

  /**
   * Tests the menu link form.
   */
  public function testMenuLinkForm() : void {
    $storage = \Drupal::entityTypeManager()
      ->getStorage('remote_entity_test');

    $account = $this->createUser([
      'administer remote entities',
      'administer menu',
    ]);
    $this->drupalLogin($account);

    $entity = $storage->create(['id' => 1, 'name' => 'Test en'])
      ->addTranslation('fi', ['name' => 'Test fi']);
    $entity->save();

    foreach (['en', 'fi'] as $langcode) {
      $this->drupalGet($this->getEditRoute($langcode, $entity->id()));
      // Menu link should be disabled by default.
      $this->assertSession()->checkboxNotChecked('menu[enabled]');

      $this->submitForm([
        'menu[enabled]' => 1,
        'menu[title]' => 'Test title ' . $langcode,
      ], 'Save');

      // Make sure menu link is show in edit form.
      $this->drupalGet($this->getEditRoute($langcode, $entity->id()));
      $this->assertFieldValues($langcode);
    }

    // Test forms again to make sure we didn't just override the same
    // menu link all over again.
    foreach (['en', 'fi'] as $langcode) {
      // Make sure menu link is show in edit form.
      $this->drupalGet($this->getEditRoute($langcode, $entity->id()));
      $this->assertFieldValues($langcode);
      $this->assertSession()->linkExists('Test title ' . $langcode);

      $storage->resetCache([$entity->id()]);
      // Make sure menu link is referenced in entity.
      $menuLink = $storage->load($entity->id())
        ->getTranslation($langcode)
        ->get('menu_link')
        ->entity
        ->getTranslation($langcode);

      $this->assertEquals('Test title ' . $langcode, $menuLink->getTitle());
    }

    // Make sure we can delete menu link translations by disabling them.
    $this->drupalGet($this->getEditRoute('fi', $entity->id()));
    $this->submitForm([
      'menu[enabled]' => 0,
    ], 'Save');

    $this->drupalGet($this->getEditRoute('fi', $entity->id()));
    $this->assertSession()->checkboxNotChecked('menu[enabled]');
    $this->assertSession()->linkNotExists('Test title fi');

    // Make sure english menu link still exists.
    $storage->resetCache([$entity->id()]);
    $menuLink = $storage->load($entity->id())
      ->getTranslation('en')
      ->get('menu_link')
      ->entity;
    $this->assertEquals('Test title en', $menuLink->getTitle());

    // Re-add finnish menu link and remove the default translation.
    $this->drupalGet($this->getEditRoute('fi', $entity->id()));
    $this->submitForm([
      'menu[enabled]' => 1,
      'menu[title]' => 'Test title fi',
    ], 'Save');
    $this->drupalGet($this->getEditRoute('en', $entity->id()));
    $this->submitForm([
      'menu[enabled]' => 0,
    ], 'Save');

    // Make sure both menu links are removed.
    foreach (['fi', 'en'] as $langcode) {
      $this->drupalGet($this->getEditRoute($langcode, $entity->id()));
      $this->assertSession()->checkboxNotChecked('menu[enabled]');
    }
  }

}
