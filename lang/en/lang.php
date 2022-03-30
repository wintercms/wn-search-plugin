<?php

return [
    'plugin' => [
        'name' => 'Search',
        'description' => 'Implements full-text searching capabilities into Winter.'
    ],
    'components' => [
        'search' => [
            'name' => 'Search',
            'description' => 'Adds a search feature into a template',
            'handler' => [
                'title' => 'Search handlers',
                'description' => 'Select search handlers that have been registered through a plugin\'s "registerSearchHandlers" method. You may select more than one.',
                'placeholder' => 'Select one or more',
            ],
        ],
    ],
];
