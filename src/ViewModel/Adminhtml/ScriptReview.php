<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\ViewModel\Adminhtml;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Event as EventResource;
use MeetMage\PaymentScriptGuard\Model\ResourceModel\Script as ScriptResource;
use MeetMage\PaymentScriptGuard\Model\Source\IntegrityStatus;
use MeetMage\PaymentScriptGuard\Model\Source\Status;

/**
 * Data for the script review page.
 */
class ScriptReview implements ArgumentInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var ScriptResource
     */
    private ScriptResource $scriptResource;

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @var Status
     */
    private Status $status;

    /**
     * @var IntegrityStatus
     */
    private IntegrityStatus $integrityStatus;

    /**
     * @var array<string,mixed>|null
     */
    private ?array $script = null;

    /**
     * @param RequestInterface $request
     * @param ScriptResource $scriptResource
     * @param EventResource $eventResource
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param Status $status
     * @param IntegrityStatus $integrityStatus
     */
    public function __construct(
        RequestInterface $request,
        ScriptResource $scriptResource,
        EventResource $eventResource,
        UrlInterface $urlBuilder,
        AuthorizationInterface $authorization,
        Status $status,
        IntegrityStatus $integrityStatus
    ) {
        $this->request = $request;
        $this->scriptResource = $scriptResource;
        $this->eventResource = $eventResource;
        $this->urlBuilder = $urlBuilder;
        $this->authorization = $authorization;
        $this->status = $status;
        $this->integrityStatus = $integrityStatus;
    }

    /**
     * The inventory row being reviewed.
     *
     * @return array<string,mixed>
     */
    public function getScript(): array
    {
        if ($this->script === null) {
            $connection = $this->scriptResource->getConnection();
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($this->scriptResource->getMainTable())
                    ->where('script_id = ?', (int) $this->request->getParam('id'))
            );
            $this->script = $row ?: [];
        }
        return $this->script;
    }

    /**
     * Activity of this script, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getEvents(): array
    {
        $script = $this->getScript();
        return $script ? $this->eventResource->getForScript((int) $script['script_id']) : [];
    }

    /**
     * Whether the admin user may authorize or reject scripts.
     *
     * @return bool
     */
    public function canDecide(): bool
    {
        return $this->authorization->isAllowed('MeetMage_PaymentScriptGuard::authorize');
    }

    /**
     * Label of an authorization status.
     *
     * @param string $value
     * @return string
     */
    public function statusLabel(string $value): string
    {
        return $this->label($this->status->toOptionArray(), $value);
    }

    /**
     * Label of an integrity status.
     *
     * @param string $value
     * @return string
     */
    public function integrityLabel(string $value): string
    {
        return $this->label($this->integrityStatus->toOptionArray(), $value);
    }

    /**
     * Form action URL.
     *
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->urlBuilder->getUrl('meetmage_psg/script/save');
    }

    /**
     * Inventory grid URL.
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->urlBuilder->getUrl('meetmage_psg/script/index');
    }

    /**
     * Option label for a value.
     *
     * @param array<int,array{value:string,label:mixed}> $options
     * @param string $value
     * @return string
     */
    private function label(array $options, string $value): string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return (string) $option['label'];
            }
        }
        return $value;
    }
}
