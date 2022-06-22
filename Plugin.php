<?php namespace Winter\Docs;

use Backend;
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

    /**
     * @inheritDoc
     */
    public function registerComponents()
    {
        return [
            \Winter\Docs\Components\DocsPage::class => 'docsPage',
            \Winter\Docs\Components\DocsList::class => 'docsList',
        ];
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    /**
     * Register commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            \Winter\Docs\Console\DocsList::class,
            \Winter\Docs\Console\DocsProcess::class,
        ]);
    }
}
