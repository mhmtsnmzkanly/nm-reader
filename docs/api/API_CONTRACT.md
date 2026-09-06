# NM-Reader Canonical API v1 Contract Specification

> **Archived baseline:** This pre-freeze document is retained for history and may omit later routes. `app/Config.php` is the runtime route authority; `docs/api/openapi.json` is a generated reference for the documented subset.

**Version:** 1.0.0-draft  
**Status:** PROPOSED (Pre-Freeze)  
**Date:** 2026-08-14  
**Scope:** Canonical specification for all 94 backend API endpoints (51 Public/User + 43 Admin) serving as the authoritative contract for the React/CSR frontend.

---

## 1. API Overview

The NM-Reader API v1 provides high-performance, stateless JSON communication for serialized content discovery, reading, taxonomy navigation, user profile/library/history management, social interaction (comments, votes, ratings), monetization (wallets, coin purchases, content/feature unlocks), and management console operations.

---

## 2. Base URL & Protocol

- **Base URL:** `{APP_URL}/api/v1` (e.g. `https://example.com/api/v1` or `http://localhost:8080/api/v1`)
- **Transport:** HTTPS enforced in production (308 Permanent Redirect for unencrypted HTTP).
- **Format:** `application/json; charset=utf-8`
- **CORS:** Enabled for permitted origins; credentials supported (`Access-Control-Allow-Credentials: true`).

---

## 3. Versioning Strategy

- The current version is `/api/v1`.
- **Breaking changes** (field deletions, type modifications, semantic envelope alterations, new mandatory request parameters) require a major version bump to `/api/v2` or a dedicated migration path.
- **Non-breaking additions** (new optional response fields, new optional query parameters, new independent endpoints) are permitted within v1 without version increment.

---

## 4. Authentication Contract

The API supports a hybrid authentication model:

1. **Session Authentication (Primary / Canonical for Web & CSR Frontend):**
   - Cookie: `nm_reader_session` (HttpOnly, SameSite=Lax, Secure in HTTPS).
   - Identity stored in PHP session (`$_SESSION['user_id']`, `$_SESSION['roles']`, `$_SESSION['permissions']`, `$_SESSION['session_key']`).
   - Session lifetime: configurable (default: 7200 seconds).
   - Auto-recovery: `nm_remember` cookie (30-day token) automatically re-establishes session if expired.
2. **Bearer Token Authentication (Mobile / Public Flow Support):**
   - Header: `Authorization: Bearer <api_token>`
   - Populates `request->getAttribute('user_id')`. Note: Protected mutation groups also enforce session + CSRF token.
3. **Endpoint Auth Classifications:**
   - `Public`: Anonymous access allowed; does not require auth credentials.
   - `Viewer-Aware (Optional Auth)`: Anonymous access allowed, but if session or Bearer token is provided, returns personalized state (e.g. `my_vote`, `is_unlocked`, `is_followed`).
   - `Required`: Missing authentication returns `401 UNAUTHORIZED`.
   - `Admin Permission`: Requires authentication + specific RBAC permission (`403 FORBIDDEN` if missing).

---

## 5. CSRF Protection Contract

- **Header Name:** `X-CSRF-Token`
- **Scope:** All state-changing HTTP methods (`POST`, `PUT`, `DELETE`, `PATCH`) in the protected API groups.
- **Exception:** `POST /api/v1/auth/login`, `POST /api/v1/auth/register`, `POST /api/v1/auth/refresh`, `GET/POST /api/v1/auth/logout`, `POST /api/v1/log/error`, `POST /api/v1/user/activity`.
- **Validation:** Matches token with `$_SESSION['csrf_token']`.
- **Failure:** Returns `419 CSRF_INVALID`.
- **Acquisition:** Sent in response header `X-CSRF-Token` on every session response, and returned in payload of `POST /auth/login` and `POST /auth/refresh`.

---

## 6. Standard Response Envelope

All API endpoints MUST respond with one of the two canonical envelopes:

### 6.1 Success Envelope (HTTP 200 / 201)
```json
{
  "status": "success",
  "data": {},
  "meta": {},
  "error": null
}
```
- `status` (`string`): Always `"success"`.
- `data` (`object|array`): Primary payload. Never `null` on success (use empty object `{}` or array `[]` if no data).
- `meta` (`object`): Supplemental metadata (pagination, filters, server time). Defaults to empty object `{}`.
- `error` (`null`): Always `null` on success.

### 6.2 Error Envelope (HTTP 4xx / 5xx)
```json
{
  "status": "error",
  "data": null,
  "meta": {},
  "error": {
    "code": 404,
    "key": "NOT_FOUND",
    "message": "Resource not found",
    "params": [],
    "fields": {}
  }
}
```
- `status` (`string`): Always `"error"`.
- `data` (`null`): Always `null` on error.
- `meta` (`object`): Supplemental metadata (or empty object `{}`).
- `error` (`object`):
  - `code` (`integer`): HTTP status code matching the response status.
  - `key` (`string`): Machine-readable uppercase identifier for frontend i18n lookup (e.g. `NOT_FOUND`, `UNAUTHORIZED`, `VALIDATION_ERROR`).
  - `message` (`string`): Human-readable descriptive English message.
  - `params` (`array`): Optional placeholder substitution parameters.
  - `fields` (`object<string, array<string>>`, optional): Field-level validation error map for forms.

---

## 7. HTTP Status Codes Contract

| Status Code | Meaning | Key | Description / Frontend Handling |
|---|---|---|---|
| `200 OK` | Success | - | Standard successful retrieval or synchronous mutation. |
| `201 Created` | Created | - | Resource successfully created (`POST /blogs`, `POST /chapter/.../comment`, `POST /auth/register`). |
| `400 Bad Request` | Bad Request | `BAD_REQUEST` | Malformed parameters, type mismatch, invalid argument. Show inline error or alert. |
| `401 Unauthorized` | Unauthorized | `UNAUTHORIZED` | Unauthenticated user requesting protected resource or invalid credentials. Prompt login. |
| `402 Payment Required` | Payment Required | `PAYMENT_REQUIRED` | Insufficient coin balance for unlock/purchase. Redirect to Shop/Wallet modal. |
| `403 Forbidden` | Forbidden | `FORBIDDEN` | Authenticated user lacks permission or action is restricted. Show permission denied. |
| `404 Not Found` | Not Found | `NOT_FOUND` | Target entity (series, chapter, blog, user) does not exist or is inactive. Show 404 screen. |
| `409 Conflict` | Conflict | `CONFLICT` | State conflict (e.g. email already registered, post already approved). |
| `419 CSRF Invalid` | Session Expired | `CSRF_INVALID` | Stale or missing CSRF token. Trigger silent session refresh or prompt retry. |
| `422 Unprocessable` | Validation Error | `VALIDATION_ERROR` | Semantic validation failure. Render field errors from `error.fields`. |
| `429 Too Many Req.` | Rate Limited | `RATE_LIMITED` | Rate threshold exceeded. Show retry countdown. |
| `500 Server Error` | Internal Error | `INTERNAL_ERROR` | Unhandled server exception. Show generic fallback error. |

---

## 8. Error Contract & Standard Error Keys

```
BAD_REQUEST
UNAUTHORIZED
PAYMENT_REQUIRED
FORBIDDEN
NOT_FOUND
CONFLICT
CSRF_INVALID
VALIDATION_ERROR
RATE_LIMITED
INTERNAL_ERROR
INSUFFICIENT_BALANCE
ALREADY_UNLOCKED
ALREADY_PURCHASED
INVALID_CREDENTIALS
ACCOUNT_LOCKED
ACTION_RESTRICTED
```

---

## 9. Pagination Contract

### 9.1 Offset Pagination (Standard for catalog, history, transactions, admin lists)
**Request Parameters:**
- `page` (`integer`, optional, default: 1, min: 1)
- `per_page` (`integer`, optional, default: 20, min: 1, max: 50)

**Response `meta.pagination` Envelope:**
```json
{
  "meta": {
    "pagination": {
      "type": "offset",
      "page": 1,
      "per_page": 20,
      "total": 142,
      "total_pages": 8
    }
  }
}
```

### 9.2 Cursor Pagination (Standard for real-time threads: comments, notifications)
**Request Parameters:**
- `cursor` (`string|null`, optional, base64-encoded composite cursor)
- `per_page` (`integer`, optional, default: 20, min: 1, max: 50)

**Response `meta.pagination` Envelope:**
```json
{
  "meta": {
    "pagination": {
      "type": "cursor",
      "per_page": 20,
      "next_cursor": "eyJjcmVhdGVkX2F0IjoiMjAyNi0wOC0xNC...===",
      "has_more": true
    }
  }
}
```

---

## 10. Data Types & Canonical Formats

1. **Identifiers:**
   - Users: 8-character lowercase alphanumeric string (`/^[a-z0-9]{8}$/`, e.g. `"usr00001"`).
   - Series / Content: 6-character lowercase alphanumeric string (`/^[a-z0-9]{6}$/`, e.g. `"s00001"`).
   - Chapters: 6-character lowercase alphanumeric string (`/^[a-z0-9]{6}$/`, e.g. `"c00001"`).
   - Comments / Packages / Transactions: Positive integer (`int`).
2. **Timestamps:**
   - Format: Standard ISO 8601 UTC / MySQL format (`YYYY-MM-DD HH:MM:SS` or `YYYY-MM-DDTHH:MM:SSZ`).
   - Localization & relative formatting ("2 hours ago") MUST be performed on the client.
3. **Numbers & Decimals:**
   - `rating_avg`: Strict `float` (e.g. `4.75`).
   - `rating_count`, `chapter_count`, `comment_count`, `views_count`, `balance_coin`, `price_coin`: Strict `integer`.
   - `chapter_number`: Strict `string` (supports decimals e.g. `"1"`, `"1.5"`, `"10.1"`).
4. **Booleans:**
   - Strict `true` / `false` (no integer `1`/`0` or string `"true"`/`"false"`).

---

## 11. Nullability & Empty Collections Rules

- **Nullable Objects / Strings:** `null` signifies absence of value (e.g. `cover_image: null`, `bio: null`, `body: null` for locked chapters).
- **Empty Arrays:** Arrays default to `[]`, never `null` (e.g. `series_genres: []`, `pages: []`).
- **Empty Objects:** Meta defaults to `{}` when empty.
- **Sentinel Values:** No implicit sentinels (e.g. `-1` or `0` meaning "none" is disallowed for nullable references).

---

## 12. Complete Public & User API Endpoints Contract (51 Endpoints)

### 12.1 Content & Discovery (13 Endpoints)

#### 1. `GET /api/v1/home`
- **Action:** `ContentController::home`
- **Auth:** Public
- **CSRF:** None
- **Query:** `page` (`int`, opt, def: 1), `per_page` (`int`, opt, def: 20)
- **Response `data`:**
  - `explore`: `array<ContentDto>`
  - `recent_chapters`: `array<{chapter_id: string, chapter_number: string, title: string|null, type: string, created_at: string, series_id: string, series_title: string, series_slug: string, series_type: string, cover_image: string|null}>`
  - `recently_added`: `array<ContentDto>`
  - `popular_blogs`: `array<BlogSummary>`
  - `latest_blogs`: `array<BlogSummary>`
- **Meta:** `pagination` object
- **Side Effect:** Logs `home_view` analytics event.

#### 2. `GET /api/v1/content/type/{type}`
- **Action:** `ContentController::byType`
- **Constraints:** `type`: `light-novel|web-novel|novel|manga|manhua|manhwa|webtoon`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<ContentDto>`

#### 3. `GET /api/v1/content/{type}/{slug}`
- **Action:** `ContentController::contentByType`
- **Auth:** Viewer-Aware (checks `$_SESSION['user_id']` or Bearer)
- **Response `data`:**
  - `id`: `string`
  - `title`: `string`
  - `slug`: `string`
  - `type`: `string`
  - `status`: `string` (`ongoing|completed|hiatus|cancelled`)
  - `description`: `string|null`
  - `author`: `string|null`
  - `artist`: `string|null`
  - `cover_image`: `string|null`
  - `accent_color`: `string|null`
  - `release_year`: `string|null`
  - `country`: `string|null`
  - `alternative_titles`: `string|null`
  - `rating_avg`: `float`
  - `rating_count`: `int`
  - `chapter_count`: `int`
  - `comment_count`: `int`
  - `series_genres`: `array<{id: int, name: string, slug: string}>`
  - `series_tags`: `array<{id: int, name: string, slug: string}>`
  - `type_path`: `string`
  - `url_path`: `string`
  - `is_followed`: `boolean` (viewer-specific, default `false` for guests)
  - `reading_progress`: `{chapter_id: string, chapter_number: string, read_at: string}|null`
  - `series_unlock_price`: `int`
  - `is_series_unlocked`: `boolean`
  - `has_any_premium`: `boolean`
- **Errors:** `404 NOT_FOUND`

#### 4. `GET /api/v1/content/{type}/{slug}/chapters`
- **Action:** `ContentController::chaptersByType`
- **Auth:** Viewer-Aware (MUST read `$_SESSION['user_id']` fallback for logged-in sessions)
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<ChapterDto>`
  - `id`: `string`
  - `content_id`: `string`
  - `chapter_number`: `string`
  - `title`: `string|null`
  - `type`: `string`
  - `created_at`: `string`
  - `is_locked`: `boolean`
  - `price_coin`: `int`
  - `is_unlocked`: `boolean`
- **Meta:** `pagination` object

#### 5. `GET /api/v1/latest-chapters`
- **Action:** `ContentController::latestChapters`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<LatestChapterItem>`

#### 6. `GET /api/v1/content/{type}/chapters`
- **Action:** `ContentController::latestChaptersByType`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<LatestChapterItem>`

#### 7. `GET /api/v1/genres` & `GET /api/v1/series_genres`
- **Action:** `ContentController::genres`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<{id: int, name: string, slug: string, description: string|null, series_count: int}>`

#### 8. `GET /api/v1/tags` & `GET /api/v1/series_tags`
- **Action:** `ContentController::tags`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<{id: int, name: string, slug: string, series_count: int}>`

#### 9. `GET /api/v1/genre/{slug}`
- **Action:** `ContentController::genre`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<ContentDto>`

#### 10. `GET /api/v1/tag/{slug}`
- **Action:** `ContentController::tag`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<ContentDto>`

#### 11. `GET /api/v1/shop/packages`
- **Action:** `ContentController::shopPackages`
- **Auth:** Public
- **Response `data`:** `array<{id: int, name: string, coin_amount: int, bonus_coin: int, total_coin: int, price_cents: int, currency: string}>`

#### 12. `GET /api/v1/shop/features`
- **Action:** `ContentController::shopFeatures`
- **Auth:** Public
- **Response `data`:** `array<{code: string, name: string, description: string, duration_days: int, price_coin: int, is_active: boolean}>`

#### 13. `GET /api/v1/user/follows`
- **Action:** `ContentController::followed`
- **Auth:** Required (Session)
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<ContentDto>`

---

### 12.2 Search Domain (2 Endpoints)

#### 14. `GET /api/v1/search`
- **Action:** `ContentController::search`
- **Auth:** Public
- **Query Parameters:**
  - `q` (`string`, required)
  - `genres` (`string`, optional, comma-separated genre slugs)
  - `tags` (`string`, optional, comma-separated tag slugs)
  - `status` (`string`, optional: `ongoing|completed|hiatus|cancelled`)
  - `sort` (`string`, optional: `popular|latest|rating|title|views`)
  - `page` (`int`, optional, default: 1)
  - `per_page` (`int`, optional, default: 20, max: 50)
- **Response `data`:** `array<ContentDto>`
- **Meta:** `{"q": string, "pagination": {...}}`
- **Side Effect:** Logs search analytics.

#### 15. `GET /api/v1/search/suggest`
- **Action:** `ContentController::suggest`
- **Auth:** Public
- **Query:** `q` (`string`, min 2 chars)
- **Response `data`:** `array<{title: string, slug: string, type: string, cover_image: string|null}>`

---

### 12.3 Reader & Chapter Domain (3 Endpoints)

#### 16. `GET /api/v1/content/{type}/{slug}/chapter/{chapterNumber}`
- **Action:** `ContentController::chapterDetail`
- **Auth:** Viewer-Aware (reads session user ID)
- **Response `data` (`object`):**
  - `id`: `string` (6-char)
  - `content_id`: `string` (6-char)
  - `series_title`: `string`
  - `series_slug`: `string`
  - `series_type`: `string`
  - `chapter_number`: `string`
  - `title`: `string|null`
  - `type`: `string` (`novel` or `manga`)
  - `created_at`: `string`
  - `price_coin`: `int`
  - `is_locked`: `boolean`
  - `body`: `string|null` (Present ONLY if `access.granted == true` and `type == 'novel'`; otherwise `null`)
  - `pages`: `array<{image_path: string, page_order: int}>` (Present ONLY if `access.granted == true` and `type == 'manga'`; otherwise `[]`)
  - `access`: `object`
    - `granted`: `boolean`
    - `reason`: `string` (`free|series_unlocked|chapter_unlocked|admin|author`)
    - `price_coin`: `int`
    - `balance_coin`: `int`
    - `can_unlock`: `boolean`
  - `adjacent_chapters`: `object`
    - `prev`: `string|null`
    - `next`: `string|null`
- **Side Effect:** If authenticated AND `access.granted == true`, marks chapter as read in `reading_history`.
- **Errors:** `404 NOT_FOUND`

#### 17. `POST /api/v1/content/{type}/{slug}/unlock`
- **Action:** `ContentController::unlockByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"unlocked": true, "content_id": string, "balance_coin": int, "cost_coin": int}`
- **Errors:** `402 PAYMENT_REQUIRED`, `404 NOT_FOUND`, `400 BAD_REQUEST`

#### 18. `POST /api/v1/chapter/{chapterId}/unlock`
- **Action:** `ContentController::unlockChapter`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"unlocked": true, "chapter_id": string, "balance_coin": int, "cost_coin": int}`
- **Errors:** `402 PAYMENT_REQUIRED`, `404 NOT_FOUND`, `400 BAD_REQUEST`

---

### 12.4 User Library & Social Interactions (4 Endpoints)

#### 19. `POST /api/v1/content/{type}/{slug}/follow`
- **Action:** `ContentController::followByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"followed": true}`

#### 20. `DELETE /api/v1/content/{type}/{slug}/follow`
- **Action:** `ContentController::unfollowByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"followed": false}`

#### 21. `POST /api/v1/content/{type}/{slug}/rate`
- **Action:** `UserInteractionController::rateByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"rating": integer}` (1..5)
- **Response `data`:** `{"rated": true}`

#### 22. `POST /api/v1/user/activity`
- **Action:** `UserInteractionController::trackActivity`
- **Auth:** Required (Session)
- **Body:** `{"tab_id": string, "duration": integer}`
- **Response `data`:** `{"tracked": true}`

---

### 12.5 Comments Domain (6 Endpoints)

#### 23. `GET /api/v1/chapter/{chapterId}/comments`
- **Action:** `UserInteractionController::listChapterComments`
- **Auth:** Viewer-Aware
- **Query:** `page` (`int`), `per_page` (`int`), `cursor` (`string|null`)
- **Response `data`:** `array<CommentDto>`
- **Meta:** `pagination` object with `next_cursor`

#### 24. `GET /api/v1/content/{type}/{slug}/comments`
- **Action:** `UserInteractionController::listSeriesComments`
- **Auth:** Viewer-Aware
- **Same structure as chapter comments.**

#### 25. `GET /api/v1/blogs/{slug}/comments`
- **Action:** `UserInteractionController::listBlogComments`
- **Auth:** Viewer-Aware
- **Same structure as chapter comments.**

#### 26. `POST /api/v1/chapter/{chapterId}/comment`
- **Action:** `UserInteractionController::createChapterComment`
- **Auth:** Required (Session + RestrictedAction `commenting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"body": string, "parent_id": integer|null}`
- **Response `data`:** `{"comment_id": integer}` (HTTP 201)

#### 27. `POST /api/v1/content/{type}/{slug}/comment`
- **Action:** `UserInteractionController::createSeriesComment`
- **Auth:** Required (Session + RestrictedAction `commenting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"body": string, "parent_id": integer|null}`
- **Response `data`:** `{"comment_id": integer}` (HTTP 201)

#### 28. `POST /api/v1/comments/{commentId}/vote`
- **Action:** `UserInteractionController::voteComment`
- **Auth:** Required (Session + RestrictedAction `voting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"vote": integer}` (`-1|0|1`)
- **Response `data`:** `{"score": int, "upvotes": int, "downvotes": int, "my_vote": int}`

---

### 12.6 Blog Platform (6 Endpoints)

#### 29. `GET /api/v1/blogs`
- **Action:** `BlogController::list`
- **Auth:** Public
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<BlogSummary>`
- **Meta:** `pagination` object

#### 30. `GET /api/v1/blogs/{slug}`
- **Action:** `BlogController::show`
- **Auth:** Viewer-Aware
- **Response `data` (`object`):**
  - Full blog post fields + `body` (`string`), `my_vote` (`integer`: `-1|0|1`)

#### 31. `POST /api/v1/blogs`
- **Action:** `BlogController::create`
- **Auth:** Required (Session + RestrictedAction `blog creation`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"title": string, "body": string, "excerpt": string|null, "cover_image": string|null}`
- **Response `data`:** `{"id": string, "slug": string, "status": "pending"}` (HTTP 201)

#### 32. `POST /api/v1/blogs/image`
- **Action:** `BlogController::uploadImage`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** Multipart `image` file
- **Response `data`:** `{"path": string}`

#### 33. `POST /api/v1/blogs/{slug}/vote`
- **Action:** `BlogController::vote`
- **Auth:** Required (Session + RestrictedAction `voting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"vote": integer}` (`-1|0|1`)
- **Response `data`:** `{"vote_score": int, "my_vote": int}`

#### 34. `GET /api/v1/user/blogs`
- **Action:** `BlogController::listMyBlogs`
- **Auth:** Required (Session)
- **Response `data`:** `array<BlogSummary>`

---

### 12.7 User Account, History & Preferences (11 Endpoints)

#### 35. `GET /api/v1/user/profile`
- **Action:** `UserController::profile`
- **Auth:** Viewer-Aware (returns guest payload if anonymous)
- **Response `data`:**
  - `is_guest`: `boolean`
  - `id`: `string|null`
  - `username`: `string`
  - `email`: `string|null`
  - `bio`: `string|null`
  - `profile_image`: `string|null`
  - `cover_image`: `string|null`
  - `created_at`: `string|null`

#### 36. `POST /api/v1/user/profile`
- **Action:** `UserController::updateProfile`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** `{"bio"?: string, "avatar"?: UploadedFile}`
- **Response `data`:** Updated profile object

#### 37. `GET /api/v1/profile/{person}`
- **Action:** `UserController::publicProfile`
- **Auth:** Public
- **Query:** `blog_page`, `blog_per_page`, `comment_page`, `comment_per_page`
- **Response `data`:** `{user, is_following, statistics, blogs, recent_comments}`

#### 38. `GET /api/v1/user/history`
- **Action:** `UserController::history`
- **Auth:** Required (Session)
- **Query:** `page` (`int`), `per_page` (`int`)
- **Response `data`:** `array<{content_id, title, slug, type, cover_image, chapter_id, chapter_number, read_at}>`

#### 39. `GET /api/v1/user/preferences`
- **Action:** `UserController::preferences`
- **Auth:** Required (Session)
- **Response `data`:** `{lang, theme, reader_layout, reader_font_size, reader_line_height, reader_font_weight}`

#### 40. `PUT /api/v1/user/preferences`
- **Action:** `UserController::updatePreferences`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Body:** Partial preferences object
- **Response `data`:** Updated preferences object

#### 41. `GET /api/v1/user/notifications`
- **Action:** `UserController::notifications`
- **Auth:** Required (Session)
- **Query:** `page`, `per_page`, `cursor`
- **Response `data`:** `array<NotificationDto>`
- **Meta:** `pagination` object with `next_cursor`

#### 42. `POST /api/v1/user/notifications/read`
- **Action:** `UserController::markNotificationsRead`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"updated": true}`

#### 43. `GET /api/v1/user/follows/users`
- **Action:** `UserController::followedUsers`
- **Auth:** Required (Session)
- **Response `data`:** `array<{id, username, profile_image, bio}>`

#### 44. `POST /api/v1/user/follows/{person}`
- **Action:** `UserController::follow`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"followed": true}`

#### 45. `DELETE /api/v1/user/follows/{person}`
- **Action:** `UserController::unfollow`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"followed": false}`

---

### 12.8 Wallet, Unlocks & Monetization (5 Endpoints)

#### 46. `GET /api/v1/user/wallet`
- **Action:** `UserController::wallet`
- **Auth:** Required (Session)
- **Response `data`:** `{balance_coin: int, total_purchased_coin: int, total_spent_coin: int, active_features: array<string>, updated_at: string}`

#### 47. `GET /api/v1/user/wallet/transactions`
- **Action:** `UserController::walletTransactions`
- **Auth:** Required (Session)
- **Query:** `page`, `per_page`
- **Response `data`:** `array<TransactionDto>`

#### 48. `GET /api/v1/user/unlocks/series`
- **Action:** `UserController::seriesUnlocks`
- **Auth:** Required (Session)
- **Response `data`:** `array<SeriesUnlockDto>`

#### 49. `GET /api/v1/user/unlocks/chapters`
- **Action:** `UserController::chapterUnlocks`
- **Auth:** Required (Session)
- **Response `data`:** `array<ChapterUnlockDto>`

#### 50. `GET /api/v1/user/features` & `GET /api/v1/user/features/entitlements`
- **Action:** `UserController::featureStatus` / `UserController::featureEntitlements`
- **Auth:** Required (Session)
- **Response `data`:** Active feature list & entitlement history

#### 51. `POST /api/v1/user/features/ad-free/purchase`
- **Action:** `UserController::purchaseAdFree`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response `data`:** `{"purchased": true, "expires_at": string, "balance_coin": int}`

---

## 13. Complete Admin API Endpoints Contract (43 Endpoints)

All Admin API endpoints are prefixed with `/api/v1/admin` and require:
- `Auth`: Required (Session)
- `CSRF`: Required on mutations (`X-CSRF-Token`)
- `Rate Limit`: 120 req / 300s
- `Permission`: Verified via `PermissionMiddleware`

| # | Method | Endpoint | Action | Required RBAC Permission | Request / Body | Response `data` |
|---|---|---|---|---|---|---|
| 1 | `GET` | `/overview` | `AdminPanelController::overview` | `admin.panel.access` | None | `{counts, revenue, active_users}` |
| 2 | `GET` | `/series` | `AdminPanelController::listSeries` | `admin.panel.access` | `page`, `per_page` | `array<ContentDto>` |
| 3 | `GET` | `/contents` | `AdminPanelController::listSeries` | `admin.panel.access` | `page`, `per_page` | `array<ContentDto>` |
| 4 | `GET` | `/content` | `AdminPanelController::listSeries` | `admin.panel.access` | `page`, `per_page` | `array<ContentDto>` |
| 5 | `POST` | `/content` | `AdminPanelController::createContent` | `admin.content.create` | `{title, type, ...}` | Created content object |
| 6 | `PUT` | `/content/{id}` | `AdminPanelController::updateContent` | `admin.content.update` | Partial payload | `{}` (HTTP 200) |
| 7 | `PUT` | `/contents/{id}/taxonomy` | `AdminPanelController::updateTaxonomy` | `admin.content.update` | `{genres, tags}` | `{}` |
| 8 | `POST` | `/upload-images` | `AdminPanelController::uploadImages` | `admin.content.create` | Multipart files | `{"paths": array<string>}` |
| 9 | `GET` | `/content/{id}/chapters` | `AdminPanelController::listChapters` | `admin.panel.access` | `page`, `per_page` | `array<ChapterDto>` |
| 10 | `GET` | `/chapters/{id}` | `AdminPanelController::getChapter` | `admin.panel.access` | None | Detailed chapter object |
| 11 | `POST` | `/content/{id}/chapters` | `AdminPanelController::createChapterByContentId` | `admin.chapter.create` | `{chapter_number, ...}` | Created chapter |
| 12 | `POST` | `/content/{type}/{slug}/chapters` | `AdminPanelController::createChapter` | `admin.chapter.create` | `{chapter_number, ...}` | Created chapter |
| 13 | `PUT` | `/chapters/{id}` | `AdminPanelController::updateChapter` | `admin.content.update` | Partial payload | `{}` |
| 14 | `DELETE` | `/chapters/{id}` | `AdminPanelController::deleteChapter` | `admin.content.update` | None | `{}` |
| 15 | `GET` | `/genres` | `AdminPanelController::listGenres` | `admin.panel.access` | `page`, `per_page` | `array<GenreObject>` |
| 16 | `POST` | `/series_genres` | `AdminPanelController::createGenre` | `admin.content.create` | `{name, slug}` | Created genre |
| 17 | `GET` | `/tags` | `AdminPanelController::listTags` | `admin.panel.access` | `page`, `per_page` | `array<TagObject>` |
| 18 | `POST` | `/series_tags` | `AdminPanelController::createTag` | `admin.content.create` | `{name, slug}` | Created tag |
| 19 | `GET` | `/users` | `AdminPanelController::listUsers` | `admin.panel.access` | `page`, `per_page` | `array<UserObject>` |
| 20 | `GET` | `/users/options` | `AdminPanelController::userOptions` | `admin.wallet.view` | None | `array<{id, username}>` |
| 21 | `PUT` | `/users/{id}` | `AdminPanelController::updateUser` | `admin.users.manage` | `{roles, status}` | `{}` |
| 22 | `GET` | `/uploads` | `AdminPanelController::uploads` | `admin.panel.access` | `page`, `per_page` | `array<UploadObject>` |
| 23 | `DELETE` | `/uploads/{id}` | `AdminPanelController::deleteUpload` | `admin.panel.access` | None | `{}` |
| 24 | `GET` | `/blogs` | `AdminPanelController::blogs` | `admin.panel.access` | `page`, `per_page` | `array<BlogObject>` |
| 25 | `GET` | `/blogs/pending` | `BlogController::pending` | `admin.panel.access` | `page`, `per_page` | `array<BlogObject>` |
| 26 | `POST` | `/blogs/{id}/approve` | `BlogController::approve` | `admin.blog.hide` | None | `{"approved": true}` |
| 27 | `POST` | `/blogs/{id}/hide` | `AdminPanelController::hideBlog` | `admin.blog.hide` | None | `{"hidden": true}` |
| 28 | `DELETE` | `/blogs/{id}` | `AdminPanelController::deleteBlog` | `admin.blog.hide` | None | `{"deleted": true}` |
| 29 | `GET` | `/comments` | `AdminPanelController::comments` | `admin.panel.access` | `page`, `per_page` | `array<CommentObject>` |
| 30 | `DELETE` | `/comments/{id}` | `AdminPanelController::deleteComment` | `admin.comment.delete` | None | `{"deleted": true}` |
| 31 | `GET` | `/rbac/roles` | `AdminPanelController::rbacRoles` | `admin.panel.access` | None | Role definitions |
| 32 | `GET` | `/rbac/assignments` | `AdminPanelController::rbacAssignments` | `admin.panel.access` | None | Assignments map |
| 33 | `POST` | `/rbac/permissions/assign` | `AdminPanelController::assignPermissionToRole` | `admin.permissions.grant` | `{role, permission}` | `{"assigned": true}` |
| 34 | `GET` | `/queue/jobs` | `AdminPanelController::queueJobs` | `admin.panel.access` | None | Queue jobs status |
| 35 | `POST` | `/queue/run-once` | `AdminPanelController::runQueueOnce` | `admin.jobs.run` | None | `{"ran": true}` |
| 36 | `POST` | `/retention/cleanup` | `AdminPanelController::cleanupRetention` | `admin.jobs.run` | None | `{"cleaned": true}` |
| 37 | `POST` | `/maintenance/backup` | `AdminPanelController::triggerBackup` | `admin.jobs.run` | None | `{"backup": true}` |
| 38 | `POST` | `/maintenance/sitemap` | `AdminPanelController::triggerSitemap` | `admin.jobs.run` | None | `{"sitemap": true}` |
| 39 | `POST` | `/maintenance/warmup` | `AdminPanelController::triggerCacheWarmup` | `admin.jobs.run` | None | `{"warmed": true}` |
| 40 | `POST` | `/maintenance/analytics` | `AdminPanelController::triggerAnalytics` | `admin.jobs.run` | None | `{"aggregated": true}` |
| 41 | `GET` | `/shop/packages` | `AdminPanelController::shopPackages` | `admin.shop.manage` | None | Packages list |
| 42 | `POST` | `/shop/packages` | `AdminPanelController::createShopPackage` | `admin.shop.manage` | `{name, coin_amount, ...}` | Created package |
| 43 | `PUT` | `/shop/packages/{id}` | `AdminPanelController::updateShopPackage` | `admin.shop.manage` | Partial payload | `{}` |

---

## 14. Canonical Reader Contract

1. **Authorization Authority:** `WalletService::chapterAccess($contentId, $chapterId, $userId)` is the single business authority.
2. **Access Evaluation:**
   - Free Chapter → `access.granted = true`, `access.reason = "free"`.
   - Series Unlocked → `access.granted = true`, `access.reason = "series_unlocked"`.
   - Chapter Unlocked → `access.granted = true`, `access.reason = "chapter_unlocked"`.
   - Author / Admin → `access.granted = true`, `access.reason = "admin"|"author"`.
   - Locked → `access.granted = false`, `access.reason = "locked"`.
3. **Payload Locking:**
   - If `access.granted == false`: `body = null` and `pages = []`.
   - If `access.granted == true`: `body` contains text (for novel) or `pages` contains ordered image array (for manga).
4. **Series Identity:** Response MUST always contain `series_title`, `series_slug`, `series_type`.
5. **Reading History:** If authenticated AND `access.granted == true`, marks chapter read in database.

---

## 15. Canonical Search Contract

The search endpoint `GET /api/v1/search` MUST accept and process:
```
GET /api/v1/search?q={query}&genres={slug1,slug2}&tags={tag1,tag2}&status={status}&sort={sort}&page={page}&per_page={per_page}
```
- `q` (`string`, required): Search string (matched against title, alternative titles, author, artist).
- `genres` (`string`, optional): Comma-separated genre slugs.
- `tags` (`string`, optional): Comma-separated tag slugs.
- `status` (`string`, optional): One of `ongoing`, `completed`, `hiatus`, `cancelled`.
- `sort` (`string`, optional): One of `popular` (default), `latest`, `rating`, `title`, `views`.
- `page` (`integer`, optional, default: 1).
- `per_page` (`integer`, optional, default: 20, max: 50).

---

## 16. Canonical Wallet & Monetization Contract

1. **Balance Invariant:** All coin transactions (`credit`, `debit`, `unlock`) execute within database transactions with row-level locks (`SELECT ... FOR UPDATE`).
2. **Double-Purchase Prevention:** Unlocking an already unlocked chapter or series returns `{unlocked: true, cost_coin: 0}` without deducting balance.
3. **Idempotency:** Unique composite keys in `user_chapter_unlocks` (`user_id`, `chapter_id`) prevent duplicate debits.

---

## 17. Media Contract

- Media URLs in API payloads are relative paths (e.g. `/uploads/cover.img001.webp`).
- Direct filesystem path concatenation from user input is strictly prohibited.
- Files are validated for MIME type (`finfo`), re-encoded, and assigned deterministic IDs (`{prefix}.{imageId}.{ext}`).

---

## 18. Security Contract Summary

- **Authentication Bypass:** All private endpoints check valid session.
- **CSRF:** All private mutations require valid `X-CSRF-Token`.
- **IDOR Protection:** All user data endpoints (history, follows, wallet, unlocks, notifications, preferences) extract user ID from session context, NEVER from user-supplied query/body parameters.
- **SQL Injection:** 100% prepared statements with parameterized queries.
- **XSS:** Output sanitization on client; raw HTML stripped from comments and blog submissions.

---

## 19. Implementation Gaps (Action Items for Aşama 5)

The following gaps exist between current code and this canonical contract:

### P0 (Critical Security / Authorization)
- **GAP-01:** Legacy SSR reader bypasses `WalletService::chapterAccess()`. (To be resolved by retiring SSR / routing through `ChapterService`).

### P1 (High Functional Discrepancies)
- **GAP-02:** `ContentController::search` ignores `genres`, `tags`, `status`, `sort` query parameters.
- **GAP-03:** `ContentController::chaptersByType` does not fall back to `$_SESSION['user_id']`, treating logged-in session users as guests.
- **GAP-04:** `ChapterService::getByTypeSlugAndNumber` omits `series_title`, `series_slug`, `series_type` in its response.
- **GAP-05:** SSR reader does not write reading history on chapter access.

### P2 (Medium Standardization)
- **GAP-06:** Standardize `meta.pagination` across all collection endpoints.
- **GAP-07:** Standardize validation error response structure (`error.fields`).

---

## 20. Endpoint Coverage Verification

| Category | Defined in Contract | Actual in Codebase | Coverage |
|---|---|---|---|
| **Public & User Endpoints** | 51 | 51 | **100% (51/51)** |
| **Admin Panel Endpoints** | 43 | 43 | **100% (43/43)** |
| **Total Endpoints** | **94** | **94** | **100% (94/94)** |
