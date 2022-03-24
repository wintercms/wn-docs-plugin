<?php namespace Winter\Docs\Classes;

use File;
use Http;
use Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Winter\Docs\Classes\Contracts\Documentation;

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
    protected $path;

    /**
     * The URL where the compiled documentation can be found.
     *
     * @var string
     */
    protected $url;

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
        $this->path = $config['path'];
        $this->url = $config['url'];
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

        if ($this->source === 'local') {
            return $this->available = (
                File::exists($this->source . '/index.md')
                || $this->getStorageDisk()->exists($this->getProcessedPath() . '/index.html')
            );
        }

        return $this->available = (
            $this->getStorageDisk()->exists($this->getProcessedPath() . '/index.html')
            || $this->isRemoteAvailable()
        );
    }

    /**
     * @inheritDoc
     */
    abstract public function isProcessed(): bool;

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

        // If a remotely-sourced documentation is processed, we'll assume it's downloaded.
        if ($this->isProcessed()) {
            return true;
        }

        if ($this->isLocalStorage()) {
            return $this->downloaded = $this->getStorageDisk()->exists($this->getDownloadPath() . '/archive.zip');
        }

        return $this->downloaded = File::exists(temp_path($this->getDownloadPath() . '/archive.zip'));
    }

    /**
     * Downloads a remote ZIP file for the documentation.
     *
     * The downloaded file will be placed at the expected location for processing
     *
     * @return void
     */
    public function download()
    {
        // Create temporary location
        if (!File::exists(temp_path($this->getDownloadPath()))) {
            File::makeDirectory(temp_path($this->getDownloadPath()), 0777, true);
        }

        // Download ZIP file
        Http::get($this->url, function ($http) {
            $http->toFile(temp_path($this->getDownloadPath() . '/archive.zip'));
        });

        // Move the file into location for local storage
        if ($this->isLocalStorage()) {
            $this->getStorageDisk()->put(
                $this->getDownloadPath() . '/archive.zip',
                temp_path($this->getDownloadPath() . '/archive.zip')
            );
        }
    }

    /**
     * Checks if a remotely-sourced documentation ZIP file is available.
     *
     * @return boolean
     */
    protected function isRemoteAvailable(): bool
    {
        return Http::head($this->source)->ok;
    }

    /**
     * Provides the path where processed documentation will be stored.
     *
     * @return string
     */
    protected function getProcessedPath(): string
    {
        return Config::get('winter.docs::storage.processedPath', 'docs/processed') . '/' . $this->getPathIdentifier();
    }

    /**
     * Provides the path where downloaded remotely-sourced documentation will be stored.
     *
     * @return string
     */
    protected function getDownloadPath(): string
    {
        return Config::get('winter.docs::storage.downloadPath', 'docs/download') . '/' . $this->getPathIdentifier();
    }

    /**
     * Provides a path identifier for this particular documentation.
     *
     * This is a kebab-case string that will be used as a subfolder for the processed and download paths.
     *
     * @return string
     */
    protected function getPathIdentifier(): string
    {
        return kebab_case(strtolower(str_replace('.', '-', $this->identifier)));
    }

    /**
     * Gets the storage disk.
     *
     * @return Filesystem
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
     *
     * @return boolean
     */
    protected function isLocalStorage(): bool
    {
        return File::isLocalDisk($this->getStorageDisk());
    }
}
