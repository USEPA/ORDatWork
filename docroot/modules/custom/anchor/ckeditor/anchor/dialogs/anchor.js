(function () {

  CKEDITOR.dialog.add('drupalanchor_dialog', function (editor) {

    var loadElements = function(element) {
      this._.selectedElement = element;

      var attributeValue = element.data( 'cke-saved-name' );
      this.setValueOf( 'info', 'txtName', attributeValue || '' );
    };

    function createFakeAnchor(editor, attributes) {
      return editor.createFakeElement(editor.document.createElement('a', {
        attributes: attributes
      }), 'cke_drupalanchor', 'anchor');
    }

    function getSelectedAnchor(selection) {
      var range = selection.getRanges()[0],
        element = selection.getSelectedElement();

      // In case of table cell selection, we want to shrink selection from td to a element.
      range.shrink(CKEDITOR.SHRINK_ELEMENT);
      element = range.getEnclosedNode();

      if (element && element.type === CKEDITOR.NODE_ELEMENT &&
        (element.data( 'cke-real-element-type' ) === 'anchor' || element.is( 'a' ))) {
        return element;
      }
    }

    return {
      title: Drupal.t('Anchor'),
      minWidth: 300,
      minHeight: 60,
      contents: [
        {
          id: 'info',
          label: Drupal.t('Anchor'),
          elements: [
            {
              type: 'text',
              id: 'txtName',
              label: Drupal.t('Name')
            }
          ]
        }
      ],
      onOk: function () {
        var name = CKEDITOR.tools.trim( this.getValueOf('info', 'txtName'));
        var attributes = {
          id: name,
          name: name,
          'data-cke-saved-name': name
        };

        if (this._.selectedElement) {
          if (this._.selectedElement.data('cke-realelement')) {
            var newFake = createFakeAnchor(editor, attributes);
            newFake.replace(this._.selectedElement);

            // Selecting fake element for IE. (http://dev.ckeditor.com/ticket/11377)
            if (CKEDITOR.env.ie) {
              editor.getSelection().selectElement(newFake);
            }
          }
          else {
            this._.selectedElement.setAttributes(attributes);
          }
        }
        else {
          var sel = editor.getSelection(),
            range = sel && sel.getRanges()[0];

          // Empty anchor.
          if (range.collapsed) {
            var anchor = createFakeAnchor(editor, attributes);
            range.insertNode(anchor);
          }
          else {
            if (CKEDITOR.env.ie && CKEDITOR.env.version < 9)
              attributes['class'] = 'cke_drupalanchor';

            // Apply style.
            var style = new CKEDITOR.style({element: 'a', attributes: attributes});
            style.type = CKEDITOR.STYLE_INLINE;
            style.applyToRange(range);
          }
        }
      },

      onHide: function() {
        delete this._.selectedElement;
      },

      onShow: function() {
        var sel = editor.getSelection(),
          fullySelected = getSelectedAnchor(sel),
          fakeSelected = fullySelected && fullySelected.data( 'cke-realelement' ),
          linkElement = fakeSelected ?
            CKEDITOR.plugins.drupalanchor.tryRestoreFakeAnchor(editor, fullySelected) :
            CKEDITOR.plugins.drupalanchor.getSelectedLink(editor);

        if (linkElement) {
          loadElements.call(this, linkElement);
          !fakeSelected && sel.selectElement(linkElement);

          if (fullySelected) {
            this._.selectedElement = fullySelected;
          }
        }

        this.getContentElement('info', 'txtName').focus();
      }

    };
  });

})();
