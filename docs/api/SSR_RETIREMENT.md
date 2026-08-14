# NM-Reader Public SSR Retirement Specification

**Version:** 1.0.0  
**Status:** CANONICAL SPECIFICATION  
**Scope:** Architectural declaration and verification of the full retirement of legacy public Server-Side Rendered (SSR) views in favor of the React Client-Side Rendered (CSR) App Shell with server-side SEO injection.

---

## 1. Legacy SSR Architecture (Historical Overview)

Prior to the React migration, NM-Reader rendered public HTML pages entirely on the server using PHP templates located in `storage/views/`:

```
User Request ──> PHP Router ──> WebController ──> storage/views/{template}.php ──> layout_main.php ──> HTML
```

### Limitations of the Legacy Public SSR Model
- Tightly coupled presentation logic with server PHP controllers.
- Required full-page reloads for client transitions, leading to slower perceived latency.
- Required dual maintenance of template logic and REST API endpoints.

---

## 2. Modern CSR / App Shell Architecture

Under the new architecture, all public requests are served a single HTML5 React App Shell ([`public/app.html`](file:///home/duldul/Belgeler/nm-reader/public/app.html)) with rich, server-injected SEO metadata:

```
Browser / Crawler
        │
        ▼
PHP Entrypoint (`public/index.php`)
        │
        ▼
`WebController` (Prepares SEO presentation data)
        │
        ▼
`SeoService` (Injects title, description, canonical, OG, Twitter, JSON-LD into `public/app.html`)
        │
        ▼
Crawler-Ready HTML Document Response
        │
        ▼
React 19 SPA Hydration / CSR Router (`ui/src/App.tsx`)
        │
        ▼
Centralized `HttpClient` (`ui/src/api/client.ts`) ──> REST API (`/api/v1/*`)
```

---

## 3. Public SSR Retirement Declaration

All public-facing PHP views and template rendering logic have been **permanently retired**.

### Retirement Scope:
- **Zero Public PHP Templates:** Public routes never evaluate or output `.php` view templates.
- **Unified Shell:** All indexable and interactive public routes (`/`, `/browse`, `/search`, `/{type}/{slug}`, `/{type}/{slug}/chapter/{number}`, `/blogs`, `/blog/{slug}`, `/profile`, `/library`, etc.) resolve to `app.html`.
- **Pure Client State:** Navigation, filtering, modal state, wallet actions, and user interactions are handled 100% within the React frontend consuming `/api/v1/*`.

---

## 4. Admin SSR Architecture (Preserved Exception)

The management console ([`/admin/*`](file:///home/duldul/Belgeler/nm-reader/app/Controllers/AdminPanelController.php)) **remains on Server-Side Rendering (SSR)**.

### Rationale:
- Admin panel security relies directly on server session RBAC middleware and CSRF enforcement.
- Admin UI does not require public indexing or search engine crawling.
- Preserves the battle-tested AdminLTE template and operations console without introducing unnecessary frontend complexity.

---

## 5. App Shell Routing Matrix

| Route Pattern | Handler | Output Mechanism | Client Routing |
|:---|:---|:---|:---|
| `/` | `WebController::home` | `app.html` + WebSite JSON-LD | `HomePage` |
| `/{type:manga\|novel...}` | `WebController::listing` | `app.html` + Breadcrumb | `BrowsePage` |
| `/{type}/{slug}` | `WebController::content` | `app.html` + CreativeWorkSeries | `ContentDetailPage` |
| `/{type}/{slug}/chapter/{num}` | `WebController::chapter` | `app.html` + Chapter SEO | `ReaderPage` |
| `/blogs` | `WebController::blog` | `app.html` + Blog Directory | `BlogListPage` |
| `/blogs/{slug}` | `WebController::blog` | `app.html` + BlogPosting JSON-LD | `BlogDetailPage` |
| `/search` | `WebController::search` | `app.html` + noindex, follow | `SearchPage` |
| `/profile` | `WebController::profile` | `app.html` + noindex, nofollow | `ProfilePage` |
| `/profile/{person}` | `WebController::profile` | `app.html` + Public Creator | `PublicProfilePage` |
| `/admin/*` | `AdminPanelController` | `storage/views/admin_*.php` + `layout_adminlte.php` | N/A (Server SSR) |
| `/api/v1/*` | `ApiController` | JSON API Envelope | N/A (Data Transport) |
| `/media/*` | `MediaController` | Binary Media Stream | N/A (Media Engine) |

---

## 6. SEO Injection Boundary

- **Server Responsibility:** Title, meta description, robots policy, canonical URL, OpenGraph tags, Twitter Card tags, and Schema.org JSON-LD structured data are generated server-side in `SeoService`.
- **Client Responsibility:** React components do not manage `<head>` tags; they focus purely on UI rendering and state management.

---

## 7. API Boundary Isolation

The REST API layer (`/api/v1/*`) remains strictly decoupled from presentation:
- Endpoints return standard unified JSON envelopes (`{ status, data, meta, error }`).
- Zero HTML fragments or SSR view logic is returned by the API.
- All 94 canonical endpoints adhere strictly to [`API_V1_FREEZE.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_V1_FREEZE.md).

---

## 8. Media Boundary Isolation

Media endpoints (`/media/public/*` and `/media/chapter/*`) are fully independent:
- Public covers and avatars are resolved via `/media/public/{filename}`.
- Chapter images require ephemeral HMAC signed tokens (`/media/chapter/t_*`).
- Protected chapter tokens **NEVER** leak into the public HTML shell, OpenGraph tags, Twitter cards, or JSON-LD metadata.

---

## 9. Inventory of Removed vs Preserved Views

### Retired & Removed Public Views:
- ❌ `storage/views/home.php`
- ❌ `storage/views/series_list.php`
- ❌ `storage/views/content.php`
- ❌ `storage/views/chapter.php`
- ❌ `storage/views/blog.php`
- ❌ `storage/views/profile.php`
- ❌ `storage/views/login.php`
- ❌ `storage/views/chat.php`
- ❌ `storage/views/layout_main.php`
- ❌ `storage/views/partials_modals.php`

### Preserved Admin Views:
- ✅ `storage/views/admin_dashboard.php`
- ✅ `storage/views/admin_content.php`
- ✅ `storage/views/admin_blogs.php`
- ✅ `storage/views/admin_comments.php`
- ✅ `storage/views/admin_users.php`
- ✅ `storage/views/admin_ops.php`
- ✅ `storage/views/admin_monetization.php`
- ✅ `storage/views/admin_config.php`
- ✅ `storage/views/admin_uploads.php`
- ✅ `storage/views/admin_logs.php`
- ✅ `storage/views/admin_tutorial.php`
- ✅ `storage/views/layout_adminlte.php`
- ✅ `storage/views/install.php` (Installer fallback view)
- ✅ `storage/views/error.php` (Error fallback view)

---

## 10. Automated Verification Suite

Automated verification is executed via:

```bash
# Verify SSR retirement & view structure (44 checks)
composer test:ssr

# Verify SEO injection & JSON-LD (32 checks)
composer test:seo

# Verify REST API regression (124 tests)
composer test:api

# Verify React API client & E2E flows (54 tests)
npm run test:client && npm run test:e2e
```
