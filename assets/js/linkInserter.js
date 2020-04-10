(function ($) {
    $(document).ready(function () {
        var $menuToolbar = $('nav#layout-mainmenu ul.mainmenu-toolbar'),
            isMobile = $('html').hasClass('mobile'),
            backendBasePath = $('meta[name="backend-base-path"]').attr('content')
            $link = $('<li class="mainmenu-docs with-tooltip">'
                + '<a href="' + backendBasePath + '/docs" title="Read the documentation">'
                + '<i class="icon-question-circle"></i>'
                + '</a>'
                + '</li>');

        $menuToolbar.prepend($link);

        $link.children('a').tooltip({
            container: 'body',
            placement: 'bottom',
            template: '<div class="tooltip mainmenu-tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
        })
        .on('show.bs.tooltip', function (e) {
            if (isMobile) {
                e.preventDefault();
            }
        });
    });
})(jQuery);
