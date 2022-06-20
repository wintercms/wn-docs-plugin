<?php

namespace Winter\Docs\Classes;

use DOMElement;
use Winter\Docs\Classes\Contracts\Page;
use Winter\Storm\Exception\ApplicationException;

/**
 * HTML Page instance.
 *
 * This is a representation of the documentation page. It expects the page to have been processed into
 * raw HTML.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright Winter CMS
 */
class HtmlPage implements Page
{
    /**
     * Documentation instance.
     */
    protected BaseDocumentation $docs;

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
    public function __construct(BaseDocumentation $docs, string $path, string $title)
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
    public function load(string $pageUrl = '')
    {
        $content = $this->docs->getProcessedFile($this->path . '.htm');
        if (is_null($content)) {
            throw new ApplicationException(
                sprintf(
                    'Unable to load processed file at path "%s"',
                    $this->path . '.htm'
                )
            );
        }

        if (extension_loaded('xml')) {
            libxml_use_internal_errors(true);

            $dom = new \DOMDocument('1.0', 'UTF-8');
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
                $class = $body->firstChild->attributes->getNamedItem('class')->value;
                if (!is_null($class) && $class === 'table-of-contents') {
                    $this->navigation = $this->processNav($body->firstChild);
                }
                $body->removeChild($body->firstChild);
            }

            // Look for links with "page:" prefixes and replace them with a proper page link
            $links = $dom->getElementsByTagName('a');

            if ($links->length >= 1) {
                foreach ($links as $link) {
                    $href = $link->getAttributeNode('href');
                    if ($href !== false && str_starts_with($href->value, 'path:')) {
                        $pagePath = str_after($href->value, 'path:');
                        $href->value = $pageUrl . '/' . $pagePath;
                    }
                }
            }

            $this->content = utf8_decode($dom->saveHTML($body));
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
                    $linkNode = null;
                    $spanNode = null;
                    $subNavNode = null;

                    foreach ($node->childNodes as $childNode) {
                        if (empty($childNode->tagName)) {
                            continue;
                        }

                        if ($childNode->tagName === 'a') {
                            $linkNode = $childNode;
                        } elseif ($childNode->tagName === 'span') {
                            $spanNode = $childNode;
                        } elseif ($childNode->tagName === 'ul') {
                            $subNavNode = $childNode;
                        }
                    }

                    // There must be a link node or a span node
                    if (is_null($linkNode) && is_null($spanNode)) {
                        continue;
                    }

                    if (!is_null($linkNode)) {
                        $navItem = [
                            'title' => $linkNode->textContent,
                            'anchor' => $linkNode->attributes->getNamedItem('href')->value,
                        ];
                    } else {
                        $navItem = [
                            'title' => $spanNode->textContent,
                        ];
                    }

                    // There MAY be a subnav (<ul>) node
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

        return $this->navigation;
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
