<?php
/**
 * Finishes a login that was held for the second factor - mirrors what
 * Magento\Customer\Controller\Account\LoginPost does after a successful authenticate().
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;

class LoginCompleter
{
    public function __construct(
        private readonly Session $session,
        private readonly AccountRedirect $accountRedirect,
        private readonly RedirectFactory $redirectFactory,
        private readonly RedirectInterface $redirect,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly PendingLogin $pendingLogin
    ) {
    }

    public function complete(CustomerInterface $customer): ResultInterface
    {
        $this->pendingLogin->clear();
        $this->session->setCustomerDataAsLoggedIn($customer);

        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata()->setPath('/');
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }

        $redirectUrl = $this->accountRedirect->getRedirectCookie();
        $redirectToDashboard = $this->scopeConfig->isSetFlag('customer/startup/redirect_dashboard', ScopeInterface::SCOPE_STORE);
        if (!$redirectToDashboard && $redirectUrl) {
            $this->accountRedirect->clearRedirectCookie();
            $result = $this->redirectFactory->create();
            // internal-URL check happens in RedirectInterface::success()
            $result->setUrl($this->redirect->success($redirectUrl));
            return $result;
        }

        return $this->accountRedirect->getRedirect();
    }
}
