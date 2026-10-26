<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use FreeMagento\CustomerTwoFactor\Model\Config;
use FreeMagento\CustomerTwoFactor\Model\CustomerPreference;
use FreeMagento\CustomerTwoFactor\Model\TrustedDeviceService;

class Account implements ArgumentInterface
{
    private ?CustomerInterface $customer = null;

    public function __construct(
        private readonly Config $config,
        private readonly CustomerPreference $preference,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly Session $session,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function getCustomer(): CustomerInterface
    {
        if ($this->customer === null) {
            $this->customer = $this->customerRepository->getById((int)$this->session->getCustomerId());
        }
        return $this->customer;
    }

    public function isFeatureEnabled(): bool
    {
        return $this->config->isEnabled($this->getWebsiteId());
    }

    public function isRequiredByStore(): bool
    {
        return $this->config->isRequiredForEveryone($this->getWebsiteId());
    }

    /** Is the second factor currently active for this customer (by choice or by policy)? */
    public function isActive(): bool
    {
        return $this->isFeatureEnabled() && ($this->isRequiredByStore() || $this->preference->isEnabled($this->getCustomer()));
    }

    public function isTrustedDeviceEnabled(): bool
    {
        return $this->config->isTrustedDeviceEnabled($this->getWebsiteId());
    }

    public function getTrustedDeviceLifetime(): int
    {
        return $this->config->getTrustedDeviceLifetime($this->getWebsiteId());
    }

    /**
     * @return array<int, array{id:int, label:string, ip:string, added:string, last_used:string, expires:string, current:bool}>
     */
    public function getTrustedDevices(): array
    {
        $currentId = $this->trustedDevices->getCurrentDeviceId();
        $out = [];
        foreach ($this->trustedDevices->listForCustomer((int)$this->getCustomer()->getId()) as $row) {
            $out[] = [
                'id' => (int)$row['entity_id'],
                'label' => $this->describeUserAgent((string)($row['user_agent'] ?? '')),
                'ip' => (string)($row['ip_address'] ?? ''),
                'added' => $this->formatDate((string)$row['created_at']),
                'last_used' => $this->formatDate((string)($row['last_used_at'] ?? $row['created_at'])),
                'expires' => $this->formatDate((string)$row['expires_at']),
                'current' => $currentId === (int)$row['entity_id'],
            ];
        }
        return $out;
    }

    /** Turn a user-agent string into "Chrome on macOS" style text. Best effort, no library. */
    public function describeUserAgent(string $ua): string
    {
        if ($ua === '') {
            return (string)__('Unknown device');
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'CriOS/') || str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => (string)__('Browser'),
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        return $os !== '' ? sprintf('%s on %s', $browser, $os) : $browser;
    }

    private function formatDate(string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        return $this->timezone->formatDateTime($utc, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }

    private function getWebsiteId(): ?int
    {
        return $this->getCustomer()->getWebsiteId() !== null ? (int)$this->getCustomer()->getWebsiteId() : null;
    }
}
