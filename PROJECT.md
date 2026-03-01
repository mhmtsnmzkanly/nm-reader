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
### Taxonomy Data Update (2026-03-01)
- **Status**: Completed.
- **Change**: Updated the default list of genres and tags to be more comprehensive and specifically tailored for a novel/manga platform.
- **Details**:
  - Genres now include specific icons (e.g., `bi-fire` for Action, `bi-heart` for Romance).
  - Tags now include color mappings for UI badges (e.g., `danger` for OP Protagonist, `success` for Leveling).
  - Updated both `app/database/schema.sql` and `app/Console/seed_default_data.php` to ensure consistency between new installs and existing data syncs.

### Content Description Markdown Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Content descriptions on series detail pages were rendered as plain text, ignoring Markdown formatting.
- **Fix**:
  - Updated `storage/views/pages_content.php` to include an ID and the `markdown-body` class for the description container.
  - Updated `public/assets/js/content.js` to automatically parse and render the description using `marked.js` (via `NMR.parseMarkdown`) on page load.

### Content Metadata Visibility Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Description field was appearing empty when editing content in the admin panel.
- **Root Cause**: The `AdminConsoleService::listContents` method was only allowing the `title` field to pass through its sanitization layer for administrative listings, effectively stripping `description`, `cover_image`, and other metadata before they reached the frontend.
- **Fix**: Updated `AdminConsoleService::listContents` to include `description` in the list of allowed and sanitized fields, ensuring full metadata is available for administrative operations.

### Content Management & Upload Fixes (2026-03-01)
- **Status**: Completed.
- **Problem**: 
  - Content covers and descriptions were being cleared when editing a series.
  - All uploads were using the "chapter" prefix, regardless of type.
  - The upload tracking table was missing from the schema.
- **Fix**:
  - Added `cover_image` and `description` to `AdminConsoleRepository::listContents` so they are correctly populated in the edit modal.
  - Refactored `AdminService::updateContent` to only update fields that are explicitly provided in the API payload, preventing accidental data loss.
  - Updated `public/assets/js/admin-content.js` and `storage/views/pages_admin_content.php` to correctly pass the `type` parameter during bulk and specific image uploads.
  - Restored the `system_uploads` table to `app/database/schema.sql` for persistent tracking of all image assets.

### Content UI & Search & Social Fixes (2026-03-01)
- **Status**: Completed.
- **Problem**: 
  - Tag chips on content pages were white and unreadable.
  - Comment system was failing with 404 because it was targeting chapter-level endpoints even on series pages.
  - Search page was showing "Please enter a search term" even when a query was provided.
- **Fix**:
  - Added missing theme color variables (`--primary`, `--success`, etc.) to `:root` in `public/assets/css/site.css`.
  - Implemented series-level comment API endpoints and updated `CommentService` and `CommentRepository` to support them.
  - Updated `public/assets/js/connection.js` and `public/assets/js/content.js` to correctly route comments based on context (chapter vs series).
  - Updated `WebController::render` to include SSR context data in `window.__NMR_CONTEXT`, allowing `search.js` to correctly identify the search query.

### Content Taxonomy Update Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Updating tags and genres for a content item was failing with a 404 error.
- **Root Cause**:
  - Missing route for `PUT /api/v1/admin/contents/{id}/taxonomy` in `app/Config.php`.
  - Mismatch between the JSON payload keys sent by the frontend (`genres`, `tags`) and those expected by the backend controller (`series_genres`, `series_tags`).
- **Fix**:
  - Added the missing route to the admin API group in `app/Config.php`.
  - Updated `AdminConsoleController::updateTaxonomy` to support both sets of keys (`series_genres`/`genres` and `series_tags`/`tags`).
  - Implemented `updateContentTaxonomy`, `createGenre`, and `createTag` in `AdminConsoleRepository` to resolve the 500 Internal Server Error (Call to undefined method).

### Auth Form Robustness Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: Login/Register forms were occasionally failing with 400 Bad Request due to unreliable field extraction.
- **Fix**:
  - Added explicit `name` attributes to all authentication input fields in `storage/views/partials_modals.php`.
  - Refactored `main.js` to use `FormData` and `Object.fromEntries` for clean, reliable data extraction (including Turnstile tokens).

### Route Restoration & Turnstile JS Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: 
  - Several user routes (`/user/profile`, `/user/history`, etc.) were accidentally removed during the previous logout fix.
  - Turnstile token was not being correctly extracted from the login/register forms.
- **Fix**:
  - Restored all missing routes in `app/Config.php`.
  - Updated `main.js` to use a more reliable selector `[name="cf-turnstile-response"]` for extracting the Turnstile token.

### Turnstile Verification Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: Turnstile verification was failing in some environments due to `file_get_contents` SSL restrictions.
- **Fix**: 
  - Switched verification logic in `AuthController.php` to use `cURL`.
  - Added an automatic fallback to disable SSL verification if the initial strict request fails, ensuring reliability across different server configurations.

### Database Schema Fix for Logout (2026-02-28)
- **Status**: Completed.
- **Problem**: Logout was failing with `Unknown column 'revoked_at' in 'SET'`.
- **Root Cause**: The `user_sessions` table was missing columns, and `user_refresh_tokens` and `user_login_logs` tables were completely missing from the schema.
- **Fix**: 
  - Updated `app/database/schema.sql` with full table definitions.
  - Recommended SQL for existing installations:
    ```sql
    ALTER TABLE user_sessions ADD COLUMN last_seen_at datetime NOT NULL DEFAULT current_timestamp() AFTER expires_at;
    ALTER TABLE user_sessions ADD COLUMN revoked_at datetime DEFAULT NULL AFTER last_seen_at;

    CREATE TABLE `user_refresh_tokens` (
      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      `session_key` char(32) NOT NULL,
      `token_hash` char(64) NOT NULL,
      `expires_at` datetime NOT NULL,
      `revoked_at` datetime DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `token_hash` (`token_hash`),
      CONSTRAINT `fk_tokens_session` FOREIGN KEY (`session_key`) REFERENCES `user_sessions` (`session_key`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `user_login_logs` (
      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` char(8) DEFAULT NULL,
      `email` varchar(150) NOT NULL,
      `ip_hash` char(64) NOT NULL,
      `user_agent` varchar(255) DEFAULT NULL,
      `success` tinyint(1) NOT NULL,
      `failure_reason` varchar(50) DEFAULT NULL,
      `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ```

### Dropdown UX & Mobile Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: Dropdowns were relying on hover, which is unreliable on mobile, and didn't close when clicking outside.
- **Fix**:
  - Refactored dropdown visibility to use an `.active` class in `site.css`.
  - Added `onclick` toggles to all dropdown buttons (Library, Language, User Menu).
  - Implemented a global click-outside listener in `main.js` to automatically close open dropdowns.

### Failsafe Logout Implementation (2026-02-28)
- **Status**: Completed.
- **Problem**: AJAX-based logout was unreliable due to session/CSRF state conflicts.
- **Fix**:
  - Moved logout route to a top-level GET/POST route `/logout` in `app/Config.php`, bypassing strict CSRF/Auth middleware.
  - Converted the logout button in the UI (`main.js`) from a button with an AJAX listener to a standard `<a>` link to `/logout`.
  - This ensures the browser performs a full navigation, forcing the server to kill the session and the browser to reload all state.

### Aggressive Logout Reliability (2026-02-28)
- **Status**: Completed.
- **Problem**: Logout button was still failing for some users.
- **Fix**:
  - Enhanced `AuthController::logout` with `session_regenerate_id(true)` and double `Set-Cookie` headers for session and remember-me cookies.
  - Refactored `main.js` to clear all `sessionStorage`, manually expire cookies on the client side, and use `window.location.href` for a hard reset.

### Logout Fix & Robustness (2026-02-28)
- **Status**: Completed.
- **Problem**: Logout button was not reliably logging users out.
- **Fix**:
  - Improved `AuthController::logout` to thoroughly clear `$_SESSION` and expire the session cookie.
  - Updated `CsrfMiddleware` to skip validation for the logout endpoint to avoid 419 errors during session transitions.
  - Refactored `main.js` to clear `sessionStorage` and force a localized hard redirect after logout.

### Performance & CLS Optimizations (2026-02-28)
- **Status**: Completed.
- **Problem**: Poor performance metrics (FCP 3.0s, CLS 0.839).
- **Fix**:
  - Implemented **Skeleton Loading** for the homepage to prevent layout jumps.
  - Added fixed **aspect-ratio (2/3)** to series cover containers in CSS.
  - Applied `loading="lazy"` to all dynamically rendered images in `home.js`.
  - Reserved minimum height for Cloudflare Turnstile widget to prevent modal reflow.
  - Set `font-display: swap` for faster initial text rendering.

### Final CSP Refinement for Cloudflare Turnstile (2026-02-28)
- **Status**: Completed.
- **Problem**: Persistent browser warnings regarding implicit `script-src` and preload timing.
- **Fix**: 
  - Added `script-src-attr 'self' 'unsafe-inline'`.
  - Added `https://challenges.cloudflare.com` to `img-src` and `font-src`.

### Refined CSP for Cloudflare Turnstile (2026-02-28)
- **Status**: Completed.
- **Problem**: Browser warnings about implicit `script-src` and internal Turnstile logs.
- **Fix**: 
  - Added `script-src-elem` explicitly.
  - Added `worker-src` and `child-src` for `https://challenges.cloudflare.com`.

### CSP Update for Cloudflare Insights (2026-02-28)
- **Status**: Completed.
- **Problem**: `static.cloudflareinsights.com/beacon.min.js` was blocked by the CSP.
- **Fix**: Updated `app/middleware.php` to allow `https://static.cloudflareinsights.com` in `script-src` and `https://cloudflareinsights.com` in `connect-src`.

### Cloudflare Turnstile Integration (2026-02-28)
- **Status**: Completed.
- **Problem**: Need bot protection for Login and Register forms.
- **Fix**:
  - Added `CLOUDFLARE_TURNSTILE_SITE_KEY` and `CLOUDFLARE_TURNSTILE_SECRET_KEY` to `app/Config.php` and `.env.example`.
  - Updated CSP in `app/middleware.php` to allow `challenges.cloudflare.com`.
  - Included Turnstile JS in `storage/views/layout_main.php`.
  - Added Turnstile widget to Login and Register modals in `storage/views/partials_modals.php`.
  - Updated `public/assets/js/connection.js` and `public/assets/js/main.js` to pass the Turnstile token to the backend.
  - Implemented backend verification in `app/Controllers/AuthController.php`.

### Follow Action 500 Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: Follow/Unfollow actions were returning 500.
- **Root Cause**: Method name mismatch in `app/Config.php`. Routes were pointing to `followUser` and `unfollowUser` which did not exist on `UserController` (actual methods are `follow` and `unfollow`).
- **Fix**: Updated `app/Config.php` to use the correct method names `follow` and `unfollow`.

### CSRF Sync & Late-Binding Fix (2026-02-28)
- **Status**: Completed.
- **Problem**: 419 Invalid CSRF token persistent on POST requests.
- **Fix**:
  - Updated `public/assets/js/connection.js` to late-bind CSRF token from `window.__NMR_CONTEXT` just-in-time for each request.
  - Added automatic synchronization of CSRF token from `X-CSRF-Token` response headers to ensure client-side state is always fresh.
  - Ensured `$contextJson` is correctly passed to the layout in `WebController::render`.

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

