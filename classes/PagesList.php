<?php namespace Winter\Docs\Classes;

use Backend;
use File;
use Yaml;

/**
 * Pages List class.
 *
 * Loads and collates the list of documentation pages for navigation and search.
 *
 * @author Ben Thomson
 */
class PagesList
{
    use \Winter\Storm\Support\Traits\Singleton;

    /**
     * @var array Available pages, grouped by section and category.
     */
    protected $pages = [];

    /**
     * @var string The file containing the docs menu.
     */
    protected $menuFile;

    /**
     * Constructor.
     *
     * @return void
     */
    protected function init()
    {
        $this->menuFile = storage_path('app/docs/config/toc-docs.yaml');

        if (File::exists($this->menuFile)) {
            $this->loadPagesFromFile('docs', $this->menuFile);
        }
    }

    /**
     * Determines if any pages have been loaded.
     *
     * @return bool
     */
    public function loaded()
    {
        return count($this->pages);
    }

    /**
     * Generates an array for navigation.
     *
     * @return array
     */
    public function getNavigation($section)
    {
        if (!isset($this->pages[$section])) {
            return [];
        }

        return $this->pages[$section];
    }

    /**
     * Loads the pages from a YAML table of contents file.
     *
     * @param string $section
     * @param string $file
     * @return void
     */
    protected function loadPagesFromFile($section, $file)
    {
        $content = Yaml::parseFile($file);
        $pages = [];

        foreach ($content as $contentSection => $contentDetails) {
            // Pages containing the @ symbol are external links. We'll ignore these for now.
            if (str_contains($contentSection, '@')) {
                continue;
            }

            if (isset($contentDetails['pages'])) {
                foreach ($contentDetails['pages'] as $url => $page) {
                    if (str_contains($page, '@')) {
                        continue;
                    }

                    $pages[$contentSection][$url] = [
                        'label' => $page,
                        'url' => Backend::url('docs/' . $url)
                    ];
                }
            }
        }

        $this->pages[$section] = $pages;
    }
}
