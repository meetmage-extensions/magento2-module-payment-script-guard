<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Test\Unit\Model\Inventory;

use MeetMage\PaymentScriptGuard\Model\Inventory\UrlNormalizer;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    private const HOSTS = ['shop.test', 'cdn.shop.test'];

    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UrlNormalizer();
    }

    public function testFirstPartyStaticVersionAndQueryAreRemoved(): void
    {
        $this->assertSame(
            'https://shop.test/static/frontend/Magento/luma/en_US/requirejs/require.js',
            $this->normalizer->normalize(
                'https://Shop.test/static/version1712345678/frontend/Magento/luma/en_US/requirejs/require.js?v=3#x',
                self::HOSTS
            )
        );
    }

    public function testThirdPartyQueryIsKept(): void
    {
        $this->assertSame(
            'https://www.googletagmanager.com/gtm.js?id=GTM-ABC123',
            $this->normalizer->normalize('https://www.googletagmanager.com/gtm.js?id=GTM-ABC123#top', self::HOSTS)
        );
    }

    public function testBlobUrlsKeepOnlyTheOrigin(): void
    {
        $this->assertSame(
            'blob:https://shop.test',
            $this->normalizer->normalize('blob:https://shop.test/550e8400-e29b-41d4-a716-446655440000', self::HOSTS)
        );
    }

    public function testDataUrlsAreIdentifiedByContent(): void
    {
        $a = $this->normalizer->normalize('data:text/javascript,alert(1)', self::HOSTS);
        $b = $this->normalizer->normalize('data:text/javascript,alert(2)', self::HOSTS);

        $this->assertStringStartsWith('data:', $a);
        $this->assertNotSame($a, $b);
    }

    public function testUnsupportedSchemesAreIgnored(): void
    {
        $this->assertSame('', $this->normalizer->normalize('javascript:alert(1)', self::HOSTS));
        $this->assertSame('', $this->normalizer->normalize('', self::HOSTS));
    }

    public function testFirstPartyDetection(): void
    {
        $this->assertTrue($this->normalizer->isFirstParty('cdn.shop.test', self::HOSTS));
        $this->assertFalse($this->normalizer->isFirstParty('shop.test.evil.com', self::HOSTS));
    }
}
