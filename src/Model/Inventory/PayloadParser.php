<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Inventory;

/**
 * Validates and limits the JSON sent by the payment page reporter.
 *
 * The endpoint is public (anyone who opens the checkout gets a token), so every field is capped:
 * a malicious client can add rows only within these limits.
 *
 * Shape: {"t": token, "p": full action name, "u": page URL,
 *         "e": [external script URL, ...], "i": [sha256 of inline source, ...],
 *         "c": [{"h": sha256 or null, "c": inline source}, ...]}
 */
class PayloadParser
{
    public const MAX_BODY_BYTES = 3145728;
    public const MAX_EXTERNAL = 300;
    public const MAX_INLINE_HASHES = 300;
    public const MAX_CONTENTS = 100;
    public const MAX_SOURCE_BYTES = 262144;

    /**
     * Parsed payload, or null when it is not valid.
     *
     * @param string $body
     * @return array{token:string,page:string,url:string,external:string[],inline:string[],
     *     contents:array<int,array{hash:string,source:string}>}|null
     */
    public function parse(string $body): ?array
    {
        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !is_string($data['t'] ?? null) || !is_string($data['p'] ?? null)) {
            return null;
        }

        $external = [];
        foreach (array_slice($this->list($data['e'] ?? []), 0, self::MAX_EXTERNAL) as $url) {
            if (is_string($url) && $url !== '' && strlen($url) <= 8192) {
                $external[] = $url;
            }
        }

        $inline = [];
        foreach (array_slice($this->list($data['i'] ?? []), 0, self::MAX_INLINE_HASHES) as $hash) {
            if (is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash)) {
                $inline[] = $hash;
            }
        }

        $contents = [];
        foreach (array_slice($this->list($data['c'] ?? []), 0, self::MAX_CONTENTS) as $item) {
            $source = is_array($item) ? ($item['c'] ?? null) : null;
            if (!is_string($source) || trim($source) === '' || strlen($source) > self::MAX_SOURCE_BYTES) {
                continue;
            }
            $hash = hash('sha256', $source);
            $claimed = $item['h'] ?? null;
            // The browser hashes the exact source; a mismatch means a tampered or broken report.
            if ($claimed !== null && $claimed !== $hash) {
                continue;
            }
            $contents[] = ['hash' => $hash, 'source' => $source];
        }

        return [
            'token' => substr($data['t'], 0, 128),
            'page' => substr($data['p'], 0, 128),
            'url' => $this->pageUrl(is_string($data['u'] ?? null) ? $data['u'] : ''),
            'external' => array_values(array_unique($external)),
            'inline' => array_values(array_unique($inline)),
            'contents' => $contents,
        ];
    }

    /**
     * The value as a list, or an empty list.
     *
     * @param mixed $value
     * @return array<int,mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Page URL without query string or fragment (they can carry personal data).
     *
     * @param string $url
     * @return string
     */
    private function pageUrl(string $url): string
    {
        $url = (string) preg_replace('/[?#].*$/s', '', $url);
        return preg_match('#^https?://#i', $url) ? substr($url, 0, 1024) : '';
    }
}
