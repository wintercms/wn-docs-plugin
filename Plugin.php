<?php
namespace RainLab\Docs;

use Event;
use Lang;
use System\Classes\CombineAssets;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'rainlab.docs::lang.plugin.name',
            'description' => 'rainlab.docs::lang.plugin.description',
            'author'      => 'Ben Thomson',
            'icon'        => 'icon-tags',
            'homepage'    => 'https://github.com/rainlab/docs-plugin'
        ];
    }

    public function register()
    {
        $this->registerAssetBundles();
    }

    public function boot()
    {
        /**
         * Adds a small JS script that inserts the docs link into the top right of the Backend menu, as no event
         * exists that can insert links there.
         */
        Event::listen('backend.page.beforeDisplay', function ($controller) {
            $controller->addCss(url('plugins/rainlab/docs/assets/css/link.css'));
            $controller->addJs(url('plugins/rainlab/docs/assets/js/linkInserter.js'), [
                'id' => 'docs-link',
                'data-label' => Lang::get('rainlab.docs::lang.links.docsLink'),
            ]);
        });
    }

    public function registerAssetBundles()
    {
        CombineAssets::registerCallback(function ($combiner) {
            $combiner->registerBundle('~/plugins/rainlab/docs/assets/less/link.less');
        });
    }
}
