<?php

namespace Winter\Docs\Classes;

use Winter\Storm\Exception\ApplicationException;
use Winter\Docs\Classes\Contracts\Page;

/**
 * Pages List class.
 *
 * Loads and collates the list of documentation pages for navigation and search.
 *
 * @author Ben Thomson
 */
class PHPApiPageList extends BasePageList
{
    /**
     * The Markdown Documentation instance.
     */
    protected PHPApiDocumentation $docs;

    /**
     * Generates the page list from a page map and the table of contents files.
     */
    public function __construct(PHPApiDocumentation $docs, string $pageMap, string $toc)
    {
        foreach (json_decode($pageMap, true) as $path => $page) {
            $this->pages[$path] = new HtmlPage($docs, $path, $page['title']);
        }

        $tocData = json_decode($toc, true);
        if (!array_key_exists($tocData['root'], $this->pages)) {
            throw new ApplicationException('The root page specified for the documentation does not exist');
        }

        $this->rootPage = $this->pages[$tocData['root']];
        $this->navigation = $tocData['navigation'];
    }

    /**
     * @inheritDoc
     */
    public function getPage(string $path): ?Page
    {
        $page = parent::getPage($path);

        if (is_null($page)) {
            return null;
        }

        $page->load();

        return $page;
    }

    /**
     * @inheritDoc
     */
    public function index(): void
    {
        $index = new MarkdownPageIndex([
            'pageList' => $this,
        ]);
        $index->index();
    }
}
