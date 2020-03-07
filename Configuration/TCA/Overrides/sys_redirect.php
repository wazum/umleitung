<?php

(static function () {
    $fields = [
        'target_append_regexp_match' => [
            'exclude' => true,
            'label' => 'LLL:EXT:umleitung/Resources/Private/Language/locallang.xlf:sys_redirect.target_append_regexp_match',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => ''
            ]
        ]
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_redirect', $fields);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('sys_redirect','target_append_regexp_match', '', 'after:target');
})();
