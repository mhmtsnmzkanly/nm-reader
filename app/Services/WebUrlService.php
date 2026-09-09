<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use Psr\Http\Message\ServerRequestInterface;

/** Canonical and asset URL normalization used by web/SEO responses. */
final class WebUrlService
{
    public function absolute(ServerRequestInterface $request, string $path): string
    {
        if (str_starts_with($path, '//')) {
            return ($request->getUri()->getScheme() ?: 'http') . ':' . $path;
        }

        $uri = $request->getUri();
        $scheme = $uri->getScheme() ?: 'http';
        $settings = Config::getSettings();
        // Canonical URLs must not depend on an unvalidated Host header. The
        // explicit site address wins, with APP_URL as the deployment fallback.
        $configuredUrl = rtrim((string) (
            ($settings['app']['site_address'] ?? '') ?: ($settings['app']['url'] ?? '')
        ), '/');
        if ($configuredUrl !== '') {
            if (str_starts_with($configuredUrl, '//')) {
                $configuredUrl = $scheme . ':' . $configuredUrl;
            }
            if ($path === '') {
                return $configuredUrl;
            }
            return $configuredUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        }

        // A request without any configured URL is only a last-resort local
        // fallback. Normal deployments should always set APP_URL/SITE_ADDRESS.
        $host = $uri->getHost();
        $port = $uri->getPort();
        $authority = $host;
        if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $authority .= ':' . $port;
        }
        if ($path === '') {
            return $scheme . '://' . $authority;
        }
        return $scheme . '://' . $authority . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    public function asset(ServerRequestInterface $request, string $url, string $fallback): string
    {
        if ($url === '') {
            return $this->absolute($request, $fallback);
        }
        if (str_starts_with($url, '//')) {
            return ($request->getUri()->getScheme() ?: 'http') . ':' . $url;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return $this->absolute($request, $url);
    }
}
