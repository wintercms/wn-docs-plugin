<?php

namespace Winter\Docs\Classes;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use System\Classes\PluginManager;
use Winter\Docs\Classes\Contracts\Page;
use Winter\Docs\Classes\Contracts\PageList;
use Winter\Storm\Support\Str;

abstract class BasePageList implements PageList, \IteratorAggregate, \ArrayAccess, \Countable
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
     * {@inheritDoc}
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function setActivePage(Page $page): void
    {
        $this->activePage = $page;
        $fullNav = $this->navigation;

        $childIterator = function (&$nav) use (&$childIterator) {
            $childActive = false;

            if (isset($nav['path'])) {
                if (isset($this->pages[$nav['path']]) && $this->pages[$nav['path']] === $this->activePage) {
                    $nav['active'] = true;
                    $childActive = true;
                } else {
                    unset($nav['active']);
                }
            }

            if (isset($nav['children'])) {
                foreach ($nav['children'] as $subKey => &$subNav) {
                    if ($childIterator($subNav) === true) {
                        $childActive = true;
                    }
                }

                if ($childActive) {
                    $nav['childActive'] = true;
                } else {
                    unset($nav['childActive']);
                }
            }

            return $childActive;
        };

        // Find the page within the navigation and add an "active" state to the page, and a
        // "childActive" state to the section.
        foreach ($fullNav as $key => &$nav) {
            $childIterator($nav);
        }

        $this->navigation = $fullNav;
    }

    /**
     * {@inheritDoc}
     */
    public function getRootPage(): Page
    {
        return $this->rootPage;
    }

    /**
     * Traverse a nested associative array to find the value of an item, optionally offset from a specified item.
     * The offset will ignore any items that don't have a value for the searchKey, but will count their
     * children if they have any.
     *
     * @return string|bool Returns the value item at the given offset from the searchValue, or
     * `false` if it doesn't exist or is out of bounds.
     */
    public function findInNestedArray(array $items, string $searchKey, string $searchValue, int $offset = 0): string|bool
    {
        // Flattened array to hold searchable values of items.
        $flattenedItems = [];

        // Using RecursiveIteratorIterator to traverse the nested array.
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($items));

        // If the key matches the searchKey, add its value to the flattened array.
        foreach ($iterator as $key => $value) {
            // Skip any values that are an external URL.
            if ($key === $searchKey && !Str::contains($value, '://')) {
                $flattenedItems[] = $value;
            }
        }

        // Search for the index of the searchValue in the flattened array.
        $foundIndex = array_search($searchValue, $flattenedItems);

        // If searchValue is not found, return false.
        if ($foundIndex === false) {
            return false;
        }

        // Add the offset to the found index to get the index of the desired item.
        $desiredIndex = $foundIndex + $offset;

        // If the desired index is within the boundaries of the flattened array, return the 'path' at that index.
        // Otherwise, return false.
        return $desiredIndex >= 0 && $desiredIndex < count($flattenedItems) ? $flattenedItems[$desiredIndex] : false;
    }

    /**
     * Get the page at the given offset from the given path in the navigation.
     */
    public function getPageFromNav(array $nav, string $path, int $offset = 0): ?Page
    {
        $offsetPath = $this->findInNestedArray($nav, 'path', $path, $offset);
        return $offsetPath ? $this->getPage($offsetPath) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function nextPage(Page $page): ?Page
    {
        return $this->getPageFromNav($this->navigation, $page->getPath(), 1);
    }

    /**
     * {@inheritDoc}
     */
    public function previousPage(Page $page): ?Page
    {
        return $this->getPageFromNav($this->navigation, $page->getPath(), -1);
    }

    /**
     * {@inheritDoc}
     */
    public function getNavigation(): array
    {
        return $this->navigation;
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function isSearchable(): bool
    {
        $pluginManager = PluginManager::instance();
        return $pluginManager->exists('Winter.Search');
    }

    /**
     * {@inheritDoc}
     */
    abstract public function index(): void;

    /**
     * {@inheritDoc}
     */
    public function getDocsIdentifier(): string
    {
        return $this->docs->getIdentifier();
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getPages());
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->pages);
    }

    /**
     * {@inheritDoc}
     *
     * @return Page|null The page, or null if it does not exist.
     */
    public function offsetGet($offset): ?Page
    {
        return $this->getPage($offset);
    }

    /**
     * {@inheritDoc}
     *
     * @ignore PageLists are read-only
     */
    public function offsetSet($offset, $value): void
    {
    }

    /**
     * {@inheritDoc}
     *
     * @ignore PageLists are read-only
     */
    public function offsetUnset($offset): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->pages);
    }
}
