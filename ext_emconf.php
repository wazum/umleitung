<?php

$EM_CONF['umleitung'] = [
    'title' => 'umleitung',
    'description' => 'Redirects site base agnostic and with variant source and fuzzy target',
    'category' => 'backend',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author_company' => 'wazum.com',
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'redirects' => '9.5.0-10.4.99'
        ],
//        'conflicts' => [
//            'justincase'
//        ]
    ]
];
