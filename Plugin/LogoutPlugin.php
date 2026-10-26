<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Plugin;

use Magento\Customer\Controller\Account\Logout;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;

class LogoutPlugin
{
    public function __construct(
        private readonly PendingLogin $pendingLogin
    ) {
    }

    public function beforeExecute(Logout $subject): void
    {
        $this->pendingLogin->clear();
    }
}
