<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use FreeMagento\CustomerTwoFactor\Model\Config;
use FreeMagento\CustomerTwoFactor\Model\OtpService;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;

class Verify implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly PendingLogin $pendingLogin,
        private readonly OtpService $otp,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** Email address the code went to, lightly masked: sh•••@example.com */
    public function getMaskedEmail(): string
    {
        $email = $this->pendingLogin->getEmail();
        if (!str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        $keep = mb_strlen($local) > 2 ? 2 : 1;
        return mb_substr($local, 0, $keep) . str_repeat('•', max(3, mb_strlen($local) - $keep)) . '@' . $domain;
    }

    public function getCodeLength(): int
    {
        return OtpService::CODE_LENGTH;
    }

    public function getCodeLifetime(): int
    {
        return $this->config->getCodeLifetime($this->getWebsiteId());
    }

    /** Seconds until "Send a new code" is allowed (0 = straight away). */
    public function getResendWait(): int
    {
        $customerId = $this->pendingLogin->getCustomerId();
        if ($customerId === null) {
            return 0;
        }
        try {
            return $this->otp->getResendWait($this->customerRepository->getById($customerId));
        } catch (NoSuchEntityException $e) {
            return 0;
        }
    }

    public function isRememberDeviceAvailable(): bool
    {
        return $this->config->isTrustedDeviceEnabled($this->getWebsiteId());
    }

    public function isRememberCheckedByDefault(): bool
    {
        return $this->config->isRememberCheckedByDefault($this->getWebsiteId());
    }

    public function getTrustedDeviceLifetime(): int
    {
        return $this->config->getTrustedDeviceLifetime($this->getWebsiteId());
    }

    private function getWebsiteId(): int
    {
        return (int)$this->storeManager->getStore()->getWebsiteId();
    }
}
