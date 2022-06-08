<?php namespace Winter\Docs\Classes;

use File;
use Yaml;
use Winter\Docs\Classes\Contracts\PageList as PageListContract;
use Winter\Storm\Exception\ApplicationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Winter\Docs\Classes\Contracts\Page;

/**
 * Pages List class.
 *
 * Loads and collates the list of documentation pages for navigation and search.
 *
 * @author Ben Thomson
 */
class MarkdownPageList implements PageListContract
{
    /**
     * @var array<string, Page> Available pages, keyed by path.
     */
    protected array $pages = [];

    /**
     * @var array<string, array<string, string>> The sections within the navigation. Each section will be an array of
     *  page paths that fall within the section as the key, and the title of the page as the value.
     */
    protected array $sections = [];

    /**
     * The menu structure.
     */
    protected array $menu = [];

    /**
     * The file containing the docs menu.
     */
    protected ?string $menuFile = null;

    /**
     * Paths to ignore when collating available pages and assets.
     */
    protected array $ignoredPaths = [];

    /**
     * Generates the page list from a menu file.
     *
     * @param string $menuFile The absolute path to the menu file.
     * @return void
     */
    public function fromMenuFile(string $menuFile)
    {
        $this->menuFile = $menuFile;

        if (!File::exists($this->menuFile)) {
            throw new ApplicationException(
                sprintf(
                    'Menu file cannot be found at "%s".',
                    $this->menuFile
                )
            );
        }

        $this->loadMenuFile($this->menuFile);
    }

    /**
     * Gets the ignored paths from the menu configuration.
     */
    public function getIgnoredPaths()
    {
        return $this->ignoredPaths;
    }

    /**
     * @inheritDoc
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * @inheritDoc
     */
    public function getPage(string $path): ?Page
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function search(string $query): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getRootPage(): Page
    {

    }

    /**
     * @inheritDoc
     */
    public function nextPage(Page $page): ?Page
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function previousPage(Page $page): ?Page
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getNavigation(): array
    {
        return [];
    }

    /**
     * Loads the pages from a YAML table of contents file.
     *
     * @param string $section
     * @param string $file
     * @return void
     */
    protected function loadMenuFile($file)
    {
        $config = Yaml::parseFile($file);
        $rootPage = $config['rootPage'] ?? null;
        $this->ignoredPaths = $config['ignoredPaths'] ?? [];
        $pages = [];

        if (isset($config['sections'])) {
            foreach ($config['sections'] as $sectionName => $sectionConfig) {
                if (empty($sectionConfig['pages'])) {
                    continue;
                }

                $this->sections[$sectionName] = $sectionConfig['pages'];
            }
        }

        $this->pages[] = $pages;
    }
}
