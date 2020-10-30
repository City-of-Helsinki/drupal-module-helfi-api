<?php

declare(strict_types = 1);

namespace Drupal\helfi_api_base\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;
use Drupal\helfi_api_base\MigrateTrait;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handle life-cycle of migrated entities.
 */
final class MigrationSubscriber implements EventSubscriberInterface {

  use MigrateTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection) {
    $this->entityTypeManager = $entityTypeManager;
    $this->connection = $connection;
  }

  /**
   * Gets the entity type for given migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   *
   * @return string|null
   *   The entity type or null.
   */
  private function getEntityType(MigrationInterface $migration) : ? string {
    $configuration = $migration->getDestinationConfiguration();

    $defaults = [NULL, NULL];
    [$type, $entity_type] = explode(':', $configuration['plugin']) + $defaults;

    if ($type !== 'entity' || !$entity_type) {
      return NULL;
    }

    return $entity_type;
  }

  /**
   * Responds to post-migrate events.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate event.
   */
  public function onPostImport(MigrateImportEvent $event) : void {
    if (!$entity_type = $this->getEntityType($event->getMigration())) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entityClass = $storage->getEntityType()->getClass();

    if (!is_a($entityClass, RemoteEntityBase::class, TRUE)) {
      return;
    }

    // Fetch and delete entities that exceeds the max sync attempts
    // limit.
    $results = $storage
      ->getQuery()
      ->condition('sync_attempts', $entityClass::MAX_SYNC_ATTEMPTS, '>=')
      ->execute();

    foreach ($results as $id) {
      $storage->load($id)->delete();
    }
  }

  /**
   * Responds to pre-migrate events.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate event.
   */
  public function onPreImport(MigrateImportEvent $event) : void {
    if (!$entity_type = $this->getEntityType($event->getMigration())) {
      return;
    }

    // Increment sync counter only when we're not doing a partial migrate.
    // Partial migrates don't save any unchanged entities, leading post-migrate
    // event to delete all unchanged entities.
    if ($this->isPartialMigrate()) {
      return;
    }

    $dataTable = $this->entityTypeManager->getStorage($entity_type)
      ->getEntityType()
      ->getDataTable();

    // Increment 'sync_attempts' for all entities.
    // This will be reset back to 0 on entity save.
    // @see \Drupal\helfi_api_base\Entity\RemoteEntityBase::save().
    $this->connection->update($dataTable)
      ->expression('sync_attempts', 'sync_attempts + 1')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'migrate.pre_import' => ['onPreImport'],
      'migrate.post_import' => ['onPostImport'],
    ];
  }

}
