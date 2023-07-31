<?php namespace Winter\Docs\Classes;

use Log;
use Lang;
use Config;
use Validator;
use ApplicationException;
use Cms\Classes\Controller;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use System\Classes\PluginManager;

class DocsManager
{
    use \Winter\Storm\Support\Traits\Singleton;

    /** @var array Plugins that have registered documentation */
    protected $plugins = [];

    /** @var array Registered documentation */
    protected $registered = [];

    /** @var \System\Classes\PluginManager Plugin manager instance */
    protected $pluginManager;

    /**
     * Initialises the documentation manager
     *
     * @return void
     */
    protected function init(): void
    {
        $this->pluginManager = PluginManager::instance();

        $this->registerDocumentation();
    }

    /**
     * Registers documentation from plugin and event sources.
     *
     * @return void
     */
    protected function registerDocumentation(): void
    {
        // Load documentation from plugins
        $documentation = $this->pluginManager->getRegistrationMethodValues('registerDocumentation');

        foreach ($documentation as $pluginCode => $docs) {
            if (!is_array($docs)) {
                $errorMessage = sprintf(
                    'The "registerDocumentation" method in plugin "%s" did not return an array.',
                    [$pluginCode]
                );

                if (Config::get('app.debug', false)) {
                    throw new ApplicationException($errorMessage);
                }

                Log::error($errorMessage);
                continue;
            }

            foreach ($docs as $code => $doc) {
                $this->addDocumentation($pluginCode, $code, $doc);
            }
        }
    }

    /**
     * Lists all available documentation.
     *
     * This will return an array of documentation details, with the following keys for each
     * documentation:
     *   - `id`: The identifier of the documentation
     *   - `instance`: An instance of the `Documentation` class for the given documentation.
     *   - `name`: The name of the documentation
     *   - `type`: The type of documentation (one of `user`, `developer`, `api` or `events`)
     *   - `pageUrl`: A URL to the page where this documentation can be viewed, if available.
     *   - `sourceUrl`: A URL to the source repository for the documentation, if available.
     *   - `plugin`: The plugin providing the documentation
     *
     * @return array
     */
    public function listDocumentation(): array
    {
        $docs = [];

        foreach ($this->registered as $id => $doc) {
            $instance = $this->getDocumentation($id);

            $docs[] = [
                'id' => $id,
                'instance' => $instance,
                'name' => $doc['name'],
                'type' => $doc['type'],
                'pageUrl' => $this->getUrl($id),
                'sourceUrl' => $instance->getRepositoryUrl(),
                'plugin' => Lang::get($this->pluginManager
                    ->findByIdentifier($doc['plugin'])
                    ->pluginDetails()['name']),
            ];
        }

        return $docs;
    }

    /**
     * Gets a documentation instance by ID.
     *
     * @param string $id
     * @return BaseDocumentation|null
     */
    public function getDocumentation(string $id): ?BaseDocumentation
    {
        if (!array_key_exists($id, $this->registered)) {
            return null;
        }

        $doc = $this->registered[$id];

        switch ($doc['type']) {
            case 'md':
                return new MarkdownDocumentation($id, $doc);
            case 'php':
                return new PHPApiDocumentation($id, $doc);
        }

        return null;
    }

    /**
     * Adds a documentation instance.
     *
     * @param string $owner
     * @param string $code
     * @param array $config
     * @return bool
     */
    public function addDocumentation(string $owner, string $code, array $config): bool
    {
        // Validate documentation
        $validator = Validator::make($config, [
            'name' => 'required',
            'type' => 'required|in:md,php',
            'source' => 'required|string',
        ], [
            'name' => 'winter.docs::validation.docs.name',
            'type' => 'winter.docs::validation.docs.type',
            'source' => 'winter.docs::validation.docs.source',
        ]);

        if ($validator->fails()) {
            $errorMessage = 'Docs definition is invalid. (' . $validator->errors()->first() . ')';
            if (Config::get('app.debug', false)) {
                throw new ApplicationException($errorMessage);
            }

            Log::error($errorMessage);
            return false;
        }

        if (!in_array($owner, $this->plugins)) {
            $this->plugins[$owner] = [$code];
        } else {
            $this->plugins[$owner][] = $code;
        }

        $this->registered[$this->makeIdentifier($owner, $code)] = $config;
        $this->registered[$this->makeIdentifier($owner, $code)]['plugin'] = $owner;

        return true;
    }

    /**
     * Removes a specified documentation, if registered.
     *
     * @param string $owner
     * @param string $code
     * @return void
     */
    public function removeDocumentation(string $owner, string $code)
    {
        if ($this->hasDocumentation($owner, $code)) {
            unset($this->registered[$this->makeIdentifier($owner, $code)]);
        }
    }

    /**
     * Checks if a specified documentation has been registered.
     *
     * @param string $owner
     * @param string $code
     * @return boolean
     */
    public function hasDocumentation(string $owner, string $code): bool
    {
        return array_key_exists($this->makeIdentifier($owner, $code), $this->registered);
    }

    /**
     * Creates an identifier.
     *
     * @param string $owner
     * @param string $code
     * @return string
     */
    public function makeIdentifier(string $owner, string $code): string
    {
        if (strpos($owner, '.') !== false) {
            [$author, $plugin] = explode('.', $owner, 2);

            $author = preg_replace('/[^a-z0-9]/', '', strtolower($author));
            $plugin = preg_replace('/[^a-z0-9]/', '', strtolower($plugin));
        } else {
            $author = preg_replace('/[^a-z0-9]/', '', strtolower($owner));
            $plugin = null;
        }

        $code = preg_replace('/[^a-z0-9]/', '', strtolower($code));

        return implode('.', array_filter([$author, $plugin, $code]));
    }

    /**
     * Gets the URL to the documentation, based on the page the documentation is connected to.
     */
    public function getUrl(string $id): ?string
    {
        // Find the page that this documentation is connected to
        $theme = Theme::getActiveTheme();
        $page = Page::listInTheme($theme)
            ->withComponent('docsPage', function ($component) use ($id) {
                return $component->property('docId') === $id;
            })
            ->first();
        $controller = new Controller($theme);

        if (!$page) {
            return null;
        }

        return $controller->pageUrl($page->getFileName(), ['slug' => '']);
    }

    /**
     * Handler for the pages.menuitem.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * with the following elements:
     * - references - a list of the item type reference options. The options are returned in the
     *   ["key"] => "title" format for options that don't have sub-options, and in the format
     *   ["key"] => ["title"=>"Option title", "items"=>[...]] for options that have sub-options. Optional,
     *   required only if the menu item type requires references.
     * - nesting - Boolean value indicating whether the item type supports nested items. Optional,
     *   false if omitted.
     * - dynamicItems - Boolean value indicating whether the item type could generate new menu items.
     *   Optional, false if omitted.
     * - cmsPages - a list of CMS pages (objects of the Cms\Classes\Page class), if the item type requires a CMS page reference to
     *   resolve the item URL.
     */
    public static function getMenuTypeInfo(string $type): array
    {
        $results = [];

        if ($type === 'docs') {
            $results = [
                'references'   => [],
                'nesting'      => true,
                'dynamicItems' => true
            ];

            foreach (self::instance()->listDocumentation() as $doc) {
                if (empty($doc['id']) || !$doc['instance']->isProcessed()) {
                    continue;
                }

                $results['references'][$doc['id']] = [
                    'title' => $doc['name'],
                ];
            }
        } elseif ($type === 'docs-page') {
            $results = [
                'references'   => [],
                'nesting'      => false,
                'dynamicItems' => false
            ];

            foreach (self::instance()->listDocumentation() as $doc) {
                if (empty($doc['id']) || !$doc['instance']->isProcessed()) {
                    continue;
                }

                $pageList = $doc['instance']->getPageList();
                $nav = $pageList->getNavigation();
                $items = [];

                $iterator = function ($navItems, $prefix = null) use (&$iterator, $doc, &$items) {
                    foreach ($navItems as $navItem) {
                        if (empty($navItem['path'])) {
                            if (isset($navItem['children']) && count($navItem['children'])) {
                                $iterator($navItem['children'], implode(' / ', array_filter([$prefix, $navItem['title']])));
                            }
                            continue;
                        }

                        $items[$doc['id'] . '||' . $navItem['path']] = [
                            'title' => implode(' / ', array_filter([$prefix, $navItem['title']]))
                        ];
                    }
                };
                $iterator($nav);

                $results['references'][$doc['id']] = [
                    'title' => $doc['name'],
                    'items' => $items,
                ];
            }
        }

        return $results;
    }

    /**
     * Handler for the pages.menuitem.resolveItem event.
     * Returns information about a menu item. The result is an array
     * with the following keys:
     * - url - the menu item URL. Not required for menu item types that return all available records.
     *   The URL should be returned relative to the website root and include the subdirectory, if any.
     *   Use the Url::to() helper to generate the URLs.
     * - isActive - determines whether the menu item is active. Not required for menu item types that
     *   return all available records.
     * - items - an array of arrays with the same keys (url, isActive, items) + the title key.
     *   The items array should be added only if the $item's $nesting property value is TRUE.
     *
     * @param DefinitionItem|MenuItem $item Specifies the menu item.
     */
    public static function resolveMenuItem(string $type, object $item, string $currentUrl, Theme $theme): ?array
    {
        $result = null;

        if ($type === 'docs') {
            $docs = DocsManager::instance()->getDocumentation($item->reference);
            $baseUrl = DocsManager::instance()->getUrl($item->reference);

            if (!$docs) {
                return null;
            }

            $result = [
                'url' => $baseUrl,
                'isActive' => $baseUrl === $currentUrl,
                'isChildActive' => str_starts_with($currentUrl, $baseUrl)
            ];

            if ($item->nesting) {
                $pageList = $docs->getPageList();
                $nav = $pageList->getNavigation();

                $iterator = function ($items) use (&$iterator, $baseUrl, $currentUrl) {
                    $thisLevel = [];

                    foreach ($items as $navItem) {
                        if (empty($navItem['path'])) {
                            $url = false;
                        } elseif ($navItem['external'] ?? false) {
                            $url = $navItem['path'];
                        } else {
                            $url = $baseUrl . '/' . $navItem['path'];
                        }

                        $thisItem = [
                            'title' => $navItem['title'],
                            'external' => $navItem['external'] ?? false,
                            'url' => $url,
                            'isActive' => (isset($navItem['external']) && $navItem['external'] === true)
                                ? false
                                : $url === $currentUrl,
                        ];
                        if (isset($navItem['children']) && count($navItem['children'])) {
                            $thisItem['isChildActive'] = (isset($navItem['external']) && $navItem['external'] === true)
                                ? false
                                : str_starts_with($currentUrl, $url);
                            $thisItem['items'] = $iterator($navItem['children']);
                        }

                        $thisLevel[] = $thisItem;
                    }

                    return $thisLevel;
                };

                $result['items'] = $iterator($nav);
            }
        } elseif ($type === 'docs-page') {
            if (str_contains($item->reference, '||')) {
                [$docId, $pagePath] = explode('||', $item->reference, 2);
            } else {
                $docId = $item->reference;
            }

            $docs = DocsManager::instance()->getDocumentation($docId);
            $baseUrl = DocsManager::instance()->getUrl($docId);

            if (!$docs) {
                return null;
            }

            $pageUrl = $baseUrl . '/' . $pagePath;

            $result = [
                'url' => $pageUrl,
                'isActive' => $pageUrl === $currentUrl,
            ];
        }

        return $result;
    }
}
