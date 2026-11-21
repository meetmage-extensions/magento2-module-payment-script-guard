<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Test\Unit\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use MeetMage\PaymentScriptGuard\Model\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    private Token $token;

    protected function setUp(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('hash')->willReturnCallback(
            static fn (string $data): string => hash_hmac('sha256', $data, 'secret-key')
        );
        $this->token = new Token($encryptor);
    }

    public function testTokenIsValidTodayAndTomorrow(): void
    {
        $now = gmmktime(10, 0, 0, 11, 22, 2026);
        $token = $this->token->create('checkout_index_index', $now);

        $this->assertTrue($this->token->isValid($token, 'checkout_index_index', $now));
        $this->assertTrue($this->token->isValid($token, 'checkout_index_index', $now + 86400));
        $this->assertFalse($this->token->isValid($token, 'checkout_index_index', $now + 2 * 86400));
    }

    public function testTokenIsBoundToThePage(): void
    {
        $token = $this->token->create('checkout_index_index');

        $this->assertFalse($this->token->isValid($token, 'cms_index_index'));
        $this->assertFalse($this->token->isValid('forged', 'checkout_index_index'));
    }
}
