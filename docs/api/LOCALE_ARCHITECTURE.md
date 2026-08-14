# NM-Reader — Backend Locale Retirement & User Language Preference Architecture

## Overview

NM-Reader has transitioned from legacy **URL-based locale routing** (e.g. `/tr/browse`, `/en/blogs`) to a clean, decoupled **Frontend-Managed User Language Preference** architecture.

```
OLD: URL Locale Routing          NEW: Clean Canonical URLs + User Preference
-----------------------          -------------------------------------------
https://example.com/tr/browse    → https://example.com/browse
https://example.com/en/blogs     → https://example.com/blogs
GET / (302 -> /tr)               → GET / (200 OK — React App Shell)
```

---

## 1. Core Principles

1. **URL Locale != User Language Preference**: The URL represents the canonical resource path and contains no language prefix. Language preference is a user-level configuration persisted in the user profile or browser storage.
2. **Frozen API V1**: No API v1 endpoint signatures, request/response envelopes, or DTO structures were changed.
3. **Clean SEO Canonical URLs**: All canonical tags, OpenGraph tags, and Structured Data (JSON-LD) graphs reference clean, unprefixed paths (e.g., `/content/manga/solo-leveling`).
4. **Seamless 301 Migration for Legacy URLs**: Any incoming requests to legacy `/tr/*` or `/en/*` paths are permanently redirected (HTTP 301) to their clean canonical target (except invalid paths like `/tr/api/*` or `/tr/admin/*` which return 404).

---

## 2. URL Routing Architecture

### Public Routes (Clean Canonicals)

| Route | Handler | Purpose |
|:---|:---|:---|
| `/` | `WebController::home` | Homepage React App Shell |
| `/browse` | `WebController::listing` | Catalog & Content Directory |
| `/search` | `WebController::search` | Search & Filtering |
| `/genres` / `/genre/{slug}` | `WebController::genre` | Taxonomy / Genres |
| `/tags` / `/tag/{slug}` | `WebController::tag` | Taxonomy / Tags |
| `/{type}/{slug}` | `WebController::content` | Series Details & Metadata |
| `/{type}/{slug}/chapter/{number}` | `WebController::chapter` | Reader Experience |
| `/blogs` / `/blogs/{slug}` | `WebController::blog` | Community Blogs |
| `/profile` / `/me` / `/u/{username}` | `WebController::profile` | User Profiles |
| `/wallet` / `/shop` | `WebController::home` | Wallet & Monetization |
| `/preferences` / `/notifications` | `WebController::home` | User Preferences & Feed |

### Legacy URL Migration (301 Permanent Redirects)

```php
// In App\Config::registerWebRoutes
$app->get("/{lang:tr|en}", function ($req, $res) {
    return $res->withHeader("Location", "/")->withStatus(301);
});

$app->get("/{lang:tr|en}/{path:.*}", function ($req, $res, array $args) {
    $path = (string) ($args["path"] ?? "");
    if (str_starts_with($path, "api/") || str_starts_with($path, "admin") || str_starts_with($path, "media/")) {
        return $res->withStatus(404);
    }
    return $res->withHeader("Location", "/" . ltrim($path, "/"))->withStatus(301);
});
```

---

## 3. Language Preference Lifecycle

### A. Authenticated User Flow
1. **Bootstrap / Login**: User logs in. React app requests `GET /api/v1/user/preferences` (or `GET /api/v1/user/profile`).
2. **Hydration**: React `PreferencesContext` reads `preferences.language.locale` (or `preferences.lang`) and sets the active translation dictionary.
3. **Language Change**: User switches language in the UI (e.g. from Turkish to English).
4. **Persistence**: `PreferencesContext` calls `userService.updatePreferences({ language: { locale: 'en' }, lang: 'en' })` which executes `PUT /api/v1/user/preferences`.
5. **Database Storage**: The backend stores the preference in the database and echoes it in subsequent sessions.

### B. Guest User Flow
1. **Initial Load**: If no authenticated session exists, `PreferencesContext` checks `localStorage.getItem('nm_user_preferences')`.
2. **Fallback**: If not set in `localStorage`, it negotiates browser language or defaults to `tr`.
3. **Language Change**: User switches language. The new preference is saved to `localStorage` immediately without triggering backend mutation errors.

---

## 4. API & Backend Localization

- **API Error Localization**: `App\Middleware\I18nMiddleware` resolves the user's preferred language via `I18nService` (from Auth Profile, `X-Lang` header, cookie, or `Accept-Language`) and localizes error message strings using `storage/lang/tr.php` and `storage/lang/en.php`.
- **API Dictionaries**: `GET /api/v1/i18n/{lang}` remains available as part of the frozen API v1 contract.
- **Admin Panel**: Admin routes (`/admin/*`) remain isolated under `App\Middleware\PermissionMiddleware` with their own independent rendering dictionary.
