<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Config;
use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Services\AnalyticsAggregationService;
use App\Services\BackupService;
use App\Services\CacheService;
use App\Services\QueueService;
use App\Services\RetentionService;
use App\Services\SeriesService;
use App\Services\SlugService;
use App\Services\SitemapService;

use App\Services\Admin\AdminConsoleServiceBase;

/** Domain service extracted from the legacy admin console service. */
final class AdminUserService extends AdminConsoleServiceBase
{
    public function listUsers(int $page, int $perPage, string $query = '', ?string $status = null, ?string $role = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listUsers($page, $perPage, $query, $status, $role, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['username', 'bio']);
    
        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function listAllUsersForSelect(): array
    {
        $items = $this->repo->listAllUsersForSelect();
        return OutputSanitizer::sanitizeRows($items, ['username']);
    }

    public function recordViolation(string $userId, array $payload, string $moderatorId): array
    {
        $targetType = strtolower(trim((string) ($payload['target_type'] ?? '')));
        if (!in_array($targetType, ['series', 'chapter', 'blog', 'comment', 'system'], true)) {
            throw new \InvalidArgumentException('Invalid violation target type');
        }

        $targetId = trim((string) ($payload['target_id'] ?? ''));
        if ($targetId === '' || strlen($targetId) > 32) {
            throw new \InvalidArgumentException('Invalid violation target id');
        }

        $scope = strtolower(trim((string) ($payload['scope'] ?? match ($targetType) {
            'comment' => 'comment',
            'blog' => 'blog',
            default => 'general',
        })));
        if (!in_array($scope, ['general', 'comment', 'blog'], true)) {
            throw new \InvalidArgumentException('Invalid violation scope');
        }
        if (($targetType === 'comment' && $scope !== 'comment')
            || ($targetType === 'blog' && $scope !== 'blog')
            || (in_array($targetType, ['series', 'chapter', 'system'], true) && $scope !== 'general')) {
            throw new \InvalidArgumentException('Violation scope does not match the target type');
        }

        $autoEscalate = is_bool($payload['auto_escalate'] ?? null)
            ? (bool) $payload['auto_escalate']
            : in_array(strtolower(trim((string) ($payload['auto_escalate'] ?? ''))), ['1', 'true', 'yes', 'on'], true);
        $level = strtolower(trim((string) ($payload['level'] ?? ($autoEscalate ? 'warning' : ''))));
        if (!in_array($level, AdminConsoleServiceBase::BAN_LEVELS, true)) {
            throw new \InvalidArgumentException('Invalid violation level');
        }

        $reason = Validator::sanitizeMultilineText((string) ($payload['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new \InvalidArgumentException('Violation reason is required and must be at most 1000 characters');
        }

        $endsAt = trim((string) ($payload['ends_at'] ?? ''));
        if ($endsAt !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $endsAt)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endsAt);
            if (!$date || $date <= new \DateTimeImmutable('now')) {
                throw new \InvalidArgumentException('ends_at must be a future date');
            }
            $endsAt = $date->format('Y-m-d H:i:s');
        } else {
            $endsAt = null;
        }

        $rootId = trim((string) (Config::getSettings()['app']['root_user'] ?? ''));
        // Auto escalation can turn a nominal warning into a permanent
        // restriction, so protect the moderator/ROOT_USER before the
        // repository calculates the effective level.
        if (($autoEscalate || in_array($level, ['temporary', 'permanent'], true))
            && ($userId === $moderatorId || ($rootId !== '' && $userId === $rootId))) {
            throw new \InvalidArgumentException('The ROOT_USER account and your own account cannot receive a restriction.');
        }

        return $this->repo->recordViolation(
            $userId,
            $targetType,
            $targetId,
            $scope,
            $level,
            $reason,
            $moderatorId,
            $endsAt,
            $autoEscalate
        );
    }

    public function listViolations(string $userId, int $limit = 100): array
    {
        return $this->repo->listViolations($userId, $limit);
    }

    public function updateUser(string $id, array $payload, string $moderatorId): array
    {
        $rawBanned = $payload['is_banned'] ?? false;
        $isBanned = is_bool($rawBanned)
            ? $rawBanned
            : is_scalar($rawBanned)
                && in_array(strtolower(trim((string) $rawBanned)), ['1', 'true', 'yes', 'on'], true);

        $rootId = trim((string) (Config::getSettings()['app']['root_user'] ?? ''));
        if ($isBanned && $id === $moderatorId) {
            throw new \InvalidArgumentException('You cannot ban your own account.');
        }
        if ($isBanned && $rootId !== '' && $id === $rootId) {
            throw new \InvalidArgumentException('The ROOT_USER account cannot be banned.');
        }

        if (isset($payload['email']) && $payload['email'] !== '' && !Validator::validEmail((string)$payload['email'])) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    
        $banType = strtolower(trim((string) ($payload['ban_type'] ?? 'general')));
        if (!in_array($banType, AdminConsoleServiceBase::BAN_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid ban type');
        }

        $banLevel = strtolower(trim((string) ($payload['ban_level'] ?? 'temporary')));
        if (!in_array($banLevel, AdminConsoleServiceBase::BAN_LEVELS, true)) {
            throw new \InvalidArgumentException('Invalid ban level');
        }
    
        $banReason = Validator::sanitizeMultilineText((string) ($payload['ban_reason'] ?? ''));
        if (strlen($banReason) > 1000) {
            throw new \InvalidArgumentException('ban_reason must be at most 1000 characters');
        }
    
        $banEndsAt = trim((string) ($payload['ban_ends_at'] ?? ''));
        if ($banEndsAt !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $banEndsAt)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $banEndsAt);
            if (!$date || $date <= new \DateTimeImmutable('now')) {
                throw new \InvalidArgumentException('ban_ends_at must be a future date');
            }
            $banEndsAt = $date->format('Y-m-d H:i:s');
        } else {
            $banEndsAt = null;
        }
    
        $this->repo->updateUser(
            $id,
            (string) ($payload['role'] ?? ''),
            $isBanned,
            $moderatorId,
            $payload['email'] ?? null,
            $payload['bio'] ?? null,
            $banType,
            $banReason === '' ? null : $banReason,
            $banEndsAt,
            $banLevel
        );
    
        return ['id' => $id, 'updated' => true];
    }

    public function listRbacRoles(): array
    {
        $items = $this->repo->listRolesWithPermissions();
        return $this->withMeta($items, count($items), 1, count($items));
    }

    public function listRolesWithPermissions(): array
    {
        return $this->repo->listRolesWithPermissions();
    }

    public function listRbacAssignments(int $page, int $perPage): array
    {
        $result = $this->repo->listUserRoleAssignments($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function assignPermissionToRole(array $payload, string $moderatorId): bool
    {
        $role = (string)($payload['role'] ?? '');
        $perm = (string)($payload['permission'] ?? '');
        if ($role === '' || $perm === '') throw new \InvalidArgumentException('role and permission are required');
        if (!$this->repo->roleExistsBySlug($role)) throw new \InvalidArgumentException('Unknown role');
        if (!$this->repo->permissionExistsByCode($perm)) throw new \InvalidArgumentException('Unknown permission');
        
        $this->repo->assignPermissionToRole($role, $perm, $moderatorId);
        return true;
    }

    public function revokePermissionFromRole(array $payload, string $moderatorId): bool
    {
        $role = (string)($payload['role'] ?? '');
        $perm = (string)($payload['permission'] ?? '');
        if ($role === '' || $perm === '') throw new \InvalidArgumentException('role and permission are required');
        if (!$this->repo->roleExistsBySlug($role)) throw new \InvalidArgumentException('Unknown role');
        if (!$this->repo->permissionExistsByCode($perm)) throw new \InvalidArgumentException('Unknown permission');
        if ($role === 'admin' && $perm === 'admin.panel.access') {
            throw new \InvalidArgumentException('Administrator panel access cannot be revoked');
        }
    
        return $this->repo->revokePermissionFromRole($role, $perm, $moderatorId);
    }

    public function assignRoleToUser(array $payload): bool
    {
        $userId = (string)($payload['user_id'] ?? '');
        $role = (string)($payload['role'] ?? '');
        if ($userId === '' || $role === '') throw new \InvalidArgumentException('user_id and role are required');
    
        return $this->repo->assignRoleToUser($userId, $role);
    }

    public function revokeUserSession(string $userId, string $sessionKey, string $moderatorId): void
    {
        $this->repo->revokeUserSession($userId, $sessionKey, $moderatorId);
    }

    public function ownershipCapabilities(): array
    {
        return [
            'capabilities' => [
                ['entity_type' => 'content', 'entity_label' => 'İçerik', 'action' => 'Oluşturma', 'permission' => 'admin.content.create', 'scope' => 'role'],
                ['entity_type' => 'content', 'entity_label' => 'İçerik', 'action' => 'Düzenleme / metadata', 'permission' => 'admin.content.update', 'scope' => 'role'],
                ['entity_type' => 'content', 'entity_label' => 'Yayın yaşam döngüsü', 'permission' => 'admin.content.update', 'action' => 'Taslak / yayın / arşiv', 'scope' => 'role'],
                ['entity_type' => 'chapter', 'entity_label' => 'Bölüm', 'action' => 'Oluşturma', 'permission' => 'admin.chapter.create', 'scope' => 'role'],
                ['entity_type' => 'chapter', 'entity_label' => 'Bölüm', 'action' => 'Düzenleme / silme', 'permission' => 'admin.content.update', 'scope' => 'role'],
                ['entity_type' => 'chapter', 'entity_label' => 'Bölüm fiyatı', 'action' => 'Fiyatlandırma', 'permission' => 'admin.shop.manage', 'scope' => 'role'],
                ['entity_type' => 'blog', 'entity_label' => 'Blog', 'action' => 'Oluşturma / kendi kaydını düzenleme', 'permission' => null, 'scope' => 'owner'],
                ['entity_type' => 'blog', 'entity_label' => 'Blog', 'action' => 'Gizleme / silme', 'permission' => 'admin.blog.hide', 'scope' => 'role'],
                ['entity_type' => 'comment', 'entity_label' => 'Yorum', 'action' => 'Oluşturma / kendi kaydı', 'permission' => null, 'scope' => 'owner'],
                ['entity_type' => 'comment', 'entity_label' => 'Yorum', 'action' => 'Silme', 'permission' => 'admin.comment.delete', 'scope' => 'role'],
                ['entity_type' => 'image_upload', 'entity_label' => 'Görsel yükleme', 'action' => 'Blog / profil görseli yükleme', 'permission' => null, 'scope' => 'authenticated'],
                ['entity_type' => 'image_upload', 'entity_label' => 'Görsel yükleme', 'action' => 'Yükleme', 'permission' => 'admin.content.create veya admin.content.update veya admin.chapter.create', 'scope' => 'role_any'],
                ['entity_type' => 'image_upload', 'entity_label' => 'Görsel yükleme', 'action' => 'Silme', 'permission' => 'admin.uploads.delete', 'scope' => 'role'],
                ['entity_type' => 'image_upload', 'entity_label' => 'Görsel yükleme', 'action' => 'Optimize etme', 'permission' => 'admin.uploads.optimize', 'scope' => 'role'],
            ],
            'records' => $this->repo->listCreatedEntityOwnership(150),
        ];
    }
}
