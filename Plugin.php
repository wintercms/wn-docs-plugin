<?php namespace Winter\Docs;

use Backend;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use System\Classes\PluginBase;
use Winter\Docs\Classes\DocsManager;
use Winter\Docs\Classes\MarkdownDocumentation;
use Winter\Docs\Classes\MarkdownPageIndex;
use Winter\Docs\Classes\PHPApiPageIndex;
use Winter\Storm\Support\Str;

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

    public function registerSearchHandlers()
    {
        $handlers = [];
        $docs = DocsManager::instance()->listDocumentation();

        foreach ($docs as $doc) {
            if (!$doc['instance']->isProcessed()) {
                continue;
            }

            // Find page connected to this documentation.
            $theme = Theme::getActiveTheme();
            $page = Page::inTheme($theme)->whereComponent('docsPage', 'docId', $doc['id'])->first();

            $handlers['docs.' . $doc['id']] = [
                'name' => $doc['name'],
                'model' => function () use ($doc) {
                    if ($doc['instance'] instanceof MarkdownDocumentation) {
                        MarkdownPageIndex::setPageList($doc['instance']->getPageList());

                        return new MarkdownPageIndex();
                    }

                    PHPApiPageIndex::setPageList($doc['instance']->getPageList());

                    return new PHPApiPageIndex();
                },
                'record' => function ($record, $query) use ($page) {
                    $excerpt = Str::excerpt($record->content, $query);

                    if (is_null($excerpt)) {
                        $excerpt = Str::substr($record->content, 0, 100);
                    }

                    return [
                        'title' => $record->title,
                        'description' => $excerpt,
                        'url' => Page::url($page->getBaseFileName(), ['slug' => $record->path]),
                    ];
                },
            ];
        }

        return $handlers;
    }
}
