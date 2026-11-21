<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Integrity;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use MeetMage\PaymentScriptGuard\Model\UrlParts;

/**
 * Reads the current content of a script.
 *
 * Files under the store's static or media URL are read straight from disk (pub/static, pub/media):
 * this is where Magecart attacks usually modify files, and it needs no HTTP request. Everything else
 * is downloaded. Hosts that resolve to private or reserved addresses are refused unless they belong
 * to the store, so reported URLs cannot be used to reach internal services.
 */
class Fetcher
{
    private const TIMEOUT = 10;
    private const MAX_BYTES = 10485760;

    /**
     * @var CurlFactory
     */
    private CurlFactory $curlFactory;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var UrlParts
     */
    private UrlParts $urlParts;

    /**
     * @var array<string,string>|null
     */
    private ?array $localPrefixes = null;

    /**
     * @param CurlFactory $curlFactory
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param UrlParts $urlParts
     */
    public function __construct(
        CurlFactory $curlFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        UrlParts $urlParts
    ) {
        $this->curlFactory = $curlFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->urlParts = $urlParts;
    }

    /**
     * Script content, or null when it cannot be read.
     *
     * @param string $url
     * @param bool $firstParty
     * @return string|null
     */
    public function fetch(string $url, bool $firstParty): ?string
    {
        if ($firstParty) {
            $local = $this->readLocal($url);
            if ($local !== null) {
                return $local;
            }
        } elseif (!$this->isPublicHost($this->urlParts->host($url))) {
            return null;
        }
        return $this->download($url);
    }

    /**
     * Content of a static or media file on disk, or null when the URL is not a local file.
     *
     * @param string $url
     * @return string|null
     */
    public function readLocal(string $url): ?string
    {
        $parts = $this->urlParts->parse($url);
        // The static content version changes with every deployment, in the script URL and in the
        // store's static base URL alike: compare without it.
        $path = $this->withoutVersion($parts['path']);
        $host = $parts['host'];
        foreach ($this->getLocalPrefixes() as $prefix => $directoryCode) {
            [$prefixHost, $prefixPath] = explode('|', $prefix, 2);
            if ($host !== $prefixHost || !str_starts_with($path, $prefixPath)) {
                continue;
            }
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- decode the URL path to a file name
            $relative = rawurldecode(substr($path, strlen($prefixPath)));
            if ($relative === '' || str_contains($relative, '..')) {
                return null;
            }
            $directory = $this->filesystem->getDirectoryRead($directoryCode);
            if ($directory->isFile($relative)) {
                return $directory->readFile($relative);
            }
        }
        return null;
    }

    /**
     * Download a script over HTTP(S).
     *
     * @param string $url
     * @return string|null
     */
    private function download(string $url): ?string
    {
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(self::TIMEOUT);
            $curl->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'MeetMage-PaymentScriptGuard/1.0 (integrity check)',
            ]);
            $curl->get($url);
            $body = $curl->getBody();
            if ($curl->getStatus() !== 200 || strlen($body) > self::MAX_BYTES) {
                return null;
            }
            return $body;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Whether every address of the host is public (not private, loopback or reserved).
     *
     * @param string $host
     * @return bool
     */
    private function isPublicHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    /**
     * URL path without a static content version segment (/static/version1712345678/ => /static/).
     *
     * @param string $path
     * @return string
     */
    private function withoutVersion(string $path): string
    {
        return (string) preg_replace('#/version[0-9]+/#', '/', $path, 1);
    }

    /**
     * "host|/path/" prefixes of the static and media URLs, mapped to their directory.
     *
     * @return array<string,string>
     */
    private function getLocalPrefixes(): array
    {
        if ($this->localPrefixes !== null) {
            return $this->localPrefixes;
        }
        $prefixes = [];
        $types = [
            UrlInterface::URL_TYPE_STATIC => DirectoryList::STATIC_VIEW,
            UrlInterface::URL_TYPE_MEDIA => DirectoryList::MEDIA,
        ];
        foreach ($this->storeManager->getStores() as $store) {
            foreach ($types as $type => $directoryCode) {
                foreach ([false, true] as $secure) {
                    try {
                        $base = (string) $store->getBaseUrl($type, $secure);
                    } catch (\Exception $e) {
                        continue;
                    }
                    $parts = $this->urlParts->parse($base);
                    $host = $parts['host'];
                    $path = rtrim($this->withoutVersion($parts['path'] . '/'), '/') . '/';
                    if ($host !== '') {
                        $prefixes[$host . '|' . $path] = $directoryCode;
                    }
                }
            }
        }
        $this->localPrefixes = $prefixes;
        return $prefixes;
    }
}
