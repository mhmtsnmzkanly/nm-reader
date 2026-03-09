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
- **Exclusive Actions**: Only the ROOT_USER can trigger system backups, sitemap generation, cache warming, and manual analytics aggregation via the admin/API flows.

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
- **Push Requirement**: After local verification passes, push the commit to `origin/main` so the remote deployment source stays current.

### Documentation Mandate
- **Markdown Change Log**: Every requested code or behavior change must also be recorded in the relevant Markdown documentation file(s), at minimum in `PROJECT.md` and additionally in `API_REFERENCE.md` or `DATABASE.md` when interface or schema behavior changes.
- **Development Context**: This repository checkout is a development workspace. Production runs on a remote server, so local changes must be treated as staged development work until they are verified and pushed.

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
### Documentation Synchronization & Maintenance (2026-03-09)
- **Status**: Completed.
- **Maintenance**:
  - Hardened `.gitignore` by removing redundant exclusions for SSR PHP views. This ensures that essential layout, page, and partial templates (like `partials_modals.php`) are consistently tracked and deployed.
  - Synchronized `DATABASE.md` with the production schema by adding `user_login_logs` and descriptive entries for core supporting tables (preferences, follows, reading history, and analytics snapshots).
  - Synchronized `API_REFERENCE.md` by documenting public aliases for taxonomy endpoints (`/series_genres`, `/series_tags`).
  - Verified `melt`-prefixed routes in the backend controller to ensure alignment with the Flores-inspired UI rebuild from 2026-03-08.

### Melt View Template Hardening (2026-03-08)
- **Status**: Completed.
- **Problem**:
  - `pages_melt_*.php` templates had brittle Melt breadcrumb rewriting and weak empty-state handling, making some catalogue/detail screens feel broken when data was sparse.
  - Melt content chips were also losing taxonomy color/icon metadata compared with the main UI.
- **Fix**:
  - Hardened Melt breadcrumb URL conversion for content, chapter, and listing templates so locale-aware links consistently point to Melt routes without duplicate path segments.
  - Added explicit empty-state rendering for Melt content/listing views to avoid blank sections when no SSR items are available.
  - Restored taxonomy chip color/icon rendering in the Melt content template and made the chapter template tolerant of both `chapter` and `ssr_chapter` payload keys.

### Melt Frontend Preview Routes & Flores-Inspired UI Rebuild (2026-03-08)
- **Status**: Completed.
- **Problem**:
  - The existing public frontend was functionally usable but visually behind the desired reading/browsing UX target.
  - A separate Next.js design reference existed under `floresscans.com/`, but it needed to be reinterpreted for the current PHP + Melt stack without rewriting the backend.
- **Fix**:
  - Added `melt`-prefixed SSR web routes for home, search, listing, content, genre/tag listing, and chapter reader flows so the legacy pages remain intact.
  - Added a dedicated `layout_melt.php`, new `pages_melt_*.php` templates, and `public/assets/css/melt-nm.css` for the redesigned mobile-first presentation layer.
  - Added `public/assets/js/melt-front.js` to handle Melt-specific mobile navigation, search suggestions, chapter list rendering, comment flows, and unlock/follow/rate interactions against the existing API.
  - Updated `public/assets/js/app-bundle.js` so reader routing and global search also work correctly on `melt`-prefixed URLs.

### Wallet, Coin Unlocks & Schema Alignment (2026-03-08)
- **Status**: Completed.
- **Problem**:
  - The backend had no monetization model for paid chapter/series access.
  - Active code referenced multiple tables that were missing from `app/database/schema.sql`, creating schema drift and deployment risk.
- **Fix**:
  - Added wallet, ledger, package, pricing, and unlock backend flows for coin-based access.
  - Added authenticated user endpoints for wallet balance, transaction history, owned unlocks, and chapter/series unlock actions.
  - Added administrative endpoints for manual wallet credit/debit, package management, and per-series/per-chapter pricing.
  - Extended content/chapter API responses with access metadata so frontend changes can consume lock state without backend rework.
  - Re-aligned `schema.sql` with active backend dependencies by restoring missing queue, preferences, follows, read-history, and analytics tables.

### Package Credit & Ad-Free Feature Expansion (2026-03-08)
- **Status**: Completed.
- **Problem**:
  - Admin flows still lacked package-based credit assignment to a user wallet.
  - The monetization backend had no feature-level purchase model for using the site without ads.
- **Fix**:
  - Added package grant support so admins can apply a configured package directly to a user's wallet and keep fiat/cash metadata in the wallet ledger.
  - Added feature product and user entitlement storage for coin-purchased site features.
  - Added `ad_free` product management in the admin API and user endpoints for feature status, entitlement history, and ad-free purchase with coins.

### Admin Monetization Console UI (2026-03-08)
- **Status**: Completed.
- **Problem**: The monetization backend endpoints existed, but there was no dedicated admin panel page to operate wallet, package, pricing, and ad-free flows.
- **Fix**:
  - Added a new `Monetization` admin page and sidebar entry.
  - Added UI forms for wallet credit/debit, package grant, package management, ad-free product configuration, and series/chapter pricing updates.
  - Added wallet summary lookup and transaction listing for a target user inside the admin panel.
  - Replaced manual `user_id` entry with a selectable user list fed from an admin user-options endpoint.

### Documentation Sync For Route/Auth Drift (2026-03-08)
- **Status**: Completed.
- **Problem**:
  - Markdown references for API/admin behavior had drifted from the registered Slim routes.
  - `ROOT_USER` restrictions were under-documented for maintenance operations.
- **Fix**:
  - Updated `PROJECT.md` and `API_REFERENCE.md` to match current public, authenticated, and admin endpoint coverage.
  - Clarified that manual analytics aggregation is also restricted to `ROOT_USER`, alongside backup, sitemap, and cache warmup actions.

### Content Alternative Titles Visibility (2026-03-01)
- **Status**: Completed.
- **Problem**: Alternative titles were cluttering the hero section and weren't easily readable.
- **Fix**: Moved alternative titles from the hero section to a dedicated box within the main description card in `pages_content.php`, improving detail visibility and overall layout balance.

### Dropdown UX & Hover Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Dropdowns in the header had conflicting hover and click behaviors, causing flickering and inconsistent visibility.
- **Fix**:
  - Disabled hover-based triggering for dropdowns in `site.css` to prevent conflicts with JS-based click toggles.
  - Ensured dropdowns only open via the `.active` class, which is managed by the existing `onclick` handlers and the global click-outside listener in `main.js`.

### Content Page UI Overhaul (2026-03-01)
- **Status**: Completed.
- **Problem**: Layout flaws on the series detail page, including z-index issues in the hero section, tight spacing, and poor mobile responsiveness.
- **Fix**:
  - Overhauled `storage/views/pages_content.php` and `public/assets/css/site.css`.
  - Fixed hero backdrop z-index and overlay transparency.
  - Adjusted margins and padding for better alignment with the global header.
  - Improved mobile responsiveness with better stacking and spacing.
  - Refined metadata section with Bootstrap icons and a better grid layout.
  - Implemented a two-column layout for chapter lists and comments on desktop for better space utilization.

### Taxonomy Loading Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Genres and tags were missing from content modals unless the page was manually refreshed or certain elements were present.
- **Fix**:
  - Refactored `loadTaxonomy` in `public/assets/js/admin-content.js` to always fetch data from the API regardless of whether the listing table elements exist on the current page.
  - Added versioning to admin-specific script tags in `WebController.php` to ensure the latest fixes are loaded by the browser.

### Markdown Library Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Markdown rendering (descriptions, comments) was failing because the library was not being loaded in the public layout.
- **Fix**:
  - Added `marked.min.js` script inclusion to `storage/views/layout_main.php`.
  - Refactored `NMR.parseMarkdown` in `public/assets/js/utils.js` to ensure synchronous execution and improved error handling for the library.

### Browser Cache Busting (2026-03-01)
- **Status**: Completed.
- **Problem**: Persistent 404 errors on comment submission despite code fixes, likely due to browsers caching old JavaScript files.
- **Fix**: Added dynamic versioning (timestamp-based cache busting) to `content.js` and `connection.js` script inclusions to ensure clients always load the latest logic.

### Taxonomy Visibility Fix (2026-03-01)
- **Status**: Completed.
- **Problem**: Not all genres and tags were appearing in the content management modals.
- **Root Cause**: The frontend was using public API endpoints which had pagination limits (capped at 20-50 items).
- **Fix**:
  - Added dedicated administrative endpoints (`/admin/genres` and `/admin/tags`) that return all taxonomy items without pagination.
  - Updated `AdminConsoleService` and `AdminConsoleRepository` to support full listing of taxonomy data.
  - Updated `public/assets/js/admin-content.js` to use these new non-paginated endpoints for populating modals.

### Content Admin & UI Final Fixes (2026-03-01)
- **Status**: Completed.
- **Problem**:
  - Description field still appearing empty in admin content edit.
  - Tag and genre chips on content pages were still visually broken (no background color).
- **Fix**:
  - **Admin Description**: 
    - Removed `description` from `OutputSanitizer::sanitizeRows` in `AdminConsoleService::listContents`. Sanitizing for the admin list was stripping content needed for editing.
    - Removed redundant Edit/Create Content modals from `pages_admin_dashboard.php` to prevent ID conflicts with the dedicated `pages_admin_content.php`.
  - **Tag UI**:
    - Added CSS background utility classes (`.bg-primary`, `.bg-success`, etc.) to `public/assets/css/site.css`.
    - Updated `storage/views/pages_content.php` and `storage/views/layout_main.php` to correctly wrap theme color names (like `primary`, `success`) in `var()` for the `--chip-color` variable.

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
### Google Analytics 4 Integration (2026-02-28)
- **Status**: Completed.
- **Components**:
  - Integrated `gtag.js` in `storage/views/layout_main.php`.
  - Configured `GOOGLE_ANALYTICS_ID` in `app/Config.php` (via `.env`).
  - Updated `app/middleware.php` to include necessary Content Security Policy (CSP) directives.

### Admin Dashboard & Analytics Overhaul (2026-03-07)
- **Status**: Completed.
- **Problem**: Dashboard metrics (Funnel, Retention, Top Contents) were hardcoded to zero, and analytics required manual CLI execution.
- **Fix**:
  - Implemented real database queries for all dashboard KPIs in `AdminConsoleRepository`.
  - Introduced a **Lazy Cron** system in `AdminConsoleService`: The dashboard now automatically triggers analytics aggregation every 12 hours if data is stale.
  - Added "Retention & Search" metrics to track user loyalty and search quality (Zero Results rate).
  - Fixed a critical "Undefined property" error in the aggregation service injection.

### Uploads Management & Preview (2026-03-07)
- **Status**: Completed.
- **Feature**: Added a dedicated "Uploads" tab in the Admin Panel to track all system-wide image assets.
- **Components**:
  - Added `file_path` column to `system_uploads` table for reliable image rendering.
  - Created `pages_admin_uploads.php` and `admin-uploads.js` for a paginated, searchable upload gallery.
  - Implemented image preview modals and deletion controls.
  - Updated `UploadService` to persistently log the relative file path of every upload.

### Chapter Ordering & Metadata Fixes (2026-03-07)
- **Status**: Completed.
- **Problem**: 
  - Chapter images were uploading in random order.
  - Uploader information was missing from the chapter list.
  - 500 errors during chapter creation due to missing `created_by` column.
- **Fix**:
  - Implemented **Natural Sorting** (1.png, 2.png, 10.png) on both Frontend (`admin-content.js`) and Backend (`AdminController.php`) to guarantee correct reading order.
  - Added `created_by` column to the `chapters` table and updated the admin list to show the uploader's username.
  - Fixed SQL syntax errors in the Admin Creation CLI tool.

### Reader Experience & "Long Strip" Support (2026-03-07)
- **Status**: Completed.
- **Fix**: Implemented settings modal for layout (Vertical/Single/Double) and image fit options.

### User Retention: Reading Progress (2026-03-07)
- **Status**: Completed.
- **Feature**: Implemented "Continue Reading" logic. The system now tracks the last read chapter per series for each user.
- **UI**: Added a dynamic button on series pages that replaces "Start Reading" with "Continue Reading (Ch. X)" if progress exists.
- **Backend**: New `user_reading_progress` table and logic integrated into `ChapterService` and `SeriesService`.

### Security & Data Integrity: Soft Delete (2026-03-07)
- **Status**: Completed.
- **Feature**: Implemented **Soft Delete** for `series` and `chapters`. Deleted items are now marked with a `deleted_at` timestamp instead of being physically removed.
- **Refactor**: Updated `SeriesRepository` and `ChapterRepository` to filter out deleted items in all public and admin queries using `deleted_at IS NULL`.
- **Database**: Added indices on `deleted_at` columns to maintain high query performance.

### Monetization Schema Alignment (2026-03-08)
- **Status**: Completed.
- **Problem**: `grant-package` operations were failing with `SQLSTATE[01000]: Warning: 1265 Data truncated for column 'type'` on the remote server. Also, manual schema updates for `admin_actions` failed due to missing ENUM values.
- **Root Cause**: 
  - The `wallet_transactions` table on the remote database was using an outdated ENUM definition for the `type` column.
  - The `admin_actions` table was missing several values used in the codebase (`security` target type, and multiple action types).
- **Fix**:
  - Updated `app/database/schema.sql` with the comprehensive ENUM lists.
  - Recommended SQL for existing installations to align the schema:
    ```sql
    ALTER TABLE wallet_transactions MODIFY COLUMN `type` enum('manual_credit','manual_debit','package_credit','chapter_unlock','series_unlock','feature_unlock','refund','adjustment') NOT NULL;
    
    -- Align admin_actions with all values used in the codebase:
    ALTER TABLE admin_actions MODIFY COLUMN `target_type` enum('comment','blog','content','user','system','role','series','chapter','security') NOT NULL;
    
    ALTER TABLE admin_actions MODIFY COLUMN `action` enum('hide','delete','ban','warn','approve','trigger','grant_permission','revoke_permission','role_change','unban','update','create','update_taxonomy','revoke_session','wallet_credit','wallet_debit','wallet_package_credit','refund','series_unlock','chapter_unlock','feature_unlock','package_create','package_update','pricing_update','feature_update','auth_fail','permission_denied','create_genre','create_tag','env_update') NOT NULL;
    ```

### Queue & Notification Fix (2026-03-08)
- **Status**: Completed.
- **Problem**: `notify_new_chapter` jobs were failing with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'data' in 'INSERT INTO'`.
- **Root Cause**: The `user_notifications` table in the remote database was missing the `data` column, or the column name was conflicting with reserved keywords in some environments.
- **Fix**:
  - Added backticks to the `data` column in all SQL queries involving `user_notifications` and `chapters` tables across `QueueService`, `UserRepository`, `CommentVoteRepository`, `ChapterRepository`, and `AdminService`.
  - Recommended SQL for existing installations to ensure the `data` column exists:
    ```sql
    ALTER TABLE user_notifications ADD COLUMN `data` longtext DEFAULT NULL AFTER body;
    ```

### API Documentation Refresh (2026-03-08)
- **Status**: Completed.
- **Problem**: Several endpoints (`POST /log/error`, `GET /admin/comments`, `DELETE /admin/comments/{id}`) and analytics tables were missing or incomplete in `API_REFERENCE.md` and `DATABASE.md`.
- **Fix**: Synchronized Markdown documentation with the current `app/Config.php` routing and `app/database/schema.sql` definitions to ensure absolute authority for AI agents and developers.

### Asset Optimization: Unified JS Bundling (2026-03-07)
- **Status**: Completed.
- **Feature**: Consolidated all fragmented JavaScript files into two primary bundles: `app-bundle.js` (Frontend) and `admin-bundle.js` (Administration).
- **Architecture**: Implemented a modular namespace-based structure with path-based routing to prevent cross-page execution conflicts.
- **Refactor**:
  - Removed redundant script tags from all view files and `WebController` render calls.
  - Standardized API communication and CSRF handling within the bundles.
  - Improved reader stability and settings persistence.
