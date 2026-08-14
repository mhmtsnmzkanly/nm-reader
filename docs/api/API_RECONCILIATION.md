# SSR ↔ API Reconciliation Report

**Repository:** `mhmtsnmzkanly/nm-reader`  
**Date:** 2026-08-14  
**Scope:** Canonical business data and business logic reconciliation between Legacy SSR and API v1.

---

## 1. Executive Summary & Objective

The objective of this reconciliation is **not** to replicate SSR presentation artifacts (e.g. HTML formatting, localized breadcrumb arrays, hardcoded Turkish date strings, or SEO meta injection) in the API.

The objective is to establish:
> **"What is the single canonical source of truth for business data, access rules, mutation side effects, and domain state in the backend?"**

---

## 2. Core Reconciliation Findings & Canonical Decisions

### 2.1 Reader Premium Access Control (P0 - CRITICAL)
- **SSR Behavior:** Calls `SeriesService::chapterDetailByTypeSlugAndNumber()` which loads text/images directly from `ChapterRepository` without calling `WalletService::chapterAccess()`. Locked/paid chapters render in full in SSR HTML.
- **API Behavior:** Calls `ChapterService::getByTypeSlugAndNumber()` which calls `WalletService::chapterAccess()`. When access is not granted, `body` is `null` and `pages` is `[]`.
- **Canonical Decision:** **API is the single canonical business rule.**
  - Access calculation must occur for every chapter request.
  - Locked chapters MUST NEVER return text bodies or page image lists.
  - Access metadata (`granted`, `reason`, `price_coin`, `balance_coin`, `can_unlock`) must be returned in the chapter payload.
  - Unify the service path: All chapter detail resolution must flow through `ChapterService`.

---

### 2.2 Reading History Recording (P1 - HIGH)
- **SSR Behavior:** Visiting a chapter in SSR does not call `markRead` in the database.
- **API Behavior:** `ContentController::chapterDetail()` verifies `if ($userId && $chapter['access']['granted'] === true)` and calls `ChapterService::markRead($userId, $chapterId)`, recording the reading progress into `reading_history`.
- **Canonical Decision:** **API is the canonical business rule.**
  - Reading history is a side effect triggered when an authenticated user successfully accesses an unlocked chapter.

---

### 2.3 Search Filtering Support (P1 - HIGH)
- **SSR Behavior:** `WebController::search()` parses query parameters: `q`, `genres` (comma-separated), `tags` (comma-separated), `status`, `sort`, and forwards them to `SeriesService::search($q, 1, 50, $filters)`.
- **API Behavior:** `ContentController::search()` only reads `q`, ignoring `genres`, `tags`, `status`, `sort`.
- **Canonical Decision:** **SSR had the complete business filter contract; API must adopt it.**
  - `GET /api/v1/search` must accept:
    - `q` (`string`, query text)
    - `genres` (`string`, comma-separated genre slugs or IDs)
    - `tags` (`string`, comma-separated tag slugs or IDs)
    - `status` (`string`: `ongoing|completed|hiatus|cancelled`)
    - `sort` (`string`: `popular|latest|rating|title|views`)
    - `page` (`integer`, min: 1, default: 1)
    - `per_page` (`integer`, min: 1, max: 50, default: 20)

---

### 2.4 Chapter List Authentication & Session Identity (P1 - HIGH)
- **SSR Behavior:** `WebController::content()` passes `$_SESSION['user_id']` into chapter resolution to mark which chapters are unlocked for the viewer.
- **API Behavior:** `GET /api/v1/content/{type}/{slug}/chapters` only read `request->getAttribute('user_id')`. Because this route is public, session-authenticated users had `null` attribute, resulting in `is_unlocked: false` for all paid chapters.
- **Canonical Decision:** **Public discovery endpoints must read session user ID as fallback.**
  - `$userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);`
  - Allows authenticated browser sessions to receive personalized unlock states on public listings.

---

### 2.5 Reader Series Identity (P1 - HIGH)
- **SSR Behavior:** Injected `series_title`, `series_slug`, `series_type` into the chapter context for navigation and breadcrumbs.
- **API Behavior:** `ChapterService::getByTypeSlugAndNumber` omitted series identity fields, returning only `content_id`.
- **Canonical Decision:** **Reader API payload must include series identity metadata.**
  - Fields: `series_title` (`string`), `series_slug` (`string`), `series_type` (`string`).
  - Enables standalone client-side reader to render breadcrumbs, header titles, and return links without making a redundant `/content/{type}/{slug}` request.

---

### 2.6 Blog Detail Viewer Vote (P2 - MEDIUM)
- **SSR Behavior:** `WebController::blog()` queried `findApprovedBySlug($slug, null)`, always hardcoding `my_vote = 0`.
- **API Behavior:** `BlogController::show()` passes the viewer's user ID to `BlogService::getApprovedBySlug($slug, $userId)`, returning `my_vote: -1|0|1`.
- **Canonical Decision:** **API is the canonical business rule.**
  - Blog detail must be viewer-aware when session exists.

---

### 2.7 Pagination Standardization (P2 - MEDIUM)
- **SSR Behavior:** Uses hardcoded slice lengths (Home: 20, List: 50, Content Chapters: 200, Profile History: 50, Follows: 100).
- **API Behavior:** Configurable `page` and `per_page` parameters (default 20, max 50).
- **Canonical Decision:**
  - Standard Offset Pagination envelope:
    ```json
    "meta": {
      "pagination": {
        "page": 1,
        "per_page": 20,
        "total": 120,
        "total_pages": 6
      }
    }
    ```
  - Cursor Pagination envelope (Comments, Notifications):
    ```json
    "meta": {
      "pagination": {
        "page": 1,
        "per_page": 20,
        "next_cursor": "eyJjcmVhdGVkX2F0Ij...",
        "has_more": true
      }
    }
    ```

---

### 2.8 Wallet Balance & Session Caching (P2 - MEDIUM)
- **SSR Behavior:** Header template read `$_SESSION['user_wallet']['balance'] ?? '0'`. This session value could become stale after coin transactions.
- **API Behavior:** `GET /api/v1/user/wallet` queries `WalletRepository` directly, returning real-time `balance_coin`, `total_purchased_coin`, `total_spent_coin`, and active entitlements.
- **Canonical Decision:** **API database query is the canonical data.**
  - The React frontend must fetch wallet balance from `GET /api/v1/user/wallet` and update its global state upon successful coin mutations.

---

### 2.9 Authentication & CSRF Contract (P2 - MEDIUM)
- **SSR Behavior:** Session cookie + CSRF token injected into meta tags and `window.__NMR_CONTEXT`.
- **API Behavior:** Session cookie (`nm_reader_session`), `nm_remember` cookie for refresh, `X-CSRF-Token` header for state-changing requests (`POST`, `PUT`, `DELETE`).
- **Canonical Decision:**
  - Session-based authentication is the canonical auth mechanism for the web/React client.
  - All state-changing API endpoints enforce `X-CSRF-Token`.
  - API responses include `X-CSRF-Token` response header on session initialization/refresh.

---

### 2.10 Error Response Structure (P2 - MEDIUM)
- **SSR Behavior:** HTML error pages (`error.php`).
- **API Behavior:** Standard JSON error envelope:
  ```json
  {
    "status": "error",
    "data": null,
    "meta": [],
    "error": {
      "code": 404,
      "key": "NOT_FOUND",
      "message": "Resource not found",
      "params": []
    }
  }
  ```
- **Canonical Decision:** Standardize all API error outputs to this structure, ensuring field-level validation errors are attached under `error.fields` when validation fails.

---

## 3. Non-Business SSR Elements (Excluded from API)

The following SSR features are purely presentation/SEO layer concerns and **MUST NOT** be embedded in domain API payloads:
1. **HTML Strings & Formatting:** `nl2br`, `<span class="badge">`, etc.
2. **Pre-formatted Turkish Strings:** e.g. `"12 Ağustos 2026"`, `"2 saat önce"`, `"5.2K Okunma"`. Raw numeric and ISO 8601 timestamps are returned by the API; formatting is handled by the client.
3. **SEO Head Tags & JSON-LD:** Structured data (`<script type="application/ld+json">`), OpenGraph, Twitter Cards, meta robots. These will be injected by the PHP Router HTML shell (Aşama 15), not the business API.
4. **Breadcrumb Arrays:** Server-computed HTML breadcrumb links. The client derives breadcrumb hierarchy from entity routes.
5. **Static Site Navigation Dictionaries:** Footer category/tag clouds injected into SSR layouts.

---

## 4. Reconciliation Action Items Matrix

| ID | Issue | Current State | Target State | Phase Target |
|---|---|---|---|---|
| **REC-01** | Reader access control bypass | SSR bypasses WalletService | All reader access routed through `ChapterService::getByTypeSlugAndNumber` | Aşama 5 |
| **REC-02** | Search filter omission | API ignores filters | `ContentController::search` supports `genres`, `tags`, `status`, `sort` | Aşama 5 |
| **REC-03** | Chapter list guest fallback | API misses session user | `ContentController::chaptersByType` reads `$_SESSION['user_id']` | Aşama 5 |
| **REC-04** | Reader series identity missing | `ChapterService` lacks series title/slug/type | `ChapterService` attaches `series_title`, `series_slug`, `series_type` | Aşama 5 |
| **REC-05** | Pagination meta standardization | Inconsistent pagination meta | Standardize `meta.pagination` across all collection endpoints | Aşama 6 |
| **REC-06** | Validation error structure | Inconsistent validation errors | Standardize field-level error envelope | Aşama 3 |
