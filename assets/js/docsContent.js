/*
 * Docs Content class
 *
 * Dependences:
 * - Highlight.js (assets/vendor/hightlight/highlight.pack.js)
 */
+function ($) { "use strict";

    var HIGHLIGHT_CONFIG = {
        tabReplace: '    ', // 4 spaces
    };

    var DocsContent = function () {
        this.init();
    };

    DocsContent.prototype.init = function () {
        var self = this;

        $(document).ready(function () {
            // Configure highlighter
            hljs.configure(HIGHLIGHT_CONFIG);

            self.setupCodeBlocks();
        });
    };

    DocsContent.prototype.setupCodeBlocks = function () {
        var self = this;

        $('#docs-content pre code').each(function () {
            // Store original code with element
            $(this).data('code', $(this).text());

            // Enable highlighting
            hljs.highlightBlock(this);

            // Enable copying
            self.enableCopy(this);
        });
    };

    DocsContent.prototype.enableCopy = function (element) {
        var
            copyLabel = $('#docs-content-js').data('lang-copy-code'),
            $toolbar = $('' +
                '<div class="code-toolbar">' +
                    '<a href="#" class="has-tooltip" title="' + copyLabel + '" data-copy>' +
                        '<i class="icon-clone"></i>' +
                    '</a>' +
                '</div>'
            ),
            $element = $(element);

        // Append to code block
        $element.append($toolbar);

        // Add tooltip to link
        $toolbar.find('.has-tooltip').tooltip({
            container: 'body',
            placement: 'top',
            template: '<div class="tooltip mainmenu-tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
        });

        // Add copy functionality
        $toolbar.find('[data-copy]').on('click', function (e) {
            var $this = $(this);

            e.preventDefault();

            // Create temporary textarea with code
            var $textarea = $('' +
                '<textarea style="position: absolute; bottom: 0; right: 0; height: 1px; width: 1px">' +
                '</textarea>'
            );
            $('body').append($textarea);
            $textarea.val($this.closest('code').data('code'));

            // Copy content
            $textarea.get(0).select();
            document.execCommand('copy');

            // Remove textarea
            $textarea.remove();
        });
    };

    if ($.oc === undefined) {
        $.oc = {};
    }

    $.oc.docsContent = new DocsContent();

}(window.jQuery);
