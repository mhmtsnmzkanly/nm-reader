# NM-Reader Storage Write Diagnostic Guide

**Tool:** `app/Console/StorageCheck.php`  
**Command:** `composer check:storage` (or `php app/Console/StorageCheck.php`)  
**Scope:** Automated filesystem read/write/delete health verification for all persistent storage roots.

---

## 1. Overview & Purpose

The `check:storage` diagnostic tool inspects and actively verifies whether the PHP process has sufficient filesystem permissions to create, read, and delete runtime files within the application's storage directories.

### Key Principles:
- **Active Probe Testing:** Does not rely solely on `is_writable()`. It performs an actual write, read, and delete cycle using a temporary probe file (`.diagnostic_probe_*.tmp`).
- **No Side Effects:** Does not alter permissions (`chmod`/`chown`), does not delete existing user/system files, and immediately cleans up test probe files.
- **CI & Deployment Ready:** Exits with code `0` on health, and non-zero (`1`) on any critical failure.

---

## 2. Discovered Storage Roots

| Storage Root | Canonical Path | Role | Critical |
|:---|:---|:---|:---:|
| **Public & Chapter Media** | `storage/media/` | Storage for public covers (`/media/public/*`) and protected chapter token pages (`/media/chapter/t_*`) | **YES** |
| **Sessions** | `storage/sessions/` | Server-side PHP session state and auth tokens (`nm_reader_session`) | **YES** |
| **System Logs** | `storage/logs/` | Monolog access, error, and audit trail logs | **YES** |
| **Cache** | `storage/cache/` | Query caching and metadata storage | NO |
| **Backups** | `storage/backups/` | Automated database and media archives | NO |

---

## 3. Diagnostic Probe Cycle

For each target root, the probe executes 7 sequential checks:

1. **Exists:** Directory exists on disk (`file_exists()`).
2. **Directory:** Target is a directory, not a plain file (`is_dir()`).
3. **Readable:** Directory readable by current process (`is_readable()`).
4. **Writable:** Directory writable flag set (`is_writable()`).
5. **Write Probe:** Writes random cryptographically generated payload to `.diagnostic_probe_{id}.tmp`.
6. **Read Probe:** Reads back payload and verifies exact byte-for-byte integrity.
7. **Delete Probe:** Unlinks the temporary probe file and verifies removal.

---

## 4. Execution & Example Outputs

### Running the Diagnostic
```bash
composer check:storage
# OR
php app/Console/StorageCheck.php
```

### Example Healthy Output (Exit Code: 0)
```text
==============================================================
              NM-READER STORAGE DIAGNOSTIC                    
==============================================================

Environment:
  PHP Version:  8.3.x
  Process User: www-data
  Base Path:    /var/www/nm-reader

--------------------------------------------------------------
Target: [media] Public & Chapter Media Storage
Path:   /var/www/nm-reader/storage/media
Role:   Serves public assets (/media/public/*) and protected chapter tokens (/media/chapter/t_*)
--------------------------------------------------------------
  Exists:     [PASS]
  Directory:  [PASS]
  Readable:   [PASS]
  Writable:   [PASS]
  Write:      [PASS]
  Read:       [PASS]
  Delete:     [PASS]
  Status:     >>> HEALTHY <<<

--------------------------------------------------------------
Target: [sessions] Session Storage
Path:   /var/www/nm-reader/storage/sessions
Role:   PHP Session state and authentication tokens
--------------------------------------------------------------
  Exists:     [PASS]
  Directory:  [PASS]
  Readable:   [PASS]
  Writable:   [PASS]
  Write:      [PASS]
  Read:       [PASS]
  Delete:     [PASS]
  Status:     >>> HEALTHY <<<

==============================================================
DIAGNOSTIC RESULT: PASS (All storage targets verified)
==============================================================
```

### Example Failure Output (Exit Code: 1)
```text
--------------------------------------------------------------
Target: [media] Public & Chapter Media Storage
Path:   /var/www/nm-reader/storage/media
Role:   Serves public assets (/media/public/*) and protected chapter tokens (/media/chapter/t_*)
--------------------------------------------------------------
  Exists:     [PASS]
  Directory:  [PASS]
  Readable:   [PASS]
  Writable:   [FAIL]
  Write:      [SKIP]
  Read:       [SKIP]
  Delete:     [SKIP]
  Status:     >>> UNHEALTHY <<<
  ERROR:      DIRECTORY_PERMISSION_DENIED

==============================================================
DIAGNOSTIC RESULT: FAIL (Storage errors detected)
==============================================================
```

---

## 5. Troubleshooting Permission Errors

If `composer check:storage` reports `DIRECTORY_PERMISSION_DENIED` or `DIRECTORY_NOT_FOUND`:

1. **Check Ownership:** Ensure the web server / PHP-FPM process user owns the storage tree:
   ```bash
   sudo chown -R www-data:www-data storage/
   ```
2. **Check Permissions:** Set proper directory permissions (0775 for shared group, 0755 standard):
   ```bash
   sudo chmod -R 775 storage/
   ```
3. **SELinux / AppArmor:** If running on CentOS/RHEL/Ubuntu with SELinux enforcing:
   ```bash
   sudo chcon -R -t httpd_sys_rw_content_t storage/
   ```
