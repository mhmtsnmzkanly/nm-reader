<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Helpers\ResponseHelper;
use App\Services\HtmlTemplateService;
use App\Services\InstallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP boundary for the installation wizard.
 *
 * Every endpoint only validates and stores a short-lived session draft. The
 * InstallService::complete() call is the single point that changes the
 * database, media directory, and .env file.
 */
final class InstallController
{
    private string $basePath;

    public function __construct(
        private readonly array $settings,
        private readonly HtmlTemplateService $templates,
        private readonly InstallService $installer,
    ) {
        $this->basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request, false)) !== null) return $guard;
        if (is_file($this->basePath . '/.env')) return $response->withHeader('Location', '/')->withStatus(302);
        $context = [
            'app_name' => (string) ($this->settings['app']['name'] ?? 'NM Reader'),
            'csrf_token' => (string) ($_SESSION['csrf_token'] ?? ''),
            'modes' => [InstallService::MODE_FRESH, InstallService::MODE_RESTORE, InstallService::MODE_ENV_ONLY],
            'environment_defaults' => InstallService::environmentDefaults(),
            'site_defaults' => InstallService::siteDefaults(),
            'install_path' => '/install-63e4qq3',
        ];
        $content = $this->templates->render('install.html', [
            'install_context_json' => $this->jsonForHtml($context),
            'app_name' => (string) ($this->settings['app']['name'] ?? 'NM Reader'),
        ]);
        if ($content === null) {
            $response->getBody()->write('Installation template missing.');
            return $response->withStatus(500);
        }
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function selectMode(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        try {
            $mode = (string) ($this->body($request)['mode'] ?? '');
            if (!in_array($mode, [InstallService::MODE_FRESH, InstallService::MODE_RESTORE, InstallService::MODE_ENV_ONLY], true)) {
                throw new \InvalidArgumentException('Invalid installation mode.');
            }
            $previous = $_SESSION['installer_draft'] ?? [];
            if (is_array($previous)) foreach ((array) ($previous['backup'] ?? []) as $upload) $this->discardUpload($upload['path'] ?? null);
            $_SESSION['installer_draft'] = ['mode' => $mode];
            return ResponseHelper::success(['mode' => $mode]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    public function validateEnvironment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        try {
            $draft = $this->draft();
            $body = $this->body($request);
            $result = $this->installer->validateEnvironment((array) ($body['environment'] ?? $body));
            $draft['environment'] = $result['environment'];
            $draft['database'] = $result['database'];
            $this->saveDraft($draft);
            return ResponseHelper::success([
                'environment' => $this->redactEnvironment($result['environment']),
                'database' => $result['database'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(422, $this->safeInstallMessage($exception), 'INSTALL_VALIDATION_FAILED');
        }
    }

    public function validateSiteSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        try {
            $draft = $this->draft();
            $this->requireDraftPart($draft, 'environment');
            $body = $this->body($request);
            $settings = $this->installer->validateSiteSettings((array) ($body['site_settings'] ?? $body));
            $draft['site_settings'] = $settings;
            $this->saveDraft($draft);
            return ResponseHelper::success(['site_settings' => $settings]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(409, $this->safeInstallMessage($exception), 'INSTALL_VALIDATION_FAILED');
        }
    }

    public function validateBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        $stored = [];
        try {
            $draft = $this->draft();
            if (($draft['mode'] ?? '') !== InstallService::MODE_RESTORE) {
                throw new \InvalidArgumentException('Backup uploads are only valid in restore mode.');
            }
            $files = $request->getUploadedFiles();
            $database = $files['database_backup'] ?? null;
            $media = $files['media_backup'] ?? null;
            if (!$database instanceof \Psr\Http\Message\UploadedFileInterface || $database->getError() === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('A database backup file is required.');
            }
            if (!$media instanceof \Psr\Http\Message\UploadedFileInterface || $media->getError() === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('A media backup file is required.');
            }
            $this->discardUpload($draft['backup']['database']['path'] ?? null);
            $this->discardUpload($draft['backup']['media']['path'] ?? null);
            $stored['database'] = $this->installer->storeBackupUpload($database, 'database');
            $stored['media'] = $this->installer->storeBackupUpload($media, 'media');
            $draft['backup'] = $stored;
            $this->saveDraft($draft);
            return ResponseHelper::success([
                'database' => $this->uploadSummary($draft['backup']['database']),
                'media' => $this->uploadSummary($draft['backup']['media']),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            foreach ($stored as $upload) $this->discardUpload($upload['path'] ?? null);
            return ResponseHelper::error(422, $this->safeInstallMessage($exception), 'INSTALL_VALIDATION_FAILED');
        }
    }

    public function validateExistingDatabase(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        try {
            $draft = $this->draft();
            if (($draft['mode'] ?? '') !== InstallService::MODE_ENV_ONLY) {
                throw new \InvalidArgumentException('Existing database inspection is only valid in env-only mode.');
            }
            $this->requireDraftPart($draft, 'environment');
            $inspection = $this->installer->inspectExistingDatabase((array) $draft['environment']);
            $draft['database_inspection'] = $inspection;
            $this->saveDraft($draft);
            return ResponseHelper::success(['inspection' => $inspection]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(422, $this->safeInstallMessage($exception), 'INSTALL_VALIDATION_FAILED');
        }
    }

    public function validateRoot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        try {
            $draft = $this->draft();
            $mode = (string) ($draft['mode'] ?? '');
            if (!in_array($mode, [InstallService::MODE_FRESH, InstallService::MODE_RESTORE, InstallService::MODE_ENV_ONLY], true)) {
                throw new \InvalidArgumentException('Choose an installation mode first.');
            }
            $this->requireDraftPart($draft, 'environment');
            if ($mode === InstallService::MODE_ENV_ONLY) $this->requireDraftPart($draft, 'database_inspection');
            if ($mode === InstallService::MODE_RESTORE) $this->requireDraftPart($draft, 'backup');
            $body = $this->body($request);
            if (in_array($mode, [InstallService::MODE_FRESH, InstallService::MODE_RESTORE], true)
                && (int) (($draft['database']['existing_table_count'] ?? 0)) > 0
                && trim((string) ($body['existing_confirmation'] ?? '')) !== (string) ($draft['environment']['DB_DATABASE'] ?? '')) {
                throw new \InvalidArgumentException('The target database is not empty. Type its name to confirm replacement.');
            }
            $root = $this->installer->validateRoot((array) ($body['root'] ?? $body), $mode, (array) ($draft['database_inspection'] ?? []));
            $draft['root'] = $root;
            if (array_key_exists('existing_confirmation', $body)) {
                $draft['existing_confirmation'] = trim((string) $body['existing_confirmation']);
            }
            $this->saveDraft($draft);
            return ResponseHelper::success(['root' => $this->redactRoot($root)]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(409, $this->safeInstallMessage($exception), 'INSTALL_VALIDATION_FAILED');
        }
    }

    public function complete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (($guard = $this->guard($request)) !== null) return $guard;
        try {
            $draft = $this->draft();
            foreach (['mode', 'environment', 'root'] as $part) $this->requireDraftPart($draft, $part);
            $mode = (string) $draft['mode'];
            if ($mode === InstallService::MODE_FRESH) $this->requireDraftPart($draft, 'site_settings');
            if ($mode === InstallService::MODE_RESTORE) $this->requireDraftPart($draft, 'backup');
            if ($mode === InstallService::MODE_ENV_ONLY) $this->requireDraftPart($draft, 'database_inspection');

            $result = $this->installer->complete($draft);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['root_user_id'];
            $_SESSION['username'] = $result['root_username'];
            $_SESSION['roles'] = ['root', 'admin', 'user'];
            unset($_SESSION['is_admin']);
            $permissions = [];
            foreach ((array) (Config::getRbacConfig()['roles'] ?? []) as $role) {
                foreach ((array) ($role['permissions'] ?? []) as $permission) $permissions[] = (string) $permission;
            }
            $_SESSION['permissions'] = array_values(array_unique($permissions));
            foreach ((array) ($draft['backup'] ?? []) as $upload) $this->discardUpload($upload['path'] ?? null);
            unset($_SESSION['installer_draft']);
            return ResponseHelper::success([
                'message' => $result['message'],
                'mode' => $result['mode'],
                'root_user_id' => $result['root_user_id'],
                'redirect' => '/panel',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(409, $this->safeInstallMessage($exception), 'INSTALL_VALIDATION_FAILED');
        }
    }

    /** Backwards-compatible alias for clients that still post to the old URL. */
    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->complete($request, $response);
    }

    private function guard(ServerRequestInterface $request, bool $alreadyInstalled = true): ?ResponseInterface
    {
        if (!$this->isAuthorizedInstaller($request)) return ResponseHelper::error(403, 'Installer access denied. Configure INSTALL_TOKEN for remote setup.');
        if ($alreadyInstalled && is_file($this->basePath . '/.env')) return ResponseHelper::error(409, 'System already installed.');
        return null;
    }

    /** @return array<string,mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    /** @return array<string,mixed> */
    private function draft(): array
    {
        $draft = $_SESSION['installer_draft'] ?? [];
        if (!is_array($draft) || $draft === []) throw new \InvalidArgumentException('Installation session expired. Start again.');
        return $draft;
    }

    /** @param array<string,mixed> $draft */
    private function saveDraft(array $draft): void
    {
        $_SESSION['installer_draft'] = $draft;
    }

    /** @param array<string,mixed> $draft */
    private function requireDraftPart(array $draft, string $part): void
    {
        if (!array_key_exists($part, $draft) || $draft[$part] === [] || $draft[$part] === null) throw new \InvalidArgumentException('Complete the previous installation step first.');
    }

    /** @param array<string,mixed> $environment @return array<string,mixed> */
    private function redactEnvironment(array $environment): array
    {
        if (($environment['DB_PASSWORD'] ?? '') !== '') $environment['DB_PASSWORD'] = '••••••••';
        foreach (['RESEND_API_KEY', 'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SECRET_KEY'] as $key) {
            if (($environment[$key] ?? '') !== '') $environment[$key] = '••••••••';
        }
        return $environment;
    }

    /** @param array<string,mixed> $root @return array<string,mixed> */
    private function redactRoot(array $root): array
    {
        unset($root['password'], $root['password_confirmation']);
        return $root;
    }

    /** @param array<string,mixed> $upload @return array<string,mixed> */
    private function uploadSummary(array $upload): array
    {
        return ['name' => $upload['name'] ?? '', 'size' => (int) ($upload['size'] ?? 0), 'kind' => $upload['kind'] ?? ''];
    }

    private function discardUpload(mixed $path): void
    {
        if (is_string($path) && str_starts_with($path, $this->basePath . '/storage/install/uploads/')) @unlink($path);
    }

    private function safeInstallMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $lower = strtolower($message);
        if ($message === '' || str_contains($lower, 'password') || str_contains($lower, 'sqlstate')) return 'Installation validation failed. Check the supplied values and server configuration.';
        return $message;
    }

    private function jsonForHtml(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    private function isAuthorizedInstaller(ServerRequestInterface $request): bool
    {
        $remoteAddress = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
        if (in_array($remoteAddress, ['127.0.0.1', '::1'], true)) return true;
        $expected = trim((string) ($_ENV['INSTALL_TOKEN'] ?? getenv('INSTALL_TOKEN') ?: ''));
        if (strlen($expected) < 16) return false;
        $provided = trim($request->getHeaderLine('X-Install-Token'));
        if ($provided === '') $provided = trim((string) ($request->getQueryParams()['install_token'] ?? ''));
        return $provided !== '' && hash_equals($expected, $provided);
    }
}
