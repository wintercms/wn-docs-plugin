<?php

namespace Winter\Docs\Components;

use Cms\Classes\ComponentBase;
use Winter\Docs\Classes\DocsManager;

class DocsList extends ComponentBase
{
    /**
     * Gets the details for the component
     */
    public function componentDetails()
    {
        return [
            'name'        => 'winter.docs::lang.components.docsList.name',
            'description' => 'winter.docs::lang.components.docsList.description',
        ];
    }

    public function docs()
    {
        // Load documentation
        $docsManager = DocsManager::instance();
        return $docsManager->listDocumentation();
    }
}
