<?php namespace Winter\Docs\Components;

use Flash;
use Request;
use Cms\Classes\ComponentBase;
use Winter\Docs\Classes\DocsManager;

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
            // Docs must be processed
            return;
        }

        $pageList = $docs->getPageList();

        if (empty($path)) {
            $page = $pageList->getRootPage();
            $page->load();
        } else {
            $page = $pageList->getPage($path);
            if (is_null($page)) {
                // 404
                return;
            }
            $page->load();
        }
        $pageList->setActivePage($page);

        $this->page['docId'] = $docs->getIdentifier();
        $this->page['docTitle'] = $docs->getName();
        $this->page['docType'] = $docs->getType();
        $this->page['title'] = $page->getTitle();
        $this->page['content'] = $page->getContent();
        $this->page['mainNav'] = $pageList->getNavigation();
        $this->page['pageNav'] = $page->getNavigation();
    }

    public function onLoadPage()
    {
        $docId = $this->property('docId');
        $path = Request::post('page');

        // Load documentation
        $docsManager = DocsManager::instance();
        $docs = $docsManager->getDocumentation($docId);

        if (!$docs->isAvailable()) {
            Flash::error('Documentation does not exist');
            return;
        }

        $pageList = $docs->getPageList();

        if (empty($path)) {
            $page = $pageList->getRootPage();
            $page->load();
        } else {
            $page = $pageList->getPage($path);
            if (is_null($page)) {
                Flash::error('The page you have requested does not exist');
                return;
            }
            $page->load();
        }
        $pageList->setActivePage($page);

        return [
            'docId' => $docs->getIdentifier(),
            'docName' => $docs->getName(),
            'docType' => $docs->getType(),
            'title' => $page->getTitle(),
            'path' => $path,
            '#docs-menu' => $this->renderPartial('@menu', [
                'mainNav' => $pageList->getNavigation(),
            ]),
            '#docs-content' => $this->renderPartial('@contents', [
                'content' => $page->getContent(),
            ]),
            '#docs-toc' => $this->renderPartial('@toc', [
                'pageNav' => $page->getNavigation(),
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
}
