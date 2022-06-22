<?php

namespace Winter\Docs\Classes;

use File;
use Yaml;
use Config;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Query;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Winter\Docs\Classes\Contracts\PageList as PageListContact;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Support\Str;

/**
 * Markdown Documentation instance.
 *
 * This class represents an entire instance of Markdown documentation. Markdown documentation is
 * generally used for user and developer documentation.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright Winter CMS
 */
class MarkdownDocumentation extends BaseDocumentation
{
    /**
     * The relative path to the table of contents in the source.
     */
    protected ?string $tocPath = null;

    /**
     * The page list instance.
     */
    protected ?PageListContact $pageList = null;

    /**
     * The CommonMark environment for manipulating and rendering Markdown docs.
     */
    protected ?Environment $environment = null;

    /**
     * Constructor.
     */
    public function __construct(string $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        $this->tocPath = $config['tocPath'] ?? null;
    }

    /**
     * Gets the page list instance.
     *
     * The page list instance is used for navigation and searching documentation.
     *
     * @return PageListContact
     */
    public function getPageList(): PageListContact
    {
        if (!$this->isProcessed()) {
            throw new ApplicationException(
                sprintf(
                    'The "%s" documentation must be processed before a page list can be retrieved.',
                    $this->identifier
                ),
            );
        }

        if (!is_null($this->pageList)) {
            return $this->pageList;
        }

        return $this->pageList = new MarkdownPageList(
            $this,
            $this->getStorageDisk()->get($this->getProcessedPath('page-map.json')),
            $this->getStorageDisk()->get($this->getProcessedPath('toc.json'))
        );
    }

    /**
     * Processes the documentation.
     *
     * This will take the Markdown files and assets within the documentation and convert it into
     * HTML files.
     *
     * @return void
     */
    public function process(): void
    {
        // Find Markdown files
        $pageMap = [];
        $markdownFiles = $this->getProcessFiles('md');

        foreach ($markdownFiles as $file) {
            $page = $this->processMarkdownFile($file);
            $pageMap[$page['path']] = $page;
        }

        // Order page map by path
        ksort($pageMap, SORT_NATURAL);

        // Create page map
        $this->getStorageDisk()->put(
            $this->getProcessedPath('page-map.json'),
            json_encode($pageMap),
        );

        // Generate table of contents
        $tocPath = ($this->tocPath && File::exists($this->getProcessPath($this->tocPath)))
            ? $this->getProcessPath($this->tocPath)
            : $this->guessTocPath();

        if (is_null($tocPath)) {
            // Create a TOC from the page list
            $toc = $this->autoGenerateToc($pageMap);
        } else {
            $toc = $this->processTocFile($tocPath, $pageMap);
        }
        $this->getStorageDisk()->put(
            $this->getProcessedPath('toc.json'),
            json_encode($toc),
        );
    }

    /**
     * Processes a single Markdown file, converting the Markdown to HTML and storing it in the processed
     * folder.
     *
     * @return array An array that represents the meta of this file. It should contain the following:
     *  - `path`: The path to the file, without any extensions - will be used as the slug
     *  - `fileName`: The path to the file, with the final extension (.htm)
     *  - `title`: The title of the page
     */
    public function processMarkdownFile(string $path): array
    {
        $file = $this->getProcessPath($path);
        $directory = (str_contains($path, '/')) ? str_replace(File::basename($path), '', $path) : '';
        $fileName = File::name($file);
        $contents = File::get($file);
        $title = null;

        // Create a CommonMark environment and parse the Markdown document for an AST.
        if (is_null($this->environment)) {
            $this->environment = $this->createMarkdownEnvironment();
        }

        $frontMatterParser = new FrontMatterParser(new SymfonyYamlFrontMatterParser());
        $parts = $frontMatterParser->parse($contents);
        $frontMatter = $parts->getFrontMatter();
        $contents = $parts->getContent();
        $markdownParser = new MarkdownParser($this->environment);
        $markdownAst = $markdownParser->parse($contents);

        // Find a title, if available
        if (!empty($frontMatter['title'])) {
            $title = $frontMatter['title'];
        } else {
            $matching = (new Query)
                ->where(Query::type(Heading::class))
                ->findAll($markdownAst);

            foreach ($matching as $node) {
                if ($node->getLevel() === 1) {
                    $children = $node->children();

                    foreach ($children as $child) {
                        if ($child instanceof Text) {
                            $title = $child->getLiteral();
                        }
                    }
                }
            }
        }

        // If no title was found, try to convert the filename into a title
        if (is_null($title)) {
            $title = Str::title($fileName);
        }

        // Find all links and images, and correct the URLs
        $matching = (new Query)
            ->where(Query::type(Link::class))
            ->orWhere(Query::type(Image::class))
            ->findAll($markdownAst);

        foreach ($matching as $node) {
            if ($node instanceof Link) {
                $url = $node->getUrl();

                // Skip hashbang or external links
                if (starts_with($url, ['#', 'http://', 'https://'])) {
                    continue;
                }

                // Remove .md extension from internal links
                if (preg_match('/\.md($|[#?])/', $url)) {
                    $node->setUrl(preg_replace('/(\.md)($|[#?])/', '$2', $url));
                }
            }
        }

        // Render the document
        $renderer = new HtmlRenderer($this->environment);
        $rendered = $renderer->renderDocument($markdownAst);

        // Prepend the front matter, if available
        if (!empty($frontMatter)) {
            $rendered = '<script id="frontMatter" type="application/json">'
                . json_encode($frontMatter)
                . '</script>' . "\n"
                . $rendered;
        }

        $this->getStorageDisk()->put(
            $this->getProcessedPath($directory . $fileName . '.htm'),
            $rendered
        );

        return [
            'path' => $directory . $fileName,
            'fileName' => $directory . $fileName . '.htm',
            'title' => $title,
        ];
    }

    /**
     * Guesses the location of the table of contents file.
     *
     * @return string|null
     */
    protected function guessTocPath(): ?string
    {
        if (!$this->isDownloaded()) {
            return null;
        }

        $paths = [
            $this->getProcessPath('toc.yaml'),
            $this->getProcessPath('toc-docs.yaml'),
            $this->getProcessPath('contents.yaml'),
            $this->getProcessPath('menu.yaml'),
            $this->getProcessPath('config/toc.yaml'),
            $this->getProcessPath('config/toc-docs.yaml'),
            $this->getProcessPath('config/contents.yaml'),
            $this->getProcessPath('menu.yaml'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Creates a custom CommonMark environment for use with the docs.
     *
     * @return Environment
     */
    protected function createMarkdownEnvironment()
    {
        $config = [
            'default_attributes' => [
                Heading::class => [
                    'class' => static function (Heading $node) {
                        if ($node->getLevel() === 1) {
                            return 'main-title';
                        } else {
                            return null;
                        }
                    }
                ],
                Link::class => [
                    'class' => static function (Link $node) {
                        if ($node->firstChild() instanceof Code) {
                            return 'code-link';
                        }
                        return null;
                    },
                ],
            ],
            'external_link' => [
                'internal_hosts' => Config::get('app.trustedHosts', false) ?: [],
                'open_in_new_window' => true,
                'html_class' => 'external-link',
            ],
            'heading_permalink' => [
                'id_prefix' => 'content',
                'fragment_prefix' => '',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'symbol' => '#'
            ],
            'table_of_contents' => [
                'min_heading_level' => 2,
                'max_heading_level' => 3,
            ],
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => [
                        'class' => 'table-container',
                    ],
                ],
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new DefaultAttributesExtension());
        $environment->addExtension(new DisallowedRawHtmlExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TableOfContentsExtension());

        return $environment;
    }

    /**
     * Processes a table of contents file from the source and creates a formatted navigation.
     */
    protected function processTocFile(string $path, array $pageMap): array
    {
        $contents = Yaml::parse(File::get($path));

        return [
            'root' => $contents['rootPage'] ?? $this->guessRootPage($pageMap),
            'navigation' => (isset($contents['sections']))
                ? $this->processTocSections($contents['sections'])
                : $this->processTocPages($contents['pages'] ?? [])
        ];
    }

    /**
     * Guesses the root page of the documentation.
     *
     * This is a fallback if no root page is specified. It will look for the first file it finds
     * out of the following:
     *  - `index`
     *  - `home`
     *  - `main`
     *  - `docs`
     *
     * If it finds one, that path will be returned, otherwise `null` will be returned.
     */
    protected function guessRootPage(array $pageMap): ?string
    {
        $paths = array_keys($pageMap);
        $found = null;

        foreach (['index', 'home', 'main', 'docs'] as $root) {
            if (in_array($root, $paths)) {
                $found = $root;
                break;
            }
        }

        return $found;
    }

    /**
     * Processes a sections definition in the table of contents file.
     */
    protected function processTocSections(array $sections): array
    {
        $navigation = [];

        foreach ($sections as $title => $section) {
            $sectionNav = [
                'title' => $title,
                'children' => [],
            ];

            if (isset($section['pages'])) {
                foreach ($section['pages'] as $path => $pageTitle) {
                    $sectionNav['children'][] = [
                        'title' => $pageTitle,
                        'path' => $path,
                        'external' => $this->isExternalPath($path),
                    ];
                }
            }

            $navigation[] = $sectionNav;
        }

        return $navigation;
    }

    /**
     * Processes a pages definition in the table of contents file.
     */
    protected function processTocPages(array $pages): array
    {
        $navigation = [];

        foreach ($pages as $path => $pageTitle) {
            $navigation[] = [
                'title' => $pageTitle,
                'path' => $path,
                'external' => $this->isExternalPath($path),
            ];
        }

        return $navigation;
    }

    /**
     * Determines if the given path looks to be an external link.
     */
    protected function isExternalPath(string $path): bool
    {
        return str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '/')
            || str_starts_with($path, '../');
    }
}
