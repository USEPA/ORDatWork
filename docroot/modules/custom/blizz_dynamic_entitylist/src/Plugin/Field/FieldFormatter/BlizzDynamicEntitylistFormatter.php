<?php

namespace Drupal\blizz_dynamic_entitylist\Plugin\Field\FieldFormatter;

use Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistWidgetServicesInterface;
use Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the 'blizz_dynamic_entitylist' formatter.
 *
 * @FieldFormatter(
 *   id = "blizz_dynamic_entitylist_formatter",
 *   label = @Translation("Dynamic Entity List Formatter"),
 *   field_types = {
 *     "blizz_dynamic_entitylist"
 *   }
 * )
 */
class BlizzDynamicEntitylistFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The currently processed request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Drupal's router matcher service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatcher;

  /**
   * Custom service to ease the handling of entities.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface
   */
  protected $entityServices;

  /**
   * Custom service to ease the handling of list definitions.
   *
   * @var \Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistWidgetServicesInterface
   */
  protected $entitylistWidgetServices;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration,
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('blizz_dynamic_entitylist.entity_services'),
      $container->get('blizz_dynamic_entitylist.field_widget_service')
    );
  }

  /**
   * BlizzDynamicEntitylistFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugnin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param array $configuration
   *   The configuration array.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Drupal's router matcher service.
   * @param \Drupal\blizz_dynamic_entitylist\Services\EntityServicesInterface $entity_services
   *   Custom service to ease the handling of entities.
   * @param \Drupal\blizz_dynamic_entitylist\Services\BlizzDynamicEntitylistWidgetServicesInterface $entity_list_widget_services
   *   Custom service to ease the handling of list definitions.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    array $configuration,
    RequestStack $request_stack,
    RouteMatchInterface $route_match,
    EntityServicesInterface $entity_services,
    BlizzDynamicEntitylistWidgetServicesInterface $entity_list_widget_services
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings']
    );
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->routeMatcher = $route_match;
    $this->entityServices = $entity_services;
    $this->entitylistWidgetServices = $entity_list_widget_services;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'preview_on_admin_routes' => TRUE,
      'register_result_entity_ids' => TRUE,
      'wrapper' => 'div',
      'css_classes' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();

    return [
      'preview_on_admin_routes' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Preview only on admin routes?'),
        '#description' => $this->t('This list of entities may be included on entity edit routes in a preview mode. Should only a brief preview get displayed instead of the full list content?'),
        '#default_value' => $settings['preview_on_admin_routes'],
      ],
      'register_result_entity_ids' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Exclude entities shown in this list on other lists?'),
        '#description' => $this->t('If there are multiple dynamic entity lists on a single page listing the same entity type, doublets may occur based upon the list definition. Check this checkmark if you want subsequent entity lists to exclude already displayed entities. This setting has no impact on the list preview. (Note: "first come, first display" - the first rendered list "wins" the entity.)'),
        '#default_value' => $settings['register_result_entity_ids'],
      ],
      'wrapper' => [
        '#type' => 'select',
        '#title' => $this->t('Wrapper element'),
        '#description' => $this->t('What kind of wrapper element should the list get wrapped in?'),
        '#options' => [
          'div' => $this->t('HTML Div element'),
          'section' => $this->t('HTML5 Section element'),
          'aside' => $this->t('HTML5 Aside element'),
          'none' => $this->t('No wrapper'),
        ],
        '#default_value' => $settings['wrapper'],
        '#required' => TRUE,
      ],
      'css_classes' => [
        '#type' => 'textfield',
        '#title' => $this->t('Additional CSS classes'),
        '#description' => $this->t('Should the rendered element get any additional CSS classes?'),
        '#default_value' => $settings['css_classes'],
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();

    $summary = [
      $this->t('Exclude Entities: @state', ['@state' => $settings['register_result_entity_ids'] ? $this->t('enabled') : $this->t('disabled')]),
      $this->t('Wrapper: @wrapper', ['@wrapper' => $settings['wrapper']]),
      $this->t('Additional CSS classes: @classes', ['@classes' => !empty($settings['css_classes']) ? $settings['css_classes'] : 'none']),
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    return $this->viewElements($items, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $listdefinition = $this->entitylistWidgetServices->getListDefinition($items->get(0)->getValue());

    if (!empty($listdefinition)) {

      // Determine the display settings.
      $settings = $this->getSettings();

      // Are we in a preview mode right now?
      $preview_mode = $settings['preview_on_admin_routes'] && (bool) $this->routeMatcher->getRouteObject()->getOption('_admin_route');

      // Build the list of entities according to the criteria.
      $entity_ids = $this->entitylistWidgetServices->getListEntities($listdefinition, $settings['register_result_entity_ids'], $preview_mode);

      // If there are entity ids returned, build a list.
      if (!empty($entity_ids)) {

        // Load the actual entities.
        $entities = $this->entityServices->loadEntities($listdefinition['entity_type_id'], $entity_ids);

        // If we are in a preview mode we'll simply render
        // a table containing the entity labels.
        if ($preview_mode) {
          return $this->entitylistWidgetServices->listPreview(
            $listdefinition['entity_type_id'],
            $listdefinition['bundle'],
            $entities,
            TRUE
          );
        }

        // Build an array of cache tags for this list.
        $cachetags = array_values(array_map(
          function ($id) use ($listdefinition) {
            return sprintf(
              '%s:%d',
              $listdefinition['entity_type_id'],
              $id
            );
          },
          $entity_ids
        ));

        // Add common list cachetags.
        $cachetags[] = 'dynamic_entity_list';
        foreach ($listdefinition['bundle'] as $bundle) {
          $cachetags[] = sprintf(
            'dynamic_entity_list:%1$s:%2$s',
            $listdefinition['entity_type_id'],
            $bundle
          );
          $cachetags[] = sprintf(
            'config:core.entity_view_display.%1$s.%2$s.%3$s',
            $listdefinition['entity_type_id'],
            $bundle,
            $listdefinition['viewmode']->{$bundle}
          );
        }

        // Get the view builder of the entity type defined.
        $viewbuilder = $this->entityServices->getEntityViewbuilder($listdefinition['entity_type_id']);

        // Let the view builder build the render array.
        $renderarray = [];
        foreach ($entities as $entity) {
          /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $renderarray[] = $viewbuilder->view(
            $entity,
            $listdefinition['viewmode']->{$entity->bundle()},
            $this->currentRequest->getLocale()
          );
        }

        $renderarray += [
          // Enrich the render array with caching information.
          '#cache' => [
            'tags' => $cachetags,
          ],
        ];

        // Should there be a wrapper (and css classes)?
        if (!empty($settings['wrapper']) && $settings['wrapper'] != 'none') {
          $renderarray['#prefix'] = sprintf(
            '<%1$s class="dynamic_entity_list%2$s">',
            $settings['wrapper'],
            (!empty($settings['css_classes']) ? sprintf(' %s', $settings['css_classes']) : '')
          );
          $renderarray['#suffix'] = sprintf(
            '</%1$s>',
            $settings['wrapper']
          );
        }

        // Return the render array.
        return $renderarray;

      }

    }

    // Return an element with "no access" to prevent empty html containers.
    return ['#access' => FALSE];

  }

}
