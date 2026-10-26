<?php
/**
 * Decides whether a given customer must complete the second factor on this website.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Api\Data\CustomerInterface;

class Policy
{
    public function __construct(
        private readonly Config $config,
        private readonly CustomerPreference $preference
    ) {
    }

    public function isRequiredFor(CustomerInterface $customer): bool
    {
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;
        if (!$this->config->isEnabled($websiteId)) {
            return false;
        }
        if ($this->config->isRequiredForEveryone($websiteId)) {
            return true;
        }
        return $this->preference->isEnabled($customer);
    }
}
