<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WalletRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function ensureWallet(string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_wallets (user_id, balance_coin, total_coin_purchased, total_coin_spent, updated_at)
             VALUES (:user_id, 0, 0, 0, NOW())
             ON DUPLICATE KEY UPDATE updated_at = updated_at'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function getWallet(string $userId): array
    {
        $this->ensureWallet($userId);

        $stmt = $this->pdo->prepare(
            'SELECT user_id, balance_coin, total_coin_purchased, total_coin_spent, updated_at
             FROM user_wallets
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? [
            'user_id' => $userId,
            'balance_coin' => 0,
            'total_coin_purchased' => 0,
            'total_coin_spent' => 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ] : $row;
    }

    public function getWalletForUpdate(string $userId): array
    {
        $this->ensureWallet($userId);

        $stmt = $this->pdo->prepare(
            'SELECT user_id, balance_coin, total_coin_purchased, total_coin_spent
             FROM user_wallets
             WHERE user_id = :user_id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? [
            'user_id' => $userId,
            'balance_coin' => 0,
            'total_coin_purchased' => 0,
            'total_coin_spent' => 0,
        ] : $row;
    }

    public function updateWalletBalances(string $userId, int $balanceCoin, int $totalPurchased, int $totalSpent): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_wallets
             SET balance_coin = :balance_coin,
                 total_coin_purchased = :total_coin_purchased,
                 total_coin_spent = :total_coin_spent,
                 updated_at = NOW()
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'balance_coin' => $balanceCoin,
            'total_coin_purchased' => $totalPurchased,
            'total_coin_spent' => $totalSpent,
        ]);
    }

    public function createTransaction(
        string $userId,
        string $type,
        int $coinDelta,
        int $balanceAfter,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        array $metadata = [],
        ?string $createdBy = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO wallet_transactions (
                user_id,
                type,
                coin_delta,
                balance_after,
                reference_type,
                reference_id,
                description,
                metadata,
                created_by,
                created_at
             ) VALUES (
                :user_id,
                :type,
                :coin_delta,
                :balance_after,
                :reference_type,
                :reference_id,
                :description,
                :metadata,
                :created_by,
                NOW()
             )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'coin_delta' => $coinDelta,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => $metadata === [] ? null : (json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null),
            'created_by' => $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findPackageById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, coin_amount, bonus_coin, fiat_price AS display_price, currency, is_active, sort_order, created_at, updated_at
             FROM coin_catalog
             WHERE id = :id AND catalog_type = "coin_package"
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listTransactions(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT id, type, coin_delta, balance_after, reference_type, reference_id, description, metadata, created_by, created_at
             FROM wallet_transactions
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countTransactions(string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM wallet_transactions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function adminTransactionSummary(): array
    {
        $sql = 'SELECT
            (SELECT COALESCE(SUM(balance_coin), 0) FROM user_wallets) AS circulating_coin,
            COALESCE(SUM(CASE WHEN wt.coin_delta > 0 THEN wt.coin_delta ELSE 0 END), 0) AS credited_coin,
            COALESCE(ABS(SUM(CASE WHEN wt.coin_delta < 0 THEN wt.coin_delta ELSE 0 END)), 0) AS spent_coin,
            COALESCE(SUM(CASE WHEN wt.type = "refund" THEN wt.coin_delta ELSE 0 END), 0) AS refunded_coin,
            COUNT(*) AS transaction_count
            FROM wallet_transactions wt
            WHERE wt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        $row = $this->pdo->query($sql)->fetch();
        return $row ?: [];
    }

    public function listAdminTransactions(int $page, int $perPage, string $query = '', ?string $type = null, string $sort = 'newest'): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(wt.user_id LIKE :query OR u.username LIKE :query OR wt.description LIKE :query OR wt.reference_id LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if ($type !== null && $type !== '') {
            $where[] = 'wt.type = :type';
            $params['type'] = $type;
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $orderSql = $sort === 'oldest' ? 'wt.id ASC' : ($sort === 'amount_desc' ? 'ABS(wt.coin_delta) DESC, wt.id DESC' : 'wt.id DESC');
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM wallet_transactions wt INNER JOIN users u ON u.id = wt.user_id $whereSql");
        $count->execute($params);
        $stmt = $this->pdo->prepare("SELECT wt.id, wt.user_id, u.username, wt.type, wt.coin_delta, wt.balance_after, wt.reference_type, wt.reference_id, wt.description, wt.metadata, wt.created_by, wt.created_at,
            (SELECT COALESCE(SUM(r.coin_delta), 0) FROM wallet_transactions r WHERE r.type = 'refund' AND r.reference_type = 'wallet_transaction' AND r.reference_id = CAST(wt.id AS CHAR)) AS refunded_coin
            FROM wallet_transactions wt INNER JOIN users u ON u.id = wt.user_id $whereSql ORDER BY $orderSql LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => (int)$count->fetchColumn()];
    }

    public function transactionForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_id, type, coin_delta, reference_type, reference_id, description FROM wallet_transactions WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function refundedCoinForTransaction(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(coin_delta), 0) FROM wallet_transactions WHERE type = "refund" AND reference_type = "wallet_transaction" AND reference_id = :id');
        $stmt->execute(['id' => (string)$id]);
        return (int)$stmt->fetchColumn();
    }

    public function revokeUnlockByTransactionId(int $transactionId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_unlocks WHERE transaction_id = :id');
        $stmt->execute(['id' => $transactionId]);
    }

    public function listPackages(int $page, int $perPage, bool $activeOnly = false): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = $activeOnly ? 'AND is_active = 1' : '';
        $stmt = $this->pdo->prepare(
            "SELECT id, name, coin_amount, bonus_coin, fiat_price AS display_price, currency, is_active, sort_order, created_at, updated_at
             FROM coin_catalog
             WHERE catalog_type = 'coin_package'
             {$where}
             ORDER BY sort_order ASC, id ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countPackages(bool $activeOnly = false): int
    {
        $sql = $activeOnly ? "SELECT COUNT(*) FROM coin_catalog WHERE catalog_type = 'coin_package' AND is_active = 1" : "SELECT COUNT(*) FROM coin_catalog WHERE catalog_type = 'coin_package'";
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function createPackage(string $name, int $coinAmount, int $bonusCoin, string $displayPrice, string $currency, bool $isActive, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO coin_catalog (catalog_type, item_key, name, coin_amount, bonus_coin, fiat_price, currency, is_active, sort_order, created_at, updated_at)
             VALUES ("coin_package", :item_key, :name, :coin_amount, :bonus_coin, :fiat_price, :currency, :is_active, :sort_order, NOW(), NOW())'
        );
        $itemKey = 'pkg_' . bin2hex(random_bytes(6));
        $stmt->execute([
            'item_key' => $itemKey,
            'name' => $name,
            'coin_amount' => $coinAmount,
            'bonus_coin' => $bonusCoin,
            'fiat_price' => (float) $displayPrice,
            'currency' => $currency,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePackage(int $id, string $name, int $coinAmount, int $bonusCoin, string $displayPrice, string $currency, bool $isActive, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE coin_catalog
             SET name = :name,
                 coin_amount = :coin_amount,
                 bonus_coin = :bonus_coin,
                 fiat_price = :fiat_price,
                 currency = :currency,
                 is_active = :is_active,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE id = :id AND catalog_type = "coin_package"'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'coin_amount' => $coinAmount,
            'bonus_coin' => $bonusCoin,
            'fiat_price' => (float) $displayPrice,
            'currency' => $currency,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);
    }

    public function upsertSeriesPricing(string $contentId, int $priceCoin, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO coin_catalog (catalog_type, item_key, name, coin_price, is_active, updated_at)
             VALUES ("series_bundle", :item_key, :name, :price_coin, :is_active, NOW())
             ON DUPLICATE KEY UPDATE coin_price = VALUES(coin_price), is_active = VALUES(is_active), updated_at = NOW()'
        );
        $stmt->execute([
            'item_key' => $contentId,
            'name' => 'Series Bundle ' . $contentId,
            'price_coin' => $priceCoin,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function upsertChapterPricing(string $chapterId, int $priceCoin, bool $isActive): void
    {
        $priceAmount = $isActive ? max(0, $priceCoin) : 0;
        $stmt = $this->pdo->prepare(
            'UPDATE chapters
             SET price_amount = :price_amount, price_last_update = NOW()
             WHERE id = :chapter_id'
        );
        $stmt->execute([
            'chapter_id' => $chapterId,
            'price_amount' => $priceAmount,
        ]);
    }

    public function getSeriesPrice(string $contentId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT coin_price
             FROM coin_catalog
             WHERE item_key = :content_id AND catalog_type = "series_bundle" AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['content_id' => $contentId]);
        $value = $stmt->fetchColumn();
        return $value === false ? 0 : max(0, (int) $value);
    }

    public function getChapterPrice(string $chapterId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT CASE 
                WHEN is_free_after IS NOT NULL AND is_free_after <= NOW() THEN 0 
                ELSE price_amount 
             END AS effective_price
             FROM chapters
             WHERE id = :chapter_id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['chapter_id' => $chapterId]);
        $value = $stmt->fetchColumn();
        return $value === false ? 0 : max(0, (int) $value);
    }

    public function getChapterPricing(string $chapterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT price_amount, price_last_update, published_at, is_free_after,
                    CASE WHEN is_free_after IS NOT NULL AND is_free_after <= NOW() THEN 1 ELSE 0 END AS is_freed
             FROM chapters
             WHERE id = :chapter_id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['chapter_id' => $chapterId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['price_coin' => 0, 'price_last_update' => null, 'published_at' => null, 'is_free_after' => null];
        }

        $effectivePrice = !empty($row['is_freed']) ? 0 : max(0, (int) ($row['price_amount'] ?? 0));

        return [
            'price_coin' => $effectivePrice,
            'base_price' => max(0, (int) ($row['price_amount'] ?? 0)),
            'price_last_update' => $row['price_last_update'] ?? null,
            'published_at' => $row['published_at'] ?? null,
            'is_free_after' => $row['is_free_after'] ?? null,
        ];
    }

    public function hasSeriesPricing(string $contentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM coin_catalog WHERE item_key = :content_id AND catalog_type = "series_bundle" AND is_active = 1 AND coin_price > 0 LIMIT 1');
        $stmt->execute(['content_id' => $contentId]);
        return $stmt->fetchColumn() !== false;
    }

    public function hasPremiumChapters(string $contentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM chapters ch
             WHERE ch.content_id = :content_id
               AND ch.deleted_at IS NULL
               AND ch.price_amount > 0
             LIMIT 1'
        );
        $stmt->execute(['content_id' => $contentId]);
        return $stmt->fetchColumn() !== false;
    }

    public function hasSeriesUnlock(string $userId, string $contentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM user_unlocks WHERE user_id = :user_id AND unlock_type = "series" AND target_id = :content_id LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'content_id' => $contentId]);
        return $stmt->fetchColumn() !== false;
    }

    public function hasChapterUnlock(string $userId, string $chapterId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM user_unlocks
             WHERE user_id = :user_id
               AND (
                   (unlock_type = "chapter" AND target_id = :chapter_id)
                   OR (unlock_type = "series" AND target_id = (SELECT content_id FROM chapters WHERE id = :chapter_id_sub))
               ) LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'chapter_id' => $chapterId,
            'chapter_id_sub' => $chapterId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function createSeriesUnlock(string $userId, string $contentId, int $priceCoin, ?int $transactionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_unlocks (user_id, unlock_type, target_id, content_id, price_coin, transaction_id, unlocked_at)
             VALUES (:user_id, "series", :target_id, :content_id, :price_coin, :transaction_id, NOW())
             ON DUPLICATE KEY UPDATE price_coin = VALUES(price_coin)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $contentId,
            'content_id' => $contentId,
            'price_coin' => $priceCoin,
            'transaction_id' => $transactionId,
        ]);
    }

    public function createChapterUnlock(string $userId, string $chapterId, string $contentId, int $priceCoin, ?int $transactionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_unlocks (user_id, unlock_type, target_id, content_id, price_coin, transaction_id, unlocked_at)
             VALUES (:user_id, "chapter", :target_id, :content_id, :price_coin, :transaction_id, NOW())
             ON DUPLICATE KEY UPDATE price_coin = VALUES(price_coin)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $chapterId,
            'content_id' => $contentId,
            'price_coin' => $priceCoin,
            'transaction_id' => $transactionId,
        ]);
    }

    public function listSeriesUnlocks(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.content_id, s.title AS content_title, s.slug AS content_slug, s.type AS content_type, u.price_coin, u.transaction_id, u.unlocked_at
             FROM user_unlocks u
             INNER JOIN series s ON s.id = u.content_id
             WHERE u.user_id = :user_id AND u.unlock_type = "series"
             ORDER BY u.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countSeriesUnlocks(string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_unlocks WHERE user_id = :user_id AND unlock_type = "series"');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function listChapterUnlocks(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.content_id, u.target_id AS chapter_id, s.title AS content_title, s.slug AS content_slug, s.type AS content_type,
                    COALESCE(ch.chapter_number, CAST(ch.number AS CHAR)) AS chapter_number, ch.title AS chapter_title, u.price_coin, u.transaction_id, u.unlocked_at
             FROM user_unlocks u
             INNER JOIN series s ON s.id = u.content_id
             INNER JOIN chapters ch ON ch.id = u.target_id
             WHERE u.user_id = :user_id AND u.unlock_type = "chapter"
             ORDER BY u.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countChapterUnlocks(string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_unlocks WHERE user_id = :user_id AND unlock_type = "chapter"');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function upsertFeatureProduct(string $featureKey, string $name, int $coinPrice, int $durationDays, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO coin_catalog (catalog_type, item_key, name, coin_price, duration_days, is_active, updated_at)
             VALUES ("feature_pass", :feature_key, :name, :coin_price, :duration_days, :is_active, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                coin_price = VALUES(coin_price),
                duration_days = VALUES(duration_days),
                is_active = VALUES(is_active),
                updated_at = NOW()'
        );
        $stmt->execute([
            'feature_key' => $featureKey,
            'name' => $name,
            'coin_price' => $coinPrice,
            'duration_days' => $durationDays,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function getFeatureProduct(string $featureKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT item_key AS feature_key, name, coin_price, duration_days, is_active, updated_at
             FROM coin_catalog
             WHERE item_key = :feature_key AND catalog_type = "feature_pass"
             LIMIT 1'
        );
        $stmt->execute(['feature_key' => $featureKey]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listFeatureProducts(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'AND is_active = 1' : '';
        $stmt = $this->pdo->query(
            "SELECT item_key AS feature_key, name, coin_price, duration_days, is_active, updated_at
             FROM coin_catalog
             WHERE catalog_type = 'feature_pass'
             {$where}
             ORDER BY item_key ASC"
        );
        return $stmt->fetchAll();
    }

    public function createFeatureEntitlement(
        string $userId,
        string $featureKey,
        ?string $sourceType,
        ?string $sourceId,
        ?int $transactionId,
        string $startsAt,
        string $expiresAt
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_unlocks (user_id, unlock_type, target_id, content_id, price_coin, transaction_id, starts_at, expires_at, unlocked_at)
             VALUES (:user_id, "feature", :target_id, NULL, 0, :transaction_id, :starts_at, :expires_at, NOW())
             ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $featureKey,
            'transaction_id' => $transactionId,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getLatestActiveFeatureEntitlement(string $userId, string $featureKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, target_id AS feature_key, transaction_id, starts_at, expires_at, unlocked_at AS created_at
             FROM user_unlocks
             WHERE user_id = :user_id
               AND unlock_type = "feature"
               AND target_id = :feature_key
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY expires_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'feature_key' => $featureKey,
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listFeatureEntitlements(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT id, target_id AS feature_key, transaction_id, starts_at, expires_at, unlocked_at AS created_at
             FROM user_unlocks
             WHERE user_id = :user_id AND unlock_type = "feature"
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countFeatureEntitlements(string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_unlocks WHERE user_id = :user_id AND unlock_type = "feature"');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
