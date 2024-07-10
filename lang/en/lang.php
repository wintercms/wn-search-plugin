<?php

return [
    'plugin' => [
        'name' => 'Search',
        'description' => 'Implements full-text searching capabilities into Winter.'
    ],
    'otherPlugins' => [
        'cmsPages' => 'CMS Pages',
        'staticPages' => 'Static Pages',
        'winterBlog' => 'Winter Blog',
    ],
    'components' => [
        'search' => [
            'name' => 'Search',
            'description' => 'Adds a search feature into a template',
            'groups' => [
                'pagination' => 'Pagination',
                'display' => 'Display',
                'grouping' => 'Result grouping',
            ],
            'handler' => [
                'title' => 'Search handlers',
                'description' => 'Select search handlers that have been registered through a plugin\'s "registerSearchHandlers" method. You may select more than one.',
                'placeholder' => 'Select one or more',
            ],
            'showExcerpts' => [
                'title' => 'Show excerpts?',
                'description' => 'If checked, excerpts from the result content will be displayed in search results.',
            ],
            'limit' => [
                'title' => 'Results limit',
                'description' => 'Define the total amount of results you wish to retrieve. Set to 0 to have no limit.',
                'validationMessage' => 'Results limit must be a number',
            ],
            'perPage' => [
                'title' => 'Results per page',
                'description' => 'Define the amount of results you wish to retrieve per page. Set to 0 to have no pagination.',
                'validationMessage' => 'Results per page must be a number',
            ],
            'grouping' => [
                'title' => 'Enable grouping?',
                'description' => 'If enabled, results will be grouped by logical groupings, such as categories or pages.',
            ],
            'perGroup' => [
                'title' => 'Number of results per group',
                'description' => 'Define the amount of results you wish to retrieve per group. Set to 0 to have no limit.',
                'validationMessage' => 'Results per group must be a number',
            ],
        ],
    ],
    'validation' => [
        'modelRequired' => 'The "model" property for the ":name" search handler must be specified (provided by ":plugin")',
        'recordRequired' => 'The "record" property for the ":name" search handler must be specified (provided by ":plugin")',
    ]
];
