<?php namespace Winter\Docs\Classes;

use File;
use Http;
use Config;
use Storage;
use ApplicationException;
use DirectoryIterator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Winter\Docs\Classes\Contracts\Documentation;
use Winter\Docs\Classes\Contracts\PageList;
use Winter\Storm\Filesystem\Zip;
use ZipArchive;

abstract class BaseDocumentation implements Documentation
{
    /**
     * The identifier of this documentation.
     *
     * @var string
     */
    protected $identifier;

    /**
     * The source disk which will be used for storage.
     *
     * @var string
     */
    protected $source = 'local';

    /**
     * The path where this documentation is loaded.
     *
     * @var string
     */
    protected $path = null;

    /**
     * The URL where the compiled documentation can be found.
     *
     * @var string
     */
    protected $url = null;

    /**
     * The subfolder within the ZIP file in which this documentation is stored.
     *
     * @var string
     */
    protected $zipFolder;

    /**
     * Is this documentation available?
     *
     * @var bool|null
     */
    protected $available = null;

    /**
     * Is this documentation downloaded?
     *
     * @var bool|null
     */
    protected $downloaded = null;

    /**
     * The storage disk where processed and downloaded documentation is stored.
     *
     * @var Filesystem
     */
    protected $storageDisk;

    /**
     * Constructor.
     *
     * Expects an identifier for the documentation in the format of Author.Plugin.Doc (eg. Winter.Docs.Docs). The
     * second parameter is the configuration for this documentation.
     *
     * @param string $identifier
     * @param array $config
     */
    public function __construct(string $identifier, array $config = [])
    {
        $this->identifier = $identifier;
        $this->source = $config['source'];
        $this->path = $config['path'] ?? null;
        $this->url = $config['url'] ?? null;
        $this->zipFolder = $config['zipFolder'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        if (!is_null($this->available)) {
            return $this->available;
        }

        if ($this->isLocalStorage()) {
            return $this->available = $this->getStorageDisk()->exists($this->getProcessedPath() . '/index.html');
        }

        return $this->available = (
            $this->getStorageDisk()->exists($this->getProcessedPath() . '/index.html')
            || $this->isRemoteAvailable()
        );
    }

    /**
     * @inheritDoc
     */
    public function isProcessed(): bool
    {
        return $this->isDownloaded() && !$this->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function isDownloaded(): bool
    {
        if (!is_null($this->downloaded)) {
            return $this->downloaded;
        }

        if ($this->source === 'local') {
            return $this->downloaded = true;
        }

        // If a remotely-sourced documentation is available, we'll assume it's downloaded.
        if ($this->isAvailable()) {
            return $this->downloaded = true;
        }

        return $this->downloaded = File::exists($this->getDownloadPath() . '/archive.zip');
    }

    /**
     * Downloads a remote ZIP file for the documentation.
     *
     * The downloaded file will be placed at the expected location and extracted for processing
     */
    public function download(): void
    {
        // Local sources do not need to be downloaded
        if ($this->source === 'local') {
            return;
        }

        // Create temporary location
        if (!File::exists($this->getDownloadPath())) {
            File::makeDirectory($this->getDownloadPath(), 0777, true);
        }

        // Download ZIP file
        $http = Http::get($this->url, function ($http) {
            $http->toFile($this->getDownloadPath() . '/archive.zip');
        });

        if (!$http->ok) {
            throw new ApplicationException(
                sprintf(
                    'Could not retrieve the documentation for "%s" from the remote source "%s"',
                    $this->identifier,
                    $this->source
                )
            );
        }
    }

    /**
     * Extracts the downloaded ZIP file
     *
     * The ZIP file will be extracted to an "extracted" subfolder within the download path, and the source
     * made available for processing.
     *
     * @throws ApplicationException If the docs ZIP has not been downloaded
     */
    public function extract(): void
    {
        // Local sources do not need to be downloaded, therefore don't need to be extracted
        if ($this->source === 'local') {
            return;
        }

        if (!$this->isDownloaded()) {
            throw new ApplicationException(
                sprintf(
                    'You must download the "%s" documentation first',
                    $this->identifier
                )
            );
        }

        // Create extracted location
        if (!File::exists($this->getDownloadPath() . '/extracted')) {
            File::makeDirectory($this->getDownloadPath() . '/extracted', 0777, true);
        }

        // Extract ZIP to location
        $zip = new Zip();
        $zip->open($this->getDownloadPath() . '/archive.zip');
        $zip->extractTo($this->getDownloadPath() . '/extracted');

        if (!empty($this->zipFolder)) {
            // Remove all files and folders that do not meet the ZIP folder provided
            $dir = new DirectoryIterator($this->getDownloadPath() . '/extracted');

            foreach ($dir as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $relativePath = str_replace($this->getDownloadPath() . '/extracted/', '', $item->getPathname());

                if ($relativePath !== $this->zipFolder) {
                    if ($item->isDir()) {
                        File::deleteDirectory($item->getPathname());
                    } else {
                        File::delete($item->getPathname());
                    }
                }
            }

            // Move remaining files into location
            $dir = new DirectoryIterator($this->getDownloadPath() . '/extracted/' . $this->zipFolder);

            foreach ($dir as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $relativePath = str_replace($this->getDownloadPath() . '/extracted/' . $this->zipFolder . '/', '', $item->getPathname());

                rename($item->getPathname(), $this->getDownloadPath() . '/extracted/' . $relativePath);
            }

            // Remove ZIP folder
            File::deleteDirectory($this->getDownloadPath() . '/extracted/' . $this->zipFolder);
        }

        // Remove ZIP file
        File::delete($this->getDownloadPath() . '/archive.zip');
    }

    /**
     * Deletes any temporary downloads and extracted files.
     *
     * @return void
     */
    public function cleanupDownload()
    {
        if (File::exists($this->getDownloadPath() . '/archive.zip')) {
            File::delete($this->getDownloadPath() . '/archive.zip');
        }

        if (File::exists($this->getDownloadPath() . '/extracted')) {
            File::deleteDirectory($this->getDownloadPath() . '/extracted');
        }
    }

    /**
     * @inheritDoc
     */
    abstract public function getPageList(): PageList;

    /**
     * Checks if a remotely-sourced documentation ZIP file is available.
     */
    protected function isRemoteAvailable(): bool
    {
        return Http::head($this->source)->ok;
    }

    /**
     * Provides the path where processed documentation will be stored.
     *
     * This path will be used on the storage disk.
     */
    public function getProcessedPath(): string
    {
        return Config::get('winter.docs::storage.processedPath', 'docs/processed') . '/' . $this->getPathIdentifier();
    }

    /**
     * Provides the path where downloaded remotely-sourced documentation will be stored.
     *
     * This will always be stored in temp storage, as it is expected that a download will be
     * immediately processed afterwards.
     */
    public function getDownloadPath(): string
    {
        return temp_path(Config::get('winter.docs::storage.downloadPath', 'docs/download') . '/' . $this->getPathIdentifier());
    }

    /**
     * Provides a path identifier for this particular documentation.
     *
     * This is a kebab-case string that will be used as a subfolder for the processed and download paths.
     */
    protected function getPathIdentifier(): string
    {
        return kebab_case(strtolower(str_replace('.', '-', $this->identifier)));
    }

    /**
     * Gets the storage disk.
     */
    protected function getStorageDisk(): Filesystem
    {
        if ($this->storageDisk) {
            return $this->storageDisk;
        }

        return $this->storageDisk = Storage::disk(
            Config::get('winter.docs::storage.disk', 'local')
        );
    }

    /**
     * Determines if the storage disk is using the "local" driver.
     */
    protected function isLocalStorage(): bool
    {
        return File::isLocalDisk($this->getStorageDisk());
    }
}
