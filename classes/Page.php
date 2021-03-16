<?php namespace Winter\Docs\Classes;

use ApplicationException;
use File;
use Markdown;

/**
 * Page class.
 *
 * Represents a single page for the documentation. Documentation should be a parsed Markdown file converted to HTML.
 *
 * @author Ben Thomson
 */
class Page
{
    /**
     * @var string The title of the page
     */
    public $title;

    /**
     * @var array The chapters of the page (ie. the table of contents)
     */
    public $chapters = [];

    /**
     * @var string The page content
     */
    public $content;

    /**
     * Constructor.
     *
     * @param string $file
     */
    public function __construct($file = null)
    {
        if (!empty($file)) {
            $this->fromFile($file);
        }
    }

    /**
     * Loads a file and extracts the contents.
     *
     * @param string $file
     * @return void
     */
    public function fromFile($file = null)
    {
        if (!File::exists($file)) {
            throw new ApplicationException(
                sprintf('Documentation file \'%s\' could not be found.', $file)
            );
        }

        $extension = strtolower(File::extension($file));

        if ($extension === 'md') {
            // Parse Markdown files before manipulation
            try {
                $this->content = Markdown::parse(File::get($file));
            } catch (\Exception $e) {
                throw new ApplicationException(
                    sprintf('Documentation file \'%s\' could not be parsed as a Markdown file.', $file)
                );
            }
        } elseif ($extension === 'htm' || $extension === 'html') {
            $this->content = File::get($file);
        } else {
            throw new ApplicationException(
                sprintf('Documentation file \'%s\' must be either an HTML or a Markdown file.', $file)
            );
        }

        $this->extractMeta();
    }

    /**
     * Extracts meta information from the content of the file.
     *
     * This includes the title of the document and the table of contents, if available.
     *
     * @return void
     */
    protected function extractMeta()
    {
        if (extension_loaded('xml')) {
            // If libxml is available, use a much more reliable DOM parser to extract the title and table of contents.

            libxml_use_internal_errors(true);

            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->loadHTML($this->content);
            $body = $dom->getElementsByTagName('body');

            if ($body->length >= 1) {
                $body = $body->item(0);
            } else {
                throw new ApplicationException('Documentation file has no body content.');
            }

            if (!count(libxml_get_errors())) {
                /**
                 * One of the following must be true to get both the title and TOC:
                 *  - The first element must be a <ul> or <ol> (the TOC), followed by a <h1-6> (the title)
                 *   or
                 *  - The first element must be a <h1-6> (the title), followed by a <ul> or <ol>
                 */
                $validFirstElement = false;
                $firstTag = strtolower($body->firstChild->nodeName);

                if (in_array($firstTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $this->title = $body->firstChild->textContent;
                    $validFirstElement = 'title';
                } elseif (in_array($firstTag, ['ul', 'ol'])) {
                    $this->chapters = $this->parseTableOfContents($body->firstChild);
                    $validFirstElement = 'toc';
                }

                if ($validFirstElement) {
                    $body->removeChild($body->firstChild);

                    if ($body->hasChildNodes()) {
                        foreach ($body->childNodes as $child) {
                            $childTag = strtolower($child->nodeName);

                            // Allow break tags between title and TOC
                            if ($childTag === 'br') {
                                continue;
                            }

                            // Allow empty paragraphs between title and TOC
                            if ($childTag === 'p' && trim($child->textContent) === '') {
                                continue;
                            }

                            // Allow text
                            if ($child instanceof \DOMText) {
                                continue;
                            }

                            if (
                                in_array($childTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])
                                && $validFirstElement === 'toc'
                            ) {
                                $this->title = $child->textContent;
                                $body->removeChild($child);
                            } elseif (
                                in_array($childTag, ['ul', 'ol'])
                                && $validFirstElement === 'title'
                            ) {
                                $this->chapters = $this->parseTableOfContents($child);
                                $body->removeChild($child);
                            }
                            break;
                        }
                    }

                    $this->content = $dom->saveHTML($body);
                    $this->tidyStartOfContent();
                    return;
                }
            }
        }

        // Otherwise, use a basic (hacky) regex to extract the title. We won't attempt to extract the table of contents.
        preg_match('/^[\s\n\r]*<h1>([^<]+)<\/h1>[\n\r\s]+(.*?)$/ims', $this->content, $matches);
        $this->title = $matches[1] ?? null;
        $this->content = $matches[2] ?? null;
    }

    /**
     * Removes or reformats some initial content to clean up the code.
     *
     * @return void
     */
    protected function tidyStartOfContent()
    {
        if (extension_loaded('xml')) {
            // If libxml is available, use a much more reliable DOM parser to extract the title and table of contents.

            libxml_use_internal_errors(true);

            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->loadHTML($this->content);
            $body = $dom->getElementsByTagName('body');

            if ($body->length >= 1) {
                $body = $body->item(0);
            } else {
                throw new ApplicationException('Documentation file has no body content.');
            }

            if (!count(libxml_get_errors())) {
                $removeNodes = [];

                foreach ($body->childNodes as $node) {
                    $nodeTag = strtolower($node->nodeName);

                    // Look for <p> tags that just contain an anchor, and remove them (but keep the anchor)
                    if (
                        $nodeTag === 'p'
                        && $node->childNodes->length === 1
                        && strtolower($node->firstChild->nodeName) === 'a'
                        && $node->firstChild->getAttribute('name')
                    ) {
                        $body->replaceChild($node->firstChild, $node);
                        continue;
                    }

                    // Look for empty <p> tags and remove them
                    if ($nodeTag === 'p' && trim($node->textContent) === '') {
                        $removeNodes[] = $node;
                        continue;
                    }

                    // Remove empty text
                    if ($node instanceof \DOMText && trim($node->textContent) === '') {
                        $removeNodes[] = $node;
                        continue;
                    }

                    break;
                }

                // Remove empty nodes
                if (count($removeNodes)) {
                    foreach ($removeNodes as $node) {
                        $body->removeChild($node);
                    }
                }

                $this->content = $dom->saveHTML($body);
                return;
            }
        }
    }

    /**
     * Parses a list DOMElement for chapter (table of content) information.
     *
     * This is a recursive function.
     *
     * @param \DOMElement $list
     * @return void
     */
    protected function parseTableOfContents(\DOMElement $list)
    {
        $chapters = [];

        if ($list->hasChildNodes()) {
            foreach ($list->getElementsByTagName('li') as $item) {
                // Only direct descendants
                if ($item->parentNode !== $list) {
                    continue;
                }

                $ulLinkList = $item->getElementsByTagName('ul');
                $olLinkList = $item->getElementsByTagName('li');
                $aList = $item->getElementsByTagName('a');

                if ($aList->length >= 1) {
                    $aTag = $aList->item(0);
                    $chapter = [
                        'url' => $aTag->getAttribute('href'),
                        'anchor' => (substr($aTag->getAttribute('href'), 0, 1) === '#'),
                        'title' => $aTag->textContent,
                        'children' => [],
                    ];

                    if ($ulLinkList->length >= 1) {
                        $chapter['children'] = $this->parseTableOfContents($ulLinkList->item(0));
                    } elseif ($olLinkList->length >= 1) {
                        $chapter['children'] = $this->parseTableOfContents($olLinkList->item(0));
                    }

                    $chapters[] = $chapter;
                }
            }
        }

        return $chapters;
    }
}
