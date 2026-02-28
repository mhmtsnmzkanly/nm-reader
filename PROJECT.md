# NovelMangaReader - Unified Project Documentation (Optimized for AI Agents)

This document serves as the absolute authority on the project's architecture, conventions, and development workflows.

---

## 1. Core Architecture (Static-First)

### Unified Configuration (`app/Config.php`)
- **Single Source of Truth**: All application settings, RBAC definitions, and routing rules are centralized in the `App\Config` class.
- **Singleton Pattern**: Use `Config::getInstance()` or `Config::getSettings()` for high-performance, memory-cached access.
- **Environment Integration**: Values are pulled from `.env` via `$_ENV` with strict fallback defaults.

### Hybrid Monolith
- **Rendering**: Uses Server-Side Rendering (SSR) for SEO and initial page loads, and Client-Side Rendering (CSR) for high-interactivity modules.
- **API**: Standardized JSON REST API under `/api/v1`.

---

## 2. Security & RBAC

### ROOT_USER (Superuser)
- Defined by the `ROOT_USER` ID in `.env`.
- **Bypass Mode**: Automatically granted all permissions, bypassing the RBAC logic.
- **Exclusive Actions**: Only the ROOT_USER can trigger system backups, sitemap generation, and cache warming via the UI.

### Static RBAC
- Roles (`admin`, `moderator`, `editor`, `user`) and their permission nodes are defined in `Config.php`.
- **Database Mapping**: The `users.roles` column stores comma-separated numeric IDs (e.g., `1,2`) which are mapped to slugs via `Config::getRbacConfig()['id_map']`.

---

## 3. Directory Structure Map

- `app/`:
    - `Config.php`: Centralized logic for Settings and Routing.
    - `app.php`: Application bootstrap and Slim initialization.
    - `dependencies.php`: PHP-DI container definitions.
    - `database/`: Contains `schema.sql` (Source of Truth).
    - `Console/`: Standalone CLI tools for maintenance.
    - `Controllers/`, `Services/`, `Repositories/`, `Middleware/`, `DTO/`, `Helpers/`: Core logic.
- `public/`: Web root.
- `storage/`:
    - `cache/`: File-based caching.
    - `logs/`: Application and audit logs.
    - `sessions/`: PHP session files.
    - `views/`: SSR PHP templates.
    - `lang/`: i18n dictionaries.

---

## 4. Development Standards & Workflow

### Backend (PHP 8.3)
- **Strict Typing**: `declare(strict_types=1);` is mandatory.
- **Layered Pattern**: Controller (HTTP) -> Service (Business Logic) -> Repository (SQL).
- **ID Generation**: Use `App\Services\EntityIdService` for alphanumeric IDs.

### Frontend (Melt.js)
- **Library**: Use the custom `melt.js` utility. Avoid standard jQuery.
- **i18n**: Use `window.NMR.__t('key')` for all UI strings.

### Git Mandate (CRITICAL)
- **Descriptive Commits**: AFTER every logical change or feature, a Git commit MUST be created with a clear description (e.g., `git commit -m "feat: add localized footer taxonomy"`).
- **Restoration**: This ensures every step is a verifiable checkpoint for potential rollbacks.

### Feature Implementation Steps:
1. **DB**: Update `app/database/schema.sql` and run migration.
2. **Backend**: DTO -> Repository -> Service -> Controller.
3. **Routing**: Register in `app/Config.php`.
4. **Frontend**: Update `connection.js` and implement UI logic.
5. **Commit**: Perform Git commit for the task.

---

## 5. Maintenance & CLI Tasks

### Core Scripts (`app/Console/`)
- `system_backup.php`: Full DB + Media backup.
- `generate_sitemap.php`: Updates `public/sitemap.xml`.
- `analytics_aggregate.php`: Aggregates stats.
- `retention_cleanup.php`: Deletes expired data.

### Cron Setup
```bash
# Every Hour: Statistics
0 * * * * php /path/to/project/app/Console/analytics_aggregate.php
# Daily at 3 AM: Backup
0 3 * * * php /path/to/project/app/Console/system_backup.php
# Daily at 12 AM: Cleanup
0 0 * * * php /path/to/project/app/Console/retention_cleanup.php
```

---

## 6. Recent Activity
### Profile CSRF & Favicon Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: 
  - Follow action was failing with 419 (Invalid CSRF token) on profile pages.
  - `/favicon.ico` was returning 404.
- **Fix**:
  - Refactored `storage/views/pages_profile.php` to use `Object.assign` for `window.__NMR_CONTEXT` to prevent overwriting the CSRF token injected by the layout.
  - Created an empty `public/favicon.ico` to satisfy browser requests.

### Profile UI & Social Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: 
  - Profile page was showing 404 for missing `user.png` and `default-cover.png`.
  - Follow button was failing with 405 because `window.__NMR_CONTEXT.person` was missing.
- **Fix**:
  - Updated `storage/views/pages_profile.php` to use CDN placeholders for default images.
  - Injected `person` variable into the JavaScript context in `pages_profile.php`.

### Database Schema Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: `profile/memo` (User: memo) was failing with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.content_id' in 'SELECT'`.
- **Root Cause**: The `social_comments` table was missing the `content_id` column which is used to link comments directly to a series.
- **Fix**:
  - Updated `app/database/schema.sql` to include `content_id` and `fk_comments_series` in `social_comments`.
  - Recommended SQL for existing installations:
    ```sql
    ALTER TABLE social_comments ADD COLUMN content_id char(6) DEFAULT NULL AFTER user_id;
    ALTER TABLE social_comments ADD CONSTRAINT fk_comments_series FOREIGN KEY (content_id) REFERENCES series(id) ON DELETE CASCADE;
    ```

### Google Analytics 4 Integration (2026-02-28)
...
- **Status**: Completed.
- **Components**:
  - Integrated `gtag.js` in `storage/views/layout_main.php`.
  - Configured `GOOGLE_ANALYTICS_ID` in `app/Config.php` (via `.env`).
  - Updated `app/middleware.php` to include necessary Content Security Policy (CSP) directives:
    - `script-src`: added `https://www.googletagmanager.com` and `https://www.google-analytics.com`.
    - `img-src`: added `https://www.google-analytics.com` and `https://www.googletagmanager.com`.
    - `connect-src`: added `https://www.google-analytics.com` and `https://region1.google-analytics.com`.

