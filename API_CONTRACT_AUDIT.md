# API Contract Audit & Endpoint Inventory

**Repository:** `mhmtsnmzkanly/nm-reader`  
**Date:** 2026-08-14  
**Scope:** Complete inventory of all API endpoints, request/response models, field data types, nullability, pagination models, error codes, authentication/authorization requirements, CSRF requirements, side effects, and SSR vs API discrepancies.

---

## 1. Global API Conventions & Protocol

### 1.1 Response Envelope Structure

All standard API endpoints return JSON conforming to the following envelopes:

#### Success Envelope (`ResponseHelper::success` / `ResponseHelper::created`)
```json
{
  "status": "success",
  "data": {},
  "meta": {},
  "error": null
}
```
*HTTP Status Codes:* `200 OK`, `201 Created`

#### Error Envelope (`ResponseHelper::error`)
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
*Standard Error Codes & Keys:*
- `400 Bad Request` (`BAD_REQUEST`): Validation error, invalid parameters.
- `401 Unauthorized` (`UNAUTHORIZED`): Missing or invalid session/auth credentials.
- `402 Payment Required` (`PAYMENT_REQUIRED`): Insufficient coin balance.
- `403 Forbidden` (`FORBIDDEN`): Insufficient RBAC permissions or restricted action.
- `404 Not Found` (`NOT_FOUND`): Target resource does not exist.
- `409 Conflict` (`CONFLICT`): Duplicate resource (e.g. email exists, already approved).
- `419 CSRF Invalid` (`CSRF_INVALID`): Missing or mismatched CSRF token on mutation.
- `422 Unprocessable Entity` (`UNPROCESSABLE_ENTITY`): Semantic payload validation failure.
- `429 Rate Limited` (`RATE_LIMITED`): Request threshold exceeded.
- `500 Internal Error` (`INTERNAL_ERROR`): Unhandled server exception.

---

## 2. Public & User API Endpoints Inventory

### 2.1 Content & Discovery Domain

#### `GET /api/v1/home`
- **Action:** `ContentController::home`
- **Auth:** None (Public)
- **CSRF:** None
- **Query Params:**
  - `page` (`integer`, optional, default: 1, min: 1)
  - `per_page` (`integer`, optional, default: 20, min: 1, max: 50)
- **Response Data (`object`):**
  - `explore` (`array<ContentDto>`): Featured content items
  - `recent_chapters` (`array<object>`): Latest chapter updates with series metadata
  - `recently_added` (`array<ContentDto>`): Newly added series
  - `popular_blogs` (`array<object>`): Top approved blogs
  - `latest_blogs` (`array<object>`): Latest approved blogs
- **Meta:** `{"page": int, "per_page": int}`
- **Side Effects:** Tracks `home_view` analytics.
- **Service / Repo:** `SeriesService::home()` → `SeriesRepository`, `BlogRepository`
- **SSR vs API Discrepancy:** SSR renders fixed 20 items; API allows 1–50 per page. SSR includes pre-computed Turkish date strings.

---

#### `GET /api/v1/content/type/{type}`
- **Action:** `ContentController::byType`
- **Route Constraints:** `type`: `light-novel|web-novel|novel|manga|manhua|manhwa|webtoon`
- **Auth:** None (Public)
- **CSRF:** None
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<ContentDto>`
- **Meta:** `{"page": int, "per_page": int}`
- **Errors:** `400 BAD_REQUEST` (invalid type)
- **Service / Repo:** `SeriesService::byType()` → `SeriesRepository::findByType()`

---

#### `GET /api/v1/content/{type}/{slug}`
- **Action:** `ContentController::contentByType`
- **Auth:** Optional (uses `$_SESSION['user_id']` if present)
- **CSRF:** None
- **Response Data (`object`):**
  - `id` (`string`, 6-char alphanumeric)
  - `title` (`string`)
  - `slug` (`string`)
  - `type` (`string`)
  - `status` (`string`: `ongoing|completed|hiatus|cancelled`)
  - `description` (`string|null`)
  - `author` (`string|null`)
  - `artist` (`string|null`)
  - `cover_image` (`string|null`)
  - `accent_color` (`string|null`)
  - `release_year` (`string|null`)
  - `country` (`string|null`)
  - `alternative_titles` (`string|null`)
  - `rating_avg` (`float`)
  - `rating_count` (`integer`)
  - `chapter_count` (`integer`)
  - `comment_count` (`integer`)
  - `series_genres` (`array<{id: int, name: string, slug: string}>`)
  - `series_tags` (`array<{id: int, name: string, slug: string}>`)
  - `type_path` (`string`)
  - `url_path` (`string`)
  - `is_followed` (`boolean`) [authenticated only]
  - `reading_progress` (`object|null`) [authenticated only]
  - `series_unlock_price` (`integer`)
  - `is_series_unlocked` (`boolean`) [authenticated only]
  - `has_any_premium` (`boolean`)
- **Errors:** `400 BAD_REQUEST`, `404 NOT_FOUND`
- **Service / Repo:** `SeriesService::contentDetailByType()` → `SeriesRepository`, `WalletRepository`
- **SSR vs API Discrepancy:** SSR embeds 200 initial chapters and generates JSON-LD/SEO metadata; API supplies detail DTO only.

---

#### `GET /api/v1/content/{type}/{slug}/chapters`
- **Action:** `ContentController::chaptersByType`
- **Auth:** Optional (reads `request->getAttribute('user_id')`)
- **CSRF:** None
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<ChapterDto>`
  - `id` (`string`, 6-char)
  - `content_id` (`string`, 6-char)
  - `chapter_number` (`string`)
  - `title` (`string|null`)
  - `type` (`string`)
  - `created_at` (`string`, ISO 8601 / datetime)
  - `is_locked` (`boolean`)
  - `price_coin` (`integer`)
  - `is_unlocked` (`boolean`)
- **Meta:** `{"page": int, "per_page": int}`
- **Service / Repo:** `SeriesService::chaptersByType()` → `ChapterRepository`
- **SSR vs API Discrepancy:** In public API, session auth was not populated into `request->getAttribute('user_id')`, returning guest unlock status even if user was logged in via session.

---

#### `GET /api/v1/latest-chapters`
- **Action:** `ContentController::latestChapters`
- **Auth:** None (Public)
- **CSRF:** None
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<object>` (chapter + content join)
- **Meta:** `{"page": int, "per_page": int}`
- **Service / Repo:** `SeriesService::latestChapters()` → `ChapterRepository`

---

#### `GET /api/v1/content/{type}/chapters`
- **Action:** `ContentController::latestChaptersByType`
- **Auth:** None (Public)
- **CSRF:** None
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<object>`
- **Meta:** `{"page": int, "per_page": int}`
- **Service / Repo:** `SeriesService::latestChaptersByType()` → `ChapterRepository`

---

#### `GET /api/v1/genres` and `GET /api/v1/series_genres`
- **Action:** `ContentController::genres`
- **Auth:** None (Public)
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<{id: int, name: string, slug: string, description: string|null, series_count: int}>`
- **Meta:** `{"page": int, "per_page": int}`

---

#### `GET /api/v1/tags` and `GET /api/v1/series_tags`
- **Action:** `ContentController::tags`
- **Auth:** None (Public)
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<{id: int, name: string, slug: string, series_count: int}>`
- **Meta:** `{"page": int, "per_page": int}`

---

#### `GET /api/v1/genre/{slug}` and `GET /api/v1/tag/{slug}`
- **Action:** `ContentController::genre` / `ContentController::tag`
- **Auth:** None (Public)
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<ContentDto>`
- **Meta:** `{"page": int, "per_page": int}`

---

### 2.2 Search Domain

#### `GET /api/v1/search`
- **Action:** `ContentController::search`
- **Auth:** None (Public)
- **CSRF:** None
- **Query Params:**
  - `q` (`string`, required)
  - `page` (`integer`, optional, default: 1)
  - `per_page` (`integer`, optional, default: 20, max: 50)
  - *Missing Filters in current API:* `genres` (`string`), `tags` (`string`), `status` (`string`), `sort` (`string`)
- **Response Data:** `array<ContentDto>`
- **Meta:** `{"q": string, "page": int, "per_page": int}`
- **Side Effects:** Logs query in search analytics.
- **SSR vs API Discrepancy:** SSR passes `genres`, `tags`, `status`, `sort` to `SeriesService::search()`. API currently drops all parameters except `q`.

---

#### `GET /api/v1/search/suggest`
- **Action:** `ContentController::suggest`
- **Auth:** None (Public)
- **Query Params:** `q` (`string`, min 2 chars)
- **Response Data:** `array<{title: string, slug: string, type: string, cover_image: string|null}>`

---

### 2.3 Reader & Chapter Domain

#### `GET /api/v1/content/{type}/{slug}/chapter/{chapterNumber}`
- **Action:** `ContentController::chapterDetail`
- **Auth:** Optional (checks `$_SESSION['user_id']`)
- **CSRF:** None
- **Path Params:**
  - `type` (`string`)
  - `slug` (`string`)
  - `chapterNumber` (`string`, float-compatible e.g. "1", "1.5")
- **Response Data (`object`):**
  - `id` (`string`, 6-char alphanumeric)
  - `content_id` (`string`, 6-char alphanumeric)
  - `chapter_number` (`string`)
  - `title` (`string|null`)
  - `type` (`string`: `novel` or `manga`)
  - `created_at` (`string`)
  - `price_coin` (`integer`)
  - `is_locked` (`boolean`)
  - `body` (`string|null`): Present ONLY if `access.granted == true` and type is novel; `null` if locked.
  - `pages` (`array<{image_path: string, page_order: int}>`): Present ONLY if `access.granted == true` and type is manga; `[]` if locked.
  - `access` (`object`):
    - `granted` (`boolean`)
    - `reason` (`string`: `free|series_unlocked|chapter_unlocked|admin|author`)
    - `price_coin` (`integer`)
    - `balance_coin` (`integer`)
    - `can_unlock` (`boolean`)
  - `adjacent_chapters` (`object`):
    - `prev` (`string|null`)
    - `next` (`string|null`)
- **Side Effects:** If user is authenticated AND `access.granted === true`, marks chapter as read (`reading_history`).
- **SSR vs API Discrepancy (CRITICAL P0):** SSR chapter route bypasses `WalletService::chapterAccess()`, loading text/images unconditionally without checking coin lock. API enforces access control and returns `body: null` / `pages: []` when locked.

---

### 2.4 User Library, Follows & Ratings

#### `POST /api/v1/content/{type}/{slug}/follow`
- **Action:** `ContentController::followByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"followed": true}`

#### `DELETE /api/v1/content/{type}/{slug}/follow`
- **Action:** `ContentController::unfollowByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"followed": false}`

#### `GET /api/v1/user/follows`
- **Action:** `ContentController::followed`
- **Auth:** Required (Session)
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<ContentDto>`
- **Meta:** `{"page": int, "per_page": int}`

#### `POST /api/v1/content/{type}/{slug}/rate`
- **Action:** `UserInteractionController::rateByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `{"rating": integer}` (1 to 5)
- **Response Data:** `{"rated": true}`

---

### 2.5 Comments Domain

#### `GET /api/v1/chapter/{chapterId}/comments`
- **Action:** `UserInteractionController::listChapterComments`
- **Auth:** Optional (checks viewer vote if logged in)
- **Query Params:** `page` (`int`), `per_page` (`int`), `cursor` (`string|null`)
- **Response Data:** `array<CommentObject>`
  - `id` (`integer`)
  - `user_id` (`string`)
  - `username` (`string`)
  - `avatar` (`string|null`)
  - `body` (`string`)
  - `parent_id` (`integer|null`)
  - `score` (`integer`)
  - `upvotes` (`integer`)
  - `downvotes` (`integer`)
  - `my_vote` (`integer`: `-1|0|1`)
  - `created_at` (`string`)
  - `replies` (`array<CommentObject>`)
- **Meta:** `{"page": int, "per_page": int, "next_cursor": string|null}`

#### `GET /api/v1/content/{type}/{slug}/comments`
- **Action:** `UserInteractionController::listSeriesComments`
- **Same structure as chapter comments.**

#### `GET /api/v1/blogs/{slug}/comments`
- **Action:** `UserInteractionController::listBlogComments`
- **Same structure as chapter comments.**

#### `POST /api/v1/chapter/{chapterId}/comment`
- **Action:** `UserInteractionController::createChapterComment`
- **Auth:** Required (Session + RestrictedActionMiddleware `commenting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `{"body": string, "parent_id": integer|null}`
- **Response Data:** `{"comment_id": integer}` (HTTP 201)

#### `POST /api/v1/content/{type}/{slug}/comment`
- **Action:** `UserInteractionController::createSeriesComment`
- **Auth:** Required (Session + RestrictedActionMiddleware `commenting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `{"body": string, "parent_id": integer|null}`
- **Response Data:** `{"comment_id": integer}` (HTTP 201)

#### `POST /api/v1/comments/{commentId}/vote`
- **Action:** `UserInteractionController::voteComment`
- **Auth:** Required (Session + RestrictedActionMiddleware `voting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `{"vote": integer}` (`-1|0|1`)
- **Response Data:** `{"score": int, "upvotes": int, "downvotes": int, "my_vote": int}`

---

### 2.6 Blog Platform Domain

#### `GET /api/v1/blogs`
- **Action:** `BlogController::list`
- **Auth:** None (Public)
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<BlogObject>`
  - `id` (`string`, 6-char)
  - `title` (`string`)
  - `slug` (`string`)
  - `excerpt` (`string`)
  - `cover_image` (`string|null`)
  - `author_id` (`string`)
  - `author_username` (`string`)
  - `author_avatar` (`string|null`)
  - `views_count` (`integer`)
  - `vote_score` (`integer`)
  - `comment_count` (`integer`)
  - `created_at` (`string`)
- **Meta:** `{"page": int, "per_page": int}`

#### `GET /api/v1/blogs/{slug}`
- **Action:** `BlogController::show`
- **Auth:** Optional (AuthMiddleware optional: true)
- **Response Data (`object`):**
  - Full blog post fields plus `body` (`string`), `my_vote` (`integer`: `-1|0|1`).
- **SSR vs API Discrepancy:** SSR always passes `userId = null` to repository, so `my_vote` is always 0 in SSR; API includes authenticated user's vote.

#### `POST /api/v1/blogs`
- **Action:** `BlogController::create`
- **Auth:** Required (Session + RestrictedActionMiddleware `blog creation`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `{"title": string, "body": string, "excerpt": string|null, "cover_image": string|null}`
- **Response Data:** `{"id": string, "slug": string, "status": "pending"}` (HTTP 201)

#### `POST /api/v1/blogs/image`
- **Action:** `BlogController::uploadImage`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `multipart/form-data` with `image` file
- **Response Data:** `{"path": string}`

#### `POST /api/v1/blogs/{slug}/vote`
- **Action:** `BlogController::vote`
- **Auth:** Required (Session + RestrictedActionMiddleware `voting`)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** `{"vote": integer}` (`-1|0|1`)
- **Response Data:** `{"vote_score": int, "my_vote": int}`

---

### 2.7 User Account, Profile & Activity Domain

#### `GET /api/v1/user/profile`
- **Action:** `UserController::profile`
- **Auth:** Optional (Returns guest payload if anonymous)
- **Response Data (`object`):**
  - `is_guest` (`boolean`)
  - `id` (`string|null`)
  - `username` (`string`)
  - `email` (`string|null`)
  - `bio` (`string|null`)
  - `profile_image` (`string|null`)
  - `cover_image` (`string|null`)
  - `created_at` (`string|null`)

#### `POST /api/v1/user/profile`
- **Action:** `UserController::updateProfile`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** JSON or multipart form: `bio` (`string`), `avatar` (file upload)
- **Response Data:** Updated profile object

#### `GET /api/v1/profile/{person}`
- **Action:** `UserController::publicProfile`
- **Auth:** None (Public)
- **Path Params:** `person` (username or 8-character user ID)
- **Query Params:** `blog_page`, `blog_per_page`, `comment_page`, `comment_per_page`
- **Response Data (`object`):**
  - `user`: `{id, username, bio, profile_image, cover_image, created_at}`
  - `is_following` (`boolean`)
  - `statistics`: `{blogs_count, comments_count, reputation_score}`
  - `blogs`: `array<BlogObject>`
  - `recent_comments`: `array<CommentObject>`

#### `GET /api/v1/user/history`
- **Action:** `UserController::history`
- **Auth:** Required (Session)
- **Query Params:** `page` (`int`), `per_page` (`int`)
- **Response Data:** `array<{content_id, title, slug, type, cover_image, chapter_id, chapter_number, read_at}>`
- **Meta:** `{"page": int, "per_page": int}`

#### `GET /api/v1/user/preferences`
- **Action:** `UserController::preferences`
- **Auth:** Required (Session)
- **Response Data (`object`):**
  - `lang` (`string`)
  - `theme` (`string`)
  - `reader_layout` (`string`: `vertical|horizontal|webtoon`)
  - `reader_font_size` (`string` or `int`)
  - `reader_line_height` (`string` or `float`)
  - `reader_font_weight` (`string` or `int`)

#### `PUT /api/v1/user/preferences`
- **Action:** `UserController::updatePreferences`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Request Body:** Partial or full preferences object
- **Response Data:** Updated preferences object

#### `GET /api/v1/user/notifications`
- **Action:** `UserController::notifications`
- **Auth:** Required (Session)
- **Query Params:** `page`, `per_page`, `cursor`
- **Response Data:** `array<{id, type, title, body, is_read, actor_username, data, created_at}>`
- **Meta:** `{"page": int, "per_page": int, "next_cursor": string|null}`

#### `POST /api/v1/user/notifications/read`
- **Action:** `UserController::markNotificationsRead`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"updated": true}`

#### `GET /api/v1/user/follows/users`
- **Action:** `UserController::followedUsers`
- **Auth:** Required (Session)
- **Response Data:** `array<{id, username, profile_image, bio}>`

#### `POST /api/v1/user/follows/{person}` and `DELETE /api/v1/user/follows/{person}`
- **Action:** `UserController::follow` / `UserController::unfollow`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"followed": true|false}`

#### `POST /api/v1/user/activity`
- **Action:** `UserInteractionController::trackActivity`
- **Auth:** Required (Session)
- **Request Body:** `{"tab_id": string, "duration": integer}`
- **Response Data:** `{"tracked": true}`

---

### 2.8 Wallet, Unlocks & Shop Domain

#### `GET /api/v1/user/wallet`
- **Action:** `UserController::wallet`
- **Auth:** Required (Session)
- **Response Data (`object`):**
  - `balance_coin` (`integer`)
  - `total_purchased_coin` (`integer`)
  - `total_spent_coin` (`integer`)
  - `active_features` (`array<string>`)
  - `updated_at` (`string`)

#### `GET /api/v1/user/wallet/transactions`
- **Action:** `UserController::walletTransactions`
- **Auth:** Required (Session)
- **Query Params:** `page`, `per_page`
- **Response Data:** `array<{id, type, amount_coin, balance_after, description, reference_id, created_at}>`
- **Meta:** `{"page": int, "per_page": int, "total": int, "total_pages": int}`

#### `GET /api/v1/user/unlocks/series`
- **Action:** `UserController::seriesUnlocks`
- **Auth:** Required (Session)
- **Response Data:** `array<{content_id, title, slug, type, cover_image, unlocked_at, price_coin}>`

#### `GET /api/v1/user/unlocks/chapters`
- **Action:** `UserController::chapterUnlocks`
- **Auth:** Required (Session)
- **Response Data:** `array<{chapter_id, chapter_number, content_id, content_title, content_slug, unlocked_at, price_coin}>`

#### `POST /api/v1/content/{type}/{slug}/unlock`
- **Action:** `ContentController::unlockByType`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"unlocked": true, "content_id": string, "balance_coin": integer, "cost_coin": integer}`
- **Errors:** `402 PAYMENT_REQUIRED` (insufficient balance), `404 NOT_FOUND`, `400 BAD_REQUEST`

#### `POST /api/v1/chapter/{chapterId}/unlock`
- **Action:** `ContentController::unlockChapter`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"unlocked": true, "chapter_id": string, "balance_coin": integer, "cost_coin": integer}`
- **Errors:** `402 PAYMENT_REQUIRED`, `404 NOT_FOUND`

#### `GET /api/v1/user/features` and `GET /api/v1/user/features/entitlements`
- **Action:** `UserController::featureStatus` / `UserController::featureEntitlements`
- **Auth:** Required (Session)
- **Response Data:** Active feature list / entitlement history

#### `POST /api/v1/user/features/ad-free/purchase`
- **Action:** `UserController::purchaseAdFree`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"purchased": true, "expires_at": string, "balance_coin": integer}`

#### `GET /api/v1/shop/packages`
- **Action:** `ContentController::shopPackages`
- **Auth:** None (Public)
- **Response Data:** `array<{id, name, coin_amount, bonus_coin, total_coin, price_cents, currency}>`

#### `GET /api/v1/shop/features`
- **Action:** `ContentController::shopFeatures`
- **Auth:** None (Public)
- **Response Data:** `array<{code, name, description, duration_days, price_coin, is_active}>`

---

### 2.9 Authentication & Session Management Domain

#### `POST /api/v1/auth/register`
- **Action:** `AuthController::register`
- **Rate Limit:** 3 requests per 10 minutes per email
- **Auth:** None (Public)
- **Request Body:** `{"username": string, "email": string, "password": string, "turnstile_token"?: string}`
- **Response Data:** `{"id": string, "username": string, "email": string}` (HTTP 201)
- **Note:** Registration does NOT auto-login.

#### `POST /api/v1/auth/login`
- **Action:** `AuthController::login`
- **Rate Limit:** 10 requests per 1 minute per email
- **Auth:** None (Public)
- **Request Body:** `{"email": string, "password": string, "remember"?: boolean, "turnstile_token"?: string}`
- **Response Data (`object`):**
  - `id` (`string`, 8-char)
  - `username` (`string`)
  - `email` (`string`)
  - `csrf_token` (`string`)
  - `refresh_token` (`string|null`)
  - `api_token` (`string`)
  - `roles` (`array<string>`)
  - `permissions` (`array<string>`)
  - `session_key` (`string`)
- **Side Effects:** Sets `nm_remember` cookie when `remember: true`.

#### `POST /api/v1/auth/refresh`
- **Action:** `AuthController::refresh`
- **Rate Limit:** 20 requests per 1 minute
- **Auth:** None (Public token-based)
- **Request Body:** `{"refresh_token": string}`
- **Response Data:** Rotated tokens and new `csrf_token`.

#### `POST /api/v1/auth/logout` and `GET /api/v1/auth/logout`
- **Action:** `AuthController::logout`
- **Auth:** None (Cleans session if active)
- **Response Data:** `{"logged_out": true}`
- **Side Effects:** Destroys PHP session, clears `nm_remember` cookie.

#### `GET /api/v1/auth/sessions`
- **Action:** `AuthController::sessions`
- **Auth:** Required (Session)
- **Response Data:** `array<{session_key, ip_address, user_agent, last_active_at, is_current}>`

#### `DELETE /api/v1/auth/sessions/{sessionKey}`
- **Action:** `AuthController::revokeSession`
- **Auth:** Required (Session)
- **CSRF:** Required (`X-CSRF-Token`)
- **Response Data:** `{"revoked": true}`

---

### 2.10 Utility & Frontend Support API

#### `GET /api/v1/i18n/{lang}`
- **Action:** `WebController::i18nJson`
- **Auth:** None (Public)
- **Path Params:** `lang` (`tr|en`)
- **Response Data (`raw object`):** `{"hash": string, "lang": string, "data": { ... }}`

#### `POST /api/v1/log/error`
- **Action:** `WebController::logError`
- **Auth:** None (Public)
- **Request Body:** `{"message": string, "url"?: string, "stack"?: string, "context"?: object}`
- **Response Data:** `{"logged": true}`

---

## 3. Admin API Endpoints Inventory (`/api/v1/admin/*`)

All Admin endpoints enforce:
1. `RateLimitMiddleware` (120 req / 300s)
2. `CsrfMiddleware` (on mutations)
3. `AuthMiddleware` (Session required)
4. Specific `PermissionMiddleware` check

| Method | Endpoint | Action | Required Permission |
|---|---|---|---|
| `GET` | `/overview` | `AdminPanelController::overview` | `admin.panel.access` |
| `GET` | `/series` / `/contents` / `/content` | `AdminPanelController::listSeries` | `admin.panel.access` |
| `POST` | `/content` | `AdminPanelController::createContent` | `admin.content.create` |
| `PUT` | `/content/{id}` | `AdminPanelController::updateContent` | `admin.content.update` |
| `PUT` | `/contents/{id}/taxonomy` | `AdminPanelController::updateTaxonomy` | `admin.content.update` |
| `POST` | `/upload-images` | `AdminPanelController::uploadImages` | `admin.content.create` |
| `GET` | `/content/{id}/chapters` | `AdminPanelController::listChapters` | `admin.panel.access` |
| `GET` | `/chapters/{id}` | `AdminPanelController::getChapter` | `admin.panel.access` |
| `POST` | `/content/{id}/chapters` | `AdminPanelController::createChapterByContentId` | `admin.chapter.create` |
| `POST` | `/content/{type}/{slug}/chapters` | `AdminPanelController::createChapter` | `admin.chapter.create` |
| `PUT` | `/chapters/{id}` | `AdminPanelController::updateChapter` | `admin.content.update` |
| `DELETE` | `/chapters/{id}` | `AdminPanelController::deleteChapter` | `admin.content.update` |
| `GET` | `/genres` | `AdminPanelController::listGenres` | `admin.panel.access` |
| `POST` | `/series_genres` | `AdminPanelController::createGenre` | `admin.content.create` |
| `GET` | `/tags` | `AdminPanelController::listTags` | `admin.panel.access` |
| `POST` | `/series_tags` | `AdminPanelController::createTag` | `admin.content.create` |
| `GET` | `/users` | `AdminPanelController::listUsers` | `admin.panel.access` |
| `GET` | `/users/options` | `AdminPanelController::userOptions` | `admin.wallet.view` |
| `PUT` | `/users/{id}` | `AdminPanelController::updateUser` | `admin.users.manage` |
| `GET` | `/uploads` | `AdminPanelController::uploads` | `admin.panel.access` |
| `DELETE` | `/uploads/{id}` | `AdminPanelController::deleteUpload` | `admin.panel.access` |
| `GET` | `/blogs` | `AdminPanelController::blogs` | `admin.panel.access` |
| `GET` | `/blogs/pending` | `BlogController::pending` | `admin.panel.access` |
| `POST` | `/blogs/{id}/approve` | `BlogController::approve` | `admin.blog.hide` |
| `POST` | `/blogs/{id}/hide` | `AdminPanelController::hideBlog` | `admin.blog.hide` |
| `DELETE` | `/blogs/{id}` | `AdminPanelController::deleteBlog` | `admin.blog.hide` |
| `GET` | `/comments` | `AdminPanelController::comments` | `admin.panel.access` |
| `DELETE` | `/comments/{id}` | `AdminPanelController::deleteComment` | `admin.comment.delete` |
| `GET` | `/rbac/roles` | `AdminPanelController::rbacRoles` | `admin.panel.access` |
| `GET` | `/rbac/assignments` | `AdminPanelController::rbacAssignments` | `admin.panel.access` |
| `POST` | `/rbac/permissions/assign` | `AdminPanelController::assignPermissionToRole` | `admin.permissions.grant` |
| `GET` | `/queue/jobs` | `AdminPanelController::queueJobs` | `admin.panel.access` |
| `POST` | `/queue/run-once` | `AdminPanelController::runQueueOnce` | `admin.jobs.run` |
| `POST` | `/retention/cleanup` | `AdminPanelController::cleanupRetention` | `admin.jobs.run` |
| `POST` | `/maintenance/backup` | `AdminPanelController::triggerBackup` | `admin.jobs.run` |
| `POST` | `/maintenance/sitemap` | `AdminPanelController::triggerSitemap` | `admin.jobs.run` |
| `POST` | `/maintenance/warmup` | `AdminPanelController::triggerCacheWarmup` | `admin.jobs.run` |
| `POST` | `/maintenance/analytics` | `AdminPanelController::triggerAnalytics` | `admin.jobs.run` |
| `GET` | `/shop/packages` | `AdminPanelController::shopPackages` | `admin.shop.manage` |
| `POST` | `/shop/packages` | `AdminPanelController::createShopPackage` | `admin.shop.manage` |
| `PUT` | `/shop/packages/{id}` | `AdminPanelController::updateShopPackage` | `admin.shop.manage` |
| `POST` | `/wallets/{userId}/grant-package` | `AdminPanelController::grantShopPackage` | `admin.wallet.manage` |
| `POST` | `/wallets/{userId}/credit` | `AdminPanelController::creditWallet` | `admin.wallet.manage` |
| `POST` | `/wallets/{userId}/debit` | `AdminPanelController::debitWallet` | `admin.wallet.manage` |
| `GET` | `/wallets/{userId}` | `AdminPanelController::walletSummary` | `admin.wallet.view` |
| `GET` | `/wallets/{userId}/transactions` | `AdminPanelController::walletTransactions` | `admin.wallet.view` |
| `PUT` | `/series/{id}/pricing` | `AdminPanelController::updateSeriesPricing` | `admin.shop.manage` |
| `PUT` | `/chapters/{id}/pricing` | `AdminPanelController::updateChapterPricing` | `admin.shop.manage` |
| `GET` | `/features` | `AdminPanelController::featureProducts` | `admin.shop.manage` |
| `PUT` | `/features/ad-free` | `AdminPanelController::configureAdFree` | `admin.shop.manage` |
| `GET` | `/maintenance/env` | `AdminPanelController::getEnvConfig` | `admin.panel.access` |
| `POST` | `/maintenance/env` | `AdminPanelController::saveEnvConfig` | `admin.panel.access` |
| `GET` | `/audit-logs` | `AdminPanelController::auditLogs` | `admin.logs.view` |
| `GET` | `/login-events` | `AdminPanelController::loginEvents` | `admin.logs.view` |
| `GET` | `/moderation-actions` | `AdminPanelController::moderationActions` | `admin.logs.view` |
| `POST` | `/moderation-actions` | `AdminPanelController::createModerationAction` | `admin.logs.view` |
| `GET` | `/logs/access` | `AdminPanelController::systemAccessLogs` | `admin.logs.view` |
| `GET` | `/logs/error` | `AdminPanelController::systemErrorLogs` | `admin.logs.view` |
| `GET` | `/stats/visits` | `AdminPanelController::siteVisits` | `admin.metrics.view` |
| `GET` | `/stats/views` | `AdminPanelController::viewStats` | `admin.metrics.view` |
| `GET` | `/stats/blogs` | `AdminPanelController::blogStats` | `admin.metrics.view` |
| `GET` | `/stats/reputation` | `AdminPanelController::userReputation` | `admin.metrics.view` |
| `GET` | `/metrics` / `/dashboard` | `AdminPanelController::metricsSnapshot` | `admin.metrics.view` |
| `GET` | `/metrics/insights` | `AdminPanelController::metricsInsights` | `admin.metrics.view` |

---

## 4. SSR vs API Discrepancy Matrix

| Domain / Feature | SSR Behavior | API Behavior | Severity | Root Cause & Resolution Plan |
|---|---|---|---|---|
| **Reader Premium Access** | Calls `SeriesService` directly without checking `WalletService::chapterAccess()`. Delivers full text / images even if locked. | Calls `ChapterService` with `WalletService::chapterAccess()`. Returns `body: null` and `pages: []` when locked. | **P0 (Critical)** | Unify reader access: SSR must route through the same canonical business rule in `ChapterService` or be replaced by API. |
| **Reading History** | SSR chapter rendering does not call `markRead`. | API calls `ChapterService::markRead()` when chapter is unlocked and user is logged in. | **P1 (High)** | Mark read is canonical side effect of accessing an unlocked chapter. |
| **Search Filters** | SSR processes `q`, `genres`, `tags`, `status`, `sort`. | `GET /api/v1/search` only reads `q`, ignoring all filter parameters. | **P1 (High)** | Add `genres`, `tags`, `status`, `sort` query parameter parsing and forwarding in `ContentController::search`. |
| **Chapter List Auth** | SSR evaluates unlocked status from session. | API reads `request->getAttribute('user_id')` on a public route, which is `null` without Bearer token, causing logged-in session users to see locked state. | **P1 (High)** | Fall back to `$_SESSION['user_id']` in `ContentController::chaptersByType`. |
| **Reader Series Identity** | SSR template includes series title, type, and slug. | `ChapterService::getByTypeSlugAndNumber` omits `series_title`, `series_slug`, `series_type`. | **P1 (High)** | Include series identity metadata in chapter detail response. |
| **Blog Detail Viewer Vote** | SSR always queries repository with `userId = null` (`my_vote = 0`). | API passes authenticated user ID to supply `my_vote`. | **P2 (Medium)** | Canonical API behavior is correct (viewer-aware). |
| **Pagination Metadata** | SSR uses fixed page sizes (20 for home, 50 for list, 200 for chapters). | API returns structured `meta` with configurable `page` and `per_page`. | **P2 (Medium)** | Standardize pagination envelope across all collection endpoints. |
| **Field Types & Coercion** | Number strings formatted with Turkish locale in HTML. | Strict typed numbers/booleans in API DTOs. | **P2 (Medium)** | Maintain raw typed data in API; let client handle presentation formatting. |
