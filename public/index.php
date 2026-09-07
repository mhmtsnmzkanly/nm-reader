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
    }
}

require __DIR__ . '/../vendor/autoload.php';

$app = \App\Config::createApp();

$app->run();
