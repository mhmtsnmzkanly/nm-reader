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
     *   og?: array{title?: string, description?: string, image?: string, image_alt?: string, url?: string, type?: string, site_name?: string},
     *   twitter?: array{title?: string, description?: string, image?: string, card?: string},
     *   jsonLd?: array|object|null
     * } $seo
     */
    public function renderShell(array $seo = [], ?string $contextJson = null, string $initialContent = ''): string
    {
        $appHtmlPath = $this->basePath . '/public/app.html';
        if (!file_exists($appHtmlPath)) {
            // Fallback basic shell if build hasn't run
            $html = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><!-- SEO:TITLE --><!-- SEO:META --><!-- SEO:CANONICAL --><!-- SEO:OG --><!-- SEO:TWITTER --><!-- SEO:JSONLD --></head><body class="bg-[#09090b] text-[#f4f4f5] antialiased min-h-screen"><div id="root"></div></body></html>';
        } else {
            $html = (string) file_get_contents($appHtmlPath);
        }

        $title = $this->sanitizeText($seo['title'] ?? 'NM-Reader — Novel & Manga Okuma Platformu');
        $rawDescription = $seo['description'] ?? 'En popüler manga, manhwa, manhua ve web novelleri Türkçe oku.';
        $description = $this->truncateDescription($rawDescription, 160);
        $canonical = filter_var($seo['canonical'] ?? '', FILTER_SANITIZE_URL) ?: '';
        $robots = $this->sanitizeText($seo['robots'] ?? 'index, follow');

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $escapedRobots = htmlspecialchars($robots, ENT_QUOTES, 'UTF-8');

        // 1. Clean up default title/meta tags in shell to prevent duplicates
        $html = preg_replace('/<title>.*?<\/title>\s*/si', '', $html);
        $html = preg_replace('/<meta name="description" content=".*?" \/>\s*/si', '', $html);
        $html = preg_replace('/<meta name="robots" content=".*?" \/>\s*/si', '', $html);
        $html = preg_replace('/<link rel="canonical" href=".*?" \/>\s*/si', '', $html);
        $html = preg_replace('/<meta property="og:.*?" content=".*?" \/>\s*/si', '', $html);
        $html = preg_replace('/<meta name="twitter:.*?" content=".*?" \/>\s*/si', '', $html);
        $html = preg_replace('/<script type="application\/ld\+json">.*?<\/script>\s*/si', '', $html);

        // 2. Inject Title
        $titleTag = "<title>{$escapedTitle}</title>";
        if (str_contains($html, '<!-- SEO:TITLE -->')) {
            $html = str_replace('<!-- SEO:TITLE -->', $titleTag, $html);
        } else {
            $html = str_replace('<head>', "<head>\n    {$titleTag}", $html);
        }

        // 3. Inject Meta & Robots
        $metaTags = "<meta name=\"description\" content=\"{$escapedDescription}\" />\n    <meta name=\"robots\" content=\"{$escapedRobots}\" />";
        if (str_contains($html, '<!-- SEO:META -->')) {
            $html = str_replace('<!-- SEO:META -->', $metaTags, $html);
        }

        $verificationTags = [];
        foreach ([
            'google-site-verification' => $seo['googleSiteVerification'] ?? '',
            'msvalidate.01' => $seo['bingSiteVerification'] ?? '',
            'yandex-verification' => $seo['yandexSiteVerification'] ?? '',
        ] as $name => $value) {
            $value = trim((string) $value);
            if ($value !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
                $verificationTags[] = '<meta name="' . $name . '" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />';
            }
        }
        if ($verificationTags !== [] && str_contains($html, '</head>')) {
            $html = str_replace('</head>', '  ' . implode("\n  ", $verificationTags) . "\n  </head>", $html);
        }

        // 4. Inject Canonical
        $canonicalTag = !empty($canonical) ? "<link rel=\"canonical\" href=\"" . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . "\" />" : '';
        if (str_contains($html, '<!-- SEO:CANONICAL -->')) {
            $html = str_replace('<!-- SEO:CANONICAL -->', $canonicalTag, $html);
        }

        // 5. Inject Open Graph Tags
        $og = $seo['og'] ?? [];
        $ogTags = [];
        $ogTitle = $this->sanitizeText($og['title'] ?? $title);
        $ogDescription = $this->truncateDescription($og['description'] ?? $description, 160);
        $ogUrl = filter_var($og['url'] ?? $canonical, FILTER_SANITIZE_URL) ?: '';
        $ogType = $this->sanitizeText($og['type'] ?? 'website');
        $ogImage = $this->sanitizeMediaUrl($og['image'] ?? '');
        $ogSiteName = $this->sanitizeText($og['site_name'] ?? 'NM-Reader');
        $ogImageAlt = $this->sanitizeText($og['image_alt'] ?? $ogTitle);

        if (!empty($ogTitle)) {
            $ogTags[] = '<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($ogDescription)) {
            $ogTags[] = '<meta property="og:description" content="' . htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($ogUrl)) {
            $ogTags[] = '<meta property="og:url" content="' . htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($ogImage)) {
            $ogTags[] = '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '" />';
            if ($ogImageAlt !== '') {
                $ogTags[] = '<meta property="og:image:alt" content="' . htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') . '" />';
            }
        }
        if (!empty($ogType)) {
            $ogTags[] = '<meta property="og:type" content="' . htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($ogSiteName)) {
            $ogTags[] = '<meta property="og:site_name" content="' . htmlspecialchars($ogSiteName, ENT_QUOTES, 'UTF-8') . '" />';
        }

        $ogTagBlock = !empty($ogTags) ? implode("\n    ", $ogTags) : '';
        if (str_contains($html, '<!-- SEO:OG -->')) {
            $html = str_replace('<!-- SEO:OG -->', $ogTagBlock, $html);
        }

        // Hint the browser to fetch a real primary social/hero image early.
        // The configured placeholder is only a fallback and is not guaranteed
        // to be rendered above the fold, so preloading it creates a needless
        // browser warning and competes with the application bundle.
        $ogImagePath = (string) (parse_url($ogImage, PHP_URL_PATH) ?: $ogImage);
        $isPlaceholderImage = str_ends_with($ogImagePath, '/assets/img/covers/placeholder.svg');
        if ($ogImage !== '' && !$isPlaceholderImage && str_contains($html, '</head>')) {
            $preload = '<link rel="preload" as="image" href="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '" fetchpriority="high" />';
            $html = str_replace('</head>', "  {$preload}\n  </head>", $html);
        }

        // 6. Inject Twitter Card Tags
        $twitter = $seo['twitter'] ?? [];
        $twTags = [];
        $twCard = $this->sanitizeText($twitter['card'] ?? 'summary_large_image');
        $twTitle = $this->sanitizeText($twitter['title'] ?? $ogTitle);
        $twDescription = $this->truncateDescription($twitter['description'] ?? $ogDescription, 160);
        $twImage = $this->sanitizeMediaUrl($twitter['image'] ?? $ogImage);

        $twTags[] = '<meta name="twitter:card" content="' . htmlspecialchars($twCard, ENT_QUOTES, 'UTF-8') . '" />';
        if (!empty($twTitle)) {
            $twTags[] = '<meta name="twitter:title" content="' . htmlspecialchars($twTitle, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($twDescription)) {
            $twTags[] = '<meta name="twitter:description" content="' . htmlspecialchars($twDescription, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if (!empty($twImage)) {
            $twTags[] = '<meta name="twitter:image" content="' . htmlspecialchars($twImage, ENT_QUOTES, 'UTF-8') . '" />';
        }

        $twTagBlock = !empty($twTags) ? implode("\n    ", $twTags) : '';
        if (str_contains($html, '<!-- SEO:TWITTER -->')) {
            $html = str_replace('<!-- SEO:TWITTER -->', $twTagBlock, $html);
        }

        // 7. Inject JSON-LD Structured Data
        $jsonLdTag = '';
        if (!empty($seo['jsonLd'])) {
            $jsonLdString = json_encode(
                $seo['jsonLd'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT
            );
            if ($jsonLdString !== false) {
                $jsonLdTag = "<script type=\"application/ld+json\">\n{$jsonLdString}\n</script>";
            }
        }
        if (str_contains($html, '<!-- SEO:JSONLD -->')) {
            $html = str_replace('<!-- SEO:JSONLD -->', $jsonLdTag, $html);
        } elseif ($jsonLdTag !== '' && str_contains($html, '</head>')) {
            $html = str_replace('</head>', "  {$jsonLdTag}\n  </head>", $html);
        }

        // 8. Inject Application Context (window.__NMR_CONTEXT)
        if ($contextJson !== null && $contextJson !== '') {
            $contextTag = "<script>window.__NMR_CONTEXT = {$contextJson};</script>";
            if (str_contains($html, '<!-- CONTEXT_JSON -->')) {
                $html = str_replace('<!-- CONTEXT_JSON -->', $contextTag, $html);
            } else if (str_contains($html, '</head>')) {
                $html = str_replace('</head>', "  {$contextTag}\n  </head>", $html);
            }

        }

        if ($initialContent !== '') {
            $html = preg_replace(
                '/<div id="root"><\/div>/',
                '<div id="root">' . $initialContent . '</div>',
                $html,
                1,
            ) ?? $html;
        }

        // 9. Clean up any leftover SEO comment placeholders
        $html = preg_replace('/<!--\s*SEO:[A-Z_]+\s*-->\s*/', '', $html);

        return $html;
    }

    public function renderInitialContent(array $context): string
    {
        $page = is_array($context['current_page'] ?? null) ? $context['current_page'] : [];
        $route = (string) ($page['route'] ?? '');
        $data = is_array($page['data'] ?? null) ? $page['data'] : [];
        if ($route === '') return '';

        $escape = static fn (mixed $value): string => htmlspecialchars(
            trim(strip_tags((string) $value)),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $text = static function (mixed $value, int $limit = 500) use ($escape): string {
            $clean = trim(preg_replace('/[#*_>`~\[\]]+/', ' ', strip_tags((string) $value)) ?? '');
            $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
            if (mb_strlen($clean) > $limit) $clean = rtrim(mb_substr($clean, 0, $limit - 1)) . '…';
            return $escape($clean);
        };
        $path = static function (array $item): string {
            $candidate = (string) ($item['url_path'] ?? '');
            if ($candidate !== '' && str_starts_with($candidate, '/')) return $candidate;
            $type = str_replace('_', '-', (string) ($item['type_path'] ?? ($item['type'] ?? 'novel')));
            $slug = rawurlencode((string) ($item['slug'] ?? ''));
            return $slug === '' ? '#' : '/' . rawurlencode($type) . '/' . $slug;
        };
        $cards = static function (array $items, string $heading) use ($escape, $text, $path): string {
            if ($items === []) return '';
            $html = '<section><h2>' . $escape($heading) . '</h2><ul>';
            foreach (array_slice($items, 0, 20) as $item) {
                if (!is_array($item)) continue;
                $title = $escape($item['title'] ?? ($item['series_title'] ?? ''));
                if ($title === '') continue;
                $href = $path($item);
                $cover = trim((string) ($item['cover_image'] ?? ($item['cover'] ?? '')));
                $html .= '<li><article>';
                if ($cover !== '' && !str_contains($cover, '/media/chapter/')) {
                    $html .= '<a href="' . $escape($href) . '" tabindex="-1"><img src="' . $escape($cover)
                        . '" alt="' . $title . '" width="240" height="320" loading="lazy" decoding="async"></a>';
                }
                $html .= '<h3><a href="' . $escape($href) . '">' . $title . '</a></h3>';
                $summary = $text($item['description'] ?? ($item['summary'] ?? ''), 220);
                if ($summary !== '') $html .= '<p>' . $summary . '</p>';
                $html .= '</article></li>';
            }
            return $html . '</ul></section>';
        };

        $html = '<main id="server-content" data-server-rendered="true">';
        if ($route === 'home') {
            $html .= '<h1>' . $escape(($context['site_config']['site_name'] ?? 'NM Reader') . ' — Manga, Manhwa, Webtoon ve Novel Oku') . '</h1>';
            $html .= '<p>' . $text($context['site_config']['site_description'] ?? '', 300) . '</p>';
            $html .= $cards((array) ($data['explore'] ?? []), 'Öne Çıkan Seriler');
            $html .= $cards((array) ($data['recently_added'] ?? []), 'Yeni Eklenen Seriler');
            $recent = [];
            foreach ((array) ($data['recent_chapters'] ?? []) as $chapter) {
                if (!is_array($chapter)) continue;
                $chapter['title'] = ($chapter['series_title'] ?? '') . ' Bölüm ' . ($chapter['chapter_number'] ?? '');
                $chapter['url_path'] = $path($chapter) . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? ''));
                $recent[] = $chapter;
            }
            $html .= $cards($recent, 'Son Güncellenen Bölümler');
        } elseif (in_array($route, ['listing', 'genre', 'tag'], true)) {
            $taxonomy = is_array($data['taxonomy'] ?? null) ? $data['taxonomy'] : [];
            $name = (string) ($taxonomy['name'] ?? str_replace('-', ' ', (string) ($data['type'] ?? $data['slug'] ?? 'Seriler')));
            $config = is_array($taxonomy['ui_config'] ?? null) ? $taxonomy['ui_config'] : [];
            $html .= '<h1>' . $escape(ucwords($name) . ' Serileri') . '</h1>';
            if (!empty($config['description'])) $html .= '<p>' . $text($config['description'], 500) . '</p>';
            $html .= $cards((array) ($data['items'] ?? []), 'Seriler');
        } elseif ($route === 'content') {
            $content = is_array($data['content'] ?? null) ? $data['content'] : [];
            $title = $escape($content['title'] ?? '');
            $html .= '<article><h1>' . $title . '</h1>';
            $cover = trim((string) ($content['cover_image'] ?? ''));
            if ($cover !== '') {
                $html .= '<img src="' . $escape($cover) . '" alt="' . $title . ' kapak görseli" width="360" height="480" fetchpriority="high">';
            }
            if (!empty($content['description'])) $html .= '<p>' . $text($content['description'], 1200) . '</p>';
            foreach (['series_genres' => 'genre', 'series_tags' => 'tag'] as $key => $segment) {
                if (!is_array($content[$key] ?? null) || $content[$key] === []) continue;
                $html .= '<ul>';
                foreach ($content[$key] as $taxonomy) {
                    if (!is_array($taxonomy) || empty($taxonomy['slug'])) continue;
                    $html .= '<li><a href="/' . $segment . '/' . rawurlencode((string) $taxonomy['slug']) . '">'
                        . $escape($taxonomy['name'] ?? $taxonomy['slug']) . '</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</article>';
            $chapters = (array) ($data['chapters'] ?? []);
            if ($chapters !== []) {
                $type = rawurlencode(str_replace('_', '-', (string) ($data['type'] ?? 'novel')));
                $slug = rawurlencode((string) ($data['slug'] ?? ''));
                $html .= '<section><h2>Bölümler</h2><ol>';
                foreach (array_slice($chapters, 0, 100) as $chapter) {
                    if (!is_array($chapter)) continue;
                    $number = (string) ($chapter['chapter_number'] ?? ($chapter['number'] ?? ''));
                    if ($number === '') continue;
                    $html .= '<li><a href="/' . $type . '/' . $slug . '/chapter/' . rawurlencode($number) . '">'
                        . $title . ' Bölüm ' . $escape($number) . '</a></li>';
                }
                $html .= '</ol></section>';
            }
            $html .= $cards((array) ($data['related'] ?? []), 'Benzer Seriler');
        } elseif ($route === 'chapter') {
            $chapter = is_array($data['chapter'] ?? null) ? $data['chapter'] : [];
            $title = (string) ($chapter['series_title'] ?? '');
            $number = (string) ($chapter['chapter_number'] ?? '');
            $html .= '<article><h1>' . $escape($title . ' Bölüm ' . $number) . '</h1>';
            $html .= '<p><a href="/' . rawurlencode(str_replace('_', '-', (string) ($chapter['series_type'] ?? 'novel')))
                . '/' . rawurlencode((string) ($chapter['series_slug'] ?? '')) . '">' . $escape($title) . ' seri sayfasına dön</a></p></article>';
        } elseif ($route === 'blog') {
            $blog = is_array($data['blog'] ?? null) ? $data['blog'] : [];
            $html .= '<article><h1>' . $escape($blog['title'] ?? 'Blog') . '</h1>';
            if (!empty($blog['cover_image'])) {
                $html .= '<img src="' . $escape($blog['cover_image']) . '" alt="' . $escape($blog['title'] ?? 'Blog')
                    . '" width="960" height="540" fetchpriority="high">';
            }
            $html .= '<p>' . $text($blog['body'] ?? ($blog['excerpt'] ?? ''), 1500) . '</p></article>';
        } elseif ($route === 'blogs') {
            $html .= '<h1>NM Reader Blog</h1>';
            $html .= $cards((array) ($data['items'] ?? []), 'Son Yazılar');
        } elseif ($route === 'error') {
            $html .= '<h1>Sayfa Bulunamadı</h1><p>Aradığınız içerik kaldırılmış, taşınmış veya hiç var olmamış olabilir.</p>'
                . '<p><a href="/">Ana sayfaya dön</a></p>';
        }

        return $html . '</main>';
    }

    /**
     * Sanitizes plain text inputs for SEO strings.
     */
    public function sanitizeText(string $text): string
    {
        $clean = strip_tags($text);
        $clean = preg_replace('/(?:^|\s)[#*_>`~]+/u', ' ', $clean);
        $clean = preg_replace('/!\[([^\]]*)\]\([^)]+\)/u', '$1', (string) $clean);
        $clean = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', (string) $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim((string) $clean);
    }

    /**
     * Truncates descriptions to a target character limit without breaking words.
     */
    public function truncateDescription(string $text, int $limit = 160): string
    {
        $clean = $this->sanitizeText($text);
        if (mb_strlen($clean, 'UTF-8') <= $limit) {
            return $clean;
        }

        $truncated = mb_substr($clean, 0, $limit - 3, 'UTF-8');
        $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > ($limit / 2)) {
            $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($truncated, '.,!?:;') . '...';
    }

    /**
     * Sanitizes media URLs and guarantees NO temporary protected chapter tokens (t_*) leak into SEO.
     */
    public function sanitizeMediaUrl(string $url): string
    {
        $clean = trim($url);
        if ($clean === '' || str_contains($clean, '/media/chapter/') || str_starts_with($clean, 't_')) {
            return '';
        }
        return filter_var($clean, FILTER_SANITIZE_URL) ?: '';
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
            'url' => rtrim($siteUrl, '/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => rtrim($siteUrl, '/') . '/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string'
            ]
        ];
    }

    /**
     * Builds JSON-LD for Series (CreativeWorkSeries).
     */
    public function buildSeriesSchema(array $series, string $url, string $siteUrl = ''): array
    {
        $authorName = (string) ($series['author'] ?? 'Unknown');
        $genres = (array) ($series['genres'] ?? []);
        $image = $this->sanitizeMediaUrl((string) ($series['cover_image'] ?? ''));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWorkSeries',
            'name' => (string) ($series['title'] ?? ''),
            'headline' => (string) ($series['title'] ?? ''),
            'description' => $this->truncateDescription((string) ($series['description'] ?? ''), 250),
            'url' => $url,
            'author' => [
                '@type' => 'Person',
                'name' => $authorName
            ],
        ];

        if (!empty($image)) {
            $schema['image'] = $image;
        }

        if (!empty($genres)) {
            $schema['genre'] = array_values(array_filter($genres, 'is_string'));
        }

        if (!empty($siteUrl)) {
            $schema['publisher'] = [
                '@type' => 'Organization',
                'name' => 'NM-Reader',
                'url' => rtrim($siteUrl, '/')
            ];
        }

        return $schema;
    }

    /**
     * Builds JSON-LD for BlogPosting.
     */
    public function buildBlogSchema(array $blog, string $url, string $siteUrl = ''): array
    {
        $authorName = (string) ($blog['author_name'] ?? ($blog['username'] ?? 'NM-Reader'));
        $image = $this->sanitizeMediaUrl((string) ($blog['cover_image'] ?? ''));
        $datePublished = (string) ($blog['created_at'] ?? gmdate('Y-m-d H:i:s'));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) ($blog['title'] ?? ''),
            'description' => $this->truncateDescription((string) ($blog['summary'] ?? ($blog['excerpt'] ?? '')), 200),
            'url' => $url,
            'datePublished' => $datePublished,
            'author' => [
                '@type' => 'Person',
                'name' => $authorName
            ],
        ];

        if (!empty($image)) {
            $schema['image'] = $image;
        }

        if (!empty($siteUrl)) {
            $schema['publisher'] = [
                '@type' => 'Organization',
                'name' => 'NM-Reader',
                'url' => rtrim($siteUrl, '/')
            ];
        }

        return $schema;
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
                'name' => $this->sanitizeText($item['name']),
                'item' => filter_var($item['url'], FILTER_SANITIZE_URL) ?: $item['url']
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements
        ];
    }
}
