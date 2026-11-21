<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Test\Unit\Model;

use MeetMage\PaymentScriptGuard\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testIsEnabledReadsStoreScopedFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, 3)
            ->willReturn(true);

        $this->assertTrue((new Config($scopeConfig))->isEnabled(3));
    }

    public function testPaymentPagesAreReadLineByLine(): void
    {
        $config = new Config($this->scopeConfig([
            Config::XML_PATH_PAGES => "checkout_index_index\r\n\n  onestepcheckout_index_index  \n",
        ]));

        $this->assertSame(['checkout_index_index', 'onestepcheckout_index_index'], $config->getPaymentPages());
        $this->assertTrue($config->isPaymentPage('onestepcheckout_index_index'));
        $this->assertFalse($config->isPaymentPage('cms_index_index'));
        $this->assertFalse($config->isPaymentPage(''));
    }

    public function testSampleRateIsClamped(): void
    {
        $this->assertSame(100, (new Config($this->scopeConfig([Config::XML_PATH_SAMPLE_RATE => '250'])))->getSampleRate());
        $this->assertSame(100, (new Config($this->scopeConfig([Config::XML_PATH_SAMPLE_RATE => ''])))->getSampleRate());
        $this->assertSame(25, (new Config($this->scopeConfig([Config::XML_PATH_SAMPLE_RATE => '25'])))->getSampleRate());
    }

    public function testInvalidAlertRecipientsAreDropped(): void
    {
        $config = new Config($this->scopeConfig([
            Config::XML_PATH_ALERTS_RECIPIENTS => 'ops@shop.test, not-an-email; dev@shop.test',
        ]));

        $this->assertSame(['ops@shop.test', 'dev@shop.test'], $config->getAlertRecipients());
    }

    /**
     * @param array<string,string> $values
     */
    private function scopeConfig(array $values): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[$path] ?? null
        );
        return $scopeConfig;
    }
}
