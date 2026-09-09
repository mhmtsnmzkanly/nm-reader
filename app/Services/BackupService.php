<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Creates application backups without relying on shell commands.
 *
 * The database and media archives are written to temporary files first. A
 * backup is reported as successful only after both archives have been
 * finalized, so the admin panel never treats a partial backup as complete.
 */
final class BackupService
{
    private const RETENTION_DAYS = 7;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $basePath,
    ) {
    }

    /**
     * @return array{success:bool,output:list<string>,files?:array<string,string>}
     */
    public function create(): array
    {
        $backupDir = rtrim($this->basePath, '/\\') . '/storage/backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Backup directory could not be created.');
        }
        @chmod($backupDir, 0700);

        $timestamp = gmdate('Y-m-d_H-i-s');
        $output = ["--- Starting system backup [$timestamp] ---"];
        $dbPath = $backupDir . '/db_' . $timestamp . '.sql.gz';
        $mediaPath = $backupDir . '/media_' . $timestamp . '.tar.gz';

        try {
            $output[] = '[1/3] Exporting database with native PDO...';
            $this->exportDatabase($dbPath);
            $output[] = 'SUCCESS: Database exported and compressed.';

            $output[] = '[2/3] Archiving media with native PharData...';
            $this->archiveMedia($mediaPath);
            $output[] = 'SUCCESS: Media archive created.';

            $deleted = $this->cleanupOldBackups($backupDir, $timestamp);
            $output[] = '[3/3] Cleanup: removed ' . $deleted . ' old backup file(s).';
            $output[] = '--- Backup completed ---';

            return [
                'success' => true,
                'output' => $output,
                'files' => [
                    'database' => basename($dbPath),
                    'media' => basename($mediaPath),
                ],
            ];
        } catch (\Throwable $exception) {
            @unlink($dbPath);
            @unlink($mediaPath);
            $output[] = 'ERROR: ' . $exception->getMessage();
            return ['success' => false, 'output' => $output];
        }
    }

    private function exportDatabase(string $targetPath): void
    {
        $plainPath = $targetPath . '.tmp.sql';
        $handle = fopen($plainPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Database backup file could not be opened.');
        }

        try {
            fwrite($handle, "-- NM Reader PDO backup\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            $tableStmt = $this->pdo->query('SHOW FULL TABLES');
            if ($tableStmt === false) {
                throw new \RuntimeException('Database tables could not be listed.');
            }

            while ($table = $tableStmt->fetch(PDO::FETCH_NUM)) {
                $name = (string) ($table[0] ?? '');
                $kind = strtoupper((string) ($table[1] ?? 'BASE TABLE'));
                if ($name === '' || $kind !== 'BASE TABLE' || !preg_match('/^[A-Za-z0-9_$-]+$/', $name)) {
                    continue;
                }

                $quotedName = $this->quoteIdentifier($name);
                $createStmt = $this->pdo->query('SHOW CREATE TABLE ' . $quotedName);
                $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_NUM) : false;
                $createSql = is_array($createRow) ? (string) ($createRow[1] ?? '') : '';
                if ($createSql === '') {
                    throw new \RuntimeException('Table definition could not be read: ' . $name);
                }

                fwrite($handle, 'DROP TABLE IF EXISTS ' . $quotedName . ";\n" . $createSql . ";\n");
                $rows = $this->pdo->query('SELECT * FROM ' . $quotedName);
                if ($rows !== false) {
                    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                        $values = [];
                        foreach ($row as $value) {
                            $values[] = $value === null ? 'NULL' : $this->pdo->quote((string) $value);
                        }
                        fwrite($handle, 'INSERT INTO ' . $quotedName . ' VALUES (' . implode(',', $values) . ");\n");
                    }
                }
                fwrite($handle, "\n");
            }
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        $source = fopen($plainPath, 'rb');
        $gzip = gzopen($targetPath, 'wb9');
        if ($source === false || $gzip === false) {
            if (is_resource($source)) fclose($source);
            if ($gzip !== false) gzclose($gzip);
            @unlink($plainPath);
            throw new \RuntimeException('Database backup could not be compressed.');
        }
        try {
            while (!feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) throw new \RuntimeException('Database backup could not be read.');
                if ($chunk !== '' && gzwrite($gzip, $chunk) === 0) {
                    throw new \RuntimeException('Database backup could not be written.');
                }
            }
        } finally {
            fclose($source);
            gzclose($gzip);
            @unlink($plainPath);
        }
    }

    private function archiveMedia(string $targetPath): void
    {
        $mediaPath = rtrim($this->basePath, '/\\') . '/storage/media';
        if (!is_dir($mediaPath)) {
            throw new \RuntimeException('Media directory does not exist.');
        }

        $tarPath = substr($targetPath, 0, -3);
        @unlink($tarPath);
        @unlink($targetPath);
        $archive = new PharData($tarPath);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($mediaPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new \RuntimeException('Media directory contains an unsafe symbolic link.');
            }
            $relative = str_replace('\\', '/', $iterator->getSubPathName());
            if ($relative === '') continue;
            if ($file->isDir()) {
                $archive->addEmptyDir($relative);
            } else {
                $archive->addFile($file->getPathname(), $relative);
                $count++;
            }
        }
        unset($archive);

        $compressed = new PharData($tarPath);
        $compressed->compress(Phar::GZ);
        unset($compressed);
        if (!is_file($targetPath)) {
            throw new \RuntimeException('Media archive could not be compressed.');
        }
        @unlink($tarPath);
        if ($count === 0) {
            // An empty media directory is valid; the archive itself remains
            // present so restore mode can always require both backup files.
            return;
        }
    }

    private function cleanupOldBackups(string $backupDir, string $currentTimestamp): int
    {
        $cutoff = time() - self::RETENTION_DAYS * 86400;
        $deleted = 0;
        foreach (glob($backupDir . '/{db_*.sql.gz,media_*.tar.gz}', GLOB_BRACE) ?: [] as $file) {
            if (!is_file($file) || str_contains($file, $currentTimestamp)) continue;
            if ((int) (filemtime($file) ?: time()) < $cutoff && @unlink($file)) $deleted++;
        }
        return $deleted;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
