<?php

declare(strict_types=1);

/**
 * Automated SSR Retirement & Architecture Verification Suite.
 * Validates:
 * 1. Public routes cleanly serve the React App Shell (app.html) with SEO injection.
 * 2. Legacy public SSR view files are completely retired.
 * 3. Admin SSR views are preserved and intact.
 * 4. Robots indexing policies on public vs private routes.
 * 5. Media & API boundary isolation and zero token leakage.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\SeoService;

$basePath = dirname(__DIR__, 2);
$seoService = new SeoService($basePath);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$failures = [];

function assertCheck(bool $condition, string $name, string $detail = ''): void
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
echo "      NM-READER — SSR RETIREMENT VERIFICATION SUITE           \n";
echo "==============================================================\n\n";

// -----------------------------------------------------------------
// 1. Storage / Views Directory Audit (Admin Preserved, Public Retired)
// -----------------------------------------------------------------
echo "1. Auditing storage/views Directory Structure...\n";
$viewsDir = $basePath . '/storage/views';
$existingViews = array_diff(scandir($viewsDir) ?: [], ['.', '..']);

// Retired Public views that must NOT exist in storage/views
$retiredPublicViews = [
    'home.php',
    'series_list.php',
    'content.php',
    'chapter.php',
    'blog.php',
    'profile.php',
    'login.php',
    'chat.php',
    'layout_main.php',
    'partials_modals.php'
];

foreach ($retiredPublicViews as $view) {
    assertCheck(!file_exists($viewsDir . '/' . $view), "Legacy public view '{$view}' is retired");
}

// Required Admin SSR views that MUST exist in storage/views
$requiredAdminViews = [
    'admin_dashboard.php',
    'admin_content.php',
    'admin_blogs.php',
    'admin_comments.php',
    'admin_users.php',
    'admin_ops.php',
    'admin_monetization.php',
    'admin_config.php',
    'admin_uploads.php',
    'admin_logs.php',
    'admin_tutorial.php',
    'layout_adminlte.php'
];

foreach ($requiredAdminViews as $adminView) {
    assertCheck(file_exists($viewsDir . '/' . $adminView), "Admin SSR view '{$adminView}' is preserved");
}

// -----------------------------------------------------------------
// 2. React App Shell Delivery & SEO Injection
// -----------------------------------------------------------------
echo "\n2. Auditing React App Shell Delivery across Public Routes...\n";

$publicRoutes = [
    [
        'name' => 'Home Route',
        'seo' => [
            'title' => 'NM-Reader — Manga & Novel Oku',
            'description' => 'En popüler seriler',
            'canonical' => 'https://nmreader.com/tr',
            'robots' => 'index, follow',
            'jsonLd' => $seoService->buildWebSiteSchema('NM-Reader', 'https://nmreader.com')
        ],
        'expectIndex' => true
    ],
    [
        'name' => 'Content Detail Route',
        'seo' => [
            'title' => 'Solo Leveling — Manga Oku',
            'description' => 'En zayıf avcıdan en güçlü gölge hükümdarına.',
            'canonical' => 'https://nmreader.com/tr/manga/solo-leveling',
            'robots' => 'index, follow',
            'jsonLd' => $seoService->buildSeriesSchema(['title' => 'Solo Leveling'], 'https://nmreader.com/tr/manga/solo-leveling')
        ],
        'expectIndex' => true
    ],
    [
        'name' => 'Blog Detail Route',
        'seo' => [
            'title' => 'Manhwa İncelemesi — Blog',
            'description' => 'Detaylı analiz',
            'canonical' => 'https://nmreader.com/tr/blogs/manhwa-review',
            'robots' => 'index, follow',
            'jsonLd' => $seoService->buildBlogSchema(['title' => 'Manhwa İncelemesi'], 'https://nmreader.com/tr/blogs/manhwa-review')
        ],
        'expectIndex' => true
    ],
    [
        'name' => 'Private Profile Route',
        'seo' => [
            'title' => 'Profilim — NM-Reader',
            'description' => 'Kullanıcı paneli',
            'robots' => 'noindex, nofollow'
        ],
        'expectIndex' => false
    ],
    [
        'name' => 'Search Route',
        'seo' => [
            'title' => 'Arama: solo — NM-Reader',
            'description' => 'Arama sonuçları',
            'canonical' => 'https://nmreader.com/search',
            'robots' => 'noindex, follow'
        ],
        'expectIndex' => false
    ]
];

foreach ($publicRoutes as $route) {
    $renderedHtml = $seoService->renderShell($route['seo']);
    assertCheck(str_contains($renderedHtml, '<div id="root"></div>'), "{$route['name']} serves React root mounting element");
    assertCheck(str_contains($renderedHtml, '<title>' . htmlspecialchars($route['seo']['title'], ENT_QUOTES, 'UTF-8') . '</title>'), "{$route['name']} injects dynamic title");
    assertCheck(!preg_match('/<!--\s*SEO:[A-Z_]+\s*-->/', $renderedHtml), "{$route['name']} has zero unreplaced SEO placeholders");

    if ($route['expectIndex']) {
        assertCheck(str_contains($renderedHtml, 'content="index, follow"'), "{$route['name']} has index, follow robots");
    } else {
        assertCheck(str_contains($renderedHtml, 'content="noindex'), "{$route['name']} has noindex robots");
    }
}

// -----------------------------------------------------------------
// 3. Media & Protected Token Isolation
// -----------------------------------------------------------------
echo "\n3. Auditing Media URL & Protected Token Isolation...\n";
$protectedMediaInput = 't_eyJhbGciOiJIUzI1NiJ9.signature_token_test';
$safeUrl = $seoService->sanitizeMediaUrl($protectedMediaInput);
assertCheck($safeUrl === '', 'Protected chapter tokens sanitized to empty string');

$publicMediaInput = 'https://nmreader.com/media/public/cover.sololeveling.webp';
$safePublicUrl = $seoService->sanitizeMediaUrl($publicMediaInput);
assertCheck($safePublicUrl === $publicMediaInput, 'Public media URLs preserved correctly');

// -----------------------------------------------------------------
// 4. Verification Summary
// -----------------------------------------------------------------
echo "\n==============================================================\n";
echo "TOTAL RETIREMENT CHECKS: {$totalTests} | PASSED: {$passedTests} | FAILED: {$failedTests}\n";
echo "==============================================================\n\n";

if ($failedTests > 0) {
    echo "FAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
