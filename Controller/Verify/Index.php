<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Verify;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly PendingLogin $pendingLogin,
        private readonly Session $session
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->session->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }
        if (!$this->pendingLogin->isActive()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Check your email'));
        return $page;
    }
}
