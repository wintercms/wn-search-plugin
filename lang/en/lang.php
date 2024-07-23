<?php

return [
    'plugin' => [
        'name' => 'Search',
        'description' => 'Implements full-text searching capabilities into Winter.'
    ],
    'otherPlugins' => [
        'cmsPages' => 'CMS Pages',
        'staticPages' => 'Static Pages',
        'winterBlog' => 'Winter Blog Posts',
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
            'fuzzySearch' => [
                'title' => 'Fuzzy search?',
                'description' => 'Allows the search query to match records more loosely. Some index providers may already provide fuzzy searching, so only enable this if necessary.',
            ],
            'orderByRelevance' => [
                'title' => 'Order by relevance?',
                'description' => 'Runs a custom relevance algorithm on results and orders based on relevancy. This is recommended only for database or collection search indexes, as other providers have their own relevance algorithms.',
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
                'title' => 'Results per group',
                'description' => 'Define the upper limit of results you wish to retrieve per group. Set to 0 to have no limit.',
                'validationMessage' => 'Results per group must be a number',
            ],
        ],
    ],
    'validation' => [
        'modelRequired' => 'The "model" property for the ":name" search handler must be specified (provided by ":plugin")',
        'recordRequired' => 'The "record" property for the ":name" search handler must be specified (provided by ":plugin")',
    ]
];
