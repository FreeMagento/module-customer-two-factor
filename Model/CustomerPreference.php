<?php
/**
 * The customer's own opt-in flag, stored as the customer EAV attribute `fm_twofactor_enabled`
 * (also editable by staff on the admin customer form).
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

class CustomerPreference
{
    public const ATTRIBUTE_CODE = 'fm_twofactor_enabled';

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function isEnabled(CustomerInterface $customer): bool
    {
        $attribute = $customer->getCustomAttribute(self::ATTRIBUTE_CODE);
        return $attribute !== null && (int)$attribute->getValue() === 1;
    }

    public function setEnabled(CustomerInterface $customer, bool $enabled): void
    {
        $customer->setCustomAttribute(self::ATTRIBUTE_CODE, $enabled ? 1 : 0);
        $this->customerRepository->save($customer);
    }
}
