<?php namespace Winter\Docs\Classes\Contracts;

use Winter\Docs\Classes\PageList;

/**
 * Documentation contract
 *
 * A documentation is a singular collection of documentation content. This can be used to document systems, plugins,
 * events and much more. Documentation can be made up of Markdown content, API documentation or event documentation,
 * and can be either sourced locally, or from a remote location as a ZIP archive.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @author Winter CMS
 */
interface Documentation
{
    /**
     * Determines if the documentation is available.
     *
     * For local documentation, or documentation included with the plugin, this will generally be true. For remote
     * documentation, this will only be true if the remote documentation has been downloaded and processed.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Determines if the documentation is processed.
     *
     * This determines if the documentation has been processed and converted to HTML for display.
     *
     * @return bool
     */
    public function isProcessed(): bool;

    /**
     * Determines if the documentation is downloaded.
     *
     * For local documentation, this will always be true. For remote documentation, this will only be true if the
     * remote documentation has been downloaded.
     *
     * @return bool
     */
    public function isDownloaded(): bool;

    /**
     * Provides a PageList instance for the documentation.
     *
     * A PageList provides a collated list of pages available for the documentation.
     *
     * @return PageList
     */
    public function getPageList(): PageList;
}
