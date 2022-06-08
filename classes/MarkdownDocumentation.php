<?php namespace Winter\Docs\Classes;

use File;
use Markdown;
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

        // Convert YAML file to a PageList instance.
        $this->pageList = new MarkdownPageList();
        $this->pageList->fromMenuFile($this->tocPath);
        $ignoredPaths = $this->pageList->getIgnoredPaths();

        $markdownFiles = $this->getProcessFiles('md', $ignoredPaths);

        foreach ($markdownFiles as $file) {
            $this->processMarkdownFile($file);
        }
    }

    /**
     * Processes a single Markdown file, converting the Markdown to HTML and storing it in the processed
     * folder.
     */
    public function processMarkdownFile(string $path): string
    {
        $file = $this->getProcessPath($path);
        $directory = (str_contains($path, '/')) ? str_replace(File::basename($path), '', $path) : '';
        $fileName = File::name($file);
        $contents = File::get($file);

        $rendered = Markdown::parse($contents);

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
}
