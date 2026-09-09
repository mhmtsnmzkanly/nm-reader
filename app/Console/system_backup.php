#!/usr/bin/env php
<?php

declare(strict_types=1);

/** CLI compatibility entrypoint for the native BackupService. */

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);
\App\Config::loadEnvironment($basePath);
$container = require __DIR__ . '/../dependencies.php';
$backup = $container->get(\App\Services\BackupService::class);
$result = $backup->create();
foreach ((array) ($result['output'] ?? []) as $line) {
    fwrite(STDOUT, (string) $line . PHP_EOL);
}

exit(($result['success'] ?? false) ? 0 : 1);
