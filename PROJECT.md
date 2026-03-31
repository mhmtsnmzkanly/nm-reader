# NovelMangaReader Unified Documentation

This file is the single canonical project document for the repository. Legacy Markdown files now point back here.

## Contents
- Project Overview
- Architecture
- Backend
- Frontend Web
- Mobile App
- API Reference
- Database
- Operations
- Development Workflow
- Recent Changes

## Project Overview

NovelMangaReader is a PHP 8.3 + Slim 4 application for reading manga and novels, with:
- server-side rendered public pages
- a JSON API under `/api/v1`
- an admin surface
- a Framework7 mobile web app served from `/mobile`
- wallet and unlock flows for paid content/features

Core repository areas:
- `app/`: application code
- `public/`: web root and built assets
- `storage/`: logs, cache, sessions, views, language files
- `ui/web/`: web CSS toolchain
- `ui/mobile/`: Framework7 mobile source

## Architecture

### Central Configuration

`App\Config` in [app/Config.php](/home/duldul/Belgeler/nm-reader/app/Config.php) is the main coordination point for:
- app settings
- DB settings
- cache settings
- RBAC definitions
- route registration

Configuration values are loaded from `.env` via `$_ENV` with defaults.

### Request Model

The application follows a layered flow:
- Controller: HTTP input/output
- Service: business rules
- Repository: SQL access

Bootstrap flow:
1. [public/index.php](/home/duldul/Belgeler/nm-reader/public/index.php)
2. [app/app.php](/home/duldul/Belgeler/nm-reader/app/app.php)
3. [app/dependencies.php](/home/duldul/Belgeler/nm-reader/app/dependencies.php)
4. [app/middleware.php](/home/duldul/Belgeler/nm-reader/app/middleware.php)
5. route registration in [app/Config.php](/home/duldul/Belgeler/nm-reader/app/Config.php)

### Rendering Strategy

Public web pages use SSR PHP templates from `storage/views`.

The mobile app is a separate Framework7 frontend that builds into `public/mobile` and is served through `/mobile`.

## Backend

### Runtime

- PHP `^8.3`
- Slim `^4.13`
- PHP-DI `^7`
- Monolog `^3`
- Dotenv `^5.6`

### Key Conventions

- `declare(strict_types=1);` is required
- primary application services live under `app/Services`
- repositories live under `app/Repositories`
- DTOs live under `app/DTO`
- alphanumeric entity IDs are generated through `App\Services\EntityIdService`

### Security and RBAC

Roles are statically defined in [app/Config.php](/home/duldul/Belgeler/nm-reader/app/Config.php):
- `admin`
- `moderator`
- `editor`
- `user`

`ROOT_USER` from `.env` is treated as a superuser and bypasses standard RBAC checks.

Protected API routes use session auth plus CSRF enforcement. Optional bearer token identity is supported for public or native/mobile flows, but does not replace the session requirement for protected routes.

## Frontend Web

### Delivery Model

The main site is SSR-first and intentionally light on client rendering.

Important assets:
- [public/assets/js/main.js](/home/duldul/Belgeler/nm-reader/public/assets/js/main.js)
- [public/assets/js/app-bundle.js](/home/duldul/Belgeler/nm-reader/public/assets/js/app-bundle.js)
- [public/assets/js/admin-bundle.js](/home/duldul/Belgeler/nm-reader/public/assets/js/admin-bundle.js)
- [public/assets/css/main.css](/home/duldul/Belgeler/nm-reader/public/assets/css/main.css)

### Web CSS Toolchain

Source lives in [ui/web/assets/css/input.css](/home/duldul/Belgeler/nm-reader/ui/web/assets/css/input.css).

Build commands from [ui/web/package.json](/home/duldul/Belgeler/nm-reader/ui/web/package.json):
- `npm run build:css`
- `npm run watch:css`

Output target:
- [public/assets/css/main.css](/home/duldul/Belgeler/nm-reader/public/assets/css/main.css)

The web Tailwind toolchain uses Tailwind CSS v4 plus `@tailwindcss/cli`.

## Mobile App

### Stack

The mobile frontend uses:
- Framework7 v9 Core
- Vite
- vanilla JS/HTML/CSS

Source:
- `ui/mobile`

Build output:
- `public/mobile`

Entry path:
- `/mobile`

### Mobile Goals

The mobile app mirrors the core reading flow:
- browse content
- open detail pages
- load chapters
- read chapter
- log in/register
- unlock with wallet balance

### Mobile State Model

Minimal global store in [ui/mobile/src/js/store.js](/home/duldul/Belgeler/nm-reader/ui/mobile/src/js/store.js):
- `auth`
- `wallet`

### Mobile API Client

The Framework7 app talks to the backend via [ui/mobile/src/js/api.js](/home/duldul/Belgeler/nm-reader/ui/mobile/src/js/api.js).

Default base URL:
- `/api/v1`

Auth model:
- web: session cookie + CSRF
- fallback/native-style support: bearer `api_token`

### Mobile Routing

Primary routes live in [ui/mobile/src/js/routes.js](/home/duldul/Belgeler/nm-reader/ui/mobile/src/js/routes.js).

Implemented screens include:
- home
- type list
- content detail
- reader
- wallet
- wallet transactions
- shop pages
- profile/public profile
- library/history
- blogs/blog detail
- chat
- notifications
- preferences
- sessions

## API Reference

Base URL:
- `{APP_URL}/api/v1`

Standard response envelope:

```json
{
  "status": "success",
  "data": {},
  "meta": {
    "total": 0,
    "page": 1,
    "per_page": 20
  }
}
```

### Auth and Identity

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/refresh`
- `GET|POST /auth/logout`
- `GET /auth/sessions`
- `DELETE /auth/sessions/{sessionKey}`

Protected non-GET routes require `X-CSRF-Token` from login/refresh responses.

### Public Discovery

- `GET /home`
- `GET /content/type/{type}`
- `GET /content/{type}/{slug}`
- `GET /content/{type}/{slug}/chapters`
- `GET /content/{type}/{slug}/chapter/{chapterNumber}`
- `GET /chapter/{chapterNumber}` legacy read route
- `GET /latest-chapters`
- `GET /content/{type}/chapters`
- `GET /search`
- `GET /search/suggest`
- `GET /genres`
- `GET /tags`
- `GET /series_genres`
- `GET /series_tags`
- `GET /genre/{slug}`
- `GET /tag/{slug}`
- `GET /profile/{person}`
- `GET /i18n/{lang}`
- `POST /log/error`

### Social and Blog Reads

- `GET /blogs`
- `GET /blogs/{slug}`
- `GET /blogs/{slug}/comments`
- `GET /chapter/{chapterId}/comments`
- `GET /content/{type}/{slug}/comments`

Comment list endpoints support `page`, `per_page`, and optional keyset `cursor`.

### Protected User Actions

- `POST /content/{type}/{slug}/follow`
- `DELETE /content/{type}/{slug}/follow`
- `POST /content/{type}/{slug}/rate`
- `POST /content/{type}/{slug}/comment`
- `POST /chapter/{chapterId}/comment`
- `POST /comments/{commentId}/vote`
- `POST /blogs/{slug}/comments`
- `POST /blogs/{slug}/comments/{commentId}/vote`

### User Area

- `GET /user/profile`
- `POST /user/profile`
- `GET /user/history`
- `GET /user/preferences`
- `PUT /user/preferences`
- `GET /user/follows`
- `GET /user/follows/users`
- `POST /user/follows/{person}`
- `DELETE /user/follows/{person}`
- `GET /user/notifications`
- `POST /user/notifications/read`

### Wallet and Monetization

- `GET /user/wallet`
- `GET /user/wallet/transactions`
- `GET /user/unlocks/series`
- `GET /user/unlocks/chapters`
- `POST /content/{type}/{slug}/unlock`
- `POST /chapter/{chapterId}/unlock`
- `GET /shop/packages`
- `GET /shop/features`
- `GET /user/features`
- `GET /user/features/entitlements`
- `POST /user/features/ad-free/purchase`

### Admin API

Admin endpoints are grouped under:
- `/api/v1/admin`

Coverage includes:
- overview and metrics
- users and RBAC
- content/chapters/taxonomy
- uploads
- blogs/comments moderation
- queue/maintenance
- wallet/shop/pricing
- logs and audit views

For exact route list, use [app/Config.php](/home/duldul/Belgeler/nm-reader/app/Config.php) as the implementation reference.

## Database

Schema source of truth:
- [app/database/schema.sql](/home/duldul/Belgeler/nm-reader/app/database/schema.sql)

Migrations:
- `app/database/migrations/*.sql`

### Core Design

- MySQL/MariaDB with InnoDB
- `utf8mb4_unicode_ci`
- primary entities use alphanumeric IDs
- log/auxiliary tables typically use auto-increment IDs

### Core Tables

#### Users and Identity

- `users`
- `user_sessions`
- `user_refresh_tokens`
- `user_login_logs`
- `user_preferences`
- `user_follows`

#### Content

- `series`
- `series_metadata`
- `chapters`
- `series_genres`
- `series_tags`
- `series_genre_map`
- `series_tag_map`

#### Social

- `blogs`
- `social_comments`
- `ratings`
- `user_series_follows`
- `user_activity`

#### Reading and Progress

- `user_reading_progress`
- `user_chapters_reads`
- `user_notifications`

#### Monetization

- `user_wallets`
- `wallet_transactions`
- `shop_packages`
- `site_feature_products`
- `user_feature_entitlements`
- `series_access_products`
- `user_series_unlocks`
- `user_chapter_unlocks`

#### Analytics and System

- `analytics_events`
- `analytics_search_logs`
- `analytics_snapshots_*`
- `analytics_series_daily`
- `analytics_chapters_daily`
- `analytics_series_views`
- `analytics_chapters_views`
- `system_uploads`
- `system_audit_logs`
- `admin_actions`
- `system_jobs`

### Important Notes

- `chapters.data` stores either novel text content or pipe-separated image URLs
- chapter-level pricing is stored on `chapters.price_amount`
- series-level pricing is stored in `series_access_products`
- wallet activity is ledger-based via `wallet_transactions`

## Operations

Core console scripts under `app/Console`:
- `analytics_aggregate.php`
- `retention_cleanup.php`
- `system_backup.php`
- `generate_sitemap.php`
- `cache_warmer.php`
- `setup_chat.php`
- `seed_default_data.php`
- `create_admin.php`
- `queue_worker.php`

Common commands:

```bash
php app/Console/analytics_aggregate.php
php app/Console/retention_cleanup.php
php app/Console/system_backup.php
php app/Console/generate_sitemap.php
```

Suggested cron examples:

```bash
0 * * * * php /path/to/project/app/Console/analytics_aggregate.php
0 3 * * * php /path/to/project/app/Console/system_backup.php
0 0 * * * php /path/to/project/app/Console/retention_cleanup.php
```

## Development Workflow

### Backend Change Flow

1. update schema or migration if data shape changes
2. update repository/service/controller stack
3. register routes or config in [app/Config.php](/home/duldul/Belgeler/nm-reader/app/Config.php) if needed
4. update views or frontend integration
5. update this document when behavior/contracts change
6. verify
7. commit
8. push

### Verification Commands

Examples used in this repository:
- `php -l app/Config.php`
- `php -l app/app.php`
- `php -l app/dependencies.php`
- `php -l public/index.php`
- `npm run build` in `ui/mobile`
- `npm run build:css` in `ui/web`

### Git Expectations

- commit after each logical change
- use descriptive commit messages
- push verified work to `origin/main`
- do not overwrite unrelated user changes

## Recent Changes

### 2026-03-31
- Reorganized repository Markdown into this unified single-file document.
- Restored the `ui/web` Tailwind build pipeline by adding `@tailwindcss/cli`.
- Fixed the web CSS output path so builds target `public/assets/css/main.css`.
- Refreshed the generated web CSS bundle.

### 2026-03-23
- Hardened mobile global chat API handling for string blog IDs.
- Added safer invalid-target handling for comment endpoints.
- Restored long chapter pagination in mobile content detail.
- Added reader adjacent chapter navigation and chapter comment form.
- Improved structured text rendering for novel chapters.
- Fixed several mobile API contract mismatches around voting, avatars, and follows.
- Hardened Turnstile verification to fail closed.

### 2026-03-21
- Stabilized the mobile Framework7 app and completed core browse/read/auth/wallet flows.
- Added public profiles, profile editing, search suggestions, global chat, and richer content detail views.

### 2026-03-19
- Added mobile reader themes.
- Added chapter comments to the mobile reader.

### 2026-03-18
- Removed Tailwind from the mobile app.
- Rebuilt `ui/mobile` on Framework7 CLI and served it from `/mobile`.
