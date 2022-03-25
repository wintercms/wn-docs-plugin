<?php namespace Winter\Docs\Tests\Classes;

use File;
use ApplicationException;
use TestCase;

/**
 * @covers \Winter\Docs\Classes\BaseDocumentation
 * @testdox The Base Documentation abstract (\Winter\Docs\Classes\BaseDocumentation)
 */
class BaseDocumentationTest extends TestCase
{
    protected $tmpPath;

    public function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = dirname(dirname(__DIR__)) . '/tmp';

        if (File::exists($this->tmpPath)) {
            File::deleteDirectory($this->tmpPath);
        }
        File::makeDirectory($this->tmpPath);
    }

    public function tearDown(): void
    {
        if (File::exists($this->tmpPath)) {
            File::deleteDirectory($this->tmpPath);
        }

        parent::tearDown();
    }

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
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ]
            ],
            '',
            true,
            true,
            true,
            ['getDownloadPath']
        );

        $doc->method('getDownloadPath')
            ->will($this->returnValue($this->tmpPath));

        $doc->download();
        $this->assertFileExists($doc->getDownloadPath() . '/archive.zip');
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
                    'source' => 'remote',
                    'url' => 'https://wintercms.com/docs/missing/docs.zip',
                    'zipFolder' => 'docs-main',
                ]
            ],
            '',
            true,
            true,
            true,
            ['getDownloadPath']
        );

        $doc->method('getDownloadPath')
            ->will($this->returnValue($this->tmpPath));

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
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ]
            ],
            '',
            true,
            true,
            true,
            ['getDownloadPath']
        );

        $doc->method('getDownloadPath')
            ->will($this->returnValue($this->tmpPath));

        $doc->expects($this->any())
            ->method('getDownloadPath')
            ->will($this->returnValue($this->tmpPath));

        $doc->download();
        $doc->extract();

        $this->assertDirectoryExists($doc->getDownloadPath() . '/extracted');
        $this->assertFileExists($doc->getDownloadPath() . '/extracted/snowboard-introduction.md');
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
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ]
            ],
            '',
            true,
            true,
            true,
            ['getDownloadPath']
        );

        $doc->method('getDownloadPath')
            ->will($this->returnValue($this->tmpPath));

        $doc->expects($this->any())
            ->method('getDownloadPath')
            ->will($this->returnValue($this->tmpPath));

        $doc->download();
        $doc->extract();

        // Re-download the file
        $doc->download();

        // Clean up
        $doc->cleanupDownload();

        $this->assertFileDoesNotExist($doc->getDownloadPath() . '/archive.zip');
        $this->assertDirectoryDoesNotExist($doc->getDownloadPath() . '/extracted');
    }
}
