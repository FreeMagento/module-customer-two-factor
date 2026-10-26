<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Verify;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use FreeMagento\CustomerTwoFactor\Model\LoginCompleter;
use FreeMagento\CustomerTwoFactor\Model\OtpService;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;
use FreeMagento\CustomerTwoFactor\Model\TrustedDeviceService;
use FreeMagento\CustomerTwoFactor\Model\VerifyResult;

class Post implements HttpPostActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly PendingLogin $pendingLogin,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OtpService $otp,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly LoginCompleter $loginCompleter,
        private readonly Session $session
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->session->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }
        $customerId = $this->pendingLogin->getCustomerId();
        if ($customerId === null || !$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Your sign-in session has expired. Please sign in again.'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $e) {
            $this->pendingLogin->clear();
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $result = $this->otp->verify($customer, (string)$this->request->getPost('code', ''));

        if ($result->isOk()) {
            if ($this->request->getPost('remember_device')) {
                $this->trustedDevices->trust($customer);
            }
            return $this->loginCompleter->complete($customer);
        }

        $this->messageManager->addErrorMessage(match ($result->status) {
            VerifyResult::WRONG => $result->attemptsLeft === 1
                ? __('That code is not right. You have 1 attempt left before a new code is needed.')
                : __('That code is not right. You have %1 attempts left.', $result->attemptsLeft),
            VerifyResult::EXPIRED => __('That code has expired. Please request a new one.'),
            VerifyResult::TOO_MANY_ATTEMPTS => __('Too many wrong attempts. Please request a new code.'),
            default => __('No active code was found. Please request a new one.'),
        });

        return $this->redirectFactory->create()->setPath('twofactor/verify');
    }
}
