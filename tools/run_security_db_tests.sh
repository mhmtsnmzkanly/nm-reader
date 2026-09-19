#!/usr/bin/env bash
set -euo pipefail

# Disposable MariaDB harness for database-backed security verification.
# It never reads the application's .env database credentials and refuses to
# run against a non-test database name.

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
data_dir="$(mktemp -d /tmp/nm-reader-security-db.XXXXXX)"
socket_path="$data_dir/mysql.sock"
port="${NM_READER_TEST_PORT:-$((20000 + RANDOM % 20000))}"
database="nm_reader_test_${RANDOM}${RANDOM}"
server_pid=""

cleanup() {
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -rf "$data_dir"
}
trap cleanup EXIT INT TERM

mariadb-install-db --datadir="$data_dir" --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1
mariadbd \
    --datadir="$data_dir" \
    --socket="$socket_path" \
    --port="$port" \
    --bind-address=127.0.0.1 \
    --pid-file="$data_dir/mysql.pid" \
    --skip-name-resolve \
    --log-error="$data_dir/error.log" \
    --user="$(id -un)" >/dev/null 2>&1 &
server_pid=$!

ready=0
for _ in $(seq 1 100); do
    if mariadb-admin --no-defaults --socket="$socket_path" -uroot ping >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 0.1
done
if [[ "$ready" != "1" ]]; then
    cat "$data_dir/error.log" >&2 || true
    echo "MariaDB disposable test server did not become ready" >&2
    exit 1
fi

mariadb --no-defaults --socket="$socket_path" -uroot -e \
    "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb --no-defaults --socket="$socket_path" -uroot "$database" < "$repo_root/app/database/schema.sql"
mkdir -p "$data_dir/media"

export APP_ENV=test
export APP_DEBUG=false
export APP_URL=http://127.0.0.1
export ENFORCE_HTTPS=false
export MEDIA_SECRET=security-test-media-secret-012345678901234567890123
export MEDIA_STORAGE_PATH="$data_dir/media"
export DB_HOST=127.0.0.1
export DB_PORT="$port"
export DB_DATABASE="$database"
export DB_USERNAME=root
export DB_PASSWORD=
export DB_PERSISTENT=false
export NM_READER_SECURITY_DB=1

php "$repo_root/app/Console/SecurityDbTestSuite.php" "$@"
test_exit_code=$?
exit "$test_exit_code"
