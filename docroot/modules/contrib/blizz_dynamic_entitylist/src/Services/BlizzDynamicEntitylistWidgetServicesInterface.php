<?php

namespace Drupal\blizz_dynamic_entitylist\Services;

/**
 * Interface BlizzDynamicEntitylistWidgetServicesInterface.
 *
 * @package Drupal\blizz_dynamic_entitylist\Services
 */
interface BlizzDynamicEntitylistWidgetServicesInterface {

  /**
   * Generates the form potion of the second widget state.
   *
   * @param string $field_id
   *   The configuration name of the field requesting this api call.
   * @param string $entity_type
   *   The entity type machine name.
   * @param array $listdefinition
   *   The listdefinition (if pre-filled).
   * @param array $fieldsettings
   *   The configured field constraints.
   * @param bool $raw
   *   Flag indicating if the form array should be returned.
   *
   * @return array|string
   *   Dependent upon $raw, either the form array or HTML.
   */
  public function widgetStage2($field_id, $entity_type, array $listdefinition = [], array $fieldsettings = [], $raw = FALSE);

  /**
   * Generates the form potion of the third widget state.
   *
   * @param string $field_id
   *   The configuration name of the field requesting this api call.
   * @param string $entity_type
   *   The entity type machine name.
   * @param string $bundle
   *   The bundle name of the given entity type.
   * @param array $listdefinition
   *   The listdefinition (if pre-filled).
   * @param array $fieldsettings
   *   The configured field constraints.
   * @param bool $raw
   *   Flag indicating if the form array should be returned.
   *
   * @return array|string
   *   Dependent upon $raw, either the form array or HTML.
   */
  public function widgetStage3($field_id, $entity_type, $bundle, array $listdefinition = [], array $fieldsettings = [], $raw = FALSE);

  /**
   * Ajax callback for autocomplete filter selects (select2).
   *
   * @param string $entity_type
   *   The entity type machine name.
   * @param string $bundle
   *   Comma-separated list of bundles of the given entity type.
   *
   * @return mixed
   *   An array holding the autocomplete information in select2 format.
   */
  public function handleAutocomplete($entity_type, $bundle);

  /**
   * Turns the serialized storage format of the field into an array.
   *
   * @param array $fieldcontents
   *   The serialized list definition.
   *
   * @return array
   *   Array holding the list definition.
   */
  public function getListDefinition(array $fieldcontents);

  /**
   * Returns ready-loaded entities for a given list definition.
   *
   * @param array $listdefinition
   *   The definition of the entity list.
   * @param bool $register_ids
   *   Should entity ids of the current list be registered in the id cache?
   * @param bool $preview_mode
   *   Flag indicating if list length sould get capped at 10 entities.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   List of entities matching the list definition.
   */
  public function getListEntities(array $listdefinition, $register_ids, $preview_mode = FALSE);

  /**
   * Ajax-interface for the list preview.
   *
   * Needed parameters for the list definition have to
   * be transferred within POST.
   *
   * @return string
   *   Ready-to-use HTML of the preview.
   */
  public function getListPreviewByAjax();

  /**
   * Fetches a preview on a given list definition.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The entity bundle of the given type.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   An array of loaded entities.
   * @param bool $raw
   *   Flag indicating if the preview should also get rendered into HTML.
   *
   * @return array|string
   *   The render array if $raw or readily build HTML.
   */
  public function listPreview($entity_type_id, $bundle, array $entities, $raw = FALSE);

  /**
   * Tries to determine if the current request has an entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity of the route or NULL if undetermined.
   */
  public function getEntityFromRequest();

}
