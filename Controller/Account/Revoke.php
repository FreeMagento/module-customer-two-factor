<?php
/**
 * Forget one trusted device (POST id=<device>) or all of them (POST all=1).
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Account;

use Magento\Customer\Controller\AccountInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use FreeMagento\CustomerTwoFactor\Model\TrustedDeviceService;

class Revoke implements HttpPostActionInterface, AccountInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly Session $session,
        private readonly TrustedDeviceService $trustedDevices
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create()->setPath('twofactor/account');
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
            return $redirect;
        }

        $customerId = (int)$this->session->getCustomerId();
        if ($this->request->getPost('all')) {
            $this->trustedDevices->revokeAll($customerId);
            $this->messageManager->addSuccessMessage(__("All remembered devices have been forgotten. You'll be asked for a code on your next sign-in."));
            return $redirect;
        }

        $deviceId = (int)$this->request->getPost('id');
        if ($deviceId > 0) {
            $this->trustedDevices->revoke($customerId, $deviceId);
            $this->messageManager->addSuccessMessage(__('That device has been forgotten.'));
        }
        return $redirect;
    }
}
