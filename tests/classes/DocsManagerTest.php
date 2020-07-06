<?php namespace RainLab\Docs\Tests\Classes;

use RainLab\Docs\Classes\DocsManager;
use TestCase;

class DocsManagerTest extends TestCase
{
    protected $docsManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->docsManager = DocsManager::instance();
    }

    public function testMakeIdentifier()
    {
        $this->assertEquals(
            'rainlab.docs.test',
            $this->docsManager->makeIdentifier('RainLab.Docs', 'test')
        );

        $this->assertEquals(
            'system.docs',
            $this->docsManager->makeIdentifier('system', 'docs')
        );

        $this->assertEquals(
            'rainlab.docstest.docs',
            $this->docsManager->makeIdentifier('RainLab.Docs.Test', 'docs')
        );

        $this->assertEquals(
            'rainlab.docstest',
            $this->docsManager->makeIdentifier('RainLab', 'Docs.TEST')
        );
    }
}
