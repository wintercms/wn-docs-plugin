/*
 * Docs Content class
 *
 * Dependences:
 * - Waterfall plugin (waterfall.js)
 */
+function ($) { "use strict";

    var DocsContent = function () {
        this.init();
    }

    DocsContent.prototype.init = function () {

        $(document).ready(function () {
            $('.docs-content pre').addClass('prettyprint');
            prettyPrint();
        })

    }

    if ($.oc === undefined) {
        $.oc = {};
    }

    $.oc.docsContent = new DocsContent();

}(window.jQuery);
