<?php
/**
 * Generates, emails and verifies the one-time code. Only the HMAC of the code is stored.
 */
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use FreeMagento\CustomerTwoFactor\Model\ResourceModel\CodeStorage;

class OtpService
{
    public const CODE_LENGTH = 6;

    public function __construct(
        private readonly Config $config,
        private readonly CodeStorage $storage,
        private readonly EncryptorInterface $encryptor,
        private readonly Mailer $mailer
    ) {
    }

    /**
     * Generate a fresh code, store its hash and email it to the customer.
     *
     * @throws LocalizedException when the resend cooldown has not elapsed or the email cannot be sent
     */
    public function send(CustomerInterface $customer): void
    {
        $customerId = (int)$customer->getId();
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;

        $cooldown = $this->config->getResendCooldown($websiteId);
        $existing = $this->storage->get($customerId);
        if ($existing && $cooldown > 0) {
            $sentAt = $this->toTimestamp($existing['created_at']);
            $wait = $cooldown - (time() - $sentAt);
            if ($wait > 0) {
                throw new LocalizedException(
                    __('A code was sent a moment ago. Please wait %1 seconds before requesting another.', $wait)
                );
            }
        }

        $code = $this->generateCode();
        $lifetime = $this->config->getCodeLifetime($websiteId);
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+' . $lifetime . ' minutes');

        $this->storage->save($customerId, $this->hash($code), $expiresAt);
        $this->mailer->sendCode($customer, $code, $lifetime);
    }

    /**
     * Check a customer-entered code. Wrong entries count against the attempt limit;
     * a correct entry consumes the code.
     */
    public function verify(CustomerInterface $customer, string $input): VerifyResult
    {
        $customerId = (int)$customer->getId();
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;
        $input = preg_replace('/\D+/', '', $input) ?? '';

        $row = $this->storage->get($customerId);
        if (!$row) {
            return VerifyResult::noCode();
        }
        if ($this->toTimestamp($row['expires_at']) < time()) {
            $this->storage->delete($customerId);
            return VerifyResult::expired();
        }
        if (strlen($input) === self::CODE_LENGTH && hash_equals($row['code_hash'], $this->hash($input))) {
            $this->storage->delete($customerId);
            return VerifyResult::ok();
        }

        $attempts = $row['attempts'] + 1;
        if ($attempts >= $this->config->getMaxAttempts($websiteId)) {
            $this->storage->delete($customerId);
            return VerifyResult::tooManyAttempts();
        }
        $this->storage->incrementAttempts($customerId);
        return VerifyResult::wrong($this->config->getMaxAttempts($websiteId) - $attempts);
    }

    public function discard(int $customerId): void
    {
        $this->storage->delete($customerId);
    }

    /** Seconds the customer still has to wait before "Send a new code" works (0 = now). */
    public function getResendWait(CustomerInterface $customer): int
    {
        $websiteId = $customer->getWebsiteId() !== null ? (int)$customer->getWebsiteId() : null;
        $row = $this->storage->get((int)$customer->getId());
        if (!$row) {
            return 0;
        }
        $wait = $this->config->getResendCooldown($websiteId) - (time() - $this->toTimestamp($row['created_at']));
        return max(0, $wait);
    }

    private function generateCode(): string
    {
        return str_pad((string)random_int(0, (10 ** self::CODE_LENGTH) - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        // Keyed HMAC with the installation's crypt key - a plain sha256 of a 6-digit code would be trivially reversible
        return $this->encryptor->hash($code, Encryptor::HASH_VERSION_SHA256);
    }

    private function toTimestamp(string $mysqlDateTime): int
    {
        return (new \DateTimeImmutable($mysqlDateTime, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
