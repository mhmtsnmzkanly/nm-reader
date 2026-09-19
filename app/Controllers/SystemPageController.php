<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Helpers\RequestSecurity;
use App\Services\CacheService;
use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\SitemapService;
use App\Services\WebPageRenderer;
use App\Services\WebUrlService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** System documents, diagnostics and error shell endpoints. */
final class SystemPageController
{
    public function __construct(
        private readonly array $settings,
        private readonly SiteConfigService $siteConfig,
        private readonly I18nService $i18n,
        private readonly CacheService $cache,
        private readonly SitemapService $sitemapService,
        private readonly WebUrlService $webUrls,
        private readonly WebPageRenderer $renderer,
        private readonly \Monolog\Logger $errorLogger,
    ) {}

    public function logError(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $contentLength = trim($request->getHeaderLine('Content-Length'));
        if ($contentLength !== '' && (!ctype_digit($contentLength) || (int) $contentLength > 32768)) {
            return \App\Helpers\ResponseHelper::error(413, 'Error report is too large.');
        }

        $parsed = $request->getParsedBody();
        if (!is_array($parsed)) {
            return \App\Helpers\ResponseHelper::error(400, 'Invalid error report.');
        }

        $encoded = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 32768) {
            return \App\Helpers\ResponseHelper::error(413, 'Error report is too large.');
        }

        $message = $this->logField($parsed, 'message', 4096);
        $url = $this->logField($parsed, 'url', 2048);
        $stack = $this->logField($parsed, 'stack', 12000);
        if ($message === null || $url === null || $stack === null) {
            return \App\Helpers\ResponseHelper::error(400, 'Invalid error report fields.');
        }

        $context = $parsed['context'] ?? [];
        if ($context !== [] && (!is_array($context) || !$this->safeLogContext($context))) {
            return \App\Helpers\ResponseHelper::error(400, 'Invalid error report context.');
        }

        $this->errorLogger->error('frontend_error: ' . ($message !== '' ? $message : 'Unknown JS error'), [
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_hash' => hash('sha256', RequestSecurity::clientIp($request, (array) (Config::getSettings()['app']['trusted_proxies'] ?? []))),
            'user_agent' => substr((string) $request->getHeaderLine('User-Agent'), 0, 255),
            'url' => $url,
            'stack' => $stack,
            'browser_context' => $context,
        ]);
        return \App\Helpers\ResponseHelper::success(['logged' => true]);
    }

    private function logField(array $payload, string $field, int $maxLength): ?string
    {
        if (!array_key_exists($field, $payload)) {
            return '';
        }
        if (!is_string($payload[$field])) {
            return null;
        }

        $value = $payload[$field];
        if (mb_strlen($value) > $maxLength) {
            return null;
        }

        // Monolog encodes context, but the message itself is a log-line prefix.
        // Remove control characters so a client cannot forge additional lines.
        return (string) preg_replace('/[\x00-\x1F\x7F\r\n]+/u', ' ', $value);
    }

    private function safeLogContext(array $context, int $depth = 0): bool
    {
        if ($depth > 3 || count($context) > 32) {
            return false;
        }

        foreach ($context as $key => $value) {
            if (!is_int($key) && (!is_string($key) || mb_strlen($key) > 128)) {
                return false;
            }
            if (is_array($value)) {
                if (!$this->safeLogContext($value, $depth + 1)) {
                    return false;
                }
                continue;
            }
            if (is_string($value) && mb_strlen($value) <= 512) {
                continue;
            }
            if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                continue;
            }
            return false;
        }

        return true;
    }

    public function robotsTxt(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $cached = $this->cache->get('robots_txt');
        if (is_string($cached) && $cached !== '') {
            $response->getBody()->write($cached);
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
        $base = $this->webUrls->absolute($request, '');
        $payload = "User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /panel\nDisallow: /login\nDisallow: /logout\nDisallow: /uploads/\nSitemap: {$base}/sitemap.xml\n";
        $this->cache->set('robots_txt', $payload, 86400);
        $basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
        $staticFile = $basePath . '/public/robots.txt';
        try {
            if (is_writable($basePath . '/public') || (is_file($staticFile) && is_writable($staticFile))) {
                if (@file_put_contents($staticFile, $payload) !== false) @chmod($staticFile, 0644);
            }
        } catch (\Throwable) {
            // The cache remains the primary fallback.
        }
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function sitemapXml(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $cached = $this->sitemapService->getCachedXml();
        if ($cached !== null) {
            $response->getBody()->write($cached);
            return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
        }
        $base = $this->webUrls->absolute($request, '');
        $this->sitemapService->generateAndSave($base);
        $xml = $this->sitemapService->getCachedXml() ?? $this->sitemapService->buildSitemapXml($base);
        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function i18nJson(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $langCode = (string) ($args['lang'] ?? 'tr');
        $data = $this->i18n->getDictionaryWithFallback($langCode);
        $hash = md5((string) json_encode($data, JSON_UNESCAPED_UNICODE));
        $response->getBody()->write((string) json_encode(['hash' => $hash, 'lang' => $langCode, 'data' => $data]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function siteConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return \App\Helpers\ResponseHelper::success($this->siteConfig->public());
    }

    public function renderError(ServerRequestInterface $request, ResponseInterface $response, int $code, string $message): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        return $this->renderer->render($request, $response, [], 'Error ' . $code . ' - ' . $siteName, [
            'title' => 'Error ' . $code . ' - ' . $siteName,
            'robots' => 'noindex,nofollow',
        ]);
    }

}
