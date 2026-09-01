<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Global HTTP Security Headers Middleware.
 *
 * Enforces standard security headers to protect against Clickjacking,
 * MIME-sniffing, sensitive referrer leakage, and unauthorized browser feature access.
 *
 * @package App\Middleware
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), xr-spatial-tracking=(), interest-cohort=()')
            ->withHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://code.jquery.com https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://www.google-analytics.com https://challenges.cloudflare.com https://static.cloudflareinsights.com; script-src-elem 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://code.jquery.com https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://www.google-analytics.com https://challenges.cloudflare.com https://static.cloudflareinsights.com; script-src-attr 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com https://challenges.cloudflare.com; img-src 'self' data: https: https://www.google-analytics.com https://www.googletagmanager.com https://challenges.cloudflare.com; connect-src 'self' https://unpkg.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://www.google-analytics.com https://region1.google-analytics.com https://challenges.cloudflare.com https://cloudflareinsights.com; frame-src https://challenges.cloudflare.com; worker-src 'self' https://challenges.cloudflare.com; child-src https://challenges.cloudflare.com; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    }
}
