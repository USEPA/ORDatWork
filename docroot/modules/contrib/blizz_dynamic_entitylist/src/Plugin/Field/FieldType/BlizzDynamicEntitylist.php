<?php

namespace Drupal\blizz_dynamic_entitylist\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'blizz_dynamic_entitylist' field type.
 *
 * @FieldType(
 *   id = "blizz_dynamic_entitylist",
 *   label = @Translation("Dynamic Entity list"),
 *   description = @Translation("Provides a field type that lets you define automatic lists of entities based upon selection criteria."),
 *   default_widget = "blizz_dynamic_entitylist_widget",
 *   default_formatter = "blizz_dynamic_entitylist_formatter"
 * )
 */
class BlizzDynamicEntitylist extends FieldItemBase {

  /**
   * Drupal's string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslator;

  /**
   * Custom service to easy the handling of entities.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface
   */
  protected $entityServices;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->stringTranslator = \Drupal::service('string_translation');
    $this->entityServices = \Drupal::service('blizz_dynamic_entitylist.entity_services');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'list_default_length' => 10,
      'constraints' => [],
      'constraints_version' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {

    // Retrieve the current field settings.
    $settings = $this->getSettings();

    // The default list length for this field.
    $element['list_default_length'] = [
      '#type' => 'number',
      '#title' => $this->t('List default length'),
      '#description' => $this->t('Enter the default length of a new entity list (0 = infinite).'),
      '#default_value' => isset($settings['list_default_length']) ? $settings['list_default_length'] : 10,
      '#min' => 0,
      '#max' => 9999,
      '#required' => TRUE,
    ];

    // The base container of our constraint interface.
    $element['constraints'] = [
      '#tree' => TRUE,
      '#theme_wrappers' => ['vertical_tabs'],
      '#attached' => [
        'library' => [
          'blizz_dynamic_entitylist/BlizzDynamicEntitylistAdminWidget',
        ],
      ],
      'vertical_tabs' => [
        '#type' => 'vertical_tabs',
      ],
    ];

    // Generate a tab for every content entity type available in the system.
    foreach ($this->entityServices->getContentEntityTypeOptions() as $entity_type => $entity_type_label) {

      // Determine if this entity type is enabled for listings.
      if (
        isset($settings['constraints']['entity_types']) &&
        is_array($settings['constraints']['entity_types']) &&
        in_array($entity_type, $settings['constraints']['entity_types'])
      ) {
        $entity_type_enabled = $entity_type;
      }
      else {
        $entity_type_enabled = 0;
      }

      // Generate the entity type tab.
      $element['constraints']["{$entity_type}_container"] = [
        '#type' => 'details',
        '#title' => $entity_type_label,
        '#group' => 'vertical_tabs',
        '#attributes' => [
          'class' => [
            'entityTypeSettingsContainer',
          ],
        ],
        'entity_type' => [
          '#type' => 'value',
          '#value' => $entity_type,
        ],
        'listenabled' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable this entity type for listings'),
          '#description' => $this->t('Leave unchecked on all entity types to make all types available for listings.'),
          '#default_value' => $entity_type_enabled,
          '#return_value' => $entity_type,
          '#attributes' => [
            'class' => [
              'entityTypeSwitch',
            ],
          ],
        ],
        'bundles' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Bundle restrictions'),
          '#description' => $this->t('Leave all bundle checkboxes unchecked to make all bundles available for listings. Leave all view mode checkboxes unchecked to make all view modes available for listings.'),
        ],
      ];

      // Generate the per-bundle settings.
      foreach ($this->entityServices->getEntityTypeBundleOptions($entity_type) as $bundle => $bundle_label) {

        if (
          isset($settings['constraints']['bundles'][$entity_type]) &&
          in_array($bundle, $settings['constraints']['bundles'][$entity_type])
        ) {
          $bundle_active = 'active';
        }
        else {
          $bundle_active = 0;
        }

        $viewmodes = isset($settings['constraints']['viewmodes'][$entity_type][$bundle])
          ? $settings['constraints']['viewmodes'][$entity_type][$bundle]
          : [];

        $element['constraints']["{$entity_type}_container"]['bundles']["{$bundle}_container"] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'bundleContainer',
            ],
          ],
          'bundle' => [
            '#type' => 'hidden',
            '#value' => $bundle,
            '#attributes' => [
              'class' => [
                'bundle',
              ],
            ],
          ],
          $bundle => [
            '#type' => 'checkbox',
            '#title' => $this->t('"%bundle" bundle', ['%bundle' => $bundle_label]),
            '#return_value' => 'active',
            '#default_value' => $bundle_active,
            '#attributes' => [
              'class' => [
                'bundleSwitch',
              ],
            ],
          ],
          'viewmodes' => [
            '#type' => 'checkboxes',
            '#title' => $this->t('View mode restrictions'),
            '#title_display' => 'before',
            '#options' => $this->entityServices->getEntityTypeBundleViewmodes($entity_type, $bundle),
            '#default_value' => $viewmodes,
            '#attributes' => [
              'class' => [
                'indented_checkboxes',
                'bundleViewmodes',
              ],
            ],
          ],
        ];

      }

    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldSettingsToConfigData(array $settings) {

    // Readily preprocessed settings get a version indicator...
    if (
      !isset($settings['constraints_version']) ||
      (isset($settings['constraints_version']) && $settings['constraints_version'] === FALSE)
    ) {

      // Make a copy of the configuration entered...
      $configuration = $settings['constraints'];

      // ...in order to have a clean settings object.
      $settings['constraints'] = [];

      // Retrieve activated entity types (if any).
      $settings['constraints']['entity_types'] = array_values(
        array_map(
          function ($container) {
            return $container['entity_type'];
          },
          array_filter(
            $configuration,
            function ($container) {
              return isset($container['entity_type']) && $container['listenabled'];
            }
          )
        )
      );

      // Extract bundle and view mode configuration.
      $bundles = [];
      $viewmodes = [];
      foreach ($configuration as $container) {

        // Skip the "vertical tabs" element...
        if (!isset($container['entity_type'])) {
          continue;
        }

        // Extract any selected bundles.
        $bundles[$container['entity_type']] = array_values(
          array_map(
            function ($container) {
              return $container['bundle'];
            },
            array_filter(
              $container['bundles'],
              function ($container) {
                return $container[$container['bundle']];
              }
            )
          )
        );

        // Determine if any view modes have been selected.
        foreach ($container['bundles'] as $bundlecontainer) {
          $viewmodes[$container['entity_type']][$bundlecontainer['bundle']] = array_filter($bundlecontainer['viewmodes']);
        }

        // If entity types are pre-selected, we don't need the
        // information on the unselected entity types.
        $viewmodes = array_filter(
          $viewmodes,
          function ($key) use ($settings) {
            return in_array($key, $settings['constraints']['entity_types']) || empty($settings['constraints']['entity_types']);
          },
          ARRAY_FILTER_USE_KEY
        );

      }

      if (empty($settings['constraints']['entity_types'])) {
        // If all entity types are listable, we need the bundle
        // constraint information on all entity types.
        $settings['constraints']['bundles'] = $bundles;
      }
      else {
        // If just a few entity types have been pre-selected, ignore the
        // bundle constraint information on unselected entity types in
        // order to keep the constraint definition as simple as possible.
        $settings['constraints']['bundles'] = array_filter(
          $bundles,
          function ($key) use ($settings) {
            return in_array($key, $settings['constraints']['entity_types']);
          },
          ARRAY_FILTER_USE_KEY
        );
      }

      // Further simplify the constraint information on the bundle view modes.
      foreach ($viewmodes as $entity_type => &$bundles) {
        if (!empty($settings['constraints']['bundles'][$entity_type])) {
          $bundles = array_filter(
            $bundles,
            function ($key) use ($settings, $entity_type) {
              return in_array($key, $settings['constraints']['bundles'][$entity_type]);
            },
            ARRAY_FILTER_USE_KEY
          );
        }
        foreach ($bundles as &$selected_viewmodes) {
          $selected_viewmodes = array_values($selected_viewmodes);
        }
      }
      $settings['constraints']['viewmodes'] = $viewmodes;

      // Flag these settings as already readily processed. This is done to
      // be able to distinct between configuration imports and form submission
      // and to determine the structure version of these settings.
      $settings['constraints_version'] = 2;

    }

    return parent::fieldSettingsToConfigData($settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['listdefinition'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('The (serialized) list definition of this entity list instance.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'listdefinition' => [
          'description' => 'The (serialized) list definition of this entity list instance.',
          'type' => 'blob',
          'length' => 'normal',
          'serialize' => TRUE,
          'not null' => TRUE,
          'binary' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->get('listdefinition')->getValue());
  }

}
