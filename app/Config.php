<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AdminPanelController;
use App\Controllers\ContentController;
use App\Controllers\UserInteractionController;
use App\Controllers\AuthController;
use App\Controllers\BlogController;
use App\Controllers\InstallController;
use App\Controllers\UserController;
use App\Controllers\WebController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
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
use Slim\Routing\RouteCollectorProxy;

final class Config
{
    private static ?array $cachedSettings = null;

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
                "session_same_site" => "Lax",
                "session_cookie_secure" => true,
                "session_lifetime_seconds" => (int) self::env("SESSION_LIFETIME", 7200),
                "refresh_token_days" => (int) self::env("REFRESH_TOKEN_DAYS", 30),
                "timezone" => (string) self::env("APP_TIMEZONE", "UTC"),
            ],
            "database" => [
                "host" => (string) self::env("DB_HOST", "127.0.0.1"),
                "port" => (int) self::env("DB_PORT", 3306),
                "database" => (string) self::env("DB_DATABASE", "nm-reader"),
                "username" => (string) self::env("DB_USERNAME", "root"),
                "password" => (string) self::env("DB_PASSWORD", "default000"),
                "charset" => (string) self::env("DB_CHARSET", "utf8mb4"),
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
            "site_name" => (string) self::env("SITE_NAME", "NovelMangaReader"),
            "site_abbreviation" => (string) self::env("SITE_ABBREVIATION", "NMR"),
            "site_logo" => (string) self::env("SITE_LOGO", "/assets/img/logo.svg"),
            "site_description" => (string) self::env("SITE_DESCRIPTION", "Read manga and novels for free."),
            "enforce_https" => (bool) self::env("ENFORCE_HTTPS", false),
            "site_address" => (string) self::env("SITE_ADDRESS", "https://example.com"),
            "default_language" => (string) self::env("DEFAULT_LANGUAGE", "tr"),
            "default_theme" => (string) self::env("DEFAULT_THEME", "dark"),
            "default_profile_image" => (string) self::env("DEFAULT_PROFILE_IMAGE", "/assets/img/default-profile.png"),
            "default_content_cover_image" => (string) self::env("DEFAULT_CONTENT_COVER_IMAGE", "/assets/img/covers/placeholder.svg"),
            "integrations" => [
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
                        "admin.shop.manage", "admin.finance.refund",
                    ],
                ],
                "moderator" => [
                    "name" => "Moderator", "priority" => 50,
                    "permissions" => [
                        "admin.panel.access", "admin.blog.hide", "admin.comment.delete", "admin.content.create",
                        "admin.content.update", "admin.chapter.create", "admin.metrics.view", "admin.wallet.view",
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
        $app->get("/install-63e4qq3", [InstallController::class, "index"]);
        $app->post("/install-63e4qq3", [InstallController::class, "process"]);
        if (!file_exists(dirname(__DIR__) . "/.env")) return;
        $typePattern = "light-novel|web-novel|novel|manga|manhua|manhwa|webtoon";
        self::registerWebRoutes($app, $typePattern);
        self::registerApiRoutes($app, $typePattern);
        self::registerAdminRoutes($app, $typePattern);
    }

    private static function registerWebRoutes(App $app, string $typePattern): void
    {
        $addWebRoutes = function (RouteCollectorProxy $group, bool $includeHome = true) use ($typePattern): void {
            if ($includeHome) $group->get("", [WebController::class, "home"]);
            $group->get("/blogs", [WebController::class, "blog"]);
            $group->get("/blogs/{slug}", [WebController::class, "blog"]);
            $group->get("/chat", [WebController::class, "chat"]);
            $group->get("/search", [WebController::class, "search"]);
            $group->get("/genre/{slug}", [WebController::class, "genre"]);
            $group->get("/tag/{slug}", [WebController::class, "tag"]);
            $group->get("/{type:" . $typePattern . "}", [WebController::class, "listing"]);
            $group->get("/{type:" . $typePattern . "}/{slug}/chapter/{chapterNumber}", [WebController::class, "chapter"]);
            $group->get("/{type:" . $typePattern . "}/{slug}", [WebController::class, "content"]);
            $group->get("/login", [WebController::class, "login"]);
            $group->get("/profile", [WebController::class, "profile"]);
            $group->get("/profile/{person:[A-Za-z0-9_]+}", [WebController::class, "profile"]);
            $group->get("/admin", [WebController::class, "adminDashboard"]);
            $group->get("/admin/content", [WebController::class, "adminContent"]);
            $group->get("/admin/blogs", [WebController::class, "adminBlogs"]);
            $group->get("/admin/comments", [WebController::class, "adminComments"]);
            $group->get("/admin/users", [WebController::class, "adminUsers"]);
            $group->get("/admin/ops", [WebController::class, "adminOps"]);
            $group->get("/admin/monetization", [WebController::class, "adminMonetization"]);
            $group->get("/admin/config", [WebController::class, "adminConfig"]);
            $group->get("/admin/uploads", [WebController::class, "adminUploads"]);
            $group->get("/admin/logs", [WebController::class, "adminLogs"]);
            $group->get("/admin/tutorial", [WebController::class, "adminTutorial"]);
        };
        $app->get("/robots.txt", [WebController::class, "robotsTxt"]);
        $app->get("/sitemap.xml", [WebController::class, "sitemapXml"]);
        $app->get("/mobile[/{path:.*}]", [WebController::class, "mobile"]);
        $app->get("/logout", [AuthController::class, "logout"]);
        $app->get("/", fn($req, $res) => $res->withHeader("Location", "/tr")->withStatus(302));
        $app->group("/{lang:tr|en}", function ($g) use ($addWebRoutes): void {
            $addWebRoutes($g, true);
        });
        $app->group("", function ($g) use ($addWebRoutes): void {
            $addWebRoutes($g, false);
        });
    }

    private static function registerApiRoutes(App $app, string $typePattern): void
    {
        $container = $app->getContainer();
        $cache = $container->get(CacheService::class);
        $authorization = $container->get(AuthorizationService::class);
        $users = static fn() => $container->get(UserRepository::class);

        $app->group("/api/v1", function (RouteCollectorProxy $group) use ($typePattern, $cache, $authorization, $users): void {
            $group->get("/home", [ContentController::class, "home"]);
            $group->get("/genres", [ContentController::class, "genres"]);
            $group->get("/tags", [ContentController::class, "tags"]);
            $group->get("/content/type/{type:" . $typePattern . "}", [ContentController::class, "byType"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}", [ContentController::class, "contentByType"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}/chapters", [ContentController::class, "chaptersByType"]);
            $group->get("/genre/{slug}", [ContentController::class, "genre"]);
            $group->get("/tag/{slug}", [ContentController::class, "tag"]);
            $group->get("/latest-chapters", [ContentController::class, "latestChapters"]);
            $group->get("/shop/packages", [ContentController::class, "shopPackages"]);
            $group->get("/shop/features", [ContentController::class, "shopFeatures"]);
            $group->get("/series_genres", [ContentController::class, "genres"]);
            $group->get("/series_tags", [ContentController::class, "tags"]);
            $group->get("/content/{type:" . $typePattern . "}/chapters", [ContentController::class, "latestChaptersByType"]);
            
            $group->get("/profile/{person:[A-Za-z0-9_]+}", [UserController::class, "publicProfile"]);
            $group->get("/blogs", [BlogController::class, "list"]);
            $group->get("/blogs/{slug}", [BlogController::class, "show"])->add(new AuthMiddleware(true, $authorization));
            $group->get("/content/{type:".$typePattern."}/{slug}/chapter/{chapterNumber}", [ContentController::class, "chapterDetail"]);
            $group->get("/search", [ContentController::class, "search"]);
            $group->get("/search/suggest", [ContentController::class, "suggest"]);
            $group->get("/i18n/{lang:[a-z]{2}}", [WebController::class, "i18nJson"]);
            $group->post("/log/error", [WebController::class, "logError"]);
            $group->post("/user/activity", [UserInteractionController::class, "trackActivity"])->add(new AuthMiddleware(true, $authorization));
            
            $group->get("/chapter/{chapterId:[a-z0-9]{6}}/comments", [UserInteractionController::class, "listChapterComments"]);
            $group->get("/content/{type:" . $typePattern . "}/{slug}/comments", [UserInteractionController::class, "listSeriesComments"]);
            $group->get("/blogs/{slug}/comments", [UserInteractionController::class, "listBlogComments"]);

            $group->post("/auth/register", [AuthController::class, "register"])->add(new RateLimitKeyedMiddleware($cache, "register_email", 3, 600, fn($req) => "email:".strtolower(trim((string)($req->getParsedBody()["email"]??"")))));
            $group->post("/auth/login", [AuthController::class, "login"])->add(new RateLimitKeyedMiddleware($cache, "login_email", 10, 60, fn($req) => "email:".strtolower(trim((string)($req->getParsedBody()["email"]??"")))));
            $group->post("/auth/refresh", [AuthController::class, "refresh"])->add(new RateLimitMiddleware($cache, "refresh", 20, 60));
            $group->map(["GET", "POST"], "/auth/logout", [AuthController::class, "logout"]);

            $group->group("", function (RouteCollectorProxy $secure) use ($typePattern, $users): void {
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/follow", [ContentController::class, "followByType"]);
                $secure->delete("/content/{type:" . $typePattern . "}/{slug}/follow", [ContentController::class, "unfollowByType"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/rate", [UserInteractionController::class, "rateByType"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/comment", [UserInteractionController::class, "createSeriesComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/chapter/{chapterId:[a-z0-9]{6}}/comment", [UserInteractionController::class, "createChapterComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/comments/{commentId:[0-9]+}/vote", [UserInteractionController::class, "voteComment"])->add(new RestrictedActionMiddleware($users, "voting"));
                $secure->post("/user/profile", [UserController::class, "updateProfile"]);
                $secure->get("/user/profile", [UserController::class, "profile"]);
                $secure->get("/user/history", [UserController::class, "history"]);
                $secure->get("/user/preferences", [UserController::class, "preferences"]);
                $secure->put("/user/preferences", [UserController::class, "updatePreferences"]);
                $secure->get("/user/follows", [ContentController::class, "followed"]);
                $secure->get("/user/wallet", [UserController::class, "wallet"]);
                $secure->get("/user/wallet/transactions", [UserController::class, "walletTransactions"]);
                $secure->get("/user/features", [UserController::class, "featureStatus"]);
                $secure->get("/user/features/entitlements", [UserController::class, "featureEntitlements"]);
                $secure->post("/user/features/ad-free/purchase", [UserController::class, "purchaseAdFree"]);
                $secure->get("/user/unlocks/series", [UserController::class, "seriesUnlocks"]);
                $secure->get("/user/unlocks/chapters", [UserController::class, "chapterUnlocks"]);
                $secure->post("/content/{type:" . $typePattern . "}/{slug}/unlock", [ContentController::class, "unlockByType"]);
                $secure->post("/chapter/{chapterId:[a-z0-9]{6}}/unlock", [ContentController::class, "unlockChapter"]);
                $secure->get("/user/blogs", [BlogController::class, "listMyBlogs"]);
                $secure->post("/blogs", [BlogController::class, "create"])->add(new RestrictedActionMiddleware($users, "blog creation"));
                $secure->post("/blogs/image", [BlogController::class, "uploadImage"]);
                $secure->post("/blogs/{slug}/vote", [BlogController::class, "vote"])->add(new RestrictedActionMiddleware($users, "voting"));
                $secure->post("/blogs/{slug}/comments", [UserInteractionController::class, "createBlogComment"])->add(new RestrictedActionMiddleware($users, "commenting"));
                $secure->post("/blogs/{slug}/comments/{commentId:[0-9]+}/vote", [UserInteractionController::class, "voteBlogComment"])->add(new RestrictedActionMiddleware($users, "voting"));
                $secure->get("/auth/sessions", [AuthController::class, "sessions"]);
                $secure->delete("/auth/sessions/{sessionKey:[a-z0-9]+}", [AuthController::class, "revokeSession"]);
                $secure->get("/user/notifications", [UserController::class, "notifications"]);
                $secure->post("/user/notifications/read", [UserController::class, "markNotificationsRead"]);
                $secure->get("/user/follows/users", [UserController::class, "followedUsers"]);
                $secure->post("/user/follows/{person:[A-Za-z0-9_]+}", [UserController::class, "follow"]);
                $secure->delete("/user/follows/{person:[A-Za-z0-9_]+}", [UserController::class, "unfollow"]);
            })->add(new CsrfMiddleware())->add(new AuthMiddleware($authorization));
        });
    }

    private static function registerAdminRoutes(App $app, string $typePattern): void
    {
        $container = $app->getContainer();
        $cache = $container->get(CacheService::class);
        $authorization = $container->get(AuthorizationService::class);
        $perm = static fn(array $p): PermissionMiddleware => new PermissionMiddleware($p);

        $app->group("/api/v1/admin", function (RouteCollectorProxy $group) use ($typePattern, $perm, $cache): void {
            $group->get("/overview", [AdminPanelController::class, "overview"])->add($perm(["admin.panel.access"]));
            $group->get("/series", [AdminPanelController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/contents", [AdminPanelController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/content", [AdminPanelController::class, "listSeries"])->add($perm(["admin.panel.access"]));
            $group->get("/genres", [AdminPanelController::class, "listGenres"])->add($perm(["admin.panel.access"]));
            $group->get("/tags", [AdminPanelController::class, "listTags"])->add($perm(["admin.panel.access"]));
            $group->get("/users", [AdminPanelController::class, "listUsers"])->add($perm(["admin.panel.access"]));
            $group->get("/users/options", [AdminPanelController::class, "userOptions"])->add($perm(["admin.wallet.view"]));
            $group->get("/uploads", [AdminPanelController::class, "uploads"])->add($perm(["admin.panel.access"]));
            $group->delete("/uploads/{id:[0-9]+}", [AdminPanelController::class, "deleteUpload"])->add($perm(["admin.panel.access"]));
            $group->get("/blogs", [AdminPanelController::class, "blogs"])->add($perm(["admin.panel.access"]));
            $group->get("/blogs/pending", [BlogController::class, "pending"])->add($perm(["admin.panel.access"]));
            $group->get("/comments", [AdminPanelController::class, "comments"])->add($perm(["admin.panel.access"]));
            $group->delete("/comments/{id:[0-9]+}", [AdminPanelController::class, "deleteComment"])->add($perm(["admin.comment.delete"]));
            $group->put("/users/{id}", [AdminPanelController::class, "updateUser"])->add($perm(["admin.users.manage"]));
            $group->get("/rbac/roles", [AdminPanelController::class, "rbacRoles"])->add($perm(["admin.panel.access"]));
            $group->get("/rbac/assignments", [AdminPanelController::class, "rbacAssignments"])->add($perm(["admin.panel.access"]));
            $group->post("/rbac/permissions/assign", [AdminPanelController::class, "assignPermissionToRole"])->add($perm(["admin.permissions.grant"]));
            $group->get("/queue/jobs", [AdminPanelController::class, "queueJobs"])->add($perm(["admin.panel.access"]));
            $group->post("/queue/run-once", [AdminPanelController::class, "runQueueOnce"])->add($perm(["admin.jobs.run"]));
            $group->post("/retention/cleanup", [AdminPanelController::class, "cleanupRetention"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/backup", [AdminPanelController::class, "triggerBackup"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/sitemap", [AdminPanelController::class, "triggerSitemap"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/warmup", [AdminPanelController::class, "triggerCacheWarmup"])->add($perm(["admin.jobs.run"]));
            $group->post("/maintenance/analytics", [AdminPanelController::class, "triggerAnalytics"])->add($perm(["admin.jobs.run"]));
            $group->get("/shop/packages", [AdminPanelController::class, "shopPackages"])->add($perm(["admin.shop.manage"]));
            $group->post("/shop/packages", [AdminPanelController::class, "createShopPackage"])->add($perm(["admin.shop.manage"]));
            $group->put("/shop/packages/{id:[0-9]+}", [AdminPanelController::class, "updateShopPackage"])->add($perm(["admin.shop.manage"]));
            $group->post("/wallets/{userId:[a-z0-9]{8}}/grant-package", [AdminPanelController::class, "grantShopPackage"])->add($perm(["admin.wallet.manage"]));
            $group->post("/wallets/{userId:[a-z0-9]{8}}/credit", [AdminPanelController::class, "creditWallet"])->add($perm(["admin.wallet.manage"]));
            $group->post("/wallets/{userId:[a-z0-9]{8}}/debit", [AdminPanelController::class, "debitWallet"])->add($perm(["admin.wallet.manage"]));
            $group->get("/wallets/{userId:[a-z0-9]{8}}", [AdminPanelController::class, "walletSummary"])->add($perm(["admin.wallet.view"]));
            $group->get("/wallets/{userId:[a-z0-9]{8}}/transactions", [AdminPanelController::class, "walletTransactions"])->add($perm(["admin.wallet.view"]));
            $group->put("/series/{id:[a-z0-9]{6}}/pricing", [AdminPanelController::class, "updateSeriesPricing"])->add($perm(["admin.shop.manage"]));
            $group->put("/chapters/{id:[a-z0-9]{6}}/pricing", [AdminPanelController::class, "updateChapterPricing"])->add($perm(["admin.shop.manage"]));
            $group->get("/features", [AdminPanelController::class, "featureProducts"])->add($perm(["admin.shop.manage"]));
            $group->put("/features/ad-free", [AdminPanelController::class, "configureAdFree"])->add($perm(["admin.shop.manage"]));
            $group->get("/maintenance/env", [AdminPanelController::class, "getEnvConfig"])->add($perm(["admin.panel.access"]));
            $group->post("/maintenance/env", [AdminPanelController::class, "saveEnvConfig"])->add($perm(["admin.panel.access"]));
            $group->get("/audit-logs", [AdminPanelController::class, "auditLogs"])->add($perm(["admin.logs.view"]));
            $group->get("/login-events", [AdminPanelController::class, "loginEvents"])->add($perm(["admin.logs.view"]));
            $group->get("/moderation-actions", [AdminPanelController::class, "moderationActions"])->add($perm(["admin.logs.view"]));
            $group->post("/moderation-actions", [AdminPanelController::class, "createModerationAction"])->add($perm(["admin.logs.view"]));
            $group->get("/logs/access", [AdminPanelController::class, "systemAccessLogs"])->add($perm(["admin.logs.view"]));
            $group->get("/logs/error", [AdminPanelController::class, "systemErrorLogs"])->add($perm(["admin.logs.view"]));
            $group->get("/stats/visits", [AdminPanelController::class, "siteVisits"])->add($perm(["admin.metrics.view"]));
            $group->get("/stats/views", [AdminPanelController::class, "viewStats"])->add($perm(["admin.metrics.view"]));
            $group->get("/stats/blogs", [AdminPanelController::class, "blogStats"])->add($perm(["admin.metrics.view"]));
            $group->get("/stats/reputation", [AdminPanelController::class, "userReputation"])->add($perm(["admin.metrics.view"]));
            $group->get("/metrics", [AdminPanelController::class, "metricsSnapshot"])->add($perm(["admin.metrics.view"]));
            $group->get("/dashboard", [AdminPanelController::class, "metricsSnapshot"])->add($perm(["admin.metrics.view"]));
            $group->get("/metrics/insights", [AdminPanelController::class, "metricsInsights"])->add($perm(["admin.metrics.view"]));
            $group->post("/content", [AdminPanelController::class, "createContent"])->add($perm(["admin.content.create"]));
            $group->post("/upload-images", [AdminPanelController::class, "uploadImages"])->add($perm(["admin.content.create"]));
            $group->put("/content/{id}", [AdminPanelController::class, "updateContent"])->add($perm(["admin.content.update"]));
            $group->put("/contents/{id}/taxonomy", [AdminPanelController::class, "updateTaxonomy"])->add($perm(["admin.content.update"]));
            $group->post("/content/{id}/chapters", [AdminPanelController::class, "createChapterByContentId"])->add($perm(["admin.chapter.create"]));
            $group->post("/content/{type:" . $typePattern . "}/{slug}/chapters", [AdminPanelController::class, "createChapter"])->add($perm(["admin.chapter.create"]));
            $group->get("/content/{id}/chapters", [AdminPanelController::class, "listChapters"])->add($perm(["admin.panel.access"]));
            $group->get("/chapters/{id}", [AdminPanelController::class, "getChapter"])->add($perm(["admin.panel.access"]));
            $group->put("/chapters/{id}", [AdminPanelController::class, "updateChapter"])->add($perm(["admin.content.update"]));
            $group->delete("/chapters/{id}", [AdminPanelController::class, "deleteChapter"])->add($perm(["admin.content.update"]));
            $group->post("/series_genres", [AdminPanelController::class, "createGenre"])->add($perm(["admin.content.create"]));
            $group->post("/series_tags", [AdminPanelController::class, "createTag"])->add($perm(["admin.content.create"]));
            $group->post("/blogs/{id}/approve", [BlogController::class, "approve"])->add($perm(["admin.blog.hide"]));
            $group->post("/blogs/{id}/hide", [AdminPanelController::class, "hideBlog"])->add($perm(["admin.blog.hide"]));
            $group->delete("/blogs/{id}", [AdminPanelController::class, "deleteBlog"])->add($perm(["admin.blog.hide"]));
        })->add(new RateLimitMiddleware($cache, "admin_api", 120, 300))->add(new CsrfMiddleware())->add(new AuthMiddleware($authorization));
    }
}
