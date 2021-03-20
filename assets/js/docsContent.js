/*
 * Docs Content class
 *
 * Dependences:
 * - Highlight.js (assets/vendor/hightlight/highlight.pack.js)
 */
+function ($) { "use strict";
    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype;

    var HIGHLIGHT_CONFIG = {
        tabReplace: '    ', // 4 spaces
    };

    var DocsContent = function () {
        var self = this;

        Base.call(this);

        $(document).ready(function () {
            self.$el = $('#docs-content');
            $.wn.foundation.controlUtils.markDisposable(self.$el.get(0));
            self.init();
        });
    };

    DocsContent.prototype = Object.create(BaseProto);
    DocsContent.prototype.constructor = DocsContent;

    DocsContent.prototype.init = function () {
        this.$el.one('dispose-control', this.proxy(this.dispose));

        this.anchorPositions = {};

        // Configure highlighter
        hljs.configure(HIGHLIGHT_CONFIG);

        this.setupCodeBlocks();
        this.enableReadTracking();
        this.trackRead();
    };

    DocsContent.prototype.dispose = function () {
        this.$el.off('dispose-control', this.proxy(this.dispose));
        this.$el.find('pre code .code-toolbar a[data-copy]').off('click', this.proxy(this.copyCode));
        $(document).off('scroll', this.proxy(this.trackRead));

        this.$el = null
        BaseProto.dispose.call(this)
    };

    DocsContent.prototype.setupCodeBlocks = function () {
        var self = this;

        this.$el.find('pre code').each(function () {
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
        $toolbar.find('[data-copy]').on('click', this.proxy(this.copyCode));
    };

    DocsContent.prototype.copyCode = function (event) {
        var $this = $(event.target);

        event.preventDefault();

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
    };

    DocsContent.prototype.enableReadTracking = function () {
        var self = this;

        $('#layout-side-panel ul.nav-list li a').each(function () {
            var $this = $(this),
                anchorName = $this.attr('href').replace('#', ''),
                anchor = self.$el.find('a[name="' + anchorName + '"]');

            if (anchor.length > 0) {
                self.anchorPositions[anchorName] = anchor.get(0).offsetTop + 140;
            }
        });

        $(document).on('scroll', this.proxy(this.trackRead));
    };

    DocsContent.prototype.trackRead = function (event) {
        var scrollTop = $(document).scrollTop(),
            scrollBottom = window.innerHeight + scrollTop,
            currentAnchor = null;

        $('#layout-side-panel ul.nav-list li a').removeClass('reading');

        if (scrollTop === 0) {
            $('#layout-side-panel ul.nav-list li a[href="#' + this.getFirstAnchor() + '"]').addClass('reading');
            return;
        }

        if (scrollBottom === $(document).outerHeight()) {
            $('#layout-side-panel ul.nav-list li a[href="#' + this.getLastAnchor() + '"]').addClass('reading');
            return;
        }

        for (var anchor in this.anchorPositions) {
            if (this.anchorPositions[anchor] <= scrollBottom) {
                currentAnchor = anchor;
            }
        }

        $('#layout-side-panel ul.nav-list li a[href="#' + currentAnchor + '"]').addClass('reading');
    };

    DocsContent.prototype.getFirstAnchor = function () {
        var firstAnchor = null;

        for (var anchor in this.anchorPositions) {
            if (firstAnchor === null || this.anchorPositions[firstAnchor] > this.anchorPositions[anchor]) {
                firstAnchor = anchor;
            }
        }

        return firstAnchor;
    };

    DocsContent.prototype.getLastAnchor = function () {
        var lastAnchor = null;

        for (var anchor in this.anchorPositions) {
            if (lastAnchor === null || this.anchorPositions[lastAnchor] < this.anchorPositions[anchor]) {
                lastAnchor = anchor;
            }
        }

        return lastAnchor;
    };

    if ($.wn === undefined) {
        $.wn = {};
    }
    if ($.oc === undefined) {
        $.oc = $.wn;
    }

    $.wn.docsContent = new DocsContent();

}(window.jQuery);
