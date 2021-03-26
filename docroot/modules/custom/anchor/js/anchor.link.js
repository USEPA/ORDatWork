(function ($) {

  Drupal.behaviors.AnchorLink = {
    attach: function () {
      const $select = $('#anchors-list');
      const $input_href = $('input[name="attributes[href]"]');
      const href_original_value = $input_href.val();
      const anchorExists = this.anchorExists;
      let anchors = this.searchAnchorsFromCKEditorInstances();

      this.prepareSelectElement($select, anchors);

      // Set active value in select element when href field contains anchor.
      if (anchorExists($select, href_original_value)) {
        $select.val(href_original_value);
      }

      $select.once('DrupalAnchor').each(function () {
        $(this).on('change', function (e) {
          let val = $(this).val();

          if (val !== '') {
            $input_href.val(val);
          }
          else {
            // If the original value was not an anchor, then we restore old value.
            if (href_original_value.substring(0, 1) !== '#') {
              $input_href.val(href_original_value);
            }
            else {
              // Set empty value when original was an anchor.
              $input_href.val('');
            }
          }
        });
      });

      $input_href.once().each(function () {
        $input_href.on('change', function () {
          // Set select value if it exists.
          if (anchorExists($select, $input_href.val())) {
            $select.val($input_href.val());
          }
          else {
            $select.val('');
          }
        });
      });
    },
    /**
     * Get all CKEditor instances and find anchors.
     *
     * @returns {Array}
     */
    searchAnchorsFromCKEditorInstances: function () {
      let anchors = [];

      $.each(CKEDITOR.instances, function (index, value) {
        const $editor_anchors = $("a[id!=''][id][name!=''][name]", value.getData());

        if ($editor_anchors.length > 0) {
          $editor_anchors.each(function (index, anchor) {
            anchors.push({
              id: $(anchor).attr('id'),
              name: $(anchor).attr('name')
            });
          });
        }
      });

      return anchors;
    },
    prepareSelectElement: function ($select, anchors) {
      // Add anchors to the select element.
      $.each(anchors, function (index, value) {
        $select.append('<option value="#' + value.id + '">' + value.name + '</option>')
      });
    },
    anchorExists: function ($select, anchor) {
      return $('option[value="' + anchor + '"]', $select).length > 0;
    }
  };

})(jQuery);
