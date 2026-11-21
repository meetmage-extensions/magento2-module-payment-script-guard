<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\Inventory;

/**
 * Stable fingerprint of an inline script.
 *
 * Inline scripts on checkout pages often embed per-visitor data (window.checkoutConfig holds the cart,
 * addresses and form keys), so an exact hash would create a new inventory entry for every visitor.
 * The fingerprint therefore hashes the code *structure*: string contents and numbers are masked,
 * comments and whitespace are dropped. Any change to the code itself produces a new fingerprint.
 *
 * Masked strings could hide a swapped exfiltration URL, so every host referenced anywhere in the
 * script (also inside strings) is added to the fingerprint: pointing a script at a new domain
 * always produces a new, unauthorized entry.
 */
class InlineFingerprint
{
    /**
     * Fingerprint of an inline script: SHA-256 of its normalized code plus referenced hosts.
     *
     * @param string $source
     * @return string
     */
    public function fingerprint(string $source): string
    {
        return hash('sha256', $this->normalize($source) . "\n#hosts:" . implode(',', $this->hosts($source)));
    }

    /**
     * Code with strings masked, numbers replaced by 0, comments removed and whitespace collapsed.
     *
     * A small single-pass tokenizer, not a full JavaScript parser: regular expression literals are
     * treated as code. That is fine for a fingerprint, which only has to be deterministic.
     *
     * @param string $source
     * @return string
     */
    public function normalize(string $source): string
    {
        $source = $this->maskRandomIdentifiers($source);
        $out = '';
        $length = strlen($source);
        $space = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            $next = $i + 1 < $length ? $source[$i + 1] : '';

            if ($char === '"' || $char === "'" || $char === '`') {
                $i = $this->skipString($source, $i, $char);
                $out .= $this->separator($out, 'S', $space) . 'S';
                $space = false;
                continue;
            }
            if ($char === '/' && $next === '/') {
                $end = strpos($source, "\n", $i);
                $i = $end === false ? $length : $end - 1;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($source, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $space = true;
                continue;
            }
            if (ctype_space($char)) {
                $space = true;
                continue;
            }
            if (ctype_digit($char) && ($space || !$this->isIdentifierChar(substr($out, -1)))) {
                while ($i + 1 < $length && (ctype_digit($source[$i + 1]) || $source[$i + 1] === '.')) {
                    $i++;
                }
                $char = '0';
            }
            $out .= $this->separator($out, $char, $space) . $char;
            $space = false;
        }
        return $out;
    }

    /**
     * Separator to emit before the next character.
     *
     * A space only when whitespace separated two word characters ("var a", "return 0"), so "a = 1"
     * and "a=1" normalize the same.
     *
     * @param string $out
     * @param string $next
     * @param bool $space
     * @return string
     */
    private function separator(string $out, string $next, bool $space): string
    {
        return $space && $this->isIdentifierChar(substr($out, -1)) && $this->isIdentifierChar($next) ? ' ' : '';
    }

    /**
     * Sorted, unique hosts referenced in the script (URLs inside strings included, JSON escapes removed).
     *
     * @param string $source
     * @return string[]
     */
    public function hosts(string $source): array
    {
        // Magento escapes URLs in inline scripts ("https://maps.googleapis.com"),
        // JSON escapes slashes ("https:\/\/"): decode both before looking for hosts.
        $plain = (string) preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $m): string => mb_chr((int) hexdec($m[1]), 'UTF-8'),
            $source
        );
        $plain = str_replace('\\', '', $plain);
        preg_match_all(
            '~(?:https?:|wss?:)?//((?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63})(?![a-z0-9-])~i',
            $plain,
            $matches
        );
        $hosts = array_unique(array_map('strtolower', $matches[1]));
        sort($hosts, SORT_STRING);
        return array_values($hosts);
    }

    /**
     * Short single-line preview of the script for the admin grid.
     *
     * @param string $source
     * @param int $length
     * @return string
     */
    public function preview(string $source, int $length = 160): string
    {
        $flat = trim((string) preg_replace('/\s+/', ' ', $source));
        return mb_strlen($flat) > $length ? rtrim(mb_substr($flat, 0, $length - 1)) . '…' : $flat;
    }

    /**
     * Replace identifiers that are random on every page load.
     *
     * Magento's SecureHtmlRenderer names variables eventListener/listenedElement + 10 random characters
     * and elem + 8 random characters; Hyvä templates often add uniqid() suffixes (13 hex digits).
     *
     * @param string $source
     * @return string
     */
    private function maskRandomIdentifiers(string $source): string
    {
        $source = (string) preg_replace(
            ['/\b(eventListener|listenedElement)[A-Za-z0-9]{10}/', '/\belem[A-Za-z0-9]{8}(?=Array\b)/'],
            ['$1R', 'elemR'],
            $source
        );
        return (string) preg_replace('/(?=[0-9a-f]*[0-9])(?=[0-9a-f]*[a-f])[0-9a-f]{12,}/', 'H', $source);
    }

    /**
     * Index of the closing quote of the string that starts at $start (or the last index).
     *
     * @param string $source
     * @param int $start
     * @param string $quote
     * @return int
     */
    private function skipString(string $source, int $start, string $quote): int
    {
        $length = strlen($source);
        for ($i = $start + 1; $i < $length; $i++) {
            if ($source[$i] === '\\') {
                $i++;
                continue;
            }
            if ($source[$i] === $quote) {
                return $i;
            }
        }
        return $length;
    }

    /**
     * Whether the character can be part of an identifier (so a following digit is not a number).
     *
     * @param string $char
     * @return bool
     */
    private function isIdentifierChar(string $char): bool
    {
        return $char !== '' && (ctype_alnum($char) || $char === '_' || $char === '$');
    }
}
