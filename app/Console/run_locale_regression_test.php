<?php

declare(strict_types=1);

/**
 * NM-READER — Locale Retirement & Language Preference Regression Suite.
 *
 * Verifies that:
 * 1. URL-based locale routing is completely removed from backend.
 * 2. Public canonical URLs (/, /browse, /search, /blogs, etc.) serve 200 OK without redirects.
 * 3. /admin, /api/v1/*, and /media/* are completely untouched by locale routing.
 * 4. Legacy /tr/* and /en/* URLs permanently redirect (301) to clean canonical paths.
 * 5. Invalid /tr/api, /tr/admin, /tr/media paths return 404 (no 301).
 * 6. SEO canonical and JSON-LD URLs contain zero /tr or /en prefixes.
 * 7. Protected chapter media tokens never leak into HTML.
 * 8. Language preferences and translation dictionaries function properly.
 */

namespace App\Console;

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/vendor/autoload.php';

use App\Services\I18nService;
use App\Services\SeoService;
use App\Services\SiteConfigService;
use Slim\Psr7\Factory\ServerRequestFactory;
use PDO;
use PDOStatement;

class LocaleMockPdoStatement extends PDOStatement
{
    private array $data;
    public function __construct(array $data = []) { $this->data = $data; }
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool { return true; }
    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool { return true; }
    public function execute(?array $params = null): bool { return true; }
    public function rowCount(): int { return count($this->data); }
    public function columnCount(): int { return 1; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->data[0] ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->data; }
    public function fetchColumn(int $column = 0): mixed { return '1'; }
}

class LocaleRegressionSuite
{
    private \Slim\App $app;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $mockPdo = new class extends PDO {
            public function __construct() {}
            public function prepare(string $query, array $options = []): PDOStatement {
                return new LocaleMockPdoStatement([
                    [
                        'id' => '1',
                        'name' => 'Action',
                        'slug' => 'action',
                        'title' => 'Solo Leveling',
                        'type' => 'manga',
                        'chapter_number' => '1',
                        'created_at' => '2026-08-14 12:00:00',
                        'username' => 'testuser',
                        'email' => 'test@example.com',
                        'role' => 'user',
                        'lang' => 'tr',
                        'theme' => 'dark',
                    ]
                ]);
            }
            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement {
                return new LocaleMockPdoStatement([]);
            }
            public function lastInsertId(?string $name = null): string|false { return '1'; }
            public function beginTransaction(): bool { return true; }
            public function commit(): bool { return true; }
            public function rollBack(): bool { return true; }
            public function inTransaction(): bool { return false; }
        };

        $GLOBALS['TESTING_MOCK_PDO'] = $mockPdo;

        $this->app = require dirname(__DIR__) . '/app.php';
    }

    public function run(): int
    {
        echo "==============================================================\n";
        echo "      NM-READER — LOCALE RETIREMENT REGRESSION SUITE          \n";
        echo "==============================================================\n\n";

        $this->testCleanCanonicalRoutes();
        $this->testAdminAndApiIsolation();
        $this->testLegacyUrl301Migration();
        $this->testSeoCleanCanonicals();
        $this->testUserLanguagePreference();

        echo "==============================================================\n";
        echo "TOTAL LOCALE TESTS: " . ($this->passed + $this->failed) . " | PASSED: {$this->passed} | FAILED: {$this->failed}\n";
        echo "==============================================================\n\n";

        return $this->failed > 0 ? 1 : 0;
    }

    private function assert(string $label, bool $condition, string $details = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$label}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$label}" . ($details ? " ({$details})" : "") . "\n";
        }
    }

    private function request(string $method, string $uri, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        $req = (new ServerRequestFactory())->createServerRequest($method, $uri);
        foreach ($headers as $k => $v) {
            $req = $req->withHeader($k, $v);
        }
        return $this->app->handle($req);
    }

    private function testCleanCanonicalRoutes(): void
    {
        echo "1. Testing Public Clean Canonical Routes (No Locale Redirect)...\n";

        $resHome = $this->request('GET', '/');
        $this->assert('GET / returns 200 OK (no 302 to /tr)', $resHome->getStatusCode() === 200, 'Status: ' . $resHome->getStatusCode());
        $bodyHome = (string) $resHome->getBody();
        $this->assert('GET / serves React root element', str_contains($bodyHome, '<div id="root"></div>'));

        $resBrowse = $this->request('GET', '/browse');
        $this->assert('GET /browse returns 200 OK', $resBrowse->getStatusCode() === 200, 'Status: ' . $resBrowse->getStatusCode());

        $resBlogs = $this->request('GET', '/blogs');
        $this->assert('GET /blogs returns 200 OK', $resBlogs->getStatusCode() === 200);

        $resSearch = $this->request('GET', '/search');
        $this->assert('GET /search returns 200 OK', $resSearch->getStatusCode() === 200);
        echo "\n";
    }

    private function testAdminAndApiIsolation(): void
    {
        echo "2. Testing /admin, /api/v1/*, /media/* Isolation from Locale...\n";

        $resAdmin = $this->request('GET', '/admin');
        $this->assert('GET /admin does not redirect to /tr/admin', $resAdmin->getStatusCode() === 200 || ($resAdmin->getStatusCode() === 302 && $resAdmin->getHeaderLine('Location') === '/'));

        $resPanel = $this->request('GET', '/panel');
        $this->assert('GET /panel does not redirect to /tr/panel', $resPanel->getStatusCode() === 200 || ($resPanel->getStatusCode() === 302 && $resPanel->getHeaderLine('Location') === '/'));

        $resApiHome = $this->request('GET', '/api/v1/home');
        $this->assert('GET /api/v1/home returns 200 OK without locale redirect', $resApiHome->getStatusCode() === 200);

        $resApiI18n = $this->request('GET', '/api/v1/i18n/tr');
        $this->assert('GET /api/v1/i18n/tr returns 200 OK dictionary', $resApiI18n->getStatusCode() === 200);

        $resMedia = $this->request('GET', '/media/public/nonexistent_test.jpg');
        $this->assert('GET /media/public/* returns 404 without locale redirect', $resMedia->getStatusCode() === 404);
        echo "\n";
    }

    private function testLegacyUrl301Migration(): void
    {
        echo "3. Testing Legacy /tr/* and /en/* 301 Migration...\n";

        $resTrHome = $this->request('GET', '/tr');
        $this->assert('GET /tr redirects 301 to /', $resTrHome->getStatusCode() === 301 && $resTrHome->getHeaderLine('Location') === '/');

        $resEnHome = $this->request('GET', '/en');
        $this->assert('GET /en redirects 301 to /', $resEnHome->getStatusCode() === 301 && $resEnHome->getHeaderLine('Location') === '/');

        $resTrBrowse = $this->request('GET', '/tr/browse');
        $this->assert('GET /tr/browse redirects 301 to /browse', $resTrBrowse->getStatusCode() === 301 && $resTrBrowse->getHeaderLine('Location') === '/browse');

        $resEnBlogs = $this->request('GET', '/en/blogs/test-post');
        $this->assert('GET /en/blogs/test-post redirects 301 to /blogs/test-post', $resEnBlogs->getStatusCode() === 301 && $resEnBlogs->getHeaderLine('Location') === '/blogs/test-post');

        $resTrSeries = $this->request('GET', '/tr/manga/solo-leveling');
        $this->assert('GET /tr/manga/solo-leveling redirects 301 to /manga/solo-leveling', $resTrSeries->getStatusCode() === 301 && $resTrSeries->getHeaderLine('Location') === '/manga/solo-leveling');

        $resTrApi = $this->request('GET', '/tr/api/v1/home');
        $this->assert('GET /tr/api/v1/home returns 404 (no 301 for invalid api path)', $resTrApi->getStatusCode() === 404, 'Status was: ' . $resTrApi->getStatusCode());

        $resTrAdmin = $this->request('GET', '/tr/admin');
        $this->assert('GET /tr/admin returns 404 (no 301 for invalid admin path)', $resTrAdmin->getStatusCode() === 404, 'Status was: ' . $resTrAdmin->getStatusCode());

        $resTrMedia = $this->request('GET', '/tr/media/public/cover.jpg');
        $this->assert('GET /tr/media/public/* returns 404 (no 301 for invalid media path)', $resTrMedia->getStatusCode() === 404, 'Status was: ' . $resTrMedia->getStatusCode());
        echo "\n";
    }

    private function testSeoCleanCanonicals(): void
    {
        echo "4. Testing SEO Canonical & JSON-LD Clean URLs (Zero /tr or /en)...\n";

        $seoService = new SeoService(dirname(__DIR__, 2));
        $html = $seoService->renderShell([
            'title' => 'Solo Leveling - Manga Oku',
            'canonical' => 'https://nmreader.com/manga/solo-leveling',
            'og' => [
                'url' => 'https://nmreader.com/manga/solo-leveling',
                'image' => '/media/public/solo-leveling.jpg',
            ],
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'CreativeWorkSeries',
                'name' => 'Solo Leveling',
                'url' => 'https://nmreader.com/manga/solo-leveling',
            ],
        ]);

        $this->assert('Canonical link contains clean URL without /tr', str_contains($html, '<link rel="canonical" href="https://nmreader.com/manga/solo-leveling" />'));
        $this->assert('OG:url tag contains clean URL without /tr', str_contains($html, '<meta property="og:url" content="https://nmreader.com/manga/solo-leveling" />'));
        $this->assert('JSON-LD contains clean URL without /tr', str_contains($html, 'solo-leveling') && !str_contains($html, '/tr/'));
        $this->assert('Protected media tokens sanitized in output', !str_contains($html, 't_'));
        echo "\n";
    }

    private function testUserLanguagePreference(): void
    {
        echo "5. Testing User Language Preference Persistence...\n";

        $container = $this->app->getContainer();
        $i18n = $container->get(I18nService::class);

        // Test Header Resolution
        $reqHeader = (new ServerRequestFactory())->createServerRequest('GET', '/browse')->withHeader('X-Lang', 'en');
        $resolvedHeader = $i18n->resolveLocale($reqHeader);
        $this->assert('I18nService resolves language from X-Lang header', $resolvedHeader === 'en');

        // Test Cookie Resolution
        $reqCookie = (new ServerRequestFactory())->createServerRequest('GET', '/browse')->withCookieParams(['nm_reader_lang' => 'tr']);
        $resolvedCookie = $i18n->resolveLocale($reqCookie);
        $this->assert('I18nService resolves language from cookie', $resolvedCookie === 'tr');

        // Test Dictionaries in storage/lang
        $dictTr = $i18n->getDictionary('tr');
        $dictEn = $i18n->getDictionary('en');
        $this->assert('Turkish dictionary loaded from storage/lang/tr.php', !empty($dictTr));
        $this->assert('English dictionary loaded from storage/lang/en.php', !empty($dictEn));
        echo "\n";
    }
}

$suite = new LocaleRegressionSuite();
exit($suite->run());
