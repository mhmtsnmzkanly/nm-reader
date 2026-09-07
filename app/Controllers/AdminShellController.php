<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\WebContextBuilder;
use App\Services\HtmlTemplateService;
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
        private readonly HtmlTemplateService $templates,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->webContext->canAccessAdminPanel()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        $siteConfig = $this->siteConfig->public();
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
            'site_config' => $siteConfig,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = $this->templates->render('admin.html', [
            'context_json' => $contextJson !== '' ? $contextJson : '{}',
            'site_name' => $escape((string) (($siteConfig['site_name'] ?? null) ?: 'Main Site')),
            'admin_username' => $escape((string) ($authContext['username'] ?? 'Administrator')),
            'logout_label' => $escape($this->i18n->translate($langCode, 'logout')),
            'site_abbreviation' => $escape((string) (($siteConfig['site_abbreviation'] ?? null) ?: 'NMR')),
            'next_year' => (string) (((int) date('Y')) + 1),
        ]);
        if ($html === null) {
            $response->getBody()->write('Panel Template not found');
            return $response->withStatus(404);
        }
        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
}
