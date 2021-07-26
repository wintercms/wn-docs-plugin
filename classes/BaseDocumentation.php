<?php namespace Winter\Docs\Classes;

use File;
use Http;
use Storage;
use Winter\Docs\Classes\Contracts\Documentation;

abstract class BaseDocumentation implements Documentation
{
    protected $identifier;

    protected $source = 'local';

    protected $path;

    protected $zipFolder;

    protected $available = null;

    protected $downloaded = null;

    protected $storageDisk = null;

    public function __construct(string $identifier, array $config = [])
    {
        $this->identifier = $identifier;
        $this->source = $config['source'];
        $this->path = $config['path'];
        $this->zipFolder = $config['zipFolder'] ?? '';
    }

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

    abstract public function isProcessed(): bool;

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

        return $this->downloaded = $this->getStorageDisk()->exists($this->getDownloadPath() . '/archive.zip');
    }

    protected function isRemoteAvailable()
    {
        return Http::head($this->source)->ok;
    }

    protected function getProcessedPath()
    {
        return Config::get('winter.docs::storage.processedPath', 'docs/processed') . '/' . $this->getPathIdentifier();
    }

    protected function getDownloadPath()
    {
        return Config::get('winter.docs::storage.downloadPath', 'docs/download') . '/' . $this->getPathIdentifier();
    }

    protected function getPathIdentifier()
    {
        return kebab_case(strtolower(str_replace('.', '-', $this->identifier)));
    }

    protected function getStorageDisk()
    {
        if ($this->storageDisk) {
            return $this->storageDisk;
        }

        return $this->storageDisk = Storage::disk(
            Config::get('winter.docs::storage.disk', 'local')
        );
    }
}
