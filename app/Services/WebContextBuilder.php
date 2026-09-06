<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds the single bootstrap context shared by public and panel shells.
 * Keeping this here prevents controllers from drifting in auth/me handling.
 */
final class WebContextBuilder
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly UserService $userService,
        private readonly MeService $meService,
        private readonly SiteConfigService $siteConfig,
        private readonly I18nService $i18n,
        private readonly \Monolog\Logger $errorLogger,
    ) {}

    /** @return array{lang_code:string,auth:array<string,mixed>,site_config:array<string,mixed>,current_page?:array<string,mixed>} */
    public function build(ServerRequestInterface $request, array $pageContext = []): array
    {
        $userId = isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ''
            ? (string) $_SESSION['user_id']
            : null;
        $langCode = $this->i18n->resolveLocale($request, $userId);

        $auth = $this->auth($userId);
        $payload = [
            'auth' => $auth,
            'lang_code' => $langCode,
            'site_config' => $this->siteConfig->public(),
        ];
        if (isset($pageContext['current_page']) && is_array($pageContext['current_page'])) {
            $payload['current_page'] = $pageContext['current_page'];
        }

        return $payload;
    }

    /** @return array<string,mixed> */
    public function auth(?string $userId = null): array
    {
        $userId ??= isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
        $username = isset($_SESSION['username']) ? (string) $_SESSION['username'] : null;
        if ($userId !== null && $userId !== '' && ($username === null || $username === '')) {
            try {
                $profile = $this->userService->profile($userId);
                $username = $profile['username'] ?? null;
            } catch (\Throwable) {
                $username = null;
            }
        }

        $roles = $this->roles($userId);
        $auth = [
            'is_logged_in' => $userId !== null && $userId !== '',
            'user_id' => $userId,
            'username' => $username,
            'roles' => $roles,
            'permissions' => $this->permissions($userId, $roles),
            'csrf_token' => $_SESSION['csrf_token'] ?? null,
        ];

        if ($userId !== null && $userId !== '') {
            try {
                $snapshot = $this->meService->snapshot($userId);
                if (is_array($snapshot)) {
                    $auth = array_merge($auth, $snapshot);
                }
            } catch (\Throwable $exception) {
                $this->errorLogger->warning('web.me_bootstrap_failed', [
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $auth;
    }

    /** @return list<string> */
    public function roles(?string $userId = null): array
    {
        $roles = is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : [];
        return $this->authorization->normalizeRolesForUser($roles, $userId);
    }

    /** @param list<string>|null $roles @return list<string> */
    public function permissions(?string $userId = null, ?array $roles = null): array
    {
        $roles ??= $this->roles($userId);
        $permissions = is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];
        return $this->authorization->resolveEffectivePermissions($roles, $permissions, $userId);
    }

    public function canAccessAdminPanel(?string $userId = null): bool
    {
        return in_array('admin.panel.access', $this->permissions($userId), true);
    }
}
