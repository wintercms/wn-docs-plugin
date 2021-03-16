<?php namespace Winter\Docs\Tests\Classes;

use Winter\Docs\Classes\DocsManager;
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
}
