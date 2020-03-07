<?php
declare(strict_types=1);

namespace Wazum\Umleitung\Frontend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectService;

/**
 * Class SiteBaseRedirectResolver
 *
 * @package Wazum\Umleitung\Frontend\Middleware
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class SiteBaseRedirectResolver extends \TYPO3\CMS\Frontend\Middleware\SiteBaseRedirectResolver
{
    /**
     * Check for matching redirect records
     * before resolving and redirecting to site language base
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $redirectService = GeneralUtility::makeInstance(RedirectService::class);
        $port = $request->getUri()->getPort();
        $matchedRedirect = $redirectService->matchRedirect(
            $request->getUri()->getHost() . ($port ? ':' . $port : ''),
            $request->getUri()->getPath(),
            $request->getUri()->getQuery() ?? ''
        );
        // No matching redirect found, so let the original middleware take over
        if ($matchedRedirect === null) {
            return parent::process($request, $handler);
        }

        return $handler->handle($request);
    }
}
