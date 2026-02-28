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
        // Safety: If .env exists and is valid, redirect to home.
        if (file_exists($this->basePath . '/.env')) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $templatePath = $this->basePath . '/storage/views/pages_install.php';
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
        if (file_exists($this->basePath . '/.env')) {
            return ResponseHelper::error(403, 'System already installed.');
        }

        $data = (array)$request->getParsedBody();
        $db = $data['db'] ?? [];
        $admin = $data['admin'] ?? [];

        try {
            // 1. Test Database Connection
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['username'], $db['password'], [
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
            $hashedPassword = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $pdo->prepare("INSERT INTO users (id, username, email, password_hash, roles, created_at) VALUES (?, ?, ?, ?, '1', NOW())");
            $stmt->execute([$userId, $admin['username'], $admin['email'], $hashedPassword]);

            // 4. Generate .env file
            $envContent = $this->generateEnv($db, $userId);
            file_put_contents($this->basePath . '/.env', $envContent);

            return ResponseHelper::success(['message' => 'Installation successful! Please refresh the page.']);
        } catch (\Throwable $e) {
            return ResponseHelper::error(500, 'Installation failed: ' . $e->getMessage());
        }
    }

    private function generateEnv(array $db, string $adminId): string
    {
        return <<<EOT
# Application Settings
APP_NAME=NovelMangaReader
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080
APP_TIMEZONE=UTC

# Database Settings
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['database']}
DB_USERNAME={$db['username']}
DB_PASSWORD={$db['password']}
DB_CHARSET=utf8mb4

# Admin Settings
ROOT_USER={$adminId}
EOT;
    }
}
