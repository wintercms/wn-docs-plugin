<?php

namespace Winter\Docs;

use Event;
use Backend;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use System\Classes\PluginBase;
use Winter\Docs\Classes\DocsManager;
use Winter\Docs\Classes\MarkdownDocumentation;
use Winter\Docs\Classes\MarkdownPageIndex;
use Winter\Docs\Classes\PHPApiPageIndex;
use Winter\Storm\Support\Str;

/**
 * Docs plugin.
 *
 * Comprehensive documentation suite for Winter CMS. Allows for the quick generation of docs from Markdown, or a PHP
 * API.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @package winter/wn-docs-plugin
 */
class Plugin extends PluginBase
{
    /**
     * {@inheritDoc}
     */
    public function pluginDetails(): array
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
     * {@inheritDoc}
     */
    public function registerQuickActions(): array
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
     * {@inheritDoc}
     */
    public function registerComponents(): array
    {
        return [
            \Winter\Docs\Components\DocsPage::class => 'docsPage',
            \Winter\Docs\Components\DocsList::class => 'docsList',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishedConfig();
        }

        // Extend mirror paths to mirror assets
        Event::listen('system.console.mirror.extendPaths', function ($paths) {
            $paths->directories = array_merge($paths->directories, [
                'storage/app/docs/processed/*/_assets',
            ]);
        });
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \Winter\Docs\Console\DocsList::class,
            \Winter\Docs\Console\DocsProcess::class,
        ]);
    }


    /**
     * Register published configurations.
     */
    protected function registerPublishedConfig(): void
    {
        $this->publishes([
            __DIR__ . '/config/storage.php' => implode(DIRECTORY_SEPARATOR, [
                $this->app->configPath(),
                'winter',
                'docs',
                'storage.php'
            ])
        ]);
    }

    /**
     * Register search handlers when the Winter.Search plugin is installed.
     *
     * These search handlers automatically allow searching of any registered and processed docs.
     */
    public function registerSearchHandlers(): array
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
                        if (Str::length($record->content) > 200) {
                            $excerpt = Str::substr($record->content, 0, 200) . '...';
                        } else {
                            $excerpt = $record->content;
                        }
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
