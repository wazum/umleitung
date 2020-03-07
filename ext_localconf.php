<?php

defined('TYPO3_MODE') or die ('Access denied.');

(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Redirects\Service\RedirectService::class] = [
        'className' => \Wazum\Umleitung\Redirects\Service\RedirectService::class
    ];
})();
