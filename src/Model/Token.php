<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Short-lived token embedded in payment pages, so the reporting endpoint only accepts reports
 * from pages rendered by this store (payment pages are not full-page cached).
 */
class Token
{
    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param EncryptorInterface $encryptor
     */
    public function __construct(EncryptorInterface $encryptor)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * Token for a page, valid today and tomorrow (UTC).
     *
     * @param string $page Full action name
     * @param int|null $time
     * @return string
     */
    public function create(string $page, ?int $time = null): string
    {
        return $this->sign($page, gmdate('Y-m-d', $time ?? time()));
    }

    /**
     * Whether the token was issued for this page today or yesterday.
     *
     * @param string $token
     * @param string $page
     * @param int|null $time
     * @return bool
     */
    public function isValid(string $token, string $page, ?int $time = null): bool
    {
        $time = $time ?? time();
        foreach ([$time, $time - 86400] as $moment) {
            if (hash_equals($this->sign($page, gmdate('Y-m-d', $moment)), $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * HMAC-SHA256 with the store's encryption key.
     *
     * @param string $page
     * @param string $day
     * @return string
     */
    private function sign(string $page, string $day): string
    {
        // Magento's encryptor: HMAC-SHA256 keyed with the deployment's crypt key
        return substr((string) $this->encryptor->hash('meetmage_psg|' . $page . '|' . $day), 0, 64);
    }
}
