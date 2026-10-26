<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Mailer
{
    public function __construct(
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function sendCode(CustomerInterface $customer, string $code, int $lifetimeMinutes): void
    {
        $storeId = $customer->getStoreId() !== null
            ? (int)$customer->getStoreId()
            : (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($storeId);

        $name = trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname());

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->config->getEmailTemplate($storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'code' => $code,
                    'code_spaced' => implode(' ', str_split($code, 3)),
                    'lifetime' => $lifetimeMinutes,
                    'customer_name' => $name !== '' ? $name : (string)$customer->getEmail(),
                    'customer_firstname' => (string)$customer->getFirstname(),
                    'store' => $store,
                ])
                ->setFromByScope($this->config->getEmailIdentity($storeId), $storeId)
                ->addTo((string)$customer->getEmail(), $name)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error('[FreeMagento_CustomerTwoFactor] Could not send sign-in code: ' . $e->getMessage());
            throw new LocalizedException(
                __('We could not send your sign-in code just now. Please try again in a moment.'),
                $e
            );
        }
    }
}
