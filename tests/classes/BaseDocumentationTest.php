<?php

namespace Winter\Docs\Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use System\Tests\Bootstrap\TestCase;
use Winter\Docs\Classes\BaseDocumentation;
use Winter\Storm\Exception\ApplicationException;

#[CoversClass(\Winter\Docs\Classes\BaseDocumentation::class)]
#[TestDox('The Base Documentation abstract (\Winter\Docs\Classes\BaseDocumentation)')]
class BaseDocumentationTest extends TestCase
{
    #[TestDox('can download a remote documentation ZIP file and indicate that it is downloaded.')]
    public function testDownload(): void
    {
        $doc = $this->getMockBuilder(BaseDocumentation::class)
            ->setConstructorArgs([
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'md',
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ],
            ])
            ->onlyMethods(['process', 'getPageList'])
            ->getMock();

        $doc->download();
        $this->assertFileExists($doc->getDownloadPath('archive.zip'));
        $this->assertTrue($doc->isDownloaded());
    }

    #[TestDox('will throw an exception if the documentation URL is invalid when downloading.')]
    public function testDownloadInvalidUrl(): void
    {
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessageMatches('/Could not retrieve the documentation/i');

        $doc = $this->getMockBuilder(BaseDocumentation::class)
            ->setConstructorArgs([
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'md',
                    'source' => 'remote',
                    'url' => 'https://wintercms.com/missing/docs.zip',
                    'zipFolder' => 'docs-main',
                ],
            ])
            ->onlyMethods(['process', 'getPageList'])
            ->getMock();

        $doc->download();
    }

    #[TestDox('can extract a downloaded docs ZIP file and clean-up afterwards.')]
    public function testExtractAndCleanUp(): void
    {
        $doc = $this->getMockBuilder(BaseDocumentation::class)
            ->setConstructorArgs([
                'Winter.Docs.Test',
                [
                    'name' => 'Winter Docs Test',
                    'type' => 'md',
                    'source' => 'remote',
                    'url' => 'https://github.com/wintercms/docs/archive/refs/heads/main.zip',
                    'zipFolder' => 'docs-main',
                ],
            ])
            ->onlyMethods(['process', 'getPageList'])
            ->getMock();

        $doc->download();
        $doc->extract();

        $this->assertDirectoryExists($doc->getDownloadPath('collated'));
        $this->assertFileExists($doc->getDownloadPath('collated/snowboard/introduction.md'));

        // Re-download the file
        $doc->download();

        // Clean up
        $doc->cleanupDownload();

        $this->assertFileDoesNotExist($doc->getDownloadPath('archive.zip'));
        $this->assertDirectoryDoesNotExist($doc->getDownloadPath('extracted'));
    }
}
