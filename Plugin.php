<?php namespace Winter\Docs;

use Backend;
use Event;
use Lang;
use System\Classes\CombineAssets;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'winter.docs::lang.plugin.name',
            'description' => 'winter.docs::lang.plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-tags',
            'homepage'    => 'https://github.com/wintercms/wn-docs-plugin',
            'replaces'    => 'RainLab.Docs'
        ];
    }

    /**
     * Registers back-end quick actions for this plugin.
     *
     * @return array
     */
    public function registerQuickActions()
    {
        return [
            'help' => [
                'label' => 'winter.docs::lang.links.docsLink',
                'icon' => 'icon-question-circle',
                'url' => Backend::url('docs'),
            ],
        ];
    }
}
