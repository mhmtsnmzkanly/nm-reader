<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BlogRepository;
use App\Repositories\SeriesRepository;

final class SitemapService
{
    public const CACHE_KEY = 'sitemap_xml';
    public const CACHE_TTL = 43200; // 12 hours

    public function __construct(
        private readonly SiteConfigService $siteConfig,
        private readonly SeriesService $seriesService,
        private readonly SeriesRepository $seriesRepository,
        private readonly BlogRepository $blogRepository,
        private readonly CacheService $cache,
        private readonly array $settings = []
    ) {
    }

    public function getCachedXml(): ?string
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return null;
    }

    public function buildSitemapXml(?string $baseUrl = null): string
    {
        $base = $this->resolveBaseUrl($baseUrl);
        $nowIso = gmdate("Y-m-d\TH:i:s\Z");

        $urls = [];
        $push = static function (
            array &$bucket,
            string $loc,
            ?string $lastmod = null,
            string $changefreq = "daily",
            string $priority = "0.7"
        ): void {
            $bucket[] = [
                "loc" => $loc,
                "lastmod" => $lastmod,
                "changefreq" => $changefreq,
                "priority" => $priority,
            ];
        };

        // Canonical public URLs
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
            ] as $type
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
                "0.7"
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
                "0.7"
            );
        }

        $seriesList = $this->seriesRepository->listContentsForSitemap(5000);
        foreach ($seriesList as $series) {
            $slug = (string) ($series["slug"] ?? "");
            $type = (string) ($series["type"] ?? "novel");
            $lastmod = !empty($series["created_at"])
                ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $series["created_at"]))
                : $nowIso;
            if ($slug === "") {
                continue;
            }
            $push(
                $urls,
                $base . "/" . $type . "/" . rawurlencode($slug),
                $lastmod,
                "daily",
                "0.8"
            );
        }

        $chapterList = $this->seriesRepository->listChaptersForSitemap(10000);
        foreach ($chapterList as $chap) {
            $slug = (string) ($chap["slug"] ?? "");
            $type = (string) ($chap["type"] ?? "novel");
            $chapNumber = (string) ($chap["chapter_number"] ?? "");
            $lastmod = !empty($chap["created_at"])
                ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $chap["created_at"]))
                : $nowIso;
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
                "0.6"
            );
        }

        // Add blogs
        $blogs = $this->blogRepository->listApprovedForSitemap(5000);
        foreach ($blogs as $blog) {
            $slug = (string) ($blog["slug"] ?? "");
            $lastmod = !empty($blog["lastmod"])
                ? gmdate("Y-m-d\TH:i:s\Z", strtotime((string) $blog["lastmod"]))
                : $nowIso;
            if ($slug === "") {
                continue;
            }
            $push(
                $urls,
                $base . "/blogs/" . rawurlencode($slug),
                $lastmod,
                "weekly",
                "0.6"
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
                    "UTF-8"
                ) .
                "</loc>";
            if (!empty($url["lastmod"])) {
                $xml[] =
                    "    <lastmod>" .
                    htmlspecialchars(
                        (string) $url["lastmod"],
                        ENT_XML1 | ENT_QUOTES,
                        "UTF-8"
                    ) .
                    "</lastmod>";
            }
            $xml[] =
                "    <changefreq>" .
                htmlspecialchars(
                    (string) $url["changefreq"],
                    ENT_XML1 | ENT_QUOTES,
                    "UTF-8"
                ) .
                "</changefreq>";
            $xml[] =
                "    <priority>" .
                htmlspecialchars(
                    (string) $url["priority"],
                    ENT_XML1 | ENT_QUOTES,
                    "UTF-8"
                ) .
                "</priority>";
            $xml[] = "  </url>";
        }

        $xml[] = "</urlset>";
        return implode("\n", $xml);
    }

    public function generateAndSave(?string $baseUrl = null): array
    {
        $this->cache->delete(self::CACHE_KEY);
        $xmlContent = $this->buildSitemapXml($baseUrl);
        $this->cache->set(self::CACHE_KEY, $xmlContent, self::CACHE_TTL);

        $basePath = (string) ($this->settings["app"]["base_path"] ?? dirname(__DIR__, 2));
        $staticFile = $basePath . '/public/sitemap.xml';

        $saved = false;
        try {
            if (is_writable($basePath . '/public') || (is_file($staticFile) && is_writable($staticFile))) {
                $saved = (@file_put_contents($staticFile, $xmlContent) !== false);
            }
        } catch (\Throwable) {
            $saved = false;
        }

        return [
            'success' => true,
            'output' => [
                'SUCCESS: Sitemap başarıyla güncellendi.',
                'Dosya: ' . $staticFile . ($saved ? ' (diske yazıldı)' : ' (önbelleğe alındı)'),
                'Boyut: ' . number_format(strlen($xmlContent) / 1024, 2) . ' KB',
            ],
        ];
    }

    private function resolveBaseUrl(?string $baseUrl = null): string
    {
        if ($baseUrl !== null && trim($baseUrl) !== '') {
            return rtrim(trim($baseUrl), '/');
        }

        $siteAddress = rtrim((string) $this->siteConfig->siteAddress(), '/');
        if ($siteAddress !== '') {
            return $siteAddress;
        }

        $appUrl = rtrim((string) ($this->settings['app']['url'] ?? ''), '/');
        if ($appUrl !== '') {
            return $appUrl;
        }

        return 'https://localhost';
    }
}
