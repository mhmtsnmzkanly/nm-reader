<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\OutputSanitizer;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Services\SeriesService;
use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\WebPageRenderer;
use App\Services\TaxonomyFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Public content, catalogue and discovery pages. */
final class ContentPageController extends BasePageController
{
    public function __construct(
        WebPageRenderer $renderer,
        I18nService $i18n,
        SiteConfigService $siteConfig,
        private readonly SeriesService $seriesService,
        private readonly SeriesRepository $seriesRepository,
        private readonly ChapterRepository $chapterRepository,
        private readonly TaxonomyFormatter $taxonomyFormatter,
    ) {
        parent::__construct($renderer, $i18n, $siteConfig);
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        $context = [];
        if ($request->getUri()->getPath() === '/') {
            $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
            $context['current_page'] = ['route' => 'home', 'data' => $this->seriesService->home(1, 20, $userId)];
        }
        return $this->render($request, $response, $context, 'Home - ' . $siteName, [
            'title' => $siteName . ' - Manga, Manhwa, Webtoon ve Novel Oku',
            'description' => 'Manga, manhwa, webtoon ve novel serilerini tek yerde keşfet. Hızlı okuma deneyimi ve düzenli güncellemeler.',
            'keywords' => 'manga oku, manhwa oku, webtoon oku, novel oku, light novel, web novel',
            'type' => 'website',
            'robots' => 'index,follow',
            'json_ld' => [
                '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $siteName,
                'url' => $this->absoluteUrl($request, '/'), 'inLanguage' => 'tr-TR',
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $this->absoluteUrl($request, '/search?q={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ]);
    }

    public function content(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $type = (string) ($args['type'] ?? '');
        $slug = (string) ($args['slug'] ?? '');
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
        $content = $this->seriesRepository->findContentByTypeAndSlug($this->seriesService->toDbType($type), $slug, $userId);
        if ($content === null) return $this->notFound($request, $response);
        if ((bool) ($content['is_members_only'] ?? false) && $userId === null) {
            throw new \DomainException('MEMBERS_ONLY_REQUIRED: Bu içerik yalnızca kayıtlı üyelere özeldir.');
        }

        $title = (string) ($content['title'] ?? 'Content');
        $description = $this->truncateDescription((string) ($content['description'] ?? ''));
        $cover = (string) ($content['cover_image'] ?? '');
        $author = (string) ($content['author'] ?? '');
        $releaseYear = (string) ($content['release_year'] ?? '');
        $altTitles = (string) ($content['alternative_titles'] ?? '');
        $jsonLd = [
            '@context' => 'https://schema.org', '@type' => 'CreativeWorkSeries', 'name' => $title,
            'url' => $this->absoluteUrl($request, sprintf('/%s/%s', $type, $slug)),
            'description' => $description,
            'image' => $cover !== '' ? $this->absoluteUrl($request, $cover) : null,
            'datePublished' => (string) ($content['created_at'] ?? gmdate('Y-m-d H:i:s')),
            'inLanguage' => 'tr-TR',
        ];
        if ($cover === '') unset($jsonLd['image']);
        if ($altTitles !== '') $jsonLd['alternateName'] = array_map('trim', explode(',', $altTitles));
        if ($author !== '') $jsonLd['author'] = ['@type' => 'Person', 'name' => $author];
        if (
            isset($content['rating_avg'], $content['rating_count'])
            && (float) $content['rating_avg'] > 0
            && (int) $content['rating_count'] > 0
        ) {
            $jsonLd['aggregateRating'] = [
                '@type' => 'AggregateRating', 'ratingValue' => (float) $content['rating_avg'],
                'reviewCount' => (int) $content['rating_count'], 'bestRating' => 5, 'worstRating' => 1,
            ];
        }
        $genres = $this->taxonomyFormatter->names((string) ($content['series_genres_raw'] ?? ''));
        if ($genres !== []) $jsonLd['genre'] = $genres;
        if ($releaseYear !== '' && $releaseYear !== '0') $jsonLd['copyrightYear'] = $releaseYear;
        $keywords = $title . ', ' . ucfirst($type) . ' oku';
        if ($altTitles !== '') $keywords .= ', ' . $altTitles;
        if ($author !== '') $keywords .= ', ' . $author;
        $seo = [
            'title' => sprintf('%s Türkçe Oku - %s', $title, $this->displayType($type)),
            'description' => $description !== '' ? $description : $title . ' konusu, seri detayları ve güncel bölümleri.',
            'type' => 'article', 'image' => $cover, 'keywords' => $keywords, 'json_ld' => $jsonLd,
            'canonical' => $this->absoluteUrl($request, sprintf('/%s/%s', $type, $slug)),
        ];
        $content['total_views'] = (int) ($content['total_views'] ?? 0);
        $content['views'] = $content['total_views'];
        $contentBootstrap = OutputSanitizer::sanitizeFields($content, ['title', 'description']);
        $contentBootstrap['series_genres'] = $this->taxonomyFormatter->items((string) ($content['series_genres_raw'] ?? ''));
        $contentBootstrap['series_tags'] = $this->taxonomyFormatter->items((string) ($content['series_tags_raw'] ?? ''));
        $chaptersBootstrap = $this->seriesService->chaptersByType($type, $slug, 1, 100, $userId);
        $relatedBootstrap = array_slice(array_values(array_filter(
            $this->seriesService->byType($type, 1, 6, $userId),
            static fn (array $item): bool => (string) ($item['slug'] ?? '') !== $slug,
        )), 0, 3);
        return $this->render($request, $response, [
            'breadcrumbs' => $this->breadcrumbs($request, 'content', ['type' => $type, 'title' => $title]),
            'current_page' => ['route' => 'content', 'data' => [
                'type' => $type, 'slug' => $slug, 'content' => $contentBootstrap,
                'chapters' => $chaptersBootstrap, 'related' => $relatedBootstrap,
            ]],
        ], $seo['title'], $seo);
    }

    public function chapter(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $type = (string) ($args['type'] ?? '');
        $slug = (string) ($args['slug'] ?? '');
        $chapterNumber = (string) ($args['chapterNumber'] ?? '');
        $chapter = $this->chapterRepository->findByTypeSlugAndChapterNumber($this->seriesService->toDbType($type), $slug, $chapterNumber);
        if ($chapter === null) return $this->notFound($request, $response);
        $seriesTitle = (string) ($chapter['series_title'] ?? '');
        $seoTitle = $seriesTitle . ' Bölüm ' . $chapterNumber . ' Türkçe Oku';
        return $this->render($request, $response, [
            'breadcrumbs' => $this->breadcrumbs($request, 'chapter', [
                'content_type' => $type, 'content_slug' => $slug, 'content_title' => $seriesTitle,
                'chapter_number' => $chapterNumber,
            ]),
            'current_page' => ['route' => 'chapter', 'data' => ['chapter' => $chapter]],
        ], $seoTitle, [
            'title' => $seoTitle . ' - ' . $this->siteConfig->siteName(),
            'description' => $seriesTitle . ' Bölüm ' . $chapterNumber . ' Türkçe okuma sayfası.',
            'type' => 'article', 'robots' => 'index,follow',
            'canonical' => $this->absoluteUrl($request, sprintf('/%s/%s/chapter/%s', $type, $slug, $chapterNumber)),
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'Chapter',
                'name' => $seriesTitle . ' Bölüm ' . $chapterNumber,
                'isPartOf' => [
                    '@type' => 'CreativeWorkSeries',
                    'name' => $seriesTitle,
                    'url' => $this->absoluteUrl($request, sprintf('/%s/%s', $type, $slug)),
                ],
                'url' => $this->absoluteUrl($request, sprintf('/%s/%s/chapter/%s', $type, $slug, $chapterNumber)),
                'datePublished' => (string) ($chapter['created_at'] ?? ''),
                'inLanguage' => 'tr-TR',
            ],
        ]);
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        return $this->render($request, $response, [], 'Search - ' . $siteName, [
            'title' => $query !== '' ? sprintf('Arama: %s - %s', $query, $siteName) : 'Arama - ' . $siteName,
            'description' => 'İçerik arama sonuçları.', 'robots' => 'noindex,follow',
            'canonical' => $this->absoluteUrl($request, '/search'),
        ]);
    }

    public function listing(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        $type = (string) ($args['type'] ?? '');
        $bootstrapType = $type !== '' ? $type : 'manga';
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
        $path = $request->getUri()->getPath();
        $contextRoute = $path === '/genres' ? 'genres' : ($path === '/tags' ? 'tags' : 'listing');
        $items = match ($contextRoute) {
            'genres' => $this->seriesService->series_genres(1, 50),
            'tags' => $this->seriesService->series_tags(1, 50),
            default => $this->seriesService->byType($bootstrapType, 1, 10, $userId !== '' ? $userId : null),
        };
        $display = $type !== '' ? $this->displayType($type) : 'Tüm';
        return $this->render($request, $response, [
            'current_page' => [
                'route' => $contextRoute,
                'data' => ['type' => $bootstrapType, 'items' => $items, 'page' => 1],
            ],
        ], 'Browse - ' . $siteName, [
            'title' => sprintf('%s Serileri - %s', $display, $siteName),
            'description' => sprintf('%s türündeki manga ve novel serilerini keşfet, incele ve okumaya başla.', $display),
            'type' => 'website', 'robots' => 'index,follow',
        ]);
    }

    public function genre(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        return $this->taxonomyPage($request, $response, (string) ($args['slug'] ?? ''), 'genre', '%s türündeki serileri keşfedin.');
    }

    public function tag(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        return $this->taxonomyPage($request, $response, (string) ($args['slug'] ?? ''), 'tag', '%s etiketine sahip serileri keşfedin.');
    }

    private function taxonomyPage(ServerRequestInterface $request, ResponseInterface $response, string $slug, string $route, string $description): ResponseInterface
    {
        $taxonomy = $this->seriesService->taxonomyBySlug($route, $slug);
        if ($taxonomy === null) {
            return $this->notFound($request, $response);
        }
        $display = (string) $taxonomy['name'];
        $uiConfig = (array) ($taxonomy['ui_config'] ?? []);
        $taxonomyDescription = trim((string) ($uiConfig['description'] ?? ''));
        if ($taxonomyDescription === '') {
            $taxonomyDescription = sprintf($description, $display);
        }
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
        $items = $route === 'genre'
            ? $this->seriesService->byGenre($slug, 1, 10, $userId !== '' ? $userId : null)
            : $this->seriesService->byTag($slug, 1, 10, $userId !== '' ? $userId : null);
        return $this->render($request, $response, [
            'breadcrumbs' => $this->breadcrumbs($request, $route, ['name' => $display]),
            'current_page' => [
                'route' => $route,
                'data' => ['slug' => $slug, 'taxonomy' => $taxonomy, 'items' => $items, 'page' => 1],
            ],
        ], $display . ' Serileri', [
            'title' => sprintf('%s %s - %s', $display, $route === 'genre' ? 'Türündeki Seriler' : 'Etiketli Seriler', $this->siteConfig->siteName()),
            'description' => $taxonomyDescription, 'robots' => 'index,follow',
        ]);
    }

    private function displayType(string $type): string
    {
        return match (str_replace('_', '-', $type)) {
            'light-novel' => 'Light Novel',
            'web-novel' => 'Web Novel',
            'manhwa' => 'Manhwa',
            'manhua' => 'Manhua',
            'webtoon' => 'Webtoon',
            'manga' => 'Manga',
            default => 'Novel',
        };
    }
}
