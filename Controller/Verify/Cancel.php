<?php
/**
 * "Use a different account" - abandon the held login and go back to the sign-in form.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Controller\Verify;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use FreeMagento\CustomerTwoFactor\Model\OtpService;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;

class Cancel implements HttpPostActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly PendingLogin $pendingLogin,
        private readonly OtpService $otp
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->formKeyValidator->validate($this->request)) {
            $customerId = $this->pendingLogin->getCustomerId();
            if ($customerId !== null) {
                $this->otp->discard($customerId);
            }
            $this->pendingLogin->clear();
        }
        return $this->redirectFactory->create()->setPath('customer/account/login');
    }
}
