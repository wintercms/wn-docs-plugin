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
