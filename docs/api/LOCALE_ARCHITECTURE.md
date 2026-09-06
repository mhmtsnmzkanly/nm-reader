# NM-Reader — Backend Locale Retirement & User Language Preference Architecture

## Overview

NM-Reader has transitioned from legacy **URL-based locale routing** (e.g. `/tr/browse`, `/en/blogs`) to a clean, decoupled **Frontend-Managed User Language Preference** architecture.

```
OLD: URL Locale Routing          NEW: Clean Canonical URLs + User Preference
-----------------------          -------------------------------------------
https://example.com/tr/browse    → https://example.com/browse
https://example.com/en/blogs     → https://example.com/blogs
GET / (302 → /tr)               → GET / (200 OK — React App Shell)
```

> **KEY PRINCIPLE**: `URL locale ≠ User language preference`
> The URL is a canonical resource identifier. Language is a user configuration.

---

## 1. What Was Removed

| Component | What Was Removed |
|:---|:---|
| `I18nService::resolveLocale()` | URL path segment (`/tr`, `/en`) detection as priority #1 |
| `WebController::render()` | `$urlLang` route-argument extraction |
| `WebController::render()` | URL-to-DB language sync block (writing URL lang to user preferences) |
| `WebController::render()` | Locale-prefixed `$url` helper (`/tr/browse` → `/browse`) |
| Legacy admin SSR renderer | `WebController::renderAdmin()` and `/admin/*` views |
| `WebController::sitemapXml()` | Unused `$supportedLangs` variable |
| `App.tsx` | All `/:lang(tr|en)/*` prefixed React Router routes |

## 2. What Was Kept / Unchanged

| Component | Status | Reason |
|:---|:---|:---|
| `Config::registerWebRoutes` `/{lang:tr\|en}` 301 redirect | **KEPT** | SEO continuity for indexed legacy URLs |
| `Config::registerWebRoutes` `/{lang:tr\|en}/{path:.*}` 301 redirect | **KEPT** | Legacy URL migration |
| `I18nMiddleware` | **KEPT** | API error message localization |
| `I18nService::resolveLocale()` (remaining fallback chain) | **KEPT** | User pref → X-Lang header → cookie → Accept-Language → default |
| `storage/lang/tr.php` + `storage/lang/en.php` | **KEPT** | Used by I18nMiddleware and `GET /api/v1/i18n/{lang}` |
| `GET /api/v1/i18n/{lang}` | **FROZEN** | Part of API V1 contract |
| `PUT /api/v1/user/preferences` | **FROZEN** | Used by frontend to persist language choice |
| All other API V1 contracts | **FROZEN** | See `API_V1_FREEZE.md` |

---

## 3. URL Routing Architecture

### Public Routes (Clean Canonicals, No Locale Prefix)

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

### Protected Routes (No Locale Routing)

| Prefix | Locale Redirect | Notes |
|:---|:---|:---|
| `/api/v1/*` | **NONE** | Frozen API contract |
| `/media/*` | **NONE** | Frozen media contract |
| `/panel` / `/panel/*` | **NONE** | Unified admin panel |

### Legacy URL Migration (301 Permanent Redirects)

```
GET /tr          → 301 → /
GET /en          → 301 → /
GET /tr/browse   → 301 → /browse
GET /en/blogs/x  → 301 → /blogs/x
GET /tr/manga/x  → 301 → /manga/x

GET /tr/api/v1/* → 404  (no migration for invalid paths)
GET /tr/admin/*  → 404  (no migration for invalid paths)
GET /tr/panel/*  → 404  (no migration for protected paths)
GET /tr/media/*  → 404  (no migration for invalid paths)
```

---

## 4. Language Preference Lifecycle

### A. Authenticated User Flow

```
Login
  ↓
GET /api/v1/user/preferences (or bootstrap)
  ↓
PreferencesContext reads preferences.lang or preferences.language.locale
  ↓
React i18n dictionary applied (tr | en)
  ↓
User switches language in UI
  ↓
PreferencesContext: PUT /api/v1/user/preferences { lang: 'en', language: { locale: 'en' } }
  ↓
Backend persists to DB — canonical user language stored
  ↓
Next session: DB preference wins
```

### B. Guest User Flow

```
Request (no session)
  ↓
PreferencesContext checks localStorage ('nm_user_preferences')
  ↓
If found: merge & apply
If not found: default to site default ('tr')
  ↓
User switches language
  ↓
Saved to localStorage only (no backend call for guests)
  ↓
Login
  ↓
Backend user preference becomes canonical
```

### C. I18nService Locale Resolution (Backend — API Errors Only)

Priority order (URL segment detection **removed**):

1. Authenticated user DB preference (`preferences.lang`)
2. `X-Lang` request header
3. `nm_reader_lang` cookie
4. `Accept-Language` header negotiation
5. Site default (configured via `DEFAULT_LANGUAGE` env)

---

## 5. API & Backend Localization

- **API Error Localization**: `App\Middleware\I18nMiddleware` resolves the user's preferred language via `I18nService` and localizes error message strings using `storage/lang/tr.php` and `storage/lang/en.php`.
- **API Dictionaries**: `GET /api/v1/i18n/{lang}` remains available as part of the frozen API v1 contract. Used by the React frontend to load server-side translation strings.
- **Admin Panel**: The browser UI is served only from `/panel` and `/panel/*`. It consumes the permission-protected `/api/v1/admin/*` endpoints; the removed `/admin/*` browser routes are not aliases or redirects.

---

## 6. SEO Canonical URLs

All SEO tags produce locale-prefix-free canonical URLs:

| Tag | Example Value |
|:---|:---|
| `<link rel="canonical">` | `https://example.com/manga/solo-leveling` |
| `<meta property="og:url">` | `https://example.com/manga/solo-leveling` |
| `JSON-LD url` | `https://example.com/manga/solo-leveling` |
| `JSON-LD BreadcrumbList item` | `https://example.com/manga/solo-leveling` |
| `sitemap.xml <loc>` | `https://example.com/manga/solo-leveling` |

**Protected media tokens** (`t_*`, `/media/chapter/*`) are sanitized by `SeoService::sanitizeMediaUrl()` and **never** appear in SEO output.

---

## 7. Hreflang Decision

**HREFLANG NOT GENERATED**.

The platform uses a single canonical URL per resource (`/manga/solo-leveling`). There are no separate indexable language versions of pages (content is the same regardless of UI language). Generating `hreflang` tags for `tr`/`en` variants would produce misleading signals to search engines and has been omitted intentionally.

If separate-language content pages are introduced in the future, this decision should be revisited.

---

## 8. storage/lang Files

| File | Status | Used By |
|:---|:---|:---|
| `storage/lang/tr.php` | **RETAINED** | I18nMiddleware (API error localization), `GET /api/v1/i18n/tr` |
| `storage/lang/en.php` | **RETAINED** | I18nMiddleware (API error localization), `GET /api/v1/i18n/en` |

These files are **not** legacy SSR relics. They are active and serve the API error message localization pipeline and the frozen i18n API endpoint.

---

## 9. Test Coverage

| Suite | Result |
|:---|:---|
| Locale Regression (`composer test:locale`) | 25/25 PASS |
| SEO (`composer test:seo`) | 32/32 PASS |
| SSR Retirement (`composer test:ssr`) | 44/44 PASS |
| Storage Check (`composer check:storage`) | HEALTHY |
| PHP Lint | No errors |
| TypeScript (`npm run typecheck`) | No errors |
| git diff --check | OK |
