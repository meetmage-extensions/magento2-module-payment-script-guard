<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Inventory;

use MeetMage\PaymentScriptGuard\Model\UrlParts;

/**
 * Turns a script URL reported by the browser into a stable inventory key.
 *
 * The same file must map to the same entry across deployments, so for first-party files the
 * static content version (/static/version1712345678/) and cache-busting query strings are removed.
 * Third-party URLs keep their query string (it often selects the account, e.g. ?id=GTM-XXXX).
 */
class UrlNormalizer
{
    private const MAX_LENGTH = 2048;

    /**
     * @var UrlParts
     */
    private UrlParts $urlParts;

    /**
     * @param UrlParts|null $urlParts
     */
    public function __construct(?UrlParts $urlParts = null)
    {
        $this->urlParts = $urlParts ?? new UrlParts();
    }

    /**
     * Normalized URL used as the inventory identity.
     *
     * @param string $url Absolute URL as reported by script.src
     * @param string[] $firstPartyHosts
     * @return string Empty string when the URL is not usable
     */
    public function normalize(string $url, array $firstPartyHosts): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > self::MAX_LENGTH * 4) {
            return '';
        }
        $url = (string) preg_replace('/#.*$/s', '', $url);
        $scheme = strtolower((string) strstr($url, ':', true));

        if ($scheme === 'blob') {
            // blob:https://shop.test/550e8400-... is unique per page load: keep only the origin.
            return 'blob:' . $this->origin(substr($url, 5));
        }
        if ($scheme === 'data') {
            // Inline payloads: identify them by content.
            return 'data:' . hash('sha256', $url);
        }
        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $parts = $this->urlParts->parse($url);
        if ($parts['host'] === '') {
            return '';
        }
        $path = $parts['path'];
        $query = $parts['query'];
        if ($this->isFirstParty($parts['host'], $firstPartyHosts)) {
            $path = (string) preg_replace('#/static/version[^/]+/#', '/static/', $path);
            $query = '';
        }

        $normalized = $scheme . '://' . $parts['host'] . ($parts['port'] ? ':' . $parts['port'] : '')
            . ($path !== '' ? $path : '/') . ($query !== '' ? '?' . $query : '');
        return substr($normalized, 0, self::MAX_LENGTH);
    }

    /**
     * Lower-case host of an URL.
     *
     * @param string $url
     * @return string
     */
    public function host(string $url): string
    {
        return $this->urlParts->host($url);
    }

    /**
     * Whether the host is one of the store's own hosts.
     *
     * @param string $host
     * @param string[] $firstPartyHosts
     * @return bool
     */
    public function isFirstParty(string $host, array $firstPartyHosts): bool
    {
        return $host !== '' && in_array(strtolower($host), $firstPartyHosts, true);
    }

    /**
     * Scheme, host and port of an URL.
     *
     * @param string $url
     * @return string
     */
    private function origin(string $url): string
    {
        $parts = $this->urlParts->parse($url);
        return $parts['scheme'] !== '' && $parts['host'] !== ''
            ? $parts['scheme'] . '://' . $parts['host'] . ($parts['port'] ? ':' . $parts['port'] : '')
            : 'unknown';
    }
}
