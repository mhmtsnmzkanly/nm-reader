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
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            return $this->addCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowOrigin = $origin !== '' ? $origin : '*';
        $res = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Vary', 'Origin');

        if ($allowOrigin !== '*') {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $res;
    }
}
