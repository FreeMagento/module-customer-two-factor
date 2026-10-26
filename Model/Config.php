<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const MODE_OPTIONAL = 'optional';
    public const MODE_REQUIRED = 'required';

    private const XML_ENABLED = 'freemagento_twofactor/general/enabled';
    private const XML_MODE = 'freemagento_twofactor/general/mode';
    private const XML_CODE_LIFETIME = 'freemagento_twofactor/code/lifetime';
    private const XML_CODE_MAX_ATTEMPTS = 'freemagento_twofactor/code/max_attempts';
    private const XML_CODE_RESEND_COOLDOWN = 'freemagento_twofactor/code/resend_cooldown';
    private const XML_CODE_EMAIL_TEMPLATE = 'freemagento_twofactor/code/email_template';
    private const XML_CODE_EMAIL_IDENTITY = 'freemagento_twofactor/code/email_identity';
    private const XML_DEVICE_ENABLED = 'freemagento_twofactor/trusted_device/enabled';
    private const XML_DEVICE_LIFETIME = 'freemagento_twofactor/trusted_device/lifetime';
    private const XML_DEVICE_CHECKED = 'freemagento_twofactor/trusted_device/checked_by_default';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function isRequiredForEveryone(?int $websiteId = null): bool
    {
        return $this->getMode($websiteId) === self::MODE_REQUIRED;
    }

    public function getMode(?int $websiteId = null): string
    {
        $mode = (string)$this->scopeConfig->getValue(self::XML_MODE, ScopeInterface::SCOPE_WEBSITE, $websiteId);
        return $mode === self::MODE_REQUIRED ? self::MODE_REQUIRED : self::MODE_OPTIONAL;
    }

    /** Code lifetime in minutes */
    public function getCodeLifetime(?int $websiteId = null): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_CODE_LIFETIME, ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    public function getMaxAttempts(?int $websiteId = null): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_CODE_MAX_ATTEMPTS, ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    /** Resend cooldown in seconds */
    public function getResendCooldown(?int $websiteId = null): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_CODE_RESEND_COOLDOWN, ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    public function getEmailTemplate(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_CODE_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId)
            ?: 'freemagento_twofactor_code';
    }

    public function getEmailIdentity(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_CODE_EMAIL_IDENTITY, ScopeInterface::SCOPE_STORE, $storeId)
            ?: 'general';
    }

    public function isTrustedDeviceEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_DEVICE_ENABLED, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /** Trusted-device lifetime in days */
    public function getTrustedDeviceLifetime(?int $websiteId = null): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_DEVICE_LIFETIME, ScopeInterface::SCOPE_WEBSITE, $websiteId));
    }

    public function isRememberCheckedByDefault(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_DEVICE_CHECKED, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }
}
