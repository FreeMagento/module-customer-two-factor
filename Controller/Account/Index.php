<?php
/**
 * My Account > Login Security: switch two-factor on/off (when optional) and manage trusted devices.
 * Implements AccountInterface so Magento's customer-auth plugin redirects guests to sign in.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Account;

use Magento\Customer\Controller\AccountInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface, AccountInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Login Security'));
        return $page;
    }
}
