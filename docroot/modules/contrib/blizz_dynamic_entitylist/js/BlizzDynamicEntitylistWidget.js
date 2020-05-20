/**
 * @file
 * Contains the javascript implementation of the dynamic entitylist widget.
 *
 * @package Drupal\blizz_dynamic_entitylist
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.BlizzDynamicEntitylistWidget = {

    ajaxDelay: 200,

    serializationTimeout: {},

    getAllAttributes: function (elem, filter) {

      // "filter" is an optional parameter.
      if (typeof filter === 'undefined') {
        filter = false;
      }

      // Prepare an object holding the results.
      var attributes = [];

      // Determine all attributes and their respective values.
      $(elem).each(function () {
        $.each(this.attributes, function () {
          if (this.specified) {
            attributes[this.name] = this.value;
          }
        });
      });

      // If there is a filter set, use it.
      if (filter !== false) {
        var unfiltered = $.extend([], attributes);
        attributes = [];
        for (var key in unfiltered) {
          if (unfiltered.hasOwnProperty(key) && key.match(new RegExp(filter))) {
            attributes[key] = unfiltered[key];
          }
        }
      }

      // Return the result.
      return attributes;
    },

    bindEvents: function (elem) {
      var classes = $(elem).attr('class').split(' ');
      var callback = false;
      var arg;
      for (var index in classes) {
        if (classes.hasOwnProperty(index)) {
          switch (true) {
            case (/^select-/).test(classes[index]):
              callback = 'ajaxSelectOnChange';
              arg = classes[index].split('-').pop();
              break;

            case (/^checkboxes-/).test(classes[index]):
              callback = 'ajaxCheckboxesOnChange';
              arg = classes[index].split('-').pop();
              break;

            case (/^autocomplete-/).test(classes[index]):
              callback = 'autocomplete';
              arg = classes[index].split('-').pop();
              break;

            case (/^data-selector/).test(classes[index]):
              callback = 'selectOnChange';
              arg = null;
              break;

            case (/^data-input/).test(classes[index]):
              callback = 'onChange';
              arg = null;
              break;

          }
          if (callback) {
            break;
          }
        }
      }

      if (callback !== false) {
        Drupal.BlizzDynamicEntitylistWidget[callback](elem, arg);
      }
    },

    ajaxSelectOnChange: function (elem, argument) {
      $(elem).bind(
        'change',
        function () {

          // Determine the DOM context of the select changed.
          var $fieldContainer = $(this).parents().filter('.field--type-blizz-dynamic-entitylist');

          // Which element should hold the results?
          var targetarea = $(this).attr('data-targetarea');

          if ($(this).val() !== '') {

            // Determine the URL to make a call to.
            var url = drupalSettings.blizz_dynamic_entitylist['callback-urls'][argument];

            // Are there any placeholders needing to be replaced with actual values?
            var placeholders = Drupal.BlizzDynamicEntitylistWidget.getAllAttributes(elem, '^data\-replace\-');

            // Process each placeholder defined...
            for (var index in placeholders) {
              if (placeholders.hasOwnProperty(index)) {
                // Determine the actual string of the placeholder.
                var placeholder = index.replace('data-replace-', '').toUpperCase();

                // Replace the placeholder string in the URL with the actual value.
                url = url.replace(placeholder, $(placeholders[index], $fieldContainer).val());
              }
            }

            // Make the ajax call.
            $.ajax({
              url: url,
              dataType: 'html',
              success: function (data) {
                // Insert the response into the DOM.
                $('.' + targetarea, $fieldContainer).html(data);
                // Re-initialise any elements that need ajax functionalities.
                Drupal.BlizzDynamicEntitylistWidget.init();
              },
              complete: function () {
                // If there is an element contained in the response stating to auto-trigger
                // the next step, then auto-trigger and remove the attribute.
                $('*[data-trigger-next-step="1"]').each(function (index, elem) {
                  $(elem).trigger('change').removeAttr('data-trigger-next-step');
                });
              }
            });
          }
          else {
            // Empty the target container if the element's value is switched back to empty.
            $('.' + targetarea, $fieldContainer).html('');
          }

          // Refresh list definition after selection change.
          Drupal.BlizzDynamicEntitylistWidget.scheduleSerialization(
            $(this).parents().filter('.field--type-blizz-dynamic-entitylist')
          );

        }
      );
    },

    ajaxCheckboxesOnChange: function (elem, argument) {
      $(elem).bind(
        'change',
        function () {

          // Determine the DOM context of the select changed.
          var $fieldContainer = $(this).parents().filter('.field--type-blizz-dynamic-entitylist');

          // Which element should hold the results?
          var targetarea = $(this).attr('data-targetarea');

          // Determine the value of the checkboxes checked.
          var vars = {marked: []};
          var $container = $(this).parents().filter('.form-checkboxes');
          $('input[type="checkbox"]', $container).each(function (index, checkbox) {
            if ($(checkbox).is(':checked')) {
              vars.marked.push($(checkbox).val());
            }
          });
          vars.marked = vars.marked.join(',');

          // If there are checkboxes actually chosen, perform an ajax request upon the selection.
          if (vars.marked !== '') {

            // Determine the URL to make a call to.
            var url = drupalSettings.blizz_dynamic_entitylist['callback-urls'][argument];

            // Are there any placeholders needing to be replaced with actual values?
            var placeholders = Drupal.BlizzDynamicEntitylistWidget.getAllAttributes(elem, '^data\-replace\-');

            // Process each placeholder defined...
            for (var index in placeholders) {
              // Only act on the property iterated if it is not inherited.
              if (placeholders.hasOwnProperty(index)) {
                // Determine the actual string of the placeholder.
                var placeholder = index.replace('data-replace-', '').toUpperCase();

                if ((/^value\./).test(placeholders[index])) {
                  // Determine the name of the dynamic variable.
                  var varname = placeholders[index].split('.').pop();

                  // Replace the placeholder with the value indicated.
                  url = url.replace(placeholder, vars[varname]);
                }
                else {
                  // Replace the placeholder string in the URL with the actual value of the css sselector.
                  url = url.replace(placeholder, $(placeholders[index], $fieldContainer).val());
                }
              }
            }

            // Make the ajax call.
            $.ajax({
              url: url,
              dataType: 'html',
              success: function (data) {
                // Insert the response into the DOM.
                $('.' + targetarea, $fieldContainer).html(data);
                // Re-initialise any elements that need ajax functionalities.
                Drupal.BlizzDynamicEntitylistWidget.init();
              },
              complete: function () {
                // If there is an element contained in the response stating to auto-trigger
                // the next step, then auto-trigger and remove the attribute.
                $('*[data-trigger-next-step="1"]').each(function (index, elem) {
                  $(elem).trigger('change').removeAttr('data-trigger-next-step');
                });
              }
            });
          }
          else {
            // Empty the target container if the element's value is switched back to empty.
            $('.' + targetarea, $fieldContainer).html('');
          }

          // Refresh list definition after selection change.
          Drupal.BlizzDynamicEntitylistWidget.scheduleSerialization(
            $(this).parents().filter('.field--type-blizz-dynamic-entitylist')
          );

        }
      );
    },

    selectOnChange: function (elem) {
      // Refresh list definition after selection change.
      $(elem).bind(
        'change',
        function () {
          Drupal.BlizzDynamicEntitylistWidget.scheduleSerialization(
            $(this).parents().filter('.field--type-blizz-dynamic-entitylist')
          );
        }
      );
    },

    autocomplete: function (elem, argument) {

      // Determine the URL to make a call to.
      var url = drupalSettings.blizz_dynamic_entitylist['callback-urls']['autocomplete'];
      url = url.replace('ENTITY_TYPE', argument);

      var multipleSelect = (Number($(elem).attr('data-multiple')) === 1);
      var options = {
        width: '300px',
        multiple: multipleSelect,
        allowClear: true,
        placeholder: '',
        position: 'below',
        ajax: {
          url: url.replace('BUNDLE', $(elem).attr('data-targetbundles')),
          dataType: 'json',
          data: function (params) {
            return {
              term: params.term
            };
          }
        }
      };

      if (multipleSelect) {
        options.maximumSelectionLength = Number($(elem).attr('data-maximumselectionlength'));
      }

      // Bind the autocomplete event / select2 library to the select field.
      $(elem).select2(options);

      // Refresh list definition after selection change.
      $(elem).bind(
        'change',
        function () {
          Drupal.BlizzDynamicEntitylistWidget.scheduleSerialization(
            $(this).parents().filter('.field--type-blizz-dynamic-entitylist')
          );
        }
      );

    },

    onChange: function (elem) {
      // Refresh list definition after value change.
      $(elem).bind(
        'change',
        function () {
          Drupal.BlizzDynamicEntitylistWidget.scheduleSerialization(
            $(this).parents().filter('.field--type-blizz-dynamic-entitylist')
          );
        }
      );
    },

    readListdefinition: function (elem) {
      // Determine the bundles selected.
      var bundles = [];
      $('.form-checkboxes input[type="checkbox"]', elem).each(function (index, checkbox) {
        if ($(checkbox).is(':checked')) {
          bundles.push($(checkbox).val());
        }
      });

      // Collect the view modes selected.
      var viewmodes = {};
      for (var i in bundles) {
        if (bundles.hasOwnProperty(i)) {
          var bundle = bundles[i];
          viewmodes[bundle] = $('select.list-viewmode[data-bundle="' + bundle + '"]', elem).val();
        }
      }

      // Collect all list options.
      var listoptions = {};
      $('details.listoptions input', elem).each(function (index, input) {
        listoptions[$(input).val()] = $(input).is(':checked');
      });

      // Construct the list definition object.
      var definition = {
        entity_type_id: $('select.select-bundlelist', elem).val(),
        bundle: bundles,
        viewmode: viewmodes,
        options: listoptions,
        length: $('input.list-length', elem).val(),
        offset: $('input.list-offset', elem).val(),
        filters: {}
      };

      // Collect all filters set.
      $('.filters select.filtervalue', elem).each(function (index, elem) {
        definition.filters[$(elem).attr('data-sourcefield')] = $(elem).val();
      });

      return definition;
    },

    scheduleSerialization: function (elem) {
      var listId = $(elem).attr('id');
      if (typeof Drupal.BlizzDynamicEntitylistWidget.serializationTimeout[listId] !== 'undefined') {
        clearTimeout(Drupal.BlizzDynamicEntitylistWidget.serializationTimeout[listId]);
      }
      Drupal.BlizzDynamicEntitylistWidget.serializationTimeout[listId] = setTimeout(function () {
        Drupal.BlizzDynamicEntitylistWidget.serializeWidget(elem);
        delete Drupal.BlizzDynamicEntitylistWidget.serializationTimeout[listId];
      }, Drupal.BlizzDynamicEntitylistWidget.ajaxDelay);
    },

    serializeWidget: function (elem) {

      // The field holding the serialized list definition.
      var $listdefinition = $('.blizzdynamicentitylist-definition', elem);

      // Read the current list definition of the widget.
      var definition = Drupal.BlizzDynamicEntitylistWidget.readListdefinition(elem);

      // Set the list definition as the json'd value of the field definition holder.
      $listdefinition.val(JSON.stringify(definition));

      // Trigger a new preview.
      Drupal.BlizzDynamicEntitylistWidget.listPreview(definition, elem);

    },

    listPreview: function (definition, elem) {
      if (definition.entity_type_id && definition.bundle.length) {
        if (typeof drupalSettings.blizz_dynamic_entitylist.current_entity !== 'undefined') {
          definition.exclude = drupalSettings.blizz_dynamic_entitylist.current_entity;
        }
        $.ajax({
          url: drupalSettings.blizz_dynamic_entitylist['callback-urls'].preview,
          dataType: 'html',
          cache: false,
          method: 'post',
          data: definition,
          success: function (preview) {
            $('.preview', elem).html(preview);
          }
        });
      }
    },

    init: function () {

      // Remove the "no javascript warning".
      $('.field--type-blizz-dynamic-entitylist p.noJavascriptWarning').remove();

      /*
        Since this widget relies upon external libraries, we will make sure these
        libraries are loaded before initializing the widget's fields.

        Reason:
        It may be possible, that this field is initially rendered in some kind of
        a "preview" mode when editing it's host entity, so this field widget library
        will get loaded by a subsequent ajax request when entering the "edit" mode
        for this field. If so, it seems that external libraries like the "Select2"
        library defined in this module will not get loaded properly and therefore
        this widget will throw a javascript error, resulting in useless select fields.
       */

      var external_libraries = [
        {
          toTest: $.easing,
          js: 'https://cdn.rawgit.com/gdsmith/jquery.easing/v1.4.0/jquery.easing.min.js'
        },
        {
          toTest: $.fn.switchButton,
          css: 'https://cdn.rawgit.com/olance/jQuery-switchButton/master/jquery.switchButton.css',
          js: 'https://cdn.rawgit.com/olance/jQuery-switchButton/master/jquery.switchButton.js'
        },
        {
          toTest: $.fn.select2,
          css: 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css',
          js: 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'
        }
      ];

      var initDelayed = false;
      for (var library in external_libraries) {
        if (external_libraries.hasOwnProperty(library) && typeof external_libraries[library].toTest === 'undefined') {
          initDelayed = true;
          if (typeof external_libraries[library].css !== 'undefined') {
            $('<link>')
              .appendTo('head')
              .attr({
                type: 'text/css',
                rel: 'stylesheet',
                href: external_libraries[library].css
              });
          }
          $.ajax({
            async: false,
            url: external_libraries[library].js,
            dataType: 'script',
            success: function () {
              Drupal.BlizzDynamicEntitylistWidget.init();
            }
          });
        }
      }

      if (!initDelayed) {

        $('.field--type-blizz-dynamic-entitylist').each(function (index, entitylist) {

          // Determine the input elements that need to be bound to automatic events.
          $('.blizzdynamicentitylist-selector', entitylist).once('bindEvents').each(function (index, selector) {

            // Bind input functionality.
            Drupal.BlizzDynamicEntitylistWidget.bindEvents(selector);

            // Prepopulate further widget steps, if there is only a single entity type
            // available for selection and no default field values are present.
            $('*[data-trigger-next-step="1"]', entitylist).each(function (index, elem) {
              var $bundleSelect = $('select.select-viewmodelist', entitylist);
              if ($bundleSelect.length < 1 || ($bundleSelect.length && $bundleSelect.val() === '')) {
                $(elem).trigger('change').removeAttr('data-trigger-next-step');
              }
            });

            // Trigger a fresh preview on pre-filled field instances.
            $('details[data-triggerpreview="1"]', entitylist).parent().once('triggerpreview').each(function (index, elem) {
              Drupal.BlizzDynamicEntitylistWidget.listPreview(
                Drupal.BlizzDynamicEntitylistWidget.readListdefinition(elem),
                elem
              );
            });

          });

          // Initialize switchbutton checkboxes.
          $('input[type="checkbox"].switchButton', entitylist).each(function (index, checkbox) {
            $(checkbox).switchButton({
              on_label: $(checkbox).data('label-active'),
              off_label: $(checkbox).data('label-inactive')
            });
            if ($(checkbox).hasClass('data-input')) {
              $(checkbox).bind('change', function () {
                Drupal.BlizzDynamicEntitylistWidget.scheduleSerialization(
                  $(this).parents().filter('.field--type-blizz-dynamic-entitylist')
                );
              });
            }
          });

        });

      }

    }

  };

  Drupal.behaviors.BlizzDynamicEntitylist = {
    attach: function () {
      Drupal.BlizzDynamicEntitylistWidget.init();
    }
  };

}(jQuery, Drupal, drupalSettings));
