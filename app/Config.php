<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use App\Controllers\Admin\CommerceController as AdminCommerceController;
use App\Controllers\Admin\ContentController as AdminContentController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\ModerationController as AdminModerationController;
use App\Controllers\Admin\OperationsController as AdminOperationsController;
use App\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Controllers\Admin\StorageController as AdminStorageController;
use App\Controllers\Admin\UsersController as AdminUsersController;
use App\Controllers\ContentController;
use App\Controllers\UserInteractionController;
use App\Controllers\AuthController;
use App\Controllers\BlogController;
use App\Controllers\InstallController;
use App\Controllers\UserController;
use App\Controllers\ContentPageController;
use App\Controllers\BlogPageController;
use App\Controllers\AccountPageController;
use App\Controllers\AdminShellController;
use App\Controllers\SystemPageController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\AnyPermissionMiddleware;
use App\Middleware\CriticalActionMiddleware;
use App\Middleware\RateLimitKeyedMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RestrictedActionMiddleware;
use App\Middleware\I18nMiddleware;
use App\Helpers\RequestSecurity;
use App\Services\SiteConfigService;
use App\Services\I18nService;
use App\Services\CacheService;
use App\Services\AuthorizationService;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

final class Config
{
    public const INSTALL_PATH = '/install-63e4qq3';

    private static ?array $cachedSettings = null;
    private static ?string $cachedEnvironmentFingerprint = null;
    private static bool $environmentLoaded = false;
    /** @var array<string, string> */
    private static array $processEnvironment = [];
    /** @var list<string> Keys previously loaded from the .env file. */
    private static array $fileEnvironmentKeys = [];

    /**
     * Boots and configures the Slim application.
     *
     * Keeping application creation here makes Config.php the single runtime
     * authority for environment loading, dependency wiring and route setup.
     */
    public static function createApp(): App
    {
        $basePath = dirname(__DIR__);
        self::loadEnvironment($basePath);

        $settings = self::getSettings();
        date_default_timezone_set((string) $settings['app']['timezone']);

        $container = require __DIR__ . '/dependencies.php';
        AppFactory::setContainer($container);

        $app = AppFactory::create();

        // Enable FastRoute precompiled route caching in production.
        if (!(bool) ($settings['app']['debug'] ?? false)) {
            $cachePath = (string) ($settings['cache']['path'] ?? ($basePath . '/storage/cache'));
            $routeSources = [__FILE__, $basePath . '/public/index.php'];
            $routeSignature = hash_init('sha256');
            foreach ($routeSources as $routeSource) {
                hash_update_file($routeSignature, $routeSource);
            }
            $routeHash = substr(hash_final($routeSignature), 0, 16);
            $cacheFile = $cachePath . '/fastroute_cache_' . $routeHash . '.php';

            // Route caches are generated and recoverable. Remove obsolete
            // signatures so deployments cannot keep stale route tables.
            foreach (glob($cachePath . '/fastroute_cache*.php') ?: [] as $oldCacheFile) {
                if ($oldCacheFile !== $cacheFile && is_file($oldCacheFile)) {
                    @unlink($oldCacheFile);
                }
            }
            $app->getRouteCollector()->setCacheFile($cacheFile);
        }

        require __DIR__ . '/middleware.php';
        self::registerRoutes($app);

        return $app;
    }

    /**
     * Loads the optional .env file without overriding values injected by the
     * process/web server. CLI entrypoints use this same method as the web
     * bootstrap so configuration precedence cannot drift between runtimes.
     * Passing $reload after a file update refreshes the in-process file values.
     */
    public static function loadEnvironment(?string $basePath = null, bool $reload = false): void
    {
        $basePath = rtrim($basePath ?: dirname(__DIR__), '/');
        if (!self::$environmentLoaded) {
            $processEnvironment = getenv();
            if (is_array($processEnvironment)) {
                foreach ($processEnvironment as $key => $value) {
                    $key = strtoupper((string) $key);
                    if (self::isRuntimeEnvironmentKey($key)) {
                        self::$processEnvironment[$key] = (string) $value;
                    }
                }
            }
            foreach ([$_ENV, $_SERVER] as $source) {
                foreach ($source as $key => $value) {
                    $key = strtoupper((string) $key);
                    if (self::isRuntimeEnvironmentKey($key) && !array_key_exists($key, self::$processEnvironment)) {
                        self::$processEnvironment[$key] = (string) $value;
                    }
                }
            }
            self::$environmentLoaded = true;
        }

        $environmentPath = $basePath . '/.env';
        if (!is_file($environmentPath)) {
            foreach (self::$fileEnvironmentKeys as $key) {
                if (!array_key_exists($key, self::$processEnvironment)) {
                    unset($_ENV[$key], $_SERVER[$key]);
                    putenv($key);
                }
            }
            self::$fileEnvironmentKeys = [];
        } elseif ($reload || self::$cachedEnvironmentFingerprint === null) {
            if ($reload) {
                // safeLoad() does not remove variables deleted from a file.
                // Clear the prior file-owned values first, while preserving
                // process/container overrides captured above.
                foreach (self::$fileEnvironmentKeys as $key) {
                    if (!array_key_exists($key, self::$processEnvironment)) {
                        unset($_ENV[$key], $_SERVER[$key]);
                        putenv($key);
                    }
                }
                $loaded = Dotenv::createUnsafeMutable($basePath)->safeLoad();
                self::$fileEnvironmentKeys = array_values(array_map('strval', array_keys($loaded)));
                // Mutable loading is used only when the file changed (for
                // example after an admin update). Restore process variables so
                // managed-host/container overrides still win over .env.
                foreach (self::$processEnvironment as $key => $value) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    putenv($key . '=' . $value);
                }
            } else {
                $loaded = Dotenv::createUnsafeImmutable($basePath)->safeLoad();
                self::$fileEnvironmentKeys = array_values(array_map('strval', array_keys($loaded)));
            }
        }
    }

    /**
     * Reads a runtime environment value using the same precedence everywhere.
     *
     * Keeping this public prevents services from implementing subtly different
     * $_ENV/$_SERVER/getenv() fallbacks for the same setting.
     */
    public static function getEnv(string $key, mixed $default = null): mixed
    {
        self::loadEnvironment();
        return self::env($key, $default);
    }

    /**
     * Returns which configuration layer currently supplies a value. This is
     * intentionally metadata-only so admin diagnostics can explain why an
     * edited .env value is not taking effect without exposing the value.
     */
    public static function environmentSource(string $key): string
    {
        self::loadEnvironment();
        $key = strtoupper(trim($key));
        if (array_key_exists($key, self::$processEnvironment)) {
            return 'process';
        }
        if (in_array($key, self::$fileEnvironmentKeys, true)) {
            return 'file';
        }
        return 'default';
    }

    /**
     * Clears the in-process settings snapshot. This is needed after an admin
     * updates .env in a long-lived PHP-FPM/worker process; the next request
     * will rebuild settings from the new environment values.
     */
    public static function clearCache(): void
    {
        self::$cachedSettings = null;
        self::$cachedEnvironmentFingerprint = null;
        self::loadEnvironment(dirname(__DIR__), true);
    }

    /**
     * Validates and normalizes values written to the .env file by the admin
     * console. The installer and admin paths can share this contract without
     * exposing any secret values.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function normalizeEnvironment(array $input): array
    {
        $normalized = [];
        $booleanKeys = [
            'APP_DEBUG', 'SESSION_COOKIE_SECURE', 'REMEMBER_COOKIE_SECURE',
            'ENFORCE_HTTPS', 'DB_PERSISTENT',
        ];
        $integerKeys = [
            'SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME', 'REFRESH_TOKEN_DAYS',
            'CACHE_TTL', 'DB_PORT',
        ];
        $knownKeys = array_flip([
            'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'SITE_ADDRESS', 'APP_TIMEZONE',
            'CORS_ALLOWED_ORIGINS', 'SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME',
            'REFRESH_TOKEN_DAYS', 'CACHE_TTL', 'SESSION_COOKIE_SECURE',
            'SESSION_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE', 'REMEMBER_COOKIE_SAME_SITE',
            'ENFORCE_HTTPS', 'TRUSTED_PROXIES', 'DB_PERSISTENT', 'DB_HOST', 'DB_PORT',
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET', 'ROOT_USER',
            'MEDIA_SECRET', 'RESEND_API_KEY', 'GOOGLE_ANALYTICS_ID',
            'GOOGLE_RECAPTCHA_SITE_KEY', 'GOOGLE_RECAPTCHA_SECRET_KEY',
            'CLOUDFLARE_TURNSTILE_SITE_KEY', 'CLOUDFLARE_TURNSTILE_SECRET_KEY',
            'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS', 'INSTALL_TOKEN',
        ]);

        foreach ($input as $rawKey => $rawValue) {
            $key = strtoupper(trim((string) $rawKey));
            if (!isset($knownKeys[$key])) {
                continue;
            }
            if (!is_scalar($rawValue)) {
                throw new \InvalidArgumentException("{$key} must be a scalar value.");
            }

            $value = (string) $rawValue;
            if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
                throw new \InvalidArgumentException("{$key} contains invalid control characters.");
            }

            if (in_array($key, $booleanKeys, true)) {
                $normalized[$key] = self::normalizeBooleanString($key, $value);
                continue;
            }

            if (in_array($key, $integerKeys, true)) {
                $parsed = filter_var(trim($value), FILTER_VALIDATE_INT);
                $minimum = $key === 'SESSION_COOKIE_LIFETIME' ? 0 : 1;
                if ($parsed === false || $parsed < $minimum || ($key === 'DB_PORT' && $parsed > 65535)) {
                    throw new \InvalidArgumentException("{$key} must be a valid integer.");
                }
                $normalized[$key] = (string) $parsed;
                continue;
            }

            $trimmed = trim($value);
            switch ($key) {
                case 'APP_ENV':
                    $trimmed = self::normalizeAppEnvironment($trimmed);
                    break;
                case 'APP_URL':
                    $trimmed = self::normalizeHttpUrl($key, $trimmed, false);
                    break;
                case 'SITE_ADDRESS':
                    $trimmed = self::normalizeHttpUrl($key, $trimmed, true);
                    break;
                case 'APP_TIMEZONE':
                    if (!in_array($trimmed, \DateTimeZone::listIdentifiers(), true)) {
                        throw new \InvalidArgumentException('APP_TIMEZONE is invalid.');
                    }
                    break;
                case 'SESSION_COOKIE_SAME_SITE':
                case 'REMEMBER_COOKIE_SAME_SITE':
                    $trimmed = self::normalizeSameSite($key, $trimmed);
                    break;
                case 'CORS_ALLOWED_ORIGINS':
                    $trimmed = self::normalizeOrigins($trimmed);
                    break;
                case 'ROOT_USER':
                    if ($trimmed !== '' && preg_match('/^[a-z0-9]{8}$/', $trimmed) !== 1) {
                        throw new \InvalidArgumentException('ROOT_USER must be an eight-character lowercase user ID.');
                    }
                    break;
                case 'INSTALL_TOKEN':
                    if ($trimmed !== '' && mb_strlen($trimmed) < 16) {
                        throw new \InvalidArgumentException('INSTALL_TOKEN must contain at least 16 characters.');
                    }
                    break;
                case 'TRUSTED_PROXIES':
                    $trimmed = self::normalizeTrustedProxies($trimmed);
                    break;
                case 'MAIL_FROM_ADDRESS':
                    if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
                        throw new \InvalidArgumentException('MAIL_FROM_ADDRESS must be a valid email address.');
                    }
                    if (mb_strlen($trimmed) > 150) {
                        throw new \InvalidArgumentException('MAIL_FROM_ADDRESS exceeds its maximum length.');
                    }
                    break;
                case 'DB_HOST':
                    if ($trimmed === '' || !preg_match('/^[A-Za-z0-9._:\[\]-]+$/', $trimmed)) {
                        throw new \InvalidArgumentException('DB_HOST contains unsupported characters.');
                    }
                    break;
                case 'DB_DATABASE':
                    if ($trimmed === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
                        throw new \InvalidArgumentException('DB_DATABASE contains unsupported characters.');
                    }
                    break;
                case 'DB_CHARSET':
                    if ($trimmed === '' || !preg_match('/^[A-Za-z0-9_]+$/', $trimmed)) {
                        throw new \InvalidArgumentException('DB_CHARSET contains unsupported characters.');
                    }
                    break;
                default:
                    if ($trimmed === '' && in_array($key, ['APP_NAME', 'DB_USERNAME'], true)) {
                        throw new \InvalidArgumentException("{$key} is required.");
                    }
                    $maxLength = match ($key) {
                        'APP_NAME', 'MAIL_FROM_NAME' => 120,
                        'DB_HOST' => 255,
                        'DB_DATABASE' => 64,
                        'DB_USERNAME' => 150,
                        'DB_PASSWORD' => 512,
                        'DB_CHARSET' => 32,
                        default => 2048,
                    };
                    if (mb_strlen($trimmed) > $maxLength) {
                        throw new \InvalidArgumentException("{$key} exceeds its maximum length.");
                    }
                    break;
            }

            $normalized[$key] = $trimmed;
        }

        foreach ([
            ['SESSION_COOKIE_SAME_SITE', 'SESSION_COOKIE_SECURE'],
            ['REMEMBER_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE'],
        ] as [$sameSiteKey, $secureKey]) {
            if (($normalized[$sameSiteKey] ?? null) === 'None'
                && ($normalized[$secureKey] ?? 'false') !== 'true') {
                throw new \InvalidArgumentException("{$secureKey} must be true when {$sameSiteKey} is None.");
            }
        }

        return $normalized;
    }

    private static function normalizeBooleanString(string $key, string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['true', '1', 'yes', 'on'], true)) {
            return 'true';
        }
        if (in_array($value, ['false', '0', 'no', 'off', ''], true)) {
            return 'false';
        }
        throw new \InvalidArgumentException("{$key} must be true or false.");
    }

    private static function normalizeHttpUrl(string $key, string $value, bool $optional): string
    {
        if ($optional && $value === '') {
            return '';
        }
        $parts = parse_url($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false || !is_array($parts)
            || !preg_match('~^https?://$~i', (string) (($parts['scheme'] ?? '') . '://'))
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || !in_array((string) ($parts['path'] ?? ''), ['', '/'], true)) {
            throw new \InvalidArgumentException("{$key} must be a valid HTTP(S) URL.");
        }
        return rtrim($value, '/');
    }

    private static function normalizeAppEnvironment(string $value): string
    {
        $value = strtolower(trim($value));
        if (!in_array($value, ['local', 'staging', 'production'], true)) {
            throw new \InvalidArgumentException('APP_ENV must be local, staging, or production.');
        }
        return $value;
    }

    private static function normalizeSameSite(string $key, string $value): string
    {
        $value = ucfirst(strtolower(trim($value)));
        if (!in_array($value, ['Lax', 'Strict', 'None'], true)) {
            throw new \InvalidArgumentException("{$key} must be Lax, Strict, or None.");
        }
        return $value;
    }

    private static function safeSameSite(string $value): string
    {
        try {
            return self::normalizeSameSite('COOKIE_SAME_SITE', $value);
        } catch (\InvalidArgumentException) {
            return 'Lax';
        }
    }

    private static function safeBoolean(string $value, bool $default = false): bool
    {
        try {
            return self::normalizeBooleanString('CONFIG_BOOLEAN', $value) === 'true';
        } catch (\InvalidArgumentException) {
            return $default;
        }
    }

    private static function safeInteger(string $value, int $default, int $minimum = 0, ?int $maximum = null): int
    {
        $parsed = filter_var(trim($value), FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < $minimum || ($maximum !== null && $parsed > $maximum)) {
            return $default;
        }
        return (int) $parsed;
    }

    private static function safeHttpUrl(string $key, string $value, string $default, bool $optional = false): string
    {
        try {
            return self::normalizeHttpUrl($key, trim($value), $optional);
        } catch (\InvalidArgumentException) {
            return $default;
        }
    }

    private static function safeTimezone(string $value): string
    {
        $value = trim($value);
        return in_array($value, \DateTimeZone::listIdentifiers(), true) ? $value : 'UTC';
    }

    private static function safeAppEnvironment(string $value): string
    {
        try {
            return self::normalizeAppEnvironment($value);
        } catch (\InvalidArgumentException) {
            return 'production';
        }
    }

    private static function safeRootUser(string $value): string
    {
        $value = trim($value);
        return $value === '' || preg_match('/^[a-z0-9]{8}$/', $value) === 1 ? $value : '';
    }

    /** @return list<string> */
    private static function safeOrigins(string $value, string $fallback): array
    {
        try {
            $normalized = self::normalizeOrigins($value);
            return $normalized === '' ? [$fallback] : array_values(array_filter(array_map('trim', explode(',', $normalized))));
        } catch (\InvalidArgumentException) {
            return [$fallback];
        }
    }

    /** @return list<string> */
    private static function safeTrustedProxies(string $value): array
    {
        try {
            $normalized = self::normalizeTrustedProxies($value);
            return $normalized === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $normalized))));
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    private static function normalizeOrigins(string $value): string
    {
        $origins = array_values(array_filter(array_map('trim', explode(',', $value))));
        $normalizedOrigins = [];
        foreach ($origins as $origin) {
            if ($origin === '*') {
                throw new \InvalidArgumentException('CORS_ALLOWED_ORIGINS cannot use * when credentials are enabled.');
            }
            $parts = parse_url($origin);
            if (filter_var($origin, FILTER_VALIDATE_URL) === false || !is_array($parts)
                || !preg_match('~^https?://$~i', (string) (($parts['scheme'] ?? '') . '://'))
                || trim((string) ($parts['host'] ?? '')) === ''
                || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
                || !in_array((string) ($parts['path'] ?? ''), ['', '/'], true)) {
                throw new \InvalidArgumentException('CORS_ALLOWED_ORIGINS contains an invalid origin.');
            }
            $normalizedOrigins[] = rtrim($origin, '/');
        }
        return implode(',', $normalizedOrigins);
    }

    private static function normalizeTrustedProxies(string $value): string
    {
        $entries = array_values(array_filter(array_map('trim', explode(',', $value))));
        foreach ($entries as $entry) {
            if (!str_contains($entry, '/')) {
                if (filter_var($entry, FILTER_VALIDATE_IP) === false) {
                    throw new \InvalidArgumentException('TRUSTED_PROXIES contains an invalid IP address.');
                }
                continue;
            }
            [$network, $prefix] = array_pad(explode('/', $entry, 2), 2, null);
            $networkBytes = is_string($network) ? inet_pton($network) : false;
            $prefixLength = is_numeric($prefix) ? (int) $prefix : -1;
            if ($networkBytes === false || $prefixLength < 0 || $prefixLength > strlen($networkBytes) * 8) {
                throw new \InvalidArgumentException('TRUSTED_PROXIES contains an invalid CIDR range.');
            }
        }
        return implode(',', $entries);
    }

    private static function env(string $key, mixed $default = null): mixed
    {
        $val = self::rawEnv($key);
        if ($val === false || $val === null || $val === "") return $default;
        if (is_string($val)) {
            $lower = strtolower($val);
            if ($lower === "true" || $lower === "yes" || $lower === "on" || $val === "1") return true;
            if ($lower === "false" || $lower === "no" || $lower === "off" || $val === "0") return false;
        }
        return $val;
    }

    private static function rawEnv(string $key): mixed
    {
        $key = strtoupper(trim($key));
        if (array_key_exists($key, self::$processEnvironment)) {
            return self::$processEnvironment[$key];
        }
        return $_ENV[$key] ?? ($_SERVER[$key] ?? getenv($key));
    }

    private static function isRuntimeEnvironmentKey(string $key): bool
    {
        return preg_match('/^(?:APP_|SITE_|CORS_|SESSION_|REFRESH_|CACHE_|ENFORCE_|TRUSTED_|DB_|ROOT_|MEDIA_|RESEND_|GOOGLE_|CLOUDFLARE_|MAIL_|INSTALL_)/', $key) === 1;
    }

    public static function getSettings(): array
    {
        $basePath = dirname(__DIR__);
        self::loadEnvironment($basePath);
        $environmentFingerprint = self::environmentFingerprint();
        if (self::$cachedSettings !== null && self::$cachedEnvironmentFingerprint !== $environmentFingerprint) {
            self::loadEnvironment($basePath, true);
            $environmentFingerprint = self::environmentFingerprint();
        }
        if (self::$cachedSettings !== null && self::$cachedEnvironmentFingerprint === $environmentFingerprint) {
            return self::$cachedSettings;
        }
        self::$cachedSettings = null;
        self::$cachedEnvironmentFingerprint = $environmentFingerprint;
        $appUrl = self::safeHttpUrl(
            'APP_URL',
            (string) self::env("APP_URL", "http://localhost:8080"),
            'http://localhost:8080'
        );
        $siteAddress = self::safeHttpUrl(
            'SITE_ADDRESS',
            (string) self::env("SITE_ADDRESS", ""),
            '',
            true
        );
        $publicUrl = $siteAddress !== '' ? $siteAddress : $appUrl;
        $sessionSameSite = self::safeSameSite((string) self::env("SESSION_COOKIE_SAME_SITE", "Lax"));
        $sessionSecure = self::safeBoolean(
            (string) self::env("SESSION_COOKIE_SECURE", str_starts_with(strtolower($publicUrl), "https://") ? 'true' : 'false'),
            str_starts_with(strtolower($publicUrl), "https://")
        ) || $sessionSameSite === 'None';
        $rememberSameSite = self::safeSameSite((string) self::env("REMEMBER_COOKIE_SAME_SITE", "Lax"));
        $rememberSecure = self::safeBoolean(
            (string) self::env("REMEMBER_COOKIE_SECURE", str_starts_with(strtolower($publicUrl), "https://") ? 'true' : 'false'),
            str_starts_with(strtolower($publicUrl), "https://")
        ) || $rememberSameSite === 'None';
        self::$cachedSettings = [
            "app" => [
                "name" => (string) self::env("APP_NAME", "NovelMangaReader"),
                "url" => $appUrl,
                "site_address" => $siteAddress,
                "env" => self::safeAppEnvironment((string) self::env("APP_ENV", "production")),
                "debug" => self::safeBoolean((string) self::env("APP_DEBUG", "false")),
                "enforce_https" => self::safeBoolean((string) self::env("ENFORCE_HTTPS", "false")),
                "base_path" => $basePath,
                // A missing ROOT_USER must never grant predictable root access.
                "root_user" => self::safeRootUser((string) self::env("ROOT_USER", "")),
                "session_name" => "nm_reader_session",
                "session_path" => $basePath . "/storage/sessions",
                "session_cookie_lifetime" => self::safeInteger((string) self::env("SESSION_COOKIE_LIFETIME", 0), 0),
                "session_same_site" => $sessionSameSite,
                "session_cookie_secure" => $sessionSecure,
                "remember_cookie_same_site" => $rememberSameSite,
                "remember_cookie_secure" => $rememberSecure,
                "session_lifetime_seconds" => self::safeInteger((string) self::env("SESSION_LIFETIME", 7200), 7200, 1),
                "refresh_token_days" => self::safeInteger((string) self::env("REFRESH_TOKEN_DAYS", 30), 30, 1),
                "media_secret" => (string) self::env("MEDIA_SECRET", self::env("APP_SECRET", "")),
                "cors_allowed_origins" => self::safeOrigins(
                    (string) self::env("CORS_ALLOWED_ORIGINS", $appUrl),
                    $appUrl
                ),
                // Only these proxy addresses may supply client IP forwarding headers.
                // Empty by default so direct clients cannot spoof the maintenance whitelist.
                "trusted_proxies" => self::safeTrustedProxies((string) self::env("TRUSTED_PROXIES", "")),
                "timezone" => self::safeTimezone((string) self::env("APP_TIMEZONE", "UTC")),
            ],
            "database" => [
                "host" => (string) self::env("DB_HOST", "127.0.0.1"),
                "port" => self::safeInteger((string) self::env("DB_PORT", 3306), 3306, 1, 65535),
                "database" => (string) self::env("DB_DATABASE", "nm-reader"),
                "username" => (string) self::env("DB_USERNAME", "root"),
                "password" => (string) self::env("DB_PASSWORD", ""),
                "charset" => (string) self::env("DB_CHARSET", "utf8mb4"),
                "persistent" => self::safeBoolean((string) self::env("DB_PERSISTENT", "false")),
            ],
            "cache" => [
                "driver" => "file",
                "path" => $basePath . "/storage/cache",
                "default_ttl" => self::safeInteger((string) self::env("CACHE_TTL", 300), 300, 1),
            ],
            // Only non-sensitive integration identifiers belong in the generic
            // settings payload. Secrets are read through getEnv() by the
            // server-side services that need them.
            "system" => self::getSystemConfig(),
            "rbac" => self::getRbacConfig(),
        ];
        return self::$cachedSettings;
    }

    private static function environmentFingerprint(): string
    {
        $path = dirname(__DIR__) . '/.env';
        if (!is_file($path)) {
            // Process-level environment values are expected to be immutable
            // for a worker lifetime; clearCache() handles deliberate changes.
            return 'process-environment';
        }

        clearstatcache(true, $path);
        $stat = @stat($path);
        if (!is_array($stat)) {
            return 'env-unavailable';
        }

        $contentHash = @hash_file('sha256', $path) ?: 'unreadable';
        return implode(':', [
            (string) ($stat['mtime'] ?? 0),
            (string) ($stat['size'] ?? 0),
            (string) ($stat['ino'] ?? 0),
            $contentHash,
        ]);
    }

    public static function getInstance(): array
    {
        return self::getSettings();
    }

    public static function getSystemConfig(): array
    {
        self::loadEnvironment();
        return [
            "integrations" => [
                "google_analytics_id" => (string) self::env("GOOGLE_ANALYTICS_ID", ""),
                "google_recaptcha_site_key" => (string) self::env("GOOGLE_RECAPTCHA_SITE_KEY", ""),
                "cloudflare_turnstile_site_key" => (string) self::env("CLOUDFLARE_TURNSTILE_SITE_KEY", ""),
            ],
        ];
    }

    public static function getRbacConfig(): array
    {
        return [
            "roles" => [
                "admin" => [
                    "name" => "Administrator", "priority" => 100,
                    "permissions" => [
                        "admin.panel.access", "admin.users.manage", "admin.content.create", "admin.content.update",
                        "admin.chapter.create", "admin.blog.hide", "admin.comment.delete", "admin.logs.view",
                        "admin.metrics.view", "admin.jobs.run", "admin.settings.modify", "admin.permissions.grant",
                        "admin.permissions.revoke", "admin.roles.assign", "admin.wallet.manage", "admin.wallet.view",
                        "admin.shop.manage", "admin.finance.refund", "admin.finance.view",
                        "admin.reports.view", "admin.reports.manage", "admin.uploads.view",
                        "admin.uploads.delete", "admin.uploads.optimize", "admin.health.view",
                    ],
                ],
                "moderator" => [
                    "name" => "Moderator", "priority" => 50,
                    "permissions" => [
                        "admin.panel.access", "admin.blog.hide", "admin.comment.delete", "admin.content.create",
                        "admin.content.update", "admin.chapter.create", "admin.metrics.view", "admin.wallet.view",
                        "admin.reports.view", "admin.reports.manage", "admin.uploads.view",
                    ],
                ],
                "editor" => [
                    "name" => "Editor", "priority" => 30,
                    "permissions" => [
                        "admin.panel.access", "admin.content.create", "admin.content.update", "admin.chapter.create", "admin.metrics.view",
                    ],
                ],
                "user" => ["name" => "User", "priority" => 10, "permissions" => []],
            ],
            "id_map" => ["admin" => 1, "moderator" => 2, "editor" => 3, "user" => 4],
        ];
    }

    public static function isApplicationConfigured(?string $basePath = null): bool
    {
        $basePath = rtrim($basePath ?: dirname(__DIR__), '/');
        if (is_file($basePath . '/.env')) {
            self::loadEnvironment($basePath);
            // A placeholder or truncated .env must not switch the app to its
            // database route set. Treat it as an unfinished installation.
            foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
                if (!self::hasExplicitEnv($key)) {
                    return false;
                }
            }
            return true;
        }

        // Do not infer installation from Config defaults. A file-less
        // deployment is considered configured only when its required database
        // values were explicitly supplied by the process/web server.
        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            if (!self::hasExplicitEnv($key)) {
                return false;
            }
        }

        return true;
    }

    private static function hasExplicitEnv(string $key): bool
    {
        $value = self::rawEnv($key);
        return $value !== false && trim((string) $value) !== '';
    }

    public static function registerRoutes(App $app): void
    {
        $app->get('/health/live', fn ($request, $response) => $response->withStatus(204));
        $app->get('/health', function ($request, $response) use ($app) {
            $settings = self::getSettings();
            $checks = ['database' => false, 'storage' => false];

            try {
                $checks['database'] = $app->getContainer()->get(\PDO::class)->query('SELECT 1') !== false;
            } catch (\Throwable) {
                $checks['database'] = false;
            }

            $storagePaths = [
                (string) $settings['app']['session_path'],
                (string) $settings['cache']['path'],
                (string) $settings['app']['base_path'] . '/storage/logs',
            ];
            $checks['storage'] = array_all(
                $storagePaths,
                static fn (string $path): bool => is_dir($path) && is_writable($path)
            );
            $healthy = !in_array(false, $checks, true);

            return \App\Helpers\ResponseHelper::json([
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
            ], $healthy ? 200 : 503);
        });

        $installPath = self::INSTALL_PATH;
        $app->get($installPath, [InstallController::class, "index"]);
        $app->post($installPath, [InstallController::class, "process"])->add(new CsrfMiddleware());
        $app->post($installPath . '/mode', [InstallController::class, "selectMode"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-environment', [InstallController::class, "validateEnvironment"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-site-settings', [InstallController::class, "validateSiteSettings"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-backup', [InstallController::class, "validateBackup"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-existing-database', [InstallController::class, "validateExistingDatabase"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-root', [InstallController::class, "validateRoot"])->add(new CsrfMiddleware());
        $app->post($installPath . '/complete', [InstallController::class, "complete"])->add(new CsrfMiddleware());
        // A deployment may inject configuration through the process
        // environment instead of creating a physical .env file. Use the file
        // when present, otherwise require the minimum explicit DB settings.
        // This keeps an unconfigured checkout in installer-only mode without
        // breaking container/managed-host deployments.
        if (!self::isApplicationConfigured(dirname(__DIR__))) return;
        // Accept both the canonical hyphenated URL segments and the legacy
        // underscore variants stored in older links/bookmarks.
        $typePattern = "light-novel|light_novel|web-novel|web_novel|novel|manga|manhua|manhwa|webtoon";
        self::registerWebRoutes($app, $typePattern);
        self::registerApiRoutes($app, $typePattern);
        self::registerAdminRoutes($app, $typePattern);
    }

    private static function registerWebRoutes(App $app, string $typePattern): void
    {
        $addWebRoutes = function (RouteCollectorProxy $group, bool $includeHome = true) use ($typePattern): void {
            if ($includeHome) $group->get("", [ContentPageController::class, "home"]);
            $group->get("/browse", [ContentPageController::class, "listing"]);
            $group->get("/browse/{type:" . $typePattern . "}", [ContentPageController::class, "listing"]);
            $group->get("/genres", [ContentPageController::class, "listing"]);
            $group->get("/tags", [ContentPageController::class, "listing"]);
            $group->get("/library", [ContentPageController::class, "home"]);
            $group->get("/history", [ContentPageController::class, "home"]);
            $group->get("/wallet", [ContentPageController::class, "home"]);
            $group->get("/shop", [ContentPageController::class, "home"]);
            $group->get("/preferences", [ContentPageController::class, "home"]);
            $group->get("/notifications", [ContentPageController::class, "home"]);
            $group->get("/blogs", [BlogPageController::class, "blog"]);
            $group->get("/blogs/new", [ContentPageController::class, "home"]);
            $group->get("/my-blogs", [ContentPageController::class, "home"]);
            $group->get("/blog/{slug}", [BlogPageController::class, "blog"]);
            $group->get("/blogs/{slug}", [BlogPageController::class, "blog"]);
            $group->get("/search", [ContentPageController::class, "search"]);
            $group->get("/genre/{slug}", [ContentPageController::class, "genre"]);
            $group->get("/tag/{slug}", [ContentPageController::class, "tag"]);
            $group->get("/{type:" . $typePattern . "}", [ContentPageController::class, "listing"]);
            $group->get("/{type:" . $typePattern . "}/{slug}/chapter/{chapterNumber}", [ContentPageController::class, "chapter"]);
            $group->get("/{type:" . $typePattern . "}/{slug}/chapters", [ContentPageController::class, "content"]);
            $group->get("/{type:" . $typePattern . "}/{slug}", [ContentPageController::class, "content"]);
            $group->get("/login", [AccountPageController::class, "login"]);
            $group->get("/register", [AccountPageController::class, "login"]);
            $group->get("/me", [AccountPageController::class, "profile"]);
            $group->get("/profile", [AccountPageController::class, "profile"]);
            $group->get("/profile/{person:[A-Za-z0-9_]+}", [AccountPageController::class, "profile"]);
            $group->get("/u/{person:[A-Za-z0-9_]+}", [AccountPageController::class, "profile"]);
            // Unified Lime-CSR Admin Console Shell
            $group->get("/panel", [AdminShellController::class, "index"]);
            $group->get("/panel/{section:.*}", [AdminShellController::class, "index"]);
        };
        $app->get("/robots.txt", [SystemPageController::class, "robotsTxt"]);
        $app->get("/sitemap.xml", [SystemPageController::class, "sitemapXml"]);
        $app->get("/media/public/{filename:[a-zA-Z0-9_\.\-]+}", [\App\Controllers\MediaController::class, "servePublicMedia"]);
        $app->get("/media/chapter/{token:[a-zA-Z0-9_\.\-]+}", [\App\Controllers\MediaController::class, "serveChapterMedia"]);
        $app->get("/logout", [AuthController::class, "logout"]);

        // Public Web Routes (Direct Clean URLs without locale prefix)
        $app->get("/", [ContentPageController::class, "home"]);
        $addWebRoutes($app, false);

        // Legacy /tr and /en URL Migration (301 Permanent Redirect to canonical URLs)
        $app->get("/{lang:tr|en}", function ($req, $res) {
            return $res->withHeader("Location", "/")->withStatus(301);
        });
        $app->get("/{lang:tr|en}/{path:.*}", function ($req, $res, array $args) {
            $path = (string) ($args["path"] ?? "");
            if (str_starts_with($path, "api") || str_starts_with($path, "admin") || str_starts_with($path, "panel") || str_starts_with($path, "media")) {
                $payload = json_encode([
                    "status" => "error",
                    "error" => [
                        "key" => "NOT_FOUND",
                        "code" => 404,
                        "message" => "Resource not found",
                    ],
                ], JSON_UNESCAPED_UNICODE);
                $res->getBody()->write($payload);
                return $res->withHeader("Content-Type", "application/json")->withStatus(404);
            }
            return $res->withHeader("Location", "/" . ltrim($path, "/"))->withStatus(301);
        });
    }

    private static function registerApiRoutes(App $app, string $typePattern): void
    {
        $container = $app->getContainer();
        $cache = $container->get(CacheService::class);
        $authorization = $container->get(AuthorizationService::class);
        $trustedProxies = (array) (self::getSettings()['app']['trusted_proxies'] ?? []);
        $users = static fn() => $container->get(UserRepository::class);

        $app->group("/api/v1", function (RouteCollectorProxy $group) use ($typePattern, $cache, $authorization, $users, $trustedProxies): void {
            $group->get("/media/public/{filename:[a-zA-Z0-9_\.\-]+}", [\App\Controllers\MediaController::class, "servePublicMedia"]);
            $group->get("/media/chapter/{token:[a-zA-Z0-9_\.\-]+}", [\App\Controllers\MediaController::class, "serveChapterMedia"]);
            $group->get("/home", [ContentController::class, "home"]);
            $group->get("/genres", [ContentController::class, "genres"]);
            $group->get("/tags", [ContentController::class, "tags"]);
            $group->get("/content/type/{type:" . $typePattern . "}", [ContentController::class, "byType"]);
            $group->get("/content/{type:" . $typePattern . "}/chapters", [ContentController::class, "latestChaptersByType"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}/overview", [ContentController::class, "contentOverview"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}", [ContentController::class, "contentByType"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}/chapters", [ContentController::class, "chaptersByType"]);
            $group->get("/genre/{slug}", [ContentController::class, "genre"]);
            $group->get("/tag/{slug}", [ContentController::class, "tag"]);
            $group->get("/latest-chapters", [ContentController::class, "latestChapters"]);
            $group->get("/shop/packages", [ContentController::class, "shopPackages"]);
            $group->get("/shop/features", [ContentController::class, "shopFeatures"]);
            $group->get("/series_genres", [ContentController::class, "genres"]);
            $group->get("/series_tags", [ContentController::class, "tags"]);
            
            $group->get("/profile/{person:[A-Za-z0-9_]+}", [UserController::class, "publicProfile"]);
            $group->get("/blogs", [BlogController::class, "list"]);
            $group->get("/blogs/{slug}", [BlogController::class, "show"])->add(new AuthMiddleware(true, $authorization));
            $group->get("/blogs/{slug}/related", [BlogController::class, "related"]);
            $group->get("/content/{type:".$typePattern."}/{slug}/chapter/{chapterNumber}", [ContentController::class, "chapterDetail"]);
            $group->get("/search", [ContentController::class, "search"]);
            $group->get("/search/suggest", [ContentController::class, "suggest"]);
            $group->get("/i18n/{lang:[a-z]{2}}", [SystemPageController::class, "i18nJson"]);
            $group->get("/site-config", [SystemPageController::class, "siteConfig"]);
            $group->post("/log/error", [SystemPageController::class, "logError"]);
            $group->post("/user/activity", [UserInteractionController::class, "trackActivity"])->add(new AuthMiddleware(true, $authorization));
            
            $group->get("/chapter/{chapterId:[a-z0-9]{6}}/comments", [UserInteractionController::class, "listChapterComments"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}/comments", [UserInteractionController::class, "listSeriesComments"]);
            $group->get("/blogs/{slug}/comments", [UserInteractionController::class, "listBlogComments"]);

            $group->post("/auth/register", [AuthController::class, "register"])->add(new RateLimitKeyedMiddleware($cache, "register_email", 5, 600, function ($req) use ($trustedProxies) {
                $ip = RequestSecurity::clientIp($req, $trustedProxies);
                $email = strtolower(trim((string) ($req->getParsedBody()['email'] ?? '')));
                return "ip:{$ip}:email:" . ($email !== '' ? $email : 'anon');
            }));
            $group->post("/auth/login", [AuthController::class, "login"])->add(new RateLimitKeyedMiddleware($cache, "login_email", 10, 60, function ($req) use ($trustedProxies) {
                $ip = RequestSecurity::clientIp($req, $trustedProxies);
                $email = strtolower(trim((string) ($req->getParsedBody()['email'] ?? '')));
                return "ip:{$ip}:email:" . ($email !== '' ? $email : 'anon');
            }));
            $group->post("/auth/forgot-password", [AuthController::class, "forgotPassword"])->add(new RateLimitKeyedMiddleware($cache, "forgot_password", 5, 600, function ($req) use ($trustedProxies) {
                $ip = RequestSecurity::clientIp($req, $trustedProxies);
                $email = strtolower(trim((string) ($req->getParsedBody()['email'] ?? '')));
                return "ip:{$ip}:email:" . ($email !== '' ? $email : 'anon');
            }));
            $group->post("/auth/reset-password", [AuthController::class, "resetPassword"])->add(new RateLimitMiddleware($cache, "reset_password", 10, 600, $trustedProxies));
            $group->map(["GET", "POST"], "/auth/verify-email", [AuthController::class, "verifyEmail"])->add(new RateLimitMiddleware($cache, "verify_email", 15, 600, $trustedProxies));
            $group->post("/auth/verify-email/resend", [AuthController::class, "resendVerificationEmail"])
                ->add(new RateLimitMiddleware($cache, "resend_verify_email", 5, 300, $trustedProxies))
                ->add(new AuthMiddleware(true, $authorization));
            $group->post("/auth/refresh", [AuthController::class, "refresh"])->add(new RateLimitMiddleware($cache, "refresh", 20, 60, $trustedProxies));
            $group->get("/auth/csrf", [AuthController::class, "csrf"])->add(new RateLimitMiddleware($cache, "csrf", 60, 60, $trustedProxies));
            $group->map(["GET", "POST"], "/auth/logout", [AuthController::class, "logout"]);

            $group->group("", function (RouteCollectorProxy $secure) use ($typePattern, $users): void {
                $secure->get("/me", [UserController::class, "me"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/follow", [ContentController::class, "followByType"]);
                $secure->delete("/content/{type:" . $typePattern . "}/{slug}/follow", [ContentController::class, "unfollowByType"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/rate", [UserInteractionController::class, "rateByType"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/comment", [UserInteractionController::class, "createSeriesComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/chapter/{chapterId:[a-z0-9]{6}}/comment", [UserInteractionController::class, "createChapterComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                // Reactions remain available to users with interaction
                // penalties; only content creation/moderation actions are
                // restricted by the ban middleware.
                $secure->post("/comments/{commentId:[0-9]+}/vote", [UserInteractionController::class, "voteComment"]);
                $secure->post("/user/profile", [UserController::class, "updateProfile"]);
                $secure->get("/user/profile", [UserController::class, "profile"]);
                $secure->get("/user/history", [UserController::class, "history"]);
                $secure->post("/user/history", [UserController::class, "recordHistory"]);
                $secure->delete("/user/history", [UserController::class, "clearHistory"]);
                $secure->delete("/user/history/{historyId:[A-Za-z0-9]+}", [UserController::class, "deleteHistory"]);
                $secure->get("/user/preferences", [UserController::class, "preferences"]);
                $secure->put("/user/preferences", [UserController::class, "updatePreferences"]);
                $secure->get("/user/follows", [ContentController::class, "followed"]);
                $secure->get("/user/wallet", [UserController::class, "wallet"]);
                $secure->get("/user/wallet/transactions", [UserController::class, "walletTransactions"]);
                $secure->post("/shop/packages/{packageId:[0-9]+}/purchase", [UserController::class, "purchasePackage"]);
                $secure->get("/user/features", [UserController::class, "featureStatus"]);
                $secure->get("/user/features/entitlements", [UserController::class, "featureEntitlements"]);
                $secure->post("/user/features/ad-free/purchase", [UserController::class, "purchaseAdFree"]);
                $secure->get("/user/unlocks/series", [UserController::class, "seriesUnlocks"]);
                $secure->get("/user/unlocks/chapters", [UserController::class, "chapterUnlocks"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/unlock", [ContentController::class, "unlockByType"]);
                $secure->post("/chapter/{chapterId:[a-z0-9]{6}}/unlock", [ContentController::class, "unlockChapter"]);
                $secure->get("/user/blogs", [BlogController::class, "listMyBlogs"]);
                $secure->get("/user/blogs/{id:[a-z0-9]{6}}", [BlogController::class, "showMyBlog"]);
                $secure->post("/blogs", [BlogController::class, "create"])->add(new RestrictedActionMiddleware($users, "blog creation"));
                $secure->put("/blogs/{id:[a-z0-9]{6}}", [BlogController::class, "update"])->add(new RestrictedActionMiddleware($users, "blog update"));
                $secure->delete("/blogs/{id:[a-z0-9]{6}}", [BlogController::class, "delete"])->add(new RestrictedActionMiddleware($users, "blog deletion"));
                $secure->post("/blogs/image", [BlogController::class, "uploadImage"])->add(new RestrictedActionMiddleware($users, "blog creation"));
                $secure->post("/blogs/{slug}/vote", [BlogController::class, "vote"]);
                $secure->post("/blogs/{slug}/comments", [UserInteractionController::class, "createBlogComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/blogs/{slug}/comments/{commentId:[0-9]+}/vote", [UserInteractionController::class, "voteBlogComment"]);
                $secure->get("/auth/sessions", [AuthController::class, "sessions"]);
                $secure->delete("/auth/sessions/{sessionKey:[a-z0-9]+}", [AuthController::class, "revokeSession"]);
                $secure->post("/auth/sessions/revoke-others", [AuthController::class, "revokeOtherSessions"]);
                $secure->get("/user/notifications", [UserController::class, "notifications"]);
                $secure->post("/user/notifications/read", [UserController::class, "markNotificationsRead"]);
                $secure->delete("/user/notifications/{notificationId:[0-9]+}", [UserController::class, "deleteNotification"]);
                $secure->get("/user/follows/users", [UserController::class, "followedUsers"]);
                $secure->get("/user/lists", [UserController::class, "lists"]);
                $secure->post("/user/lists", [UserController::class, "createList"]);
                $secure->get("/user/lists/{listId:[0-9]+}", [UserController::class, "list"]);
                $secure->put("/user/lists/{listId:[0-9]+}", [UserController::class, "updateList"]);
                $secure->delete("/user/lists/{listId:[0-9]+}", [UserController::class, "deleteList"]);
                $secure->post("/user/lists/{listId:[0-9]+}/items", [UserController::class, "addListItem"]);
                $secure->delete("/user/lists/{listId:[0-9]+}/items/{contentId:[a-z0-9]{6}}", [UserController::class, "removeListItem"]);
                $secure->post("/user/follows/{person:[A-Za-z0-9_]+}", [UserController::class, "follow"]);
                $secure->delete("/user/follows/{person:[A-Za-z0-9_]+}", [UserController::class, "unfollow"]);
                $secure->post("/reports", [\App\Controllers\ReportController::class, "create"])->add(new RestrictedActionMiddleware($users, "reporting"));
            })
                ->add(\App\Middleware\RequireVerifiedEmailMiddleware::class)
                ->add(new CsrfMiddleware())
                ->add(new AuthMiddleware($authorization));
        });
    }

    private static function registerAdminRoutes(App $app, string $typePattern): void
    {
        $container = $app->getContainer();
        $cache = $container->get(CacheService::class);
        $authorization = $container->get(AuthorizationService::class);
        $trustedProxies = (array) (self::getSettings()['app']['trusted_proxies'] ?? []);
        $perm = static fn(array $p): PermissionMiddleware => new PermissionMiddleware($p);
        $anyPerm = static fn(array $p): AnyPermissionMiddleware => new AnyPermissionMiddleware($p);

        $app->group("/api/v1/admin", function (RouteCollectorProxy $group) use ($typePattern, $perm, $anyPerm, $cache, $trustedProxies): void {
            $group->get("/overview", [AdminDashboardController::class, "overview"])->add($perm(["admin.panel.access"]));
            $group->get("/dashboard-data", [AdminDashboardController::class, "dashboardData"])->add($perm(["admin.metrics.view"]));
            $group->post("/auth/reauth", [AdminDashboardController::class, "reauthenticate"])->add($perm(["admin.panel.access"]));
            $group->get("/series", [AdminContentController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/contents", [AdminContentController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/content", [AdminContentController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/genres", [AdminContentController::class, "listGenres"])->add($perm(["admin.panel.access"]));
            $group->get("/tags", [AdminContentController::class, "listTags"])->add($perm(["admin.panel.access"]));
            $group->get("/users", [AdminUsersController::class, "listUsers"])->add($perm(["admin.panel.access"]));
            $group->get("/users/{id}/overview", [AdminUsersController::class, "userOverview"])->add($perm(["admin.users.manage"]));
            $group->get("/users/{id}/comments", [AdminUsersController::class, "userComments"])->add($perm(["admin.users.manage"]));
            $group->get("/users/{id}/blogs", [AdminUsersController::class, "userBlogs"])->add($perm(["admin.users.manage"]));
            $group->get("/users/{id}/violations", [AdminUsersController::class, "listViolations"])->add($perm(["admin.users.manage"]));
            $group->post("/users/{id}/violations", [AdminUsersController::class, "recordViolation"])->add(new CriticalActionMiddleware())->add($perm(["admin.users.manage"]));
            $group->get("/users/options", [AdminUsersController::class, "userOptions"])->add($perm(["admin.wallet.view"]));
            $group->get("/uploads", [AdminStorageController::class, "uploads"])->add($perm(["admin.uploads.view"]));
            $group->post("/uploads/cleanup", [AdminStorageController::class, "cleanupUploads"])->add($anyPerm(["admin.content.create", "admin.content.update", "admin.chapter.create"]));
            $group->delete("/uploads/{id:[0-9]+}", [AdminStorageController::class, "deleteUpload"])->add(new CriticalActionMiddleware())->add($perm(["admin.uploads.delete"]));
            $group->post("/uploads/bulk-delete", [AdminStorageController::class, "deleteUploads"])->add(new CriticalActionMiddleware())->add($perm(["admin.uploads.delete"]));
            $group->post("/uploads/{id:[0-9]+}/optimize", [AdminStorageController::class, "optimizeUpload"])->add($perm(["admin.uploads.optimize"]));
            $group->get("/votes/likers", [AdminModerationController::class, "likers"])->add($perm(["admin.panel.access"]));
            $group->get("/blogs", [AdminModerationController::class, "blogs"])->add($perm(["admin.panel.access"]));
            $group->get("/blogs/pending", [BlogController::class, "pending"])->add($perm(["admin.panel.access"]));
            $group->get("/blogs/{id}/preview", [AdminModerationController::class, "blogPreview"])->add($perm(["admin.panel.access"]));
            $group->get("/comments", [AdminModerationController::class, "comments"])->add($perm(["admin.panel.access"]));
            $group->delete("/comments/{id:[0-9]+}", [AdminModerationController::class, "deleteComment"])->add(new CriticalActionMiddleware())->add($perm(["admin.comment.delete"]));
            $group->put("/comments/{id:[0-9]+}/moderation", [AdminModerationController::class, "moderateComment"])->add(new CriticalActionMiddleware())->add($perm(["admin.comment.delete"]));
            $group->put("/users/{id}/profile", [AdminUsersController::class, "updateUserProfile"])->add(new CriticalActionMiddleware())->add($perm(["admin.users.manage"]));
            $group->put("/users/{id}", [AdminUsersController::class, "updateUser"])->add(new CriticalActionMiddleware())->add($perm(["admin.users.manage"]));
            $group->get("/rbac/roles", [AdminUsersController::class, "rbacRoles"])->add($perm(["admin.panel.access"]));
            $group->get("/rbac/assignments", [AdminUsersController::class, "rbacAssignments"])->add($perm(["admin.panel.access"]));
            $group->post("/rbac/permissions/assign", [AdminUsersController::class, "assignPermissionToRole"])->add(new CriticalActionMiddleware())->add($perm(["admin.permissions.grant"]));
            $group->delete("/rbac/permissions", [AdminUsersController::class, "revokePermissionFromRole"])->add(new CriticalActionMiddleware())->add($perm(["admin.permissions.revoke"]));
            $group->get("/queue/jobs", [AdminOperationsController::class, "queueJobs"])->add($perm(["admin.panel.access"]));
            $group->get("/system/health", [AdminOperationsController::class, "systemHealth"])->add($perm(["admin.health.view"]));
            $group->post("/queue/jobs/{id:[0-9]+}/retry", [AdminOperationsController::class, "retryQueueJob"])->add($perm(["admin.jobs.run"]));
            $group->post("/queue/jobs/{id:[0-9]+}/cancel", [AdminOperationsController::class, "cancelQueueJob"])->add($perm(["admin.jobs.run"]));
            $group->post("/queue/run-once", [AdminOperationsController::class, "runQueueOnce"])->add($perm(["admin.jobs.run"]));
            $group->post("/retention/cleanup", [AdminOperationsController::class, "cleanupRetention"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/backup", [AdminOperationsController::class, "triggerBackup"])->add(new CriticalActionMiddleware())->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/sitemap", [AdminOperationsController::class, "triggerSitemap"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/warmup", [AdminOperationsController::class, "triggerCacheWarmup"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/analytics", [AdminOperationsController::class, "triggerAnalytics"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/api-tests", [AdminOperationsController::class, "triggerApiTests"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/openapi", [AdminOperationsController::class, "triggerOpenApi"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/seed-data", [AdminOperationsController::class, "triggerSeedData"])->add(new CriticalActionMiddleware())->add($perm(["admin.jobs.run"]));
            $group->get("/shop/packages", [AdminCommerceController::class, "shopPackages"])->add($perm(["admin.shop.manage"]));
            $group->post("/shop/packages", [AdminCommerceController::class, "createShopPackage"])->add($perm(["admin.shop.manage"]));
            $group->put("/shop/packages/{id:[0-9]+}", [AdminCommerceController::class, "updateShopPackage"])->add($perm(["admin.shop.manage"]));
            $group->post("/wallets/{userId:[a-z0-9]{8}}/grant-package", [AdminCommerceController::class, "grantShopPackage"])->add(new CriticalActionMiddleware())->add($perm(["admin.wallet.manage"]));
            $group->post("/wallets/{userId:[a-z0-9]{8}}/credit", [AdminCommerceController::class, "creditWallet"])->add(new CriticalActionMiddleware())->add($perm(["admin.wallet.manage"]));
            $group->post("/wallets/{userId:[a-z0-9]{8}}/debit", [AdminCommerceController::class, "debitWallet"])->add(new CriticalActionMiddleware())->add($perm(["admin.wallet.manage"]));
            $group->get("/wallets/{userId:[a-z0-9]{8}}", [AdminCommerceController::class, "walletSummary"])->add($perm(["admin.wallet.view"]));
            $group->get("/wallets/{userId:[a-z0-9]{8}}/transactions", [AdminCommerceController::class, "walletTransactions"])->add($perm(["admin.wallet.view"]));
            $group->put("/series/{id:[a-z0-9]{6}}/pricing", [AdminCommerceController::class, "updateSeriesPricing"])->add($perm(["admin.shop.manage"]));
            $group->put("/chapters/{id:[a-z0-9]{6}}/pricing", [AdminCommerceController::class, "updateChapterPricing"])->add($perm(["admin.shop.manage"]));
            $group->get("/features", [AdminCommerceController::class, "featureProducts"])->add($perm(["admin.shop.manage"]));
            $group->put("/features/ad-free", [AdminCommerceController::class, "configureAdFree"])->add($perm(["admin.shop.manage"]));
            $group->get("/maintenance/env", [AdminSettingsController::class, "getEnvConfig"])->add($perm(["admin.settings.modify"]));
            $group->post("/maintenance/env", [AdminSettingsController::class, "saveEnvConfig"])->add(new CriticalActionMiddleware())->add($perm(["admin.settings.modify"]));
            $group->get("/audit-logs", [AdminModerationController::class, "auditLogs"])->add($perm(["admin.logs.view"]));
            $group->get("/login-events", [AdminModerationController::class, "loginEvents"])->add($perm(["admin.logs.view"]));
            $group->get("/moderation-actions", [AdminModerationController::class, "moderationActions"])->add($perm(["admin.logs.view"]));
            $group->post("/moderation-actions", [AdminModerationController::class, "createModerationAction"])->add($perm(["admin.logs.view"]));
            $group->get("/logs/access", [AdminOperationsController::class, "systemAccessLogs"])->add($perm(["admin.logs.view"]));
            $group->get("/logs/error", [AdminOperationsController::class, "systemErrorLogs"])->add($perm(["admin.logs.view"]));
            $group->get("/stats/visits", [AdminDashboardController::class, "siteVisits"])->add($perm(["admin.metrics.view"]));
            $group->get("/stats/views", [AdminDashboardController::class, "viewStats"])->add($perm(["admin.metrics.view"]));
            $group->get("/stats/blogs", [AdminDashboardController::class, "blogStats"])->add($perm(["admin.metrics.view"]));
            $group->get("/stats/reputation", [AdminDashboardController::class, "userReputation"])->add($perm(["admin.metrics.view"]));
            $group->get("/metrics", [AdminDashboardController::class, "metricsSnapshot"])->add($perm(["admin.metrics.view"]));
            $group->get("/dashboard", [AdminDashboardController::class, "metricsSnapshot"])->add($perm(["admin.metrics.view"]));
            $group->get("/metrics/insights", [AdminDashboardController::class, "metricsInsights"])->add($perm(["admin.metrics.view"]));
            $group->post("/content", [AdminContentController::class, "createContent"])->add($perm(["admin.content.create"]));
            $group->post("/upload-images", [AdminStorageController::class, "uploadImages"])->add($anyPerm(["admin.content.create", "admin.content.update", "admin.chapter.create"]));
            $group->put("/content/{id}", [AdminContentController::class, "updateContent"])->add($perm(["admin.content.update"]));
            $group->post("/content/{id}/lifecycle", [AdminContentController::class, "changeContentLifecycle"])->add($perm(["admin.content.update"]));
            $group->get("/content/{id}/preview", [AdminContentController::class, "contentPreview"])->add($perm(["admin.panel.access"]));
            $group->get("/content/{id}/revisions", [AdminContentController::class, "contentRevisions"])->add($perm(["admin.panel.access"]));
            $group->put("/contents/{id}/taxonomy", [AdminContentController::class, "updateTaxonomy"])->add($perm(["admin.content.update"]));
            $group->post("/content/{id}/chapters", [AdminContentController::class, "createChapterByContentId"])->add($perm(["admin.chapter.create"]));
            $group->post("/content/{type:" . $typePattern . "}/{slug}/chapters", [AdminContentController::class, "createChapter"])->add($perm(["admin.chapter.create"]));
            $group->get("/content/{id}/chapters", [AdminContentController::class, "listChapters"])->add($perm(["admin.panel.access"]));
            $group->get("/chapters/{id}", [AdminContentController::class, "getChapter"])->add($perm(["admin.panel.access"]));
            $group->put("/chapters/{id}", [AdminContentController::class, "updateChapter"])->add($perm(["admin.content.update"]));
            $group->delete("/chapters/{id}", [AdminContentController::class, "deleteChapter"])->add(new CriticalActionMiddleware())->add($perm(["admin.content.update"]));
            $group->post("/chapters/bulk", [AdminContentController::class, "bulkChapters"])->add($perm(["admin.content.update"]));
            $group->get("/series/{id:[a-z0-9]{6}}/team", [AdminContentController::class, "listSeriesTeam"])->add($perm(["admin.panel.access"]));
            $group->post("/series/{id:[a-z0-9]{6}}/team", [AdminContentController::class, "assignSeriesTeam"])->add($perm(["admin.content.update"]));
            $group->delete("/series/team/{assignmentId:[0-9]+}", [AdminContentController::class, "removeSeriesTeam"])->add($perm(["admin.content.update"]));
            $group->get("/rbac/matrix", [AdminUsersController::class, "permissionMatrix"])->add($perm(["admin.panel.access"]));
            $group->get("/rbac/ownership", [AdminUsersController::class, "ownershipCapabilities"])->add($perm(["admin.panel.access"]));
            $group->get("/config/site", [AdminSettingsController::class, "getSiteConfig"])->add($perm(["admin.settings.modify"]));
            $group->post("/config/site", [AdminSettingsController::class, "updateSiteConfig"])->add($perm(["admin.settings.modify"]));
            $group->get("/webhooks", [AdminSettingsController::class, "listWebhooks"])->add($perm(["admin.settings.modify"]));
            $group->post("/webhooks", [AdminSettingsController::class, "createWebhook"])->add($perm(["admin.settings.modify"]));
            $group->put("/webhooks/{id:[0-9]+}", [AdminSettingsController::class, "updateWebhook"])->add($perm(["admin.settings.modify"]));
            $group->delete("/webhooks/{id:[0-9]+}", [AdminSettingsController::class, "deleteWebhook"])->add(new CriticalActionMiddleware())->add($perm(["admin.settings.modify"]));
            $group->post("/webhooks/{id:[0-9]+}/test", [AdminSettingsController::class, "testWebhook"])->add($perm(["admin.settings.modify"]));
            $group->get("/reports", [\App\Controllers\ReportController::class, "list"])->add($perm(["admin.reports.view"]));
            $group->get("/reports/{id:[0-9]+}", [\App\Controllers\ReportController::class, "show"])->add($perm(["admin.reports.view"]));
            $group->patch("/reports/{id:[0-9]+}", [\App\Controllers\ReportController::class, "update"])->add($perm(["admin.reports.manage"]));
            $group->put("/reports/{id:[0-9]+}", [\App\Controllers\ReportController::class, "update"])->add($perm(["admin.reports.manage"]));
            $group->get("/analytics/monetization", [AdminDashboardController::class, "monetizationAnalytics"])->add($perm(["admin.metrics.view"]));
            $group->get("/finance/transactions", [AdminCommerceController::class, "financeTransactions"])->add($perm(["admin.finance.view"]));
            $group->post("/finance/transactions/{id:[0-9]+}/refund", [AdminCommerceController::class, "refundFinanceTransaction"])->add(new CriticalActionMiddleware())->add($perm(["admin.finance.refund"]));
            $group->get("/analytics/search-insights", [AdminDashboardController::class, "searchInsights"])->add($perm(["admin.metrics.view"]));
            $group->get("/analytics/funnel/{id:[a-z0-9]{6}}", [AdminDashboardController::class, "seriesReadingFunnel"])->add($perm(["admin.metrics.view"]));
            $group->post("/series_genres", [AdminContentController::class, "createGenre"])->add($perm(["admin.content.create"]));
            $group->post("/series_tags", [AdminContentController::class, "createTag"])->add($perm(["admin.content.create"]));
            $group->put("/taxonomies/{id:[0-9]+}", [AdminContentController::class, "editTaxonomy"])->add($perm(["admin.content.update"]));
            $group->delete("/taxonomies/{id:[0-9]+}", [AdminContentController::class, "deleteTaxonomyItem"])->add(new CriticalActionMiddleware())->add($perm(["admin.content.update"]));
            $group->post("/taxonomies/merge", [AdminContentController::class, "mergeTaxonomies"])->add(new CriticalActionMiddleware())->add($perm(["admin.content.update"]));
            $group->put("/taxonomies/order", [AdminContentController::class, "reorderTaxonomies"])->add($perm(["admin.content.update"]));
            $group->post("/blogs/{id}/approve", [BlogController::class, "approve"])->add($perm(["admin.blog.hide"]));
            $group->post("/blogs/{id}/hide", [AdminModerationController::class, "hideBlog"])->add($perm(["admin.blog.hide"]));
            $group->delete("/blogs/{id}", [AdminModerationController::class, "deleteBlog"])->add(new CriticalActionMiddleware())->add($perm(["admin.blog.hide"]));
        })->add(new RateLimitMiddleware($cache, "admin_api", 120, 300, $trustedProxies))->add(new CsrfMiddleware())->add(new AuthMiddleware($authorization));
    }
}
