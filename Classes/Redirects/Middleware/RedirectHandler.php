<?php
declare(strict_types=1);

namespace Wazum\Umleitung\Redirects\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;
use TYPO3\CMS\Redirects\Service\RedirectService;

/**
 * Class RedirectHandler
 *
 * @package Wazum\Umleitung\Redirects\Middleware
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class RedirectHandler extends \TYPO3\CMS\Redirects\Http\Middleware\RedirectHandler
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $redirectService = GeneralUtility::makeInstance(RedirectService::class);
        $port = $request->getUri()->getPort();
        $domainName = $request->getUri()->getHost() . ($port ? ':' . $port : '');
        $path = $request->getUri()->getPath();
        $matchedRedirect = $redirectService->matchRedirect(
            $domainName,
            $path,
            $request->getUri()->getQuery() ?? ''
        );

        if (is_array($matchedRedirect)) {
            $url = $redirectService->getTargetUrl(
                $matchedRedirect,
                $request->getQueryParams(),
                $request->getAttribute('site', null)
            );
            if ($url instanceof UriInterface) {
                if ((bool)$matchedRedirect['is_regexp'] &&
                    !empty($matchedRedirect['target_append_regexp_match']) &&
                    preg_match($matchedRedirect['source_path'], $path, $matches)
                ) {
                    $targetPath = $url->getPath();
                    foreach ($matches as $key => $value) {
                        if ($key > 0) {
                            if (strpos($matchedRedirect['target_append_regexp_match'], '$' . $key) !== false) {
                                $targetPath = str_replace(
                                    '$' . $key,
                                    $value,
                                    rtrim($targetPath, '/') . '/' . ltrim($matchedRedirect['target_append_regexp_match'], '/')
                                );
                            }
                        }
                    }
                    $url = $url->withPath($targetPath);
                }

                $this->logger->debug('Redirecting', ['record' => $matchedRedirect, 'uri' => $url]);
                $response = $this->buildRedirectResponse($url, $matchedRedirect);
                $this->incrementHitCount($matchedRedirect);

                return $response;
            }
        }

        return $handler->handle($request);
    }

    /**
     * @return array
     */
    protected function fetchRedirects(): array
    {
        return GeneralUtility::makeInstance(RedirectCacheService::class)->getRedirects();
    }

    /**
     * @param array $redirectRecord
     * @return bool
     */
    protected function isRedirectActive(array $redirectRecord): bool
    {
        return !$redirectRecord['disabled'] && $redirectRecord['starttime'] <= $GLOBALS['SIM_ACCESS_TIME'] &&
            (!$redirectRecord['endtime'] || $redirectRecord['endtime'] >= $GLOBALS['SIM_ACCESS_TIME']);
    }
}