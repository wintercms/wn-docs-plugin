<?php namespace Winter\Docs\Components;

use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Winter\Docs\Classes\DocsManager;
use Winter\Storm\Exception\AjaxException;

class DocsPage extends ComponentBase
{
    /**
     * Gets the details for the component
     */
    public function componentDetails()
    {
        return [
            'name'        => 'winter.docs::lang.components.docsPage.name',
            'description' => 'winter.docs::lang.components.docsPage.description',
        ];
    }

    /**
     * Returns the properties provided by the component
     */
    public function defineProperties()
    {
        return [
            'docId' => [
                'title' => 'winter.docs::lang.components.docsPage.docId.title',
                'type' => 'dropdown',
                'required' => true,
                'placeholder' => 'winter.docs::lang.components.docsId.placeholder',
            ],
            'pageSlug' => [
                'title' => 'winter.docs::lang.components.docsPage.pageSlug.title',
                'description' => 'winter.docs::lang.components.docsPage.pageSlug.description',
                'type' => 'string',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function onRun()
    {
        $docId = $this->property('docId');
        $path = $this->property('pageSlug');

        // Load documentation
        $docsManager = DocsManager::instance();
        $docs = $docsManager->getDocumentation($docId);

        if (!$docs->isAvailable()) {
            return $this->respond404();
        }

        $pageList = $docs->getPageList();

        if (empty($path)) {
            $page = $pageList->getRootPage();
            return redirect()->to($this->controller->pageUrl($this->page->baseFileName, [
                'slug' => $page->getPath(),
            ]));
        } else {
            $page = $pageList->getPage($path);
            if (is_null($page)) {
                return $this->respond404();
            }
            $page->load($this->controller->pageUrl($this->page->baseFileName, [
                'slug' => ''
            ]));
        }
        $pageList->setActivePage($page);

        $this->page['docId'] = $docs->getIdentifier();
        $this->page['docName'] = $docs->getName();
        $this->page['docType'] = $docs->getType();
        $this->page['sourceUrl'] = $docs->getRepositoryUrl();
        $this->page['mainNav'] = $pageList->getNavigation();
        $this->page['title'] = $page->getTitle();
        $this->page->title = $page->getTitle() . ' | ' . $docs->getName();
        $this->page['pagePath'] = $page->getPath();
        $this->page['content'] = $page->getContent();
        $this->page['editUrl'] = $page->getEditUrl();
        $this->page['pageNav'] = $page->getNavigation();
        $this->page['frontMatter'] = $page->getFrontMatter();
        $this->page['previousPage'] = $pageList->previousPage($page);
        $this->page['nextPage'] = $pageList->nextPage($page);
    }

    public function onLoadPage()
    {
        $docId = $this->property('docId');
        $path = Request::post('page');

        // Load documentation
        $docsManager = DocsManager::instance();
        $docs = $docsManager->getDocumentation($docId);

        if (!$docs->isAvailable()) {
            throw new AjaxException([
                'error' => 'The documentation that you have requested does not exist',
            ]);
        }

        $pageList = $docs->getPageList();

        if (empty($path)) {
            $page = $pageList->getRootPage();
            return redirect()->to($this->controller->pageUrl($this->page->baseFileName, [
                'slug' => $page->getPath(),
            ]));
        } else {
            $page = $pageList->getPage($path);
            if (is_null($page)) {
                throw new AjaxException([
                    'error' => 'The page that you have requested does not exist',
                ]);
            }
            $page->load($this->controller->pageUrl($this->page->baseFileName, [
                'slug' => ''
            ]));
        }
        $pageList->setActivePage($page);

        return [
            'docId' => $docs->getIdentifier(),
            'docName' => $docs->getName(),
            'docType' => $docs->getType(),
            'title' => $page->getTitle(),
            'pagePath' => $page->getPath(),
            'frontMatter' => $page->getFrontMatter(),
            '#docs-menu' => $this->renderPartial('@menu', [
                'mainNav' => $pageList->getNavigation(),
                'docId' => $docs->getIdentifier(),
                'docName' => $docs->getName(),
                'docType' => $docs->getType(),
                'sourceUrl' => $docs->getRepositoryUrl(),
                'title' => $page->getTitle(),
                'pagePath' => $page->getPath(),
                'frontMatter' => $page->getFrontMatter(),
                'editUrl' => $page->getEditUrl(),
            ]),
            '#docs-content' => $this->renderPartial('@contents', [
                'content' => $page->getContent(),
                'docId' => $docs->getIdentifier(),
                'docName' => $docs->getName(),
                'docType' => $docs->getType(),
                'sourceUrl' => $docs->getRepositoryUrl(),
                'title' => $page->getTitle(),
                'pagePath' => $page->getPath(),
                'frontMatter' => $page->getFrontMatter(),
                'editUrl' => $page->getEditUrl(),
                'previousPage' => $pageList->previousPage($page),
                'nextPage' => $pageList->nextPage($page),
            ]),
            '#docs-toc' => $this->renderPartial('@toc', [
                'pageNav' => $page->getNavigation(),
                'docId' => $docs->getIdentifier(),
                'docName' => $docs->getName(),
                'docType' => $docs->getType(),
                'sourceUrl' => $docs->getRepositoryUrl(),
                'title' => $page->getTitle(),
                'pagePath' => $page->getPath(),
                'frontMatter' => $page->getFrontMatter(),
                'editUrl' => $page->getEditUrl(),
            ]),
        ];
    }

    /**
     * Get documentation options.
     *
     * @return array
     */
    public function getDocIdOptions()
    {
        $docsManager = DocsManager::instance();
        $docs = $docsManager->listDocumentation();
        $options = [];

        foreach ($docs as $doc) {
            $options[$doc['id']] = $doc['name'];
        }

        return $options;
    }

    /**
     * Responds with the correct 404 page depending on location.
     *
     * @return Response
     */
    protected function respond404()
    {
        if (App::runningInBackend()) {
            return response(View::make('backend::404'), 404);
        } else {
            $this->controller->setStatusCode(404);
            return $this->controller->run('404');
        }
    }
}
