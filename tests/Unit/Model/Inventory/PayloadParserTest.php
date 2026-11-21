<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Test\Unit\Model\Inventory;

use MeetMage\PaymentScriptGuard\Model\Inventory\PayloadParser;
use PHPUnit\Framework\TestCase;

class PayloadParserTest extends TestCase
{
    private PayloadParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PayloadParser();
    }

    public function testValidReportIsParsed(): void
    {
        $source = 'console.log("hi")';
        $report = $this->parser->parse((string) json_encode([
            't' => 'token',
            'p' => 'checkout_index_index',
            'u' => 'https://shop.test/checkout/?step=payment#billing',
            'e' => ['https://js.stripe.com/v3/', 'https://js.stripe.com/v3/', 42],
            'i' => [str_repeat('a', 64), 'not-a-hash'],
            'c' => [['h' => hash('sha256', $source), 'c' => $source]],
        ]));

        $this->assertNotNull($report);
        $this->assertSame('https://shop.test/checkout/', $report['url']);
        $this->assertSame(['https://js.stripe.com/v3/'], $report['external']);
        $this->assertSame([str_repeat('a', 64)], $report['inline']);
        $this->assertSame([['hash' => hash('sha256', $source), 'source' => $source]], $report['contents']);
    }

    public function testSourceWithWrongHashIsDropped(): void
    {
        $report = $this->parser->parse((string) json_encode([
            't' => 'token',
            'p' => 'checkout_index_index',
            'c' => [['h' => str_repeat('b', 64), 'c' => 'evil()']],
        ]));

        $this->assertNotNull($report);
        $this->assertSame([], $report['contents']);
    }

    public function testSourceWithoutHashIsHashedByTheServer(): void
    {
        $report = $this->parser->parse((string) json_encode([
            't' => 'token',
            'p' => 'checkout_index_index',
            'c' => [['h' => null, 'c' => 'go()']],
        ]));

        $this->assertSame(hash('sha256', 'go()'), $report['contents'][0]['hash'] ?? null);
    }

    public function testListsAreCapped(): void
    {
        $urls = array_map(static fn (int $i): string => 'https://x.test/' . $i . '.js', range(1, 400));
        $report = $this->parser->parse((string) json_encode(['t' => 't', 'p' => 'p', 'e' => $urls]));

        $this->assertCount(PayloadParser::MAX_EXTERNAL, $report['external'] ?? []);
    }

    public function testInvalidBodiesAreRejected(): void
    {
        $this->assertNull($this->parser->parse(''));
        $this->assertNull($this->parser->parse('not json'));
        $this->assertNull($this->parser->parse('{"p":"checkout_index_index"}'));
    }
}
