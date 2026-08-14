<?php

declare(strict_types=1);

/**
 * Automated SEO & Structured Data Verification Suite for NM-Reader.
 * Validates SeoService, app.html shell replacement, XSS escaping, JSON-LD schemas,
 * OpenGraph, Twitter Cards, robots policies, and token non-leakage.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\SeoService;

$basePath = dirname(__DIR__, 2);
$seoService = new SeoService($basePath);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function assertTest(bool $condition, string $name, string $detail = ''): void
{
    global $totalTests, $passedTests, $failedTests, $failures;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$name}\n";
    } else {
        $failedTests++;
        $failures[] = "{$name}" . ($detail !== '' ? " -> {$detail}" : '');
        echo "  [FAIL] {$name}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
    }
}

echo "==============================================================\n";
echo "      NM-READER — SEO INJECTION & STRUCTURED DATA TESTS       \n";
echo "==============================================================\n\n";

// -----------------------------------------------------------------
// 1. Shell Injection & Placeholder Cleanliness
// -----------------------------------------------------------------
echo "1. Testing Shell Injection & Placeholder Cleanliness...\n";
$html = $seoService->renderShell([
    'title' => 'Test Homepage — NM-Reader',
    'description' => 'Test platform description for manga & novels.',
    'canonical' => 'https://nmreader.com/tr',
    'robots' => 'index, follow',
    'og' => [
        'title' => 'Test Homepage — NM-Reader',
        'description' => 'Test platform description.',
        'image' => 'https://nmreader.com/media/public/cover.default.webp',
        'type' => 'website',
        'site_name' => 'NM-Reader'
    ],
    'twitter' => [
        'title' => 'Test Homepage — NM-Reader',
        'description' => 'Test platform description.',
        'image' => 'https://nmreader.com/media/public/cover.default.webp',
        'card' => 'summary_large_image'
    ],
    'jsonLd' => $seoService->buildWebSiteSchema('NM-Reader', 'https://nmreader.com')
]);

assertTest(str_contains($html, '<title>Test Homepage — NM-Reader</title>'), 'Title tag injected correctly');
assertTest(str_contains($html, '<meta name="description" content="Test platform description for manga &amp; novels." />'), 'Meta description tag injected');
assertTest(str_contains($html, '<meta name="robots" content="index, follow" />'), 'Meta robots tag injected');
assertTest(str_contains($html, '<link rel="canonical" href="https://nmreader.com/tr" />'), 'Canonical link tag injected');
assertTest(str_contains($html, '<meta property="og:title" content="Test Homepage — NM-Reader" />'), 'OG title injected');
assertTest(str_contains($html, '<meta name="twitter:card" content="summary_large_image" />'), 'Twitter card injected');
assertTest(str_contains($html, '<script type="application/ld+json">'), 'JSON-LD script block injected');
assertTest(!preg_match('/<!--\s*SEO:[A-Z_]+\s*-->/', $html), 'Zero raw SEO comment placeholders left in output');

// Duplicate check
preg_match_all('/<title>/i', $html, $titleMatches);
assertTest(count($titleMatches[0]) === 1, 'Exactly one <title> tag exists (no duplicate title)');
preg_match_all('/<link rel="canonical"/i', $html, $canonMatches);
assertTest(count($canonMatches[0]) === 1, 'Exactly one canonical link tag exists');

// -----------------------------------------------------------------
// 2. HTML Security & XSS Escaping
// -----------------------------------------------------------------
echo "\n2. Testing HTML Attribute Escaping & XSS Protection...\n";
$xssPayload = '<script>alert("xss")</script>" onmouseover="alert(1)';
$htmlXss = $seoService->renderShell([
    'title' => $xssPayload,
    'description' => $xssPayload,
    'canonical' => 'https://nmreader.com/test',
    'robots' => 'noindex, nofollow',
    'og' => ['title' => $xssPayload]
]);

assertTest(!str_contains($htmlXss, '<script>alert("xss")</script>'), 'Raw script tags stripped or escaped in title/meta');
assertTest(str_contains($htmlXss, '&quot; onmouseover=&quot;alert(1)'), 'Attributes safely escaped with htmlspecialchars');

// -----------------------------------------------------------------
// 3. Protected Chapter Token Leakage Guard
// -----------------------------------------------------------------
echo "\n3. Testing Protected Chapter Media Non-Leakage...\n";
$protectedToken = 't_eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.sig123';
$protectedUrl = 'https://nmreader.com/media/chapter/' . $protectedToken;

$htmlMedia = $seoService->renderShell([
    'title' => 'Solo Leveling — Chapter 179',
    'description' => 'Read chapter 179',
    'og' => ['image' => $protectedUrl],
    'twitter' => ['image' => $protectedUrl],
    'jsonLd' => [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWorkSeries',
        'image' => $seoService->sanitizeMediaUrl($protectedUrl)
    ]
]);

assertTest(!str_contains($htmlMedia, $protectedToken), 'Protected chapter tokens NEVER leak into HTML output');
assertTest(!str_contains($htmlMedia, '/media/chapter/'), 'Chapter media URLs filtered out from OG/Twitter/JSON-LD');

// -----------------------------------------------------------------
// 4. JSON-LD Schema Validation
// -----------------------------------------------------------------
echo "\n4. Validating Structured Data (JSON-LD) Schemas...\n";

// A. WebSite Schema
$websiteSchema = $seoService->buildWebSiteSchema('NM-Reader', 'https://nmreader.com');
assertTest($websiteSchema['@type'] === 'WebSite', 'WebSite schema type valid');
assertTest($websiteSchema['potentialAction']['@type'] === 'SearchAction', 'SearchAction potentialAction valid');

// B. Series Schema
$seriesSchema = $seoService->buildSeriesSchema([
    'title' => 'Omniscient Reader\'s Viewpoint',
    'description' => 'A reader survives in a novel world.',
    'cover_image' => 'https://nmreader.com/media/public/cover.orv.webp',
    'genres' => ['Action', 'Fantasy', 'Supernatural'],
    'author' => 'Sing Shong'
], 'https://nmreader.com/manhwa/orv', 'https://nmreader.com');

assertTest($seriesSchema['@type'] === 'CreativeWorkSeries', 'CreativeWorkSeries schema type valid');
assertTest($seriesSchema['author']['name'] === 'Sing Shong', 'Author person entity valid');
assertTest($seriesSchema['image'] === 'https://nmreader.com/media/public/cover.orv.webp', 'Series cover image valid');

// C. BlogPosting Schema
$blogSchema = $seoService->buildBlogSchema([
    'title' => 'Top 10 Manhwa to Read in 2026',
    'summary' => 'Our curated list of the best ongoing action manhwa.',
    'cover_image' => 'https://nmreader.com/media/public/blog.top10.webp',
    'created_at' => '2026-08-14 12:00:00',
    'author_name' => 'Editor Dave'
], 'https://nmreader.com/blogs/top-10-manhwa-2026', 'https://nmreader.com');

assertTest($blogSchema['@type'] === 'BlogPosting', 'BlogPosting schema type valid');
assertTest($blogSchema['headline'] === 'Top 10 Manhwa to Read in 2026', 'Blog headline valid');
assertTest($blogSchema['author']['name'] === 'Editor Dave', 'Blog author person entity valid');

// D. Breadcrumb Schema
$breadcrumbSchema = $seoService->buildBreadcrumbSchema([
    ['name' => 'Ana Sayfa', 'url' => 'https://nmreader.com/tr'],
    ['name' => 'Manga', 'url' => 'https://nmreader.com/tr/manga'],
    ['name' => 'Solo Leveling', 'url' => 'https://nmreader.com/tr/manga/solo-leveling'],
    ['name' => 'Bölüm 1', 'url' => 'https://nmreader.com/tr/manga/solo-leveling/chapter/1']
]);

assertTest($breadcrumbSchema['@type'] === 'BreadcrumbList', 'BreadcrumbList schema type valid');
assertTest(count($breadcrumbSchema['itemListElement']) === 4, 'Breadcrumb contains 4 hierarchical items');
assertTest($breadcrumbSchema['itemListElement'][2]['position'] === 3, 'Breadcrumb position indexing is 1-based');

// Test Combined @graph JSON-LD injection into HTML
$combinedGraph = [
    '@context' => 'https://schema.org',
    '@graph' => [$seriesSchema, $breadcrumbSchema]
];
$htmlGraph = $seoService->renderShell(['title' => 'Series Page', 'jsonLd' => $combinedGraph]);
preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $htmlGraph, $jsonMatches);
assertTest(!empty($jsonMatches[1]), 'JSON-LD script extracted from rendered HTML');

$decoded = json_decode(trim($jsonMatches[1]), true);
assertTest(is_array($decoded) && isset($decoded['@graph']), 'Rendered JSON-LD is valid, uncorrupted JSON graph');
assertTest(count($decoded['@graph']) === 2, 'Graph contains both CreativeWorkSeries and BreadcrumbList');

// -----------------------------------------------------------------
// 5. Route Policies (Indexable vs Non-Indexable)
// -----------------------------------------------------------------
echo "\n5. Testing Route Indexability & Canonical Policies...\n";

// Private route (Profile / Wallet / Settings)
$htmlPrivate = $seoService->renderShell([
    'title' => 'Cüzdanım — NM-Reader',
    'robots' => 'noindex, nofollow'
]);
assertTest(str_contains($htmlPrivate, '<meta name="robots" content="noindex, nofollow" />'), 'Private route sets noindex, nofollow');

// Search route
$htmlSearch = $seoService->renderShell([
    'title' => 'Arama: solo — NM-Reader',
    'canonical' => 'https://nmreader.com/search',
    'robots' => 'noindex, follow'
]);
assertTest(str_contains($htmlSearch, '<meta name="robots" content="noindex, follow" />'), 'Search page sets noindex, follow');
assertTest(str_contains($htmlSearch, '<link rel="canonical" href="https://nmreader.com/search" />'), 'Search page canonical ignores search query params');

// 404 Error page
$html404 = $seoService->renderShell([
    'title' => 'Error 404 — NM-Reader',
    'robots' => 'noindex, nofollow'
]);
assertTest(str_contains($html404, '<meta name="robots" content="noindex, nofollow" />'), '404 error page sets noindex, nofollow');

echo "\n==============================================================\n";
echo "TOTAL SEO TESTS: {$totalTests} | PASSED: {$passedTests} | FAILED: {$failedTests}\n";
echo "==============================================================\n\n";

if ($failedTests > 0) {
    exit(1);
}
