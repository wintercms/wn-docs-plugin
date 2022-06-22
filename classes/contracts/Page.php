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
     * Gets the page path, relative to the documentation.
     */
    public function getPath(): string;

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

    /**
     * Gets the front matter as an array.
     *
     * Front matter is metadata that's defined in the source documentation files. It's useful for storing
     * custom data such as meta tag content, a custom title for the page, and so on.
     *
     * @return array
     */
    public function getFrontMatter(): array;
}
