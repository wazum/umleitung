<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'umleitung',
    'description' => 'Redirects site base agnostic and with fuzzy target',
    'category' => 'backend',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'author_company' => 'wazum.com',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'redirects' => '9.5.0-9.5.99'
        ]
    ]
];
