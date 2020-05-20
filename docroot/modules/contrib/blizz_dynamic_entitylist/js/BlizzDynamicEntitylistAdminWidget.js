/**
 * @file
 * Contains the javascript implementation of the dynamic entitylist admin widget.
 *
 * @package Drupal\blizz_dynamic_entitylist
 */

(function ($, Drupal) {

  'use strict';

  Drupal.BlizzDynamicEntitylistAdminWidget = {

   /**
    * Determines which entity types are listable and marks the appropriate menu tabs.
    *
    * @param {jQuery} $tabmenu
    *   The jQuery tabmenu object.
    * @param {jQuery} $settingsContainer
    *   The jQuery settings container object.
    *
    * @return {array}
    *   An array of the selected entity types.
    */
    markListableEntityTypes: function ($tabmenu, $settingsContainer) {

      // First, determine which entity types are listable.
      var selected_entity_types = [];
      $('input.entityTypeSwitch', $settingsContainer).each(function (index, checkbox) {
        if ($(checkbox).is(':checked')) {
          selected_entity_types[index] = $(checkbox).val();
        }
      });

      // No entity type is checked, so all entity types are listable.
      if (!selected_entity_types.length) {
        $('input.entityTypeSwitch', $settingsContainer).each(function (index, checkbox) {
          selected_entity_types[index] = $(checkbox).val();
        });
      }

      // Clean up any classes assigned before.
      $('li', $tabmenu).removeClass('listable notlistable');

      // Add the "selected" or "unselected" classes to the tabmenu items.
      $('li', $tabmenu).each(function (index, listitem) {
        if (selected_entity_types.hasOwnProperty(index) && typeof selected_entity_types[index] !== 'undefined') {
          $('li:nth-child(' + (index + 1) + ')', $tabmenu).addClass('listable');
        }
        else {
          $('li:nth-child(' + (index + 1) + ')', $tabmenu).addClass('notlistable');
        }
      });

      return selected_entity_types;

    },

    /**
     * Disables and unchecks all checkboxes from unlistable entities.
     *
     * @param {jQuery} $settingsContainer
     *   The jQuery settings container object.
     * @param {array} selected_entities
     *   The array of the selected entity types.
     */
    disableUnselectedEntityTypeSettings: function ($settingsContainer, selected_entities) {
      $settingsContainer.each(function (index, container) {
        // Enable all checkboxes by default.
        $('input[type="checkbox"]', container).enableCheckbox();
        if (typeof selected_entities[index] === 'undefined') {
          // This entity type is not listable.
          $('input[type="checkbox"]', container).not('.entityTypeSwitch').disableCheckbox();
        }
      });
    },

    /**
     * Sets the state of the view mode selector checkboxes.
     *
     * @param {jQuery} $settingsContainer
     *   The jQuery settings container object.
     * @param {array} selected_entities
     *   The array of the selected entity types.
     */
    disableUnselectedBundleViewModes: function ($settingsContainer, selected_entities) {
      $settingsContainer.each(function (index, container) {
        // Only perform anything if this entity type is enabled at all...
        if (selected_entities.indexOf($('input.entityTypeSwitch', container).val()) !== -1) {

          // First, determine selected bundles (if any).
          var selectedBundles = [];
          $('div.bundleContainer', container).each(function (bundleIndex, bundleContainer) {
            var bundle = $('input[type="hidden"].bundle', bundleContainer).val();
            if ($('input.bundleSwitch', bundleContainer).is(':checked')) {
              selectedBundles[bundleIndex] = bundle;
            }
          });

          // If there are bundles selected, disable the view modes of the unselected ones.
          if (selectedBundles.length) {
            $('div.bundleContainer', container).each(function (bundleIndex, bundleContainer) {
              // If the current bundle is not selected, disable it's view mode selectors.
              if (typeof selectedBundles[bundleIndex] === 'undefined') {
                $('input[type="checkbox"].bundleViewmodes', bundleContainer).disableCheckbox();
              }
            });
          }

        }
      });
    },

    /**
     * Stack of method calls to properly initialize the checkbox states.
     */
    setWidgetState: function () {
      var selected_entity_types = Drupal.BlizzDynamicEntitylistAdminWidget.markListableEntityTypes(
        Drupal.BlizzDynamicEntitylistAdminWidget.$tabmenu,
        Drupal.BlizzDynamicEntitylistAdminWidget.$entitySettingsContainer
      );
      // Disable the checkboxes in the unlistable entity types.
      Drupal.BlizzDynamicEntitylistAdminWidget.disableUnselectedEntityTypeSettings(
        Drupal.BlizzDynamicEntitylistAdminWidget.$entitySettingsContainer,
        selected_entity_types
      );
      // Disable checkboxes in the unlistable entity bundles.
      Drupal.BlizzDynamicEntitylistAdminWidget.disableUnselectedBundleViewModes(
        Drupal.BlizzDynamicEntitylistAdminWidget.$entitySettingsContainer,
        selected_entity_types
      );
    },

    /**
     * Initializes the admin interface of the "blizz_dynamic_entitylist" field settings.
     */
    init: function () {

      // Determine some contexts...
      Drupal.BlizzDynamicEntitylistAdminWidget.$form = $('form.field-config-edit-form');
      Drupal.BlizzDynamicEntitylistAdminWidget.$tabmenu = $('ul.vertical-tabs__menu', Drupal.BlizzDynamicEntitylistAdminWidget.$form);
      Drupal.BlizzDynamicEntitylistAdminWidget.$entitySettingsContainer = $('details.entityTypeSettingsContainer', Drupal.BlizzDynamicEntitylistAdminWidget.$form);

      // Initially set the widget state.
      Drupal.BlizzDynamicEntitylistAdminWidget.setWidgetState();

      // Bind change events.
      $('input.entityTypeSwitch, input.bundleSwitch', Drupal.BlizzDynamicEntitylistAdminWidget.$entitySettingsContainer).bind(
        'change',
        Drupal.BlizzDynamicEntitylistAdminWidget.setWidgetState
      );
    }

  };

  /**
   * A little helper for enabling drupal checkboxes.
   *
   * @return {jQuery}
   *   Returns the jQuery object that was thrown in.
   */
  $.fn.enableCheckbox = function () {
    if ($(this).is('input[type="checkbox"]')) {
      $('label', $(this).parent()).removeClass('disabledLabel');
      $(this).removeAttr('disabled');
    }
    return this;
  };

  /**
   * A little helper for disabling drupal checkboxes.
   *
   * @param {bool} removeCheckmark
   *   Flag indicating if a checkmark should be removed (if set).
   *
   * @return {jQuery}
   *   Returns the jQuery object that was thrown in.
   */
  $.fn.disableCheckbox = function (removeCheckmark) {
    if (typeof removeCheckmark === 'undefined') {
      removeCheckmark = false;
    }
    if ($(this).is('input[type="checkbox"]')) {
      $('label', $(this).parent()).addClass('disabledLabel');
      $(this).attr({disabled: 'disabled'});
      if (removeCheckmark) {
        $(this).prop('checked', false);
      }
    }
    return this;
  };

  Drupal.behaviors.BlizzDynamicEntitylistAdminWidget = {
    attach: function () {
      Drupal.BlizzDynamicEntitylistAdminWidget.init();
    }
  };

}(jQuery, Drupal));
