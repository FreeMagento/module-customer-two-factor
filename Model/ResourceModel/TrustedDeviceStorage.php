<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class TrustedDeviceStorage
{
    public const TABLE = 'freemagento_twofactor_trusted_device';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function insert(
        int $customerId,
        string $tokenHash,
        ?string $userAgent,
        ?string $ip,
        \DateTimeInterface $expiresAt
    ): int {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $now = $this->now();
        $connection->insert($table, [
            'customer_id' => $customerId,
            'token_hash' => $tokenHash,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'ip_address' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            'created_at' => $now,
            'last_used_at' => $now,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
        return (int)$connection->lastInsertId($table);
    }

    /** @return array<string,mixed>|null */
    public function getById(int $deviceId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE))
            ->where('entity_id = ?', $deviceId);
        $row = $connection->fetchRow($select);
        return $row ?: null;
    }

    /** @return array<int, array<string,mixed>> newest first */
    public function getForCustomer(int $customerId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE))
            ->where('customer_id = ?', $customerId)
            ->where('expires_at >= ?', $this->now())
            ->order('created_at DESC');
        return $connection->fetchAll($select);
    }

    public function touch(int $deviceId): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['last_used_at' => $this->now()],
            ['entity_id = ?' => $deviceId]
        );
    }

    public function delete(int $customerId, int $deviceId): int
    {
        return $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['customer_id = ?' => $customerId, 'entity_id = ?' => $deviceId]
        );
    }

    public function deleteAllForCustomer(int $customerId): int
    {
        return $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['customer_id = ?' => $customerId]
        );
    }

    public function deleteExpired(): int
    {
        return $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['expires_at < ?' => $this->now()]
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
