(function() {
  CKEDITOR.plugins.add('drupalanchor', {
    icons: 'anchor',
    requires: ['dialog', 'fakeobjects'],

    init: function(editor)
    {
      // Add the CSS styles for anchor placeholders.
      var iconPath = CKEDITOR.getUrl(this.path + '/images/anchor.png'),
        baseStyle = 'background:url(' + iconPath + ') no-repeat %1 center;border:1px dotted #00f;background-size:16px;';

      var template = '.%2 a.cke_drupalanchor,' +
        '.%2 a.cke_drupalanchor_empty' +
        ',.cke_editable.%2 a[name]' +
        ',.cke_editable.%2 a[data-cke-saved-name]' +
        '{' +
        baseStyle +
        'padding-%1:18px;' +
        // Show the arrow cursor for the anchor image (FF at least).
        'cursor:auto;' +
        '}' +
        '.%2 img.cke_drupalanchor' +
        '{' +
        baseStyle +
        'width:16px;' +
        'min-height:15px;' +
        // The default line-height on IE.
        'height:1.15em;' +
        // Opera works better with "middle" (even if not perfect)
        'vertical-align:text-bottom;' +
        '}';

      // Styles with contents direction awareness.
      function cssWithDir(dir) {
        return template.replace(/%1/g, dir == 'rtl' ? 'right' : 'left').replace(/%2/g, 'cke_contents_' + dir);
      }

      CKEDITOR.addCss(cssWithDir('ltr') + cssWithDir('rtl'));

      // Register the toolbar buttons.
      editor.ui.addButton('drupalanchor', {
        label: Drupal.t('Insert Anchor'),
        icon: this.path + 'icons/anchor.png',
        command: 'drupalanchor'
      });

      CKEDITOR.dialog.add('drupalanchor_dialog', this.path + '/dialogs/anchor.js');

      editor.addCommand('drupalanchor', new CKEDITOR.dialogCommand('drupalanchor_dialog', {
        allowedContent: 'a[!name,id]',
        requiredContent: 'a[name]'
      }));
    },

    afterInit: function (editor) {
      editor.dataProcessor.dataFilter.addRules({
        elements: {
          a: function (element) {
            if (!element.attributes.name) {
              return null;
            }

            if (!element.children.length) {
              return editor.createFakeParserElement(element, 'cke_drupalanchor_anchor', 'anchor');
            }

            return null;
          }
        }
      });
    }
  });

  CKEDITOR.plugins.drupalanchor = {
    tryRestoreFakeAnchor: function (editor, element) {
      if (element && element.data('cke-real-element-type') && element.data('cke-real-element-type') == 'anchor') {
        var link = editor.restoreRealElement(element);
        if (link.data( 'cke-saved-name')) {
          return link;
        }
      }
    },

    getSelectedLink: function (editor, returnMultiple) {
      var selection = editor.getSelection(),
        selectedElement = selection.getSelectedElement(),
        ranges = selection.getRanges(),
        links = [],
        link,
        range,
        i;

      if (!returnMultiple && selectedElement && selectedElement.is('a')) {
        return selectedElement;
      }

      for (i = 0; i < ranges.length; i++) {
        range = selection.getRanges()[ i ];

        // Skip bogus to cover cases of multiple selection inside tables (#tp2245).
        range.shrink(CKEDITOR.SHRINK_TEXT, false, { skipBogus: true });
        link = editor.elementPath(range.getCommonAncestor()).contains('a', 1);

        if (link && returnMultiple) {
          links.push(link);
        }
        else if (link) {
          return link;
        }
      }

      return returnMultiple ? links : null;
    }
  };

})();
