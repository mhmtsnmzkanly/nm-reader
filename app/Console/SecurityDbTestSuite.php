<?php

declare(strict_types=1);

/**
 * Database-backed security verification suite.
 *
 * This command deliberately refuses to run against a normal application
 * database. The disposable runner sets APP_ENV=test and uses an
 * nm_reader_test_* database name; CI may provide the same values for an
 * isolated MariaDB service.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class SecurityDbTestSuite
{
    private const FIXTURE_USERS = [
        'normal' => ['id' => 'secusr01', 'token' => 'security-normal-token', 'verified' => false],
        'verified' => ['id' => 'secusr02', 'token' => 'security-verified-token', 'verified' => true],
        'unpurchased' => ['id' => 'secusr03', 'token' => 'security-unpurchased-token', 'verified' => true],
        'purchased' => ['id' => 'secusr04', 'token' => 'security-purchased-token', 'verified' => true],
    ];

    private const FIXTURE_SERIES = [
        'published' => ['id' => 'sec001', 'slug' => 'security-published-series', 'lifecycle' => 'published', 'deleted' => null, 'members' => 0],
        'draft' => ['id' => 'sec002', 'slug' => 'security-draft-series', 'lifecycle' => 'draft', 'deleted' => null, 'members' => 0],
        'archived' => ['id' => 'sec003', 'slug' => 'security-archived-series', 'lifecycle' => 'archived', 'deleted' => null, 'members' => 0],
        'deleted' => ['id' => 'sec004', 'slug' => 'security-deleted-series', 'lifecycle' => 'published', 'deleted' => '2099-01-01 00:00:00', 'members' => 0],
        'members' => ['id' => 'sec005', 'slug' => 'security-members-series', 'lifecycle' => 'published', 'deleted' => null, 'members' => 1],
    ];

    private const FIXTURE_CHAPTERS = [
        'published_free' => ['id' => 'sec101', 'series' => 'published', 'number' => '1', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'published_paid' => ['id' => 'sec102', 'series' => 'published', 'number' => '2', 'type' => 'image', 'members' => 0, 'price' => 15, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'published_members' => ['id' => 'sec103', 'series' => 'published', 'number' => '3', 'type' => 'text', 'members' => 1, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'future_free' => ['id' => 'sec104', 'series' => 'published', 'number' => '4', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2099-01-01 00:00:00', 'deleted' => null],
        'future_paid' => ['id' => 'sec105', 'series' => 'published', 'number' => '5', 'type' => 'text', 'members' => 0, 'price' => 25, 'published' => '2099-01-01 00:00:00', 'deleted' => null],
        'deleted' => ['id' => 'sec106', 'series' => 'published', 'number' => '6', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => '2099-01-01 00:00:00'],
        'draft_parent' => ['id' => 'sec107', 'series' => 'draft', 'number' => '1', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'archived_parent' => ['id' => 'sec108', 'series' => 'archived', 'number' => '1', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'deleted_parent' => ['id' => 'sec109', 'series' => 'deleted', 'number' => '1', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'members_parent' => ['id' => 'sec110', 'series' => 'members', 'number' => '1', 'type' => 'text', 'members' => 0, 'price' => 0, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'protected_media' => ['id' => 'sec111', 'series' => 'published', 'number' => '7', 'type' => 'image', 'members' => 0, 'price' => 15, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
        'published_paid_text' => ['id' => 'sec112', 'series' => 'published', 'number' => '8', 'type' => 'text', 'members' => 0, 'price' => 15, 'published' => '2020-01-01 00:00:00', 'deleted' => null],
    ];

    private PDO $pdo;
    private ?\Slim\App $app = null;
    private int $requestCounter = 0;

    public function __construct()
    {
        $this->assertIsolatedEnvironment();
        $settings = $this->databaseSettings();
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $settings['host'], $settings['port'], $settings['database'], $settings['charset']);
        try {
            $this->pdo = new PDO($dsn, $settings['username'], $settings['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Security DB is unavailable: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function run(string $phase): void
    {
        $version = (string) $this->pdo->query('SELECT VERSION()')->fetchColumn();
        $this->seedFixtures();
        $this->assertFixtureIntegrity();
        if ($phase === 'fixtures') {
            printf("Security DB: %s (%s)\n", (string) getenv('DB_DATABASE'), $version);
            echo "FIXTURES: PASS\n";
            return;
        }
        if ($phase === 'chapter') {
            $this->testChapterVisibility();
            printf("Security DB: %s (%s)\n", (string) getenv('DB_DATABASE'), $version);
            echo "CHAPTER VISIBILITY: PASS\n";
            return;
        }
        if ($phase === 'comments') {
            $this->testCommentVisibility();
            printf("Security DB: %s (%s)\n", (string) getenv('DB_DATABASE'), $version);
            echo "COMMENT VISIBILITY: PASS\n";
            return;
        }
        if ($phase === 'paid') {
            $this->testPaidAccess();
            printf("Security DB: %s (%s)\n", (string) getenv('DB_DATABASE'), $version);
            echo "PAID ACCESS: PASS\n";
            return;
        }
        if ($phase === 'media') {
            $this->testMediaAuthorization();
            printf("Security DB: %s (%s)\n", (string) getenv('DB_DATABASE'), $version);
            echo "MEDIA AUTHORIZATION: PASS\n";
            return;
        }
        throw new RuntimeException('Unknown phase: ' . $phase);
    }

    private function assertIsolatedEnvironment(): void
    {
        if (strtolower(trim((string) (getenv('APP_ENV') ?: ''))) !== 'test') {
            throw new RuntimeException('Refusing security DB tests unless APP_ENV=test.');
        }
        $database = trim((string) (getenv('DB_DATABASE') ?: ''));
        if (!preg_match('/^nm_reader_test_[a-z0-9_]+$/', $database)) {
            throw new RuntimeException('Refusing security DB tests for non-isolated database name.');
        }
        if ((string) getenv('NM_READER_SECURITY_DB') !== '1') {
            throw new RuntimeException('NM_READER_SECURITY_DB=1 is required for the isolated test harness.');
        }
    }

    /** @return array{host:string,port:int,database:string,username:string,password:string,charset:string} */
    private function databaseSettings(): array
    {
        $host = trim((string) getenv('DB_HOST'));
        $database = trim((string) getenv('DB_DATABASE'));
        if ($host === '' || $database === '') {
            throw new RuntimeException('DB_HOST and DB_DATABASE are required.');
        }
        return [
            'host' => $host,
            'port' => max(1, (int) (getenv('DB_PORT') ?: 3306)),
            'database' => $database,
            'username' => (string) (getenv('DB_USERNAME') ?: 'root'),
            'password' => (string) (getenv('DB_PASSWORD') ?: ''),
            'charset' => (string) (getenv('DB_CHARSET') ?: 'utf8mb4'),
        ];
    }

    private function seedFixtures(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->removeFixtureRows();
            $this->insertUsers();
            $this->insertSeries();
            $this->insertChapters();
            $this->insertUnlock();
            $this->insertComments();
            $this->insertBlogs();
            $this->pdo->commit();
            $this->prepareMediaFixture();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function removeFixtureRows(): void
    {
        $ids = array_column(self::FIXTURE_CHAPTERS, 'id');
        $seriesIds = array_column(self::FIXTURE_SERIES, 'id');
        $userIds = array_column(self::FIXTURE_USERS, 'id');
        $placeholders = static fn (array $values): string => implode(',', array_fill(0, count($values), '?'));
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE target_id IN (' . $placeholders($ids) . ') OR target_id IN (' . $placeholders($seriesIds) . ')');
        $stmt->execute([...$ids, ...$seriesIds]);
        $stmt = $this->pdo->prepare('DELETE FROM user_unlocks WHERE target_id IN (' . $placeholders($ids) . ') OR user_id IN (' . $placeholders($userIds) . ')');
        $stmt->execute([...$ids, ...$userIds]);
        $stmt = $this->pdo->prepare('DELETE FROM chapters WHERE id IN (' . $placeholders($ids) . ')');
        $stmt->execute($ids);
        $this->pdo->exec('DELETE FROM blogs WHERE id IN ("sec201","sec202","sec203","sec204","sec205")');
        $stmt = $this->pdo->prepare('DELETE FROM series WHERE id IN (' . $placeholders($seriesIds) . ')');
        $stmt->execute($seriesIds);
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id IN (' . $placeholders($userIds) . ')');
        $stmt->execute($userIds);
    }

    private function insertUsers(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (id, username, display_name, email, email_verified_at, password_hash, roles, api_token, api_token_expires_at) VALUES (:id,:username,:display_name,:email,:verified,:password_hash,"4",:api_token,"2099-01-01 00:00:00")');
        foreach (self::FIXTURE_USERS as $name => $user) {
            $stmt->execute([
                'id' => $user['id'], 'username' => 'security_' . $name,
                'display_name' => 'Security ' . ucfirst($name), 'email' => 'security_' . $name . '@example.test',
                'verified' => $user['verified'] ? '2020-01-01 00:00:00' : null,
                'password_hash' => password_hash('security-test-password', PASSWORD_DEFAULT),
                'api_token' => hash('sha256', $user['token']),
            ]);
        }
    }

    private function insertSeries(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO series (id,title,slug,type,is_members_only,status,lifecycle_status,scheduled_at,published_at,description,author,artist,deleted_at) VALUES (:id,:title,:slug,"manga",:members,"ongoing",:lifecycle,NULL,:published_at,:description,"Security Fixture","Security Fixture",:deleted_at)');
        foreach (self::FIXTURE_SERIES as $name => $series) {
            $stmt->execute([
                'id' => $series['id'], 'title' => 'Security ' . ucfirst($name) . ' Series', 'slug' => $series['slug'],
                'members' => $series['members'], 'lifecycle' => $series['lifecycle'],
                'published_at' => $series['lifecycle'] === 'published' ? '2020-01-01 00:00:00' : null,
                'description' => 'Deterministic database-backed security fixture.', 'deleted_at' => $series['deleted'],
            ]);
        }
    }

    private function insertChapters(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO chapters (id,content_id,number,chapter_number,title,type,data,is_members_only,price_amount,published_at,created_by,deleted_at) VALUES (:id,:content_id,:number,:chapter_number,:title,:type,:data,:members,:price,:published_at,:created_by,:deleted_at)');
        foreach (self::FIXTURE_CHAPTERS as $name => $chapter) {
            $data = $chapter['type'] === 'image'
                ? ['body' => [['page_order' => 1, 'image_path' => 'chapter.' . $chapter['id'] . '.png']], 'translator_note' => null]
                : ['body' => 'SECURITY FIXTURE BODY ' . $name, 'translator_note' => null];
            $stmt->execute([
                'id' => $chapter['id'], 'content_id' => self::FIXTURE_SERIES[$chapter['series']]['id'],
                'number' => (float) $chapter['number'], 'chapter_number' => $chapter['number'],
                'title' => 'Security ' . ucfirst(str_replace('_', ' ', $name)), 'type' => $chapter['type'],
                'data' => json_encode($data, JSON_THROW_ON_ERROR), 'members' => $chapter['members'], 'price' => $chapter['price'],
                'published_at' => $chapter['published'], 'created_by' => self::FIXTURE_USERS['normal']['id'], 'deleted_at' => $chapter['deleted'],
            ]);
        }
    }

    private function insertUnlock(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO user_unlocks (user_id,unlock_type,target_id,content_id,price_coin,unlocked_at) VALUES (:user_id,"chapter",:target_id,:content_id,15,"2020-01-01 00:00:00")');
        foreach (['published_paid', 'future_paid', 'protected_media', 'published_paid_text'] as $chapterName) {
            $stmt->execute(['user_id' => self::FIXTURE_USERS['purchased']['id'], 'target_id' => self::FIXTURE_CHAPTERS[$chapterName]['id'], 'content_id' => self::FIXTURE_SERIES['published']['id']]);
        }
    }

    private function insertComments(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO comments (user_id,target_type,target_id,body,moderation_status,deleted_at) VALUES (:user_id,:target_type,:target_id,:body,:status,:deleted_at)');
        $comments = [
            ['series', 'published', 'approved public series comment', 'approved', null], ['series', 'published', 'pending comment must stay hidden', 'pending', null],
            ['series', 'published', 'hidden comment must stay hidden', 'hidden', null], ['series', 'published', 'deleted comment must stay hidden', 'deleted', '2099-01-01 00:00:00'],
            ['series', 'draft', 'draft parent comment', 'approved', null], ['series', 'archived', 'archived parent comment', 'approved', null], ['series', 'deleted', 'deleted parent comment', 'approved', null],
            ['chapter', 'published_free', 'approved public chapter comment', 'approved', null], ['chapter', 'published_free', 'hidden chapter comment', 'hidden', null],
            ['chapter', 'future_free', 'future chapter comment', 'approved', null], ['chapter', 'deleted', 'deleted chapter comment', 'approved', null], ['chapter', 'draft_parent', 'draft parent chapter comment', 'approved', null],
        ];
        foreach ($comments as [$targetType, $target, $body, $status, $deletedAt]) {
            $targetId = $targetType === 'series' ? self::FIXTURE_SERIES[$target]['id'] : self::FIXTURE_CHAPTERS[$target]['id'];
            $stmt->execute(['user_id' => self::FIXTURE_USERS['normal']['id'], 'target_type' => $targetType, 'target_id' => $targetId, 'body' => $body, 'status' => $status, 'deleted_at' => $deletedAt]);
        }
    }

    private function insertBlogs(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO blogs (id,user_id,title,slug,body,status,approved,approver_user_id,approved_at,deleted_at) VALUES (:id,:user_id,:title,:slug,:body,:status,:approved,:approver,:approved_at,:deleted_at)');
        foreach ([['sec201', 'published', 1], ['sec202', 'draft', 0], ['sec203', 'pending', 0], ['sec204', 'rejected', 0], ['sec205', 'hidden', 0]] as [$id, $status, $approved]) {
            $stmt->execute(['id' => $id, 'user_id' => self::FIXTURE_USERS['normal']['id'], 'title' => 'Security ' . $status . ' blog', 'slug' => 'security-' . $status . '-blog', 'body' => 'Security blog fixture body.', 'status' => $status, 'approved' => $approved, 'approver' => $approved ? self::FIXTURE_USERS['verified']['id'] : null, 'approved_at' => $approved ? '2020-01-01 00:00:00' : null, 'deleted_at' => null]);
        }
    }

    private function assertFixtureIntegrity(): void
    {
        foreach (self::FIXTURE_USERS as $user) $this->assertCount('users', 'id = ?', [$user['id']], 1);
        foreach (self::FIXTURE_SERIES as $series) $this->assertCount('series', 'id = ? AND slug = ?', [$series['id'], $series['slug']], 1);
        foreach (self::FIXTURE_CHAPTERS as $chapter) $this->assertCount('chapters', 'id = ? AND content_id = ?', [$chapter['id'], self::FIXTURE_SERIES[$chapter['series']]['id']], 1);
        $this->assertCount('user_unlocks', 'user_id = ? AND target_id = ?', [self::FIXTURE_USERS['purchased']['id'], self::FIXTURE_CHAPTERS['published_paid']['id']], 1);
        $this->assertCount('user_unlocks', 'user_id = ? AND target_id = ?', [self::FIXTURE_USERS['purchased']['id'], self::FIXTURE_CHAPTERS['future_paid']['id']], 1);
        $future = $this->pdo->prepare('SELECT ch.published_at, s.lifecycle_status, s.deleted_at FROM chapters ch INNER JOIN series s ON s.id = ch.content_id WHERE ch.id = ?');
        $future->execute([self::FIXTURE_CHAPTERS['future_free']['id']]);
        $row = $future->fetch();
        $this->assertTrue(is_array($row) && (string) $row['lifecycle_status'] === 'published' && $row['deleted_at'] === null && (string) $row['published_at'] > '2026-01-01 00:00:00', 'future chapter fixture is not coherent');
        $this->assertCount('comments', 'target_type = "series" AND target_id = ?', [self::FIXTURE_SERIES['published']['id']], 4);
        $this->assertCount('blogs', 'id IN ("sec201","sec202","sec203","sec204","sec205")', [], 5);
    }

    private function assertCount(string $table, string $where, array $params, int $expected): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
        $stmt->execute($params);
        $this->assertTrue((int) $stmt->fetchColumn() === $expected, sprintf('fixture assertion failed for %s', $table));
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) throw new RuntimeException($message);
    }

    private function testChapterVisibility(): void
    {
        $futureFreePath = '/api/v1/content/manga/security-published-series/chapter/4';
        $futurePaidPath = '/api/v1/content/manga/security-published-series/chapter/5';
        $deletedPath = '/api/v1/content/manga/security-published-series/chapter/6';

        $this->assertStatus($this->request('GET', $futureFreePath), 404, 'future free chapter detail');
        $this->assertStatus($this->request('GET', $futurePaidPath), 404, 'future paid chapter detail (guest)');
        $this->assertStatus($this->request('GET', $futurePaidPath, self::FIXTURE_USERS['purchased']['token']), 404, 'future paid chapter detail (purchased)');
        $this->assertStatus($this->request('GET', $deletedPath), 404, 'deleted chapter detail');

        $list = $this->jsonRequest('GET', '/api/v1/content/manga/security-published-series/chapters');
        $this->assertStatus($list, 200, 'series chapter list');
        $this->assertIdsAbsent($list['json']['data'] ?? [], ['sec104', 'sec105', 'sec106'], 'series chapter list publication filter');

        $overview = $this->jsonRequest('GET', '/api/v1/content/manga/security-published-series/overview');
        $this->assertStatus($overview, 200, 'series overview');
        $this->assertIdsAbsent($overview['json']['data']['chapters'] ?? [], ['sec104', 'sec105', 'sec106'], 'series overview publication filter');

        $latest = $this->jsonRequest('GET', '/api/v1/latest-chapters');
        $this->assertStatus($latest, 200, 'latest chapters');
        $this->assertIdsAbsent($latest['json']['data'] ?? [], ['sec104', 'sec105', 'sec106'], 'latest chapters publication filter');

        $latestType = $this->jsonRequest('GET', '/api/v1/content/manga/chapters');
        $this->assertStatus($latestType, 200, 'latest chapters by type');
        $this->assertIdsAbsent($latestType['json']['data'] ?? [], ['sec104', 'sec105', 'sec106'], 'latest chapters by type publication filter');

        $published = $this->jsonRequest('GET', '/api/v1/content/manga/security-published-series/chapter/1');
        $this->assertStatus($published, 200, 'published free chapter detail');
        $this->assertTrue(($published['json']['data']['body'] ?? null) === 'SECURITY FIXTURE BODY published_free', 'published free chapter body was not returned');
    }

    private function testCommentVisibility(): void
    {
        foreach (['security-draft-series', 'security-archived-series', 'security-deleted-series'] as $slug) {
            $this->assertStatus($this->request('GET', '/api/v1/content/manga/' . $slug . '/comments'), 404, 'non-public series comments: ' . $slug);
        }
        foreach (['sec104', 'sec106', 'sec107', 'sec108', 'sec109'] as $chapterId) {
            $this->assertStatus($this->request('GET', '/api/v1/chapter/' . $chapterId . '/comments'), 404, 'non-public chapter comments: ' . $chapterId);
        }

        $series = $this->jsonRequest('GET', '/api/v1/content/manga/security-published-series/comments');
        $this->assertStatus($series, 200, 'published series comments');
        $this->assertVisibleCommentBodies($series['json']['data'] ?? [], ['approved public series comment'], ['pending comment must stay hidden', 'hidden comment must stay hidden', 'deleted comment must stay hidden']);

        $chapter = $this->jsonRequest('GET', '/api/v1/chapter/sec101/comments');
        $this->assertStatus($chapter, 200, 'published chapter comments');
        $this->assertVisibleCommentBodies($chapter['json']['data'] ?? [], ['approved public chapter comment'], ['hidden chapter comment']);
    }

    private function testPaidAccess(): void
    {
        $path = '/api/v1/content/manga/security-published-series/chapter/8';
        $guest = $this->jsonRequest('GET', $path);
        $this->assertStatus($guest, 200, 'paid text chapter guest metadata');
        $this->assertTrue(($guest['json']['data']['body'] ?? null) === null && ($guest['json']['data']['pages'] ?? null) === [], 'guest received paid chapter content');
        $this->assertTrue(!str_contains((string) json_encode($guest['json']), '/media/chapter/'), 'guest received protected media URL');

        $unpaid = $this->jsonRequest('GET', $path, self::FIXTURE_USERS['unpurchased']['token']);
        $this->assertStatus($unpaid, 200, 'paid text chapter unpurchased metadata');
        $this->assertTrue(($unpaid['json']['data']['body'] ?? null) === null && ($unpaid['json']['data']['pages'] ?? null) === [], 'unpurchased user received paid chapter content');
        $this->assertTrue(!str_contains((string) json_encode($unpaid['json']), '/media/chapter/'), 'unpurchased user received protected media URL');

        $purchased = $this->jsonRequest('GET', $path, self::FIXTURE_USERS['purchased']['token']);
        $this->assertStatus($purchased, 200, 'paid text chapter purchased access');
        $this->assertTrue(($purchased['json']['data']['body'] ?? null) === 'SECURITY FIXTURE BODY published_paid_text', 'purchased user did not receive paid chapter body');
        $this->assertTrue(($purchased['json']['data']['is_locked'] ?? true) === false, 'purchased chapter remained locked');

        $futurePurchased = $this->request('GET', '/api/v1/content/manga/security-published-series/chapter/5', self::FIXTURE_USERS['purchased']['token']);
        $this->assertStatus($futurePurchased, 404, 'purchased future paid chapter');

        $membersOnly = '/api/v1/content/manga/security-published-series/chapter/3';
        $membersGuest = $this->jsonRequest('GET', $membersOnly);
        $this->assertStatus($membersGuest, 200, 'members-only chapter guest metadata contract');
        $this->assertTrue(($membersGuest['json']['data']['body'] ?? null) === null && ($membersGuest['json']['data']['pages'] ?? null) === [], 'guest received members-only chapter content');
        $membersUser = $this->jsonRequest('GET', $membersOnly, self::FIXTURE_USERS['verified']['token']);
        $this->assertStatus($membersUser, 200, 'members-only chapter non-member metadata contract');
        $this->assertTrue(($membersUser['json']['data']['body'] ?? null) === 'SECURITY FIXTURE BODY published_members', 'authenticated members-only chapter contract changed unexpectedly');
    }

    private function prepareMediaFixture(): void
    {
        $directory = trim((string) getenv('MEDIA_STORAGE_PATH'));
        if ($directory === '') throw new RuntimeException('MEDIA_STORAGE_PATH is required for database-backed media tests.');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Unable to create isolated media test directory.');
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . 'chapter.sec111.png', 'SECURITY MEDIA FIXTURE') === false) throw new RuntimeException('Unable to create isolated media fixture.');
    }

    private function testMediaAuthorization(): void
    {
        $chapter = $this->jsonRequest('GET', '/api/v1/content/manga/security-published-series/chapter/7', self::FIXTURE_USERS['purchased']['token']);
        $this->assertStatus($chapter, 200, 'authorized media chapter detail');
        $url = (string) ($chapter['json']['data']['pages'][0]['url'] ?? '');
        $this->assertTrue(str_starts_with($url, '/media/chapter/'), 'authorized response did not contain a signed media URL');
        $token = substr($url, strlen('/media/chapter/'));

        $valid = $this->request('GET', '/api/v1/media/chapter/' . $token, self::FIXTURE_USERS['purchased']['token']);
        $this->assertStatus($valid, 200, 'valid authorized media token');
        $this->assertTrue($valid['body'] === 'SECURITY MEDIA FIXTURE', 'valid media token returned unexpected file');
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . $tampered, self::FIXTURE_USERS['purchased']['token']), 403, 'tampered media token');
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . $token, self::FIXTURE_USERS['unpurchased']['token']), 403, 'wrong user audience');

        $media = new \App\Services\MediaService((string) getenv('MEDIA_STORAGE_PATH'), (string) getenv('MEDIA_SECRET'));
        $expired = $this->tokenFor('sec111', 1, 'chapter.sec111.png', self::FIXTURE_USERS['purchased']['id'], time() - 10);
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . $expired, self::FIXTURE_USERS['purchased']['token']), 403, 'expired media token');
        $wrongChapter = $media->generateChapterPageUrl('sec101', 1, 'chapter.sec111.png', self::FIXTURE_USERS['purchased']['id']);
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . basename($wrongChapter), self::FIXTURE_USERS['purchased']['token']), 403, 'wrong chapter media token');
        $wrongPage = $media->generateChapterPageUrl('sec111', 2, 'chapter.sec111.png', self::FIXTURE_USERS['purchased']['id']);
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . basename($wrongPage), self::FIXTURE_USERS['purchased']['token']), 403, 'wrong page media token');
        $wrongFile = $media->generateChapterPageUrl('sec111', 1, 'chapter.other.png', self::FIXTURE_USERS['purchased']['id']);
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . basename($wrongFile), self::FIXTURE_USERS['purchased']['token']), 403, 'wrong filename media token');

        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        session_id('secdb-guest-audience');
        session_start();
        $guestToken = $media->generateChapterPageUrl('sec111', 1, 'chapter.sec111.png', null);
        session_write_close();
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . basename($guestToken)), 403, 'wrong guest session audience');

        $traversal = $this->tokenFor('sec111', 1, '../chapter.sec111.png', self::FIXTURE_USERS['purchased']['id'], time() + 600);
        $traversalResponse = $this->request('GET', '/api/v1/media/chapter/' . $traversal, self::FIXTURE_USERS['purchased']['token']);
        $this->assertStatus($traversalResponse, 200, 'signed traversal basename normalization');
        $this->assertTrue($traversalResponse['body'] === 'SECURITY MEDIA FIXTURE', 'traversal token escaped isolated media boundary');
        $encodedTraversal = $this->tokenFor('sec111', 1, '..%2F..%2Fchapter.sec111.png', self::FIXTURE_USERS['purchased']['id'], time() + 600);
        $this->assertStatus($this->request('GET', '/api/v1/media/chapter/' . $encodedTraversal, self::FIXTURE_USERS['purchased']['token']), 403, 'encoded traversal token');
    }

    private function tokenFor(string $chapterId, int $page, string $filename, string $userId, int $expires): string
    {
        $payload = ['cid' => $chapterId, 'p' => $page, 'f' => $filename, 'uid' => $userId, 'sid' => null, 'exp' => $expires];
        $encoded = rtrim(strtr(base64_encode((string) json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        return 't_' . $encoded . '.' . hash_hmac('sha256', $encoded, (string) getenv('MEDIA_SECRET'));
    }

    private function assertVisibleCommentBodies(array $items, array $required, array $forbidden): void
    {
        $encoded = json_encode($items, JSON_UNESCAPED_SLASHES);
        $this->assertTrue(is_string($encoded), 'comment response could not be encoded');
        foreach ($required as $body) $this->assertTrue(str_contains($encoded, $body), 'approved comment missing: ' . $body);
        foreach ($forbidden as $body) $this->assertTrue(!str_contains($encoded, $body), 'hidden comment leaked: ' . $body);
    }

    /** @return array{status:int,json:array,headers:array<string,array<string>>} */
    private function jsonRequest(string $method, string $path, ?string $token = null): array
    {
        $result = $this->request($method, $path, $token);
        $json = json_decode($result['body'], true);
        $this->assertTrue(is_array($json), 'expected JSON response for ' . $method . ' ' . $path);
        $result['json'] = $json;
        return $result;
    }

    /** @return array{status:int,body:string,headers:array<string,array<string>>} */
    private function request(string $method, string $path, ?string $token = null): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $this->requestCounter++;
        $sessionId = 'secdb' . str_pad((string) $this->requestCounter, 20, '0', STR_PAD_LEFT);
        if (session_status() === PHP_SESSION_NONE) {
            session_id($sessionId);
        }
        $factory = new \Slim\Psr7\Factory\ServerRequestFactory();
        $request = $factory->createServerRequest($method, 'http://127.0.0.1' . $path, [
            'REMOTE_ADDR' => '198.51.100.10',
            'REQUEST_URI' => $path,
        ]);
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }
        try {
            $response = $this->application()->handle($request);
        } catch (Throwable $exception) {
            throw new RuntimeException('HTTP request failed: ' . $method . ' ' . $path . ': ' . $exception->getMessage(), 0, $exception);
        }
        return ['status' => $response->getStatusCode(), 'body' => (string) $response->getBody(), 'headers' => $response->getHeaders()];
    }

    private function application(): \Slim\App
    {
        if ($this->app === null) {
            $this->app = \App\Config::createApp();
        }
        return $this->app;
    }

    private function assertStatus(array $response, int $expected, string $label): void
    {
        $this->assertTrue($response['status'] === $expected, sprintf('%s expected HTTP %d, got %d (%s)', $label, $expected, $response['status'], substr($response['body'], 0, 240)));
    }

    private function assertIdsAbsent(array $items, array $ids, string $label): void
    {
        $encoded = json_encode($items, JSON_UNESCAPED_SLASHES);
        foreach ($ids as $id) {
            $this->assertTrue(!is_string($encoded) || !str_contains($encoded, '"id":"' . $id . '"'), $label . ' leaked chapter ' . $id);
        }
    }
}

$phase = 'fixtures';
foreach (array_slice($argv, 1) as $argument) if (str_starts_with($argument, '--phase=')) $phase = substr($argument, 8);
try {
    (new SecurityDbTestSuite())->run($phase);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "SECURITY DB TESTS FAILED: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
