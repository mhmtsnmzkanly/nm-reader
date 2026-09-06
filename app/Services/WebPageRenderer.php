<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Renders the React shell and injects the common bootstrap/SEO contract. */
final class WebPageRenderer
{
    public function __construct(
        private readonly SiteConfigService $siteConfig,
        private readonly WebContextBuilder $webContext,
        private readonly WebUrlService $webUrls,
        private readonly SeoService $seoService,
    ) {}

    public function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $context = [],
        ?string $title = null,
        array $seo = [],
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        $title ??= $siteName;
        $contextPayload = $this->webContext->build($request, $context);
        $langCode = (string) $contextPayload['lang_code'];
        $authContext = (array) $contextPayload['auth'];
        $contextJson = (string) json_encode(
            $contextPayload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $etagInput = [
            'context' => $context,
            'auth' => $authContext,
            'lang' => $langCode,
            'seo' => $seo,
            'title' => $title,
            'app_version' => '1.1.0',
        ];
        $etag = md5((string) json_encode($etagInput));
        if ($request->getHeaderLine('If-None-Match') === '"' . $etag . '"') {
            return $response->withStatus(304);
        }

        $seo = array_merge([
            'title' => $title,
            'description' => $this->siteConfig->siteDescription(),
            'keywords' => 'manga, manhwa, webtoon, novel, light novel',
            'robots' => 'index,follow',
            'type' => 'website',
            'image' => $this->siteConfig->defaultContentCoverImage(),
            'canonical' => $this->webUrls->absolute($request, $request->getUri()->getPath()),
            'json_ld' => null,
        ], $seo);

        $seoTitle = (string) $seo['title'];
        $seoDescription = $this->truncateDescription(
            (string) ($seo['description'] ?: $this->siteConfig->siteDescription()),
        );
        $seoRobots = (string) $seo['robots'];
        $seoType = (string) $seo['type'];
        $seoCanonical = (string) $seo['canonical'];
        $seoImage = $this->webUrls->asset(
            $request,
            (string) ($seo['image'] ?: $this->siteConfig->defaultContentCoverImage()),
            $this->siteConfig->defaultContentCoverImage(),
        );

        $jsonLdPayload = [];
        if (!empty($seo['json_ld']) && is_array($seo['json_ld'])) {
            $jsonLdPayload[] = $seo['json_ld'];
        }
        $breadcrumbs = $context['breadcrumbs'] ?? [];
        if (is_array($breadcrumbs) && $breadcrumbs !== []) {
            $breadcrumbItems = [];
            foreach ($breadcrumbs as $crumb) {
                if (!empty($crumb['url']) && !empty($crumb['name'])) {
                    $breadcrumbItems[] = [
                        'name' => (string) $crumb['name'],
                        'url' => $this->webUrls->absolute($request, (string) $crumb['url']),
                    ];
                }
            }
            if ($breadcrumbItems !== []) {
                $jsonLdPayload[] = $this->seoService->buildBreadcrumbSchema($breadcrumbItems);
            }
        }
        $finalJsonLd = null;
        if (count($jsonLdPayload) === 1) {
            $finalJsonLd = $jsonLdPayload[0];
        } elseif (count($jsonLdPayload) > 1) {
            $finalJsonLd = ['@context' => 'https://schema.org', '@graph' => $jsonLdPayload];
        }

        $seoData = [
            'title' => $seoTitle,
            'description' => $seoDescription,
            'canonical' => $seoCanonical,
            'robots' => $seoRobots,
            'og' => [
                'title' => $seo['og_title'] ?? $seoTitle,
                'description' => $seo['og_description'] ?? $seoDescription,
                'image' => $seoImage,
                'url' => $seoCanonical,
                'type' => $seoType,
                'site_name' => $siteName,
            ],
            'twitter' => [
                'title' => $seo['twitter_title'] ?? ($seo['og_title'] ?? $seoTitle),
                'description' => $seo['twitter_description'] ?? ($seo['og_description'] ?? $seoDescription),
                'image' => $seo['twitter_image'] ?? $seoImage,
                'card' => 'summary_large_image',
            ],
            'jsonLd' => $finalJsonLd,
        ];
        $layoutContent = $this->seoService->renderShell($seoData, $contextJson);

        $cacheControl = !empty($authContext['is_logged_in'])
            ? 'private, max-age=0, must-revalidate'
            : 'public, max-age=0, s-maxage=300, stale-while-revalidate=60, stale-if-error=300';
        $response->getBody()->write($layoutContent);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Content-Language', $langCode)
            ->withHeader('ETag', '"' . $etag . '"')
            ->withHeader('Cache-Control', $cacheControl);
    }

    public function absoluteUrl(ServerRequestInterface $request, string $path): string
    {
        return $this->webUrls->absolute($request, $path);
    }

    public function truncateDescription(string $value, int $limit = 160): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
        if ($clean === '' || mb_strlen($clean) <= $limit) {
            return $clean;
        }
        return rtrim(mb_substr($clean, 0, $limit - 1)) . '…';
    }
}
