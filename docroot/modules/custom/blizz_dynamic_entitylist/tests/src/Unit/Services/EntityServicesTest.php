<?php

namespace Drupal\Tests\blizz_dynamic_entitylist\Unit\Services;

use Drupal\blizz_dynamic_entitylist\Services\EntityServices;
use Drupal\Tests\blizz_dynamic_entitylist\Unit\UnitTestBase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\blizz_dynamic_entitylist\Services\EntityServices
 *
 * @group blizz_dynamic_entitylist
 */
class EntityServicesTest extends UnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUpTestObject() {
    $this->entityServices = new EntityServices(
      $this->entityTypeManager->reveal(),
      $this->entityBundleInfoService->reveal(),
      $this->entityFieldManager->reveal(),
      $this->entityTypeRepository->reveal(),
      $this->entityDisplayRepository->reveal(),
      $this->translationInterface->reveal()
    );
  }

  /**
   * Tests the entityServices's getContentEntityTypes method.
   */
  public function testGetContentEntityTypes() {

    // Perform the test.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_types'],
      $this->entityServices->getContentEntityTypes(),
      'Method ->getContentEntityTypes() did not return the expected result'
    );

    // Repeat the test - are the results still valid?
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_types'],
      $this->entityServices->getContentEntityTypes(),
      'Method ->getContentEntityTypes() did not return the expected result'
    );

  }

  /**
   * Tests the entityServices's getContentEntityTypeDefinition method.
   */
  public function testGetContentEntityTypeDefinition() {

    // Get the definition of nodes.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_types']['node'],
      $this->entityServices->getContentEntityTypeDefinition('node'),
      'Method ->getContentEntityTypeDefinition() did not return the expected result'
    );

    // Get the definition of taxonomy terms.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_types']['taxonomy_term'],
      $this->entityServices->getContentEntityTypeDefinition('taxonomy_term'),
      'Method ->getContentEntityTypeDefinition() did not return the expected result'
    );

    // Get the definition of media entities.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_types']['media'],
      $this->entityServices->getContentEntityTypeDefinition('media'),
      'Method ->getContentEntityTypeDefinition() did not return the expected result'
    );

    // Is the call on 'nodes' still valid?
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_types']['node'],
      $this->entityServices->getContentEntityTypeDefinition('node'),
      'Method ->getContentEntityTypeDefinition() did not return the expected result'
    );

  }

  /**
   * Tests the entityServices's getContentEntityTypeOptions method.
   */
  public function testGetContentEntityTypeOptions() {

    // First test of ->getContentEntityTypeOptions().
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_type_options'],
      $this->entityServices->getContentEntityTypeOptions(),
      'Method ->getEntityTypeOptions() did not return the expected result.'
    );

    // Does caching work?
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_type_options'],
      $this->entityServices->getContentEntityTypeOptions(),
      'Method ->getEntityTypeOptions() did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's getEntityTypeBundles method.
   */
  public function testGetEntityTypeBundles() {

    // First test of ->getEntityTypeBundles('node').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundles']['node'],
      $this->entityServices->getEntityTypeBundles('node'),
      'Method ->getEntityTypeBundles("node") did not return the expected result.'
    );

    // First test of ->getEntityTypeBundles('taxonomy_term').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundles']['taxonomy_term'],
      $this->entityServices->getEntityTypeBundles('taxonomy_term'),
      'Method ->getEntityTypeBundles("taxonomy_term") did not return the expected result.'
    );

    // First test of ->getEntityTypeBundles('media').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundles']['media'],
      $this->entityServices->getEntityTypeBundles('media'),
      'Method ->getEntityTypeBundles("media") did not return the expected result.'
    );

    // Does caching work? Re-test of ->getEntityTypeBundles('node').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundles']['node'],
      $this->entityServices->getEntityTypeBundles('node'),
      'Method ->getEntityTypeBundles("node") did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's getEntityTypeBundleOptions method.
   */
  public function testGetEntityTypeBundleOptions() {

    // First test on ->getEntityTypeBundleOptions('node').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_options']['node'],
      $this->entityServices->getEntityTypeBundleOptions('node'),
      'Method ->getEntityTypeBundleOptions("node") did not return the expected result.'
    );

    // First test on ->getEntityTypeBundleOptions('taxonomy_term').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_options']['taxonomy_term'],
      $this->entityServices->getEntityTypeBundleOptions('taxonomy_term'),
      'Method ->getEntityTypeBundleOptions("taxonomy_term") did not return the expected result.'
    );

    // First test on ->getEntityTypeBundleOptions('media').
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_options']['media'],
      $this->entityServices->getEntityTypeBundleOptions('media'),
      'Method ->getEntityTypeBundleOptions("media") did not return the expected result.'
    );

    // Re-test on ->getEntityTypeBundleOptions('node'). Does caching work?
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_options']['node'],
      $this->entityServices->getEntityTypeBundleOptions('node'),
      'Method ->getEntityTypeBundleOptions("node") did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's getEntityTypeBundleViewmodes method.
   */
  public function testGetEntityTypeBundleViewmodes() {

    // First test on node:article.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_viewmodes']['node']['article'],
      $this->entityServices->getEntityTypeBundleViewmodes('node', 'article'),
      'Method ->getEntityTypeBundleViewmodes("node", "article") did not return the expected result.'
    );

    // First test on node:page.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_viewmodes']['node']['page'],
      $this->entityServices->getEntityTypeBundleViewmodes('node', 'page'),
      'Method ->getEntityTypeBundleViewmodes("node", "page") did not return the expected result.'
    );

    // First test on taxonomy_term:tags.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_viewmodes']['taxonomy_term']['tags'],
      $this->entityServices->getEntityTypeBundleViewmodes('taxonomy_term', 'tags'),
      'Method ->getEntityTypeBundleViewmodes("taxonomy_term", "tags") did not return the expected result.'
    );

    // First test on taxonomy_term:categories.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_viewmodes']['taxonomy_term']['categories'],
      $this->entityServices->getEntityTypeBundleViewmodes('taxonomy_term', 'categories'),
      'Method ->getEntityTypeBundleViewmodes("taxonomy_term", "categories") did not return the expected result.'
    );

    // First test on taxonomy_term:colors.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_viewmodes']['taxonomy_term']['colors'],
      $this->entityServices->getEntityTypeBundleViewmodes('taxonomy_term', 'colors'),
      'Method ->getEntityTypeBundleViewmodes("taxonomy_term", "colors") did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's getEntityTypeBundleFields method.
   */
  public function testGetEntityTypeBundleFields() {

    // First test on node:article without any filtering and without base fields.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_without_basefields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFields('node', 'article'),
      'Method ->getEntityTypeBundleFields("node", "article") did not return the expected result.'
    );

    // Re-test on node:article without any filtering,
    // but now including base fields.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFields('node', 'article', [], FALSE),
      'Method ->getEntityTypeBundleFields("node", "article", [], FALSE) did not return the expected result.'
    );

    // Testing taxonomy_term:categories without base fields and filtering.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_without_basefields']['taxonomy_term']['categories'],
      $this->entityServices->getEntityTypeBundleFields('taxonomy_term', 'categories'),
      'Method ->getEntityTypeBundleFields("taxonomy_term", "categories") did not return the expected result.'
    );

    // Re-test on node:article without any filtering and without base fields.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_without_basefields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFields('node', 'article'),
      'Method ->getEntityTypeBundleFields("node", "article") did not return the expected result.'
    );

    // Only retrieve taxonomy_term reference fields from node:article.
    $expected = array_filter(
      $this->testdata['comparisondata']['entity_bundle_fields']['node']['article'],
      function ($field) {
        return isset($this->testdata['comparisondata']['fields'][$field]) &&
          $this->testdata['comparisondata']['fields'][$field]['field_config_interface']->get('field_type') == 'entity_reference' &&
          $this->testdata['comparisondata']['fields'][$field]['field_config_interface']->getSetting('handler') == 'default:taxonomy_term';
      },
      ARRAY_FILTER_USE_KEY
    );
    $taxonomyReferenceFieldCriteria = [
      'getType' => 'entity_reference',
      'getSetting' => [
        'arguments' => ['handler'],
        'value' => 'default:taxonomy_term',
      ],
    ];
    $this->assertEquals(
      $expected,
      $this->entityServices->getEntityTypeBundleFields('node', 'article', $taxonomyReferenceFieldCriteria),
      'Method ->getEntityTypeBundleFields("node", "article") filtering for taxonomy reference fields did not return the expected result.'
    );

    // Make sure that the filtering will not fail if
    // the filter functions called are bs.
    $filterDefinition = [
      'foo' => 'bar',
    ];
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_without_basefields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFields('node', 'article', $filterDefinition),
      'Method ->getEntityTypeBundleFields("node", "article") with corrupt filterinformation did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's getEntityTypeBundleFieldOptions method.
   */
  public function testGetEntityTypeBundleFieldOptions() {

    // First test on node:article without any filtering and without base fields.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_options_without_basefields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFieldOptions('node', 'article'),
      'Method ->getEntityTypeBundleFieldOptions("node", "article") did not return the expected result.'
    );

    // Re-test on node:article without any filtering,
    // but now including base fields.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_options']['node']['article'],
      $this->entityServices->getEntityTypeBundleFieldOptions('node', 'article', [], FALSE),
      'Method ->getEntityTypeBundleFieldOptions("node", "article", [], FALSE) did not return the expected result.'
    );

    // Testing taxonomy_term:categories without base fields and filtering.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_options_without_basefields']['taxonomy_term']['categories'],
      $this->entityServices->getEntityTypeBundleFieldOptions('taxonomy_term', 'categories'),
      'Method ->getEntityTypeBundleFieldOptions("taxonomy_term", "categories") did not return the expected result.'
    );

    // Re-test on node:article without any filtering and without base fields.
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_options_without_basefields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFieldOptions('node', 'article'),
      'Method ->getEntityTypeBundleFieldOptions("node", "article") did not return the expected result.'
    );

    // Only retrieve taxonomy_term reference fields from node:article.
    $expected = array_filter(
      $this->testdata['comparisondata']['entity_bundle_fields_options']['node']['article'],
      function ($field) {
        return isset($this->testdata['comparisondata']['fields'][$field]) &&
          $this->testdata['comparisondata']['fields'][$field]['field_config_interface']->get('field_type') == 'entity_reference' &&
          $this->testdata['comparisondata']['fields'][$field]['field_config_interface']->getSetting('handler') == 'default:taxonomy_term';
      },
      ARRAY_FILTER_USE_KEY
    );
    $taxonomyReferenceFieldCriteria = [
      'getType' => 'entity_reference',
      'getSetting' => [
        'arguments' => ['handler'],
        'value' => 'default:taxonomy_term',
      ],
    ];
    $this->assertEquals(
      $expected,
      $this->entityServices->getEntityTypeBundleFieldOptions('node', 'article', $taxonomyReferenceFieldCriteria),
      'Method ->getEntityTypeBundleFieldOptions("node", "article") filtering for taxonomy reference fields did not return the expected result.'
    );

    // Make sure that the filtering will not fail if the
    // filter functions called are bs.
    $filterDefinition = [
      'foo' => 'bar',
    ];
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_bundle_fields_options_without_basefields']['node']['article'],
      $this->entityServices->getEntityTypeBundleFieldOptions('node', 'article', $filterDefinition),
      'Method ->getEntityTypeBundleFieldOptions("node", "article") with corrupt filterinformation did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's getEntityKey method.
   */
  public function testGetEntityKey() {

    // Test for the node:id key (nid).
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_keys']['node']['id'],
      $this->entityServices->getEntityKey('node', 'id'),
      'Method ->getEntityKey("node", "id") did not return the expected result.'
    );

    // Test for the node:status key (status).
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_keys']['node']['status'],
      $this->entityServices->getEntityKey('node', 'status'),
      'Method ->getEntityKey("node", "status") did not return the expected result.'
    );

    // Test for the node:langcode key (lang).
    $this->assertEquals(
      $this->testdata['comparisondata']['entity_keys']['node']['langcode'],
      $this->entityServices->getEntityKey('node', 'langcode'),
      'Method ->getEntityKey("node", "langcode") did not return the expected result.'
    );

    // Test for the taxonomy_term:status key (FALSE).
    $this->assertEquals(
      FALSE,
      $this->entityServices->getEntityKey('taxonomy_term', 'status'),
      'Method ->getEntityKey("taxonomy_term", "status") did not return the expected result.'
    );

  }

  /**
   * Tests the entityService's loadEntities method.
   */
  public function testLoadEntities() {
    $this->entityStorageInterface->loadMultiple(Argument::type('array'))->shouldBeCalled();
    $this->entityServices->loadEntities('node', [1, 2, 3]);
  }

  /**
   * Tests the entityService's getQuery method.
   */
  public function testGetQuery() {
    $this->assertEquals(
      $this->entityQueryInterface->reveal(),
      $this->entityServices->getQuery('node'),
      'The method ->getQuery() did not return the expected object.'
    );
  }

  /**
   * Tests the entityService's getEntityViewbuilder method.
   */
  public function testGetEntityViewbuilder() {
    $this->assertEquals(
      $this->entityViewBuilderInterface->reveal(),
      $this->entityServices->getEntityViewbuilder('node'),
      'The method ->getEntityViewbuilder() did not return the expected object.'
    );
  }

  /**
   * Tests the entityService's getEntityStorageInterface method.
   */
  public function testGetEntityStorageInterface() {
    $this->assertEquals(
      $this->entityStorageInterface->reveal(),
      $this->entityServices->getEntityStorageInterface('node'),
      'The method ->getEntityStorageInterface() did not return the expected object.'
    );
  }

}

namespace Drupal\blizz_dynamic_entitylist\Services;

if (!function_exists('drupal_static')) {

  /**
   * {@inheritdoc}
   *
   * This is an exact copy of Drupal's drupal_static function in
   * file bootstrap.inc. It is mocked here to enable PHPUnit to run.
   */
  function &drupal_static($name, $default_value = NULL, $reset = FALSE) {
    static $data = [], $default = [];
    // First check if dealing with a previously defined static variable.
    if (isset($data[$name]) || array_key_exists($name, $data)) {
      // Non-NULL $name and both $data[$name] and $default[$name] statics exist.
      if ($reset) {
        // Reset pre-existing static variable to its default value.
        $data[$name] = $default[$name];
      }
      return $data[$name];
    }
    // Neither $data[$name] nor $default[$name] static variables exist.
    if (isset($name)) {
      if ($reset) {
        // Reset was called before a default is set and yet a variable must be
        // returned.
        return $data;
      }
      // First call with new non-NULL $name. Initialize a new static variable.
      $default[$name] = $data[$name] = $default_value;
      return $data[$name];
    }
    // Reset all: ($name == NULL). This needs to be done one at a time so that
    // references returned by earlier invocations of drupal_static() also get
    // reset.
    foreach ($default as $name => $value) {
      $data[$name] = $value;
    }
    // As the function returns a reference, the return should always be a
    // variable.
    return $data;
  }

}
