<?php
declare(strict_types=1);

namespace FreeMagento\CustomerTwoFactor\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class CodeStorage
{
    public const TABLE = 'freemagento_twofactor_code';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /** Replaces any existing code for the customer. */
    public function save(int $customerId, string $codeHash, \DateTimeInterface $expiresAt): void
    {
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            [
                'customer_id' => $customerId,
                'code_hash' => $codeHash,
                'attempts' => 0,
                'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ],
            ['code_hash', 'attempts', 'created_at', 'expires_at']
        );
    }

    /** @return array{customer_id:int, code_hash:string, attempts:int, created_at:string, expires_at:string}|null */
    public function get(int $customerId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE))
            ->where('customer_id = ?', $customerId);
        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }
        $row['customer_id'] = (int)$row['customer_id'];
        $row['attempts'] = (int)$row['attempts'];
        return $row;
    }

    public function incrementAttempts(int $customerId): void
    {
        $connection = $this->resource->getConnection();
        $connection->update(
            $this->resource->getTableName(self::TABLE),
            ['attempts' => new \Zend_Db_Expr('attempts + 1')],
            ['customer_id = ?' => $customerId]
        );
    }

    public function delete(int $customerId): void
    {
        $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['customer_id = ?' => $customerId]
        );
    }

    public function deleteExpired(): int
    {
        return $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['expires_at < ?' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')]
        );
    }
}
