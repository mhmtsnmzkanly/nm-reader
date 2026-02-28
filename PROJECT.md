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
    - `database/schema.sql`: The primary source of truth for the database structure.
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

## 4. Development Standards

### Backend (PHP 8.3)
- **Strict Typing**: `declare(strict_types=1);` is mandatory.
- **Layered Pattern**: Controller (HTTP) -> Service (Business Logic) -> Repository (SQL).
- **ID Generation**: Use `App\Services\EntityIdService` for alphanumeric IDs (`char(8)` for users, `char(6)` for content).

### Frontend (Melt.js)
- **Library**: Use the custom `melt.js` utility. Avoid standard jQuery.
- **i18n**: Use `window.NMR.__t('key')` for all UI strings.
- **Connection**: Use `connection.js` for all API calls to benefit from automatic CSRF and deduplication.

---

## 5. Maintenance & CLI Tasks

### Core Scripts (`app/Console/`)
- `system_backup.php`: Full DB + Media backup.
- `generate_sitemap.php`: Updates the physical `public/sitemap.xml`.
- `analytics_aggregate.php`: Processes raw events into daily snapshots.
- `retention_cleanup.php`: Deletes expired logs, cache, and sessions.

### Cron Setup
```bash
# Every Hour: Statistics
0 * * * * php /path/to/project/app/Console/analytics_aggregate.php
# Daily at 3 AM: Backup
0 3 * * * php /path/to/project/app/Console/system_backup.php
# Daily at 12 AM: Cleanup
0 0 * * * php /path/to/project/app/Console/retention_cleanup.php
```
