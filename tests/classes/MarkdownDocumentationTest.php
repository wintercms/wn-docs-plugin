<?php

namespace Winter\Docs\Tests\Classes;

use Illuminate\Support\Facades\Storage;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Docs\Classes\MarkdownDocumentation;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Support\Facades\File;

/**
 * @covers \Winter\Docs\Classes\MarkdownDocumentation
 * @testdox The Markdown documentation processor (\Winter\Docs\Classes\MarkdownDocumentation)
 */
class MarkdownDocumentationTest extends PluginTestCase
{
    protected string $path;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->path = sys_get_temp_dir() . '/winter-docs-test-' . uniqid();
        File::copyDirectory(dirname(__DIR__) . '/fixtures/user', $this->path);
    }

    public function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    /**
     * @testdox processes local documentation that has a table of contents.
     */
    public function testProcessWithTableOfContents(): void
    {
        $docs = $this->makeDocs();
        $docs->process();

        $this->assertTrue(Storage::disk('local')->exists($docs->getProcessedPath('toc.json')));
        $this->assertNotEmpty(json_decode(Storage::disk('local')->get($docs->getProcessedPath('page-map.json')), true));
    }

    /**
     * @testdox throws a clear error when the table of contents file is missing.
     */
    public function testProcessWithoutTableOfContentsThrows(): void
    {
        File::delete($this->path . '/toc.yaml');

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('No table of contents file was found for the "test.docs" documentation');

        $this->makeDocs()->process();
    }

    protected function makeDocs(): MarkdownDocumentation
    {
        return new MarkdownDocumentation('test.docs', [
            'name' => 'Test documentation',
            'type' => 'md',
            'source' => 'local',
            'path' => $this->path,
            'tocPath' => 'toc.yaml',
        ]);
    }
}
