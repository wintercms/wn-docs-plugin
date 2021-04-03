<?php namespace Winter\Docs\Classes;

use ApplicationException;
use Config;
use Log;
use Validator;
use System\Classes\PluginManager;

class DocsManager
{
    use \Winter\Storm\Support\Traits\Singleton;

    /** @var array Plugins that have registered documentation */
    protected $plugins = [];

    /** @var array Registered documentation */
    protected $registered = [];

    /** @var System\Classes\PluginManager Plugin manager instance */
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
        foreach ($this->pluginManager->getPlugins() as $pluginCode => $pluginObj) {
            if (!method_exists($pluginObj, 'registerDocumentation')) {
                continue;
            }

            $pluginDocs = $pluginObj->registerDocumentation();

            if (!is_array($pluginDocs)) {
                throw new ApplicationException(
                    sprintf(
                        'The "registerDocumentation" method in plugin "%s" did not return an array.',
                        [$pluginCode]
                    )
                );
            }

            foreach ($pluginDocs as $code => $doc) {
                $this->addDocumentation($pluginCode, $code, $doc);
            }
        }
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
            'type' => 'required|in:user,developer,api,events',
            'source' => 'required|in:local,remote',
            'path' => 'required',
        ], [
            'name' => 'winter.docs::lang.validation.docs.name',
            'type' => 'winter.docs::lang.validation.docs.type',
            'source' => 'winter.docs::lang.validation.docs.source',
            'path' => 'winter.docs::lang.validation.docs.path',
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
