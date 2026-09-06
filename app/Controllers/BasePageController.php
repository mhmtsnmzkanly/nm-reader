<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\BreadcrumbHelper;
use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\WebPageRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Shared helpers for the split web page controllers. */
abstract class BasePageController
{
    public function __construct(
        protected readonly WebPageRenderer $renderer,
        protected readonly I18nService $i18n,
        protected readonly SiteConfigService $siteConfig,
    ) {}

    protected function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $context = [],
        ?string $title = null,
        array $seo = [],
    ): ResponseInterface {
        return $this->renderer->render($request, $response, $context, $title, $seo);
    }

    protected function absoluteUrl(ServerRequestInterface $request, string $path): string
    {
        return $this->renderer->absoluteUrl($request, $path);
    }

    protected function truncateDescription(string $value, int $limit = 160): string
    {
        return $this->renderer->truncateDescription($value, $limit);
    }

    /** @return list<array<string,mixed>> */
    protected function breadcrumbs(ServerRequestInterface $request, string $route, array $data = []): array
    {
        $langCode = $this->i18n->resolveLocale($request);
        return BreadcrumbHelper::generate(
            $langCode,
            $this->i18n->getDictionary($langCode),
            static fn (string $path): string => '/' . ltrim($path, '/'),
            $route,
            $data,
        );
    }
}
