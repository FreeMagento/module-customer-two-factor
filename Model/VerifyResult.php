<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model;

final class VerifyResult
{
    public const OK = 'ok';
    public const WRONG = 'wrong';
    public const EXPIRED = 'expired';
    public const NO_CODE = 'no_code';
    public const TOO_MANY_ATTEMPTS = 'too_many_attempts';

    private function __construct(
        public readonly string $status,
        public readonly int $attemptsLeft = 0
    ) {
    }

    public static function ok(): self
    {
        return new self(self::OK);
    }

    public static function wrong(int $attemptsLeft): self
    {
        return new self(self::WRONG, $attemptsLeft);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function noCode(): self
    {
        return new self(self::NO_CODE);
    }

    public static function tooManyAttempts(): self
    {
        return new self(self::TOO_MANY_ATTEMPTS);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    /** True when the customer needs a fresh code before they can try again. */
    public function needsNewCode(): bool
    {
        return in_array($this->status, [self::EXPIRED, self::NO_CODE, self::TOO_MANY_ATTEMPTS], true);
    }
}
