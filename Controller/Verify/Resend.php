<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Verify;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use FreeMagento\CustomerTwoFactor\Model\OtpService;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;

class Resend implements HttpPostActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager,
        private readonly PendingLogin $pendingLogin,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OtpService $otp
    ) {
    }

    public function execute(): ResultInterface
    {
        $customerId = $this->pendingLogin->getCustomerId();
        if ($customerId === null || !$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Your sign-in session has expired. Please sign in again.'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $this->otp->send($customer);
            $this->messageManager->addSuccessMessage(__('A new code has been sent to %1.', $this->pendingLogin->getEmail()));
        } catch (NoSuchEntityException $e) {
            $this->pendingLogin->clear();
            return $this->redirectFactory->create()->setPath('customer/account/login');
        } catch (LocalizedException $e) {
            $this->messageManager->addNoticeMessage($e->getMessage());
        }

        return $this->redirectFactory->create()->setPath('twofactor/verify');
    }
}
