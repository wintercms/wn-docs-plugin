<?php
return [
    'plugin' => [
        'name' => 'Docs',
        'description' => 'Documentation suite for Winter CMS.'
    ],
    'titles' => [
        'documentation' => 'Documentation',
        'installDocs' => 'Install documentation',
        'updateDocs' => 'Refresh documentation',
    ],
    'links' => [
        'docsLink' => 'Read the documentation',
    ],
    'buttons' => [
        'updateDocs' => 'Refresh documentation',
        'copyCode' => 'Copy code',
    ],
    'updates' => [
        'downloading' => 'Downloading documentation from repository...',
        'extracting' => 'Extracting documentation...',
        'rendering' => 'Rendering documentation...',
        'finalizing' => 'Finalizing...',
        'success' => 'The documentation has been successfully updated.',
    ],
    'components' => [
        'docsPage' => [
            'name' => 'Documentation page',
            'description' => 'Displays documentation pages',
            'docId' => [
                'title' => 'Documentation to display',
                'placeholder' => 'Select a documentation',
            ],
            'pageSlug' => [
                'title' => 'Page slug',
                'description' => 'The page slug will be used to determine the documentation page.',
            ],
        ],
    ],
];
