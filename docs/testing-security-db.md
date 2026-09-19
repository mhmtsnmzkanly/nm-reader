# Database-backed security tests

`app/Console/ApiTestSuite.php` intentionally uses a mock PDO for its fast
contract checks. The database-backed suite is separate and must use an
isolated MySQL/MariaDB database.

For local verification, run:

```sh
./tools/run_security_db_tests.sh --phase=fixtures
```

The runner creates a fresh MariaDB data directory under `/tmp`, imports
`app/database/schema.sql`, uses an `nm_reader_test_*` database, and removes the
temporary server and files on exit. It never reads the application's `.env`
database credentials.

CI can use an existing disposable MariaDB service by setting `APP_ENV=test`,
`NM_READER_SECURITY_DB=1`, and `DB_DATABASE` to a name matching
`nm_reader_test_*`, then invoking the PHP suite directly. The suite fails
closed when the database is missing, unavailable, or not isolated; security
tests are never silently skipped.
