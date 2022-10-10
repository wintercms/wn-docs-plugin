<?php namespace Winter\Docs\Classes;

use Log;
use Lang;
use Config;
use Validator;
use ApplicationException;
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

            // Find the page that this documentation is connected to
            $theme = Theme::getActiveTheme();
            $page = Page::listInTheme($theme)
                ->withComponent('docsPage', function ($component) use ($id) {
                    return $component->property('docId') === $id;
                })
                ->first();

            $docs[] = [
                'id' => $id,
                'instance' => $instance,
                'name' => $doc['name'],
                'type' => $doc['type'],
                'pageUrl' => ($page) ? Page::url($page->getFileName(), ['slug' => '']) : null,
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
}
