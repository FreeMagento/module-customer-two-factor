<?php
/**
 * "Remember this device": an HttpOnly cookie carrying `{deviceId}.{randomToken}`; only the HMAC of the
 * token is stored, so a database leak does not yield usable cookies.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\Config\ConfigInterface as SessionConfig;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use FreeMagento\CustomerTwoFactor\Model\ResourceModel\TrustedDeviceStorage;

class TrustedDeviceService
{
    public const COOKIE_NAME = 'fm_2fa_device';

    public function __construct(
        private readonly Config $config,
        private readonly TrustedDeviceStorage $storage,
        private readonly EncryptorInterface $encryptor,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly SessionConfig $sessionConfig,
        private readonly HttpRequest $request,
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    /** Is the current browser a still-valid trusted device for this customer? Updates last_used_at when it is. */
    public function isTrusted(CustomerInterface $customer): bool
    {
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;
        if (!$this->config->isTrustedDeviceEnabled($websiteId)) {
            return false;
        }
        $row = $this->getCurrentDeviceRow();
        if (!$row || (int)$row['customer_id'] !== (int)$customer->getId()) {
            return false;
        }
        $this->storage->touch((int)$row['entity_id']);
        return true;
    }

    /** The trusted-device row matching the cookie, if the cookie is well-formed, unexpired and its token matches. */
    public function getCurrentDeviceRow(): ?array
    {
        $cookie = (string)$this->cookieManager->getCookie(self::COOKIE_NAME, '');
        if ($cookie === '' || !str_contains($cookie, '.')) {
            return null;
        }
        [$id, $token] = explode('.', $cookie, 2);
        if (!ctype_digit($id) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = $this->storage->getById((int)$id);
        if (!$row) {
            return null;
        }
        $expires = (new \DateTimeImmutable($row['expires_at'], new \DateTimeZone('UTC')))->getTimestamp();
        if ($expires < time()) {
            $this->storage->delete((int)$row['customer_id'], (int)$row['entity_id']);
            return null;
        }
        if (!hash_equals($row['token_hash'], $this->encryptor->hash($token, Encryptor::HASH_VERSION_SHA256))) {
            return null;
        }
        return $row;
    }

    public function getCurrentDeviceId(): ?int
    {
        $row = $this->getCurrentDeviceRow();
        return $row ? (int)$row['entity_id'] : null;
    }

    /** Trust the current browser for the customer and set the cookie. */
    public function trust(CustomerInterface $customer): void
    {
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;
        if (!$this->config->isTrustedDeviceEnabled($websiteId)) {
            return;
        }
        $days = $this->config->getTrustedDeviceLifetime($websiteId);
        $seconds = $days * 86400;
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+' . $seconds . ' seconds');

        $deviceId = $this->storage->insert(
            (int)$customer->getId(),
            $this->encryptor->hash($token, Encryptor::HASH_VERSION_SHA256),
            $this->request->getServer('HTTP_USER_AGENT') ? (string)$this->request->getServer('HTTP_USER_AGENT') : null,
            $this->remoteAddress->getRemoteAddress() ?: null,
            $expiresAt
        );

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration($seconds)
            ->setPath((string)($this->sessionConfig->getCookiePath() ?: '/'))
            ->setDomain($this->sessionConfig->getCookieDomain() ?: null)
            ->setSecure((bool)$this->request->isSecure())
            ->setHttpOnly(true)
            ->setSameSite('Lax');
        $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $deviceId . '.' . $token, $metadata);
    }

    /** Forget the current browser (cookie + row). */
    public function forgetCurrent(int $customerId): void
    {
        $row = $this->getCurrentDeviceRow();
        if ($row && (int)$row['customer_id'] === $customerId) {
            $this->storage->delete($customerId, (int)$row['entity_id']);
        }
        $this->deleteCookie();
    }

    public function revoke(int $customerId, int $deviceId): void
    {
        $this->storage->delete($customerId, $deviceId);
        if ($this->getCurrentDeviceId() === null) {
            // the revoked device may have been this one - the row is gone so the cookie is now useless
            $this->deleteCookie();
        }
    }

    public function revokeAll(int $customerId): void
    {
        $this->storage->deleteAllForCustomer($customerId);
        $this->deleteCookie();
    }

    /** @return array<int, array<string,mixed>> */
    public function listForCustomer(int $customerId): array
    {
        return $this->storage->getForCustomer($customerId);
    }

    private function deleteCookie(): void
    {
        if ($this->cookieManager->getCookie(self::COOKIE_NAME) === null) {
            return;
        }
        $metadata = $this->cookieMetadataFactory->createCookieMetadata()
            ->setPath((string)($this->sessionConfig->getCookiePath() ?: '/'))
            ->setDomain($this->sessionConfig->getCookieDomain() ?: null);
        $this->cookieManager->deleteCookie(self::COOKIE_NAME, $metadata);
    }
}
