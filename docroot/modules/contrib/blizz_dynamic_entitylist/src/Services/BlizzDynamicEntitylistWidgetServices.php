<?php

namespace Drupal\blizz_dynamic_entitylist\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpFoundation\Response;
use Zend\Diactoros\Response\JsonResponse;

/**
 * Class BlizzDynamicEntitylistWidgetServices.
 *
 * @package Drupal\blizz_dynamic_entitylist\Services
 */
class BlizzDynamicEntitylistWidgetServices implements BlizzDynamicEntitylistWidgetServicesInterface {

  /**
   * Drupal's rendering service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Drupal's string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslator;

  /**
   * Drupal's router matcher service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatcher;

  /**
   * Drupal's language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Custom service to easy the handling of entities.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface
   */
  protected $entityServices;

  /**
   * Drupal's config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Custom service to exclude entities from lists by id.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistApiInterface
   */
  protected $api;

  /**
   * BlizzDynamicEntitylistWidgetServices constructor.
   *
   * Provides functionality for different steps of the field widget.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Drupal's rendering service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translator
   *   Drupal's string translation service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Drupal's router matcher service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Drupal's language manager service.
   * @param \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface $entity_services
   *   Custom service to easy the handling of entities.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal's config factory service.
   * @param \Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistApiInterface $api
   *   Custom service to exclude entities from lists by id.
   */
  public function __construct(
    RendererInterface $renderer,
    TranslationInterface $string_translator,
    RouteMatchInterface $route_match,
    LanguageManagerInterface $language_manager,
    EntityServicesInterface $entity_services,
    ConfigFactoryInterface $config_factory,
    BlizzDynamicEntitylistApiInterface $api
  ) {
    $this->renderer = $renderer;
    $this->stringTranslator = $string_translator;
    $this->routeMatcher = $route_match;
    $this->languageManager = $language_manager;
    $this->entityServices = $entity_services;
    $this->configFactory = $config_factory;
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public function widgetStage2($field_id, $entity_type, array $listdefinition = [], array $fieldsettings = [], $raw = FALSE) {

    // On ajax calls we need to determine the field settings manually.
    if (empty($fieldsettings)) {
      $fieldsettings = $this->getFieldSettingsFromConfig($field_id);
    }

    // Get an array holding all bundles of the biven entity type id.
    $bundles = $this->entityServices->getEntityTypeBundleOptions($entity_type);
    asort($bundles);

    // Are the selectable bundles narrowed from all bundles available?
    if (!empty($fieldsettings['constraints']['bundles'][$entity_type])) {
      $bundles = array_filter(
        $bundles,
        function ($bundle) use ($fieldsettings, $entity_type) {
          return in_array($bundle, $fieldsettings['constraints']['bundles'][$entity_type]);
        },
        ARRAY_FILTER_USE_KEY
      );
    }

    $elem = [
      '#type' => 'container',
      'bundleselect' => [
        '#type' => 'checkboxes',
        '#title' => $this->stringTranslator->translate('Bundle(s)'),
        '#title_display' => 'before',
        '#description' => $this->stringTranslator->translate('Please select the bundle(s) you would like to get a list of.'),
        '#description_display' => 'after',
        '#options' => $bundles,
        '#default_value' => isset($listdefinition['bundle']) ? $listdefinition['bundle'] : NULL,
        '#required' => TRUE,
        '#attributes' => [
          'class' => [
            'blizzdynamicentitylist-selector',
            'checkboxes-viewmodelist',
          ],
          'data-targetarea' => 'viewmode',
          'data-replace-FIELD_ID' => 'input[type="hidden"].blizzdynamicentitylist-fieldid',
          'data-replace-ENTITY_TYPE' => 'select.select-bundlelist',
          'data-replace-BUNDLE' => 'value.marked',
        ],
      ],
    ];

    if (empty($listdefinition)) {
      $elem['viewmode'] = [
        '#type' => 'markup',
        '#markup' => '<div class="viewmode"></div>',
      ];
    }
    else {
      $elem['viewmode'] = $this->widgetStage3(
        $field_id,
        $listdefinition['entity_type_id'],
        $listdefinition['bundle'],
        $listdefinition,
        $fieldsettings,
        TRUE
      ) + ['#prefix' => '<div class="viewmode">', '#suffix' => '</div>'];
    }

    // If there is only a single bundle to choose from, pre-select this bundle
    // and set the widget to automatically trigger the next widget step.
    if (count($bundles) == 1 && !$raw) {
      // Determine the value of the available option.
      $optionvalue = array_keys($bundles);
      $optionvalue = array_shift($optionvalue);
      $elem['bundleselect']['#default_value'] = [$optionvalue => $optionvalue];
      $elem['bundleselect']['#attributes']['checked'] = 'checked';
      $elem['bundleselect']['#attributes']['data-trigger-next-step'] = 1;
    }

    // If we're in an ajax call, we need to simulate a normal
    // form build procedure, otherwise (multiple) checkboxes won't
    // get generated correctly.
    if (!$raw) {

      // When called in an ajax request, we need to add
      // en element id to the checkboxes.
      $elem['bundleselect']['#id'] = md5((double) microtime(TRUE));

      // Generate fake form objects in order to be able
      // to call the element process function.
      $form_state = new FormState();
      $form = [];

      // We need to inject the default values into the "#value" property.
      $elem['bundleselect']['#value'] = $elem['bundleselect']['#default_value'];

      // Let the element process function generate appropriate child elements.
      $elem['bundleselect'] = Checkboxes::processCheckboxes($elem['bundleselect'], $form_state, $form);
    }

    return $raw ? $elem : new Response($this->renderer->render($elem));
  }

  /**
   * {@inheritdoc}
   */
  public function widgetStage3($field_id, $entity_type, $bundle, array $listdefinition = [], array $fieldsettings = [], $raw = FALSE) {

    // On ajax calls we need to determine the field settings manually.
    if (empty($fieldsettings)) {
      $fieldsettings = $this->getFieldSettingsFromConfig($field_id);
    }

    // Make sure $bundle is an array.
    if (!is_array($bundle)) {
      $bundle = explode(',', $bundle);
    }

    // Prepare a container holding the stage's elements.
    $elem = [
      '#type' => 'container',
      'viewmodes' => [
        '#type' => 'details',
        '#title' => $this->stringTranslator->translate('View modes'),
        '#open' => TRUE,
      ],
    ];

    // Get the human readable bundle names.
    $bundlenames = $this->entityServices->getEntityTypeBundleOptions($entity_type);

    // Prepare a view mode selection for each selected bundle.
    foreach ($bundle as $b) {

      // Get an array of all available view modes for a given
      // entity type/bundle combination.
      $viewmodes = $this->entityServices->getEntityTypeBundleViewmodes($entity_type, $b);

      // Are the selectable bundles narrowed from all bundles available?
      if (!empty($fieldsettings['constraints']['viewmodes'][$entity_type][$b])) {
        $viewmodes = array_filter(
          $viewmodes,
          function ($viewmode) use ($fieldsettings, $entity_type, $b) {
            return in_array($viewmode, $fieldsettings['constraints']['viewmodes'][$entity_type][$b]);
          },
          ARRAY_FILTER_USE_KEY
        );
      }

      if (count($viewmodes) > 1) {
        $viewmodes = [
          '' => $this->stringTranslator->translate('Please choose'),
        ] + $viewmodes;
      }

      // Generate the view mode select element.
      $elem['viewmodes']["viewmodeselect_{$b}"] = [
        '#type' => 'select',
        '#title' => $this->stringTranslator->translate(
          'View mode for Bundle @bundle',
          ['@bundle' => $bundlenames[$b]]
        ),
        '#description' => $this->stringTranslator->translate(
          'Please select the view mode you would like bundle %bundle to be rendered in.',
          ['%bundle' => $bundlenames[$b]]
        ),
        '#description_display' => 'after',
        '#options' => $viewmodes,
        '#default_value' => isset($listdefinition['viewmode']->{$b}) ? $listdefinition['viewmode']->{$b} : NULL,
        '#required' => TRUE,
        '#attributes' => [
          'class' => [
            'blizzdynamicentitylist-selector',
            'data-selector',
            'list-viewmode',
          ],
          'data-bundle' => $b,
        ],
      ];

    }

    // Determine the default list length for new entity lists.
    $default_length = isset($fieldsettings['list_default_length']) ? $fieldsettings['list_default_length'] : 10;

    // Build the remaining form potion of the third widget step.
    $elem += [
      // List length.
      'length' => [
        '#type' => 'number',
        '#title' => $this->stringTranslator->translate('Length of the list'),
        '#description' => $this->stringTranslator->translate('How many entities would you like to include in your list? (0 = infinite)'),
        '#description_display' => 'after',
        '#required' => TRUE,
        '#min' => 0,
        '#value' => isset($listdefinition['length']) ? $listdefinition['length'] : $default_length,
        '#attributes' => [
          'class' => [
            'blizzdynamicentitylist-selector',
            'data-input',
            'list-length',
          ],
        ],
      ],
      // List offset.
      'offset' => [
        '#type' => 'number',
        '#title' => $this->stringTranslator->translate('Offset'),
        '#description' => $this->stringTranslator->translate('How many entities would you like to skip at the beginning of your list? (0 = none)'),
        '#description_display' => 'after',
        '#required' => TRUE,
        '#min' => 0,
        '#value' => isset($listdefinition['offset']) ? $listdefinition['offset'] : 0,
        '#attributes' => [
          'class' => [
            'blizzdynamicentitylist-selector',
            'data-input',
            'list-offset',
          ],
        ],
      ],
      'options' => [
        '#type' => 'details',
        '#title' => $this->stringTranslator->translate('List options'),
        '#attributes' => [
          'class' => [
            'listoptions',
          ],
        ],
        'no_entity_registration' => [
          '#type' => 'checkbox',
          '#title' => $this->stringTranslator->translate('No entity registration'),
          '#title_display' => 'before',
          '#description' => $this->stringTranslator->translate('Activate this option to prevent this particular list from registering its listed entities in the entity ID cache in order to include them in other lists on the same page.'),
          '#return_value' => 'no_entity_registration',
          '#value' => isset($listdefinition['options']->no_entity_registration) && $listdefinition['options']->no_entity_registration ? 'no_entity_registration' : NULL,
          '#attributes' => [
            'class' => [
              'data-input',
              'switchButton',
            ],
            'data-label-active' => $this->stringTranslator->translate('No registration'),
            'data-label-inactive' => $this->stringTranslator->translate('default behaviour'),
          ],
        ],
        'no_entity_exclusion' => [
          '#type' => 'checkbox',
          '#title' => $this->stringTranslator->translate('No entity exclusion'),
          '#title_display' => 'before',
          '#description' => $this->stringTranslator->translate('Activate this option if this particular list should display all matching entities, regardless if already shown in other lists.'),
          '#return_value' => 'no_entity_exclusion',
          '#value' => isset($listdefinition['options']->no_entity_exclusion) && $listdefinition['options']->no_entity_exclusion ? 'no_entity_exclusion' : NULL,
          '#attributes' => [
            'class' => [
              'data-input',
              'switchButton',
            ],
            'data-label-active' => $this->stringTranslator->translate('No exclusion'),
            'data-label-inactive' => $this->stringTranslator->translate('default behaviour'),
          ],
        ],
      ],
      'filters' => [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->stringTranslator->translate('Filters'),
        '#description' => $this->stringTranslator->translate('Should there be any filter criteria applied to the entities selected?'),
        '#attributes' => [
          'class' => [
            'filters',
          ],
        ],
        'filtercontainer' => [
          '#type' => 'container',
          'nofilters' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->stringTranslator->translate('The selected bundles do not share filterable fields.'),
          ],
        ],
      ],
      'preview' => [
        '#type' => 'details',
        '#title' => $this->stringTranslator->translate('Preview'),
        '#open' => FALSE,
        'previewarea' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => [
              'preview',
            ],
          ],
        ],
      ],
    ];

    // Taxonomy term reference fields will be defined like this:
    $taxonomyReferenceFieldCriteria = [
      'getType' => 'entity_reference',
      'getSetting' => [
        'arguments' => ['handler'],
        'value' => 'default:taxonomy_term',
      ],
    ];

    // Are there any taxonomy term reference fields within the selected
    // entity type/bundle combination?
    $taxonomyReferenceFields = $this->entityServices->getEntityTypeBundleFields(
      $entity_type,
      $bundle,
      $taxonomyReferenceFieldCriteria
    );

    // If there are taxRef fields, generate filter fields as well.
    if (!empty($taxonomyReferenceFields)) {

      // Remove the "no filters available" message.
      unset($elem['filters']['filtercontainer']['nofilters']);

      // Fetch the labels of the fields.
      $taxonomyReferenceFieldOptions = $this->entityServices->getEntityTypeBundleFieldOptions(
        $entity_type,
        $bundle,
        $taxonomyReferenceFieldCriteria
      );

      // Generate the filter fields.
      foreach ($taxonomyReferenceFieldOptions as $field_machine_name => $label) {

        // Get the field definition.
        $field_definition = $taxonomyReferenceFields[$field_machine_name];

        // Determine the cardinality of the field.
        $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();

        // Can this field hold more than a single value?
        $multiple = ($cardinality == -1 || $cardinality > 1) ? 1 : 0;

        // Which target bundles are targeted?
        $target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'];

        // Add the filter field definition.
        $elem['filters']['filtercontainer'][$field_machine_name] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'filtercontainer',
            ],
          ],
          'filtervalue' => [
            '#type' => 'select',
            // Intentionally no field name, explanation see below.
            '#name' => FALSE,
            '#title' => $label,
            '#attributes' => [
              'class' => [
                'blizzdynamicentitylist-selector',
                'autocomplete-taxonomy_term',
                'filtervalue',
              ],
              'data-sourcefield' => $field_machine_name,
              'data-targetbundles' => implode(', ', $target_bundles),
              'data-multiple' => $multiple,
              'data-maximumselectionlength' => $cardinality,
            ],
          ],
        ];

        /*
         * So why do the 'filtervalue' fields use no field name?
         *
         * Simply: they are not readily built at the time of the form
         * generation, instead options get filled in via custom Ajax calls.
         *
         * So, if an option not present at the time of the form generation would
         * be chosen, Drupal would detect an error ("An illegal choice has been
         * detected. Please contact the site administrator.") at the time of
         * form submission.
         *
         * By denying this field a field name, this field will simply not get
         * transmitted within the request headers and Drupal will not try to
         * validate it. Since the widget gets serialized and this field's single
         * value is not of importance, we can just ignore it.
         */

        // If the field cardinality allows for multiple values, ...
        if ($multiple) {

          // ...set an appropriate html attribute.
          $elem['filters']['filtercontainer'][$field_machine_name]['filtervalue']['#attributes']['multiple'] = 'multiple';

        }

        // Prepopulate filtervalue fields with potential data.
        if (!empty($listdefinition['filters'][$field_machine_name])) {

          // Shortcut for convenience...
          $input_element = &$elem['filters']['filtercontainer'][$field_machine_name]['filtervalue'];

          // Load the referenced entities to generate complete field options.
          $terms = $this->entityServices->loadEntities(
            'taxonomy_term',
            is_array($listdefinition['filters'][$field_machine_name])
              ? $listdefinition['filters'][$field_machine_name]
              : [$listdefinition['filters'][$field_machine_name]]
          );

          // Generate the chosen field options.
          $input_element['#options'] = array_map(
            function (ContentEntityInterface $term) {
              return $term->label();
            },
            $terms
          );

          // Set the chosen options also as default values.
          $input_element['#default_value'] = is_array($listdefinition['filters'][$field_machine_name])
            ? array_combine(
                array_values($listdefinition['filters'][$field_machine_name]),
                array_values($listdefinition['filters'][$field_machine_name])
              )
            : $listdefinition['filters'][$field_machine_name];

        }
      }

    }

    return $raw ? $elem : new Response($this->renderer->render($elem));
  }

  /**
   * {@inheritdoc}
   */
  public function handleAutocomplete($entity_type, $bundle) {
    $entityBundleOptions = $this->entityServices->getEntityTypeBundleOptions($entity_type);
    $query = $this->entityServices->getQuery($entity_type);
    if ($bundlekey = $this->entityServices->getEntityKey($entity_type, 'bundle')) {
      $query->condition($bundlekey, explode(',', $bundle), 'IN');
      $query->sort($bundlekey, 'ASC');
    }
    if ($labelkey = $this->entityServices->getEntityKey($entity_type, 'label')) {
      if (!empty($_GET['term'])) {
        $query->condition($labelkey, sprintf('%%%s%%', $_GET['term']), 'LIKE');
      }
      $query->sort($labelkey, 'ASC');
    }
    $ids = $query->execute();
    $entities = $this->entityServices->loadEntities($entity_type, $ids);

    $bundles = array_unique(array_map(
      function (ContentEntityInterface $entity) {
        return $entity->bundle();
      },
      $entities
    ));

    $results = [];
    foreach ($bundles as $bundle) {
      $optgroup = [
        'text' => $entityBundleOptions[$bundle],
        'children' => array_values(array_map(
          function (ContentEntityInterface $entity) {
            return [
              'id' => $entity->id(),
              'text' => $entity->label(),
            ];
          },
          array_filter(
            $entities,
            function (ContentEntityInterface $entity) use ($bundle) {
              return $entity->bundle() == $bundle;
            }
          )
        )),
      ];
      $results[] = $optgroup;
    }

    return new JsonResponse([
      'results' => array_values($results) ?: [],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getListDefinition(array $fieldcontents) {
    if (!empty($fieldcontents) && is_array($fieldcontents) && isset($fieldcontents['listdefinition'])) {
      if ($listdefinition = unserialize($fieldcontents['listdefinition'])) {
        $listdefinition = array_shift($listdefinition);
        if (empty(array_diff_key(
          array_flip([
            'entity_type_id',
            'bundle',
            'viewmode',
            'length',
            'offset',
            'filters',
          ]),
          $listdefinition))
        ) {
          return $listdefinition;
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getListEntities(array $listdefinition, $register_ids, $preview_mode = FALSE) {

    // Fetch the entity field definition(s).
    $entity_bundle_fields = $this->entityServices->getEntityTypeBundleFields(
      $listdefinition['entity_type_id'],
      $listdefinition['bundle'],
      [],
      FALSE
    );

    // Create the entity query.
    $query = $this->entityServices->getQuery($listdefinition['entity_type_id']);

    // Apply the bundle condition.
    if ($bundlekey = $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'bundle')) {
      $query->condition(
        $bundlekey,
        $listdefinition['bundle'],
        'IN'
      );
    }

    // Are there any entities that should get excluded?
    $no_entity_exclusion = isset($listdefinition['options']->no_entity_exclusion) && $listdefinition['options']->no_entity_exclusion;
    if (!$no_entity_exclusion && ($entitiesToExclude = $this->api->getExcludedEntities($listdefinition['entity_type_id']))) {
      $query->condition(
        $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'id'),
        $entitiesToExclude,
        'NOT IN'
      );
    }

    // If the entities feature a "published" flag, load just published entities.
    if ($statuskey = $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'status')) {
      $query->condition(
        $statuskey,
        1,
        '='
      );
    }

    // If the entities feature a "langcode", the request language
    // should serve as a constraint, too.
    if ($langcodekey = $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'langcode')) {
      $query->condition(
        $langcodekey,
        $this->languageManager->getCurrentLanguage()->getId(),
        '='
      );
    }

    // Determine if we're on an entity path and if that entity
    // matches the list criteria - if so, we need to exclude the
    // currently displayed entity in order to avoid recursion.
    if (
      ($requestentity = $this->getEntityFromRequest()) &&
      $requestentity->getEntityTypeId() == $listdefinition['entity_type_id']
    ) {
      $query->condition(
        $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'id'),
        $requestentity->id(),
        '<>'
      );
    }

    // If there is a specific item marked to exclude, actually exclude it.
    if (isset($listdefinition['exclude'])) {
      $exclude = explode(':', $listdefinition['exclude']);
      if ($exclude[0] == $listdefinition['entity_type_id']) {
        $query->condition(
          $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'id'),
          $exclude[1],
          '<>'
        );
      }
    }

    // Determine the maximum number of entities requested.
    if ($preview_mode) {
      // Max. 10 entities in preview mode...
      $length = ($listdefinition['length'] != 0 && $listdefinition['length'] < 10) ? $listdefinition['length'] : 10;
    }
    else {
      $length = $listdefinition['length'] != 0 ? $listdefinition['length'] : 999999;
    }

    // Apply offset and length conditions.
    $query->range(
      $listdefinition['offset'],
      $length
    );

    // Apply any filters defined, but only if the fields actually exist.
    if (!empty($listdefinition['filters'])) {
      foreach ($listdefinition['filters'] as $field => $value) {
        if (isset($entity_bundle_fields[$field]) && !empty($value)) {
          $query->condition(
            $field,
            $value,
            is_array($value) ? 'IN' : '='
          );
        }
      }
    }

    // Sorting.
    if (isset($entity_bundle_fields['created'])) {
      // Order the list to show newest entities first.
      $query->sort('created', 'DESC');
    }
    else {
      // If the entity type does not feature a "created" timestamp, we'll
      // sort the list based upon the entity label.
      $query->sort(
        $this->entityServices->getEntityKey($listdefinition['entity_type_id'], 'label'),
        'ASC'
      );
    }

    // Perform an access check on matching entities.
    $query->accessCheck(TRUE);

    // Execute the query.
    $result = $query->execute();

    // If we're not in preview mode, feed the entity ids
    // determined to the id cache (if configured).
    $no_entity_registration = isset($listdefinition['options']->no_entity_registration) && $listdefinition['options']->no_entity_registration;
    if ($register_ids && !$no_entity_registration && !$preview_mode) {
      $this->api->excludeEntitiesFromLists(
        $listdefinition['entity_type_id'],
        $result
      );
    }

    // Return the result.
    return $result;

  }

  /**
   * {@inheritdoc}
   */
  public function getListPreviewByAjax() {
    $listdefinition = [
      'entity_type_id' => $_POST['entity_type_id'],
      'bundle' => isset($_POST['bundle']) ? $_POST['bundle'] : NULL,
      'length' => isset($_POST['length']) ? (int) $_POST['length'] : 10,
      'offset' => isset($_POST['offset']) ? (int) $_POST['offset'] : 0,
      'filters' => [],
    ];

    if (!empty($_POST['filters'])) {
      foreach ($_POST['filters'] as $field => $value) {
        $listdefinition['filters'][$field] = $value;
      }
    }

    // Are we editing an existing entity? This will be marked to be excluded.
    if (!empty($_POST['exclude'])) {
      $listdefinition['exclude'] = $_POST['exclude'];
    }

    $ids = $this->getListEntities($listdefinition, FALSE,TRUE);
    $entities = $this->entityServices->loadEntities($listdefinition['entity_type_id'], $ids);

    return $this->listPreview($_POST['entity_type_id'], $_POST['bundle'], $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function listPreview($entity_type_id, $bundle, array $entities = [], $raw = FALSE) {
    $entity_type_options = $this->entityServices->getContentEntityTypeOptions();
    $bundleOptions = $this->entityServices->getEntityTypeBundleOptions($entity_type_id);

    array_walk(
      $entities,
      function (ContentEntityInterface &$value, $key) use ($bundleOptions) {
        $entity = $value;
        $value = [
          $entity->toLink($entity->label(), 'canonical', ['absolute' => TRUE, 'attributes' => ['target' => '_blank']]),
          $bundleOptions[$entity->bundle()],
          $key,
        ];
      }
    );

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->stringTranslator->translate('Entity label'),
        $this->stringTranslator->translate('Bundle'),
        $this->stringTranslator->translate('Entity ID'),
      ],
      '#rows' => array_values($entities),
      '#cache' => [
        'max-age' => 0,
      ],
      '#prefix' => sprintf(
        '<div>%s</div>',
        $this->stringTranslator->translate(
          'Listing Entities of type @entity_type',
          [
            '@entity_type' => $entity_type_options[$entity_type_id],
          ]
        )
      ),
      '#suffix' => sprintf(
        '<div>%s</div>',
        $this->stringTranslator->translate('Preview mode: displaying max. 10 items')
      ),
    ];

    return $raw ? $table : new Response($this->renderer->render($table));
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRequest() {
    // A potential entity will be found in the route parameters.
    if (($route = $this->routeMatcher->getRouteObject()) && ($parameters = $route->getOption('parameters'))) {
      // Determine if the current route represents an entity.
      foreach ($parameters as $name => $options) {
        if (isset($options['type']) && preg_match('~^entity:~i', $options['type'])) {
          $entity = $this->routeMatcher->getParameter($name);
          if ($entity instanceof ContentEntityInterface && $entity->hasLinkTemplate('canonical')) {
            return $entity;
          }
        }
      }
    }
  }

  /**
   * Fetches the field storage definition from the active config.
   *
   * @param string $field_id
   *   The configuration identifier of the field in question.
   *
   * @return array
   *   The field storage settings.
   */
  private function getFieldSettingsFromConfig($field_id) {
    $fieldConfig = $this->configFactory->get($field_id);
    return $fieldConfig->get('settings');
  }

}
