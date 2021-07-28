<?php
declare(strict_types=1);

namespace Wazum\Umleitung\Redirects\Service;

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Service\TypoLinkCodecService;
use TYPO3\CMS\Frontend\Typolink\AbstractTypolinkBuilder;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;
use function array_keys;
use function in_array;
use function ltrim;
use function parse_url;
use function preg_match;
use function rawurldecode;
use function rtrim;

/**
 * Class RedirectService
 *
 * @package Wazum\Umleitung\Redirects\Service
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
final class RedirectService extends \TYPO3\CMS\Redirects\Service\RedirectService
{
    public function matchRedirect(string $domain, string $path, string $query = ''): ?array
    {
        $domains = $this->getAllDomains($domain);
        $allRedirects = $this->fetchRedirects();
        $path = rawurldecode($path);
        // Check if the domain matches, or if there is a
        // redirect fitting for any domain
        foreach (array_merge($domains, '*') as $domainName) {
            if (empty($allRedirects[$domainName])) {
                continue;
            }

            $possibleRedirects = [];
            // check if a flat redirect matches
            if (!empty($allRedirects[$domainName]['flat'][rtrim($path, '/') . '/'])) {
                $possibleRedirects = $allRedirects[$domainName]['flat'][rtrim($path, '/') . '/'];
            }
            // check if a flat redirect matches with the Query applied
            if (!empty($query)) {
                $pathWithQuery = rtrim($path, '/') . '?' . ltrim($query, '?');
                if (!empty($allRedirects[$domainName]['respect_query_parameters'][$pathWithQuery])) {
                    $possibleRedirects = $allRedirects[$domainName]['respect_query_parameters'][$pathWithQuery];
                } else {
                    $pathWithQueryAndSlash = rtrim($path, '/') . '/?' . ltrim($query, '?');
                    if (!empty($allRedirects[$domainName]['respect_query_parameters'][$pathWithQueryAndSlash])) {
                        $possibleRedirects = $allRedirects[$domainName]['respect_query_parameters'][$pathWithQueryAndSlash];
                    }
                }
            }
            // check all redirects that are registered as regex
            if (!empty($allRedirects[$domainName]['regexp'])) {
                $allRegexps = array_keys($allRedirects[$domainName]['regexp']);
                foreach ($allRegexps as $regexp) {
                    $matchResult = @preg_match($regexp, $path);
                    if ($matchResult) {
                        $possibleRedirects += $allRedirects[$domainName]['regexp'][$regexp];
                    } elseif ($matchResult === false) {
                        $this->logger->warning('Invalid regex in redirect', ['regex' => $regexp]);
                    }
                }
            }

            foreach ($possibleRedirects as $possibleRedirect) {
                // check starttime and endtime for all existing records
                if ($this->isRedirectActive($possibleRedirect)) {
                    return $possibleRedirect;
                }
            }
        }

        return null;
    }

    /**
     * @param array $matchedRedirect
     * @param array $queryParams
     * @param SiteInterface|null $site
     * @return UriInterface|null
     */
    public function getTargetUrl(array $matchedRedirect, array $queryParams, ?SiteInterface $site = null): ?UriInterface
    {
        $this->logger->debug('Found a redirect to process', $matchedRedirect);
        $linkParameterParts = GeneralUtility::makeInstance(TypoLinkCodecService::class)->decode((string)$matchedRedirect['target']);
        $redirectTarget = $linkParameterParts['url'];
        $linkDetails = $this->resolveLinkDetailsFromLinkTarget($redirectTarget);
        $this->logger->debug('Resolved link details for redirect', $linkDetails);
        // Remove the condition for keep_query_parameters
        // as this makes no sense as the parameters come from the redirect not from outside
        if (!empty($linkParameterParts['additionalParams'])) {
            $params = GeneralUtility::explodeUrl2Array($linkParameterParts['additionalParams']);
            foreach ($params as $key => $value) {
                $queryParams[$key] = $value;
            }
        }
        // Do this for files, folders, external URLs
        if (!empty($linkDetails['url'])) {
            $url = new Uri($linkDetails['url']);
            if ($matchedRedirect['force_https']) {
                $url = $url->withScheme('https');
            }
            if ($matchedRedirect['keep_query_parameters']) {
                $url = $this->addQueryParams($queryParams, $url);
            }

            return $url;
        }

        // If it's a record or page, then boot up TSFE and use typolink
        return $this->getUriFromCustomLinkDetails($matchedRedirect, $site, $linkDetails, $queryParams);
    }

    /**
     * @inheritDoc
     */
    protected function getUriFromCustomLinkDetails(
        array $redirectRecord,
        ?SiteInterface $site,
        array $linkDetails,
        array $queryParams
    ): ?UriInterface {
        if (!isset($linkDetails['type'], $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder'][$linkDetails['type']])) {
            return null;
        }

        $controller = $this->bootFrontendController($site, $queryParams);
        /** @var AbstractTypolinkBuilder $linkBuilder */
        $linkBuilder = GeneralUtility::makeInstance(
            $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder'][$linkDetails['type']],
            $controller->cObj,
            $controller
        );
        try {
            $configuration = [
                'parameter' => (string)$redirectRecord['target'],
                'forceAbsoluteUrl' => true,
            ];
            if ($redirectRecord['force_https']) {
                $configuration['forceAbsoluteUrl.']['scheme'] = 'https';
            }
            // Remove the condition for keep_query_parameters
            // see above
            if (!empty($queryParams)) {
                $configuration['additionalParams'] = HttpUtility::buildQueryString($queryParams, '&');
            }
            [$url] = $linkBuilder->build($linkDetails, '', '', $configuration);

            return new Uri($url);
        } catch (UnableToLinkException $e) {
            // This exception is also thrown by the DatabaseRecordTypolinkBuilder
            $url = $controller->cObj->lastTypoLinkUrl;
            if (!empty($url)) {
                return new Uri($url);
            }

            return null;
        }
    }

    private function getAllDomains(string $domain): array
    {
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        foreach ($siteFinder->getAllSites() as $site) {
            $domains = [];
            $siteConfiguration = $site->getConfiguration();
            $domains[] = parse_url($siteConfiguration['base'], PHP_URL_HOST);
            foreach ($siteConfiguration['baseVariants'] ?? [] as $variant) {
                $domains[] = parse_url($variant['base'], PHP_URL_HOST);
            }

            if (in_array($domain, $domains, true)) {
                return $domains;
            }
        }

        return [$domain];
    }
}
