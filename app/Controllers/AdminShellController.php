<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\WebContextBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Lime CSR admin shell endpoint. */
final class AdminShellController
{
    public function __construct(
        private readonly array $settings,
        private readonly SiteConfigService $siteConfig,
        private readonly I18nService $i18n,
        private readonly WebContextBuilder $webContext,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->webContext->canAccessAdminPanel()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        $basePath = (string) $this->settings['app']['base_path'];
        $templatePath = $basePath . '/storage/views/admin_panel_lime.php';
        if (!is_file($templatePath)) {
            $response->getBody()->write('Panel Template not found');
            return $response->withStatus(404);
        }
        $userId = $_SESSION['user_id'] ?? null;
        $langCode = $this->i18n->resolveLocale($request, $userId ? (string) $userId : null);
        $lang = $this->i18n->getDictionary($langCode);
        $authContext = $this->webContext->auth($userId ? (string) $userId : null);
        $contextJson = (string) json_encode([
            'auth' => $authContext,
            'lang_code' => $langCode,
            'lang_hash' => md5((string) json_encode($lang, JSON_UNESCAPED_UNICODE)),
            'default_lang' => $this->i18n->getDefaultLanguage(),
            'supported_langs' => $this->i18n->getSupportedLanguages(),
            'site_config' => $this->siteConfig->public(),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $url = static fn (string $path): string => '/' . ltrim($path, '/');
        $__t = fn (string $key, array $params = []): string => $this->i18n->translate($langCode, $key, $params);
        ob_start();
        extract([
            'url' => $url,
            '__t' => $__t,
            'adminUsername' => (string) ($authContext['username'] ?? 'admin'),
            'contextJson' => $contextJson,
            'siteConfig' => $this->siteConfig->public(),
            'authContext' => $authContext,
        ], EXTR_SKIP);
        include $templatePath;
        $html = (string) ob_get_clean();
        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
}
