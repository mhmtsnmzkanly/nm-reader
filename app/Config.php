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
    private static ?array $cachedSettings = null;

    /**
     * Boots and configures the Slim application.
     *
     * Keeping application creation here makes Config.php the single runtime
     * authority for environment loading, dependency wiring and route setup.
     */
    public static function createApp(): App
    {
        $basePath = dirname(__DIR__);

        if (is_file($basePath . '/.env')) {
            Dotenv::createUnsafeImmutable($basePath)->load();
        }

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

    private static function env(string $key, mixed $default = null): mixed
    {
        $val = $_ENV[$key] ?? ($_SERVER[$key] ?? getenv($key));
        if ($val === false || $val === null || $val === "") return $default;
        if (is_string($val)) {
            $lower = strtolower($val);
            if ($lower === "true" || $lower === "yes" || $lower === "on" || $val === "1") return true;
            if ($lower === "false" || $lower === "no" || $lower === "off" || $val === "0") return false;
        }
        return $val;
    }

    public static function getSettings(): array
    {
        if (self::$cachedSettings !== null) return self::$cachedSettings;
        $basePath = dirname(__DIR__);
        self::$cachedSettings = [
            "app" => [
                "name" => (string) self::env("APP_NAME", "NovelMangaReader"),
                "url" => (string) self::env("APP_URL", "http://localhost:8080"),
                "env" => strtolower((string) self::env("APP_ENV", "production")),
                "debug" => (bool) self::env("APP_DEBUG", false),
                "base_path" => $basePath,
                "root_user" => (string) self::env("ROOT_USER", "usr00001"),
                "session_name" => "nm_reader_session",
                "session_path" => $basePath . "/storage/sessions",
                "session_same_site" => (string) self::env("SESSION_COOKIE_SAME_SITE", "Lax"),
                "session_cookie_secure" => (bool) self::env(
                    "SESSION_COOKIE_SECURE",
                    str_starts_with(strtolower((string) self::env("APP_URL", "http://localhost:8080")), "https://")
                ),
                "remember_cookie_same_site" => (string) self::env("REMEMBER_COOKIE_SAME_SITE", "Lax"),
                "remember_cookie_secure" => (bool) self::env(
                    "REMEMBER_COOKIE_SECURE",
                    str_starts_with(strtolower((string) self::env("APP_URL", "http://localhost:8080")), "https://")
                ),
                "session_lifetime_seconds" => (int) self::env("SESSION_LIFETIME", 7200),
                "refresh_token_days" => (int) self::env("REFRESH_TOKEN_DAYS", 30),
                "media_secret" => (string) self::env("MEDIA_SECRET", self::env("APP_SECRET", "")),
                "cors_allowed_origins" => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) self::env("CORS_ALLOWED_ORIGINS", (string) self::env("APP_URL", "http://localhost:8080")))
                ))),
                // Only these proxy addresses may supply client IP forwarding headers.
                // Empty by default so direct clients cannot spoof the maintenance whitelist.
                "trusted_proxies" => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) self::env("TRUSTED_PROXIES", ""))
                ))),
                "timezone" => (string) self::env("APP_TIMEZONE", "UTC"),
            ],
            "database" => [
                "host" => (string) self::env("DB_HOST", "127.0.0.1"),
                "port" => (int) self::env("DB_PORT", 3306),
                "database" => (string) self::env("DB_DATABASE", "nm-reader"),
                "username" => (string) self::env("DB_USERNAME", "root"),
                "password" => (string) self::env("DB_PASSWORD", "default000"),
                "charset" => (string) self::env("DB_CHARSET", "utf8mb4"),
                "persistent" => (bool) self::env("DB_PERSISTENT", false),
            ],
            "cache" => [
                "driver" => "file",
                "path" => $basePath . "/storage/cache",
                "default_ttl" => (int) self::env("CACHE_TTL", 300),
            ],
            "system" => self::getSystemConfig(),
            "rbac" => self::getRbacConfig(),
        ];
        return self::$cachedSettings;
    }

    public static function getInstance(): array
    {
        return self::getSettings();
    }

    public static function getSystemConfig(): array
    {
        return [
            "integrations" => [
                "resend_api_key" => (string) self::env("RESEND_API_KEY", ""),
                "google_analytics_id" => (string) self::env("GOOGLE_ANALYTICS_ID", ""),
                "google_recaptcha_site_key" => (string) self::env("GOOGLE_RECAPTCHA_SITE_KEY", ""),
                "google_recaptcha_secret_key" => (string) self::env("GOOGLE_RECAPTCHA_SECRET_KEY", ""),
                "cloudflare_turnstile_site_key" => (string) self::env("CLOUDFLARE_TURNSTILE_SITE_KEY", ""),
                "cloudflare_turnstile_secret_key" => (string) self::env("CLOUDFLARE_TURNSTILE_SECRET_KEY", ""),
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

        $installPath = '/install-63e4qq3';
        $app->get($installPath, [InstallController::class, "index"]);
        $app->post($installPath, [InstallController::class, "process"])->add(new CsrfMiddleware());
        $app->post($installPath . '/mode', [InstallController::class, "selectMode"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-environment', [InstallController::class, "validateEnvironment"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-site-settings', [InstallController::class, "validateSiteSettings"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-backup', [InstallController::class, "validateBackup"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-existing-database', [InstallController::class, "validateExistingDatabase"])->add(new CsrfMiddleware());
        $app->post($installPath . '/validate-root', [InstallController::class, "validateRoot"])->add(new CsrfMiddleware());
        $app->post($installPath . '/complete', [InstallController::class, "complete"])->add(new CsrfMiddleware());
        if (!file_exists(dirname(__DIR__) . "/.env")) return;
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
        $users = static fn() => $container->get(UserRepository::class);

        $app->group("/api/v1", function (RouteCollectorProxy $group) use ($typePattern, $cache, $authorization, $users): void {
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

            $group->post("/auth/register", [AuthController::class, "register"])->add(new RateLimitKeyedMiddleware($cache, "register_email", 5, 600, function ($req) {
                $ip = (string) ($req->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
                $email = strtolower(trim((string) ($req->getParsedBody()['email'] ?? '')));
                return "ip:{$ip}:email:" . ($email !== '' ? $email : 'anon');
            }));
            $group->post("/auth/login", [AuthController::class, "login"])->add(new RateLimitKeyedMiddleware($cache, "login_email", 10, 60, function ($req) {
                $ip = (string) ($req->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
                $email = strtolower(trim((string) ($req->getParsedBody()['email'] ?? '')));
                return "ip:{$ip}:email:" . ($email !== '' ? $email : 'anon');
            }));
            $group->post("/auth/forgot-password", [AuthController::class, "forgotPassword"])->add(new RateLimitKeyedMiddleware($cache, "forgot_password", 5, 600, function ($req) {
                $ip = (string) ($req->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
                $email = strtolower(trim((string) ($req->getParsedBody()['email'] ?? '')));
                return "ip:{$ip}:email:" . ($email !== '' ? $email : 'anon');
            }));
            $group->post("/auth/reset-password", [AuthController::class, "resetPassword"])->add(new RateLimitMiddleware($cache, "reset_password", 10, 600));
            $group->map(["GET", "POST"], "/auth/verify-email", [AuthController::class, "verifyEmail"])->add(new RateLimitMiddleware($cache, "verify_email", 15, 600));
            $group->post("/auth/verify-email/resend", [AuthController::class, "resendVerificationEmail"])
                ->add(new RateLimitMiddleware($cache, "resend_verify_email", 5, 300))
                ->add(new AuthMiddleware(true, $authorization));
            $group->post("/auth/refresh", [AuthController::class, "refresh"])->add(new RateLimitMiddleware($cache, "refresh", 20, 60));
            $group->get("/auth/csrf", [AuthController::class, "csrf"])->add(new RateLimitMiddleware($cache, "csrf", 60, 60));
            $group->map(["GET", "POST"], "/auth/logout", [AuthController::class, "logout"]);

            $group->group("", function (RouteCollectorProxy $secure) use ($typePattern, $users): void {
                $secure->get("/me", [UserController::class, "me"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/follow", [ContentController::class, "followByType"]);
                $secure->delete("/content/{type:" . $typePattern . "}/{slug}/follow", [ContentController::class, "unfollowByType"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/rate", [UserInteractionController::class, "rateByType"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/comment", [UserInteractionController::class, "createSeriesComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/chapter/{chapterId:[a-z0-9]{6}}/comment", [UserInteractionController::class, "createChapterComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/comments/{commentId:[0-9]+}/vote", [UserInteractionController::class, "voteComment"])->add(new RestrictedActionMiddleware($users, "voting"));
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
                $secure->post("/blogs/image", [BlogController::class, "uploadImage"]);
                $secure->post("/blogs/{slug}/vote", [BlogController::class, "vote"])->add(new RestrictedActionMiddleware($users, "voting"));
                $secure->post("/blogs/{slug}/comments", [UserInteractionController::class, "createBlogComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/blogs/{slug}/comments/{commentId:[0-9]+}/vote", [UserInteractionController::class, "voteBlogComment"])->add(new RestrictedActionMiddleware($users, "voting"));
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
        $perm = static fn(array $p): PermissionMiddleware => new PermissionMiddleware($p);
        $anyPerm = static fn(array $p): AnyPermissionMiddleware => new AnyPermissionMiddleware($p);

        $app->group("/api/v1/admin", function (RouteCollectorProxy $group) use ($typePattern, $perm, $anyPerm, $cache): void {
            $group->get("/overview", [AdminDashboardController::class, "overview"])->add($perm(["admin.panel.access"]));
            $group->post("/auth/reauth", [AdminDashboardController::class, "reauthenticate"])->add($perm(["admin.panel.access"]));
            $group->get("/series", [AdminContentController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/contents", [AdminContentController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/content", [AdminContentController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/genres", [AdminContentController::class, "listGenres"])->add($perm(["admin.panel.access"]));
            $group->get("/tags", [AdminContentController::class, "listTags"])->add($perm(["admin.panel.access"]));
            $group->get("/users", [AdminUsersController::class, "listUsers"])->add($perm(["admin.panel.access"]));
            $group->get("/users/options", [AdminUsersController::class, "userOptions"])->add($perm(["admin.wallet.view"]));
            $group->get("/uploads", [AdminStorageController::class, "uploads"])->add($perm(["admin.uploads.view"]));
            $group->post("/uploads/cleanup", [AdminStorageController::class, "cleanupUploads"])->add($anyPerm(["admin.content.create", "admin.content.update", "admin.chapter.create"]));
            $group->delete("/uploads/{id:[0-9]+}", [AdminStorageController::class, "deleteUpload"])->add(new CriticalActionMiddleware())->add($perm(["admin.uploads.delete"]));
            $group->post("/uploads/bulk-delete", [AdminStorageController::class, "deleteUploads"])->add(new CriticalActionMiddleware())->add($perm(["admin.uploads.delete"]));
            $group->post("/uploads/{id:[0-9]+}/optimize", [AdminStorageController::class, "optimizeUpload"])->add($perm(["admin.uploads.optimize"]));
            $group->get("/blogs", [AdminModerationController::class, "blogs"])->add($perm(["admin.panel.access"]));
            $group->get("/blogs/pending", [BlogController::class, "pending"])->add($perm(["admin.panel.access"]));
            $group->get("/comments", [AdminModerationController::class, "comments"])->add($perm(["admin.panel.access"]));
            $group->delete("/comments/{id:[0-9]+}", [AdminModerationController::class, "deleteComment"])->add(new CriticalActionMiddleware())->add($perm(["admin.comment.delete"]));
            $group->put("/comments/{id:[0-9]+}/moderation", [AdminModerationController::class, "moderateComment"])->add(new CriticalActionMiddleware())->add($perm(["admin.comment.delete"]));
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
        })->add(new RateLimitMiddleware($cache, "admin_api", 120, 300))->add(new CsrfMiddleware())->add(new AuthMiddleware($authorization));
    }
}
