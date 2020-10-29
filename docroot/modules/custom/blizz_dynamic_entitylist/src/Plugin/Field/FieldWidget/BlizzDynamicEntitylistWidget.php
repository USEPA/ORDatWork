<?php

namespace Drupal\blizz_dynamic_entitylist\Plugin\Field\FieldWidget;

use Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistWidgetServicesInterface;
use Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'blizz_dynamic_entitylist_widget' widget.
 *
 * @FieldWidget(
 *   id = "blizz_dynamic_entitylist_widget",
 *   label = @Translation("Dynamic Entity List Widget"),
 *   field_types = {
 *     "blizz_dynamic_entitylist"
 *   },
 *   multiple_values = FALSE,
 * )
 */
class BlizzDynamicEntitylistWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Custom service to easy the handling of entities.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface
   */
  protected $entityServices;

  /**
   * Custom service providing widget form potions for ajax steps.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistWidgetServicesInterface
   */
  protected $widgetServices;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('blizz_dynamic_entitylist.entity_services'),
      $container->get('blizz_dynamic_entitylist.field_widget_service')
    );
  }

  /**
   * BlizzDynamicEntitylistWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin machine name.
   * @param mixed $plugin_definition
   *   Definition data of the plugin.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition data.
   * @param array $settings
   *   The field settings.
   * @param array $third_party_settings
   *   (Possible) Third party settings.
   * @param \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface $entity_services
   *   Custom service to easy the handling of entities.
   * @param \Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistWidgetServicesInterface $widget_service
   *   Custom service providing widget form potions for ajax steps.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityServicesInterface $entity_services,
    BlizzDynamicEntitylistWidgetServicesInterface $widget_service
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityServices = $entity_services;
    $this->widgetServices = $widget_service;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {

    // If this widget is called for the default value setting screen, simply
    // return a message that we don't utilize default values.
    if (
      !empty($element['#field_parents'][0]) &&
      $element['#field_parents'][0] == 'default_value_input'
    ) {
      return [
        'no_default_value' => [
          '#type' => 'markup',
          '#markup' => $this->t('This field uses no default values.'),
          '#prefix' => '<p>',
          '#sufffix' => '</p>',
        ],
      ];
    }

    // Get the field settings.
    $settings = $this->getFieldSettings();

    // Get a possibly pre-filled value.
    $listdefinition = $this->widgetServices->getListDefinition($items->get(0)->getValue());

    // Prepare a list of selectable entity types.
    $entity_options = $this->entityServices->getContentEntityTypeOptions();

    // Are the selectable entity types narrowed from all types available?
    if (!empty($settings['constraints']['entity_types'])) {
      $entity_options = array_filter(
        $entity_options,
        function ($entity_type_id) use ($settings) {
          return in_array($entity_type_id, $settings['constraints']['entity_types']);
        },
        ARRAY_FILTER_USE_KEY
      );
    }

    // Generate the URLs needed for the javascript configuration object.
    $placeholders = [
      'bundlelist' => [
        'field_id' => 'FIELD_ID',
        'entity_type' => 'ENTITY_TYPE',
      ],
      'viewmodelist' => [
        'field_id' => 'FIELD_ID',
        'entity_type' => 'ENTITY_TYPE',
        'bundle' => 'BUNDLE',
      ],
      'autocomplete' => [
        'entity_type' => 'ENTITY_TYPE',
        'bundle' => 'BUNDLE',
      ],
    ];
    $callback_urls = [
      'bundlelist' => Url::fromRoute('blizz_dynamic_entitylist.ajax-bundlelist', $placeholders['bundlelist'])->setAbsolute(TRUE)->toString(),
      'viewmodelist' => Url::fromRoute('blizz_dynamic_entitylist.ajax-viewmodelist', $placeholders['viewmodelist'])->setAbsolute(TRUE)->toString(),
      'autocomplete' => Url::fromRoute('blizz_dynamic_entitylist.ajax-autocomplete', $placeholders['autocomplete'])->setAbsolute(TRUE)->toString(),
      'preview' => Url::fromRoute('blizz_dynamic_entitylist.ajax-preview')->setAbsolute(TRUE)->toString(),
    ];

    // Make the callback urls protocol independant...
    array_walk(
      $callback_urls,
      function (&$item) {
        $item = str_replace('http:', '', $item);
      }
    );

    // Create the basic field form element.
    $element += [
      '#tree' => TRUE,
      'no_javascript_warning' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This field widget relies on javascript. It is not possible to use with javascript deactivated.'),
        '#attributes' => [
          'class' => [
            'noJavascriptWarning',
          ],
        ],
      ],
      'entity_list' => [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $element['#title'],
        'field_id' => [
          '#type' => 'hidden',
          '#value' => sprintf(
            'field.field.%s.%s.%s',
            $this->fieldDefinition->getTargetEntityTypeId(),
            $this->fieldDefinition->getTargetBundle(),
            $this->fieldDefinition->getName()
          ),
          '#attributes' => [
            'class' => ['blizzdynamicentitylist-fieldid'],
          ],
          '#theme_wrappers' => [],
        ],
        'listdefinition' => [
          '#type' => 'textarea',
          '#default_value' => json_encode($listdefinition),
          '#theme_wrappers' => [],
          '#attributes' => [
            'class' => [
              'blizzdynamicentitylist-definition',
              'visually-hidden',
            ],
          ],
        ],
        'entity_type' => [
          '#type' => 'select',
          '#title' => $this->t('Entity type'),
          '#description' => $this->t('Please select the type of entity you want to build a list of.'),
          '#options' => $entity_options,
          '#empty_option' => $this->t('Please choose'),
          '#required' => $element['#required'],
          '#default_value' => isset($listdefinition['entity_type_id']) ? $listdefinition['entity_type_id'] : NULL,
          '#attributes' => [
            'class' => [
              'blizzdynamicentitylist-selector',
              'select-bundlelist',
            ],
            'data-targetarea' => 'bundle',
            'data-replace-FIELD_ID' => 'input[type="hidden"].blizzdynamicentitylist-fieldid',
            'data-replace-ENTITY_TYPE' => 'select.select-bundlelist',
          ],
        ],
        'bundle' => $this->widgetFormStage2($listdefinition, $settings),
      ],
      '#attached' => [
        'library' => [
          'blizz_dynamic_entitylist/BlizzDynamicEntitylistWidget',
        ],
        'drupalSettings' => [
          'blizz_dynamic_entitylist' => [
            'callback-urls' => $callback_urls,
          ],
        ],
      ],
    ];

    // Is there an entity that should probably excluded in the preview?
    if ($requestentity = $this->widgetServices->getEntityFromRequest()) {
      $element['#attached']['drupalSettings']['blizz_dynamic_entitylist']['current_entity'] = sprintf(
        '%s:%s',
        $requestentity->getEntityTypeId(),
        $requestentity->id()
      );
    }

    // If there is only a single content type available to choose
    // from and the field itself is required to be filled with data,
    // we're going to (visually) hide the select input and reveal the
    // second widget stage.
    if ($element['entity_list']['entity_type']['#required'] && count($entity_options) == 1) {

      // Determine the value of the available option.
      $optionvalue = array_keys($entity_options);
      $optionvalue = array_shift($optionvalue);

      // There is no use for an empty option on a required
      // select with just a single option...
      unset($element['entity_list']['entity_type']['#empty_option']);

      // The default value of this field has to be the option value...
      $element['entity_list']['entity_type']['#default_value'] = $optionvalue;

      // Visually hide this select field...
      $element['entity_list']['entity_type']['#theme_wrappers'] = [];
      $element['entity_list']['entity_type']['#attributes']['class'][] = 'visually-hidden';

      // Make sure the javascript widget triggers the second
      // widget state on empty widgets.
      if (empty($listdefinition)) {
        $element['entity_list']['entity_type']['#attributes']['data-trigger-next-step'] = 1;
      }

    }

    // If the list is already pre-filled, trigger a new preview on startup.
    if (!empty($listdefinition)) {
      $element['entity_list']['#attributes']['data-triggerpreview'] = 1;
    }

    // Return the ready-built form element.
    return $element;
  }

  /**
   * Complete the field widget form definition.
   *
   * Since this widget is a multistep ajax widget, we simply
   * re-use the ajax methods to provide the extending form potions for us.
   *
   * @param array $listdefinition
   *   The current definition of the list.
   * @param array $settings
   *   The current field settings as provided by Drupal.
   *
   * @return array
   *   Form API definition for the first ajax step
   *   (at least an ajax placeholder).
   */
  private function widgetFormStage2(array $listdefinition, array $settings = []) {
    if (empty($listdefinition)) {
      return [
        '#type' => 'markup',
        '#markup' => '<div class="bundle"></div>',
      ];
    }
    else {
      return $this->widgetServices->widgetStage2(
        sprintf('field.field.%s', $this->fieldDefinition->id()),
        $listdefinition['entity_type_id'],
        $listdefinition,
        $this->fieldDefinition->getSettings(),
        TRUE
       ) + ['#prefix' => '<div class="bundle">', '#suffix' => '</div>'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state
  ) {
    $listdefinition = (array) json_decode($values[0]['entity_list']['listdefinition']);
    if (!empty($listdefinition['filters'])) {
      $listdefinition['filters'] = (array) $listdefinition['filters'];
    }
    return serialize(['listdefinition' => $listdefinition]);
  }

}
