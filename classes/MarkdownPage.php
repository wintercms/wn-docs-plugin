<?php

namespace Winter\Docs\Classes;

use DOMElement;
use Winter\Docs\Classes\Contracts\Page;
use Winter\Storm\Exception\ApplicationException;

/**
 * Markdown Page instance.
 *
 * This is a representation of the Markdown page. It expects the page to have been processed into
 * raw HTML.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright Winter CMS
 */
class MarkdownPage implements Page
{
    /**
     * Markdown Documentation instance.
     */
    protected MarkdownDocumentation $docs;

    /**
     * The path of this page, relative in the processed disk storage.
     */
    protected string $path;

    /**
     * The title of the page.
     */
    protected string $title;

    /**
     * Determines if the page contents are fully loaded.
     *
     * Pages by default are only partially loaded, with the path and title, to save performance.
     * Loading a page will get all contents, and should generally be done only for the active page.
     */
    protected bool $loaded = false;

    /**
     * Constructor.
     */
    public function __construct(MarkdownDocumentation $docs, string $path, string $title)
    {
        $this->docs = $docs;
        $this->path = $path;
        $this->title = $title;
    }

    /**
     * Loads contents for this page.
     *
     * @return void
     */
    public function load()
    {
        $content = $this->docs->getProcessedFile($this->path . '.htm');
        if (is_null($content)) {
            throw new ApplicationException(
                sprintf(
                    'Unable to load processed Markdown file at path "%s"',
                    $this->path . '.htm'
                )
            );
        }

        if (extension_loaded('xml')) {
            libxml_use_internal_errors(true);

            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->loadHTML($content);
            $body = $dom->getElementsByTagName('body');

            if ($body->length >= 1) {
                $body = $body->item(0);
            } else {
                throw new ApplicationException('Documentation file has no body content.');
            }

            // If there is a table of contents for the page, it will be a <ul>, it will be
            // the first element and it will have a class "table-of-contents".
            $firstChild = $body->firstChild->nodeName;
            if ($firstChild === 'ul') {
                $class = $body->firstChild->attributes->getNamedItem('class');
                if (!is_null($class) && $class === 'table-of-contents') {
                    $this->navigation = $this->processNav($body->firstChild);
                }
                $body->removeChild($body->firstChild);
            }

            $this->content = $dom->saveHTML($body);
        } else {
            $this->navigation = [];
            $this->content = $content;
        }

        $this->loaded = true;
    }

    /**
     * Converts a DOMElement instance for the table of contents into a navigation array.
     */
    protected function processNav(DOMElement $navElement): array
    {
        $navigation = [];

        if ($navElement->childNodes->count() > 0) {
            foreach ($navElement->childNodes as $node) {
                if ($node->nodeName === 'li') {
                    $linkNode = $node->childNodes->getElementsByTagName('a')->item(0);

                    // There must be a link node
                    if (is_null($linkNode)) {
                        continue;
                    }

                    // There MAY be a subnav (<ul>) node
                    $subNavNode = $node->childNodes->getElementsByTagName('ul')->item(0);

                    $navItem = [
                        'title' => $linkNode->textContent,
                        'anchor' => $linkNode->attributes->getNamedItem('href'),
                    ];

                    if (!is_null($subNavNode)) {
                        $navItem['children'] = $this->processNav($subNavNode);
                    }

                    $navigation[] = $navItem;
                }
            }
        }

        return $navigation;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Gets the path of the page.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getNavigation(): array
    {
        if (!$this->loaded) {
            return $this->load();
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getContent(): string
    {
        if (!$this->loaded) {
            return $this->load();
        }

        return $this->content;
    }
}
