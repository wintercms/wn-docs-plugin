<?php
return [
    'plugin' => [
        'name' => 'Docs',
        'description' => 'Documentation suite for Winter CMS.'
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
        'docsList' => [
            'name' => 'Documentation list',
            'description' => 'Lists available documentation',
        ],
    ],
    'menuitem' => [
        'docs' => 'Documentation',
        'docs-page' => 'Specific documentation page',
    ],
];
