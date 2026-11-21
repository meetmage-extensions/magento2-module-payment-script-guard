<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Headers;

/**
 * Normalizes the security headers of a payment page so they can be compared between requests.
 * Per-request values (CSP nonces) are removed; everything else must stay the same.
 */
class HeaderFingerprint
{
    /**
     * Headers that affect what may run on the payment page (PCI DSS 4.0.1 requirement 11.6.1).
     */
    public const WATCHED = [
        'content-security-policy',
        'content-security-policy-report-only',
        'x-frame-options',
        'strict-transport-security',
        'x-content-type-options',
        'referrer-policy',
        'permissions-policy',
        'cross-origin-opener-policy',
    ];

    /**
     * Watched headers, normalized and sorted by name.
     *
     * @param array<string,string> $headers Header name => value (name in any case)
     * @return array<string,string>
     */
    public function normalize(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $name = strtolower(trim((string) $name));
            if (!in_array($name, self::WATCHED, true)) {
                continue;
            }
            $value = (string) preg_replace("/'nonce-[^']*'/i", "'nonce'", (string) $value);
            $value = trim((string) preg_replace('/\s+/', ' ', $value));
            if (str_starts_with($name, 'content-security-policy')) {
                $value = $this->sortDirectives($value);
            }
            $result[$name] = isset($result[$name]) ? $result[$name] . ', ' . $value : $value;
        }
        ksort($result);
        return $result;
    }

    /**
     * Hash of the normalized headers.
     *
     * @param array<string,string> $normalized
     * @return string
     */
    public function hash(array $normalized): string
    {
        return hash('sha256', (string) json_encode($normalized));
    }

    /**
     * Names of headers that were added, removed or changed.
     *
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @return string[]
     */
    public function diff(array $before, array $after): array
    {
        $names = array_unique(array_merge(array_keys($before), array_keys($after)));
        sort($names);
        $changes = [];
        foreach ($names as $name) {
            if (!isset($before[$name])) {
                $changes[] = $name . ' (added)';
            } elseif (!isset($after[$name])) {
                $changes[] = $name . ' (removed)';
            } elseif ($before[$name] !== $after[$name]) {
                $changes[] = $name . ' (changed)';
            }
        }
        return $changes;
    }

    /**
     * CSP directives in a stable order (Magento may emit them in any order).
     *
     * @param string $policy
     * @return string
     */
    private function sortDirectives(string $policy): string
    {
        $directives = array_filter(array_map('trim', explode(';', $policy)), static fn ($d) => $d !== '');
        $directives = array_map(static function (string $directive): string {
            $parts = preg_split('/\s+/', $directive) ?: [];
            $name = strtolower((string) array_shift($parts));
            sort($parts, SORT_STRING);
            return trim($name . ' ' . implode(' ', array_unique($parts)));
        }, $directives);
        sort($directives, SORT_STRING);
        return implode('; ', $directives);
    }
}
