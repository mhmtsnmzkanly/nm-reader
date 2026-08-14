#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace App\Console;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dotenv\Dotenv;

/**
 * CLI Tool & Diagnostic Class for Storage Read/Write Validation.
 * Usage: php app/Console/StorageCheck.php
 * Composer: composer check:storage
 */
final class StorageCheck
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? dirname(__DIR__, 2), '/');
        
        // Load Environment if available
        if (file_exists($this->basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($this->basePath);
            $dotenv->safeLoad();
        }
    }

    /**
     * Resolves all canonical storage roots used by the application.
     *
     * @return array<string, array{name: string, path: string, critical: bool, description: string}>
     */
    public function getStorageRoots(): array
    {
        return [
            'media' => [
                'name' => 'Public & Chapter Media Storage',
                'path' => $this->basePath . '/storage/media',
                'critical' => true,
                'description' => 'Serves public assets (/media/public/*) and protected chapter tokens (/media/chapter/t_*)'
            ],
            'sessions' => [
                'name' => 'Session Storage',
                'path' => $this->basePath . '/storage/sessions',
                'critical' => true,
                'description' => 'PHP Session state and authentication tokens'
            ],
            'logs' => [
                'name' => 'System Logs Storage',
                'path' => $this->basePath . '/storage/logs',
                'critical' => true,
                'description' => 'Monolog application, error, and audit log files'
            ],
            'cache' => [
                'name' => 'Cache Storage',
                'path' => $this->basePath . '/storage/cache',
                'critical' => false,
                'description' => 'File-based query and metadata caching'
            ],
            'backups' => [
                'name' => 'Backups Storage',
                'path' => $this->basePath . '/storage/backups',
                'critical' => false,
                'description' => 'Automated database and media archive outputs'
            ],
        ];
    }

    /**
     * Executes the diagnostic checks on a target directory.
     *
     * @param string $dirPath
     * @return array{
     *   exists: bool,
     *   directory: bool,
     *   readable: bool,
     *   writable: bool,
     *   writeProbe: bool,
     *   readProbe: bool,
     *   deleteProbe: bool,
     *   passed: bool,
     *   error: ?string,
     *   parentPath: ?string,
     *   parentExists: bool,
     *   parentWritable: bool
     * }
     */
    public function probeDirectory(string $dirPath): array
    {
        $result = [
            'exists' => false,
            'directory' => false,
            'readable' => false,
            'writable' => false,
            'writeProbe' => false,
            'readProbe' => false,
            'deleteProbe' => false,
            'passed' => false,
            'error' => null,
            'parentPath' => dirname($dirPath),
            'parentExists' => file_exists(dirname($dirPath)),
            'parentWritable' => is_writable(dirname($dirPath))
        ];

        // 1. Existence check
        if (!file_exists($dirPath)) {
            $result['error'] = 'DIRECTORY_NOT_FOUND';
            return $result;
        }
        $result['exists'] = true;

        // 2. Directory check
        if (!is_dir($dirPath)) {
            $result['error'] = 'PATH_IS_NOT_A_DIRECTORY';
            return $result;
        }
        $result['directory'] = true;

        // 3. Readability check
        if (!is_readable($dirPath)) {
            $result['error'] = 'DIRECTORY_NOT_READABLE';
            return $result;
        }
        $result['readable'] = true;

        // 4. Writability flag check
        if (!is_writable($dirPath)) {
            $result['error'] = 'DIRECTORY_PERMISSION_DENIED';
            return $result;
        }
        $result['writable'] = true;

        // 5. Active Write, Read, and Delete Probe
        $probePayload = 'STORAGE_PROBE_' . bin2hex(random_bytes(16)) . '_' . microtime(true);
        $probeFileName = '.diagnostic_probe_' . bin2hex(random_bytes(8)) . '.tmp';
        $probeFilePath = rtrim($dirPath, '/') . '/' . $probeFileName;

        try {
            // Write Probe
            $bytesWritten = @file_put_contents($probeFilePath, $probePayload);
            if ($bytesWritten === false || $bytesWritten !== strlen($probePayload)) {
                $result['error'] = 'WRITE_PROBE_FAILED';
                if (file_exists($probeFilePath)) {
                    @unlink($probeFilePath);
                }
                return $result;
            }
            $result['writeProbe'] = true;

            // Read Probe
            $readContent = @file_get_contents($probeFilePath);
            if ($readContent !== $probePayload) {
                $result['error'] = 'READ_PROBE_CORRUPTED';
                @unlink($probeFilePath);
                return $result;
            }
            $result['readProbe'] = true;

            // Delete Probe
            $deleted = @unlink($probeFilePath);
            if (!$deleted || file_exists($probeFilePath)) {
                $result['error'] = 'DELETE_PROBE_FAILED';
                return $result;
            }
            $result['deleteProbe'] = true;

            // All checks succeeded
            $result['passed'] = true;
        } catch (\Throwable $e) {
            $result['error'] = 'PROBE_EXCEPTION: ' . $e->getMessage();
            if (file_exists($probeFilePath)) {
                @unlink($probeFilePath);
            }
        }

        return $result;
    }

    /**
     * Resolves the active PHP process user.
     */
    public function getProcessUser(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $userInfo = posix_getpwuid(posix_geteuid());
            if (is_array($userInfo) && isset($userInfo['name'])) {
                return (string) $userInfo['name'];
            }
        }

        $envUser = getenv('USER') ?: getenv('USERNAME');
        if ($envUser) {
            return (string) $envUser;
        }

        if (function_exists('get_current_user')) {
            $u = get_current_user();
            if ($u !== '') {
                return $u;
            }
        }

        return 'unknown';
    }

    /**
     * Runs the full diagnostic suite and prints the report to STDOUT.
     *
     * @param bool $silent
     * @return int 0 if all critical roots pass, 1 if any critical root fails.
     */
    public function run(bool $silent = false): int
    {
        $phpVersion = PHP_VERSION;
        $phpUser = $this->getProcessUser();
        $roots = $this->getStorageRoots();

        $overallSuccess = true;

        if (!$silent) {
            echo "==============================================================\n";
            echo "              NM-READER STORAGE DIAGNOSTIC                    \n";
            echo "==============================================================\n\n";
            echo "Environment:\n";
            echo "  PHP Version:  {$phpVersion}\n";
            echo "  Process User: {$phpUser}\n";
            echo "  Base Path:    {$this->basePath}\n\n";
        }

        foreach ($roots as $key => $root) {
            $probe = $this->probeDirectory($root['path']);

            if (!$probe['passed']) {
                $overallSuccess = false;
            }

            if (!$silent) {
                echo "--------------------------------------------------------------\n";
                echo "Target: [{$key}] {$root['name']}\n";
                echo "Path:   {$root['path']}\n";
                echo "Role:   {$root['description']}\n";
                echo "--------------------------------------------------------------\n";
                echo "  Exists:     " . ($probe['exists'] ? "[PASS]" : "[FAIL]") . "\n";
                echo "  Directory:  " . ($probe['exists'] ? ($probe['directory'] ? "[PASS]" : "[FAIL]") : "[SKIP]") . "\n";
                echo "  Readable:   " . ($probe['directory'] ? ($probe['readable'] ? "[PASS]" : "[FAIL]") : "[SKIP]") . "\n";
                echo "  Writable:   " . ($probe['readable'] ? ($probe['writable'] ? "[PASS]" : "[FAIL]") : "[SKIP]") . "\n";
                echo "  Write:      " . ($probe['writable'] ? ($probe['writeProbe'] ? "[PASS]" : "[FAIL]") : "[SKIP]") . "\n";
                echo "  Read:       " . ($probe['writeProbe'] ? ($probe['readProbe'] ? "[PASS]" : "[FAIL]") : "[SKIP]") . "\n";
                echo "  Delete:     " . ($probe['readProbe'] ? ($probe['deleteProbe'] ? "[PASS]" : "[FAIL]") : "[SKIP]") . "\n";

                if ($probe['passed']) {
                    echo "  Status:     >>> HEALTHY <<<\n\n";
                } else {
                    echo "  Status:     >>> UNHEALTHY <<<\n";
                    echo "  ERROR:      " . ($probe['error'] ?? 'UNKNOWN_ERROR') . "\n";
                    if (!$probe['exists']) {
                        echo "  Parent Dir: " . ($probe['parentExists'] ? "EXISTS ({$probe['parentPath']})" : "NOT FOUND") . "\n";
                        echo "  Parent Writable: " . ($probe['parentWritable'] ? "YES" : "NO") . "\n";
                    }
                    echo "\n";
                }
            }
        }

        if (!$silent) {
            echo "==============================================================\n";
            echo "DIAGNOSTIC RESULT: " . ($overallSuccess ? "PASS (All storage targets verified)" : "FAIL (Storage errors detected)") . "\n";
            echo "==============================================================\n\n";
        }

        return $overallSuccess ? 0 : 1;
    }
}

// Execute when invoked directly from CLI
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $checker = new StorageCheck();
    $exitCode = $checker->run();
    exit($exitCode);
}
