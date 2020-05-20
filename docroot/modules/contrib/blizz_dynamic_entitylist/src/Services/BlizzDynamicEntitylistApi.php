<?php

namespace Drupal\blizz_dynamic_entitylist\Services;

/**
 * Class BlizzDynamicEntitylistApi.
 *
 * @package Drupal\blizz_dynamic_entitylist\Services
 */
class BlizzDynamicEntitylistApi implements BlizzDynamicEntitylistApiInterface {

  /**
   * Array holding information of entities that should be excluded from lists.
   *
   * @var array
   */
  protected $idCache;

  /**
   * BlizzDynamicEntitylistApi constructor.
   */
  public function __construct() {
    $this->idCache = drupal_static('blizz_dynamic_entitylist_id_cache', []);
  }

  /**
   * {@inheritdoc}
   */
  public function excludeEntitiesFromLists($entity_type_id, array $entity_ids) {
    if (!isset($this->idCache[$entity_type_id])) {
      $this->idCache[$entity_type_id] = [];
    }
    $this->idCache[$entity_type_id] = array_merge($this->idCache[$entity_type_id], $entity_ids);
    $this->idCache[$entity_type_id] = array_unique($this->idCache[$entity_type_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getExcludedEntities($entity_type_id) {
    return !empty($this->idCache[$entity_type_id])
      ? $this->idCache[$entity_type_id]
      : [];
  }

}
