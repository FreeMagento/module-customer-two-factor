<?php
/**
 * Wraps customer/account/loginPost. When the password is correct but the customer needs a second factor
 * (and this browser isn't trusted), the login is parked in the session, a code is emailed and the customer
 * is sent to /twofactor/verify instead of being signed in.
 *
 * Runs after the reCAPTCHA predispatch observer (that fires before the controller), so captcha still applies.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Plugin;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Store\Model\StoreManagerInterface;
use FreeMagento\CustomerTwoFactor\Model\Config;
use FreeMagento\CustomerTwoFactor\Model\OtpService;
use FreeMagento\CustomerTwoFactor\Model\PendingLogin;
use FreeMagento\CustomerTwoFactor\Model\Policy;
use FreeMagento\CustomerTwoFactor\Model\TrustedDeviceService;

class LoginPostPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly Policy $policy,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly OtpService $otp,
        private readonly PendingLogin $pendingLogin,
        private readonly Session $session,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountManagementInterface $accountManagement,
        private readonly StoreManagerInterface $storeManager,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly CustomerUrl $customerUrl,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @param LoginPost $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundExecute(LoginPost $subject, callable $proceed)
    {
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
        if (!$this->config->isEnabled($websiteId)
            || $this->session->isLoggedIn()
            || !$this->request->isPost()
            || !$this->formKeyValidator->validate($this->request)
        ) {
            return $proceed();
        }

        $login = $this->request->getPost('login');
        $username = is_array($login) ? trim((string)($login['username'] ?? '')) : '';
        $password = is_array($login) ? (string)($login['password'] ?? '') : '';
        if ($username === '' || $password === '') {
            return $proceed();
        }

        // Cheap pre-check: does this customer need a second factor at all, and is the browser already trusted?
        try {
            $customer = $this->customerRepository->get($username, $websiteId);
        } catch (NoSuchEntityException $e) {
            return $proceed(); // core will produce the usual "sign-in was incorrect" message
        } catch (LocalizedException $e) {
            return $proceed();
        }

        if (!$this->policy->isRequiredFor($customer) || $this->trustedDevices->isTrusted($customer)) {
            return $proceed();
        }

        // Second factor needed: authenticate here (same call core makes) and hold the login.
        try {
            $customer = $this->accountManagement->authenticate($username, $password);
        } catch (EmailNotConfirmedException $e) {
            $this->messageManager->addComplexErrorMessage(
                'confirmAccountErrorMessage',
                ['url' => $this->customerUrl->getEmailConfirmationUrl($username)]
            );
            return $this->failed($username);
        } catch (AuthenticationException $e) {
            $this->messageManager->addErrorMessage(
                __('The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.')
            );
            return $this->failed($username);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->failed($username);
        } catch (\Exception $e) {
            // PA DSS: never log the exception here - it may contain the password
            $this->messageManager->addErrorMessage(__('An unspecified error occurred. Please contact us for assistance.'));
            return $this->failed($username);
        }

        $this->pendingLogin->start((int)$customer->getId(), (string)$customer->getEmail());
        try {
            $this->otp->send($customer);
        } catch (LocalizedException $e) {
            // e.g. resend cooldown from a very recent attempt - the verify page still offers "send a new code"
            $this->messageManager->addNoticeMessage($e->getMessage());
        }

        $redirect = $this->redirectFactory->create();
        return $redirect->setPath('twofactor/verify');
    }

    private function failed(string $username)
    {
        $this->session->setUsername($username);
        $redirect = $this->redirectFactory->create();
        return $redirect->setPath('*/*/');
    }
}
