<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Middleware to handle Cross-Origin Resource Sharing (CORS).
 * 
 * Essential for mobile apps and external CSR applications (React/Vue/Flutter) 
 * to consume the API from different domains or ports.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @param list<string> $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins = [])
    {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '' && !$this->isAllowedOrigin($origin)) {
            return (new Response(403))->withHeader('Vary', 'Origin');
        }

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->addCorsHeaders(new Response(204), $origin);
        }

        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        if ($origin === '') {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '600')
            ->withHeader('Vary', 'Origin');
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $normalizedOrigin = rtrim(strtolower(trim($origin)), '/');
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($normalizedOrigin === rtrim(strtolower(trim((string) $allowedOrigin)), '/')) {
                return true;
            }
        }
        return false;
    }
}
