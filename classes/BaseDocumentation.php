<?php

namespace Winter\Docs\Classes;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Winter\Docs\Classes\Contracts\Documentation;
use Winter\Docs\Classes\Contracts\Page;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Filesystem\Zip;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\File;
use Winter\Storm\Support\Facades\Http;

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
     * A token to provide when downloading the source documentation from a URL, if the documentation is within a
     * private repository.
     */
    protected ?string $token = null;

    /**
     * The URL to the source repository for this documentation.
     */
    protected ?string $repositoryUrl = null;

    /**
     * The URL template for editing a page in the source repository.
     */
    protected ?string $repositoryEditUrl = null;

    /**
     * The subfolder within the ZIP file in which this documentation is stored. If this is `true`, the ZIP file will
     * be extracted as long as one folder exists within it, regardless of name.
     */
    protected string|bool|null $zipFolder = null;

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
    protected ?Filesystem $storageDisk = null;

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
        $this->token = $config['token'] ?? null;
        $this->zipFolder = $config['zipFolder'] ?? '';
        $this->ignoredPaths = $config['ignorePaths'] ?? [];
        $this->repositoryUrl = $config['repository']['url'] ?? null;
        $this->repositoryEditUrl = $config['repository']['editUrl'] ?? null;
    }

    /**
     * Gets the cache key for this documentation.
     */
    public function getCacheKey(string $suffix = ''): string
    {
        return !empty($suffix) ? $this->getIdentifier() . '.' . $suffix : $this->getIdentifier();
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

        // Don't cache result if running in console.
        if (App::runningInConsole()) {
            return $this->available = $this->getStorageDisk()->exists($this->getProcessedPath('page-map.json'))
                && $this->getStorageDisk()->exists($this->getProcessedPath('toc.json'));
        }

        return $this->available = Cache::rememberForever($this->getCacheKey('available'), function () {
            return (
                $this->getStorageDisk()->exists($this->getProcessedPath('page-map.json'))
                && $this->getStorageDisk()->exists($this->getProcessedPath('toc.json'))
            );
        });
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
     *
     * A token may be optionally provided, if the token is not available in the config.
     */
    public function download(?string $token = null): void
    {
        // Local sources do not need to be downloaded
        if (!$this->isRemote()) {
            return;
        }

        $token ??= $this->token;

        if (!$this->isRemoteAvailable($token)) {
            throw new ApplicationException(
                sprintf(
                    'Could not retrieve the documentation for "%s" from the remote source "%s"',
                    $this->identifier,
                    $this->source
                )
            );
        }

        // Create temporary location
        if (File::exists($this->getDownloadPath())) {
            File::deleteDirectory($this->getDownloadPath());
        }
        File::makeDirectory($this->getDownloadPath(), 0777, true);

        // Download ZIP file
        $http = Http::get($this->url, function ($http) use ($token) {
            $http->header('User-Agent', 'Winter CMS (https://wintercms.com)');
            $http->toFile($this->getDownloadPath('archive.zip'));

            if (!empty($token)) {
                $http->header('Authorization', 'token ' . $token);
            }
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
        } else {
            File::deleteDirectory($this->getDownloadPath('extracted'));
        }

        // Create collated location
        if (!File::exists($this->getDownloadPath('collated'))) {
            File::makeDirectory($this->getDownloadPath('collated'), 0777, true);
        } else {
            File::deleteDirectory($this->getDownloadPath('collated'));
        }

        // Extract ZIP to location
        $zip = new Zip();
        $zip->open($this->getDownloadPath('archive.zip'));
        $toExtract = null;

        if ($this->zipFolder === true) {
            $folders = [];

            // Find if there's only one folder
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stats = $zip->statIndex($i);

                if (str_ends_with($stats['name'], '/') && substr_count($stats['name'], '/') === 1 && $stats['size'] === 0) {
                    $folders[] = $stats['name'];
                    continue;
                }
            }

            if (count($folders) !== 1) {
                throw new ApplicationException(
                    'We could not determine the correct Zip folder to extract. Please specify a specific Zip folder.',
                );
            }

            $this->zipFolder = rtrim($folders[0], '/');
        }

        if (!empty($this->zipFolder)) {
            $toExtract = [];

            // Only get necessary files from the ZIP file
            for ($i = 1; $i < $zip->numFiles; ++$i) {
                $filename = $zip->statIndex($i)['name'];

                if (strpos($filename, $this->zipFolder) === 0) {
                    $toExtract[] = $filename;
                }
            }
        }

        $zip->extractTo($this->getDownloadPath('extracted'), $toExtract);

        // Move remaining files into location
        $extractPath = $this->getDownloadPath('extracted/' . $this->zipFolder);
        $dir = new DirectoryIterator($extractPath);

        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }

            $relativePath = str_replace($extractPath . '/', '', $item->getPathname());

            rename($item->getPathname(), $this->getDownloadPath('collated/' . $relativePath));
        }

        // Remove ZIP folder
        File::deleteDirectory($extractPath);

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

        if (File::exists($this->getDownloadPath('collated'))) {
            File::deleteDirectory($this->getDownloadPath('collated'));
        }
    }

    /**
     * @inheritDoc
     */
    abstract public function process(): void;

    /**
     * @inheritDoc
     */
    abstract public function getPageList(): BasePageList;

    /**
     * @inheritDoc
     */
    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    /**
     * Returns a URL to edit the source of the given page.
     *
     * This requires the repository edit URL to be provided in the documentation config, otherwise,
     * `null` will be returned.
     *
     * @param Page $page
     * @return string|null
     */
    public function getRepositoryEditUrl(Page $page): ?string
    {
        if (is_null($this->repositoryEditUrl)) {
            return null;
        }

        return sprintf($this->repositoryEditUrl, $page->getPath());
    }

    /**
     * Resets cached states (is available?, is downloaded?)
     *
     * @return void
     */
    public function resetState(): void
    {
        $this->downloaded = null;
        $this->available = null;
    }

    /**
     * Checks if a remotely-sourced documentation ZIP file is available.
     */
    protected function isRemoteAvailable(?string $token = null): bool
    {
        $token ??= $this->token;

        return Http::head($this->url, function ($http) use ($token) {
            $http->header('User-Agent', 'Winter CMS (https://wintercms.com)');

            if (!empty($token)) {
                $http->header('Authorization', 'token ' . $token);
            }
        })->ok;
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
            $path = $this->getDownloadPath('collated');
            if (!empty($this->path)) {
                $path .= '/' . trim($this->path, '/');
            }
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
     * Provides the path where processed assets for the documentation will be stored.
     *
     * This path will be used on the storage disk.
     */
    public function getProcessedAssetsPath(string $suffix = ''): string
    {
        $path = $this->getProcessedPath('/_assets');

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
        return Cache::rememberForever('winter.docs::storage.processedFileContents.' . $this->getCacheKey($path), function () use ($path) {
            return $this->getStorageDisk()->get(
                $this->getProcessedPath($path)
            ) ?? null;
        });
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
