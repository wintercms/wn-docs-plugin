<?php

namespace Winter\Docs\Classes;

use Winter\Docs\Classes\Contracts\Page;
use Winter\Docs\Classes\Contracts\PageList;

abstract class BasePageList implements PageList
{
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
    abstract public function index(): void;

    /**
     * Gets the identifier for the doc instance.
     */
    public function getDocsIdentifier(): string
    {
        return $this->docs->identifier;
    }
}
