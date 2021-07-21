<?php

namespace Drupal\blizz_dynamic_entitylist\Services;

/**
 * Interface EntityServicesInterface.
 *
 * Defines the API for the entity  service.
 *
 * @package Drupal\blizz_dynamic_entitylist\Services
 */
interface EntityServicesInterface {

  /**
   * Returns a list of available entity types, keyed by machine name.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface[]
   *   An array of existing entity types.
   */
  public function getContentEntityTypes();

  /**
   * Returns the definition of a single content entity type.
   *
   * @param string $entity_type_id
   *   The machine name of the entity type definition desired.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface|null
   *   The definition of the given entity type (if existent).
   */
  public function getContentEntityTypeDefinition($entity_type_id);

  /**
   * Returns ready-to-use entity type id options, keyed by machine name.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The entity type options.
   */
  public function getContentEntityTypeOptions();

  /**
   * Gets all available entity bundle informations.
   *
   * @param string $entity_type_id
   *   The entity type id the bundle information is desired for.
   *
   * @return array
   *   The bundle definitions.
   */
  public function getEntityTypeBundles($entity_type_id);

  /**
   * Returns ready-to-use bundle options, keyed by the machine name.
   *
   * @param string $entity_type_id
   *   The entity type id the bundle information is desired for.
   *
   * @return array
   *   The bundle options for the given entity type id.
   */
  public function getEntityTypeBundleOptions($entity_type_id);

  /**
   * Returns an array of enabled view mode options by bundle.
   *
   * @param string $entity_type_id
   *   The entity type whose view mode options should be returned.
   * @param string $bundle
   *   The name of the bundle.
   *
   * @return array
   *   An array of view mode labels, keyed by the display mode ID.
   */
  public function getEntityTypeBundleViewmodes($entity_type_id, $bundle);

  /**
   * Gets the field information of a given entity bundle.
   *
   * @param string $entity_type_id
   *   The entity type id the bundle information is desired for.
   * @param string $bundles
   *   The name of the entity bundle the field information is desired for.
   * @param array $filter
   *   An array of information the field definitions must match.
   * @param bool $exclude_base_fields
   *   Flag indicating if base field definitions should be excluded.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]|mixed
   *   The field information of that bundle.
   */
  public function getEntityTypeBundleFields($entity_type_id, $bundles, array $filter = [], $exclude_base_fields = TRUE);

  /**
   * Returns ready-to-use field options, keyed by machine name.
   *
   * @param string $entity_type_id
   *   The entity type id the bundle information is desired for.
   * @param string $bundle
   *   The media bundle name the field information is desired for.
   * @param array $filter
   *   An array of information the field definitions must match.
   * @param bool $exclude_base_fields
   *   Flag indicating if base field definitions should be excluded.
   *
   * @return array
   *   The field options of that bundle.
   */
  public function getEntityTypeBundleFieldOptions($entity_type_id, $bundle, array $filter = [], $exclude_base_fields = TRUE);

  /**
   * Returns the machine name of a given entity key.
   *
   * @param string $entity_type_id
   *   The machine name of the entity type in question.
   * @param string $key
   *   The Drupal-standard name of the key (e.g. "label").
   *
   * @return string|false
   *   The name of the entity key (if exists) or FALSE.
   */
  public function getEntityKey($entity_type_id, $key);

  /**
   * Loads entities by machine name and id.
   *
   * @param string $entity_type_id
   *   The machine name of the entity type.
   * @param array $ids
   *   The ids of the entities to load.
   *
   * @return array
   *   The loaded entities.
   */
  public function loadEntities($entity_type_id, array $ids);

  /**
   * Gets an entity query instance for a given entity type id.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query instance for that entity type.
   */
  public function getQuery($entity_type_id);

  /**
   * Returns the instantiated entity view builder for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return \Drupal\Core\Entity\EntityViewBuilderInterface
   *   The instantiated entity view builder.
   */
  public function getEntityViewbuilder($entity_type_id);

  /**
   * Fetches the storage interface of a given entity type (singleton).
   *
   * @param string $entity_type_id
   *   The entity type the storage interface is needed of.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage interface (if successful).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   If an invalid entity type is given, the exception is passed.
   */
  public function getEntityStorageInterface($entity_type_id);

}
