<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Model;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\DataObject;

/**
 * Name of the signed-in admin user, recorded with every authorization decision.
 */
class AdminIdentity
{
    /**
     * @var Session
     */
    private Session $authSession;

    /**
     * @param Session $authSession
     */
    public function __construct(Session $authSession)
    {
        $this->authSession = $authSession;
    }

    /**
     * "username (email)" of the current admin user.
     *
     * @return string
     */
    public function getName(): string
    {
        $user = $this->authSession->getUser();
        if (!$user instanceof DataObject) {
            return 'admin';
        }
        $name = (string) $user->getData('username');
        $email = (string) $user->getData('email');
        return $email !== '' ? sprintf('%s (%s)', $name, $email) : ($name ?: 'admin');
    }
}
