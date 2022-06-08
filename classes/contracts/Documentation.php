<?php namespace Winter\Docs\Classes\Contracts;

/**
 * Documentation contract
 *
 * A documentation is a singular collection of documentation content. This can be used to document systems, plugins,
 * events and much more. Documentation can be made up of Markdown content, API documentation or event documentation,
 * and can be either sourced locally, or from a remote location as a ZIP archive.
 *
 * The Documentation class is a manager for this collection of documentation. It handles the retrieval and processing
 * of the documentation, and provides a Page List of the contents.
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
     */
    public function isAvailable(): bool;

    /**
     * Determines if the documentation is processed.
     *
     * This determines if the documentation has been processed and converted to HTML for display.
     */
    public function isProcessed(): bool;

    /**
     * Determines if the documentation is downloaded.
     *
     * For local documentation, this will always be true. For remote documentation, this will only be true if the
     * remote documentation has been downloaded.
     */
    public function isDownloaded(): bool;

    /**
     * Processes documentation into a readable format.
     *
     * Processed documentation should generally be stored as rendered HTML pages, with a JSON schema
     * providing the table of contents.
     */
    public function process(): void;

        /**
     * Provides a PageList instance for the documentation.
     *
     * A PageList provides a collated list of pages available for the documentation.
     *
     * In general, this function should be run after the documentation is processed.
     */
    public function getPageList(): PageList;
}
