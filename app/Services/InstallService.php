<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\SiteConfigService;
use PDO;
use PharData;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Installation-time validation and commit workflow.
 *
 * Validation methods are side-effect free with regard to the application
 * installation: they only test connections, inspect uploaded files, and
 * return normalized data. The complete() method is the sole commit point.
 */
final class InstallService
{
    public const MODE_FRESH = 'fresh';
    public const MODE_RESTORE = 'restore';
    public const MODE_ENV_ONLY = 'env_only';

    private const ENV_KEYS = [
        'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'SITE_ADDRESS', 'APP_TIMEZONE',
        'CORS_ALLOWED_ORIGINS', 'SESSION_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL',
        'SESSION_COOKIE_SECURE', 'SESSION_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE',
        'REMEMBER_COOKIE_SAME_SITE', 'ENFORCE_HTTPS', 'TRUSTED_PROXIES', 'DB_PERSISTENT',
        'RESEND_API_KEY', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY',
        'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SITE_KEY',
        'CLOUDFLARE_TURNSTILE_SECRET_KEY', 'DB_HOST', 'DB_PORT', 'DB_DATABASE',
        'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET',
    ];

    private const BOOLEAN_ENV_KEYS = [
        'APP_DEBUG', 'SESSION_COOKIE_SECURE', 'REMEMBER_COOKIE_SECURE',
        'ENFORCE_HTTPS', 'DB_PERSISTENT',
    ];

    private const INTEGER_ENV_KEYS = ['SESSION_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL', 'DB_PORT'];

    private const SITE_ENV_DUPLICATES = ['site_address', 'enforce_https'];

    public function __construct(private readonly array $settings)
    {
    }

    /** @return array<string, mixed> */
    public static function environmentDefaults(): array
    {
        return [
            'APP_NAME' => 'NovelMangaReader',
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => 'http://localhost:8080',
            'SITE_ADDRESS' => '',
            'APP_TIMEZONE' => 'UTC',
            'CORS_ALLOWED_ORIGINS' => 'http://localhost:8080,http://localhost:3000',
            'SESSION_LIFETIME' => 7200,
            'REFRESH_TOKEN_DAYS' => 30,
            'CACHE_TTL' => 300,
            'SESSION_COOKIE_SECURE' => false,
            'SESSION_COOKIE_SAME_SITE' => 'Lax',
            'REMEMBER_COOKIE_SECURE' => false,
            'REMEMBER_COOKIE_SAME_SITE' => 'Lax',
            'ENFORCE_HTTPS' => false,
            'TRUSTED_PROXIES' => '',
            'DB_PERSISTENT' => false,
            'RESEND_API_KEY' => '',
            'GOOGLE_ANALYTICS_ID' => '',
            'GOOGLE_RECAPTCHA_SITE_KEY' => '',
            'GOOGLE_RECAPTCHA_SECRET_KEY' => '',
            'CLOUDFLARE_TURNSTILE_SITE_KEY' => '',
            'CLOUDFLARE_TURNSTILE_SECRET_KEY' => '',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => 3306,
            'DB_DATABASE' => 'nm-reader',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
        ];
    }

    /** @return array<string, mixed> */
    public static function siteDefaults(): array
    {
        return array_diff_key(
            SiteConfigService::defaultValues(),
            array_flip(self::SITE_ENV_DUPLICATES)
        );
    }

    /**
     * Validates environment values and tests the supplied database connection.
     *
     * @param array<string, mixed> $input
     * @return array{environment:array<string,mixed>,database:array<string,mixed>}
     */
    public function validateEnvironment(array $input): array
    {
        $values = array_replace(self::environmentDefaults(), array_intersect_key($input, array_flip(self::ENV_KEYS)));

        foreach (self::BOOLEAN_ENV_KEYS as $key) {
            $values[$key] = $this->toBool($key, $values[$key]);
        }
        foreach (self::INTEGER_ENV_KEYS as $key) {
            $values[$key] = $this->toPositiveInt($key, $values[$key]);
        }
        if ($values['DB_PORT'] > 65535) throw new \InvalidArgumentException('DB_PORT must be between 1 and 65535.');

        $values['APP_NAME'] = $this->toString('APP_NAME', $values['APP_NAME'], 120);
        $values['APP_ENV'] = strtolower($this->toString('APP_ENV', $values['APP_ENV'], 32));
        if (!in_array($values['APP_ENV'], ['local', 'staging', 'production'], true)) {
            throw new \InvalidArgumentException('APP_ENV must be local, staging, or production.');
        }
        $values['APP_URL'] = $this->url('APP_URL', $values['APP_URL'], false);
        $values['SITE_ADDRESS'] = $this->url('SITE_ADDRESS', $values['SITE_ADDRESS'], true);
        $values['APP_TIMEZONE'] = $this->timezone((string) $values['APP_TIMEZONE']);
        $values['CORS_ALLOWED_ORIGINS'] = $this->origins((string) $values['CORS_ALLOWED_ORIGINS']);
        $values['SESSION_COOKIE_SAME_SITE'] = $this->sameSite('SESSION_COOKIE_SAME_SITE', $values['SESSION_COOKIE_SAME_SITE']);
        $values['REMEMBER_COOKIE_SAME_SITE'] = $this->sameSite('REMEMBER_COOKIE_SAME_SITE', $values['REMEMBER_COOKIE_SAME_SITE']);

        $values['DB_HOST'] = $this->toString('DB_HOST', $values['DB_HOST'], 255);
        if (!preg_match('/^[A-Za-z0-9._:\[\]-]+$/', $values['DB_HOST'])) {
            throw new \InvalidArgumentException('DB_HOST contains unsupported characters.');
        }
        $values['DB_DATABASE'] = $this->toString('DB_DATABASE', $values['DB_DATABASE'], 64);
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $values['DB_DATABASE'])) {
            throw new \InvalidArgumentException('DB_DATABASE contains unsupported characters.');
        }
        $values['DB_USERNAME'] = $this->toString('DB_USERNAME', $values['DB_USERNAME'], 150);
        $values['DB_PASSWORD'] = $this->toString('DB_PASSWORD', $values['DB_PASSWORD'], 512, false);
        $values['DB_CHARSET'] = $this->toString('DB_CHARSET', $values['DB_CHARSET'], 32);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $values['DB_CHARSET'])) {
            throw new \InvalidArgumentException('DB_CHARSET contains unsupported characters.');
        }

        foreach (['TRUSTED_PROXIES', 'RESEND_API_KEY', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY',
            'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SITE_KEY',
            'CLOUDFLARE_TURNSTILE_SECRET_KEY'] as $key) {
            $values[$key] = $this->toString($key, $values[$key], 2048, false);
        }

        $pdo = $this->connect($values);
        $version = (string) ($pdo->query('SELECT VERSION()')->fetchColumn() ?: 'unknown');
        $tables = $this->tableNames($pdo);

        $basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
        if (!is_dir($basePath) || !is_writable($basePath)) {
            throw new \RuntimeException('Application root is not writable.');
        }
        $schema = $basePath . '/app/database/schema.sql';
        if (!is_file($schema) || !is_readable($schema)) {
            throw new \RuntimeException('app/database/schema.sql is missing or unreadable.');
        }

        return [
            'environment' => $values,
            'database' => [
                'server_version' => $version,
                'tables' => $tables,
                'existing_table_count' => count($tables),
                'required_tables' => ['users', 'system_settings', 'schema_migrations'],
            ],
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function validateSiteSettings(array $payload): array
    {
        foreach (self::SITE_ENV_DUPLICATES as $key) {
            unset($payload[$key]);
        }
        $input = array_replace(self::siteDefaults(), $payload);
        return SiteConfigService::validateInput($input);
    }

    /** @param array<string, mixed> $environment */
    public function inspectExistingDatabase(array $environment): array
    {
        $pdo = $this->connect($environment);
        $tables = $this->tableNames($pdo);
        $required = ['users', 'system_settings', 'schema_migrations'];
        $missing = array_values(array_diff($required, $tables));
        $users = [];
        if (in_array('users', $tables, true)) {
            $stmt = $pdo->query('SELECT id, username, email, roles FROM users ORDER BY created_at ASC LIMIT 100');
            $users = $stmt ? $stmt->fetchAll() : [];
        }

        return [
            'tables' => $tables,
            'missing_tables' => $missing,
            'schema_ready' => $missing === [],
            'users' => is_array($users) ? $users : [],
        ];
    }

    /** @param array<string, mixed> $root @return array<string, mixed> */
    public function validateRoot(array $root, string $mode, array $inspection = []): array
    {
        if ($mode === self::MODE_FRESH) {
            $username = trim((string) ($root['username'] ?? ''));
            $email = trim((string) ($root['email'] ?? ''));
            $password = (string) ($root['password'] ?? '');
            $confirmation = (string) ($root['password_confirmation'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
                throw new \InvalidArgumentException('Root username must contain 3-30 letters, numbers, or underscores.');
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('A valid root email is required.');
            }
            if (strlen($password) < 12) {
                throw new \InvalidArgumentException('Root password must be at least 12 characters.');
            }
            if (!hash_equals($password, $confirmation)) {
                throw new \InvalidArgumentException('Root passwords do not match.');
            }
            return ['username' => $username, 'email' => $email, 'password' => $password];
        }

        $id = strtolower(trim((string) ($root['user_id'] ?? '')));
        if (!preg_match('/^[a-z0-9]{8}$/', $id)) {
            throw new \InvalidArgumentException('A valid eight-character root user ID is required.');
        }
        if ($mode === self::MODE_ENV_ONLY && ($inspection['schema_ready'] ?? false) !== true) {
            throw new \InvalidArgumentException('The existing database does not contain the required NM Reader schema.');
        }
        if ($mode === self::MODE_ENV_ONLY) {
            $found = false;
            foreach ((array) ($inspection['users'] ?? []) as $user) {
                if ((string) ($user['id'] ?? '') === $id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) throw new \InvalidArgumentException('Selected root user was not found in the database.');
        }
        return ['user_id' => $id];
    }

    /**
     * Stores an uploaded backup outside the public directory after basic
     * archive validation. The file is only consumed by complete().
     *
     * @return array{path:string,name:string,size:int,kind:string}
     */
    public function storeBackupUpload(UploadedFileInterface $file, string $kind): array
    {
        if (!in_array($kind, ['database', 'media'], true)) {
            throw new \InvalidArgumentException('Unsupported backup upload type.');
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Backup upload failed.');
        }
        $name = basename((string) $file->getClientFilename());
        $lowerName = strtolower($name);
        $valid = $kind === 'database'
            ? (str_ends_with($lowerName, '.sql') || str_ends_with($lowerName, '.sql.gz'))
            : (str_ends_with($lowerName, '.tar.gz') || str_ends_with($lowerName, '.tgz'));
        if (!$valid) throw new \InvalidArgumentException('Unsupported backup file format.');

        $size = (int) ($file->getSize() ?? 0);
        $max = $kind === 'database' ? 512 * 1024 * 1024 : 4 * 1024 * 1024 * 1024;
        if ($size <= 0 || $size > $max) throw new \InvalidArgumentException('Backup file size is outside the allowed range.');

        $basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
        $directory = $basePath . '/storage/install/uploads';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create the private installer upload directory.');
        }
        $path = $directory . '/' . bin2hex(random_bytes(16)) . ($kind === 'database' ? '.sql' . (str_ends_with($lowerName, '.gz') ? '.gz' : '') : '.tar.gz');
        $file->moveTo($path);

        try {
            if ($kind === 'database') {
                $this->validateSqlArchive($path);
            } else {
                $this->validateMediaArchive($path);
            }
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => $name, 'size' => $size, 'kind' => $kind];
    }

    /**
     * Applies the complete installation state. No caller should invoke this
     * until every validation step has passed.
     *
     * @param array<string, mixed> $state
     * @return array{mode:string,root_user_id:string,root_username:string,message:string}
     */
    public function complete(array $state): array
    {
        $mode = (string) ($state['mode'] ?? '');
        if (!in_array($mode, [self::MODE_FRESH, self::MODE_RESTORE, self::MODE_ENV_ONLY], true)) {
            throw new \InvalidArgumentException('Invalid installation mode.');
        }
        $environmentResult = $this->validateEnvironment((array) ($state['environment'] ?? []));
        $environment = $environmentResult['environment'];
        $siteSettings = $this->validateSiteSettings((array) ($state['site_settings'] ?? []));
        $root = $this->validateRoot((array) ($state['root'] ?? []), $mode, (array) ($state['database_inspection'] ?? []));

        $basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
        $installDir = $basePath . '/storage/install';
        if (!is_dir($installDir) && !mkdir($installDir, 0700, true) && !is_dir($installDir)) {
            throw new \RuntimeException('Could not create the private installer state directory.');
        }
        $lock = fopen($installDir . '/install.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new \RuntimeException('Another installation is already in progress.');
        }

        $pendingEnv = $basePath . '/.env.installing-' . bin2hex(random_bytes(8));
        $mediaStage = null;
        $oldMedia = null;
        $rootId = (string) ($root['user_id'] ?? '');
        $rootUser = [];
        try {
            if (is_file($basePath . '/.env')) throw new \RuntimeException('System is already installed.');
            $pdo = $this->connect($environment);
            $tables = $this->tableNames($pdo);
            $this->assertExistingTableConfirmation(
                $mode,
                $environment,
                $tables,
                (string) ($state['existing_confirmation'] ?? '')
            );

            if ($mode === self::MODE_FRESH) {
                $this->importSchema($pdo);
                $pdo->beginTransaction();
                try {
                    $this->writeSiteSettings($pdo, $siteSettings);
                    $rootId = $this->createRootUser($pdo, $root);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $exception;
                }
            } elseif ($mode === self::MODE_RESTORE) {
                $databaseUpload = (array) ($state['backup']['database'] ?? []);
                $mediaUpload = (array) ($state['backup']['media'] ?? []);
                if ($databaseUpload === [] || $mediaUpload === []) {
                    throw new \InvalidArgumentException('Both database and media backup files are required for restore.');
                }
                $mediaStage = $this->extractMediaArchive((string) ($mediaUpload['path'] ?? ''), $basePath . '/storage/media.installing-' . bin2hex(random_bytes(8)));
                $this->importSqlArchive($pdo, (string) ($databaseUpload['path'] ?? ''));
                $rootUser = $this->assertUserExists($pdo, $rootId);
                $mediaDirectory = $basePath . '/storage/media';
                $oldMedia = $basePath . '/storage/media.previous-' . bin2hex(random_bytes(8));
                if (is_dir($mediaDirectory)) rename($mediaDirectory, $oldMedia);
                if (!rename($mediaStage, $mediaDirectory)) throw new \RuntimeException('Could not activate restored media.');
                $mediaStage = null;
            } else {
                $inspection = $this->inspectExistingDatabase($environment);
                if (!$inspection['schema_ready']) throw new \RuntimeException('Existing database schema is incomplete.');
                $rootUser = $this->assertUserExists($pdo, $rootId);
            }

            $envContent = $this->generateEnv($environment, $rootId);
            if (file_put_contents($pendingEnv, $envContent, LOCK_EX) === false) {
                throw new \RuntimeException('Could not write the pending environment file.');
            }
            @chmod($pendingEnv, 0600);
            if (is_file($basePath . '/.env')) throw new \RuntimeException('System is already installed.');
            if (!rename($pendingEnv, $basePath . '/.env')) {
                throw new \RuntimeException('Could not activate the environment file.');
            }
            $pendingEnv = '';
            if ($oldMedia !== null) $this->removeDirectory($oldMedia);

            return [
                'mode' => $mode,
                'root_user_id' => $rootId,
                'root_username' => (string) ($rootUser['username'] ?? ($root['username'] ?? '')),
                'message' => 'Installation completed successfully.',
            ];
        } catch (\Throwable $exception) {
            if ($pendingEnv !== '' && is_file($pendingEnv)) @unlink($pendingEnv);
            if ($mediaStage !== null && is_dir($mediaStage)) $this->removeDirectory($mediaStage);
            if ($oldMedia !== null && is_dir($oldMedia)) {
                $mediaDirectory = $basePath . '/storage/media';
                if (is_dir($mediaDirectory)) $this->removeDirectory($mediaDirectory);
                @rename($oldMedia, $mediaDirectory);
            }
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string, mixed> $environment */
    private function connect(array $environment): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $environment['DB_HOST'],
            (int) $environment['DB_PORT'],
            $environment['DB_DATABASE'],
            $environment['DB_CHARSET']
        );
        return new PDO($dsn, (string) $environment['DB_USERNAME'], (string) $environment['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @return list<string> */
    private function tableNames(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        return array_values(array_map('strval', is_array($tables) ? $tables : []));
    }

    private function importSchema(PDO $pdo): void
    {
        $basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
        $sql = file_get_contents($basePath . '/app/database/schema.sql');
        if ($sql === false || trim($sql) === '') throw new \RuntimeException('Schema file is empty or unreadable.');
        $pdo->exec($sql);
    }

    /** @param array<string, mixed> $settings */
    private function writeSiteSettings(PDO $pdo, array $settings): void
    {
        $definitions = SiteConfigService::settingDefinitions();
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (`group`, `key`, `type`, `value`, updated_at)
             VALUES (:grp, :key, :typ, :val, NOW())
             ON DUPLICATE KEY UPDATE `group` = VALUES(`group`), `type` = VALUES(`type`), `value` = VALUES(`value`), updated_at = NOW()'
        );
        foreach ($settings as $key => $value) {
            if (!isset($definitions[$key]) || in_array($key, self::SITE_ENV_DUPLICATES, true)) continue;
            $definition = $definitions[$key];
            $serialized = match ($definition['type']) {
                'bool' => $value ? 'true' : 'false',
                'int' => (string) $value,
                'json' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                default => (string) $value,
            };
            $stmt->execute(['grp' => $definition['group'], 'key' => $key, 'typ' => $definition['type'], 'val' => $serialized]);
        }
    }

    /** @param array<string, mixed> $root */
    private function createRootUser(PDO $pdo, array $root): string
    {
        $id = EntityIdService::generate();
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, username, email, email_verified_at, password_hash, roles, created_at)
             VALUES (:id, :username, :email, NOW(), :password_hash, :roles, NOW())'
        );
        $stmt->execute([
            'id' => $id,
            'username' => $root['username'],
            'email' => $root['email'],
            'password_hash' => password_hash((string) $root['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'roles' => '1',
        ]);
        return $id;
    }

    /** @return array{id:string,username:string,email:string,roles:string} */
    private function assertUserExists(PDO $pdo, string $id): array
    {
        $stmt = $pdo->prepare('SELECT id, username, email, roles FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!is_array($user)) throw new \InvalidArgumentException('Selected root user was not found after restore.');
        return [
            'id' => (string) ($user['id'] ?? $id),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'roles' => (string) ($user['roles'] ?? ''),
        ];
    }

    /** @param list<string> $tables */
    private function assertExistingTableConfirmation(string $mode, array $environment, array $tables, string $confirmation): void
    {
        if (in_array($mode, [self::MODE_FRESH, self::MODE_RESTORE], true)
            && $tables !== []
            && $confirmation !== (string) $environment['DB_DATABASE']) {
            throw new \InvalidArgumentException('The target database is not empty. Type its name to confirm replacement.');
        }
    }

    private function validateSqlArchive(string $path): void
    {
        if (!is_file($path) || filesize($path) <= 0) throw new \InvalidArgumentException('Database backup is empty.');
        if (str_ends_with(strtolower($path), '.gz')) {
            $raw = file_get_contents($path);
            if ($raw === false || @gzdecode($raw) === false) throw new \InvalidArgumentException('Database gzip archive is invalid.');
        } else {
            $raw = file_get_contents($path, false, null, 0, 4096);
            if ($raw === false || trim($raw) === '') throw new \InvalidArgumentException('Database SQL archive is empty.');
        }
    }

    private function validateMediaArchive(string $path): void
    {
        try {
            $archive = new PharData($path);
            $iterator = new \RecursiveIteratorIterator($archive);
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isLink()) {
                    throw new \InvalidArgumentException('Media archive contains an unsafe link.');
                }
                $name = str_replace('\\', '/', $iterator->getSubPathName());
                if ($name === '' || str_starts_with($name, '/') || preg_match('~(^|/)\.\.?(/|$)|(^|/)\.\.?$~', $name)) {
                    throw new \InvalidArgumentException('Media archive contains an unsafe path.');
                }
            }
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Media TAR archive is invalid.', 0, $exception);
        }
    }

    private function importSqlArchive(PDO $pdo, string $path): void
    {
        if (!is_file($path)) throw new \InvalidArgumentException('Database backup file is missing.');
        $sql = str_ends_with(strtolower($path), '.gz') ? @gzdecode((string) file_get_contents($path)) : file_get_contents($path);
        if ($sql === false || $sql === null || trim($sql) === '') throw new \InvalidArgumentException('Database backup could not be read.');
        $pdo->exec($sql);
    }

    private function extractMediaArchive(string $path, string $destination): string
    {
        if (!is_file($path)) throw new \InvalidArgumentException('Media backup file is missing.');
        if (!mkdir($destination, 0700, true) && !is_dir($destination)) throw new \RuntimeException('Could not create media staging directory.');
        try {
            $archive = new PharData($path);
            if (str_ends_with(strtolower($path), '.gz')) {
                $tarPath = substr($path, 0, -3);
                if (!is_file($tarPath)) $archive->decompress();
                $archive = new PharData($tarPath);
            }
            $archive->extractTo($destination, null, true);
            return $destination;
        } catch (\Throwable $exception) {
            $this->removeDirectory($destination);
            throw new \InvalidArgumentException('Media backup could not be extracted.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $environment */
    private function generateEnv(array $environment, string $rootId): string
    {
        $mediaSecret = bin2hex(random_bytes(32));
        $value = fn (mixed $input): string => $this->envValue((string) $input);
        $bool = fn (mixed $input): string => $input ? 'true' : 'false';
        $lines = [
            '# Generated by NM Reader installer',
            'APP_NAME=' . $value($environment['APP_NAME']),
            'APP_ENV=' . $value($environment['APP_ENV']),
            'APP_DEBUG=' . $bool($environment['APP_DEBUG']),
            'APP_URL=' . $value($environment['APP_URL']),
            'SITE_ADDRESS=' . $value($environment['SITE_ADDRESS']),
            'APP_TIMEZONE=' . $value($environment['APP_TIMEZONE']),
            'CORS_ALLOWED_ORIGINS=' . $value($environment['CORS_ALLOWED_ORIGINS']),
            'SESSION_LIFETIME=' . (int) $environment['SESSION_LIFETIME'],
            'REFRESH_TOKEN_DAYS=' . (int) $environment['REFRESH_TOKEN_DAYS'],
            'CACHE_TTL=' . (int) $environment['CACHE_TTL'],
            'SESSION_COOKIE_SECURE=' . $bool($environment['SESSION_COOKIE_SECURE']),
            'SESSION_COOKIE_SAME_SITE=' . $value($environment['SESSION_COOKIE_SAME_SITE']),
            'REMEMBER_COOKIE_SECURE=' . $bool($environment['REMEMBER_COOKIE_SECURE']),
            'REMEMBER_COOKIE_SAME_SITE=' . $value($environment['REMEMBER_COOKIE_SAME_SITE']),
            'ENFORCE_HTTPS=' . $bool($environment['ENFORCE_HTTPS']),
            'TRUSTED_PROXIES=' . $value($environment['TRUSTED_PROXIES']),
            'DB_PERSISTENT=' . $bool($environment['DB_PERSISTENT']),
            'MEDIA_SECRET=' . $value($mediaSecret),
            'RESEND_API_KEY=' . $value($environment['RESEND_API_KEY']),
            'GOOGLE_ANALYTICS_ID=' . $value($environment['GOOGLE_ANALYTICS_ID']),
            'GOOGLE_RECAPTCHA_SITE_KEY=' . $value($environment['GOOGLE_RECAPTCHA_SITE_KEY']),
            'GOOGLE_RECAPTCHA_SECRET_KEY=' . $value($environment['GOOGLE_RECAPTCHA_SECRET_KEY']),
            'CLOUDFLARE_TURNSTILE_SITE_KEY=' . $value($environment['CLOUDFLARE_TURNSTILE_SITE_KEY']),
            'CLOUDFLARE_TURNSTILE_SECRET_KEY=' . $value($environment['CLOUDFLARE_TURNSTILE_SECRET_KEY']),
            'DB_HOST=' . $value($environment['DB_HOST']),
            'DB_PORT=' . (int) $environment['DB_PORT'],
            'DB_DATABASE=' . $value($environment['DB_DATABASE']),
            'DB_USERNAME=' . $value($environment['DB_USERNAME']),
            'DB_PASSWORD=' . $value($environment['DB_PASSWORD']),
            'DB_CHARSET=' . $value($environment['DB_CHARSET']),
            'ROOT_USER=' . $value($rootId),
        ];
        return implode("\n", $lines) . "\n";
    }

    private function envValue(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\n\r") . '"';
    }

    private function toString(string $key, mixed $value, int $max, bool $trim = true): string
    {
        if (!is_string($value) && !is_scalar($value)) throw new \InvalidArgumentException("{$key} must be a string.");
        $value = (string) $value;
        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
            throw new \InvalidArgumentException("{$key} contains invalid control characters.");
        }
        if ($trim) $value = trim($value);
        if ($value === '' && in_array($key, ['APP_NAME', 'APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_CHARSET'], true)) {
            throw new \InvalidArgumentException("{$key} is required.");
        }
        if (mb_strlen($value) > $max) throw new \InvalidArgumentException("{$key} exceeds its maximum length.");
        return $value;
    }

    private function toPositiveInt(string $key, mixed $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) throw new \InvalidArgumentException("{$key} must be a positive integer.");
        return (int) $parsed;
    }

    private function toBool(string $key, mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) && ($value === 0 || $value === 1)) return $value === 1;
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['true', '1', 'yes', 'on'], true)) return true;
            if (in_array($value, ['false', '0', 'no', 'off', ''], true)) return false;
        }
        throw new \InvalidArgumentException("{$key} must be true or false.");
    }

    private function url(string $key, mixed $value, bool $optional): string
    {
        $value = trim((string) $value);
        if ($optional && $value === '') return '';
        if (filter_var($value, FILTER_VALIDATE_URL) === false || !preg_match('~^https?://~i', $value)) {
            throw new \InvalidArgumentException("{$key} must be a valid HTTP(S) URL.");
        }
        return rtrim($value, '/');
    }

    private function timezone(string $value): string
    {
        $value = trim($value);
        if (!in_array($value, \DateTimeZone::listIdentifiers(), true)) throw new \InvalidArgumentException('APP_TIMEZONE is invalid.');
        return $value;
    }

    private function sameSite(string $key, mixed $value): string
    {
        $value = ucfirst(strtolower(trim((string) $value)));
        if (!in_array($value, ['Lax', 'Strict', 'None'], true)) throw new \InvalidArgumentException("{$key} must be Lax, Strict, or None.");
        return $value;
    }

    private function origins(string $value): string
    {
        $origins = array_values(array_filter(array_map('trim', explode(',', $value))));
        foreach ($origins as $origin) {
            if ($origin === '*') continue;
            if (filter_var($origin, FILTER_VALIDATE_URL) === false || !preg_match('~^https?://~i', $origin)) {
                throw new \InvalidArgumentException('CORS_ALLOWED_ORIGINS contains an invalid URL.');
            }
        }
        return implode(',', $origins);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) return;
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) $this->removeDirectory($path);
            else @unlink($path);
        }
        @rmdir($directory);
    }
}
