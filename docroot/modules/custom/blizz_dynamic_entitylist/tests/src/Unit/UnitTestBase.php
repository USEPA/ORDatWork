<?php

namespace Drupal\Tests\blizz_dynamic_entitylist\Unit;

use Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Provides consistent test data for implemented unit tests.
 *
 * @group blizz_dynamic_entitylist
 */
abstract class UnitTestBase extends UnitTestCase {

  /**
   * Testdata structure.
   *
   * Holds the information architecture of the mock objects as
   * well as comparison data for the single unit tests.
   *
   * @var array
   */
  protected $testdata;

  /**
   * The entity type manager mock object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service mock object.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfoService;

  /**
   * The entity field manager mock object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type repository interface mock object.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * The entity display repository mock object.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity query mock object.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQueryInterface;

  /**
   * A generic entity storage mock object.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorageInterface;

  /**
   * A generic entity view builder mock object.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $entityViewBuilderInterface;

  /**
   * A mocked Drupal renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $rendererInterface;

  /**
   * A mocked string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationInterface;

  /**
   * A mocked route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatchInterface;

  /**
   * A mocked language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManagerInterface;

  /**
   * Our own entity service as a mock object.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface
   */
  protected $entityServicesInterface;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Mock Drupal core services.
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityBundleInfoService = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $this->entityTypeRepository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $this->entityDisplayRepository = $this->prophesize(EntityDisplayRepositoryInterface::class);
    $this->entityQueryInterface = $this->prophesize(QueryInterface::class);
    $this->entityStorageInterface = $this->prophesize(EntityStorageInterface::class);
    $this->entityViewBuilderInterface = $this->prophesize(EntityViewBuilderInterface::class);
    $this->rendererInterface = $this->prophesize(RendererInterface::class);
    $this->translationInterface = $this->prophesize(TranslationInterface::class);
    $this->routeMatchInterface = $this->prophesize(RouteMatchInterface::class);
    $this->languageManagerInterface = $this->prophesize(LanguageManagerInterface::class);
    $this->entityServicesInterface = $this->prophesize(EntityServicesInterface::class);

    // Set the return value of core service methods.
    $this->entityStorageInterface->getQuery(Argument::any())->willReturn($this->entityQueryInterface->reveal());
    $this->entityStorageInterface->loadMultiple(Argument::any())->willReturn(TRUE);

    $this->entityTypeManager->getStorage(Argument::any())->willReturn($this->entityStorageInterface->reveal());
    $this->entityTypeManager->getViewBuilder(Argument::any())->willReturn($this->entityViewBuilderInterface->reveal());

    // Prepare a realistic testing scenario.
    $this->prepareMockedObjectData();

    // Trigger implemented unit tests.
    $this->setUpTestObject();

  }

  /**
   * Prepares consistent teat data for all tests.
   *
   * @return array
   *   Some mock objects to test on.
   */
  private function prepareMockedObjectData() {

    /*
     * Prepare some common mock objects.
     */

    // Mocked storage definition for a multivalue field.
    $unlimitedvalueFieldStorageDefinition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $unlimitedvalueFieldStorageDefinition->getCardinality()->willReturn(-1);
    $unlimitedvalueFieldStorageDefinition = $unlimitedvalueFieldStorageDefinition->reveal();

    // Mocked storage definition for a singlevalue field.
    $singlevalueFieldStorageDefinition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $singlevalueFieldStorageDefinition->getCardinality()->willReturn(1);
    $singlevalueFieldStorageDefinition = $singlevalueFieldStorageDefinition->reveal();

    // Mocked storage definition for a multivalue field (not unlimited).
    $limitedMultivalueFieldStorageDefinition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $limitedMultivalueFieldStorageDefinition->getCardinality()->willReturn(3);
    $limitedMultivalueFieldStorageDefinition = $limitedMultivalueFieldStorageDefinition->reveal();

    // Base field definition A.
    $base_field_a = $this->prophesize(BaseFieldDefinition::class);
    $base_field_a->getLabel()->willReturn('Base Field Definition A');
    $base_field_a->getName()->willReturn('base_field_a');
    $base_field_a->getType()->willReturn('integer');
    $base_field_a = $base_field_a->reveal();

    // Base field definition B.
    $base_field_b = $this->prophesize(BaseFieldDefinition::class);
    $base_field_b->getLabel()->willReturn('Base Field Definition B');
    $base_field_b->getName()->willReturn('base_field_b');
    $base_field_b->getType()->willReturn('string');
    $base_field_b = $base_field_b->reveal();

    /*
     * The test structure.
     */

    $this->testdata = [
      'entity_types' => [
        'node' => [
          'label' => 'Node',
          'fields' => [
            'field_node_text' => [
              'field_type' => 'string',
              'label' => 'Text',
              'handler' => NULL,
              'handler_settings' => [],
              'field_storage_definition' => $singlevalueFieldStorageDefinition,
            ],
            'field_node_entity_reference_taxonomy_tags' => [
              'field_type' => 'entity_reference',
              'label' => 'Entity Reference: taxonomy:tags',
              'handler' => 'default:taxonomy_term',
              'handler_settings' => ['target_bundles' => ['tags' => 'tags']],
              'field_storage_definition' => $unlimitedvalueFieldStorageDefinition,
            ],
            'field_node_entity_reference_taxonomy_category' => [
              'field_type' => 'entity_reference',
              'label' => 'Entity Reference: taxonomy:tags',
              'handler' => 'default:taxonomy_term',
              'handler_settings' => ['target_bundles' => ['category' => 'category']],
              'field_storage_definition' => $singlevalueFieldStorageDefinition,
            ],
            'field_node_entity_reference_taxonomy_colors' => [
              'field_type' => 'entity_reference',
              'label' => 'Entity Reference: taxonomy:tags',
              'handler' => 'default:taxonomy_term',
              'handler_settings' => ['target_bundles' => ['colors' => 'colors']],
              'field_storage_definition' => $limitedMultivalueFieldStorageDefinition,
            ],
          ],
          'keys' => [
            'bundle' => 'type',
            'label' => 'title',
            'status' => 'status',
            'langcode' => 'lang',
            'id' => 'nid',
          ],
          'bundles' => [
            'page' => [
              'label' => 'Static Page',
              'fields' => [
                'field_node_text',
                'field_node_entity_reference_taxonomy_category',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
            'article' => [
              'label' => 'Article',
              'fields' => [
                'field_node_text',
                'field_node_entity_reference_taxonomy_tags',
                'field_node_entity_reference_taxonomy_category',
                'field_node_entity_reference_taxonomy_colors',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
          ],
        ],
        'taxonomy_term' => [
          'label' => 'Taxonomy Term',
          'fields' => [
            'field_taxonomy_term_description' => [
              'field_type' => 'string',
              'label' => 'Description',
              'handler' => NULL,
              'handler_settings' => [],
              'field_storage_definition' => $singlevalueFieldStorageDefinition,
            ],
          ],
          'keys' => [
            'bundle' => 'vocabulary',
            'label' => 'name',
            'langcode' => 'lang',
            'id' => 'tid',
          ],
          'bundles' => [
            'tags' => [
              'label' => 'Tags',
              'fields' => [
                'field_taxonomy_term_description',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
            'categories' => [
              'label' => 'Categories',
              'fields' => [
                'field_taxonomy_term_description',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
            'colors' => [
              'label' => 'Colors',
              'fields' => [
                'field_taxonomy_term_description',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
          ],
        ],
        'media' => [
          'label' => 'Media Entity',
          'fields' => [
            'field_media_entity_reference_taxonomy_tags' => [
              'field_type' => 'entity_reference',
              'label' => 'Entity Reference: taxonomy:tags',
              'handler' => 'default:taxonomy_term',
              'handler_settings' => ['target_bundles' => ['tags' => 'tags']],
              'field_storage_definition' => $unlimitedvalueFieldStorageDefinition,
            ],
            'field_media_entity_reference_taxonomy_categories' => [
              'field_type' => 'entity_reference',
              'label' => 'Entity Reference: taxonomy:categories',
              'handler' => 'default:taxonomy_term',
              'handler_settings' => ['target_bundles' => ['categories' => 'categories']],
              'field_storage_definition' => $singlevalueFieldStorageDefinition,
            ],
            'field_media_entity_reference_taxonomy_colors' => [
              'field_type' => 'entity_reference',
              'label' => 'Entity Reference: taxonomy:colors',
              'handler' => 'default:taxonomy_term',
              'handler_settings' => ['target_bundles' => ['colors' => 'colors']],
              'field_storage_definition' => $limitedMultivalueFieldStorageDefinition,
            ],
          ],
          'keys' => [
            'bundle' => 'bundle',
            'label' => 'name',
            'status' => 'status',
            'langcode' => 'lang',
            'id' => 'id',
          ],
          'bundles' => [
            'image' => [
              'label' => 'Image',
              'fields' => [
                'field_media_entity_reference_taxonomy_tags',
                'field_media_entity_reference_taxonomy_categories',
                'field_media_entity_reference_taxonomy_colors',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
            'audio' => [
              'label' => 'Audio',
              'fields' => [
                'field_media_entity_reference_taxonomy_tags',
                'field_media_entity_reference_taxonomy_categories',
                'field_media_entity_reference_taxonomy_colors',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
            'video' => [
              'label' => 'Video',
              'fields' => [
                'field_media_entity_reference_taxonomy_tags',
                'field_media_entity_reference_taxonomy_categories',
                'field_media_entity_reference_taxonomy_colors',
              ],
              'viewmodes' => [
                'default' => 'Default',
                'full' => 'Full',
                'teaser' => 'Teaser',
                'rss' => 'RSS',
              ],
            ],
          ],
        ],
      ],
      'comparisondata' => [],
    ];

    // Now dynamically define the mock objects
    // according to the structure given above.
    $testEntityTypeOptions = array_combine(
      array_keys($this->testdata['entity_types']),
      array_map(
        function ($item) {
          return $item['label'];
        },
        $this->testdata['entity_types']
      )
    );
    $this->entityTypeRepository->getEntityTypeLabels()->willReturn($testEntityTypeOptions);
    $this->testdata['comparisondata']['entity_type_options'] = $testEntityTypeOptions;

    foreach ($this->testdata['entity_types'] as $entity_type_id => &$entity_type_definition) {

      $contentEntityTypeInterfaceMock = $this->prophesize(ContentEntityTypeInterface::class);
      $contentEntityTypeInterfaceMock->getLabel()->willReturn($testEntityTypeOptions[$entity_type_id]);
      $contentEntityTypeInterfaceMock->hasKey(Argument::any())->willReturn(FALSE);
      $contentEntityTypeInterfaceMock->getKey(Argument::any())->willReturn(FALSE);
      foreach ($entity_type_definition['keys'] as $key => $value) {
        $contentEntityTypeInterfaceMock->hasKey($key)->willReturn(TRUE);
        $contentEntityTypeInterfaceMock->getKey($key)->willReturn($value);
      }
      $contentEntityTypeInterfaceMock = $contentEntityTypeInterfaceMock->reveal();
      $this->entityTypeManager->getDefinition($entity_type_id)->willReturn($contentEntityTypeInterfaceMock);
      $this->testdata['comparisondata']['entity_types'][$entity_type_id] = $contentEntityTypeInterfaceMock;
      $this->testdata['comparisondata']['entity_keys'][$entity_type_id] = $entity_type_definition['keys'];

      // Dynamically define mocked entity type bundle definitions.
      $testEntityBundles = array_combine(
        array_keys($entity_type_definition['bundles']),
        array_map(
          function ($item) use ($entity_type_definition) {
            return ['label' => $entity_type_definition['bundles'][$item]['label']];
          },
          array_keys($entity_type_definition['bundles'])
        )
      );
      $this->entityBundleInfoService->getBundleInfo($entity_type_id)->willReturn($testEntityBundles);
      $this->testdata['comparisondata']['entity_bundles'][$entity_type_id] = $testEntityBundles;
      $this->testdata['comparisondata']['entity_bundle_options'][$entity_type_id] = array_map(
        function ($item) {
          return $item['label'];
        },
        $testEntityBundles
      );

      // Create mocked fields.
      foreach ($entity_type_definition['fields'] as $field_machine_name => &$field_definition) {
        $this->testdata['comparisondata']['fields'][$field_machine_name] = $field_definition;
        $field = $this->prophesize(FieldConfigInterface::class);
        $field->getLabel()->willReturn($field_definition['label']);
        $field->getName()->willReturn($field_machine_name);
        $field->get('field_type')->willReturn($field_definition['field_type']);
        $field->getType()->willReturn($field_definition['field_type']);
        $field->getSetting('handler')->willReturn($field_definition['handler']);
        $field->getSetting('handler_settings')->willReturn($field_definition['handler_settings']);
        $field->getFieldStorageDefinition()->willReturn($field_definition['field_storage_definition']);
        $field->getTargetEntityTypeId()->willReturn($entity_type_id);
        $field_definition = $field->reveal();
        $this->testdata['comparisondata']['fields'][$field_machine_name]['field_config_interface'] = $field_definition;
      }

      // Assign the created mockfields to the respective bundles.
      foreach ($entity_type_definition['bundles'] as $bundle_machine_name => $bundle_definition) {

        $bundlefields = [
          $base_field_a->getName() => $base_field_a,
          $base_field_b->getName() => $base_field_b,
        ];

        $bundleFieldOptions = [
          $base_field_a->getName() => $base_field_a->getLabel(),
          $base_field_b->getName() => $base_field_b->getLabel(),
        ];

        $bundleFieldsWithoutBaseFields = [];
        $bundleFieldOptionsWithoutBasefields = [];

        foreach ($bundle_definition['fields'] as $field_machine_name) {
          $bundlefields[$field_machine_name] = $entity_type_definition['fields'][$field_machine_name];
          $bundleFieldOptions[$field_machine_name] = $entity_type_definition['fields'][$field_machine_name]->getLabel();
          $bundleFieldsWithoutBaseFields[$field_machine_name] = $entity_type_definition['fields'][$field_machine_name];
          $bundleFieldOptionsWithoutBasefields[$field_machine_name] = $entity_type_definition['fields'][$field_machine_name]->getLabel();
        }

        $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_machine_name)->willReturn($bundlefields);
        $this->testdata['comparisondata']['entity_bundle_fields'][$entity_type_id][$bundle_machine_name] = $bundlefields;
        $this->testdata['comparisondata']['entity_bundle_fields_without_basefields'][$entity_type_id][$bundle_machine_name] = $bundleFieldsWithoutBaseFields;
        $this->testdata['comparisondata']['entity_bundle_fields_options'][$entity_type_id][$bundle_machine_name] = $bundleFieldOptions;
        $this->testdata['comparisondata']['entity_bundle_fields_options_without_basefields'][$entity_type_id][$bundle_machine_name] = $bundleFieldOptionsWithoutBasefields;

        $this->entityDisplayRepository->getViewModeOptionsByBundle($entity_type_id, $bundle_machine_name)->willReturn($bundle_definition['viewmodes']);
        $this->testdata['comparisondata']['entity_bundle_viewmodes'][$entity_type_id][$bundle_machine_name] = $bundle_definition['viewmodes'];

      }

    }

  }

  /**
   * Sets up the class instance to test.
   */
  abstract protected function setUpTestObject();

}
