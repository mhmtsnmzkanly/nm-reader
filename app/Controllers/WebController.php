<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BlogRepository;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use App\Services\AuthorizationService;
use App\Services\SiteConfigService;
use App\Services\SeriesService;
use App\Services\UserService;
use App\Helpers\BreadcrumbHelper;
use App\Services\CacheService;
use App\Services\I18nService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for Server-Side Rendered (SSR) Web Pages.
 *
 * This is the primary controller for public-facing HTML views. It handles:
 * - Localized routing and automatic language detection.
 * - Dynamic SEO metadata generation (Title, Meta, JSON-LD).
 * - Aggregating data for complex views like Content Detail and Reader.
 * - Integration with i18n for multi-language support.
 *
 * @package App\Controllers
 */
final class WebController
{
    public function __construct(
        private readonly array $settings,
        private readonly SiteConfigService $siteConfig,
        private readonly AuthorizationService $authorization,
        private readonly SeriesService $seriesService,
        private readonly UserService $userService,
        private readonly SeriesRepository $seriesRepository,
        private readonly ChapterRepository $chapterRepository,
        private readonly UserRepository $userRepository,
        private readonly BlogRepository $blogRepository,
        private readonly I18nService $i18n,
        private readonly CacheService $cache,
        private readonly \Monolog\Logger $errorLogger,
    ) {}

    /**
     * Endpoint for frontend JS error logging.
     */
    public function logError(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $message = (string) ($payload['message'] ?? 'Unknown JS error');
        $context = (array) ($payload['context'] ?? []);
        
        $this->errorLogger->error('frontend_error: ' . $message, [
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_hash' => hash('sha256', (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')),
            'user_agent' => substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255),
            'url' => (string) ($payload['url'] ?? ''),
            'stack' => (string) ($payload['stack'] ?? ''),
            'browser_context' => $context
        ]);

        return \App\Helpers\ResponseHelper::success(['logged' => true]);
    }

    /**
     * Serves the Framework7 mobile app shell.
     */
    public function mobile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $basePath = (string) ($this->settings['app']['base_path'] ?? dirname(__DIR__, 2));
        $file = $basePath . '/public/mobile/index.html';
        if (!is_file($file)) {
            return $response->withStatus(404);
        }

        $html = (string) file_get_contents($file);
        $gaId = trim((string) ($_ENV['GOOGLE_ANALYTICS_ID'] ?? getenv('GOOGLE_ANALYTICS_ID') ?: ''));
        $turnstileKey = trim((string) ($_ENV['CLOUDFLARE_TURNSTILE_SITE_KEY'] ?? getenv('CLOUDFLARE_TURNSTILE_SITE_KEY') ?: ''));

        $injected = '';
        if ($gaId !== '') {
            $gaIdJson = json_encode($gaId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $injected .= "\n<script async src=\"https://www.googletagmanager.com/gtag/js?id={$gaId}\"></script>";
            $injected .= "\n<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',{$gaIdJson});window.NMR_GA_ID={$gaIdJson};</script>";
        }
        if ($turnstileKey !== '') {
            $turnstileKeyJson = json_encode($turnstileKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $injected .= "\n<script>window.NMR_TURNSTILE_SITE_KEY={$turnstileKeyJson};</script>";
            $injected .= "\n<script src=\"https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit\" async defer onload=\"window.dispatchEvent(new Event('turnstile:ready'))\"></script>";
        }
        if ($injected !== '') {
            $html = str_replace('</head>', $injected . "\n</head>", $html);
        }
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Renders the platform homepage.
     */
    public function home(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();

        return $this->render(
            $request,
            $response,
            "home.php",
            [],
            [],
            "Home - " . $siteName,
            "container",
            [
                "title" =>
                    $siteName . " - Manga, Manhwa, Webtoon ve Novel Oku",
                "description" =>
                    "Manga, manhwa, webtoon ve novel serilerini tek yerde kesfet. Hizli okuma deneyimi ve duzenli guncellemeler.",
                "keywords" =>
                    "manga oku, manhwa oku, webtoon oku, novel oku, light novel, web novel",
                "type" => "website",
                "robots" => "index,follow",
                "json_ld" => [
                    "@context" => "https://schema.org",
                    "@type" => "WebSite",
                    "name" => $siteName,
                    "url" => $this->absoluteUrl($request, "/"),
                    "inLanguage" => "tr-TR",
                ],
            ],
        );
    }

    /**
     * Renders the detailed view for a single series (Novel/Manga).
     *
     * @param array $args Must contain 'type' and 'slug'.
     */
    public function content(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $type = (string) ($args["type"] ?? "");
        $slug = (string) ($args["slug"] ?? "");
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        $dbType = $this->seriesService->toDbType($type);
        $content = $this->seriesRepository->findContentByTypeAndSlug($dbType, $slug, $userId);
        if ($content === null) {
            return $response->withStatus(404);
        }

        if ((bool) ($content['is_members_only'] ?? false) && $userId === null) {
            throw new \DomainException('MEMBERS_ONLY_REQUIRED: Bu içerik yalnızca kayıtlı üyelere özeldir.');
        }

        $title = (string) ($content["title"] ?? "Content");
        $description = $this->truncateDescription(
            (string) ($content["description"] ?? ""),
        );
        $cover = (string) ($content["cover_image"] ?? "");

        $author = (string) ($content["author"] ?? "");
        $releaseYear = (string) ($content["release_year"] ?? "");
        $altTitles = (string) ($content["alternative_titles"] ?? "");

        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => "Book",
            "name" => $title,
            "url" => $this->absoluteUrl(
                $request,
                sprintf("/%s/%s", $type, $slug),
            ),
            "description" => $description,
            "image" => $cover,
            "datePublished" =>
                (string) ($content["created_at"] ?? gmdate("Y-m-d H:i:s")),
        ];

        if ($altTitles !== "") {
            $titlesArray = array_map("trim", explode(",", $altTitles));
            $jsonLd["alternateName"] = $titlesArray;
        }

        if ($author !== "") {
            $jsonLd["author"] = [
                "@type" => "Person",
                "name" => $author
            ];
        }

        if (isset($content['rating_avg']) && (float)$content['rating_avg'] > 0) {
            $jsonLd["aggregateRating"] = [
                "@type" => "AggregateRating",
                "ratingValue" => (float)$content['rating_avg'],
                "reviewCount" => (int)($content['rating_count'] ?? 1),
                "bestRating" => 5,
                "worstRating" => 1
            ];
        }

        $genreNames = $this->taxonomyNames((string) ($content['series_genres_raw'] ?? ''));
        if ($genreNames !== []) {
            $jsonLd["genre"] = $genreNames;
        }

        if ($releaseYear !== "" && $releaseYear !== "0") {
            $jsonLd["copyrightYear"] = $releaseYear;
        }

        $keywords = $title . ", " . ucfirst($type) . " oku";
        if ($altTitles !== "") {
            $keywords .= ", " . $altTitles;
        }
        if ($author !== "") {
            $keywords .= ", " . $author;
        }

        $seo = [
            "title" => sprintf("%s - %s Oku", $title, ucfirst($type)),
            "description" =>
                $description !== ""
                    ? $description
                    : $title . " detaylari ve bolumleri",
            "type" => "book",
            "image" => $cover,
            "keywords" => $keywords,
            "json_ld" => $jsonLd,
            "canonical" => $this->absoluteUrl($request, sprintf("/%s/%s", $type, $slug))
        ];

        $langCode = $this->i18n->resolveLocale($request);
        $lang = $this->i18n->getDictionary($langCode);
        $urlHelper = function(string $path) {
            return "/" . ltrim($path, "/");
        };

        $breadcrumbs = BreadcrumbHelper::generate($langCode, $lang, $urlHelper, 'content', [
            'type' => $type,
            'title' => $title
        ]);

        return $this->render(
            $request,
            $response,
            "content.php",
            [
                "breadcrumbs" => $breadcrumbs,
            ],
            [],
            $seo["title"],
            "container",
            $seo,
        );
    }

    /**
     * Renders the chapter reader view.
     *
     * Aggregates chapter data (text or images) and sets up the reader context.
     *
     * @param array $args Must contain 'type', 'slug', and 'chapterNumber'.
     */
    public function chapter(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $type = (string) ($args["type"] ?? "");
        $slug = (string) ($args["slug"] ?? "");
        $chapterNumber = (string) ($args["chapterNumber"] ?? "");
        $dbType = $this->seriesService->toDbType($type);
        $chapter = $this->chapterRepository->findByTypeSlugAndChapterNumber(
            $dbType,
            $slug,
            $chapterNumber,
        );

        if ($chapter === null) {
            return $response->withStatus(404);
        }

        $seriesTitle = (string) ($chapter["series_title"] ?? "");
        $seoTitle = $seriesTitle . " - Bolum " . $chapterNumber;
        $siteName = $this->siteConfig->siteName();

        $langCode = $this->i18n->resolveLocale($request);
        $lang = $this->i18n->getDictionary($langCode);
        $urlHelper = function(string $path) {
            return "/" . ltrim($path, "/");
        };

        $breadcrumbs = BreadcrumbHelper::generate($langCode, $lang, $urlHelper, 'chapter', [
            'content_type' => $type,
            'content_slug' => $slug,
            'content_title' => $seriesTitle,
            'chapter_number' => $chapterNumber
        ]);

        return $this->render(
            $request,
            $response,
            "chapter.php",
            [
                "breadcrumbs" => $breadcrumbs,
            ],
            [],
            $seoTitle,
            "container-fluid",
            [
                "title" => $seoTitle . " - " . $siteName,
                "description" => "Read " . $seriesTitle . " chapter " . $chapterNumber . " online.",
                "type" => "article",
                "robots" => "index,follow",
            ],
        );
    }

    public function login(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        return $this->render(
            $request,
            $response,
            "login.php",
            [],
            [],
            "Login - " . $siteName,
            "container",
            [
                "title" => "Giris Yap - " . $siteName,
                "description" =>
                    "Hesabina giris yaparak takip, yorum ve okuma ayarlarina eris.",
                "robots" => "noindex,nofollow",
            ],
        );
    }

    /**
     * Renders a user's public profile page.
     *
     * @param array $args Must contain 'person' (username or ID).
     */
    public function profile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $person = (string) ($args["person"] ?? "");
        $userId = (string) ($_SESSION["user_id"] ?? "");

        if ($person === "" && $userId === "") {
            return $response
                ->withHeader("Location", "/login")
                ->withStatus(302);
        }

        // Target user
        $currentUsername = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
        if ($userId !== '' && $currentUsername === '') {
            $me = $this->userRepository->findById($userId);
            $currentUsername = (string) ($me['username'] ?? '');
        }
        $target = $person !== "" ? $person : null;
        if ($target === null && $userId !== "") {
            $target = $currentUsername;
        }

        if ($target === null || $target === "") {
            return $response->withStatus(404);
        }

        $profile = $this->userRepository->findPublicByPerson($target);
        if ($profile === null) {
            return $response->withStatus(404);
        }

        $isMe = $currentUsername !== '' && strcasecmp($currentUsername, $target) === 0;

        $username = (string) ($profile["username"] ?? "User");
        $bio = (string) ($profile["bio"] ?? "");
        $siteName = $this->siteConfig->siteName();

        return $this->render(
            $request,
            $response,
            "profile.php",
            [],
            [],
            "$username - Profile",
            "container",
            [
                "title" => "$username - " . $siteName,
                "description" => $this->truncateDescription($bio),
                "robots" => $isMe ? "noindex,nofollow" : "index,follow",
            ],
        );
    }

    /**
     * Renders either the blog listing or a specific blog post.
     *
     * @param array $args Optional 'slug' for a specific post.
     */
    public function blog(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $slug = (string) ($args["slug"] ?? "");
        $siteName = $this->siteConfig->siteName();
        $seo = [
            "title" => "Blog - " . $siteName,
            "description" =>
                $siteName . " toplulugundan blog yazilari, rehberler ve analizler.",
            "type" => "website",
            "robots" => "index,follow",
        ];

        $post = null;
        if ($slug !== "") {
            $post = $this->blogRepository->findApprovedBySlug($slug);
            if ($post === null) {
                return $response->withStatus(404);
            }
            if ($post !== null) {
                $postTitle = (string) ($post["title"] ?? "Blog");
                $postDescription = $this->truncateDescription(
                    (string) ($post["body"] ?? ""),
                );
                $seo["title"] = $postTitle . " - Blog";
                $seo["description"] =
                    $postDescription !== "" ? $postDescription : "Blog yazisi";
                $seo["type"] = "article";
                $seo["json_ld"] = [
                    "@context" => "https://schema.org",
                    "@type" => "BlogPosting",
                    "headline" => $postTitle,
                    "image" => !empty($post['cover_image']) ? $post['cover_image'] : $this->siteConfig->defaultContentCoverImage(),
                    "author" => [
                        "@type" => "Person",
                        "name" =>
                            (string) ($post["author_username"] ?? "NMR Author"),
                    ],
                    "datePublished" =>
                        (string) ($post["approved_at"] ??
                            ($post["created_at"] ?? gmdate("Y-m-d H:i:s"))),
                    "url" => $this->absoluteUrl($request, "/blogs/" . $slug),
                    "description" => $seo["description"],
                ];
            }
        }

        $langCode = $this->i18n->resolveLocale($request);
        $lang = $this->i18n->getDictionary($langCode);
        $urlHelper = function(string $path) {
            return "/" . ltrim($path, "/");
        };

        $breadcrumbs = BreadcrumbHelper::generate($langCode, $lang, $urlHelper, 'blog', [
            'title' => ($slug !== "" && $post) ? ($post['title'] ?? '') : ''
        ]);

        return $this->render(
            $request,
            $response,
            "blog.php",
            [
                "breadcrumbs" => $breadcrumbs,
            ],
            [],
            $seo["title"],
            "container",
            $seo,
        );
    }

    public function chat(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        return $this->render(
            $request,
            $response,
            "chat.php",
            [],
            [],
            "Chat - " . $siteName,
            "container",
            [
                "title" => "Chat - " . $siteName,
                "description" => "Topluluk sohbet alani.",
                "robots" => "noindex,nofollow",
            ],
        );
    }

    /**
     * Renders the search results page.
     */
    public function search(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        $params = $request->getQueryParams();
        
        $query = trim((string) ($params["q"] ?? ""));
        $title = $query !== ""
                ? sprintf("Arama: %s - %s", $query, $siteName)
                : "Arama - " . $siteName;
        
        return $this->render(
            $request,
            $response,
            "search.php",
            [],
            [],
            "Search - " . $siteName,
            "container",
            [
                "title" => $title,
                "description" => "Icerik arama sonuclari.",
                "robots" => "noindex,follow",
                "canonical" => $this->absoluteUrl($request, "/search"),
            ],
        );
    }

    /**
     * Renders various series listings (By type, genre, or tag).
     *
     * @param array $args May contain 'type', 'slug' (for genre/tag).
     */
    public function listing(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        $type = (string) ($args["type"] ?? "");
        $display = $type !== "" ? ucwords(str_replace("-", " ", $type)) : "Tum";
        return $this->render(
            $request,
            $response,
            "series_list.php",
            [
                "list_type" => "category",
                "value" => $type,
                "page_heading" => $display,
            ],
            [],
            "Browse - " . $siteName,
            "container",
            [
                "title" => sprintf("%s Serileri - %s", $display, $siteName),
                "description" => sprintf(
                    "%s turundeki serileri listele, incele ve okumaya basla.",
                    $display,
                ),
                "type" => "website",
                "robots" => "index,follow",
            ],
        );
    }

    public function genre(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        $slug = (string) ($args["slug"] ?? "");
        $display = ucwords(str_replace("-", " ", $slug));

        $langCode = $this->i18n->resolveLocale($request);
        $lang = $this->i18n->getDictionary($langCode);
        $urlHelper = function(string $path) {
            return "/" . ltrim($path, "/");
        };
        $breadcrumbs = BreadcrumbHelper::generate($langCode, $lang, $urlHelper, 'genre', [
            'name' => $display
        ]);

        return $this->render(
            $request,
            $response,
            "series_list.php",
            [
                "list_type" => "genre",
                "value" => $slug,
                "page_heading" => $display,
                "breadcrumbs" => $breadcrumbs,
            ],
            [],
            "Genre: " . ucfirst($slug),
            "container",
            [
                "title" => sprintf("Genre: %s - %s", $display, $siteName),
                "description" => sprintf(
                    "%s etiketine ait serileri kesfet.",
                    $display,
                ),
                "robots" => "index,follow",
            ],
        );
    }

    public function tag(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        $slug = (string) ($args["slug"] ?? "");
        $display = ucwords(str_replace("-", " ", $slug));

        $langCode = $this->i18n->resolveLocale($request);
        $lang = $this->i18n->getDictionary($langCode);
        $urlHelper = function(string $path) {
            return "/" . ltrim($path, "/");
        };
        $breadcrumbs = BreadcrumbHelper::generate($langCode, $lang, $urlHelper, 'tag', [
            'name' => $display
        ]);

        return $this->render(
            $request,
            $response,
            "series_list.php",
            [
                "list_type" => "tag",
                "value" => $slug,
                "page_heading" => $display,
                "breadcrumbs" => $breadcrumbs,
            ],
            [],
            "Tag: " . ucfirst($slug),
            "container",
            [
                "title" => sprintf("Tag: %s - %s", $display, $siteName),
                "description" => sprintf(
                    "%s tagine ait icerikleri goruntule.",
                    $display,
                ),
                "robots" => "index,follow",
            ],
        );
    }

    public function adminPanelLime(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        $basePath = (string) $this->settings["app"]["base_path"];
        $templatePath = $basePath . "/storage/views/admin_panel_lime.php";

        if (!is_file($templatePath)) {
            $response->getBody()->write("Panel Template not found");
            return $response->withStatus(404);
        }

        $userId = $_SESSION["user_id"] ?? null;
        $langCode = $this->i18n->resolveLocale($request, $userId ? (string)$userId : null);
        $defaultLang = $this->i18n->getDefaultLanguage();

        $lang = $this->i18n->getDictionary($langCode);
        $langHash = md5((string) json_encode($lang, JSON_UNESCAPED_UNICODE));

        $authContext = [
            "is_logged_in" => isset($_SESSION["user_id"]),
            "is_admin" => $this->canAccessAdminPanel(),
            "user_id" => $userId,
            "username" => $_SESSION["username"] ?? null,
            "roles" => $this->effectiveRoles(),
            "permissions" => $this->effectivePermissions(),
            "csrf_token" => $_SESSION["csrf_token"] ?? null,
        ];

        $url = fn(string $path) => '/' . ltrim($path, '/');

        $contextJson = (string) json_encode(
            [
                "auth" => $authContext,
                "lang_code" => $langCode,
                "lang_hash" => $langHash,
                "default_lang" => $defaultLang,
                "supported_langs" => $this->i18n->getSupportedLanguages(),
                "site_config" => $this->siteConfig->all(),
            ],
            JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES,
        );
        $adminUsername = (string) ($authContext["username"] ?? "admin");
        $siteConfig = $this->siteConfig->all();
        $__t = fn(string $key, array $params = []) => $this->i18n->translate($langCode, $key, $params);

        ob_start();
        extract([
            "url" => $url,
            "__t" => $__t,
            "adminUsername" => $adminUsername,
            "contextJson" => $contextJson,
            "siteConfig" => $siteConfig,
            "authContext" => $authContext,
        ], EXTR_SKIP);
        include $templatePath;
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response
            ->withHeader("Content-Type", "text/html; charset=utf-8")
            ->withHeader("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
            ->withHeader("Pragma", "no-cache")
            ->withHeader("Expires", "0");
    }

    /**
     * Generates and returns the robots.txt file.
     */
    public function robotsTxt(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $base = $this->absoluteUrl($request, "");
        $payload =
            "User-agent: *\n" .
            "Allow: /\n" .
            "Disallow: /api/\n" .
            "Disallow: /panel\n" .
            "Disallow: /login\n" .
            "Disallow: /logout\n" .
            "Disallow: /chat\n" .
            "Disallow: /uploads/\n" .
            "Sitemap: {$base}/sitemap.xml\n";

        $response->getBody()->write($payload);
        return $response->withHeader(
            "Content-Type",
            "text/plain; charset=utf-8",
        );
    }

    /**
     * Generates and returns the sitemap.xml file for search engines.
     *
     * Includes localized URLs for all series, chapters, and blog posts.
     */
    public function sitemapXml(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $cached = $this->cache->get('sitemap_xml');
        if (is_string($cached) && $cached !== '') {
            $response->getBody()->write($cached);
            return $response->withHeader(
                "Content-Type",
                "application/xml; charset=utf-8",
            );
        }

        $urls = [];

        $push = static function (
            array &$bucket,
            string $loc,
            ?string $lastmod = null,
            string $changefreq = "daily",
            string $priority = "0.7",
        ): void {
            $bucket[] = [
                "loc" => $loc,
                "lastmod" => $lastmod,
                "changefreq" => $changefreq,
                "priority" => $priority,
            ];
        };

        $base = $this->absoluteUrl($request, "");
        $nowIso = gmdate("Y-m-d\TH:i:s\Z");

        // Canonical public URLs (without locale prefix)
        $push($urls, $base . "/", $nowIso, "hourly", "1.0");
        $push($urls, $base . "/blogs", $nowIso, "daily", "0.9");

        foreach (
            [
                "light-novel",
                "web-novel",
                "novel",
                "manga",
                "manhua",
                "manhwa",
                "webtoon",
            ]
            as $type
        ) {
            $push($urls, $base . "/" . $type, $nowIso, "daily", "0.8");
        }

        foreach ($this->seriesService->series_genres(1, 500) as $genre) {
            if (!isset($genre["slug"])) {
                continue;
            }
            $push(
                $urls,
                $base . "/genre/" . rawurlencode((string) $genre["slug"]),
                $nowIso,
                "daily",
                "0.7",
            );
        }

        foreach ($this->seriesService->series_tags(1, 500) as $tag) {
            if (!isset($tag["slug"])) {
                continue;
            }
            $push(
                $urls,
                $base . "/tag/" . rawurlencode((string) $tag["slug"]),
                $nowIso,
                "daily",
                "0.7",
            );
        }

        $seriesList = $this->seriesRepository->listContentsForSitemap(5000);
        foreach ($seriesList as $series) {
            $slug = (string) ($series["slug"] ?? "");
            $type = (string) ($series["type"] ?? "novel");
            $lastmod = !empty($series["created_at"]) ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $series["created_at"])) : $nowIso;
            if ($slug === "") {
                continue;
            }
            $push(
                $urls,
                $base . "/" . $type . "/" . rawurlencode($slug),
                $lastmod,
                "daily",
                "0.8",
            );
        }

        $chapterList = $this->seriesRepository->listChaptersForSitemap(10000);
        foreach ($chapterList as $chap) {
            $slug = (string) ($chap["slug"] ?? "");
            $type = (string) ($chap["type"] ?? "novel");
            $chapNumber = (string) ($chap["chapter_number"] ?? "");
            $lastmod = !empty($chap["created_at"]) ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $chap["created_at"])) : $nowIso;
            if ($slug === "" || $chapNumber === "") {
                continue;
            }
            $push(
                $urls,
                $base .
                    "/" .
                    $type .
                    "/" .
                    rawurlencode($slug) .
                    "/chapter/" .
                    rawurlencode($chapNumber),
                $lastmod,
                "weekly",
                "0.6",
            );
        }

        // Add blogs
        $blogs = $this->blogRepository->listApprovedForSitemap(5000);
        foreach ($blogs as $blog) {
            $slug = (string) ($blog["slug"] ?? "");
            $lastmod = !empty($blog["lastmod"]) ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $blog["lastmod"])) : $nowIso;
            if ($slug === "") {
                continue;
            }
            $push(
                $urls,
                $base . "/blogs/" . rawurlencode($slug),
                $lastmod,
                "weekly",
                "0.6",
            );
        }

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $xml[] = "  <url>";
            $xml[] =
                "    <loc>" .
                htmlspecialchars(
                    (string) $url["loc"],
                    ENT_XML1 | ENT_QUOTES,
                    "UTF-8",
                ) .
                "</loc>";
            if (!empty($url["lastmod"])) {
                $xml[] =
                    "    <lastmod>" .
                    htmlspecialchars(
                        (string) $url["lastmod"],
                        ENT_XML1 | ENT_QUOTES,
                        "UTF-8",
                    ) .
                    "</lastmod>";
            }
            $xml[] =
                "    <changefreq>" .
                htmlspecialchars(
                    (string) $url["changefreq"],
                    ENT_XML1 | ENT_QUOTES,
                    "UTF-8",
                ) .
                "</changefreq>";
            $xml[] =
                "    <priority>" .
                htmlspecialchars(
                    (string) $url["priority"],
                    ENT_XML1 | ENT_QUOTES,
                    "UTF-8",
                ) .
                "</priority>";
            $xml[] = "  </url>";
        }

        $xml[] = "</urlset>";
        $xmlContent = implode("\n", $xml);

        // Cache generated XML for 12 hours (43200s)
        $this->cache->set('sitemap_xml', $xmlContent, 43200);

        // Sync to static file on disk if public directory is writable
        $basePath = (string) ($this->settings["app"]["base_path"] ?? dirname(__DIR__, 2));
        $staticFile = $basePath . '/public/sitemap.xml';
        if (is_writable($basePath . '/public') || (is_file($staticFile) && is_writable($staticFile))) {
            @file_put_contents($staticFile, $xmlContent);
        }

        $response->getBody()->write($xmlContent);
        return $response->withHeader(
            "Content-Type",
            "application/xml; charset=utf-8",
        );
    }

    public function i18nJson(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args = [],
    ): ResponseInterface {
        $langCode = (string) ($args["lang"] ?? "tr");
        $basePath = (string) $this->settings["app"]["base_path"];
        $data = $this->loadLang($basePath, $langCode);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $hash = md5($json ?: "");

        $payload = [
            "hash" => $hash,
            "lang" => $langCode,
            "data" => $data,
        ];

        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader("Content-Type", "application/json");
    }

    /**
     * Renders a branded HTML error page (e.g., 404, 500).
     */
    public function renderError(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $code,
        string $message,
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        return $this->render(
            $request,
            $response,
            "error.php",
            [
                "errorCode" => $code,
                "errorMessage" => $message,
            ],
            [],
            "Error " . $code . " - " . $siteName,
            "container",
            [
                "title" => "Error " . $code . " - " . $siteName,
                "robots" => "noindex,nofollow",
            ],
        );
    }

    private function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $template,
        array $context = [],
        array $scripts = [],
        ?string $title = null,
        string $containerClass = "container",
        array $seo = [],
    ): ResponseInterface {
        $siteName = $this->siteConfig->siteName();
        $title = $title ?? $siteName;
        $basePath = (string) $this->settings["app"]["base_path"];

        $userId = $_SESSION["user_id"] ?? null;
        $langCode = $this->i18n->resolveLocale($request, $userId ? (string)$userId : null);
        $username = $_SESSION["username"] ?? null;
        if ($userId !== null && $username === null) {
            $profile = $this->userService->profile((string) $userId);
            $username = $profile["username"] ?? null;
        }

        $isAdmin = $this->canAccessAdminPanel();
        $_SESSION["is_admin"] = $isAdmin;

        $authContext = [
            "is_logged_in" => isset($_SESSION["user_id"]),
            "is_admin" => $isAdmin,
            "user_id" => $userId,
            "username" => $username,
            "roles" => $_SESSION["roles"] ?? [],
            "permissions" => $_SESSION["permissions"] ?? [],
            "csrf_token" => $_SESSION["csrf_token"] ?? null,
        ];

        $siteConfig = $this->siteConfig->all();

        $contextJson = (string) json_encode(
            [
                "auth" => $authContext,
                "lang_code" => $langCode,
                "site_config" => $siteConfig,
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $etagInput = [
            'template' => $template,
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

        $seoDefaults = [
            "title" => $title,
            "description" => $this->siteConfig->siteDescription(),
            "keywords" => "manga, manhwa, webtoon, novel, light novel",
            "robots" => "index,follow",
            "type" => "website",
            "image" => $this->siteConfig->defaultContentCoverImage(),
            "canonical" => (string) $request->getUri(),
            "json_ld" => null,
        ];
        $seo = array_merge($seoDefaults, $seo);

        $seoTitle = (string) $seo["title"];
        $seoDescription = $this->truncateDescription(
            (string) ($seo["description"] ?: $this->siteConfig->siteDescription())
        );
        $seoRobots = (string) $seo["robots"];
        $seoType = (string) $seo["type"];
        $seoCanonical = (string) $seo["canonical"];
        $seoImage = $this->toAbsoluteAssetUrl((string) ($seo["image"] ?: $this->siteConfig->defaultContentCoverImage()), $request);
        $seoSiteName = $this->siteConfig->siteName();
        // Render via React App Shell (app.html) if available
        $appHtmlPath = $basePath . "/public/app.html";
        if (is_file($appHtmlPath)) {
            $seoService = new \App\Services\SeoService($basePath);
            // Build merged JSON-LD graph if breadcrumbs exist
            $jsonLdPayload = [];
            $breadcrumbs = $context['breadcrumbs'] ?? [];
            if (!empty($seo['json_ld']) && is_array($seo['json_ld'])) {
                $jsonLdPayload[] = $seo['json_ld'];
            }
            if (!empty($breadcrumbs) && is_array($breadcrumbs)) {
                $breadcrumbItems = [];
                foreach ($breadcrumbs as $crumb) {
                    if (!empty($crumb['url']) && !empty($crumb['name'])) {
                        $breadcrumbItems[] = [
                            'name' => (string) $crumb['name'],
                            'url' => $this->absoluteUrl($request, (string) $crumb['url'])
                        ];
                    }
                }
                if (!empty($breadcrumbItems)) {
                    $jsonLdPayload[] = $seoService->buildBreadcrumbSchema($breadcrumbItems);
                }
            }

            $finalJsonLd = null;
            if (count($jsonLdPayload) === 1) {
                $finalJsonLd = $jsonLdPayload[0];
            } else if (count($jsonLdPayload) > 1) {
                $finalJsonLd = [
                    '@context' => 'https://schema.org',
                    '@graph' => $jsonLdPayload
                ];
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
                    'site_name' => $seoSiteName
                ],
                'twitter' => [
                    'title' => $seo['twitter_title'] ?? ($seo['og_title'] ?? $seoTitle),
                    'description' => $seo['twitter_description'] ?? ($seo['og_description'] ?? $seoDescription),
                    'image' => $seo['twitter_image'] ?? $seoImage,
                    'card' => 'summary_large_image'
                ],
                'jsonLd' => $finalJsonLd
            ];
            $layoutContent = $seoService->renderShell($seoData, $contextJson);
        } else {
            $seoService = new \App\Services\SeoService($basePath);
            $layoutContent = $seoService->renderShell([
                'title' => $title,
                'description' => $seoDescription,
                'canonical' => $seoCanonical,
                'robots' => $seoRobots,
            ], $contextJson);
        }

        $cacheControl = $authContext["is_logged_in"]
            ? "private, max-age=0, must-revalidate"
            : "public, max-age=0, s-maxage=300, stale-while-revalidate=60, stale-if-error=300";

        $response->getBody()->write($layoutContent);
        return $response
            ->withHeader("Content-Type", "text/html; charset=utf-8")
            ->withHeader("Content-Language", $langCode)
            ->withHeader("ETag", '"' . $etag . '"')
            ->withHeader("Cache-Control", $cacheControl);
    }

    private function loadLang(string $basePath, string $code): array
    {
        $path = $basePath . "/storage/lang/" . $code . ".php";
        if (is_file($path)) {
            return include $path;
        }
        $defaultPath = $basePath . "/storage/lang/" . $this->siteConfig->defaultLanguage() . ".php";
        if (is_file($defaultPath)) {
            return include $defaultPath;
        }
        $englishPath = $basePath . "/storage/lang/en.php";
        if (is_file($englishPath)) {
            return include $englishPath;
        }
        return [];
    }

    /**
     * Extracts taxonomy display names from the repository's compact aggregate.
     *
     * @return list<string>
     */
    private function taxonomyNames(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $names = [];
        foreach (explode('||', $raw) as $item) {
            $name = trim((string) (explode('::', $item, 2)[0] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function effectiveRoles(): array
    {
        return is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : [];
    }

    private function effectivePermissions(): array
    {
        $roles = $this->effectiveRoles();
        $permissions = is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];
        return $this->authorization->resolveEffectivePermissions($roles, $permissions);
    }

    private function toAbsoluteAssetUrl(
        string $url,
        ServerRequestInterface $request,
    ): string {
        if ($url === "") {
            return $this->absoluteUrl(
                $request,
                $this->siteConfig->defaultContentCoverImage(),
            );
        }
        
        // Handle protocol-relative URLs (//example.com)
        if (str_starts_with($url, "//")) {
            $scheme = $request->getUri()->getScheme() ?: "http";
            return $scheme . ":" . $url;
        }

        if (
            str_starts_with($url, "http://") ||
            str_starts_with($url, "https://")
        ) {
            return $url;
        }
        return $this->absoluteUrl($request, $url);
    }

    private function absoluteUrl(
        ServerRequestInterface $request,
        string $path,
    ): string {
        // Handle protocol-relative input path
        if (str_starts_with($path, "//")) {
            $scheme = $request->getUri()->getScheme() ?: "http";
            return $scheme . ":" . $path;
        }

        $uri = $request->getUri();
        $scheme = $uri->getScheme() ?: "http";
        $host = $uri->getHost();
        $port = $uri->getPort();

        if ($host === "") {
            $settings = \App\Config::getInstance();
            $configuredUrl = rtrim((string) ($settings['app']['url'] ?? 'http://localhost:8080'), '/');
            
            // If configured URL is protocol-relative, prepend current scheme
            if (str_starts_with($configuredUrl, "//")) {
                $configuredUrl = $scheme . ":" . $configuredUrl;
            }

            if ($path === "") {
                return $configuredUrl;
            }
            $normalized = str_starts_with($path, "/") ? $path : "/" . $path;
            return $configuredUrl . $normalized;
        }

        $authority = $host;
        if (
            $port !== null &&
            !($scheme === "http" && $port === 80) &&
            !($scheme === "https" && $port === 443)
        ) {
            $authority .= ":" . $port;
        }

        if ($path === "") {
            return sprintf("%s://%s", $scheme, $authority);
        }

        $normalized = str_starts_with($path, "/") ? $path : "/" . $path;
        return sprintf("%s://%s%s", $scheme, $authority, $normalized);
    }

    private function canAccessAdminPanel(): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        $roles = is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : [];
        $permissions = is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];
        
        // Use AuthorizationService for a reliable check, including ROOT_USER bypass
        $effectivePermissions = $this->authorization->resolveEffectivePermissions(
            $roles, 
            $permissions, 
            $userId ? (string)$userId : null
        );
        
        return in_array('admin.panel.access', $effectivePermissions, true);
    }

    private function truncateDescription(
        string $value,
        int $limit = 160,
    ): string {
        $clean = trim(preg_replace("/\s+/", " ", strip_tags($value)) ?? "");
        if ($clean === "") {
            return "";
        }
        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }

        return rtrim(mb_substr($clean, 0, $limit - 1)) . "…";
    }
}
