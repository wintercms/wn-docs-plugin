<?php

namespace Winter\Docs\Classes;

use File;
use Illuminate\Support\Facades\App;
use Twig\TemplateWrapper;
use Winter\Docs\Classes\Contracts\PageList as PageListContact;
use Winter\Storm\Exception\ApplicationException;

/**
 * PHP API Documentation instance.
 *
 * This class represents an entire instance of PHP API documentation.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright Winter CMS
 */
class PHPApiDocumentation extends BaseDocumentation
{
    /**
     * The page list instance.
     */
    protected ?PageListContact $pageList = null;

    /**
     * Paths to collate source APIs from.
     */
    protected array $sourcePaths = [];

    /**
     * Path to the Twig template for rendering the API docs.
     */
    protected string $template;

    /**
     * Prepared template for rendering API docs.
     */
    protected TemplateWrapper $preparedTemplate;

    /**
     * Page map.
     */
    protected array $pageMap = [];

    /**
     * @inheritDoc
     */
    public function __construct(string $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        $this->sourcePaths = $config['sourcePaths'] ?? [];
        $this->template = $config['template'] ?? base_path('plugins/winter/docs/views/api-doc.twig');
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

        return $this->pageList = new PHPApiPageList(
            $this,
            $this->getStorageDisk()->get($this->getProcessedPath('page-map.json')),
            $this->getStorageDisk()->get($this->getProcessedPath('toc.json'))
        );
    }

    /**
     * Processes the documentation.
     *
     * This will get all PHP files within the documentation and convert it into HTML API documentation.
     *
     * @return void
     */
    public function process(): void
    {
        if ($this->isLocalStorage()) {
            $basePath = $this->path;
        } else {
            $basePath = $this->getProcessPath();
        }

        $apiParser = new ApiParser($basePath, $this->sourcePaths, $this->ignoredPaths);
        $apiParser->parse();
        $classMap = $apiParser->getClassMap();

        // Prepare Twig template
        $twig = App::make('twig.environment');
        $this->preparedTemplate = $twig->createTemplate(File::get($this->template));

        $nav = [];
        $this->processClassLevel($apiParser, $classMap, $nav);

        // Create page map
        $this->getStorageDisk()->put(
            $this->getProcessedPath('page-map.json'),
            json_encode($this->pageMap),
        );

        // Create table of contents
        $this->getStorageDisk()->put(
            $this->getProcessedPath('toc.json'),
            json_encode([
                'root' => array_keys($this->pageMap)[0],
                'navigation' => $nav
            ]),
        );
    }

    protected function processClassLevel(ApiParser $parser, array $classMap, array &$nav, string $baseNamespace = '')
    {
        foreach ($classMap as $key => $value) {
            if (is_array($value)) {
                $children = [];

                $this->processClassLevel($parser, $value, $children, ltrim($baseNamespace . '/' . $key, '/'));
                $nav[] = [
                    'title' => $key,
                    'children' => $children,
                ];
                continue;
            }

            $navItem = [
                'title' => $key,
                'path' => $baseNamespace . '/' . $key,
            ];

            $this->pageMap[$baseNamespace . '/' . $key] = [
                'path' => $baseNamespace . '/' . $key,
                'fileName' => $baseNamespace . '/' . $key . '.htm',
                'title' => $key,
            ];

            // Create docs
            $this->getStorageDisk()->put(
                $this->getProcessedPath(ltrim($baseNamespace . '/' . $key . '.htm')),
                $this->preparedTemplate->render([
                    'class' => $parser->getClass($value),
                ])
            );

            $nav[] = $navItem;
        }
    }
}
