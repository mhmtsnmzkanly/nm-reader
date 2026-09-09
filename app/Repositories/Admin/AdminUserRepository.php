<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminUserRepository extends AdminRepositoryBase
{
    public function listUsers(int $page, int $perPage, string $query = '', ?string $accountStatus = null, ?string $role = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(u.id LIKE :query_id OR u.username LIKE :query_username OR u.email LIKE :query_email)';
            $queryValue = '%' . $query . '%';
            $params['query_id'] = $queryValue;
            $params['query_username'] = $queryValue;
            $params['query_email'] = $queryValue;
        }
        if (in_array($accountStatus, ['active', 'banned'], true)) {
            $exists = 'EXISTS (
                SELECT 1
                FROM bans ban_filter
                WHERE ban_filter.user_id = u.id
                  AND ban_filter.revoked_at IS NULL
                  AND ban_filter.level IN ("temporary", "permanent")
                  AND (ban_filter.ends_at IS NULL OR ban_filter.ends_at > NOW())
            )';
            $where[] = $accountStatus === 'banned' ? $exists : 'NOT ' . $exists;
        }
        $roleId = (string) ((\App\Config::getSettings()['rbac']['id_map'][$role] ?? ''));
        if ($roleId !== '') {
            $where[] = 'FIND_IN_SET(:role_id, IFNULL(u.roles, "")) > 0';
            $params['role_id'] = $roleId;
        }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $orderBy = match ($sort) {
            'oldest' => 'u.created_at ASC',
            'username' => 'u.username ASC',
            default => 'u.created_at DESC',
        };
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM users u' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    
        $stmt = $this->pdo->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.bio,
                u.roles,
                u.created_at,
                EXISTS(
                    SELECT 1
                    FROM bans active_ban
                    WHERE active_ban.user_id = u.id
                      AND active_ban.revoked_at IS NULL
                      AND active_ban.level IN ("temporary", "permanent")
                      AND (active_ban.ends_at IS NULL OR active_ban.ends_at > NOW())
                ) AS is_banned,
                (
                    SELECT active_ban.type
                    FROM bans active_ban
                    WHERE active_ban.user_id = u.id
                      AND active_ban.revoked_at IS NULL
                      AND (active_ban.ends_at IS NULL OR active_ban.ends_at > NOW())
                    ORDER BY active_ban.created_at DESC
                    LIMIT 1
                ) AS ban_type,
                (
                    SELECT active_ban.level
                    FROM bans active_ban
                    WHERE active_ban.user_id = u.id
                      AND active_ban.revoked_at IS NULL
                      AND (active_ban.ends_at IS NULL OR active_ban.ends_at > NOW())
                    ORDER BY active_ban.created_at DESC
                    LIMIT 1
                ) AS ban_level,
                (
                    SELECT active_ban.reason
                    FROM bans active_ban
                    WHERE active_ban.user_id = u.id
                      AND active_ban.revoked_at IS NULL
                      AND (active_ban.ends_at IS NULL OR active_ban.ends_at > NOW())
                    ORDER BY active_ban.created_at DESC
                    LIMIT 1
                ) AS ban_reason,
                (
                    SELECT active_ban.ends_at
                    FROM bans active_ban
                    WHERE active_ban.user_id = u.id
                      AND active_ban.revoked_at IS NULL
                      AND (active_ban.ends_at IS NULL OR active_ban.ends_at > NOW())
                    ORDER BY active_ban.created_at DESC
                    LIMIT 1
                ) AS ban_ends_at,
                (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comment_count,
                (SELECT COUNT(*) FROM blogs b WHERE b.user_id = u.id) AS blog_count,
                (SELECT COUNT(*) FROM user_series_follows f WHERE f.user_id = u.id) AS follow_count,
                (SELECT COALESCE(SUM(duration_seconds), 0) FROM user_activity ua WHERE ua.user_id = u.id) AS total_seconds
             FROM users u' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
    
        // Map role IDs to slugs using static config
        $config = \App\Config::getSettings()['rbac'] ?? [];
        $idToSlug = array_flip((array) ($config['id_map'] ?? []));
    
        foreach ($items as &$item) {
            $ids = explode(',', (string)($item['roles'] ?? ''));
            $slugs = [];
            foreach ($ids as $id) {
                if (isset($idToSlug[$id])) {
                    $slugs[] = $idToSlug[$id];
                }
            }
            $item['role_names'] = implode(', ', $slugs);
        }
    
        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function listAllUsersForSelect(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, username, email
             FROM users
             ORDER BY username ASC, created_at DESC'
        );
    
        return $stmt->fetchAll();
    }

    public function updateUser(
        string $id,
        string $role,
        bool $isBanned,
        string $moderatorId,
        ?string $email = null,
        ?string $bio = null,
        string $banType = 'general',
        ?string $banReason = null,
        ?string $banEndsAt = null,
        string $banLevel = 'temporary'
    ): void
    {
        $this->pdo->beginTransaction();
        try {
            // Lock and validate the target before applying any partial update.
            // Without this check an unknown user ID could produce a misleading
            // successful response when only profile fields were submitted.
            $userStmt = $this->pdo->prepare(
                'SELECT id, roles
                 FROM users
                 WHERE id = :user_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $userStmt->execute(['user_id' => $id]);
            $currentUser = $userStmt->fetch();
            if (!is_array($currentUser)) {
                throw new \DomainException('User not found');
            }
            $oldRoles = (string) ($currentUser['roles'] ?? '');

            if ($email !== null || $bio !== null) {
                $parts = [];
                $params = ['id' => $id];
                if ($email !== null) {
                    $parts[] = 'email = :email';
                    $params['email'] = $email;
                }
                if ($bio !== null) {
                    $parts[] = 'bio = :bio';
                    $params['bio'] = $bio === '' ? null : $bio;
                }
    
                $sql = 'UPDATE users SET ' . implode(', ', $parts) . ' WHERE id = :id';
                $this->pdo->prepare($sql)->execute($params);
            }
    
            // Role update
            if ($role !== '') {
                $config = \App\Config::getSettings()['rbac'] ?? [];
                $idMap = (array) ($config['id_map'] ?? []);
                $roleId = (string) ($idMap[$role] ?? '');
    
                if ($roleId !== '' && $roleId !== $oldRoles) {
                    $this->pdo->prepare('UPDATE users SET roles = :role_id WHERE id = :user_id')
                         ->execute(['role_id' => $roleId, 'user_id' => $id]);
    
                    $audit = $this->pdo->prepare(
                        'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                         VALUES (:mod, "user", :uid, "role_change", :reason, NOW())'
                    );
                    $audit->execute([
                        'mod' => $moderatorId,
                        'uid' => $id,
                        'reason' => json_encode(['diff' => ['roles' => ['before' => $oldRoles, 'after' => $roleId]]])
                    ]);
                }
            }
    
            if (!in_array($banLevel, ['warning', 'removal', 'temporary', 'permanent'], true)) {
                throw new \InvalidArgumentException('Invalid ban level');
            }

            // Restriction status. Warning/removal actions are disciplinary
            // history only and do not block interaction. Active restricting
            // rows are updated together so duplicate rows cannot leave a
            // stale restriction behind.
            $stmt = $this->pdo->prepare(
                'SELECT id, type, level, reason, ends_at
                 FROM bans
                 WHERE user_id = :user_id
                   AND revoked_at IS NULL
                   AND (ends_at IS NULL OR ends_at > NOW())
                 ORDER BY created_at DESC
                 FOR UPDATE'
            );
            $stmt->execute(['user_id' => $id]);
            $activeBans = $stmt->fetchAll();
            $activeRestrictedBans = array_values(array_filter(
                $activeBans,
                static fn(array $ban): bool => in_array((string) ($ban['level'] ?? 'temporary'), ['temporary', 'permanent'], true)
            ));
            $activeBan = is_array($activeRestrictedBans[0] ?? null) ? $activeRestrictedBans[0] : null;
            $currentlyBanned = $activeRestrictedBans !== [];

            if ($isBanned && in_array($banLevel, ['temporary', 'permanent'], true) && !$currentlyBanned) {
                $this->pdo->prepare(
                    'INSERT INTO bans
                        (user_id, type, level, reason, ends_at, banned_by_user_id, created_at, updated_at)
                     VALUES
                        (:user_id, :type, :level, :reason, :ends_at, :banned_by, NOW(), NOW())'
                )->execute([
                    'user_id' => $id,
                    'type' => $banType,
                    'level' => $banLevel,
                    'reason' => $banReason ?? 'Banned by admin',
                    'ends_at' => $banEndsAt,
                    'banned_by' => $moderatorId,
                ]);
                $this->pdo->prepare(
                    'INSERT INTO admin_actions
                        (moderator_user_id, target_type, target_id, action, reason, metadata, created_at)
                     VALUES
                        (:mod, "user", :target_id, "ban", :reason, :metadata, NOW())'
                )->execute([
                    'mod' => $moderatorId,
                    'target_id' => $id,
                    'reason' => $banReason ?? 'Banned by admin',
                    'metadata' => json_encode(['level' => $banLevel, 'type' => $banType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } elseif ($isBanned && in_array($banLevel, ['temporary', 'permanent'], true) && $currentlyBanned) {
                $this->pdo->prepare(
                    'UPDATE bans
                     SET type = :type, level = :level, reason = :reason, ends_at = :ends_at, updated_at = NOW()
                     WHERE user_id = :user_id
                       AND revoked_at IS NULL
                       AND level IN ("temporary", "permanent")
                       AND (ends_at IS NULL OR ends_at > NOW())'
                )->execute([
                    'user_id' => $id,
                    'type' => $banType,
                    'level' => $banLevel,
                    'reason' => $banReason ?? 'Banned by admin',
                    'ends_at' => $banEndsAt,
                ]);

                $audit = $this->pdo->prepare(
                    'INSERT INTO admin_actions
                        (moderator_user_id, target_type, target_id, action, reason, metadata, created_at)
                     VALUES
                        (:mod, "user", :target_id, "ban_update", :reason, :metadata, NOW())'
                );
                $audit->execute([
                    'mod' => $moderatorId,
                    'target_id' => $id,
                    'reason' => $banReason ?? 'Ban updated by admin',
                    'metadata' => json_encode([
                        'active_bans_before' => count($activeBans),
                        'diff' => [
                            'type' => ['before' => (string) ($activeBan['type'] ?? ''), 'after' => $banType],
                            'level' => ['before' => (string) ($activeBan['level'] ?? 'temporary'), 'after' => $banLevel],
                            'reason' => ['before' => (string) ($activeBan['reason'] ?? ''), 'after' => $banReason],
                            'ends_at' => ['before' => $activeBan['ends_at'] ?? null, 'after' => $banEndsAt],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } elseif ($isBanned && !in_array($banLevel, ['temporary', 'permanent'], true) && $currentlyBanned) {
                $this->pdo->prepare(
                    'UPDATE bans
                     SET revoked_at = NOW(), revoked_by_user_id = :revoked_by, updated_at = NOW()
                     WHERE user_id = :user_id
                       AND revoked_at IS NULL
                       AND level IN ("temporary", "permanent")
                       AND (ends_at IS NULL OR ends_at > NOW())'
                )->execute(['user_id' => $id, 'revoked_by' => $moderatorId]);

                $this->pdo->prepare(
                    'INSERT INTO admin_actions
                        (moderator_user_id, target_type, target_id, action, reason, metadata, created_at)
                     VALUES
                        (:mod, "user", :target_id, "ban_downgrade", :reason, :metadata, NOW())'
                )->execute([
                    'mod' => $moderatorId,
                    'target_id' => $id,
                    'reason' => $banReason ?? 'Restriction downgraded to a non-blocking disciplinary action',
                    'metadata' => json_encode(['level' => $banLevel, 'type' => $banType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } elseif (!$isBanned && $currentlyBanned) {
                $this->pdo->prepare(
                    'UPDATE bans
                     SET revoked_at = NOW(), revoked_by_user_id = :revoked_by, updated_at = NOW()
                     WHERE user_id = :user_id
                       AND revoked_at IS NULL
                       AND level IN ("temporary", "permanent")
                       AND (ends_at IS NULL OR ends_at > NOW())'
                )->execute(['user_id' => $id, 'revoked_by' => $moderatorId]);
    
                $this->pdo->prepare(
                    'INSERT INTO admin_actions
                        (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES
                        (:mod, "user", :target_id, "unban", "Unbanned by admin", NOW())'
                )->execute(['mod' => $moderatorId, 'target_id' => $id]);
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Record a disciplinary violation and apply its optional restriction.
     *
     * Warning and removal levels are audit-only. Temporary and permanent
     * levels create an action-scoped ban, while any non-warning level removes
     * the reported target from public display. Everything is committed as one
     * transaction so a violation can never be recorded without its action.
     */
    public function recordViolation(
        string $userId,
        string $targetType,
        string $targetId,
        string $scope,
        string $level,
        string $reason,
        string $moderatorId,
        ?string $endsAt = null,
        bool $autoEscalate = false
    ): array {
        $allowedTargets = ['series', 'chapter', 'blog', 'comment', 'system'];
        $allowedScopes = ['general', 'comment', 'blog'];
        $allowedLevels = ['warning', 'removal', 'temporary', 'permanent'];
        if (!in_array($targetType, $allowedTargets, true)) throw new \InvalidArgumentException('Invalid violation target type');
        if (!in_array($scope, $allowedScopes, true)) throw new \InvalidArgumentException('Invalid violation scope');
        if (($targetType === 'comment' && $scope !== 'comment')
            || ($targetType === 'blog' && $scope !== 'blog')
            || (in_array($targetType, ['series', 'chapter', 'system'], true) && $scope !== 'general')) {
            throw new \InvalidArgumentException('Violation scope does not match the target type');
        }
        if (!in_array($level, $allowedLevels, true)) throw new \InvalidArgumentException('Invalid violation level');
        if ($targetId === '' || strlen($targetId) > 32) throw new \InvalidArgumentException('Invalid violation target id');
        if ($reason === '') throw new \InvalidArgumentException('Violation reason is required');

        $this->pdo->beginTransaction();
        try {
            $userStmt = $this->pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(['id' => $userId]);
            if (!$userStmt->fetch()) throw new \DomainException('User not found');

            if ($autoEscalate) {
                $level = $this->suggestViolationLevel($userId, $scope);
            }

            if ($level === 'temporary' && $endsAt === null) {
                $endsAt = date('Y-m-d H:i:s', time() + 7 * 86400);
            }
            if ($level === 'permanent' || in_array($level, ['warning', 'removal'], true)) {
                $endsAt = null;
            }

            // Restrictions are scoped. A blog violation must not silently
            // revoke an unrelated comment restriction; a general restriction
            // already covers every scope and is therefore retained.
            $active = $this->pdo->prepare(
                'SELECT id, type FROM bans
                 WHERE user_id = :user_id AND revoked_at IS NULL
                   AND level IN ("temporary", "permanent")
                   AND (ends_at IS NULL OR ends_at > NOW())
                   AND (type = "general" OR type = :scope)
                 FOR UPDATE'
            );
            $active->execute(['user_id' => $userId, 'scope' => $scope]);
            $activeRows = $active->fetchAll();
            $generalBan = null;
            foreach ($activeRows as $activeRow) {
                if ((string) ($activeRow['type'] ?? '') === 'general') {
                    $generalBan = $activeRow;
                    break;
                }
            }

            if (in_array($level, ['temporary', 'permanent'], true) && $generalBan === null && $activeRows !== []) {
                $this->pdo->prepare(
                    'UPDATE bans
                     SET revoked_at = NOW(), revoked_by_user_id = :moderator, updated_at = NOW()
                     WHERE user_id = :user_id AND revoked_at IS NULL
                       AND level IN ("temporary", "permanent")
                       AND (ends_at IS NULL OR ends_at > NOW())
                       AND type = :scope'
                )->execute(['user_id' => $userId, 'moderator' => $moderatorId, 'scope' => $scope]);
            }

            $banId = null;
            if (in_array($level, ['temporary', 'permanent'], true)) {
                if ($generalBan !== null) {
                    $banId = (int) ($generalBan['id'] ?? 0) ?: null;
                    if ($scope === 'general' && $banId !== null) {
                        $this->pdo->prepare(
                            'UPDATE bans
                             SET level = :level, reason = :reason, ends_at = :ends_at, updated_at = NOW()
                             WHERE id = :id'
                        )->execute([
                            'id' => $banId,
                            'level' => $level,
                            'reason' => $reason,
                            'ends_at' => $endsAt,
                        ]);
                    }
                } else {
                    $ban = $this->pdo->prepare(
                        'INSERT INTO bans
                            (user_id, type, level, reason, ends_at, banned_by_user_id, created_at, updated_at)
                         VALUES
                            (:user_id, :type, :level, :reason, :ends_at, :moderator, NOW(), NOW())'
                    );
                    $ban->execute([
                        'user_id' => $userId,
                        'type' => $scope,
                        'level' => $level,
                        'reason' => $reason,
                        'ends_at' => $endsAt,
                        'moderator' => $moderatorId,
                    ]);
                    $banId = (int) $this->pdo->lastInsertId();
                }
            }

            if ($level !== 'warning') {
                $this->removeModeratedTarget($targetType, $targetId);
            }

            $violation = $this->pdo->prepare(
                'INSERT INTO moderation_violations
                    (user_id, target_type, target_id, scope, level, action, reason, ban_id, moderator_user_id, created_at)
                 VALUES
                    (:user_id, :target_type, :target_id, :scope, :level, :action, :reason, :ban_id, :moderator, NOW())'
            );
            $violation->execute([
                'user_id' => $userId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'scope' => $scope,
                'level' => $level,
                'action' => $level === 'warning' ? 'warn' : ($level === 'removal' ? 'remove' : 'restrict'),
                'reason' => $reason,
                'ban_id' => $banId,
                'moderator' => $moderatorId,
            ]);
            $violationId = (int) $this->pdo->lastInsertId();

            $this->createModerationAction(
                $moderatorId,
                in_array($targetType, ['series', 'chapter'], true) ? 'content' : $targetType,
                $targetId,
                'disciplinary_' . $level,
                $reason,
                ['violation_id' => $violationId, 'scope' => $scope, 'ban_id' => $banId, 'auto_escalated' => $autoEscalate]
            );

            $this->pdo->commit();
            return [
                'violation_id' => $violationId,
                'user_id' => $userId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'scope' => $scope,
                'level' => $level,
                'action' => $level === 'warning' ? 'warn' : ($level === 'removal' ? 'remove' : 'restrict'),
                'ban_id' => $banId,
                'ends_at' => $endsAt,
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function listViolations(string $userId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.user_id, v.target_type, v.target_id, v.scope, v.level, v.action,
                    v.reason, v.ban_id, v.moderator_user_id, u.username AS moderator_username, v.created_at,
                    b.ends_at, b.revoked_at
             FROM moderation_violations v
             LEFT JOIN users u ON u.id = v.moderator_user_id
             LEFT JOIN bans b ON b.id = v.ban_id
             WHERE v.user_id = :user_id
             ORDER BY v.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function suggestViolationLevel(string $userId, string $scope): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM moderation_violations
             WHERE user_id = :user_id AND scope = :scope
               AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)'
        );
        $stmt->execute(['user_id' => $userId, 'scope' => $scope]);
        return match (min(3, (int) $stmt->fetchColumn())) {
            0 => 'warning',
            1 => 'removal',
            2 => 'temporary',
            default => 'permanent',
        };
    }

    private function removeModeratedTarget(string $targetType, string $targetId): void
    {
        $sql = match ($targetType) {
            'series' => 'UPDATE series SET deleted_at = COALESCE(deleted_at, NOW()) WHERE id = :id',
            'chapter' => 'UPDATE chapters SET deleted_at = COALESCE(deleted_at, NOW()) WHERE id = :id',
            'blog' => 'UPDATE blogs SET deleted_at = COALESCE(deleted_at, NOW()), status = "hidden", approved = 0 WHERE id = :id',
            'comment' => 'UPDATE comments SET deleted_at = COALESCE(deleted_at, NOW()), moderation_status = "deleted" WHERE id = :id',
            default => null,
        };
        if ($sql !== null) {
            $this->pdo->prepare($sql)->execute(['id' => $targetId]);
        }
    }

    public function isEmailAvailableForUser(string $userId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM users
             WHERE email = :email
               AND id <> :id
             LIMIT 1'
        );
        $stmt->execute([
            'email' => $email,
            'id' => $userId,
        ]);
    
        return $stmt->fetchColumn() === false;
    }

    public function listRolesWithPermissions(): array
    {
        $config = \App\Config::getSettings()['rbac'] ?? [];
        $idMap = (array) ($config['id_map'] ?? []);
        $overrides = $this->rolePermissionOverrides();
        $result = [];
    
        foreach ($config['roles'] ?? [] as $slug => $role) {
            $perms = (array) ($role['permissions'] ?? []);
            foreach ($overrides[$slug] ?? [] as $permission => $effect) {
                if ($effect === 'grant' && !in_array($permission, $perms, true)) {
                    $perms[] = $permission;
                } elseif ($effect === 'revoke') {
                    $perms = array_values(array_filter($perms, static fn(string $value): bool => $value !== $permission));
                }
            }
            sort($perms);
            $result[] = [
                'id' => (int) ($idMap[$slug] ?? 0),
                'slug' => $slug,
                'name' => (string) ($role['name'] ?? ucfirst($slug)),
                'description' => (string) ($role['description'] ?? ''),
                'permission_count' => count($perms),
                'permissions' => implode(',', $perms)
            ];
        }
    
        return $result;
    }

    public function listUserRoleAssignments(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $rolesData = $this->listRolesWithPermissions();
        $idToSlug = [];
        foreach ($rolesData as $rd) {
            $idToSlug[(string)$rd['id']] = $rd['slug'];
        }
    
        try {
            $total = $this->count('SELECT COUNT(*) FROM users');
            $stmt = $this->pdo->prepare(
                'SELECT
                    u.id AS user_id,
                    u.username,
                    u.roles,
                    u.created_at AS assigned_at
                 FROM users u
                 WHERE u.roles IS NOT NULL AND u.roles != ""
                 ORDER BY u.created_at DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();
    
            foreach ($items as &$item) {
                $ids = explode(',', (string)$item['roles']);
                $slugs = [];
                foreach ($ids as $id) {
                    if (isset($idToSlug[$id])) {
                        $slugs[] = $idToSlug[$id];
                    }
                }
                $item['roles'] = implode(',', $slugs);
            }
    
            return [
                'items' => $items,
                'total' => $total,
            ];
        } catch (\Throwable) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }
    }

    public function listCreatedEntityOwnership(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $items = [];
    
        $content = $this->pdo->prepare(
            'SELECT s.id, s.title, s.created_at, ma.moderator_user_id AS owner_id, u.username AS owner_username
             FROM series s
             LEFT JOIN (
                SELECT target_id, MAX(id) AS action_id
                FROM admin_actions
                WHERE target_type = "content" AND action = "create"
                GROUP BY target_id
             ) created ON created.target_id = s.id
             LEFT JOIN admin_actions ma ON ma.id = created.action_id
             LEFT JOIN users u ON u.id = ma.moderator_user_id
             WHERE s.deleted_at IS NULL
             ORDER BY s.created_at DESC
             LIMIT :limit'
        );
        $content->bindValue(':limit', $limit, PDO::PARAM_INT);
        $content->execute();
        foreach ($content->fetchAll() as $row) {
            $items[] = [
                'entity_type' => 'content',
                'entity_id' => (string) $row['id'],
                'label' => (string) $row['title'],
                'owner_id' => $row['owner_id'] !== null ? (string) $row['owner_id'] : null,
                'owner_username' => (string) ($row['owner_username'] ?? ($row['owner_id'] ? 'ID:' . $row['owner_id'] : 'Bilinmiyor')),
                'created_at' => (string) $row['created_at'],
            ];
        }
    
        $queries = [
            ['type' => 'chapter', 'sql' => 'SELECT ch.id, CONCAT(s.title, " · Bölüm ", ch.chapter_number) AS label, ch.created_at, ch.created_by AS owner_id, u.username AS owner_username FROM chapters ch INNER JOIN series s ON s.id = ch.content_id LEFT JOIN users u ON u.id = ch.created_by WHERE ch.deleted_at IS NULL ORDER BY ch.created_at DESC LIMIT :limit'],
            ['type' => 'blog', 'sql' => 'SELECT b.id, b.title AS label, b.created_at, b.user_id AS owner_id, u.username AS owner_username FROM blogs b LEFT JOIN users u ON u.id = b.user_id WHERE b.deleted_at IS NULL ORDER BY b.created_at DESC LIMIT :limit'],
            ['type' => 'comment', 'sql' => 'SELECT c.id, CONCAT("Yorum #", c.id) AS label, c.created_at, c.user_id AS owner_id, u.username AS owner_username FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.deleted_at IS NULL ORDER BY c.created_at DESC LIMIT :limit'],
            ['type' => 'image_upload', 'sql' => 'SELECT su.id, su.original_name AS label, su.created_at, su.user_id AS owner_id, u.username AS owner_username FROM system_uploads su LEFT JOIN users u ON u.id = su.user_id ORDER BY su.created_at DESC LIMIT :limit'],
        ];
        foreach ($queries as $query) {
            $stmt = $this->pdo->prepare($query['sql']);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $items[] = [
                    'entity_type' => $query['type'],
                    'entity_id' => (string) $row['id'],
                    'label' => (string) $row['label'],
                    'owner_id' => $row['owner_id'] !== null ? (string) $row['owner_id'] : null,
                    'owner_username' => (string) ($row['owner_username'] ?? ($row['owner_id'] ? 'ID:' . $row['owner_id'] : 'Bilinmiyor')),
                    'created_at' => (string) $row['created_at'],
                ];
            }
        }
    
        usort($items, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return array_slice($items, 0, $limit);
    }

    public function roleExistsBySlug(string $roleSlug): bool
    {
        $config = \App\Config::getSettings()['rbac'] ?? [];
        return isset($config['roles'][$roleSlug]);
    }

    public function permissionExistsByCode(string $permissionCode): bool
    {
        foreach ($this->getAllSystemPermissions() as $permissions) {
            if (array_key_exists($permissionCode, $permissions)) {
                return true;
            }
        }
        return false;
    }

    public function roleHasPermission(string $roleSlug, string $permissionCode): bool
    {
        foreach ($this->listRolesWithPermissions() as $role) {
            if (($role['slug'] ?? '') !== $roleSlug) continue;
            return in_array($permissionCode, explode(',', (string) ($role['permissions'] ?? '')), true);
        }
        return false;
    }

    public function assignPermissionToRole(string $roleSlug, string $permissionCode, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rbac_role_permission_overrides (role_slug, permission_code, effect, updated_by)
                 VALUES (:role, :permission, "grant", :updated_by)
                 ON DUPLICATE KEY UPDATE effect = "grant", updated_by = VALUES(updated_by), updated_at = NOW()'
            );
            $stmt->execute(['role' => $roleSlug, 'permission' => $permissionCode, 'updated_by' => $moderatorId]);
            $this->createModerationAction($moderatorId, 'role', $roleSlug, 'grant_permission', $permissionCode);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function revokePermissionFromRole(string $roleSlug, string $permissionCode, string $moderatorId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rbac_role_permission_overrides (role_slug, permission_code, effect, updated_by)
                 VALUES (:role, :permission, "revoke", :updated_by)
                 ON DUPLICATE KEY UPDATE effect = "revoke", updated_by = VALUES(updated_by), updated_at = NOW()'
            );
            $result = $stmt->execute(['role' => $roleSlug, 'permission' => $permissionCode, 'updated_by' => $moderatorId]);
            if ($result) {
                $this->createModerationAction($moderatorId, 'role', $roleSlug, 'revoke_permission', $permissionCode);
            }
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function assignRoleToUser(string $userId, string $roleSlug): bool
    {
        $config = \App\Config::getSettings()['rbac'] ?? [];
        $idMap = (array) ($config['id_map'] ?? []);
        $roleId = (string) ($idMap[$roleSlug] ?? '');
    
        if ($roleId !== '') {
            $stmt = $this->pdo->prepare('
                UPDATE users 
                SET roles = IF(roles IS NULL OR roles = "", :role_id, CONCAT(roles, ",", :role_id2)) 
                WHERE id = :user_id AND NOT FIND_IN_SET(:role_id3, IFNULL(roles, "")) > 0
            ');
            $stmt->execute([
                'role_id' => $roleId,
                'role_id2' => $roleId,
                'role_id3' => $roleId,
                'user_id' => $userId,
            ]);
            return $stmt->rowCount() > 0;
        }
    
        return false;
    }

    public function revokeUserSession(string $userId, string $sessionKey, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id = :user_id AND session_key = :session_key');
            $stmt->execute(['user_id' => $userId, 'session_key' => $sessionKey]);

            $this->createModerationAction($moderatorId, 'user', $userId, 'revoke_session', 'Session forcefully revoked by admin');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function userExists(string $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    public function getAllSystemPermissions(): array
    {
        return [
            'Panel & Metrics' => [
                'admin.panel.access' => 'Yönetim paneline ve listeleme uçlarına erişme',
                'admin.metrics.view' => 'Dashboard ve analitik metriklerini görüntüleme',
            ],
            'Content & Chapters' => [
                'admin.content.create' => 'Yeni Seri/İçerik Ekleme',
                'admin.content.update' => 'İçerik Bilgilerini Düzenleme',
                'admin.chapter.create' => 'Bölüm Yükleme (Resim/Metin)',
            ],
            'Moderation' => [
                'admin.blog.hide' => 'Blog Onaylama, Gizleme ve Silme',
                'admin.comment.delete' => 'Yorum Silme / Moderasyon',
                'admin.logs.view' => 'Sistem, Güvenlik ve Moderasyon Loglarını İnceleme',
                'admin.reports.view' => 'Rapor ve şikâyetleri görüntüleme',
                'admin.reports.manage' => 'Rapor ve şikâyet durumlarını yönetme',
            ],
            'Users & RBAC' => [
                'admin.users.manage' => 'Kullanıcı Bilgisi, Rolü ve Yasak Durumunu Güncelleme',
                'admin.permissions.grant' => 'Role izin atama',
                'admin.permissions.revoke' => 'Rolden izin kaldırma',
                'admin.roles.assign' => 'Kullanıcıya rol atama',
            ],
            'Monetization' => [
                'admin.wallet.view' => 'Cüzdan ve işlemleri görüntüleme',
                'admin.wallet.manage' => 'Bakiye ve paket müdahalesi',
                'admin.shop.manage' => 'Mağaza paketleri ve fiyatlandırmayı yönetme',
                'admin.finance.refund' => 'Finansal iade işlemi yapma',
                'admin.finance.view' => 'Finans işlem defterini ve özetini görüntüleme',
            ],
            'Operations & Settings' => [
                'admin.jobs.run' => 'Önbellek, yedek, sitemap, kuyruk ve bakım işleri çalıştırma',
                'admin.settings.modify' => 'Site ayarları, webhook ve ortam yapılandırmasını değiştirme',
                'admin.health.view' => 'Sistem sağlık durumunu görüntüleme',
                'admin.uploads.view' => 'Medya kütüphanesini görüntüleme',
                'admin.uploads.delete' => 'Medya kayıtlarını ve dosyalarını silme',
                'admin.uploads.optimize' => 'Medya dosyalarını optimize etme',
            ],
        ];
    }
}
