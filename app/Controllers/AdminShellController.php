<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\WebContextBuilder;
use App\Services\HtmlTemplateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Lime CSR admin shell endpoint. */
final class AdminShellController
{
    public function __construct(
        private readonly WebContextBuilder $webContext,
        private readonly HtmlTemplateService $templates,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->webContext->canAccessAdminPanel()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        $html = $this->templates->render('admin.html');
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
