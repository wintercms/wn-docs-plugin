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
     * Get a list of all pages in this Page List.
     *
     * This will return an array of Page objects for each list, keyed by the ID/path of the page. If no pages exist,
     * an
     *
     * @return array
     */
    public function getPages(): array;

    public function getNavigation(): array;

    public function getPage(string $page): ?Page;

    public function previousPage(Page $page): ?Page;

    public function nextPage(Page $page): ?Page;

    public function search(string $query): array;
}
