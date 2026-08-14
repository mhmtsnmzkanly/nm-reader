# NM-READER — CANONICAL API DOCUMENTATION (V1)

**Version:** 1.0.0  
**Status:** FROZEN  
**Specification:** OpenAPI 3.1.0 Compatible  
**Envelope Standard:** Unified JSON Envelope (`status`, `data`, `meta`, `error`)

---

## 1. Unified Response Standard

Every response returned by `/api/v1/*` follows the strict JSON structure:

### 1.1 Success Response (`status: "success"`)
```json
{
  "status": "success",
  "data": { ... } | [ ... ],
  "meta": {
    "pagination": {
      "type": "offset",
      "page": 1,
      "per_page": 20,
      "total": 142,
      "total_pages": 8
    },
    "page": 1,
    "per_page": 20
  },
  "error": null
}
```

### 1.2 Error Response (`status: "error"`)
```json
{
  "status": "error",
  "data": null,
  "meta": [],
  "error": {
    "code": 400,
    "key": "VALIDATION_FAILED",
    "message": "Validation failed",
    "params": ["Email is required"]
  }
}
```

---

## 2. Authentication & Security Contract

1. **Session Cookie**: `nm_reader_session` (`HttpOnly`, `SameSite=Lax`, `Secure`).
2. **Bearer Token**: `Authorization: Bearer <jwt_or_session_token>`.
3. **CSRF Header**: State-changing requests (`POST`, `PUT`, `DELETE`, `PATCH`) must include `X-CSRF-Token`.
4. **Media Protection**: Chapter image pages require temporary HMAC-SHA256 signed access tokens.

---

## 3. Comprehensive 94 Endpoint Domain Directory

### 3.1 Content & Discovery (Public)
| Method | Endpoint | Description | Cache Policy |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/home` | Aggregated homepage feed (featured, latest, top) | `public, max-age=60` |
| `GET` | `/api/v1/content/type/{type}` | Series listing by type (`manga`, `novel`, etc.) | `public, max-age=60` |
| `GET` | `/api/v1/content/{type}/chapters` | Latest chapters grouped by type | `public, max-age=30` |
| `GET` | `/api/v1/content/{type}/{slug}` | Series detail & metadata | `public, max-age=120` |
| `GET` | `/api/v1/content/{type}/{slug}/chapters` | Chapter list for a specific series | `public, max-age=60` |
| `GET` | `/api/v1/latest-chapters` | Global latest chapters feed | `public, max-age=30` |
| `GET` | `/api/v1/shop/packages` | Coin packages catalogue | `public, max-age=300` |
| `GET` | `/api/v1/shop/features` | Premium features & entitlement packages | `public, max-age=300` |

### 3.2 Taxonomy (Public)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/genres` | List all genres with pagination |
| `GET` | `/api/v1/tags` | List all tags with pagination |
| `GET` | `/api/v1/genre/{slug}` | Series filtered by specific genre |
| `GET` | `/api/v1/tag/{slug}` | Series filtered by specific tag |
| `GET` | `/api/v1/series_genres` | Genre list alias |
| `GET` | `/api/v1/series_tags` | Tag list alias |

### 3.3 Reader & Premium Access
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/content/{type}/{slug}/chapter/{chapterNumber}` | Optional | Returns text body or temporary signed media tokens. Free chapters return content; locked chapters return `is_locked: true, price_coin: X, body: null, pages: []`. |

### 3.4 Comments & Interactions
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/chapter/{chapterId}/comments` | Optional | Cursor-paginated chapter comment thread |
| `GET` | `/api/v1/content/{type}/{slug}/comments` | Optional | Cursor-paginated series comment thread |
| `GET` | `/api/v1/blogs/{slug}/comments` | Optional | Cursor-paginated blog comment thread |
| `POST` | `/api/v1/content/{type}/{slug}/rate` | Required | Rate series (1–5) |
| `POST` | `/api/v1/content/{type}/{slug}/comment` | Required | Post comment on series |
| `POST` | `/api/v1/chapter/{chapterId}/comment` | Required | Post comment on chapter |
| `POST` | `/api/v1/comments/{commentId}/vote` | Required | Upvote (+1) or downvote (-1) comment |
| `POST` | `/api/v1/blogs/{slug}/comments` | Required | Post comment on blog |
| `POST` | `/api/v1/blogs/{slug}/comments/{commentId}/vote` | Required | Vote on blog comment |

### 3.5 Social & Follows
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/content/{type}/{slug}/follow` | Required | Follow a series |
| `DELETE` | `/api/v1/content/{type}/{slug}/follow` | Required | Unfollow a series |
| `GET` | `/api/v1/user/follows` | Required | Paginated list of series followed by user |
| `GET` | `/api/v1/user/follows/users` | Required | List of users followed by authenticated user |
| `POST` | `/api/v1/user/follows/{person}` | Required | Follow a user profile |
| `DELETE` | `/api/v1/user/follows/{person}` | Required | Unfollow a user profile |

### 3.6 Blog & Community
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/blogs` | Optional | Paginated published blog articles |
| `GET` | `/api/v1/blogs/{slug}` | Optional | Single blog post detail with viewer vote |
| `POST` | `/api/v1/blogs` | Required | Create new community blog post (pending review) |
| `POST` | `/api/v1/blogs/image` | Required | Upload featured image for blog |
| `POST` | `/api/v1/blogs/{slug}/vote` | Required | Vote on blog (+1 / -1) |
| `GET` | `/api/v1/user/blogs` | Required | List authenticated user's own blog submissions |

### 3.7 Search & Metadata
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/search` | Optional | Filtered series search (`q`, `genres`, `tags`, `status`, `sort`) |
| `GET` | `/api/v1/search/suggest` | Optional | Live search suggestions autocomplete |
| `GET` | `/api/v1/i18n/{lang}` | Public | Static localized translations asset |
| `POST` | `/api/v1/log/error` | Public | Client runtime error reporting |
| `POST` | `/api/v1/user/activity` | Required | Client reading session heartbeats & analytics |
| `GET` | `/api/v1/profile/{person}` | Optional | Public user profile, badges, stats |

### 3.8 Authentication & Sessions
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/register` | Public | Register new user account |
| `POST` | `/api/v1/auth/login` | Public | Login with email/password |
| `POST` | `/api/v1/auth/refresh` | Public | Single-use refresh token rotation |
| `POST` | `/api/v1/auth/logout` | Optional | Invalidate current session |
| `GET` | `/api/v1/auth/sessions` | Required | List active user device sessions |
| `DELETE` | `/api/v1/auth/sessions/{sessionKey}` | Required | Terminate specific remote session |

### 3.9 User Account & Wallet
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/user/profile` | Required | Get authenticated user profile & balance |
| `POST` | `/api/v1/user/profile` | Required | Update user display name / bio / avatar |
| `GET` | `/api/v1/user/history` | Required | Paginated reading history |
| `GET` | `/api/v1/user/preferences` | Required | Get reading & UI preferences |
| `PUT` | `/api/v1/user/preferences` | Required | Update theme, font, reader direction preferences |
| `GET` | `/api/v1/user/wallet` | Required | Coin wallet balance & total spent |
| `GET` | `/api/v1/user/wallet/transactions` | Required | Wallet transaction ledger history |
| `GET` | `/api/v1/user/features` | Required | Active feature entitlements |
| `GET` | `/api/v1/user/features/entitlements`| Required | Entitlement flags (e.g. `ad_free`) |
| `POST` | `/api/v1/user/features/ad-free/purchase`| Required | Purchase ad-free feature with coins |
| `GET` | `/api/v1/user/unlocks/series` | Required | List of series unlocked by user |
| `GET` | `/api/v1/user/unlocks/chapters` | Required | List of chapters unlocked by user |
| `POST` | `/api/v1/content/{type}/{slug}/unlock` | Required | Unlock series with coins |
| `POST` | `/api/v1/chapter/{chapterId}/unlock` | Required | Unlock single chapter with coins |
| `GET` | `/api/v1/user/notifications` | Required | Notifications feed |
| `POST` | `/api/v1/user/notifications/read` | Required | Mark notifications as read |

### 3.10 Media Delivery
| Method | Endpoint | Auth | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/media/public/{filename}` | Public | Serves covers/avatars with immutable caching |
| `GET` | `/api/v1/media/chapter/{token}` | Token | Streams protected chapter image via temporary token |

### 3.11 Admin Panel (43 Endpoints — RBAC Enforced)
All `/api/v1/admin/*` endpoints require `AuthMiddleware` and `PermissionMiddleware`:
- `GET /api/v1/admin/overview` (`admin.panel.access`)
- `GET /api/v1/admin/series` (`admin.panel.access`)
- `POST /api/v1/admin/content` (`admin.content.create`)
- `PUT /api/v1/admin/content/{id}` (`admin.content.update`)
- `PUT /api/v1/admin/contents/{id}/taxonomy` (`admin.content.update`)
- `GET /api/v1/admin/content/{id}/chapters` (`admin.panel.access`)
- `POST /api/v1/admin/content/{id}/chapters` (`admin.chapter.create`)
- `POST /api/v1/admin/content/{type}/{slug}/chapters` (`admin.chapter.create`)
- `GET /api/v1/admin/chapters/{id}` (`admin.panel.access`)
- `PUT /api/v1/admin/chapters/{id}` (`admin.content.update`)
- `DELETE /api/v1/admin/chapters/{id}` (`admin.content.update`)
- `GET /api/v1/admin/genres` (`admin.panel.access`)
- `POST /api/v1/admin/series_genres` (`admin.content.create`)
- `GET /api/v1/admin/tags` (`admin.panel.access`)
- `POST /api/v1/admin/series_tags` (`admin.content.create`)
- `GET /api/v1/admin/users` (`admin.panel.access`)
- `PUT /api/v1/admin/users/{id}` (`admin.users.manage`)
- `GET /api/v1/admin/users/options` (`admin.wallet.view`)
- `GET /api/v1/admin/rbac/roles` (`admin.panel.access`)
- `GET /api/v1/admin/rbac/assignments` (`admin.panel.access`)
- `POST /api/v1/admin/rbac/permissions/assign` (`admin.permissions.grant`)
- `GET /api/v1/admin/blogs` (`admin.panel.access`)
- `GET /api/v1/admin/blogs/pending` (`admin.panel.access`)
- `POST /api/v1/admin/blogs/{id}/approve` (`admin.blog.hide`)
- `POST /api/v1/admin/blogs/{id}/hide` (`admin.blog.hide`)
- `DELETE /api/v1/admin/blogs/{id}` (`admin.blog.hide`)
- `GET /api/v1/admin/comments` (`admin.panel.access`)
- `DELETE /api/v1/admin/comments/{id}` (`admin.comment.delete`)
- `GET /api/v1/admin/uploads` (`admin.panel.access`)
- `DELETE /api/v1/admin/uploads/{id}` (`admin.panel.access`)
- `POST /api/v1/admin/upload-images` (`admin.content.create`)
- `GET /api/v1/admin/shop/packages` (`admin.shop.manage`)
- `POST /api/v1/admin/shop/packages` (`admin.shop.manage`)
- `PUT /api/v1/admin/shop/packages/{id}` (`admin.shop.manage`)
- `GET /api/v1/admin/features` (`admin.shop.manage`)
- `PUT /api/v1/admin/features/ad-free` (`admin.shop.manage`)
- `GET /api/v1/admin/wallets/{userId}` (`admin.wallet.view`)
- `GET /api/v1/admin/wallets/{userId}/transactions` (`admin.wallet.view`)
- `POST /api/v1/admin/wallets/{userId}/grant-package` (`admin.wallet.manage`)
- `POST /api/v1/admin/wallets/{userId}/credit` (`admin.wallet.manage`)
- `POST /api/v1/admin/wallets/{userId}/debit` (`admin.wallet.manage`)
- `PUT /api/v1/admin/series/{id}/pricing` (`admin.shop.manage`)
- `PUT /api/v1/admin/chapters/{id}/pricing` (`admin.shop.manage`)
- `GET /api/v1/admin/queue/jobs` (`admin.panel.access`)
- `POST /api/v1/admin/queue/run-once` (`admin.jobs.run`)
- `POST /api/v1/admin/retention/cleanup` (`admin.jobs.run`)
- `POST /api/v1/admin/maintenance/backup` (`admin.jobs.run`)
- `POST /api/v1/admin/maintenance/sitemap` (`admin.jobs.run`)
- `POST /api/v1/admin/maintenance/warmup` (`admin.jobs.run`)
- `POST /api/v1/admin/maintenance/analytics` (`admin.jobs.run`)
- `GET /api/v1/admin/maintenance/env` (`admin.panel.access`)
- `POST /api/v1/admin/maintenance/env` (`admin.panel.access`)
- `GET /api/v1/admin/audit-logs` (`admin.logs.view`)
- `GET /api/v1/admin/login-events` (`admin.logs.view`)
- `GET /api/v1/admin/moderation-actions` (`admin.logs.view`)
- `POST /api/v1/admin/moderation-actions` (`admin.logs.view`)
- `GET /api/v1/admin/logs/access` (`admin.logs.view`)
- `GET /api/v1/admin/logs/error` (`admin.logs.view`)
- `GET /api/v1/admin/stats/visits` (`admin.metrics.view`)
- `GET /api/v1/admin/stats/views` (`admin.metrics.view`)
- `GET /api/v1/admin/stats/blogs` (`admin.metrics.view`)
- `GET /api/v1/admin/stats/reputation` (`admin.metrics.view`)
- `GET /api/v1/admin/metrics` (`admin.metrics.view`)
- `GET /api/v1/admin/metrics/insights` (`admin.metrics.view`)
