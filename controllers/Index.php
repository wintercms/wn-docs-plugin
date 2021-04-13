<?php namespace Winter\Docs\Controllers;

use ApplicationException;
use BackendMenu;
use File;
use Flash;
use Http;
use Lang;
use Markdown;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redirect;
use Winter\Storm\Filesystem\Zip;
use Winter\Docs\Classes\Page;
use Winter\Docs\Classes\PagesList;

/**
 * Index controller
 *
 * Handles the documentation area.
 *
 * @author Ben Thomson
 */
class Index extends \Backend\Classes\Controller
{
    /**
     * @var string Specifies a path to the asset directory.
     */
    public $assetPath = '~/plugins/winter/docs/assets';

    /**
     * @var string The ZIP file to download the documentation source.
     */
    protected $docsRepoZip = 'https://github.com/wintercms/docs/archive/main.zip';

    /**
     * @var string Temporary storage directory.
     */
    protected $tempDirectory;

    /**
     * @var string Rendered documentation storage directory.
     */
    protected $renderDirectory;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        if ($this->action == 'backend_preferences') {
            $this->requiredPermissions = ['backend.manage_preferences'];
        }

        $this->addJs('/plugins/winter/docs/assets/js/docsUpdater.js');

        BackendMenu::setContext('Winter.Docs', 'docs');
    }

    /**
     * Loads and displays the documentation pages.
     *
     * @param string $slug
     * @return void
     */
    public function index()
    {
        $path = implode('/', func_get_args());

        BackendMenu::registerContextSidenavPartial('Winter.Docs', 'docs', 'sidenav-tree');

        $this->bodyClass = 'has-sidenav-tree';
        $this->addCss([
            'less/side.less',
        ]);

        $this->vars['loaded'] = PagesList::instance()->loaded();
        $this->vars['items'] = PagesList::instance()->getNavigation('docs');

        if (empty($path)) {
            $this->pageTitle = Lang::get('winter.docs::lang.titles.documentation');
            $this->vars['content'] = Lang::get('winter.docs::lang.content.intro');
            $this->vars['active'] = false;
            $this->vars['showSidePanel'] = false;
            $this->vars['showRefresh'] = true;
        } else {
            $this->addCss([
                'less/content.less',
                'vendor/highlight/styles/tomorrow-night.css',
            ]);
            $this->addJs([
                'js/docsContent.js',
                'vendor/highlight/highlight.pack.js',
            ], [
                'id' => 'docs-content-js',
                'data-lang-copy-code' => Lang::get('winter.docs::lang.buttons.copyCode'),
            ]);

            $page = $this->getPage($path);

            $this->vars['title'] = $this->pageTitle = $page->title;
            $this->vars['content'] = $page->content;
            $this->vars['chapters'] = $page->chapters;
            $this->vars['active'] = $path;
            $this->vars['showSidePanel'] = true;
            $this->vars['showRefresh'] = false;
        }
    }

    /**
     * AJAX handler that returns the documentation update steps.
     *
     * @return void
     */
    public function onUpdateDocs()
    {
        $updateSteps = [
            [
                'code' => 'downloadUpdates',
                'label' => Lang::get('winter.docs::lang.updates.downloading'),
            ],
            [
                'code' => 'extractUpdates',
                'label' => Lang::get('winter.docs::lang.updates.extracting'),
            ],
            [
                'code' => 'renderingDocs',
                'label' => Lang::get('winter.docs::lang.updates.rendering'),
            ],
            [
                'code' => 'completeUpdate',
                'label' => Lang::get('winter.docs::lang.updates.finalizing'),
            ],
        ];

        $this->vars['updateSteps'] = $updateSteps;
        $this->vars['install'] = (bool) post('install', false);
        return $this->makePartial('refresh');
    }

    /**
     * Executes an update step.
     *
     * @return void
     */
    public function onExecuteStep()
    {
        @set_time_limit(3600);

        $stepCode = post('code');

        switch ($stepCode) {
            case 'downloadUpdates':
                $this->downloadUpdates();
                break;
            case 'extractUpdates':
                $this->extractUpdates();
                break;
            case 'renderingDocs':
                $this->renderDocs();
                break;
            case 'completeUpdate':
                Flash::success(Lang::get('winter.docs::lang.updates.success'));
                return Redirect::refresh();
                break;
        }
    }

    /**
     * Finds a page by a given path.
     *
     * @param string $path
     * @return Page
     */
    protected function getPage($path)
    {
        $normalizedPath = str_replace('/', '-', $path);

        return new Page($this->getRenderDirectory() . '/' . $normalizedPath . '.html');
    }

    /**
     * Downloads the documentation from the repository and stores it within the temp folder.
     *
     * @return void
     */
    protected function downloadUpdates()
    {
        $tempPath = $this->getTempDirectory() . '/' . 'docs-repo.zip';

        $result = Http::get($this->docsRepoZip, function ($http) use ($tempPath) {
            $http->toFile($tempPath);
        });

        if ($result->code != 200) {
            throw new ApplicationException('Unable to download documentation.');
        }
    }

    /**
     * Extracts the documentation from the repository ZIP file.
     *
     * @return void
     */
    protected function extractUpdates()
    {
        $tempPath = $this->getTempDirectory() . '/' . 'docs-repo.zip';
        $destFolder = $this->tempDirectory;

        // Remove old extract folder if it exists
        if (File::exists($destFolder . '/docs-main')) {
            File::deleteDirectory($destFolder . '/docs-main');
        }

        if (!Zip::extract($tempPath, $destFolder)) {
            throw new ApplicationException(Lang::get('winter.docs::lang.updates.extractFailed', [
                'file' => $tempPath
            ]));
        }

        @unlink($tempPath);
    }

    /**
     * Renders the documentation as HTML files.
     *
     * This will iterate through the Markdown documents from the documentation repository and render each one as
     * an HTML file for display in the documentation area.
     *
     * It will also move other files (ie. configuration and images) into their specific folders.
     *
     * @return void
     */
    protected function renderDocs()
    {
        $renderDir = $this->getRenderDirectory();
        $tempFolder = $this->getTempDirectory() . '/docs-main';

        // Clear out old rendered docs
        if (count(File::files($renderDir)) > 0) {
            File::deleteDirectory($renderDir, true);
        }

        $files = (is_dir($tempFolder))
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempFolder))
            : [];

        foreach ($files as $file) {
            // YAML files - structure and menus
            if (File::isFile($file) && File::extension($file) === 'yaml') {
                if (!File::exists($renderDir . '/config')) {
                    File::makeDirectory($renderDir . '/config', 0777, true);
                }

                File::move($file, $renderDir . '/config/' . File::basename($file));
            }

            // Image files
            if (File::isFile($file) && in_array(File::extension($file), ['jpg', 'jpeg', 'png', 'gif'])) {
                if (!File::exists($renderDir . '/images')) {
                    File::makeDirectory($renderDir . '/images', 0777, true);
                }

                File::move($file, $renderDir . '/images/' . File::basename($file));
            }

            // Markdown files - documentation content
            if (File::isFile($file) && File::extension($file) === 'md') {
                $filename = File::name($file);
                $html = Markdown::parse(File::get($file));
                File::put($renderDir . '/' . $filename . '.html', $html);
            }
        }

        // Remove temp folder
        File::deleteDirectory($tempFolder);
    }

    /**
     * Gets the directory that will store temporary files for the update process.
     *
     * If the directory does not exist, it will be created.
     *
     * @return string
     */
    protected function getTempDirectory()
    {
        $tempDirectory = temp_path();

        if (!File::isDirectory($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0777, true);
        }

        return $this->tempDirectory = $tempDirectory;
    }

    /**
     * Gets the directory that will store the rendered Markdown files.
     *
     * If the directory does not exist, it will be created.
     *
     * @return string
     */
    protected function getRenderDirectory()
    {
        $renderDirectory = storage_path('app/docs');

        if (!File::isDirectory($renderDirectory)) {
            File::makeDirectory($renderDirectory, 0777, true);
        }

        return $this->renderDirectory = $renderDirectory;
    }
}
