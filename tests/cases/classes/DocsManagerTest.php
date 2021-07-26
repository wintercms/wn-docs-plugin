<?php namespace Winter\Docs\Tests\Classes;

use PluginTestCase;
use System\Classes\PluginManager;
use Winter\Docs\Classes\DocsManager;

class DocsManagerTest extends PluginTestCase
{
    protected $docsManager;

    public function setUp(): void
    {
        parent::setUp();

        // Get the plugin manager
        $pluginManager = PluginManager::instance();

        // Register the plugins to make features like file configuration available
        $pluginManager->registerAll(true);

        // Boot all the plugins to test with dependencies of this plugin
        $pluginManager->bootAll(true);

        // Boot this plugin
        $this->runPluginRefreshCommand('Winter.Docs');

        $this->docsManager = DocsManager::instance();
    }

    public function testRegistration()
    {
        // Should be able to see docs registered for this plugin at least.
        $this->assertTrue($this->docsManager->hasDocumentation('Winter.Docs', 'guide'));
    }

    public function testMakeIdentifier()
    {
        $this->assertEquals(
            'winter.docs.test',
            $this->docsManager->makeIdentifier('Winter.Docs', 'test')
        );

        $this->assertEquals(
            'system.docs',
            $this->docsManager->makeIdentifier('system', 'docs')
        );

        $this->assertEquals(
            'winter.docstest.docs',
            $this->docsManager->makeIdentifier('Winter.Docs.Test', 'docs')
        );

        $this->assertEquals(
            'winter.docstest',
            $this->docsManager->makeIdentifier('Winter', 'Docs.TEST')
        );
    }

    public function testAddDocumentation()
    {
        $this->assertFalse($this->docsManager->hasDocumentation('Docs.Test', 'user'));

        $this->docsManager->addDocumentation('Docs.Test', 'user', [
            'name' => 'User Documentation',
            'type' => 'user',
            'source' => 'local',
            'path' => dirname(dirname(__DIR__)) . '/fixtures/user'
        ]);

        $this->assertTrue($this->docsManager->hasDocumentation('Docs.Test', 'user'));

        $this->docsManager->removeDocumentation('Docs.Test', 'user');

        $this->assertFalse($this->docsManager->hasDocumentation('Docs.Test', 'user'));
    }
}
