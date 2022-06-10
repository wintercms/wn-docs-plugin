<?php namespace Winter\Docs\Classes;

use File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Node\Query;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Winter\Docs\Classes\Contracts\PageList as PageListContact;
use Winter\Storm\Exception\ApplicationException;

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
     * The relative path to the table of contents.
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
        return $this->pageList;
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
        if (is_null($this->tocPath)) {
            $this->tocPath = $this->guessTocPath();

            if (is_null($this->tocPath)) {
                throw new ApplicationException('You must provide a table of contents.');
            }
        }
        if (!File::exists($this->tocPath)) {
            throw new ApplicationException(
                sprintf(
                    'The table of contents cannot be found at "%s".',
                    $this->tocPath
                )
            );
        }

        // Find Markdown files
        $markdownFiles = $this->getProcessFiles('md');

        foreach ($markdownFiles as $file) {
            $this->processMarkdownFile($file);
        }
    }

    /**
     * Processes a single Markdown file, converting the Markdown to HTML and storing it in the processed
     * folder.
     */
    public function processMarkdownFile(string $path)
    {
        $file = $this->getProcessPath($path);
        $directory = (str_contains($path, '/')) ? str_replace(File::basename($path), '', $path) : '';
        $fileName = File::name($file);
        $contents = File::get($file);

        // Create a CommonMark environment and parse the Markdown document for an AST.
        if (is_null($this->environment)) {
            $this->environment = $this->createMarkdownEnvironment();
        }
        $markdown = new MarkdownParser($this->environment);
        $markdownAst = $markdown->parse($contents);

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

        $this->getStorageDisk()->put(
            $this->getProcessedPath($directory . $fileName . '.htm'),
            $rendered
        );
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
            ],
            'heading_permalink' => [
                'id_prefix' => 'content',
                'fragment_prefix' => '',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
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
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TableOfContentsExtension());

        return $environment;
    }
}
