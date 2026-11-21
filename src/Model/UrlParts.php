<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

/**
 * Splits an URL into its parts (one place for parse_url, which has no framework equivalent).
 */
class UrlParts
{
    /**
     * URL parts with every key present (empty string when missing, port as int or null).
     *
     * @param string $url
     * @return array{scheme:string,host:string,port:int|null,path:string,query:string}
     */
    public function parse(string $url): array
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- plain URL parsing, nothing is fetched
        $parts = parse_url($url);
        $parts = is_array($parts) ? $parts : [];
        return [
            'scheme' => strtolower((string) ($parts['scheme'] ?? '')),
            'host' => strtolower((string) ($parts['host'] ?? '')),
            'port' => isset($parts['port']) ? (int) $parts['port'] : null,
            'path' => (string) ($parts['path'] ?? ''),
            'query' => (string) ($parts['query'] ?? ''),
        ];
    }

    /**
     * Lower-case host of an URL.
     *
     * @param string $url
     * @return string
     */
    public function host(string $url): string
    {
        return $this->parse($url)['host'];
    }
}
