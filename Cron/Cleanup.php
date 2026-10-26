<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Cron;

use FreeMagento\CustomerTwoFactor\Model\ResourceModel\CodeStorage;
use FreeMagento\CustomerTwoFactor\Model\ResourceModel\TrustedDeviceStorage;

class Cleanup
{
    public function __construct(
        private readonly CodeStorage $codes,
        private readonly TrustedDeviceStorage $devices
    ) {
    }

    public function execute(): void
    {
        $this->codes->deleteExpired();
        $this->devices->deleteExpired();
    }
}
