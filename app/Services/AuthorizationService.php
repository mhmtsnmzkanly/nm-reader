<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Service for handling Role-Based Access Control (RBAC).
 *
 * This service defines the priority of roles and the baseline permissions
 * associated with each role. It provides logic to normalize user roles
 * and resolve effective permissions by merging explicit grants with role defaults.
 *
 * @package App\Services
 */
final class AuthorizationService
{
    /**
     * Priority map for roles.
     * Higher value means the role has more authority.
     */
    private array $rolePriority = [];

    /**
     * Default baseline permissions granted to each role.
     */
    private array $roleBasePermissions = [];

    /**
     * The unique ID of the system owner/superuser.
     */
    private ?string $rootUserId = null;

    public function __construct(private readonly ?PDO $pdo = null)
    {
        $this->rootUserId = $_ENV['ROOT_USER'] ?? getenv('ROOT_USER') ?: null;
        $config = \App\Config::getSettings()['rbac'] ?? [];
        foreach ($config['roles'] ?? [] as $slug => $role) {
            $this->rolePriority[$slug] = (int) ($role['priority'] ?? 0);
            $this->roleBasePermissions[$slug] = (array) ($role['permissions'] ?? []);
        }
    }

    /**
     * Cleans and sorts a list of roles based on their priority.
     *
     * Ensures 'user' role is always present and removes duplicates.
     *
     * @param array<int, string> $roles Raw list of role slugs.
     * @return array<int, string> Normalized and sorted role list (highest first).
     */
    public function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $slug = strtolower(trim((string) $role));
            if ($slug === '') {
                continue;
            }
            if (!in_array($slug, $normalized, true)) {
                $normalized[] = $slug;
            }
        }

        if (!in_array('user', $normalized, true)) {
            $normalized[] = 'user';
        }

        usort($normalized, function (string $a, string $b): int {
            $pa = $this->priority($a);
            $pb = $this->priority($b);
            if ($pa === $pb) {
                return strcmp($a, $b);
            }

            return $pb <=> $pa;
        });

        return $normalized;
    }

    /**
     * Identifies the primary (highest priority) role from a list.
     *
     * @param array<int, string> $roles
     * @return string The slug of the highest role.
     */
    public function highestRole(array $roles): string
    {
        $normalized = $this->normalizeRoles($roles);
        return (string) ($normalized[0] ?? 'user');
    }

    /**
     * Calculates the final list of permissions a user has.
     *
     * Merges permissions explicitly granted to the user with the 
     * baseline permissions of all their assigned roles.
     *
     * @param array<int, string> $roles Assigned roles.
     * @param array<int, string> $grantedPermissions Explicitly granted permission codes.
     * @return array<int, string> Alphabetically sorted list of unique permission codes.
     */
    public function resolveEffectivePermissions(array $roles, array $grantedPermissions = [], ?string $userId = null): array
    {
        $permissionsByRole = $this->rolePermissionsWithOverrides();
        // Absolute Superuser (Root) Bypass
        if ($userId !== null && $this->rootUserId !== null && $userId === $this->rootUserId) {
            // Collect all unique permissions defined in RBAC config
            $allPermissions = [];
            foreach ($this->roleBasePermissions as $perms) {
                foreach ($perms as $p) {
                    if (!in_array($p, $allPermissions, true)) {
                        $allPermissions[] = $p;
                    }
                }
            }
            sort($allPermissions);
            return $allPermissions;
        }

        $normalizedRoles = $this->normalizeRoles($roles);
        $effective = [];

        foreach ($grantedPermissions as $permission) {
            $code = strtolower(trim((string) $permission));
            if ($code !== '' && !in_array($code, $effective, true)) {
                $effective[] = $code;
            }
        }

        // Add baseline permissions from every assigned role.
        foreach ($normalizedRoles as $role) {
            foreach ($permissionsByRole[$role] ?? [] as $permission) {
                if (!in_array($permission, $effective, true)) {
                    $effective[] = $permission;
                }
            }
        }

        sort($effective);
        return $effective;
    }

    /**
     * Gets the numeric priority value for a role slug.
     *
     * @param string $role
     * @return int
     */
    private function priority(string $role): int
    {
        return (int) ($this->rolePriority[$role] ?? 0);
    }

    private function rolePermissionsWithOverrides(): array
    {
        $permissionsByRole = $this->roleBasePermissions;
        if ($this->pdo === null) {
            return $permissionsByRole;
        }

        try {
            $rows = $this->pdo->query(
                'SELECT role_slug, permission_code, effect FROM rbac_role_permission_overrides'
            )->fetchAll();
        } catch (\Throwable) {
            return $permissionsByRole;
        }

        foreach ($rows as $row) {
            $role = (string) ($row['role_slug'] ?? '');
            $permission = (string) ($row['permission_code'] ?? '');
            if ($role === '' || $permission === '' || !array_key_exists($role, $permissionsByRole)) {
                continue;
            }

            $permissions = $permissionsByRole[$role];
            if (($row['effect'] ?? '') === 'grant') {
                if (!in_array($permission, $permissions, true)) {
                    $permissions[] = $permission;
                }
            } else {
                $permissions = array_values(array_filter(
                    $permissions,
                    static fn(string $value): bool => $value !== $permission
                ));
            }
            $permissionsByRole[$role] = $permissions;
        }
        return $permissionsByRole;
    }
}
