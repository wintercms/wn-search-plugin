<?php

return [
    'plugin' => [
        'name' => 'Search',
        'description' => 'Implements full-text searching capabilities into Winter.'
    ],
    'otherPlugins' => [
        'cmsPages' => 'CMS Pages',
        'staticPages' => 'Static Pages',
    ],
    'components' => [
        'search' => [
            'name' => 'Search',
            'description' => 'Adds a search feature into a template',
            'groups' => [
                'pagination' => 'Pagination',
                'display' => 'Display',
            ],
            'handler' => [
                'title' => 'Search handlers',
                'description' => 'Select search handlers that have been registered through a plugin\'s "registerSearchHandlers" method. You may select more than one.',
                'placeholder' => 'Select one or more',
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
            'combineResults' => [
                'title' => 'Combine results',
                'description' => 'If multiple search handlers are included, ticking this will combine the results into one result array. Otherwise, the results array will be grouped by the search handler name.',
            ],
            'displayImages' => [
                'title' => 'Show images',
            ],
            'displayHandlerName' => [
                'title' => 'Show search handler name',
                'description' => 'Useful if you have combined results and wish to show the handler that provided the result',
            ],
            'displayPluginName' => [
                'title' => 'Show plugin name',
                'description' => 'Useful if you have combined results and wish to show the plugin that provided the result',
            ],
        ],
    ],
    'validation' => [
        'modelRequired' => 'The "model" property for the ":name" search handler must be specified (provided by ":plugin")',
        'recordRequired' => 'The "record" property for the ":name" search handler must be specified (provided by ":plugin")',
    ]
];
