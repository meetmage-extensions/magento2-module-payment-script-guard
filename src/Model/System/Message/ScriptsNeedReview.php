<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model\System\Message;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;

/**
 * Admin notice while scripts on payment pages need review.
 */
class ScriptsNeedReview implements MessageInterface
{
    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @var int|null
     */
    private ?int $count = null;

    /**
     * @param ScriptResource $scriptResource
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        ScriptResource $scriptResource,
        UrlInterface $urlBuilder,
        AuthorizationInterface $authorization
    ) {
        $this->scriptResource = $scriptResource;
        $this->urlBuilder = $urlBuilder;
        $this->authorization = $authorization;
    }

    /**
     * Identity changes with the count, so a dismissed notice comes back when more scripts need review.
     *
     * @return string
     */
    public function getIdentity(): string
    {
        return 'meetmage_psg_review_' . $this->getCount();
    }

    /**
     * Whether to show the notice.
     *
     * @return bool
     */
    public function isDisplayed(): bool
    {
        return $this->authorization->isAllowed('MeetMage_PaymentScriptGuard::inventory') && $this->getCount() > 0;
    }

    /**
     * Notice text.
     *
     * @return string
     */
    public function getText(): string
    {
        return (string) __(
            'Payment Script Guard: %1 script(s) on your payment pages are new or have changed. '
            . '<a href="%2">Review them now</a>.',
            $this->getCount(),
            $this->urlBuilder->getUrl('meetmage_psg/script/index')
        );
    }

    /**
     * Severity of the notice.
     *
     * @return int
     */
    public function getSeverity(): int
    {
        return self::SEVERITY_MAJOR;
    }

    /**
     * Number of scripts needing review.
     *
     * @return int
     */
    private function getCount(): int
    {
        if ($this->count === null) {
            try {
                $this->count = $this->scriptResource->countNeedingReview();
            } catch (\Exception $e) {
                $this->count = 0;
            }
        }
        return $this->count;
    }
}
