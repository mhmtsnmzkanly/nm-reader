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
            'description' => 'Manga, manhwa, webtoon ve novel serilerini tek yerde kesfet. Hizli okuma deneyimi ve duzenli guncellemeler.',
            'keywords' => 'manga oku, manhwa oku, webtoon oku, novel oku, light novel, web novel',
            'type' => 'website',
            'robots' => 'index,follow',
            'json_ld' => [
                '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $siteName,
                'url' => $this->absoluteUrl($request, '/'), 'inLanguage' => 'tr-TR',
            ],
        ]);
    }

    public function content(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $type = (string) ($args['type'] ?? '');
        $slug = (string) ($args['slug'] ?? '');
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
        $content = $this->seriesRepository->findContentByTypeAndSlug($this->seriesService->toDbType($type), $slug, $userId);
        if ($content === null) return $response->withStatus(404);
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
            '@context' => 'https://schema.org', '@type' => 'Book', 'name' => $title,
            'url' => $this->absoluteUrl($request, sprintf('/%s/%s', $type, $slug)),
            'description' => $description, 'image' => $cover,
            'datePublished' => (string) ($content['created_at'] ?? gmdate('Y-m-d H:i:s')),
        ];
        if ($altTitles !== '') $jsonLd['alternateName'] = array_map('trim', explode(',', $altTitles));
        if ($author !== '') $jsonLd['author'] = ['@type' => 'Person', 'name' => $author];
        if (isset($content['rating_avg']) && (float) $content['rating_avg'] > 0) {
            $jsonLd['aggregateRating'] = [
                '@type' => 'AggregateRating', 'ratingValue' => (float) $content['rating_avg'],
                'reviewCount' => (int) ($content['rating_count'] ?? 1), 'bestRating' => 5, 'worstRating' => 1,
            ];
        }
        $genres = $this->taxonomyFormatter->names((string) ($content['series_genres_raw'] ?? ''));
        if ($genres !== []) $jsonLd['genre'] = $genres;
        if ($releaseYear !== '' && $releaseYear !== '0') $jsonLd['copyrightYear'] = $releaseYear;
        $keywords = $title . ', ' . ucfirst($type) . ' oku';
        if ($altTitles !== '') $keywords .= ', ' . $altTitles;
        if ($author !== '') $keywords .= ', ' . $author;
        $seo = [
            'title' => sprintf('%s - %s Oku', $title, ucfirst($type)),
            'description' => $description !== '' ? $description : $title . ' detaylari ve bolumleri',
            'type' => 'book', 'image' => $cover, 'keywords' => $keywords, 'json_ld' => $jsonLd,
            'canonical' => $this->absoluteUrl($request, sprintf('/%s/%s', $type, $slug)),
        ];
        $contentBootstrap = OutputSanitizer::sanitizeFields($content, ['title', 'description']);
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
        if ($chapter === null) return $response->withStatus(404);
        $seriesTitle = (string) ($chapter['series_title'] ?? '');
        $seoTitle = $seriesTitle . ' - Bolum ' . $chapterNumber;
        return $this->render($request, $response, [
            'breadcrumbs' => $this->breadcrumbs($request, 'chapter', [
                'content_type' => $type, 'content_slug' => $slug, 'content_title' => $seriesTitle,
                'chapter_number' => $chapterNumber,
            ]),
        ], $seoTitle, [
            'title' => $seoTitle . ' - ' . $this->siteConfig->siteName(),
            'description' => 'Read ' . $seriesTitle . ' chapter ' . $chapterNumber . ' online.',
            'type' => 'article', 'robots' => 'index,follow',
        ]);
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        return $this->render($request, $response, [], 'Search - ' . $siteName, [
            'title' => $query !== '' ? sprintf('Arama: %s - %s', $query, $siteName) : 'Arama - ' . $siteName,
            'description' => 'Icerik arama sonuclari.', 'robots' => 'noindex,follow',
            'canonical' => $this->absoluteUrl($request, '/search'),
        ]);
    }

    public function listing(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        $type = (string) ($args['type'] ?? '');
        $display = $type !== '' ? ucwords(str_replace('-', ' ', $type)) : 'Tum';
        return $this->render($request, $response, [], 'Browse - ' . $siteName, [
            'title' => sprintf('%s Serileri - %s', $display, $siteName),
            'description' => sprintf('%s turundeki serileri listele, incele ve okumaya basla.', $display),
            'type' => 'website', 'robots' => 'index,follow',
        ]);
    }

    public function genre(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        return $this->taxonomyPage($request, $response, (string) ($args['slug'] ?? ''), 'genre', '%s etiketine ait serileri kesfet.');
    }

    public function tag(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        return $this->taxonomyPage($request, $response, (string) ($args['slug'] ?? ''), 'tag', '%s tagine ait icerikleri goruntule.');
    }

    private function taxonomyPage(ServerRequestInterface $request, ResponseInterface $response, string $slug, string $route, string $description): ResponseInterface
    {
        $display = ucwords(str_replace('-', ' ', $slug));
        return $this->render($request, $response, [
            'breadcrumbs' => $this->breadcrumbs($request, $route, ['name' => $display]),
        ], ucfirst($route) . ': ' . $slug, [
            'title' => sprintf('%s: %s - %s', ucfirst($route), $display, $this->siteConfig->siteName()),
            'description' => sprintf($description, $display), 'robots' => 'index,follow',
        ]);
    }

}
