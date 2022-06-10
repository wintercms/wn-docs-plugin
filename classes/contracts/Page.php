<?php namespace Winter\Docs\Classes\Contracts;

/**
 * Page contract
 *
 * A Page as an individual page of content for a documentation.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @author Winter CMS
 */
interface Page
{
    /**
     * Gets the title of the page.
     */
    public function getTitle(): string;

    /**
     * Gets a navigation list for the purpose of displaying a table of contents.
     *
     * Navigation lists can be unlimited levels deep. Each item should have a title, and contain a `title` attribute,
     * and either an anchor (for a linked section of the content), and/or `children` (for a section with sub-sections).
     */
    public function getNavigation(): array;

    /**
     * Gets the content of the page. This should generally be the rendered HTML.
     */
    public function getContent(): string;
}
