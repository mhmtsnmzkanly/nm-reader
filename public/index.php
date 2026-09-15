<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($path)) {
        // Server-rendered HTML shells are controller-owned templates, not
        // directly downloadable public documents in local development.
        if (in_array($path, ['/admin.html', '/install.html', '/maintenance.html'], true)) {
            http_response_code(404);
            return true;
        }
        $file = __DIR__ . $path;
        if (is_file($file)) {
            return false;
        }

        // Direct 404 for missing static files / assets in local development
        if (
            str_starts_with($path, '/assets/') ||
            (pathinfo($path, PATHINFO_EXTENSION) !== '' &&
                !str_starts_with($path, '/api/') &&
                !str_starts_with($path, '/media/') &&
                !in_array($path, ['/robots.txt', '/sitemap.xml'], true))
        ) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control', 'no-cache, no-store, must-revalidate');
            echo '404 Not Found';
            return true;
        }
    }
}

require __DIR__ . '/../vendor/autoload.php';

$app = \App\Config::createApp();

$app->run();
