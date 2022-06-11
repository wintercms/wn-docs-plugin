<?php namespace Winter\Docs\Classes\Contracts;

/**
 * Page list contract
 *
 * A Page List is an index of pages available within a documentation. The Page List is used to provide a table of
 * contents, an index and a way of navigating to specific pages.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @author Winter CMS
 */
interface PageList
{
    /**
     * Gets a list of all pages in this Page List.
     *
     * This will return an array of Page objects for each list, keyed by the ID/path of the page. If no pages exist,
     * an empty array will be returned.
     *
     * @return Page[]
     */
    public function getPages(): array;

    /**
     * Gets the root page which acts as the "home" page for the documentation.
     *
     * @return Page
     */
    public function getRootPage(): Page;

    /**
     * Gets a navigation list for the purpose of displaying a table of contents.
     *
     * Navigation lists can be unlimited levels deep. Each item should have a title, and contain
     * a `title` attribute, and either a `path` (for a page), or `children` (for a section with
     * sub-navigation items). If a page is active, it will contain an `active` key set to true. For
     * sections with an active page within, they will contain a `childActive` key set to true.
     */
    public function getNavigation(): array;

    /**
     * Finds a page from a given path.
     *
     * If the path exists within the page list, it will return a Page object for that path. Otherwise, it will return
     * `null`.
     */
    public function getPage(string $path): ?Page;

    /**
     * Sets the active page within the documentation.
     *
     * This allows the navigation to determine which page is active.
     */
    public function setActivePage(Page $page): void;

    /**
     * Gets the previous page.
     *
     * This is a helper method for navigation. When provided a page, it will get either the previous page of the current
     * section, or the last page of the previous section. If there's no previous page, this will return `null`.
     */
    public function previousPage(Page $page): ?Page;

    /**
     * Gets the next page.
     *
     * This is a helper method for navigation. When provided a page, it will get either the next page of the current
     * section, or the first page of the next section. If there's no next page, this will return `null`.
     */
    public function nextPage(Page $page): ?Page;

    /**
     * Search the current page list.
     *
     * Executes a search query within the current Page List and returns any pages that match the query in an array. If
     * no results are found, an empty array will be returned.
     */
    public function search(string $query): array;

    /**
     * Creates a search index for the available pages in the documentation.
     */
    public function index(): void;
}
