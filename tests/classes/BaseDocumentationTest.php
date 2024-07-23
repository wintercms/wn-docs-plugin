<?php

namespace Winter\Docs\Tests\Classes;

use System\Tests\Bootstrap\TestCase;
use Winter\Storm\Exception\ApplicationException;

/**
 * @covers \Winter\Docs\Classes\BaseDocumentation
 * @testdox The Base Documentation abstract (\Winter\Docs\Classes\BaseDocumentation)
 */
class BaseDocumentationTest extends TestCase
{
    /**
     * @covers \Winter\Docs\Classes\BaseDocumentation::download()
     * @covers \Winter\Docs\Classes\BaseDocumentation::isDownloaded()
     * @testdox can download a remote documentation ZIP file and indicate that it is downloaded.
     */
    public function testDownload(): void
    {
        $doc = $this->getMockForAbstractClass(
            'Winter\Docs\Classes\BaseDocumentation',
            [
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'user',
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ]
            ]
        );

        $doc->download();
        $this->assertFileExists($doc->getDownloadPath('archive.zip'));
        $this->assertTrue($doc->isDownloaded());
    }

    /**
     * @covers \Winter\Docs\Classes\BaseDocumentation::download()
     * @testdox will throw an exception if the documentation URL is invalid when downloading.
     */
    public function testDownloadInvalidUrl(): void
    {
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessageMatches('/Could not retrieve the documentation/i');

        $doc = $this->getMockForAbstractClass(
            'Winter\Docs\Classes\BaseDocumentation',
            [
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'user',
                    'source' => 'remote',
                    'url' => 'https://wintercms.com/docs/missing/docs.zip',
                    'zipFolder' => 'docs-main',
                ]
            ]
        );

        $doc->download();
    }

    /**
     * @covers \Winter\Docs\Classes\BaseDocumentation::extract()
     * @testdox can extract a downloaded docs ZIP file.
     */
    public function testExtract(): void
    {
        $doc = $this->getMockForAbstractClass(
            'Winter\Docs\Classes\BaseDocumentation',
            [
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'user',
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ]
            ]
        );

        $doc->download();
        $doc->extract();

        $this->assertDirectoryExists($doc->getDownloadPath('extracted'));
        $this->assertFileExists($doc->getDownloadPath('extracted/snowboard-introduction.md'));
    }

/**
     * @covers \Winter\Docs\Classes\BaseDocumentation::cleanupDownload()
     * @testdox can clean up downloaded and extracted assets.
     */
    public function testCleanupDownload(): void
    {
        $doc = $this->getMockForAbstractClass(
            'Winter\Docs\Classes\BaseDocumentation',
            [
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'user',
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ]
            ]
        );

        $doc->download();
        $doc->extract();

        // Re-download the file
        $doc->download();

        // Clean up
        $doc->cleanupDownload();

        $this->assertFileDoesNotExist($doc->getDownloadPath('archive.zip'));
        $this->assertDirectoryDoesNotExist($doc->getDownloadPath('extracted'));
    }
}
