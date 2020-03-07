<?php

return [
    'frontend' => [
        'typo3/cms-frontend/base-redirect-resolver' => [
            'disabled' => true
        ],
        'wazum/umleitung/base-redirect-resolver' => [
            'target' => \Wazum\Umleitung\Frontend\Middleware\SiteBaseRedirectResolver::class,
            'after' => [
                'typo3/cms-frontend/site-resolver',
            ],
            'before' => [
                'typo3/cms-frontend/static-route-resolver'
            ]
        ],
        'typo3/cms-redirects/redirecthandler' => [
            'disabled' => true
        ],
        'wazum/umleitung/redirecthandler' => [
            'target' => \Wazum\Umleitung\Redirects\Middleware\RedirectHandler::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/tsfe',
                'typo3/cms-frontend/authentication',
                'typo3/cms-frontend/static-route-resolver',
            ]
        ]
    ]
];