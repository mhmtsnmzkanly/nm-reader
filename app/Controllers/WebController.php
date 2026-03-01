<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BlogRepository;
use App\Repositories\SeriesRepository;
use App\Services\AuthorizationService;
use App\Services\SiteConfigService;
use App\Services\SeriesService;
use App\Services\UserService;
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
        private readonly BlogRepository $blogRepository,
        private readonly I18nService $i18n,
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
            ["/assets/js/home.js"],
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
        $ip =
            (string) ($request->getServerParams()["REMOTE_ADDR"] ?? "unknown");

        $content = $this->seriesService->contentDetailByType($type, $slug, $ip);
        if ($content === null) {
            return $response->withStatus(404);
        }
        $dbType = str_replace("-", "_", strtolower($type));
        $startChapterNumber = $this->seriesRepository->findFirstChapterNumberByTypeAndSlug(
            $dbType,
            $slug,
        );

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
            "robots" => "index,follow",
            "json_ld" => $jsonLd,
        ];

        return $this->render(
            $request,
            $response,
            "content.php",
            [
                "type" => $type,
                "slug" => $slug,
                "ssr_data" => $content,
                "start_chapter_number" => $startChapterNumber,
            ],
            ["/assets/js/content.js"],
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
        $ip =
            (string) ($request->getServerParams()["REMOTE_ADDR"] ?? "unknown");

        $chapter = $this->seriesService->chapterDetailByTypeSlugAndNumber(
            $type,
            $slug,
            $chapterNumber,
            $ip,
        );

        $title =
            $chapter && isset($chapter["series_title"])
                ? "{$chapter["series_title"]} - Chapter {$chapterNumber}"
                : "Chapter {$chapterNumber}";

        $siteName = $this->siteConfig->siteName();
        $nextNum = $chapter["adjacent_chapters"]["next"] ?? null;
        if ($nextNum !== null) {
            $nextUrl = $this->absoluteUrl(
                $request,
                sprintf("/%s/%s/chapter/%s", $type, $slug, rawurlencode((string)$nextNum))
            );
            $response = $response->withHeader("Link", "<{$nextUrl}>; rel=prefetch; as=document");
        }

        return $this->render(
            $request,
            $response,
            "chapter.php",
            [
                "type" => $type,
                "slug" => $slug,
                "chapterNumber" => $chapterNumber,
                "ssr_chapter" => $chapter,
            ],
            ["/assets/js/reader.js"],
            $title,
            "container-fluid",
            [
                "title" => $title . " - " . $siteName,
                "description" =>
                    $chapter && isset($chapter["series_title"])
                        ? "Read {$chapter["series_title"]} chapter {$chapterNumber} online."
                        : "Chapter reading page.",
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
        $target = $person !== "" ? $person : null;
        if ($target === null && $userId !== "") {
            $me = $this->userService->profile($userId);
            $target = (string) ($me["username"] ?? "");
        }

        if ($target === null || $target === "") {
            return $response->withStatus(404);
        }

        $profile = $this->userService->publicProfile($target);
        if ($profile === null) {
            return $response->withStatus(404);
        }

        $isMe = false;
        $history = [];
        $library = [];
        $preferences = [];

        if ($userId !== "") {
            $me = $this->userService->profile($userId);
            if (
                $me !== null &&
                strtolower((string) $me["username"]) === strtolower($target)
            ) {
                $isMe = true;
                $history = $this->userService->history($userId, 1, 50);
                $library = $this->seriesService->followedContents($userId, 1, 100);
                $preferences = $this->userService->preferences($userId);
            }
        }

        $username = (string) ($profile["user"]["username"] ?? "User");
        $bio = (string) ($profile["user"]["bio"] ?? "");
        $siteName = $this->siteConfig->siteName();

        return $this->render(
            $request,
            $response,
            "profile.php",
            [
                "person" => $target,
                "isMe" => $isMe,
                "profile" => $profile,
                "history" => $history,
                "library" => $library,
                "preferences" => $preferences,
            ],
            ["/assets/js/profile.js"],
            "$username - Profile",
            "container",
            [
                "title" => "$username - " . $siteName,
                "description" => $this->truncateDescription($bio),
                "robots" => "index,follow",
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

        $ssrData = null;
        if ($slug !== "") {
            $post = $this->blogRepository->findApprovedBySlug($slug);
            if ($post !== null) {
                $ssrData = $post;
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

        return $this->render(
            $request,
            $response,
            "blog.php",
            ["slug" => $slug, "ssr_data" => $ssrData],
            ["/assets/js/blog.js"],
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
            ["/assets/js/chat.js"],
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
        $query = trim((string) ($request->getQueryParams()["q"] ?? ""));
        $title =
            $query !== ""
                ? sprintf("Arama: %s - %s", $query, $siteName)
                : "Arama - " . $siteName;

        return $this->render(
            $request,
            $response,
            "search.php",
            ["q" => $query],
            ["/assets/js/search.js"],
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
        $display = ucwords(str_replace("-", " ", $type));

        return $this->render(
            $request,
            $response,
            "series_list.php",
            [
                "list_type" => "category",
                "value" => $type,
            ],
            ["/assets/js/series_list.js"],
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

        return $this->render(
            $request,
            $response,
            "series_list.php",
            [
                "list_type" => "genre",
                "value" => $slug,
            ],
            ["/assets/js/series_list.js"],
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

        return $this->render(
            $request,
            $response,
            "series_list.php",
            [
                "list_type" => "tag",
                "value" => $slug,
            ],
            ["/assets/js/series_list.js"],
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

    public function adminDashboard(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_dashboard.php",
            ["/assets/js/admin.js"],
            "Admin Dashboard - " . $this->siteConfig->siteName(),
        );
    }

    public function adminContent(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        $siteName = $this->siteConfig->siteName();
        return $this->renderAdmin(
            $request,
            $response,
            "admin_content.php",
            ["/assets/js/admin-content.js", "/assets/js/admin-chapters.js"],
            "Content Management - " . $siteName,
        );
    }

    public function adminBlogs(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_blogs.php",
            ["/assets/js/admin-blogs.js"],
            "Blogs - Admin",
        );
    }

    public function adminComments(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_comments.php",
            ["/assets/js/admin-comments.js"],
            "Comments - Admin",
        );
    }

    public function adminUsers(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_users.php",
            ["/assets/js/admin-users.js"],
            "Users - Admin",
        );
    }

    public function adminOps(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_ops.php",
            ["/assets/js/admin-ops.js"],
            "System Ops - Admin",
        );
    }

    public function adminConfig(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_config.php",
            ["/assets/js/admin-config.js"],
            "System Config - Admin",
        );
    }

    public function adminLogs(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_logs.php",
            ["/assets/js/admin-logs.js"],
            "Logs - Admin",
        );
    }

    public function adminTutorial(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->canAccessAdminPanel()) {
            return $response->withHeader("Location", "/")->withStatus(302);
        }

        return $this->renderAdmin(
            $request,
            $response,
            "admin_tutorial.php",
            [],
            "Admin Tutorial",
        );
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
            "Disallow: /admin\n" .
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
        $urls = [];
        $supportedLangs = $this->i18n->getSupportedLanguages();

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

        // Global homepage (redirects)
        $push($urls, $base . "/", $nowIso, "hourly", "1.0");

        foreach ($supportedLangs as $lang) {
            $langBase = $base . "/" . $lang;
            $push($urls, $langBase . "/", $nowIso, "hourly", "1.0");
            $push($urls, $langBase . "/blogs", $nowIso, "daily", "0.9");

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
                $push($urls, $langBase . "/" . $type, $nowIso, "daily", "0.8");
            }

            foreach ($this->seriesService->series_genres(1, 500) as $genre) {
                if (!isset($genre["slug"])) {
                    continue;
                }
                $push(
                    $urls,
                    $langBase . "/genre/" . rawurlencode((string) $genre["slug"]),
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
                    $langBase . "/tag/" . rawurlencode((string) $tag["slug"]),
                    $nowIso,
                    "daily",
                    "0.6",
                );
            }

            foreach (
                $this->seriesRepository->listContentsForSitemap(10000)
                as $row
            ) {
                $type = str_replace("_", "-", (string) ($row["type"] ?? "novel"));
                $slug = (string) ($row["slug"] ?? "");
                if ($slug === "") {
                    continue;
                }
                $lastmod = isset($row["created_at"])
                    ? gmdate(
                        "Y-m-d\TH:i:s\Z",
                        strtotime((string) $row["created_at"]),
                    )
                    : null;
                $push(
                    $urls,
                    $langBase . "/" . $type . "/" . rawurlencode($slug),
                    $lastmod,
                    "daily",
                    "0.8",
                );
            }

            foreach (
                $this->seriesRepository->listChaptersForSitemap(20000)
                as $row
            ) {
                $type = str_replace("_", "-", (string) ($row["type"] ?? "novel"));
                $slug = (string) ($row["slug"] ?? "");
                $chapter = (string) ($row["chapter_number"] ?? "");
                if ($slug === "" || $chapter === "") {
                    continue;
                }
                $lastmod = isset($row["created_at"])
                    ? gmdate(
                        "Y-m-d\TH:i:s\Z",
                        strtotime((string) $row["created_at"]),
                    )
                    : null;
                $push(
                    $urls,
                    $langBase .
                        "/" .
                        $type .
                        "/" .
                        rawurlencode($slug) .
                        "/chapter/" .
                        rawurlencode($chapter),
                    $lastmod,
                    "weekly",
                    "0.7",
                );
            }

            foreach ($this->blogRepository->listApprovedForSitemap(1000) as $row) {
                $slug = (string) ($row["slug"] ?? "");
                if ($slug === "") {
                    continue;
                }
                $lastmod = isset($row["lastmod"])
                    ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $row["lastmod"]))
                    : null;
                $push(
                    $urls,
                    $langBase . "/blogs/" . rawurlencode($slug),
                    $lastmod,
                    "weekly",
                    "0.7",
                );
            }
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

        $response->getBody()->write(implode("\n", $xml));
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
        $templatePath = $basePath . "/storage/views/pages_" . $template;
        $layoutPath = $basePath . "/storage/views/layout_main.php";

        if (!is_file($templatePath)) {
            $response->getBody()->write("Template not found");
            return $response->withStatus(404);
        }

        // Get lang from URL attribute (from Slim router)
        $routeContext = \Slim\Routing\RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $urlLang = $route ? $route->getArgument('lang') : null;

        $userId = $_SESSION["user_id"] ?? null;
        $langCode = $this->i18n->resolveLocale($request, $userId ? (string)$userId : null);

        $defaultLang = $this->i18n->getDefaultLanguage();
        $defaultTheme = $this->siteConfig->defaultTheme();
        $prefs = $userId
            ? $this->userService->preferences((string)$userId)
            : ["theme" => $defaultTheme, "lang" => $defaultLang, "reader" => []];
        
        // Sync language preference from URL to DB if logged in
        if ($urlLang !== null && $userId !== null && $urlLang !== $prefs["lang"]) {
            try {
                $prefs = $this->userService->updatePreferences((string)$userId, ["lang" => $urlLang]);
            } catch (\Throwable $e) {
                // Fail silently to not block rendering
            }
        }
        
        $theme = $prefs["theme"] ?? "dark";
        
        // ETag Generation: Create a hash based on inputs that affect the final HTML
        // This includes template name, context data, user state, language, and theme.
        $etagInput = [
            'template' => $template,
            'context' => $context,
            'user_id' => $userId,
            'lang' => $langCode,
            'theme' => $theme,
            'seo' => $seo,
            'title' => $title,
            'app_version' => '1.1.0' // Incremented on UI changes
        ];
        $etag = md5((string) json_encode($etagInput));

        // Check for If-None-Match header
        $noneMatch = $request->getHeaderLine('If-None-Match');
        if ($noneMatch === '"' . $etag . '"') {
            return $response->withStatus(304);
        }

        // SEO Strategy: URL Lang takes precedence. 
        if ($urlLang === null && $template !== "robotsTxt" && $template !== "sitemapXml") {
            $uri = $request->getUri();
            $path = $uri->getPath();
            
            $supportedLangs = $this->i18n->getSupportedLanguages();
            $pathParts = explode("/", ltrim($path, "/"));
            $firstPart = $pathParts[0] ?? "";
            $alreadyHasLang = in_array($firstPart, $supportedLangs, true);

            if ($alreadyHasLang || $path === "/logout" || str_starts_with($path, "/api") || str_starts_with($path, "/admin")) {
                // No redirect
            } else {
                $newPath = "/" . $langCode . ($path === "/" ? "" : $path);
                $query = $uri->getQuery();
                if ($query !== "") $newPath .= "?" . $query;
                return $response->withHeader("Location", $newPath)->withStatus(302);
            }
        }

        // Load language for SSR
        $lang = $this->i18n->getDictionary($langCode);
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
            "preferences" => $prefs,
        ];
        
        $url = function(string $path) use ($langCode) {
            $cleanPath = ltrim($path, "/");
            return "/" . $langCode . "/" . $cleanPath;
        };

        $footerGenres = $this->seriesService->series_genres(1, 20);
        $footerTags = $this->seriesService->series_tags(1, 20);

        $contextJson = (string) json_encode(
            [
                "auth" => $authContext,
                "lang_code" => $langCode,
                "site_config" => $this->siteConfig->all(),
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $fullContext = array_merge($context, [
            "auth" => $authContext, 
            "lang" => $lang, 
            "langCode" => $langCode,
            "url" => $url,
            "footerGenres" => $footerGenres,
            "footerTags" => $footerTags,
            "contextJson" => $contextJson
        ]);

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
        $seoKeywords = (string) $seo["keywords"];
        $seoRobots = (string) $seo["robots"];
        $seoType = (string) $seo["type"];
        $seoCanonical = (string) $seo["canonical"];
        $seoImage = $this->toAbsoluteAssetUrl((string) ($seo["image"] ?: $this->siteConfig->defaultContentCoverImage()), $request);
        $seoSiteName = $this->siteConfig->siteName();
        $seoLocale = $langCode === "tr" ? "tr_TR" : "en_US";

        $jsonLd = null;
        if (is_array($seo["json_ld"])) {
            $jsonLd = json_encode($seo["json_ld"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $__t = fn(string $key) => $lang[$key] ?? $key;
        $fullContext['__t'] = $__t;
        $fullContext['siteConfig'] = $this->siteConfig->all();

        // Capture Output
        ob_start();
        extract($fullContext, EXTR_SKIP);
        include $templatePath;
        $capturedContent = (string) ob_get_clean();

        ob_start();
        $content = $capturedContent;
        extract($fullContext, EXTR_SKIP);
        include $layoutPath;
        $layoutContent = (string) ob_get_clean();

        $response->getBody()->write($layoutContent);
        return $response
            ->withHeader("Content-Type", "text/html; charset=utf-8")
            ->withHeader("Content-Language", $langCode)
            ->withHeader("ETag", '"' . $etag . '"')
            ->withHeader("Cache-Control", "public, max-age=0, must-revalidate");
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

    private function renderAdmin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $template,
        array $scripts = [],
        string $title = "Admin Dashboard",
    ): ResponseInterface {
        $basePath = (string) $this->settings["app"]["base_path"];
        $templatePath = $basePath . "/storage/views/pages_" . $template;
        $layoutPath = $basePath . "/storage/views/layout_adminlte.php";

        if (!is_file($templatePath) || !is_file($layoutPath)) {
            $response->getBody()->write("Template not found");
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

        $url = function(string $path) use ($langCode) {
            $cleanPath = ltrim($path, "/");
            return "/" . $langCode . "/" . $cleanPath;
        };

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
        $__t = fn(string $key) => $lang[$key] ?? $key;

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
        $content = (string) ob_get_clean();

        ob_start();
        extract([
            "url" => $url,
            "__t" => $__t,
            "adminUsername" => $adminUsername,
            "contextJson" => $contextJson,
            "content" => $content,
            "title" => $title,
            "scripts" => $scripts,
            "siteConfig" => $siteConfig,
            "authContext" => $authContext,
        ], EXTR_SKIP);
        include $layoutPath;
        $layoutContent = (string) ob_get_clean();

        $response->getBody()->write($layoutContent);
        return $response
            ->withHeader("Content-Type", "text/html; charset=utf-8")
            ->withHeader(
                "Cache-Control",
                "no-store, no-cache, must-revalidate, max-age=0",
            )
            ->withHeader("Pragma", "no-cache")
            ->withHeader("Expires", "0");
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
