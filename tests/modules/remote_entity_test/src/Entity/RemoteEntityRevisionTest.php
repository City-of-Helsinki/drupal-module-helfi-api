<?php

declare(strict_types=1);

namespace Drupal\remote_entity_test\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the remote entity test class.
 *
 * @ContentEntityType(
 *   id = "rmert_test",
 *   label = @Translation("Remote entity revision test"),
 *   label_collection = @Translation("Remote entity revision test"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\entity\EntityAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "form" = {
 *       "default" = "Drupal\remote_entity_test\Entity\RemoteEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\helfi_api_base\Entity\Routing\EntityRouteProvider",
 *       "revision" = "\Drupal\helfi_api_base\Entity\Routing\RevisionRouteProvider",
 *     }
 *   },
 *   base_table = "rmert_test",
 *   data_table = "rmert_test_field_data",
 *   admin_permission = "administer remote_entity_revision_test",
 *   translatable = TRUE,
 *   revision_table = "rmert_test_revision",
 *   revision_data_table = "rmert_test_field_revision",
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "revision" = "vid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "published" = "content_translation_status",
 *     "owner" = "content_translation_uid",
 *   },
 *   links = {
 *     "canonical" = "/rmert_test/{rmert_test}",
 *     "edit-form" = "/admin/content/rmert_test/{rmert_test}/edit",
 *     "delete-form" = "/rmert_test/{rmert_test}/delete",
 *     "collection" = "/admin/content/rmert_test",
 *     "version-history" = "/admin/content/rmert_test/{rmert_test}/revisions",
 *     "revision-revert-language-form" = "/admin/content/rmert_test/{rmert_test}/revisions/{rmert_test_revision}/revert/{langcode}",
 *   },
 * )
 */
final class RemoteEntityRevisionTest extends RemoteEntityBase implements EntityPublishedInterface, EntityOwnerInterface, RevisionableInterface {

  use EntityPublishedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public const MAX_SYNC_ATTEMPTS = 5;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    return $fields;
  }

}
