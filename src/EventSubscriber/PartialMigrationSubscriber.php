<?php

declare(strict_types = 1);

namespace Drupal\helfi_api_base\EventSubscriber;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolve the state of the migration.
 */
final class PartialMigrationSubscriber implements EventSubscriberInterface {

  public const PARTIAL_MIGRATE_KEY = 'partial_migrate_state';

  public const PARTIAL_MIGRATE_LAST_FULL_KEY = 'partial_migrate_previous_full';

  public const PARTIAL_MIGRATE_INTERVAL = 604800;

  /**
   * State collection.
   *
   * @var Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * PartialMigrationSubscriber constructor.
   *
   * @param \Drupal\Core\State\KeyValueFactoryInterface $key_value_factory
   *   Key value factory.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory) {
    $this->state = $key_value_factory->get('state');
  }

  /**
   * Resolve whether to run full or partial TPR migration as next migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate event.
   */
  public function updateMigrationState(MigrateImportEvent $event) {
    $migrationStateKey = self::PARTIAL_MIGRATE_KEY . '_' . $event->getMigration()->id();
    $migrationLastFullTimeKey = self::PARTIAL_MIGRATE_LAST_FULL_KEY . '_' . $event->getMigration()->id();

    $lastFullMigrate = $this->state->get($migrationLastFullTimeKey);
    if (is_null($lastFullMigrate)) {
      $this->scheduleFullMigration($migrationStateKey, $migrationLastFullTimeKey);
      return;
    }

    $timeDifference = time() - (int) $lastFullMigrate;
    if ($timeDifference > self::PARTIAL_MIGRATE_INTERVAL) {
      $this->scheduleFullMigration($migrationStateKey, $migrationLastFullTimeKey);
      return;
    }

    $this->schedulePartialMigration($migrationStateKey);
  }

  /**
   * Set the next migration to be a full migration.
   *
   * @param string $migrationStateKey
   *   State to update.
   * @param string $migrationLastFullTimeKey
   *   The "last run" -state to update.
   */
  private function scheduleFullMigration(string $migrationStateKey, string $migrationLastFullTimeKey) {
    $this->state->set($migrationStateKey, 0);
    $this->state->set($migrationLastFullTimeKey, time());
  }

  /**
   * Set the next migration to be a partial migration.
   *
   * @param string $migrationStateKey
   *   State to update.
   */
  private function schedulePartialMigration(string $migrationStateKey) {
    $this->state->set($migrationStateKey, 1);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'migrate.post_import' => ['updateMigrationState'],
    ];
  }

}
