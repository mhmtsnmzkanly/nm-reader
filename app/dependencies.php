<?php

declare(strict_types=1);

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
use App\Repositories\BlogRepository;
use App\Repositories\Admin\AdminContentRepository;
use App\Repositories\Admin\AdminDashboardRepository;
use App\Repositories\Admin\AdminModerationRepository;
use App\Repositories\Admin\AdminOperationsRepository;
use App\Repositories\Admin\AdminStorageRepository;
use App\Repositories\Admin\AdminTaxonomyRepository;
use App\Repositories\Admin\AdminUserRepository;
use App\Repositories\ChapterRepository;
use App\Repositories\CommentRepository;
use App\Repositories\CommentVoteRepository;
use App\Repositories\BlogVoteRepository;
use App\Repositories\RatingRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\VoteRepository;
use App\Repositories\UserActivityRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use App\Services\AuthService;
use App\Services\Admin\AdminContentService;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\AdminModerationService;
use App\Services\Admin\AdminOperationsService;
use App\Services\Admin\AdminSettingsService;
use App\Services\Admin\AdminStorageService;
use App\Services\Admin\AdminUserService;
use App\Services\Admin\ChapterAdminService;
use App\Services\Admin\ContentAdminService;
use App\Services\Admin\TaxonomyAdminService;
use App\Services\AnalyticsService;
use App\Services\AnalyticsAggregationService;
use App\Services\BackupService;
use App\Services\AuthorizationService;
use App\Services\I18nService;
use App\Services\BlogService;
use App\Services\CacheService;
use App\Services\ChapterService;
use App\Services\CommentService;
use App\Services\EntityIdService;
use App\Services\RatingService;
use App\Services\SeriesService;
use App\Services\SiteConfigService;
use App\Services\SlugService;
use App\Services\SystemLogService;
use App\Services\UploadService;
use App\Services\UserActivityService;
use App\Services\UserService;
use App\Services\QueueService;
use App\Services\MetricsService;
use App\Services\MeService;
use App\Services\RetentionService;
use App\Services\WalletService;
use App\Services\WebContextBuilder;
use App\Services\WebUrlService;
use App\Services\WebPageRenderer;
use App\Services\HtmlTemplateService;
use App\Services\InstallService;
use App\Services\TaxonomyFormatter;
use App\Middleware\I18nMiddleware;
use App\Middleware\RequestIdMiddleware;
use DI\ContainerBuilder;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;

$builder = new ContainerBuilder();
$settings = \App\Config::getSettings();

$builder->addDefinitions([
    'settings' => $settings,

    \PDO::class => static function () use ($settings): \PDO {
        if (isset($GLOBALS['TESTING_MOCK_PDO']) && $GLOBALS['TESTING_MOCK_PDO'] instanceof \PDO) {
            return $GLOBALS['TESTING_MOCK_PDO'];
        }
        $db = $settings['database'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (!empty($db['persistent'])) {
            $options[\PDO::ATTR_PERSISTENT] = true;
        }
        return new \PDO($dsn, $db['username'], $db['password'], $options);
    },

    CacheService::class => DI\autowire(CacheService::class)
        ->constructorParameter('cachePath', $settings['cache']['path'])
        ->constructorParameter('defaultTtl', (int) $settings['cache']['default_ttl'])
        ->constructorParameter('publicPath', $settings['app']['base_path'] . '/public'),

    'logger.error' => static function () use ($settings): Logger {
        $logger = new Logger('error');
        $handler = new RotatingFileHandler($settings['app']['base_path'] . '/storage/logs/error.log', 30, Level::Warning);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));
        $logger->pushHandler($handler);
        return $logger;
    },

    'logger.access' => static function () use ($settings): Logger {
        $logger = new Logger('access');
        $handler = new RotatingFileHandler($settings['app']['base_path'] . '/storage/logs/access.log', 30, Level::Info);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));
        $logger->pushHandler($handler);
        return $logger;
    },

    'logger.audit' => static function () use ($settings): Logger {
        $logger = new Logger('audit');
        $handler = new RotatingFileHandler($settings['app']['base_path'] . '/storage/logs/audit.log', 30, Level::Info);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));
        $logger->pushHandler($handler);
        return $logger;
    },

    SystemLogService::class => DI\autowire(SystemLogService::class)
        ->constructorParameter('logPath', $settings['app']['base_path'] . '/storage/logs'),

    RequestIdMiddleware::class => DI\autowire(RequestIdMiddleware::class),
    \App\Middleware\CorsMiddleware::class => DI\autowire(\App\Middleware\CorsMiddleware::class)
        ->constructorParameter('allowedOrigins', $settings['app']['cors_allowed_origins'] ?? []),
    \App\Middleware\ApiAuthMiddleware::class => DI\autowire(\App\Middleware\ApiAuthMiddleware::class),
    \App\Middleware\SecurityHeadersMiddleware::class => DI\autowire(\App\Middleware\SecurityHeadersMiddleware::class),
    \App\Middleware\RequireVerifiedEmailMiddleware::class => DI\autowire(\App\Middleware\RequireVerifiedEmailMiddleware::class),
    I18nMiddleware::class => DI\autowire(I18nMiddleware::class),

    UserRepository::class => DI\autowire(UserRepository::class),
    BlogRepository::class => DI\autowire(BlogRepository::class),
    AdminDashboardRepository::class => DI\autowire(AdminDashboardRepository::class),
    AdminContentRepository::class => DI\autowire(AdminContentRepository::class),
    AdminUserRepository::class => DI\autowire(AdminUserRepository::class),
    AdminModerationRepository::class => DI\autowire(AdminModerationRepository::class),
    AdminStorageRepository::class => DI\autowire(AdminStorageRepository::class),
    AdminTaxonomyRepository::class => DI\autowire(AdminTaxonomyRepository::class),
    AdminOperationsRepository::class => DI\autowire(AdminOperationsRepository::class),
    SeriesRepository::class => DI\autowire(SeriesRepository::class),
    ChapterRepository::class => DI\autowire(ChapterRepository::class),
    CommentRepository::class => DI\autowire(CommentRepository::class),
    CommentVoteRepository::class => DI\autowire(CommentVoteRepository::class),
    BlogVoteRepository::class => DI\autowire(BlogVoteRepository::class),
    VoteRepository::class => DI\autowire(VoteRepository::class),
    UserActivityRepository::class => DI\autowire(UserActivityRepository::class),
    RatingRepository::class => DI\autowire(RatingRepository::class),
    UploadRepository::class => DI\autowire(UploadRepository::class),
    WalletRepository::class => DI\autowire(WalletRepository::class),
    \App\Repositories\ReportRepository::class => DI\autowire(\App\Repositories\ReportRepository::class),
    \App\Repositories\UserTokenRepository::class => DI\autowire(\App\Repositories\UserTokenRepository::class),

    AuthService::class => DI\autowire(AuthService::class)
        ->constructorParameter('sessionLifetimeSeconds', (int) ($settings['app']['session_lifetime_seconds'] ?? 7200))
        ->constructorParameter('refreshTokenDays', (int) ($settings['app']['refresh_token_days'] ?? 30)),
    AuthorizationService::class => DI\autowire(AuthorizationService::class),
    \App\Services\MailService::class => DI\autowire(\App\Services\MailService::class),
    I18nService::class => DI\autowire(I18nService::class)
        ->constructorParameter('rootPath', $settings['app']['base_path']),
    AnalyticsService::class => DI\autowire(AnalyticsService::class)
        ->constructorParameter('logger', DI\get('logger.error')),
    AnalyticsAggregationService::class => DI\autowire(AnalyticsAggregationService::class),
    BackupService::class => DI\autowire(BackupService::class)
        ->constructorParameter('basePath', $settings['app']['base_path']),
    BlogService::class => DI\autowire(BlogService::class),
    \App\Services\ReportService::class => DI\autowire(\App\Services\ReportService::class),
    SeriesService::class => DI\autowire(SeriesService::class),
    ChapterService::class => DI\autowire(ChapterService::class),
    CommentService::class => DI\autowire(CommentService::class),
    RatingService::class => DI\autowire(RatingService::class),
    EntityIdService::class => DI\autowire(EntityIdService::class),
    ContentAdminService::class => DI\autowire(ContentAdminService::class),
    ChapterAdminService::class => DI\autowire(ChapterAdminService::class),
    TaxonomyAdminService::class => DI\autowire(TaxonomyAdminService::class),
    AdminDashboardService::class => DI\autowire(AdminDashboardService::class)
        ->constructorParameter('repo', DI\get(AdminDashboardRepository::class)),
    AdminContentService::class => DI\autowire(AdminContentService::class)
        ->constructorParameter('repo', DI\get(AdminContentRepository::class)),
    AdminUserService::class => DI\autowire(AdminUserService::class)
        ->constructorParameter('repo', DI\get(AdminUserRepository::class)),
    AdminModerationService::class => DI\autowire(AdminModerationService::class)
        ->constructorParameter('repo', DI\get(AdminModerationRepository::class)),
    AdminStorageService::class => DI\autowire(AdminStorageService::class)
        ->constructorParameter('repo', DI\get(AdminStorageRepository::class)),
    AdminOperationsService::class => DI\autowire(AdminOperationsService::class)
        ->constructorParameter('repo', DI\get(AdminOperationsRepository::class)),
    AdminSettingsService::class => DI\autowire(AdminSettingsService::class)
        ->constructorParameter('repo', DI\get(AdminModerationRepository::class)),
    UserService::class => DI\autowire(UserService::class),
    MeService::class => DI\autowire(MeService::class),
    WebContextBuilder::class => DI\autowire(WebContextBuilder::class)
        ->constructorParameter('errorLogger', DI\get('logger.error')),
    WebUrlService::class => DI\autowire(WebUrlService::class),
    WebPageRenderer::class => DI\autowire(WebPageRenderer::class)
        ->constructorParameter('seoService', DI\get(\App\Services\SeoService::class)),
    HtmlTemplateService::class => DI\autowire(HtmlTemplateService::class)
        ->constructorParameter('basePath', $settings['app']['base_path']),
    InstallService::class => DI\autowire(InstallService::class)
        ->constructorParameter('settings', $settings),
    TaxonomyFormatter::class => DI\autowire(TaxonomyFormatter::class),
    UserActivityService::class => DI\autowire(UserActivityService::class),
    SlugService::class => DI\autowire(SlugService::class),
    UploadService::class => DI\autowire(UploadService::class),
    QueueService::class => DI\autowire(QueueService::class),
    MetricsService::class => DI\autowire(MetricsService::class)
        ->constructorParameter('cache', DI\get(CacheService::class)),
    RetentionService::class => DI\autowire(RetentionService::class),
    SiteConfigService::class => DI\autowire(SiteConfigService::class),
    WalletService::class => DI\autowire(WalletService::class),
    \App\Services\WebhookService::class => DI\autowire(\App\Services\WebhookService::class),
    \App\Middleware\MaintenanceMiddleware::class => DI\autowire(\App\Middleware\MaintenanceMiddleware::class)
        ->constructorParameter('trustedProxies', $settings['app']['trusted_proxies'] ?? []),
    \App\Services\MediaService::class => DI\autowire(\App\Services\MediaService::class)
        ->constructorParameter('baseUploadDir', $settings['app']['base_path'] . '/storage/media/')
        ->constructorParameter('appSecret', (string) ($settings['app']['media_secret'] ?? '')),
    \App\Services\SeoService::class => DI\autowire(\App\Services\SeoService::class)
        ->constructorParameter('basePath', $settings['app']['base_path']),
    \App\Services\ContentSecurityScanner::class => DI\autowire(\App\Services\ContentSecurityScanner::class)
        ->constructorParameter('logger', DI\get('logger.error')),
    \App\Services\SitemapService::class => static fn (\Psr\Container\ContainerInterface $c) => new \App\Services\SitemapService(
        $c->get(SiteConfigService::class),
        $c->get(SeriesService::class),
        $c->get(SeriesRepository::class),
        $c->get(BlogRepository::class),
        $c->get(CacheService::class),
        $settings
    ),

    AuthController::class => DI\autowire(AuthController::class),
    BlogController::class => DI\autowire(BlogController::class),
    ContentController::class => DI\autowire(ContentController::class),
    \App\Controllers\MediaController::class => DI\autowire(\App\Controllers\MediaController::class),
    UserInteractionController::class => DI\autowire(UserInteractionController::class),
    UserController::class => DI\autowire(UserController::class),
    AdminDashboardController::class => DI\autowire(AdminDashboardController::class),
    AdminContentController::class => DI\autowire(AdminContentController::class),
    AdminUsersController::class => DI\autowire(AdminUsersController::class),
    AdminModerationController::class => DI\autowire(AdminModerationController::class),
    AdminStorageController::class => DI\autowire(AdminStorageController::class),
    AdminOperationsController::class => DI\autowire(AdminOperationsController::class),
    AdminSettingsController::class => DI\autowire(AdminSettingsController::class),
    AdminCommerceController::class => DI\autowire(AdminCommerceController::class),
    InstallController::class => DI\autowire(InstallController::class)
        ->constructorParameter('settings', $settings),
    // Web routes are split by responsibility; shared bootstrap and shell
    // rendering are provided by WebContextBuilder/WebPageRenderer.
    ContentPageController::class => DI\autowire(ContentPageController::class),
    BlogPageController::class => DI\autowire(BlogPageController::class),
    AccountPageController::class => DI\autowire(AccountPageController::class),
    AdminShellController::class => DI\autowire(AdminShellController::class)
        ->constructorParameter('settings', $settings),
    SystemPageController::class => DI\autowire(SystemPageController::class)
        ->constructorParameter('settings', $settings)
        ->constructorParameter('errorLogger', DI\get('logger.error')),
]);

return $builder->build();
