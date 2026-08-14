<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service responsible for generating structured SEO metadata and injecting it
 * into the public React App Shell (app.html).
 */
class SeoService
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Renders the React app shell with injected SEO tags.
     *
     * @param array{
     *   title?: string,
     *   description?: string,
     *   canonical?: string,
     *   robots?: string,
     *   og?: array{title?: string, description?: string, image?: string, url?: string, type?: string},
     *   twitter?: array{title?: string, description?: string, image?: string, card?: string},
     *   jsonLd?: array|object
     * } $seo
     */
    public function renderShell(array $seo = []): string
    {
        $appHtmlPath = $this->basePath . '/public/app.html';
        if (!file_exists($appHtmlPath)) {
            // Fallback basic shell if build hasn't run
            $html = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><!-- SEO:TITLE --><!-- SEO:META --><!-- SEO:CANONICAL --><!-- SEO:OG --><!-- SEO:TWITTER --><!-- SEO:JSONLD --></head><body><div id="root"></div></body></html>';
        } else {
            $html = (string) file_get_contents($appHtmlPath);
        }

        $title = $seo['title'] ?? 'NM-Reader — Novel & Manga Okuma Platformu';
        $description = $seo['description'] ?? 'En popüler manga, manhwa, manhua ve web novelleri Türkçe oku.';
        $canonical = $seo['canonical'] ?? '';
        $robots = $seo['robots'] ?? 'index, follow';

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $escapedRobots = htmlspecialchars($robots, ENT_QUOTES, 'UTF-8');

        // 1. Replace Title
        $titleTag = "<title>{$escapedTitle}</title>";
        if (str_contains($html, '<!-- SEO:TITLE -->')) {
            $html = str_replace('<!-- SEO:TITLE -->', $titleTag, $html);
            // Also replace any existing static title tag right after
            $html = preg_replace('/<title>.*?<\/title>\s*/s', '', $html, 1);
            $html = str_replace($titleTag, "<!-- SEO:TITLE -->\n    {$titleTag}", $html);
        }

        // 2. Replace Meta & Robots
        $metaTags = "<meta name=\"description\" content=\"{$escapedDescription}\" />\n    <meta name=\"robots\" content=\"{$escapedRobots}\" />";
        if (str_contains($html, '<!-- SEO:META -->')) {
            $html = str_replace('<!-- SEO:META -->', "<!-- SEO:META -->\n    {$metaTags}", $html);
            $html = preg_replace('/<meta name="description" content=".*?" \/>\s*/s', '', $html, 1);
        }

        // 3. Canonical
        if (!empty($canonical) && str_contains($html, '<!-- SEO:CANONICAL -->')) {
            $escapedCanonical = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
            $canonicalTag = "<link rel=\"canonical\" href=\"{$escapedCanonical}\" />";
            $html = str_replace('<!-- SEO:CANONICAL -->', "<!-- SEO:CANONICAL -->\n    {$canonicalTag}", $html);
        }

        // 4. Open Graph
        $og = $seo['og'] ?? [];
        $ogTags = [];
        if (!empty($og['title'] ?? $title)) {
            $ogTags[] = '<meta property="og:title" content="' . htmlspecialchars($og['title'] ?? $title, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($og['description'] ?? $description)) {
            $ogTags[] = '<meta property="og:description" content="' . htmlspecialchars($og['description'] ?? $description, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($og['url'] ?? $canonical)) {
            $ogTags[] = '<meta property="og:url" content="' . htmlspecialchars($og['url'] ?? $canonical, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($og['image'])) {
            $ogTags[] = '<meta property="og:image" content="' . htmlspecialchars($og['image'], ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($og['type'])) {
            $ogTags[] = '<meta property="og:type" content="' . htmlspecialchars($og['type'], ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($ogTags) && str_contains($html, '<!-- SEO:OG -->')) {
            $html = str_replace('<!-- SEO:OG -->', "<!-- SEO:OG -->\n    " . implode("\n    ", $ogTags), $html);
        }

        // 5. Twitter Card
        $twitter = $seo['twitter'] ?? [];
        $twTags = [];
        $twTags[] = '<meta name="twitter:card" content="' . htmlspecialchars($twitter['card'] ?? 'summary_large_image', ENT_QUOTES, 'UTF-8') . '" />';
        if (!empty($twitter['title'] ?? $title)) {
            $twTags[] = '<meta name="twitter:title" content="' . htmlspecialchars($twitter['title'] ?? $title, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($twitter['description'] ?? $description)) {
            $twTags[] = '<meta name="twitter:description" content="' . htmlspecialchars($twitter['description'] ?? $description, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($twitter['image'] ?? ($og['image'] ?? ''))) {
            $twTags[] = '<meta name="twitter:image" content="' . htmlspecialchars($twitter['image'] ?? $og['image'], ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($twTags) && str_contains($html, '<!-- SEO:TWITTER -->')) {
            $html = str_replace('<!-- SEO:TWITTER -->', "<!-- SEO:TWITTER -->\n    " . implode("\n    ", $twTags), $html);
        }

        // 6. JSON-LD Structured Data
        if (!empty($seo['jsonLd']) && str_contains($html, '<!-- SEO:JSONLD -->')) {
            $jsonLdString = json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $jsonLdTag = "<script type=\"application/ld+json\">\n{$jsonLdString}\n</script>";
            $html = str_replace('<!-- SEO:JSONLD -->', "<!-- SEO:JSONLD -->\n    {$jsonLdTag}", $html);
        }

        return $html;
    }

    /**
     * Builds JSON-LD for WebSite / SearchAction schema.
     */
    public function buildWebSiteSchema(string $siteName, string $siteUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => rtrim($siteUrl, '/') . '/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string'
            ]
        ];
    }

    /**
     * Builds JSON-LD for Series (CreativeWorkSeries / Book).
     */
    public function buildSeriesSchema(array $series, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWorkSeries',
            'name' => $series['title'] ?? '',
            'headline' => $series['title'] ?? '',
            'description' => $series['description'] ?? '',
            'url' => $url,
            'image' => $series['cover_image'] ?? null,
            'genre' => $series['genres'] ?? [],
            'author' => [
                '@type' => 'Person',
                'name' => $series['author'] ?? 'Unknown'
            ]
        ];
    }

    /**
     * Builds JSON-LD for BlogPosting / Article.
     */
    public function buildBlogSchema(array $blog, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $blog['title'] ?? '',
            'description' => $blog['summary'] ?? ($blog['excerpt'] ?? ''),
            'url' => $url,
            'image' => $blog['cover_image'] ?? null,
            'datePublished' => $blog['created_at'] ?? null,
            'author' => [
                '@type' => 'Person',
                'name' => $blog['author_name'] ?? ($blog['username'] ?? 'Author')
            ]
        ];
    }

    /**
     * Builds JSON-LD for BreadcrumbList.
     *
     * @param array<array{name: string, url: string}> $items
     */
    public function buildBreadcrumbSchema(array $items): array
    {
        $elements = [];
        $position = 1;
        foreach ($items as $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item['name'],
                'item' => $item['url']
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements
        ];
    }
}
