<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ChapterNumber;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use DateInterval;
use DateTimeImmutable;

final class WalletService
{
    private const FEATURE_AD_FREE = 'ad_free';

    public function __construct(
        private readonly WalletRepository $wallets,
        private readonly UserRepository $users,
        private readonly SeriesRepository $series,
        private readonly ChapterRepository $chapters,
        private readonly CacheService $cache
    ) {
    }

    public function wallet(string $userId): array
    {
        $this->assertUserExists($userId);
        $row = $this->wallets->getWallet($userId);

        return [
            'user_id' => (string) $row['user_id'],
            'balance_coin' => (int) ($row['balance_coin'] ?? 0),
            'total_coin_purchased' => (int) ($row['total_coin_purchased'] ?? 0),
            'total_coin_spent' => (int) ($row['total_coin_spent'] ?? 0),
            'features' => $this->featureStatus($userId),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function transactions(string $userId, int $page, int $perPage): array
    {
        $this->assertUserExists($userId);
        return [
            'items' => $this->wallets->listTransactions($userId, $page, $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->wallets->countTransactions($userId),
            ],
        ];
    }

    public function packages(int $page, int $perPage, bool $activeOnly = true): array
    {
        $items = $this->wallets->listPackages($page, $perPage, $activeOnly);
        $items = array_map(static function (array $row): array {
            $row['total_coin'] = (int) ($row['coin_amount'] ?? 0) + (int) ($row['bonus_coin'] ?? 0);
            return $row;
        }, $items);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->wallets->countPackages($activeOnly),
            ],
        ];
    }

    public function featureProducts(bool $activeOnly = true): array
    {
        return $this->wallets->listFeatureProducts($activeOnly);
    }

    public function createPackage(array $payload, string $moderatorId): array
    {
        $data = $this->normalizePackagePayload($payload);
        $id = $this->wallets->createPackage(
            $data['name'],
            $data['coin_amount'],
            $data['bonus_coin'],
            $data['display_price'],
            $data['currency'],
            $data['is_active'],
            $data['sort_order']
        );
        $this->recordAdminAction($moderatorId, 'system', (string) $id, 'package_create', 'Created shop package');

        return ['id' => $id] + $data + ['total_coin' => $data['coin_amount'] + $data['bonus_coin']];
    }

    public function updatePackage(int $id, array $payload, string $moderatorId): array
    {
        $data = $this->normalizePackagePayload($payload);
        $this->wallets->updatePackage(
            $id,
            $data['name'],
            $data['coin_amount'],
            $data['bonus_coin'],
            $data['display_price'],
            $data['currency'],
            $data['is_active'],
            $data['sort_order']
        );
        $this->recordAdminAction($moderatorId, 'system', (string) $id, 'package_update', 'Updated shop package');

        return ['id' => $id] + $data + ['total_coin' => $data['coin_amount'] + $data['bonus_coin']];
    }

    public function creditCoins(string $targetUserId, int $amount, string $reason, string $moderatorId): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be greater than zero');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('reason is required');
        }

        $this->assertUserExists($targetUserId);
        $pdo = $this->wallets->getPdo();
        $pdo->beginTransaction();

        try {
            $wallet = $this->wallets->getWalletForUpdate($targetUserId);
            $newBalance = (int) $wallet['balance_coin'] + $amount;
            $newPurchased = (int) $wallet['total_coin_purchased'] + $amount;
            $this->wallets->updateWalletBalances($targetUserId, $newBalance, $newPurchased, (int) $wallet['total_coin_spent']);
            $transactionId = $this->wallets->createTransaction(
                $targetUserId,
                'manual_credit',
                $amount,
                $newBalance,
                'admin_wallet',
                $targetUserId,
                $reason,
                ['source' => 'admin_manual_credit'],
                $moderatorId
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAdminAction($moderatorId, 'user', $targetUserId, 'wallet_credit', $reason);

        return [
            'transaction_id' => $transactionId,
            'wallet' => $this->wallet($targetUserId),
        ];
    }

    public function debitCoins(string $targetUserId, int $amount, string $reason, string $moderatorId): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be greater than zero');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('reason is required');
        }

        $this->assertUserExists($targetUserId);
        $pdo = $this->wallets->getPdo();
        $pdo->beginTransaction();

        try {
            $wallet = $this->wallets->getWalletForUpdate($targetUserId);
            $currentBalance = (int) $wallet['balance_coin'];
            if ($currentBalance < $amount) {
                throw new \DomainException('Insufficient coin balance');
            }
            $newBalance = $currentBalance - $amount;
            $this->wallets->updateWalletBalances($targetUserId, $newBalance, (int) $wallet['total_coin_purchased'], (int) $wallet['total_coin_spent']);
            $transactionId = $this->wallets->createTransaction(
                $targetUserId,
                'manual_debit',
                -$amount,
                $newBalance,
                'admin_wallet',
                $targetUserId,
                $reason,
                ['source' => 'admin_manual_debit'],
                $moderatorId
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAdminAction($moderatorId, 'user', $targetUserId, 'wallet_debit', $reason);

        return [
            'transaction_id' => $transactionId,
            'wallet' => $this->wallet($targetUserId),
        ];
    }

    public function grantPackageToUser(string $targetUserId, int $packageId, ?string $cashAmount, string $reason, string $moderatorId): array
    {
        $this->assertUserExists($targetUserId);
        $pkg = $this->wallets->findPackageById($packageId);
        if ($pkg === null) {
            throw new \DomainException('Package not found');
        }
        if (!(bool) ($pkg['is_active'] ?? false)) {
            throw new \DomainException('Package is inactive');
        }

        $coinAmount = (int) ($pkg['coin_amount'] ?? 0) + (int) ($pkg['bonus_coin'] ?? 0);
        if ($coinAmount <= 0) {
            throw new \InvalidArgumentException('Package has no coin value');
        }

        $description = trim($reason) !== '' ? $reason : sprintf('Applied package %s', (string) ($pkg['name'] ?? $packageId));
        $pdo = $this->wallets->getPdo();
        $pdo->beginTransaction();
        try {
            $wallet = $this->wallets->getWalletForUpdate($targetUserId);
            $newBalance = (int) $wallet['balance_coin'] + $coinAmount;
            $newPurchased = (int) $wallet['total_coin_purchased'] + $coinAmount;
            $this->wallets->updateWalletBalances($targetUserId, $newBalance, $newPurchased, (int) $wallet['total_coin_spent']);
            $transactionId = $this->wallets->createTransaction(
                $targetUserId,
                'package_credit',
                $coinAmount,
                $newBalance,
                'shop_package',
                (string) $packageId,
                $description,
                [
                    'package_name' => (string) ($pkg['name'] ?? ''),
                    'cash_amount' => $cashAmount,
                    'display_price' => (string) ($pkg['display_price'] ?? '0.00'),
                    'currency' => (string) ($pkg['currency'] ?? 'TRY'),
                ],
                $moderatorId
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAdminAction($moderatorId, 'user', $targetUserId, 'wallet_package_credit', $description);

        return [
            'transaction_id' => $transactionId,
            'package_id' => $packageId,
            'credited_coin' => $coinAmount,
            'wallet' => $this->wallet($targetUserId),
        ];
    }

    public function updateSeriesPricing(string $contentId, array $payload, string $moderatorId): array
    {
        $content = $this->findSeriesById($contentId);
        if ($content === null) {
            throw new \DomainException('Content not found');
        }

        $priceCoin = max(0, (int) ($payload['price_coin'] ?? 0));
        $isActive = (bool) ($payload['is_active'] ?? true);
        $this->wallets->upsertSeriesPricing($contentId, $priceCoin, $isActive);
        $this->invalidateContentCaches((string) $content['type'], (string) $content['slug']);
        $this->recordAdminAction($moderatorId, 'series', $contentId, 'pricing_update', sprintf('Updated series pricing to %d coin', $priceCoin));

        return [
            'content_id' => $contentId,
            'price_coin' => $priceCoin,
            'is_active' => $isActive,
        ];
    }

    public function updateChapterPricing(string $chapterId, array $payload, string $moderatorId): array
    {
        $chapter = $this->chapters->findById($chapterId);
        if ($chapter === null) {
            throw new \DomainException('Chapter not found');
        }

        $priceCoin = max(0, (int) ($payload['price_coin'] ?? 0));
        $isActive = (bool) ($payload['is_active'] ?? true);
        $this->wallets->upsertChapterPricing($chapterId, $priceCoin, $isActive);

        $content = $this->findSeriesById((string) $chapter['content_id']);
        if ($content !== null) {
            $this->invalidateContentCaches((string) $content['type'], (string) $content['slug']);
        }

        $this->recordAdminAction($moderatorId, 'chapter', $chapterId, 'pricing_update', sprintf('Updated chapter pricing to %d coin', $priceCoin));

        return [
            'chapter_id' => $chapterId,
            'content_id' => (string) $chapter['content_id'],
            'price_coin' => $priceCoin,
            'is_active' => $isActive,
        ];
    }

    public function unlockSeries(string $userId, string $typeSegment, string $slug): array
    {
        $content = $this->series->findContentByTypeAndSlug($this->toDbType($typeSegment), $slug, $userId);
        if ($content === null) {
            throw new \DomainException('Content not found');
        }

        $contentId = (string) $content['id'];
        $priceCoin = $this->wallets->getSeriesPrice($contentId);
        $access = $this->contentAccess($contentId, $userId);
        if (($access['is_series_unlocked'] ?? false) === true) {
            return [
                'content_id' => $contentId,
                'already_unlocked' => true,
                'charged_coin' => 0,
                'wallet' => $this->wallet($userId),
            ];
        }
        if ($priceCoin <= 0) {
            return [
                'content_id' => $contentId,
                'already_unlocked' => true,
                'charged_coin' => 0,
                'wallet' => $this->wallet($userId),
            ];
        }

        $pdo = $this->wallets->getPdo();
        $pdo->beginTransaction();
        try {
            $wallet = $this->wallets->getWalletForUpdate($userId);
            if ($this->wallets->hasSeriesUnlock($userId, $contentId)) {
                $pdo->commit();
                return [
                    'content_id' => $contentId,
                    'already_unlocked' => true,
                    'charged_coin' => 0,
                    'wallet' => $this->wallet($userId),
                ];
            }
            $balance = (int) $wallet['balance_coin'];
            if ($balance < $priceCoin) {
                throw new \DomainException('Insufficient coin balance');
            }
            $newBalance = $balance - $priceCoin;
            $this->wallets->updateWalletBalances(
                $userId,
                $newBalance,
                (int) $wallet['total_coin_purchased'],
                (int) $wallet['total_coin_spent'] + $priceCoin
            );
            $transactionId = $this->wallets->createTransaction(
                $userId,
                'series_unlock',
                -$priceCoin,
                $newBalance,
                'series',
                $contentId,
                sprintf('Unlocked series %s', (string) ($content['title'] ?? $contentId)),
                ['slug' => $slug, 'type' => $typeSegment]
            );
            $this->wallets->createSeriesUnlock($userId, $contentId, $priceCoin, $transactionId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'content_id' => $contentId,
            'already_unlocked' => false,
            'charged_coin' => $priceCoin,
            'wallet' => $this->wallet($userId),
        ];
    }

    public function unlockChapter(string $userId, string $chapterId): array
    {
        $chapter = $this->chapters->findById($chapterId);
        if ($chapter === null) {
            throw new \DomainException('Chapter not found');
        }

        $contentId = (string) $chapter['content_id'];
        $access = $this->chapterAccess($contentId, $chapterId, $userId);
        if (($access['granted'] ?? false) === true) {
            return [
                'chapter_id' => $chapterId,
                'content_id' => $contentId,
                'already_unlocked' => true,
                'charged_coin' => 0,
                'wallet' => $this->wallet($userId),
            ];
        }

        $priceCoin = (int) ($access['chapter_unlock_price'] ?? 0);
        if ($priceCoin <= 0) {
            throw new \DomainException('Chapter is not individually unlockable');
        }

        $pdo = $this->wallets->getPdo();
        $pdo->beginTransaction();
        try {
            $wallet = $this->wallets->getWalletForUpdate($userId);
            if ($this->wallets->hasSeriesUnlock($userId, $contentId) || $this->wallets->hasChapterUnlock($userId, $chapterId)) {
                $pdo->commit();
                return [
                    'chapter_id' => $chapterId,
                    'content_id' => $contentId,
                    'already_unlocked' => true,
                    'charged_coin' => 0,
                    'wallet' => $this->wallet($userId),
                ];
            }
            $balance = (int) $wallet['balance_coin'];
            if ($balance < $priceCoin) {
                throw new \DomainException('Insufficient coin balance');
            }
            $newBalance = $balance - $priceCoin;
            $this->wallets->updateWalletBalances(
                $userId,
                $newBalance,
                (int) $wallet['total_coin_purchased'],
                (int) $wallet['total_coin_spent'] + $priceCoin
            );
            $transactionId = $this->wallets->createTransaction(
                $userId,
                'chapter_unlock',
                -$priceCoin,
                $newBalance,
                'chapter',
                $chapterId,
                sprintf('Unlocked chapter %s', ChapterNumber::normalize((string) ($chapter['chapter_number'] ?? $chapterId))),
                ['content_id' => $contentId]
            );
            $this->wallets->createChapterUnlock($userId, $chapterId, $contentId, $priceCoin, $transactionId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'chapter_id' => $chapterId,
            'content_id' => $contentId,
            'already_unlocked' => false,
            'charged_coin' => $priceCoin,
            'wallet' => $this->wallet($userId),
        ];
    }

    public function contentAccess(string $contentId, ?string $userId): array
    {
        $seriesUnlockPrice = $this->wallets->getSeriesPrice($contentId);
        $hasPremiumChapters = $this->wallets->hasPremiumChapters($contentId);
        $isSeriesUnlocked = $userId !== null && $userId !== '' ? $this->wallets->hasSeriesUnlock($userId, $contentId) : false;

        return [
            'series_unlock_price' => $seriesUnlockPrice,
            'is_series_unlocked' => $isSeriesUnlocked,
            'has_any_premium' => $seriesUnlockPrice > 0 || $hasPremiumChapters,
        ];
    }

    public function chapterAccess(string $contentId, string $chapterId, ?string $userId): array
    {
        $seriesPrice = $this->wallets->getSeriesPrice($contentId);
        $chapterPrice = $this->wallets->getChapterPrice($chapterId);
        $seriesUnlocked = $userId !== null && $userId !== '' ? $this->wallets->hasSeriesUnlock($userId, $contentId) : false;
        $chapterUnlocked = $userId !== null && $userId !== '' ? $this->wallets->hasChapterUnlock($userId, $chapterId) : false;

        $granted = $seriesUnlocked || $chapterUnlocked || ($seriesPrice <= 0 && $chapterPrice <= 0);
        $requiresSeriesUnlock = $seriesPrice > 0 && !$seriesUnlocked;
        $requiresChapterUnlock = $chapterPrice > 0 && !$chapterUnlocked;

        return [
            'granted' => $granted,
            'reason' => $granted ? 'granted' : ($requiresChapterUnlock ? 'chapter_unlock_required' : 'series_unlock_required'),
            'series_unlock_price' => $seriesPrice,
            'chapter_unlock_price' => $chapterPrice,
            'is_series_unlocked' => $seriesUnlocked,
            'is_chapter_unlocked' => $chapterUnlocked,
            'is_free' => $seriesPrice <= 0 && $chapterPrice <= 0,
            'requires_series_unlock' => $requiresSeriesUnlock,
            'requires_chapter_unlock' => $requiresChapterUnlock,
        ];
    }

    public function seriesUnlocks(string $userId, int $page, int $perPage): array
    {
        return [
            'items' => $this->wallets->listSeriesUnlocks($userId, $page, $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->wallets->countSeriesUnlocks($userId),
            ],
        ];
    }

    public function chapterUnlocks(string $userId, int $page, int $perPage): array
    {
        return [
            'items' => $this->wallets->listChapterUnlocks($userId, $page, $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->wallets->countChapterUnlocks($userId),
            ],
        ];
    }

    public function featureEntitlements(string $userId, int $page, int $perPage): array
    {
        return [
            'items' => $this->wallets->listFeatureEntitlements($userId, $page, $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->wallets->countFeatureEntitlements($userId),
            ],
        ];
    }

    public function configureAdFree(array $payload, string $moderatorId): array
    {
        $coinPrice = max(0, (int) ($payload['coin_price'] ?? 0));
        $durationDays = max(1, (int) ($payload['duration_days'] ?? 30));
        $isActive = (bool) ($payload['is_active'] ?? true);
        $name = trim((string) ($payload['name'] ?? 'Reklamsiz Deneyim'));
        if ($name === '') {
            $name = 'Reklamsiz Deneyim';
        }

        $this->wallets->upsertFeatureProduct(self::FEATURE_AD_FREE, mb_substr($name, 0, 120), $coinPrice, $durationDays, $isActive);
        $this->recordAdminAction($moderatorId, 'system', self::FEATURE_AD_FREE, 'feature_update', sprintf('Updated ad-free plan to %d coin / %d day', $coinPrice, $durationDays));

        return $this->adFreeProduct();
    }

    public function adFreeProduct(): array
    {
        $product = $this->wallets->getFeatureProduct(self::FEATURE_AD_FREE);
        if ($product === null) {
            return [
                'feature_key' => self::FEATURE_AD_FREE,
                'name' => 'Reklamsiz Deneyim',
                'coin_price' => 0,
                'duration_days' => 30,
                'is_active' => false,
            ];
        }
        return $product;
    }

    public function purchaseAdFree(string $userId): array
    {
        $this->assertUserExists($userId);
        $product = $this->wallets->getFeatureProduct(self::FEATURE_AD_FREE);
        if ($product === null || !(bool) ($product['is_active'] ?? false)) {
            throw new \DomainException('Ad-free product is unavailable');
        }

        $coinPrice = (int) ($product['coin_price'] ?? 0);
        $durationDays = max(1, (int) ($product['duration_days'] ?? 30));
        if ($coinPrice <= 0) {
            throw new \DomainException('Ad-free product price is invalid');
        }

        $pdo = $this->wallets->getPdo();
        $pdo->beginTransaction();
        try {
            $wallet = $this->wallets->getWalletForUpdate($userId);
            $balance = (int) $wallet['balance_coin'];
            if ($balance < $coinPrice) {
                throw new \DomainException('Insufficient coin balance');
            }

            $newBalance = $balance - $coinPrice;
            $this->wallets->updateWalletBalances(
                $userId,
                $newBalance,
                (int) $wallet['total_coin_purchased'],
                (int) $wallet['total_coin_spent'] + $coinPrice
            );

            $transactionId = $this->wallets->createTransaction(
                $userId,
                'feature_unlock',
                -$coinPrice,
                $newBalance,
                'feature',
                self::FEATURE_AD_FREE,
                sprintf('Purchased ad-free access for %d day(s)', $durationDays),
                ['duration_days' => $durationDays]
            );

            $now = new DateTimeImmutable('now');
            $current = $this->wallets->getLatestActiveFeatureEntitlement($userId, self::FEATURE_AD_FREE);
            $startsAt = $current !== null ? new DateTimeImmutable((string) $current['expires_at']) : $now;
            if ($startsAt < $now) {
                $startsAt = $now;
            }
            $expiresAt = $startsAt->add(new DateInterval(sprintf('P%dD', $durationDays)));
            $entitlementId = $this->wallets->createFeatureEntitlement(
                $userId,
                self::FEATURE_AD_FREE,
                'feature_product',
                self::FEATURE_AD_FREE,
                $transactionId,
                $startsAt->format('Y-m-d H:i:s'),
                $expiresAt->format('Y-m-d H:i:s')
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'transaction_id' => $transactionId,
            'entitlement_id' => $entitlementId,
            'feature' => $this->featureStatus($userId)[self::FEATURE_AD_FREE],
            'wallet' => $this->wallet($userId),
        ];
    }

    public function featureStatus(string $userId): array
    {
        $adFree = $this->wallets->getLatestActiveFeatureEntitlement($userId, self::FEATURE_AD_FREE);

        return [
            self::FEATURE_AD_FREE => [
                'feature_key' => self::FEATURE_AD_FREE,
                'active' => $adFree !== null,
                'expires_at' => $adFree['expires_at'] ?? null,
                'starts_at' => $adFree['starts_at'] ?? null,
            ],
        ];
    }

    private function normalizePackagePayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        $coinAmount = max(0, (int) ($payload['coin_amount'] ?? 0));
        $bonusCoin = max(0, (int) ($payload['bonus_coin'] ?? 0));
        if ($coinAmount <= 0) {
            throw new \InvalidArgumentException('coin_amount must be greater than zero');
        }

        $displayPrice = number_format(max(0, (float) ($payload['display_price'] ?? 0)), 2, '.', '');
        $currency = strtoupper(substr(trim((string) ($payload['currency'] ?? 'TRY')), 0, 3));
        $sortOrder = (int) ($payload['sort_order'] ?? 0);

        return [
            'name' => mb_substr($name, 0, 120),
            'coin_amount' => $coinAmount,
            'bonus_coin' => $bonusCoin,
            'display_price' => $displayPrice,
            'currency' => $currency === '' ? 'TRY' : $currency,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'sort_order' => $sortOrder,
        ];
    }

    private function assertUserExists(string $userId): void
    {
        if ($this->users->findById($userId) === null) {
            throw new \DomainException('User not found');
        }
    }

    private function findSeriesById(string $contentId): ?array
    {
        $stmt = $this->wallets->getPdo()->prepare(
            'SELECT id, slug, type, title
             FROM series
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $contentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function recordAdminAction(?string $moderatorId, string $targetType, string $targetId, string $action, string $reason): void
    {
        if ($moderatorId === null || $moderatorId === '') {
            return;
        }

        $stmt = $this->wallets->getPdo()->prepare(
            'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
             VALUES (:moderator_user_id, :target_type, :target_id, :action, :reason, NOW())'
        );
        $stmt->execute([
            'moderator_user_id' => $moderatorId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'reason' => $reason,
        ]);
    }

    private function invalidateContentCaches(string $dbType, string $slug): void
    {
        $typeSegment = str_replace('_', '-', $dbType);
        foreach (['anon'] as $audience) {
            $this->cache->delete(sprintf('content_%s_%s_%s', $dbType, $slug, $audience));
        }
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $dbType, $slug));
        $this->cache->delete(sprintf('type_list_%s_1_20', $dbType));
        $this->cache->delete(sprintf('content_%s_%s_anon', $dbType, $slug));
        $this->cache->delete(sprintf('content_%s_%s', $typeSegment, $slug));
    }

    private function toDbType(string $typeSegment): string
    {
        return match (strtolower(trim($typeSegment))) {
            'light-novel' => 'light_novel',
            'web-novel' => 'web_novel',
            'novel' => 'novel',
            'manga' => 'manga',
            'manhua' => 'manhua',
            'manhwa' => 'manhwa',
            'webtoon' => 'webtoon',
            default => throw new \DomainException('Invalid content type'),
        };
    }
}
