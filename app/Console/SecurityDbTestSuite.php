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
    ];

    private PDO $pdo;

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
        printf("Security DB: %s (%s)\n", (string) getenv('DB_DATABASE'), $version);
        if ($phase !== 'fixtures') {
            throw new RuntimeException('Unknown phase: ' . $phase);
        }
        $this->seedFixtures();
        $this->assertFixtureIntegrity();
        echo "FIXTURES: PASS\n";
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
                ? ['body' => [['page_order' => 1, 'image_path' => 'chapter.sec102.png']], 'translator_note' => null]
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
        foreach (['published_paid', 'future_paid'] as $chapterName) {
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
