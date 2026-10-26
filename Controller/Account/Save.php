<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Account;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\AccountInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use FreeMagento\CustomerTwoFactor\Model\Config;
use FreeMagento\CustomerTwoFactor\Model\CustomerPreference;
use FreeMagento\CustomerTwoFactor\Model\TrustedDeviceService;
use Psr\Log\LoggerInterface;

class Save implements HttpPostActionInterface, AccountInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly Session $session,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerPreference $preference,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create()->setPath('twofactor/account');
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
            return $redirect;
        }

        $customer = $this->customerRepository->getById((int)$this->session->getCustomerId());
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;

        if (!$this->config->isEnabled($websiteId) || $this->config->isRequiredForEveryone($websiteId)) {
            $this->messageManager->addNoticeMessage(__('Two-factor authentication is managed by the store and cannot be changed here.'));
            return $redirect;
        }

        $enable = (bool)$this->request->getPost('enabled');
        try {
            $this->preference->setEnabled($customer, $enable);
            if (!$enable) {
                // trusted devices are meaningless once 2FA is off; start clean if it is switched back on
                $this->trustedDevices->revokeAll((int)$customer->getId());
                $this->messageManager->addSuccessMessage(__('Two-factor authentication has been switched off.'));
            } else {
                $this->messageManager->addSuccessMessage(
                    __("Two-factor authentication is on. Next time you sign in we'll email a code to %1.", $customer->getEmail())
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('[FreeMagento_CustomerTwoFactor] Could not save preference: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('We could not save your setting. Please try again.'));
        }

        return $redirect;
    }
}
