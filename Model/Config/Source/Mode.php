<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use FreeMagento\CustomerTwoFactor\Model\Config;

class Mode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_OPTIONAL, 'label' => __('Optional - customers opt in from My Account')],
            ['value' => Config::MODE_REQUIRED, 'label' => __('Required - every customer must verify on sign-in')],
        ];
    }
}
