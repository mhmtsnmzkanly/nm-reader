<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\EntityIdService;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * InstallController - Handles the initial system setup.
 * Access is restricted once .env is properly configured.
 */
final class InstallController
{
    private string $basePath;

    public function __construct(private readonly array $settings)
    {
        $this->basePath = (string)($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
    }

    /**
     * Renders the installation form.
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAuthorizedInstaller($request)) {
            return ResponseHelper::error(403, 'Installer access denied. Configure INSTALL_TOKEN for remote setup.');
        }

        // Safety: If .env exists and is valid, redirect to home.
        if (file_exists($this->basePath . '/.env')) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $templatePath = $this->basePath . '/storage/views/install.php';
        if (!file_exists($templatePath)) {
            $response->getBody()->write("Installation template missing.");
            return $response->withStatus(500);
        }

        ob_start();
        include $templatePath;
        $content = ob_get_clean();

        $response->getBody()->write($content ?: '');
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Processes the installation request.
     */
    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAuthorizedInstaller($request)) {
            return ResponseHelper::error(403, 'Installer access denied. Configure INSTALL_TOKEN for remote setup.');
        }

        if (file_exists($this->basePath . '/.env')) {
            return ResponseHelper::error(403, 'System already installed.');
        }

        $data = (array)$request->getParsedBody();
        $db = $data['db'] ?? [];
        $admin = $data['admin'] ?? [];

        try {
            if (!is_array($db) || !is_array($admin)) {
                throw new \InvalidArgumentException('Invalid installation payload.');
            }
            $host = trim((string) ($db['host'] ?? ''));
            $port = filter_var($db['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            $database = trim((string) ($db['database'] ?? ''));
            $dbUsername = trim((string) ($db['username'] ?? ''));
            $dbPassword = (string) ($db['password'] ?? '');
            $adminUsername = trim((string) ($admin['username'] ?? ''));
            $adminEmail = trim((string) ($admin['email'] ?? ''));
            $adminPassword = (string) ($admin['password'] ?? '');
            if ($host === '' || $port === false || !preg_match('/^[A-Za-z0-9_-]+$/', $database) || $dbUsername === '') {
                throw new \InvalidArgumentException('Valid database connection settings are required.');
            }
            if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $adminUsername)
                || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false
                || strlen($adminPassword) < 12
            ) {
                throw new \InvalidArgumentException('Admin username, valid email and a password of at least 12 characters are required.');
            }

            // 1. Test Database Connection
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // 2. Import Schema
            $schemaFile = $this->basePath . '/app/database/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new \Exception('schema.sql not found in app/database directory.');
            }

            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);

            // 3. Create Admin User
            $userId = EntityIdService::generate();
            $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $pdo->prepare("INSERT INTO users (id, username, email, password_hash, roles, created_at) VALUES (?, ?, ?, ?, '1', NOW())");
            $stmt->execute([$userId, $adminUsername, $adminEmail, $hashedPassword]);

            // 4. Generate .env file
            $envContent = $this->generateEnv([
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $dbUsername,
                'password' => $dbPassword,
            ], $userId);
            $envPath = $this->basePath . '/.env';
            
            // Check if root directory is writable
            if (!is_writable($this->basePath)) {
                throw new \Exception("The application root ({$this->basePath}) is not writable by the application user.");
            }

            if (file_put_contents($envPath, $envContent) === false) {
                throw new \Exception('Failed to write .env file to disk.');
            }

            return ResponseHelper::success(['message' => 'Installation successful! Please refresh the page.']);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Installation failed: ' . $e->getMessage());
            return ResponseHelper::error(500, 'Installation failed. Check the server logs for details.');
        }
    }

    private function generateEnv(array $db, string $adminId): string
    {
        $mediaSecret = bin2hex(random_bytes(32));
        $dbHost = $this->envValue((string) $db['host']);
        $dbName = $this->envValue((string) $db['database']);
        $dbUser = $this->envValue((string) $db['username']);
        $dbPassword = $this->envValue((string) $db['password']);
        $rootUser = $this->envValue($adminId);

        return <<<EOT
# Application Settings
APP_NAME=NovelMangaReader
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080
APP_TIMEZONE=UTC
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://localhost:3000

# Site Identity
SITE_NAME=NovelMangaReader
SITE_ABBREVIATION=NMR
SITE_DESCRIPTION="Read manga, manhwa, webtoon and novels for free on NovelMangaReader."
SITE_LOGO=/assets/img/logo.svg
SITE_ADDRESS=http://localhost:8080

# Default User Experience
DEFAULT_LANGUAGE=tr
DEFAULT_THEME=dark
DEFAULT_PROFILE_IMAGE=/assets/img/default-profile.png
DEFAULT_CONTENT_COVER_IMAGE=/assets/img/covers/placeholder.svg

# Session & Tokens
SESSION_LIFETIME=7200
REFRESH_TOKEN_DAYS=30
CACHE_TTL=300
SESSION_COOKIE_SECURE=false
SESSION_COOKIE_SAME_SITE=Lax
REMEMBER_COOKIE_SECURE=false
REMEMBER_COOKIE_SAME_SITE=Lax

# Security
ENFORCE_HTTPS=false
MEDIA_SECRET={$mediaSecret}

# Integrations
RESEND_API_KEY=""
GOOGLE_ANALYTICS_ID=""
GOOGLE_RECAPTCHA_SITE_KEY=""
GOOGLE_RECAPTCHA_SECRET_KEY=""
CLOUDFLARE_TURNSTILE_SITE_KEY=""
CLOUDFLARE_TURNSTILE_SECRET_KEY=""

# Database Settings
DB_HOST={$dbHost}
DB_PORT={$db['port']}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPassword}
DB_CHARSET=utf8mb4

# Admin Settings
ROOT_USER={$rootUser}
EOT;
    }

    private function isAuthorizedInstaller(ServerRequestInterface $request): bool
    {
        $remoteAddress = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
        if (in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $expected = trim((string) ($_ENV['INSTALL_TOKEN'] ?? getenv('INSTALL_TOKEN') ?: ''));
        if (strlen($expected) < 16) {
            return false;
        }
        $body = $request->getParsedBody();
        $provided = trim($request->getHeaderLine('X-Install-Token'));
        if ($provided === '') $provided = trim((string) ($request->getQueryParams()['install_token'] ?? ''));
        if ($provided === '' && is_array($body)) $provided = trim((string) ($body['install_token'] ?? ''));

        return $provided !== '' && hash_equals($expected, $provided);
    }

    private function envValue(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\n\r") . '"';
    }
}
