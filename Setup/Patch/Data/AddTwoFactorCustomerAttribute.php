<?php
/**
 * Adds the per-customer opt-in flag as a customer attribute, visible on the admin customer form
 * (Customers > All Customers > edit > Account Information) so staff can switch it for someone.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use FreeMagento\CustomerTwoFactor\Model\CustomerPreference;

class AddTwoFactorCustomerAttribute implements DataPatchInterface, PatchRevertableInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entityTypeId = (int)$customerSetup->getEntityTypeId(Customer::ENTITY);
        $attributeSetId = (int)$customerSetup->getDefaultAttributeSetId($entityTypeId);
        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $groupId = (int)$attributeSet->getDefaultGroupId($attributeSetId);

        $customerSetup->addAttribute(Customer::ENTITY, CustomerPreference::ATTRIBUTE_CODE, [
            'type' => 'int',
            'label' => 'Two-Factor Authentication (email code)',
            'input' => 'boolean',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
            'required' => false,
            'default' => 0,
            'visible' => true,
            'user_defined' => true,
            'system' => false,      // must be false or the value is dropped from the customer data model
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
            'is_searchable_in_grid' => false,
            'position' => 900,
            'sort_order' => 900,
        ]);

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, CustomerPreference::ATTRIBUTE_CODE);
        $attribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $groupId,
            'used_in_forms' => ['adminhtml_customer'],
        ]);
        $attribute->save();

        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->removeAttribute(Customer::ENTITY, CustomerPreference::ATTRIBUTE_CODE);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
