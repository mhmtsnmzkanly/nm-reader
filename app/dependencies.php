<?php

declare(strict_types=1);

use App\Controllers\AdminPanelController;
use App\Controllers\ContentController;
use App\Controllers\UserInteractionController;
use App\Controllers\AuthController;
use App\Controllers\BlogController;
use App\Controllers\InstallController;
use App\Controllers\UserController;
use App\Controllers\WebController;
use App\Repositories\BlogRepository;
use App\Repositories\AdminConsoleRepository;
use App\Repositories\ChapterRepository;
use App\Repositories\CommentRepository;
use App\Repositories\CommentVoteRepository;
use App\Repositories\BlogVoteRepository;
use App\Repositories\UserActivityRepository;
use App\Repositories\RatingRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\AdminService;
use App\Services\AdminConsoleService;
use App\Services\AnalyticsService;
use App\Services\AnalyticsAggregationService;
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
use App\Services\RetentionService;
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
        $db = $settings['database'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
        return new \PDO($dsn, $db['username'], $db['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    },

    CacheService::class => DI\autowire(CacheService::class)
        ->constructorParameter('cachePath', $settings['cache']['path'])
        ->constructorParameter('defaultTtl', (int) $settings['cache']['default_ttl']),

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
    \App\Middleware\ApiAuthMiddleware::class => DI\autowire(\App\Middleware\ApiAuthMiddleware::class),
    I18nMiddleware::class => DI\autowire(I18nMiddleware::class),

    UserRepository::class => DI\autowire(UserRepository::class),
    BlogRepository::class => DI\autowire(BlogRepository::class),
    AdminConsoleRepository::class => DI\autowire(AdminConsoleRepository::class),
    SeriesRepository::class => DI\autowire(SeriesRepository::class),
    ChapterRepository::class => DI\autowire(ChapterRepository::class),
    CommentRepository::class => DI\autowire(CommentRepository::class),
    CommentVoteRepository::class => DI\autowire(CommentVoteRepository::class),
    BlogVoteRepository::class => DI\autowire(BlogVoteRepository::class),
    UserActivityRepository::class => DI\autowire(UserActivityRepository::class),
    RatingRepository::class => DI\autowire(RatingRepository::class),
    UploadRepository::class => DI\autowire(UploadRepository::class),

    AuthService::class => DI\autowire(AuthService::class)
        ->constructorParameter('sessionLifetimeSeconds', (int) ($settings['app']['session_lifetime_seconds'] ?? 7200))
        ->constructorParameter('refreshTokenDays', (int) ($settings['app']['refresh_token_days'] ?? 30)),
    AuthorizationService::class => DI\autowire(AuthorizationService::class),
    I18nService::class => DI\autowire(I18nService::class)
        ->constructorParameter('rootPath', $settings['app']['base_path']),
    AnalyticsService::class => DI\autowire(AnalyticsService::class)
        ->constructorParameter('logger', DI\get('logger.error')),
    AnalyticsAggregationService::class => DI\autowire(AnalyticsAggregationService::class),
    BlogService::class => DI\autowire(BlogService::class),
    SeriesService::class => DI\autowire(SeriesService::class),
    ChapterService::class => DI\autowire(ChapterService::class),
    CommentService::class => DI\autowire(CommentService::class),
    RatingService::class => DI\autowire(RatingService::class),
    EntityIdService::class => DI\autowire(EntityIdService::class),
    AdminService::class => DI\autowire(AdminService::class),
    AdminConsoleService::class => DI\autowire(AdminConsoleService::class),
    UserService::class => DI\autowire(UserService::class),
    UserActivityService::class => DI\autowire(UserActivityService::class),
    SlugService::class => DI\autowire(SlugService::class),
    UploadService::class => DI\autowire(UploadService::class),
    QueueService::class => DI\autowire(QueueService::class),
    MetricsService::class => DI\autowire(MetricsService::class)
        ->constructorParameter('cache', DI\get(CacheService::class)),
    RetentionService::class => DI\autowire(RetentionService::class),
    SiteConfigService::class => DI\autowire(SiteConfigService::class),

    AuthController::class => DI\autowire(AuthController::class),
    BlogController::class => DI\autowire(BlogController::class),
    ContentController::class => DI\autowire(ContentController::class),
    UserInteractionController::class => DI\autowire(UserInteractionController::class),
    UserController::class => DI\autowire(UserController::class),
    AdminPanelController::class => DI\autowire(AdminPanelController::class),
    InstallController::class => static fn () => new InstallController($settings),
    WebController::class => static fn (\Psr\Container\ContainerInterface $c) => new WebController(
        $settings, 
        $c->get(SiteConfigService::class),
        $c->get(AuthorizationService::class),
        $c->get(SeriesService::class),
        $c->get(UserService::class),
        $c->get(SeriesRepository::class),
        $c->get(BlogRepository::class),
        $c->get(I18nService::class),
        $c->get('logger.error')
    ),
]);

return $builder->build();
