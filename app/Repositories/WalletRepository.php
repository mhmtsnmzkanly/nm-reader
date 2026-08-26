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
            'SELECT id, name, coin_amount, bonus_coin, display_price, currency, is_active, sort_order, created_at, updated_at
             FROM shop_packages
             WHERE id = :id
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

    public function listPackages(int $page, int $perPage, bool $activeOnly = false): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $stmt = $this->pdo->prepare(
            "SELECT id, name, coin_amount, bonus_coin, display_price, currency, is_active, sort_order, created_at, updated_at
             FROM shop_packages
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
        $sql = $activeOnly ? 'SELECT COUNT(*) FROM shop_packages WHERE is_active = 1' : 'SELECT COUNT(*) FROM shop_packages';
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function createPackage(string $name, int $coinAmount, int $bonusCoin, string $displayPrice, string $currency, bool $isActive, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shop_packages (name, coin_amount, bonus_coin, display_price, currency, is_active, sort_order, created_at, updated_at)
             VALUES (:name, :coin_amount, :bonus_coin, :display_price, :currency, :is_active, :sort_order, NOW(), NOW())'
        );
        $stmt->execute([
            'name' => $name,
            'coin_amount' => $coinAmount,
            'bonus_coin' => $bonusCoin,
            'display_price' => $displayPrice,
            'currency' => $currency,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePackage(int $id, string $name, int $coinAmount, int $bonusCoin, string $displayPrice, string $currency, bool $isActive, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE shop_packages
             SET name = :name,
                 coin_amount = :coin_amount,
                 bonus_coin = :bonus_coin,
                 display_price = :display_price,
                 currency = :currency,
                 is_active = :is_active,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'coin_amount' => $coinAmount,
            'bonus_coin' => $bonusCoin,
            'display_price' => $displayPrice,
            'currency' => $currency,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);
    }

    public function upsertSeriesPricing(string $contentId, int $priceCoin, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO series_access_products (content_id, price_coin, is_active, updated_at)
             VALUES (:content_id, :price_coin, :is_active, NOW())
             ON DUPLICATE KEY UPDATE price_coin = VALUES(price_coin), is_active = VALUES(is_active), updated_at = NOW()'
        );
        $stmt->execute([
            'content_id' => $contentId,
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
            'SELECT price_coin
             FROM series_access_products
             WHERE content_id = :content_id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['content_id' => $contentId]);
        $value = $stmt->fetchColumn();
        return $value === false ? 0 : max(0, (int) $value);
    }

    public function getChapterPrice(string $chapterId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT price_amount
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
            'SELECT price_amount, price_last_update
             FROM chapters
             WHERE id = :chapter_id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['chapter_id' => $chapterId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['price_coin' => 0, 'price_last_update' => null];
        }

        return [
            'price_coin' => max(0, (int) ($row['price_amount'] ?? 0)),
            'price_last_update' => $row['price_last_update'] ?? null,
        ];
    }

    public function hasSeriesPricing(string $contentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM series_access_products WHERE content_id = :content_id AND is_active = 1 AND price_coin > 0 LIMIT 1');
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
        $stmt = $this->pdo->prepare('SELECT 1 FROM user_series_unlocks WHERE user_id = :user_id AND content_id = :content_id LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'content_id' => $contentId]);
        return $stmt->fetchColumn() !== false;
    }

    public function hasChapterUnlock(string $userId, string $chapterId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM user_chapter_unlocks WHERE user_id = :user_id AND chapter_id = :chapter_id LIMIT 1');
        $stmt->execute(['user_id' => $userId, 'chapter_id' => $chapterId]);
        return $stmt->fetchColumn() !== false;
    }

    public function createSeriesUnlock(string $userId, string $contentId, int $priceCoin, ?int $transactionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_series_unlocks (user_id, content_id, price_coin, transaction_id, unlocked_at)
             VALUES (:user_id, :content_id, :price_coin, :transaction_id, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
            'price_coin' => $priceCoin,
            'transaction_id' => $transactionId,
        ]);

        try {
            $this->pdo->prepare(
                'INSERT INTO user_unlocks (user_id, unlock_type, target_id, content_id, price_coin, transaction_id, unlocked_at)
                 VALUES (:user_id, "series", :target_id, :content_id, :price_coin, :transaction_id, NOW())
                 ON DUPLICATE KEY UPDATE price_coin = VALUES(price_coin)'
            )->execute([
                'user_id' => $userId,
                'target_id' => $contentId,
                'content_id' => $contentId,
                'price_coin' => $priceCoin,
                'transaction_id' => $transactionId,
            ]);
        } catch (\Throwable) {}
    }

    public function createChapterUnlock(string $userId, string $chapterId, string $contentId, int $priceCoin, ?int $transactionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_chapter_unlocks (user_id, chapter_id, content_id, price_coin, transaction_id, unlocked_at)
             VALUES (:user_id, :chapter_id, :content_id, :price_coin, :transaction_id, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'chapter_id' => $chapterId,
            'content_id' => $contentId,
            'price_coin' => $priceCoin,
            'transaction_id' => $transactionId,
        ]);

        try {
            $this->pdo->prepare(
                'INSERT INTO user_unlocks (user_id, unlock_type, target_id, content_id, price_coin, transaction_id, unlocked_at)
                 VALUES (:user_id, "chapter", :target_id, :content_id, :price_coin, :transaction_id, NOW())
                 ON DUPLICATE KEY UPDATE price_coin = VALUES(price_coin)'
            )->execute([
                'user_id' => $userId,
                'target_id' => $chapterId,
                'content_id' => $contentId,
                'price_coin' => $priceCoin,
                'transaction_id' => $transactionId,
            ]);
        } catch (\Throwable) {}
    }

    public function listSeriesUnlocks(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.content_id, s.title AS content_title, s.slug AS content_slug, s.type AS content_type, u.price_coin, u.transaction_id, u.unlocked_at
             FROM user_series_unlocks u
             INNER JOIN series s ON s.id = u.content_id
             WHERE u.user_id = :user_id
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
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_series_unlocks WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function listChapterUnlocks(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.content_id, u.chapter_id, s.title AS content_title, s.slug AS content_slug, s.type AS content_type,
                    ch.chapter_number, ch.title AS chapter_title, u.price_coin, u.transaction_id, u.unlocked_at
             FROM user_chapter_unlocks u
             INNER JOIN series s ON s.id = u.content_id
             INNER JOIN chapters ch ON ch.id = u.chapter_id
             WHERE u.user_id = :user_id
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
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_chapter_unlocks WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function upsertFeatureProduct(string $featureKey, string $name, int $coinPrice, int $durationDays, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO site_feature_products (feature_key, name, coin_price, duration_days, is_active, updated_at)
             VALUES (:feature_key, :name, :coin_price, :duration_days, :is_active, NOW())
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
            'SELECT feature_key, name, coin_price, duration_days, is_active, updated_at
             FROM site_feature_products
             WHERE feature_key = :feature_key
             LIMIT 1'
        );
        $stmt->execute(['feature_key' => $featureKey]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listFeatureProducts(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $stmt = $this->pdo->query(
            "SELECT feature_key, name, coin_price, duration_days, is_active, updated_at
             FROM site_feature_products
             {$where}
             ORDER BY feature_key ASC"
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
            'INSERT INTO user_feature_entitlements (
                user_id, feature_key, source_type, source_id, transaction_id, starts_at, expires_at, created_at
             ) VALUES (
                :user_id, :feature_key, :source_type, :source_id, :transaction_id, :starts_at, :expires_at, NOW()
             )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'feature_key' => $featureKey,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'transaction_id' => $transactionId,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        try {
            $this->pdo->prepare(
                'INSERT INTO user_unlocks (user_id, unlock_type, target_id, content_id, price_coin, transaction_id, starts_at, expires_at, unlocked_at)
                 VALUES (:user_id, "feature", :target_id, NULL, 0, :transaction_id, :starts_at, :expires_at, NOW())
                 ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)'
            )->execute([
                'user_id' => $userId,
                'target_id' => $featureKey,
                'transaction_id' => $transactionId,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);
        } catch (\Throwable) {}

        return $id;
    }

    public function getLatestActiveFeatureEntitlement(string $userId, string $featureKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, feature_key, source_type, source_id, transaction_id, starts_at, expires_at, created_at
             FROM user_feature_entitlements
             WHERE user_id = :user_id
               AND feature_key = :feature_key
               AND expires_at > NOW()
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
            'SELECT id, feature_key, source_type, source_id, transaction_id, starts_at, expires_at, created_at
             FROM user_feature_entitlements
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

    public function countFeatureEntitlements(string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_feature_entitlements WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
