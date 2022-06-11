<?php namespace Winter\Docs\Classes;

use File;
use Yaml;
use Winter\Docs\Classes\Contracts\PageList;
use Winter\Storm\Exception\ApplicationException;
use Winter\Docs\Classes\Contracts\Page;

/**
 * Pages List class.
 *
 * Loads and collates the list of documentation pages for navigation and search.
 *
 * @author Ben Thomson
 */
class MarkdownPageList implements PageList
{
    /**
     * The Markdown Documentation instance.
     */
    protected MarkdownDocumentation $docs;

    /**
     * The root page of the documentation.
     */
    protected Page $rootPage;

    /**
     * The active page of the documentation.
     */
    protected ?Page $activePage = null;

    /**
     * @var array<string, Page> Available pages, keyed by path.
     */
    protected array $pages = [];

    /**
     * The navigation of the documentation.
     */
    protected array $navigation = [];

    /**
     * Generates the page list from a page map and the table of contents files.
     */
    public function __construct(MarkdownDocumentation $docs, string $pageMap, string $toc)
    {
        foreach (json_decode($pageMap, true) as $path => $page) {
            $this->pages[$path] = new MarkdownPage($docs, $path, $page['title']);
        }

        $tocData = json_decode($toc, true);
        if (!array_key_exists($tocData['root'], $this->pages)) {
            throw new ApplicationException('The root page specified for the documentation does not exist');
        }

        $this->rootPage = $this->pages[$tocData['root']];
        $this->navigation = $tocData['navigation'];
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
        if (!array_key_exists($path, $this->pages)) {
            return null;
        }

        $page = $this->pages[$path];
        $page->load();

        return $page;
    }

    /**
     * @inheritDoc
     */
    public function setActivePage(Page $page): void
    {
        $this->activePage = $page;

        // Find the page within the navigation and add an "active" state to the page, and a
        // "childActive" state to the section.
        foreach ($this->navigation as $key => $nav) {
            if (isset($nav['path'])) {
                if (isset($this->pages[$nav['path']]) && $this->pages[$nav['path']] === $this->activePage) {
                    $this->navigation[$key]['active'] = true;
                } else {
                    unset($this->navigation[$key]['active']);
                }
            }
            if (isset($nav['children'])) {
                $childActive = false;

                foreach ($nav['children'] as $subKey => $subNav) {
                    if (isset($subNav['path'])) {
                        if (isset($this->pages[$subNav['path']]) && $this->pages[$subNav['path']] === $this->activePage) {
                            $this->navigation[$key]['children'][$subKey]['active'] = true;
                            $childActive = true;
                        } else {
                            unset($this->navigation[$key]['children'][$subKey]['active']);
                        }
                    }
                }

                if ($childActive) {
                    $this->navigation[$key]['childActive'] = true;
                } else {
                    unset($this->navigation[$key]['childActive']);
                }
            }
        }
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
        return $this->rootPage;
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
        return $this->navigation;
    }

    /**
     * @inheritDoc
     */
    public function index(): void
    {
        $index = new MarkdownPageIndex([
            'pageList' => $this,
        ]);
        $index->index();
    }

    /**
     * Gets the identifier for the doc instance.
     */
    public function getDocsIdentifier(): string
    {
        return $this->docs->identifier;
    }
}
