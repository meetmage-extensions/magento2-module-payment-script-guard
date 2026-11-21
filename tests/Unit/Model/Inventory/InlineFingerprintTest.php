<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Test\Unit\Model\Inventory;

use MeetMage\PaymentScriptGuard\Model\Inventory\InlineFingerprint;
use PHPUnit\Framework\TestCase;

class InlineFingerprintTest extends TestCase
{
    private InlineFingerprint $fingerprint;

    protected function setUp(): void
    {
        $this->fingerprint = new InlineFingerprint();
    }

    public function testPerVisitorDataDoesNotChangeTheFingerprint(): void
    {
        $a = 'window.checkoutConfig = {"quoteId":"a8F3kLm2","formKey":"Xk29dLq","total":129.95,'
            . '"email":"jane@example.com"};';
        $b = 'window.checkoutConfig = {"quoteId":"Zz91Qwe0","formKey":"p0OoLq1","total":7,'
            . '"email":"raj@example.org"};';

        $this->assertSame($this->fingerprint->fingerprint($a), $this->fingerprint->fingerprint($b));
    }

    public function testInjectedCodeChangesTheFingerprint(): void
    {
        $clean = 'window.checkoutConfig = {"quoteId":"a8F3"};';
        $infected = 'window.checkoutConfig = {"quoteId":"a8F3"}; document.forms[0].addEventListener("submit", s);';

        $this->assertNotSame($this->fingerprint->fingerprint($clean), $this->fingerprint->fingerprint($infected));
    }

    public function testNewDomainInsideAStringChangesTheFingerprint(): void
    {
        $good = 'fetch("https://api.stripe.com/v1/tokens", {method: "POST"});';
        $bad = 'fetch("https://cdn-stripe-analytics.com/v1/tokens", {method: "POST"});';

        $this->assertNotSame($this->fingerprint->fingerprint($good), $this->fingerprint->fingerprint($bad));
    }

    public function testWhitespaceAndCommentsAreIgnored(): void
    {
        $a = "var a = 1; // set a\n/* block */ go(a);";
        $b = "var a=1;\n\n   go(a);";

        $this->assertSame($this->fingerprint->normalize($a), $this->fingerprint->normalize($b));
    }

    public function testDigitsInsideIdentifiersAreKept(): void
    {
        $this->assertSame('var x1=0;', $this->fingerprint->normalize('var x1=42;'));
        $this->assertSame('return a 0;', $this->fingerprint->normalize('return a 15;'));
    }

    public function testEscapedQuotesStayInsideTheString(): void
    {
        $this->assertSame('x=S;y=S;', $this->fingerprint->normalize('x="a \\" b";y=\'c\';'));
    }

    public function testHostsAreFoundInJsonEscapedUrls(): void
    {
        $source = '{"url":"https:\/\/Checkout.Example.com\/pay","cdn":"//static.example.net/x.js",'
            . '"api":"wss://live.example.io"}';

        $this->assertSame(
            ['checkout.example.com', 'live.example.io', 'static.example.net'],
            $this->fingerprint->hosts($source)
        );
    }

    public function testMagentoRandomVariableNamesAreIgnored(): void
    {
        $template = 'var elem%1$sArray = document.querySelectorAll("#checkout-loader img");'
            . 'function eventListener%2$s () {} var listenedElement%2$sArray = [];'
            . 'function initForm_%3$s() { return {}; }';
        $a = sprintf($template, 'IYmrn70S', 'aB3dE5fG7h', '6560f1a2b3c4d');
        $b = sprintf($template, 'Qw8RtY2u', 'Zx9Cv1Bn2M', '6560f9e8d7c6b');

        $this->assertSame($this->fingerprint->fingerprint($a), $this->fingerprint->fingerprint($b));
    }

    public function testHostsAreFoundInUnicodeEscapedUrls(): void
    {
        $source = "require.config({paths: {googleMaps: 'https\\u003A\\u002F\\u002Fmaps.googleapis.com\\u002Fmaps'}});";

        $this->assertSame(['maps.googleapis.com'], $this->fingerprint->hosts($source));
    }

    public function testPreviewIsSingleLineAndShortened(): void
    {
        $preview = $this->fingerprint->preview("line one\n\n   line two " . str_repeat('x', 300), 40);

        $this->assertStringStartsWith('line one line two', $preview);
        $this->assertLessThanOrEqual(40, mb_strlen($preview));
        $this->assertStringEndsWith('…', $preview);
    }
}
