<?php

namespace Drupal\blizz_dynamic_entitylist\Services;

/**
 * Interface BlizzDynamicEntitylistApiInterface.
 *
 * @package Drupal\blizz_dynamic_entitylist\Services
 */
interface BlizzDynamicEntitylistApiInterface {

  /**
   * Excluded given entities from being contained in dynamic lists.
   *
   * This method is useful for example if you have lists with manually
   * selected entities which you don't want to appear again in dynamic
   * lists. Note: this hook needs to be called within the view builder
   * of the entity holding all lists.
   *
   * @param string $entity_type_id
   *   The machine readable entity type id of the entities to be axcluded.
   * @param array $entity_ids
   *   An array of the entities that should get excluded.
   */
  public function excludeEntitiesFromLists($entity_type_id, array $entity_ids);

  /**
   * Get excluded entity ids for a given entity type id.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return array
   *   An array of excluded entity ids.
   */
  public function getExcludedEntities($entity_type_id);

}
