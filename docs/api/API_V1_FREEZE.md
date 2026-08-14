# NM-READER — API V1 CANONICAL FREEZE SPECIFICATION

**Document Version:** 1.0.0  
**Freeze Status:** FULLY FROZEN (LOCKED FOR PRODUCTION / CSR INTEGRATION)  
**Date:** 2026-08-14  
**Scope:** Authoritative freeze document establishing the immutable baseline for the NM-Reader v1 Backend REST API, Security/Auth Subsystem, Media Engine, OpenAPI 3.1.0 Specification, and React Frontend Client Subsystem.

---

## 1. Executive Summary & Freeze Guarantee

The NM-Reader v1 API has completed all stabilization, security audit, test automation, and frontend client abstraction phases. 

### Freeze Rules:
1. **Contract Immutability:** No breaking changes, renamed fields, removed endpoints, or modified HTTP status codes may be introduced under `/api/v1/*`.
2. **Envelope Guarantee:** All responses strictly follow `{ status, data, meta, error }`.
3. **Session-First Security:** Native cookie `nm_reader_session` + `X-CSRF-Token` mutation protocol is the canonical web transport.
4. **Media Path Isolation:** Zero filesystem paths leak to API consumers; all assets resolve via `/media/public/*` or temporary HMAC signed tokens `/media/chapter/t_*`.
5. **Versioning Policy:** Any future breaking architectural changes must target `/api/v2/*` in a separate route group.

---

## 2. Canonical Contracts Inventory

All foundational contracts are finalized and co-located in [`docs/api/`](file:///home/duldul/Belgeler/nm-reader/docs/api/):

| Document | Purpose | Status |
|:---|:---|:---|
| [`API_CONTRACT.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_CONTRACT.md) | 94 Endpoints Canonical Schema & Response Envelopes | FROZEN |
| [`AUTH_CONTRACT.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/AUTH_CONTRACT.md) | Session, Cookie, Remember-Me, and CSRF Protocol | FROZEN |
| [`MEDIA_CONTRACT.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/MEDIA_CONTRACT.md) | Public Media and Protected Chapter Media Token Scheme | FROZEN |
| [`API_CLIENT_CONTRACT.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_CLIENT_CONTRACT.md) | React Frontend 3-Tier Client Architecture & Services | FROZEN |
| [`API_DOCUMENTATION.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_DOCUMENTATION.md) | Developer Guide & Domain Specifications | FROZEN |
| [`openapi.json`](file:///home/duldul/Belgeler/nm-reader/docs/api/openapi.json) | OpenAPI 3.1.0 Machine-Readable Specification (118 routes) | FROZEN |
| [`API_CONTRACT_AUDIT.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_CONTRACT_AUDIT.md) | Full Endpoint Inventory and Validation History | ARCHIVED |
| [`API_RECONCILIATION.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_RECONCILIATION.md) | SSR vs REST API Reconciliation Audit | ARCHIVED |

---

## 3. 94 Canonical Endpoints Baseline

```
================================================================================
                    NM-READER API V1 ENDPOINT REGISTRY
================================================================================
DOMAIN                        METHOD   ROUTE
--------------------------------------------------------------------------------
Public / Discovery (8)        GET      /api/v1/home
                              GET      /api/v1/content/type/{type}
                              GET      /api/v1/content/{type}/{slug}
                              GET      /api/v1/content/{type}/{slug}/chapters
                              GET      /api/v1/latest-chapters
                              GET      /api/v1/content/{type}/chapters
                              GET      /api/v1/shop/packages
                              GET      /api/v1/shop/features
--------------------------------------------------------------------------------
Taxonomy (6)                  GET      /api/v1/genres
                              GET      /api/v1/tags
                              GET      /api/v1/genre/{slug}
                              GET      /api/v1/tag/{slug}
                              GET      /api/v1/series_genres
                              GET      /api/v1/series_tags
--------------------------------------------------------------------------------
Reader & Access (1)           GET      /api/v1/content/{type}/{slug}/chapter/{chapterNumber}
--------------------------------------------------------------------------------
Comments & Reviews (9)        GET      /api/v1/chapter/{chapterId}/comments
                              GET      /api/v1/content/{type}/{slug}/comments
                              GET      /api/v1/blogs/{slug}/comments
                              POST     /api/v1/content/{type}/{slug}/rate
                              POST     /api/v1/content/{type}/{slug}/comment
                              POST     /api/v1/chapter/{chapterId}/comment
                              POST     /api/v1/comments/{commentId}/vote
                              POST     /api/v1/blogs/{slug}/comments
                              POST     /api/v1/blogs/{slug}/comments/{commentId}/vote
--------------------------------------------------------------------------------
Social & Follows (6)          POST     /api/v1/content/{type}/{slug}/follow
                              DELETE   /api/v1/content/{type}/{slug}/follow
                              GET      /api/v1/user/follows
                              GET      /api/v1/user/follows/users
                              POST     /api/v1/user/follows/{person}
                              DELETE   /api/v1/user/follows/{person}
--------------------------------------------------------------------------------
Blogs (6)                     GET      /api/v1/blogs
                              GET      /api/v1/blogs/{slug}
                              POST     /api/v1/blogs
                              POST     /api/v1/blogs/image
                              POST     /api/v1/blogs/{slug}/vote
                              GET      /api/v1/user/blogs
--------------------------------------------------------------------------------
Search & Meta (6)             GET      /api/v1/search
                              GET      /api/v1/search/suggest
                              GET      /api/v1/i18n/{lang}
                              POST     /api/v1/log/error
                              POST     /api/v1/user/activity
                              GET      /api/v1/profile/{person}
--------------------------------------------------------------------------------
Auth & Session (6)            POST     /api/v1/auth/register
                              POST     /api/v1/auth/login
                              POST     /api/v1/auth/refresh
                              POST     /api/v1/auth/logout
                              GET      /api/v1/auth/sessions
                              DELETE   /api/v1/auth/sessions/{sessionKey}
--------------------------------------------------------------------------------
User Protected (16)           GET      /api/v1/user/profile
                              POST     /api/v1/user/profile
                              GET      /api/v1/user/history
                              GET      /api/v1/user/preferences
                              PUT      /api/v1/user/preferences
                              GET      /api/v1/user/wallet
                              GET      /api/v1/user/wallet/transactions
                              GET      /api/v1/user/features
                              GET      /api/v1/user/features/entitlements
                              POST     /api/v1/user/features/ad-free/purchase
                              GET      /api/v1/user/unlocks/series
                              GET      /api/v1/user/unlocks/chapters
                              POST     /api/v1/content/{type}/{slug}/unlock
                              POST     /api/v1/chapter/{chapterId}/unlock
                              GET      /api/v1/user/notifications
                              POST     /api/v1/user/notifications/read
--------------------------------------------------------------------------------
Media (2)                     GET      /api/v1/media/public/{filename}
                              GET      /api/v1/media/chapter/{token}
--------------------------------------------------------------------------------
Admin (43)                    GET      /api/v1/admin/overview
                              GET      /api/v1/admin/series
                              GET      /api/v1/admin/genres
                              GET      /api/v1/admin/tags
                              GET      /api/v1/admin/users
                              GET      /api/v1/admin/users/options
                              GET      /api/v1/admin/uploads
                              DELETE   /api/v1/admin/uploads/{id}
                              GET      /api/v1/admin/blogs
                              GET      /api/v1/admin/blogs/pending
                              GET      /api/v1/admin/comments
                              DELETE   /api/v1/admin/comments/{id}
                              PUT      /api/v1/admin/users/{id}
                              GET      /api/v1/admin/rbac/roles
                              GET      /api/v1/admin/rbac/assignments
                              POST     /api/v1/admin/rbac/permissions/assign
                              GET      /api/v1/admin/queue/jobs
                              POST     /api/v1/admin/queue/run-once
                              POST     /api/v1/admin/retention/cleanup
                              POST     /api/v1/admin/maintenance/backup
                              POST     /api/v1/admin/maintenance/sitemap
                              POST     /api/v1/admin/maintenance/warmup
                              POST     /api/v1/admin/maintenance/analytics
                              GET      /api/v1/admin/shop/packages
                              POST     /api/v1/admin/shop/packages
                              PUT      /api/v1/admin/shop/packages/{id}
                              POST     /api/v1/admin/wallets/{userId}/grant-package
                              POST     /api/v1/admin/wallets/{userId}/credit
                              POST     /api/v1/admin/wallets/{userId}/debit
                              GET      /api/v1/admin/wallets/{userId}
                              GET      /api/v1/admin/wallets/{userId}/transactions
                              PUT      /api/v1/admin/series/{id}/pricing
                              PUT      /api/v1/admin/chapters/{id}/pricing
                              GET      /api/v1/admin/features
                              PUT      /api/v1/admin/features/ad-free
                              GET      /api/v1/admin/maintenance/env
                              POST     /api/v1/admin/maintenance/env
                              GET      /api/v1/admin/audit-logs
                              GET      /api/v1/admin/login-events
                              GET      /api/v1/admin/moderation-actions
                              POST     /api/v1/admin/moderation-actions
                              GET      /api/v1/admin/logs/access
                              GET      /api/v1/admin/logs/error
================================================================================
TOTAL CANONICAL ENDPOINTS: 94
```

---

## 4. Test Suite Baseline

- **Backend API Regression Suite:** [`app/Console/ApiTestSuite.php`](file:///home/duldul/Belgeler/nm-reader/app/Console/ApiTestSuite.php)
  - Execution: `composer test:api`
  - Result: **124 / 124 PASS (100% Green)**
- **Frontend Client Test Suite:** [`ui/scripts/test-client.ts`](file:///home/duldul/Belgeler/nm-reader/ui/scripts/test-client.ts)
  - Execution: `npm run test:client` (in `ui/`)
  - Result: **25 / 25 PASS (100% Green)**
- **OpenAPI Validation:** [`app/Console/generate_openapi.php`](file:///home/duldul/Belgeler/nm-reader/app/Console/generate_openapi.php)
  - Result: Valid OpenAPI 3.1.0 specification (118 routes documented)
- **Frontend Typecheck & Build:** `npm run typecheck && npm run build` (in `ui/`)
  - Result: Clean TypeScript compile & production bundle generated in [`public/app.html`](file:///home/duldul/Belgeler/nm-reader/public/app.html)

---

## 5. Sign-off & Status

The NM-Reader v1 API, Auth, Media, and Client contracts are formally declared **FROZEN**. All future development on the frontend will consume these endpoints according to [`docs/api/API_CLIENT_CONTRACT.md`](file:///home/duldul/Belgeler/nm-reader/docs/api/API_CLIENT_CONTRACT.md).
