<?php

declare(strict_types=1);

namespace App;

use App\Controllers\ActivityController;
use App\Controllers\AdminConsoleController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BlogController;
use App\Controllers\ChapterController;
use App\Controllers\CommentController;
use App\Controllers\InstallController;
use App\Controllers\MetricsController;
use App\Controllers\RatingController;
use App\Controllers\SeriesController;
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
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null || $val === '') return $default;
        if (is_string($val)) {
            $lower = strtolower($val);
            if ($lower === 'true' || $lower === 'yes' || $lower === 'on' || $val === '1') return true;
            if ($lower === 'false' || $lower === 'no' || $lower === 'off' || $val === '0') return false;
        }
        return $val;
    }

    public static function getSettings(): array
    {
        // If cached but APP_NAME is still default, it might have been cached too early.
        // Or if we just want to be sure, we check if Dotenv has done its job.
        if (self::$cachedSettings !== null) return self::$cachedSettings;
        $basePath = dirname(__DIR__);
        self::$cachedSettings = [
            'app' => [
                'name' => (string) self::env('APP_NAME', 'NovelMangaReader'),
                'url' => (string) self::env('APP_URL', 'http://localhost:8080'),
                'env' => strtolower((string) self::env('APP_ENV', 'production')),
                'debug' => (bool) self::env('APP_DEBUG', false),
                'base_path' => $basePath,
                'root_user' => (string) self::env('ROOT_USER', 'usr00001'),
                'session_name' => 'nm_reader_session',
                'session_path' => $basePath . '/storage/sessions',
                'session_same_site' => 'Lax',
                'session_cookie_secure' => true,
                'session_lifetime_seconds' => (int) self::env('SESSION_LIFETIME', 7200),
                'refresh_token_days' => (int) self::env('REFRESH_TOKEN_DAYS', 30),
                'timezone' => (string) self::env('APP_TIMEZONE', 'UTC'),
            ],
            'database' => [
                'host' => (string) self::env('DB_HOST', '127.0.0.1'),
                'port' => (int) self::env('DB_PORT', 3306),
                'database' => (string) self::env('DB_DATABASE', 'nm-reader'),
                'username' => (string) self::env('DB_USERNAME', 'root'),
                'password' => (string) self::env('DB_PASSWORD', 'default000'),
                'charset' => (string) self::env('DB_CHARSET', 'utf8mb4'),
            ],
            'cache' => [
                'driver' => 'file',
                'path' => $basePath . '/storage/cache',
                'default_ttl' => (int) self::env('CACHE_TTL', 300),
            ],
            'system' => self::getSystemConfig(),
            'rbac' => self::getRbacConfig(),
        ];
        return self::$cachedSettings;
    }

    public static function getInstance(): array { return self::getSettings(); }

    public static function getSystemConfig(): array
    {
        return [
            'site_name' => (string) self::env('SITE_NAME', 'NovelMangaReader'),
            'site_abbreviation' => (string) self::env('SITE_ABBREVIATION', 'NMR'),
            'site_logo' => (string) self::env('SITE_LOGO', '/assets/img/logo.svg'),
            'site_description' => (string) self::env('SITE_DESCRIPTION', 'Read manga and novels for free.'),
            'enforce_https' => (bool) self::env('ENFORCE_HTTPS', false),
            'site_address' => (string) self::env('SITE_ADDRESS', 'https://example.com'),
            'default_language' => (string) self::env('DEFAULT_LANGUAGE', 'tr'),
            'default_theme' => (string) self::env('DEFAULT_THEME', 'dark'),
            'default_profile_image' => (string) self::env('DEFAULT_PROFILE_IMAGE', '/assets/img/default-profile.png'),
            'default_content_cover_image' => (string) self::env('DEFAULT_CONTENT_COVER_IMAGE', '/assets/img/covers/one-piece.jpg'),
            'integrations' => [
                'google_analytics_id' => (string) self::env('GOOGLE_ANALYTICS_ID', ''),
                'google_recaptcha_site_key' => (string) self::env('GOOGLE_RECAPTCHA_SITE_KEY', ''),
                'google_recaptcha_secret_key' => (string) self::env('GOOGLE_RECAPTCHA_SECRET_KEY', ''),
                'cloudflare_turnstile_site_key' => (string) self::env('CLOUDFLARE_TURNSTILE_SITE_KEY', ''),
                'cloudflare_turnstile_secret_key' => (string) self::env('CLOUDFLARE_TURNSTILE_SECRET_KEY', ''),
            ],
        ];
    }

    public static function getRbacConfig(): array
    {
        return [
            'roles' => [
                'admin' => ['name' => 'Administrator', 'priority' => 100, 'permissions' => ['admin.panel.access', 'admin.users.manage', 'admin.content.create', 'admin.content.update', 'admin.chapter.create', 'admin.blog.hide', 'admin.comment.delete', 'admin.logs.view', 'admin.metrics.view', 'admin.jobs.run', 'admin.settings.modify', 'admin.permissions.grant', 'admin.permissions.revoke', 'admin.roles.assign']],
                'moderator' => ['name' => 'Moderator', 'priority' => 50, 'permissions' => ['admin.panel.access', 'admin.blog.hide', 'admin.comment.delete', 'admin.content.create', 'admin.content.update', 'admin.chapter.create', 'admin.metrics.view']],
                'editor' => ['name' => 'Editor', 'priority' => 30, 'permissions' => ['admin.panel.access', 'admin.content.create', 'admin.content.update', 'admin.chapter.create', 'admin.metrics.view']],
                'user' => ['name' => 'User', 'priority' => 10, 'permissions' => []],
            ],
            'id_map' => ['admin' => 1, 'moderator' => 2, 'editor' => 3, 'user' => 4],
        ];
    }

    public static function registerRoutes(App $app): void
    {
        // 1. Mandatory Installer Routes (Registered BEFORE anything else)
        $app->get('/install-63e4qq3', [InstallController::class, 'index']);
        $app->post('/install-63e4qq3', [InstallController::class, 'process']);

        // 2. Skip other routes if .env is missing to prevent DB errors
        if (!file_exists(dirname(__DIR__) . '/.env')) {
            return;
        }

        $typePattern = 'light-novel|web-novel|novel|manga|manhua|manhwa|webtoon';
        self::registerWebRoutes($app, $typePattern);
        self::registerApiRoutes($app, $typePattern);
        self::registerAdminRoutes($app, $typePattern);
    }

    private static function registerWebRoutes(App $app, string $typePattern): void
    {
        $addWebRoutes = function (RouteCollectorProxy $group, bool $includeHome = true) use ($typePattern): void {
            if ($includeHome) $group->get('', [WebController::class, 'home']);
            $group->get('/blogs', [WebController::class, 'blog']);
            $group->get('/blogs/{slug}', [WebController::class, 'blog']);
            $group->get('/chat', [WebController::class, 'chat']);
            $group->get('/search', [WebController::class, 'search']);
            $group->get('/genre/{slug}', [WebController::class, 'genre']);
            $group->get('/tag/{slug}', [WebController::class, 'tag']);
            $group->get('/{type:' . $typePattern . '}', [WebController::class, 'listing']);
            $group->get('/{type:' . $typePattern . '}/{slug}/chapter/{chapterNumber}', [WebController::class, 'chapter']);
            $group->get('/{type:' . $typePattern . '}/{slug}', [WebController::class, 'content']);
            $group->get('/login', [WebController::class, 'login']);
            $group->get('/profile', [WebController::class, 'profile']);
            $group->get('/profile/{person:[A-Za-z0-9_]+}', [WebController::class, 'profile']);
            
            $group->get('/admin', [WebController::class, 'adminDashboard']);
            $group->get('/admin/content', [WebController::class, 'adminContent']);
            $group->get('/admin/blogs', [WebController::class, 'adminBlogs']);
            $group->get('/admin/comments', [WebController::class, 'adminComments']);
            $group->get('/admin/users', [WebController::class, 'adminUsers']);
            $group->get('/admin/ops', [WebController::class, 'adminOps']);
            $group->get('/admin/config', [WebController::class, 'adminConfig']);
            $group->get('/admin/logs', [WebController::class, 'adminLogs']);
            $group->get('/admin/tutorial', [WebController::class, 'adminTutorial']);
        };

        $app->get('/robots.txt', [WebController::class, 'robotsTxt']);
        $app->get('/sitemap.xml', [WebController::class, 'sitemapXml']);
        $app->get('/logout', [AuthController::class, 'logout']);

        $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
            return $response->withHeader('Location', '/tr')->withStatus(302);
        });

        $app->group('/{lang:tr|en}', function (RouteCollectorProxy $group) use ($addWebRoutes): void {
            $addWebRoutes($group, true);
        });
        
        $app->group('', function (RouteCollectorProxy $group) use ($addWebRoutes): void {
            $addWebRoutes($group, false);
        });
    }

    private static function registerApiRoutes(App $app, string $typePattern): void
    {
        $container = $app->getContainer();
        $cache = $container->get(CacheService::class);
        $authorization = $container->get(AuthorizationService::class);
        $users = $container->get(UserRepository::class);

        $app->group('/api/v1', function (RouteCollectorProxy $group) use ($typePattern, $cache, $authorization, $users): void {
            $group->get('/home', [SeriesController::class, 'home']);
            $group->get('/genres', [SeriesController::class, 'series_genres']);
            $group->get('/tags', [SeriesController::class, 'series_tags']);
            $group->get('/content/type/{type:' . $typePattern . '}', [SeriesController::class, 'byType']);
            $group->get('/content/{type:' . $typePattern . '}/{slug}', [SeriesController::class, 'contentByType']);
            $group->get('/content/{type:' . $typePattern . '}/{slug}/chapters', [SeriesController::class, 'chaptersByType']);
            $group->get('/genre/{slug}', [SeriesController::class, 'genre']);
            $group->get('/tag/{slug}', [SeriesController::class, 'tag']);
            $group->get('/latest-chapters', [SeriesController::class, 'latestChapters']);
            
            $group->get('/series_genres', [SeriesController::class, 'series_genres']);
            $group->get('/series_tags', [SeriesController::class, 'series_tags']);

            $group->get('/content/{type:' . $typePattern . '}/chapters', [SeriesController::class, 'latestChaptersByType']);
            $group->get('/profile/{person:[A-Za-z0-9_]+}', [UserController::class, 'publicProfile']);
            $group->get('/blogs', [BlogController::class, 'list']);
            $group->get('/blogs/{slug}', [BlogController::class, 'show'])->add(new AuthMiddleware(true, $authorization));
            $group->get('/content/{type:' . $typePattern . '}/{slug}/chapter/{chapterNumber}', [ChapterController::class, 'showByContent']);
            $group->get('/search', [SeriesController::class, 'search']);
            $group->get('/search/suggest', [SeriesController::class, 'suggest']);
            $group->get('/i18n/{lang:[a-z]{2}}', [WebController::class, 'i18nJson']);
            $group->post('/log/error', [WebController::class, 'logError']);
            $group->post('/user/activity', [ActivityController::class, 'track'])->add(new AuthMiddleware(true, $authorization));
            $group->get('/chapter/{chapterNumber}', [ChapterController::class, 'show']);
            $group->get('/chapter/{chapterId:[a-z0-9]{6}}/comments', [CommentController::class, 'list']);
            $group->get('/blogs/{slug}/comments', [CommentController::class, 'listBlog']);

            $group->post('/auth/register', [AuthController::class, 'register'])
                ->add(new RateLimitKeyedMiddleware($cache, 'register_email', 3, 600, fn ($req) => 'email:' . strtolower(trim((string) (($req->getParsedBody()['email'] ?? ''))))));
            $group->post('/auth/login', [AuthController::class, 'login'])
                ->add(new RateLimitKeyedMiddleware($cache, 'login_email', 10, 60, fn ($req) => 'email:' . strtolower(trim((string) (($req->getParsedBody()['email'] ?? ''))))));
            $group->post('/auth/refresh', [AuthController::class, 'refresh'])
                ->add(new RateLimitMiddleware($cache, 'refresh', 20, 60));

            $group->map(['GET', 'POST'], '/auth/logout', [AuthController::class, 'logout']);

            $group->group('', function (RouteCollectorProxy $secure) use ($typePattern, $users): void {
                $secure->post('/content/{type:' . $typePattern . '}/{slug}/follow', [SeriesController::class, 'followByType']);
                $secure->delete('/content/{type:' . $typePattern . '}/{slug}/follow', [SeriesController::class, 'unfollowByType']);
                $secure->post('/content/{type:' . $typePattern . '}/{slug}/rate', [RatingController::class, 'rateByType']);
                $secure->post('/chapter/{chapterId:[a-z0-9]{6}}/comment', [CommentController::class, 'create'])->add(new RestrictedActionMiddleware($users, 'commenting'));
                $secure->post('/comments/{commentId:[0-9]+}/vote', [CommentController::class, 'vote'])->add(new RestrictedActionMiddleware($users, 'voting'));
                $secure->post('/user/profile', [UserController::class, 'updateProfile']);
                $secure->get('/user/profile', [UserController::class, 'profile']);
                $secure->get('/user/history', [UserController::class, 'history']);
                $secure->get('/user/preferences', [UserController::class, 'preferences']);
                $secure->put('/user/preferences', [UserController::class, 'updatePreferences']);
                
                $secure->get('/user/follows', [SeriesController::class, 'followed']);
                $secure->get('/user/blogs', [BlogController::class, 'listMyBlogs']);
                $secure->post('/blogs', [BlogController::class, 'create'])->add(new RestrictedActionMiddleware($users, 'blog creation'));
                $secure->post('/blogs/image', [BlogController::class, 'uploadImage']);
                $secure->post('/blogs/{slug}/vote', [BlogController::class, 'vote'])->add(new RestrictedActionMiddleware($users, 'voting'));
                $secure->post('/blogs/{slug}/comments', [CommentController::class, 'createBlog'])->add(new RestrictedActionMiddleware($users, 'commenting'));
                $secure->post('/blogs/{slug}/comments/{commentId:[0-9]+}/vote', [CommentController::class, 'voteBlog'])->add(new RestrictedActionMiddleware($users, 'voting'));
                $secure->get('/auth/sessions', [AuthController::class, 'sessions']);
                $secure->delete('/auth/sessions/{sessionKey:[a-z0-9]+}', [AuthController::class, 'revokeSession']);
                $secure->get('/user/notifications', [UserController::class, 'notifications']);
                $secure->post('/user/notifications/read', [UserController::class, 'markNotificationsRead']);
                $secure->get('/user/follows/users', [UserController::class, 'followedUsers']);
                $secure->post('/user/follows/{person:[A-Za-z0-9_]+}', [UserController::class, 'follow']);
                $secure->delete('/user/follows/{person:[A-Za-z0-9_]+}', [UserController::class, 'unfollow']);
            })->add(new CsrfMiddleware())->add(new AuthMiddleware($authorization));
        });
    }

    private static function registerAdminRoutes(App $app, string $typePattern): void
    {
        $container = $app->getContainer();
        $cache = $container->get(CacheService::class);
        $authorization = $container->get(AuthorizationService::class);
        $perm = static fn (array $p): PermissionMiddleware => new PermissionMiddleware($p);

        $app->group('/api/v1/admin', function (RouteCollectorProxy $group) use ($typePattern, $perm, $cache): void {
            $group->get('/overview', [AdminConsoleController::class, 'overview'])->add($perm(['admin.panel.access']));
            $group->get('/series', [AdminConsoleController::class, 'series'])->add($perm(['admin.panel.access']));
            $group->get('/contents', [AdminConsoleController::class, 'series'])->add($perm(['admin.panel.access']));
            $group->get('/users', [AdminConsoleController::class, 'users'])->add($perm(['admin.panel.access']));
            $group->get('/blogs', [AdminConsoleController::class, 'blogs'])->add($perm(['admin.panel.access']));
            $group->get('/blogs/pending', [BlogController::class, 'pending'])->add($perm(['admin.panel.access']));
            $group->get('/comments', [AdminConsoleController::class, 'comments'])->add($perm(['admin.panel.access']));
            $group->delete('/comments/{id:[0-9]+}', [AdminConsoleController::class, 'deleteComment'])->add($perm(['admin.comment.delete']));
            $group->put('/users/{id}', [AdminConsoleController::class, 'updateUser'])->add($perm(['admin.users.manage']));
            $group->get('/rbac/roles', [AdminConsoleController::class, 'rbacRoles'])->add($perm(['admin.panel.access']));
            $group->post('/rbac/assign-role', [AdminConsoleController::class, 'assignRole'])->add($perm(['admin.roles.assign']));
            $group->get('/queue/jobs', [AdminConsoleController::class, 'queueJobs'])->add($perm(['admin.panel.access']));
            $group->post('/queue/run-once', [AdminConsoleController::class, 'runQueueOnce'])->add($perm(['admin.jobs.run']));
            $group->post('/retention/cleanup', [AdminConsoleController::class, 'cleanupRetention'])->add($perm(['admin.jobs.run']));
            $group->post('/maintenance/backup', [AdminConsoleController::class, 'triggerBackup'])->add($perm(['admin.jobs.run']));
            $group->post('/maintenance/sitemap', [AdminConsoleController::class, 'triggerSitemap'])->add($perm(['admin.jobs.run']));
            $group->post('/maintenance/warmup', [AdminConsoleController::class, 'triggerCacheWarmup'])->add($perm(['admin.jobs.run']));
            $group->post('/maintenance/analytics', [AdminConsoleController::class, 'triggerAnalytics'])->add($perm(['admin.jobs.run']));
            $group->get('/maintenance/env', [AdminConsoleController::class, 'getEnvConfig'])->add($perm(['admin.panel.access']));
            $group->post('/maintenance/env', [AdminConsoleController::class, 'saveEnvConfig'])->add($perm(['admin.panel.access']));
            $group->get('/audit-logs', [AdminConsoleController::class, 'auditLogs'])->add($perm(['admin.logs.view']));
            $group->get('/login-events', [AdminConsoleController::class, 'loginEvents'])->add($perm(['admin.logs.view']));
            $group->get('/logs/access', [AdminConsoleController::class, 'systemAccessLogs'])->add($perm(['admin.logs.view']));
            $group->get('/logs/error', [AdminConsoleController::class, 'systemErrorLogs'])->add($perm(['admin.logs.view']));
            $group->get('/stats/visits', [AdminConsoleController::class, 'siteVisits'])->add($perm(['admin.metrics.view']));
            $group->get('/stats/views', [AdminConsoleController::class, 'viewStats'])->add($perm(['admin.metrics.view']));
            $group->get('/stats/blogs', [AdminConsoleController::class, 'blogStats'])->add($perm(['admin.metrics.view']));
            $group->get('/stats/reputation', [AdminConsoleController::class, 'userReputation'])->add($perm(['admin.metrics.view']));
            $group->get('/metrics', [MetricsController::class, 'snapshot'])->add($perm(['admin.metrics.view']));
            $group->get('/dashboard', [MetricsController::class, 'snapshot'])->add($perm(['admin.metrics.view']));
            $group->get('/metrics/insights', [MetricsController::class, 'insights'])->add($perm(['admin.metrics.view']));

            $group->post('/content', [AdminController::class, 'createContent'])->add($perm(['admin.content.create']));
            $group->put('/content/{id}', [AdminController::class, 'updateContent'])->add($perm(['admin.content.update']));
            $group->post('/content/{type:' . $typePattern . '}/{slug}/chapters', [AdminController::class, 'createChapter'])->add($perm(['admin.chapter.create']));
            $group->get('/content/{id}/chapters', [AdminController::class, 'listChapters'])->add($perm(['admin.panel.access']));
            $group->delete('/chapters/{id}', [AdminController::class, 'deleteChapter'])->add($perm(['admin.content.update']));
            $group->post('/series_genres', [AdminConsoleController::class, 'createGenre'])->add($perm(['admin.content.create']));
            $group->post('/series_tags', [AdminConsoleController::class, 'createTag'])->add($perm(['admin.content.create']));
            $group->post('/blogs/{id}/approve', [BlogController::class, 'approve'])->add($perm(['admin.blog.hide']));
        })->add(new RateLimitMiddleware($cache, 'admin_api', 120, 300))
          ->add(new CsrfMiddleware())
          ->add(new AuthMiddleware($authorization));
    }
}
