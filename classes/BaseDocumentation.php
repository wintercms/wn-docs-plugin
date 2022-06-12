<?php namespace Winter\Docs\Classes;

use File;
use Http;
use Lang;
use Config;
use Storage;
use DirectoryIterator;
use ApplicationException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Winter\Docs\Classes\Contracts\Documentation;
use Winter\Docs\Classes\Contracts\PageList;
use Winter\Storm\Filesystem\Zip;

abstract class BaseDocumentation implements Documentation
{
    /**
     * The identifier of this documentation.
     */
    protected string $identifier;

    /**
     * The name of this documentation.
     */
    protected string $name;

    /**
     * The type of this documentation.
     */
    protected string $type;

    /**
     * The source disk which will be used for storage.
     */
    protected string $source = 'local';

    /**
     * The path where this documentation is loaded.
     */
    protected ?string $path = null;

    /**
     * The URL where the compiled documentation can be found.
     */
    protected ?string $url = null;

    /**
     * The subfolder within the ZIP file in which this documentation is stored.
     */
    protected ?string $zipFolder = null;

    /**
     * Is this documentation available?
     */
    protected ?bool $available = null;

    /**
     * Is this documentation downloaded?
     */
    protected ?bool $downloaded = null;

    /**
     * The storage disk where processed and downloaded documentation is stored.
     */
    protected Filesystem $storageDisk;

    /**
     * Paths to ignore when collating available pages and assets.
     */
    protected array $ignoredPaths = [];

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
        $this->name = $config['name'];
        $this->type = $config['type'];
        $this->source = $config['source'];
        $this->path = $config['path'] ?? null;
        $this->url = $config['url'] ?? null;
        $this->zipFolder = $config['zipFolder'] ?? '';
        $this->ignoredPaths = $config['ignorePaths'] ?? [];
    }

    /**
     * Gets the identifier of the documentation.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Gets the name of this documentation.
     */
    public function getName(): string
    {
        return Lang::get($this->name);
    }

    /**
     * Gets the type of this documentation.
     *
     * The type will be one of the following:
     *  - `user`: Documentation intended for end-users or site administrators.
     *  - `developer`: Documentation intended for developers.
     *  - `api`: API documentation generated from PHP source code.
     *  - `events`: Event documentation generated from PHP source code.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Determines if this documentation is remotely sourced.
     */
    public function isRemote(): bool
    {
        return ($this->source === 'remote');
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        if (!is_null($this->available)) {
            return $this->available;
        }

        return $this->available = (
            $this->getStorageDisk()->exists($this->getProcessedPath('page-map.json'))
            && $this->getStorageDisk()->exists($this->getProcessedPath('toc.json'))
        );
    }

    /**
     * @inheritDoc
     */
    public function isProcessed(): bool
    {
        return $this->isDownloaded() && $this->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function isDownloaded(): bool
    {
        if (!is_null($this->downloaded)) {
            return $this->downloaded;
        }

        if (!$this->isRemote()) {
            return $this->downloaded = true;
        }

        // If a remotely-sourced documentation is available, we'll assume it's downloaded.
        if ($this->isAvailable()) {
            return $this->downloaded = true;
        }

        return $this->downloaded = File::exists($this->getDownloadPath('archive.zip'));
    }

    /**
     * Downloads a remote ZIP file for the documentation.
     *
     * The downloaded file will be placed at the expected location and extracted for processing
     */
    public function download(): void
    {
        // Local sources do not need to be downloaded
        if (!$this->isRemote()) {
            return;
        }

        if (!$this->isRemoteAvailable()) {
            throw new ApplicationException(
                sprintf(
                    'Could not retrieve the documentation for "%s" from the remote source "%s"',
                    $this->identifier,
                    $this->source
                )
            );
        }

        // Create temporary location
        if (!File::exists($this->getDownloadPath())) {
            File::makeDirectory($this->getDownloadPath(), 0777, true);
        }

        // Download ZIP file
        $http = Http::get($this->url, function ($http) {
            $http->toFile($this->getDownloadPath('archive.zip'));
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
        if (!$this->isRemote()) {
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
        if (!File::exists($this->getDownloadPath('extracted'))) {
            File::makeDirectory($this->getDownloadPath('extracted'), 0777, true);
        }

        // Extract ZIP to location
        $zip = new Zip();
        $zip->open($this->getDownloadPath('archive.zip'));
        $zip->extractTo($this->getDownloadPath('extracted'));

        if (!empty($this->zipFolder)) {
            // Remove all files and folders that do not meet the ZIP folder provided
            $dir = new DirectoryIterator($this->getDownloadPath('extracted'));

            foreach ($dir as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $relativePath = str_replace($this->getDownloadPath('extracted/'), '', $item->getPathname());

                if ($relativePath !== $this->zipFolder) {
                    if ($item->isDir()) {
                        File::deleteDirectory($item->getPathname());
                    } else {
                        File::delete($item->getPathname());
                    }
                }
            }

            // Move remaining files into location
            $dir = new DirectoryIterator($this->getDownloadPath('extracted/' . $this->zipFolder));

            foreach ($dir as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $relativePath = str_replace($this->getDownloadPath('extracted/' . $this->zipFolder . '/'), '', $item->getPathname());

                rename($item->getPathname(), $this->getDownloadPath('extracted/' . $relativePath));
            }

            // Remove ZIP folder
            File::deleteDirectory($this->getDownloadPath('extracted/' . $this->zipFolder));
        }

        // Remove ZIP file
        File::delete($this->getDownloadPath('archive.zip'));
    }

    /**
     * Deletes any temporary downloads and extracted files.
     *
     * @return void
     */
    public function cleanupDownload()
    {
        if (File::exists($this->getDownloadPath('archive.zip'))) {
            File::delete($this->getDownloadPath('archive.zip'));
        }

        if (File::exists($this->getDownloadPath('extracted'))) {
            File::deleteDirectory($this->getDownloadPath('extracted'));
        }
    }

    /**
     * @inheritDoc
     */
    abstract public function process(): void;

    /**
     * @inheritDoc
     */
    abstract public function getPageList(): PageList;

    /**
     * Checks if a remotely-sourced documentation ZIP file is available.
     */
    protected function isRemoteAvailable(): bool
    {
        return Http::head($this->url)->ok;
    }

    /**
     * Provides the path where unprocessed documentation will be stored.
     *
     * This will always be stored in temp storage, and assumes that the documentation is processed
     * and extracted if it is a remotely sourced documentation.
     *
     * @param string $suffix
     * @return string
     */
    public function getProcessPath(string $suffix = ''): string
    {
        if ($this->isRemote()) {
            $path = $this->getDownloadPath('extracted');
            if (!empty($suffix)) {
                $path .= '/' . (ltrim(str_replace(['\\', '/'], '/', $suffix), '/'));
            }
            return $path;
        }

        $path = rtrim($this->path, '/');
        if (!empty($suffix)) {
            $path .= '/' . (ltrim(str_replace(['\\', '/'], '/', $suffix), '/'));
        }
        return $path;
    }

    /**
     * Get all unprocessed files that meet the given extension(s).
     */
    public function getProcessFiles(string|array $extension = []): array
    {
        $extensions = (is_string($extension))
            ? [$extension]
            : $extension;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->getProcessPath())
        );
        $found = [];

        foreach ($files as $file) {
            if (File::isFile($file) && (!count($extensions) || in_array(File::extension($file), $extensions))) {
                $relative = ltrim(str_replace($this->getProcessPath(), '', $file), '/');

                foreach ($this->ignoredPaths as $path) {
                    if ($relative === $path || str_starts_with($relative, $path)) {
                        continue 2;
                    }
                }

                $found[] = $relative;
            }
        }

        return $found;
    }

    /**
     * Provides the path where processed documentation will be stored.
     *
     * This path will be used on the storage disk.
     */
    public function getProcessedPath(string $suffix = ''): string
    {
        $path = Config::get('winter.docs::storage.processedPath', 'docs/processed') . '/' . $this->getPathIdentifier();

        // Normalise suffix path
        if (!empty($suffix)) {
            $path .= '/' . (ltrim(str_replace(['\\', '/'], '/', $suffix), '/'));
        }

        return $path;
    }

    /**
     * Gets the contents of a file in the processed storage.
     *
     * Returns `null` if the file cannot be found or read.
     */
    public function getProcessedFile(string $path): ?string
    {
        return $this->getStorageDisk()->get(
            $this->getProcessedPath($path)
        ) ?? null;
    }

    /**
     * Provides the path where downloaded remotely-sourced documentation will be stored.
     *
     * This will always be stored in temp storage, as it is expected that a download will be
     * immediately processed afterwards.
     */
    public function getDownloadPath(string $suffix = ''): string
    {
        $path = temp_path(Config::get('winter.docs::storage.downloadPath', 'docs/download') . '/' . $this->getPathIdentifier());

        // Normalise suffix path
        if (!empty($suffix)) {
            $path .= '/' . (ltrim(str_replace(['\\', '/'], '/', $suffix), '/'));
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
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
