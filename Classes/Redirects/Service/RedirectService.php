<?php
declare(strict_types=1);

namespace Wazum\Umleitung\Redirects\Service;

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Service\TypoLinkCodecService;
use TYPO3\CMS\Frontend\Typolink\AbstractTypolinkBuilder;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

/**
 * Class RedirectService
 *
 * @package Wazum\Umleitung\Redirects\Service
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class RedirectService extends \TYPO3\CMS\Redirects\Service\RedirectService
{
    /**
     * @param array $matchedRedirect
     * @param array $queryParams
     * @param UriInterface $uri
     * @param SiteInterface|null $site
     * @return UriInterface|null
     */
    public function getTargetUrl(array $matchedRedirect, array $queryParams, UriInterface $uri, ?SiteInterface $site = null): ?UriInterface
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
}
