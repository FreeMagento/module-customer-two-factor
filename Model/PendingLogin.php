<?php
/**
 * Holds "password accepted, code not yet verified" state in the (still anonymous) customer session.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Model\Session;

class PendingLogin
{
    private const KEY_CUSTOMER_ID = 'fm_twofactor_pending_customer_id';
    private const KEY_STARTED_AT = 'fm_twofactor_pending_started_at';
    private const KEY_EMAIL = 'fm_twofactor_pending_email';

    /** A pending login that has not been completed within this many seconds is dropped. */
    private const MAX_AGE_SECONDS = 30 * 60;

    public function __construct(
        private readonly Session $session
    ) {
    }

    public function start(int $customerId, string $email): void
    {
        $this->session->setData(self::KEY_CUSTOMER_ID, $customerId);
        $this->session->setData(self::KEY_EMAIL, $email);
        $this->session->setData(self::KEY_STARTED_AT, time());
    }

    public function isActive(): bool
    {
        return $this->getCustomerId() !== null;
    }

    public function getCustomerId(): ?int
    {
        $id = (int)$this->session->getData(self::KEY_CUSTOMER_ID);
        $startedAt = (int)$this->session->getData(self::KEY_STARTED_AT);
        if ($id <= 0 || $startedAt <= 0) {
            return null;
        }
        if (time() - $startedAt > self::MAX_AGE_SECONDS) {
            $this->clear();
            return null;
        }
        return $id;
    }

    public function getEmail(): string
    {
        return (string)$this->session->getData(self::KEY_EMAIL);
    }

    public function clear(): void
    {
        $this->session->unsetData(self::KEY_CUSTOMER_ID);
        $this->session->unsetData(self::KEY_EMAIL);
        $this->session->unsetData(self::KEY_STARTED_AT);
    }
}
