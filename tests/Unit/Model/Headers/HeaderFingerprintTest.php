<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Test\Unit\Model\Headers;

use MeetMage\PaymentScriptGuard\Model\Headers\HeaderFingerprint;
use PHPUnit\Framework\TestCase;

class HeaderFingerprintTest extends TestCase
{
    private HeaderFingerprint $fingerprint;

    protected function setUp(): void
    {
        $this->fingerprint = new HeaderFingerprint();
    }

    public function testNoncesAndDirectiveOrderDoNotMatter(): void
    {
        $a = $this->fingerprint->normalize([
            'Content-Security-Policy' => "script-src 'self' 'nonce-abc123' https://js.stripe.com; frame-src 'self'",
            'X-Powered-By' => 'PHP',
        ]);
        $b = $this->fingerprint->normalize([
            'content-security-policy' => "frame-src 'self';  script-src https://js.stripe.com 'nonce-zzz999' 'self'",
        ]);

        $this->assertSame($this->fingerprint->hash($a), $this->fingerprint->hash($b));
        $this->assertArrayNotHasKey('x-powered-by', $a);
    }

    public function testAddedSourceIsDetected(): void
    {
        $before = $this->fingerprint->normalize(['Content-Security-Policy' => "script-src 'self'"]);
        $after = $this->fingerprint->normalize([
            'Content-Security-Policy' => "script-src 'self' https://evil.example",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);

        $this->assertNotSame($this->fingerprint->hash($before), $this->fingerprint->hash($after));
        $this->assertSame(
            ['content-security-policy (changed)', 'x-frame-options (added)'],
            $this->fingerprint->diff($before, $after)
        );
    }

    public function testRemovedHeaderIsReported(): void
    {
        $this->assertSame(
            ['strict-transport-security (removed)'],
            $this->fingerprint->diff(['strict-transport-security' => 'max-age=31536000'], [])
        );
    }
}
