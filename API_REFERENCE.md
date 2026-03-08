# NovelMangaReader API Specification

This document provides a comprehensive technical reference for the NMR API (v1).

---

## 1. Protocol & Conventions

### Base URL
`http://localhost:8080/api/v1`

### Data Formats
- **Request Body**: `application/json`
- **Response Body**: `application/json`
- **Standard Envelope**:
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

### Authentication
Two methods are supported:
1. **Cookie-based (Web)**: Uses standard PHP sessions.
2. **Bearer Token (Mobile/CSR)**:
   - Header: `Authorization: Bearer <api_token>`
   - Token is obtained via `POST /auth/login`.

---

## 2. Public Endpoints (Unauthenticated)

### 2.1 Content Discovery

#### **GET /home**
Aggregated data for the landing page.
- **Response**:
  - `explore`: [ContentDto]
  - `recent_chapters`: [ChapterDto]
  - `recently_added`: [ContentDto]
  - `popular_blogs`: [BlogDto]

#### **GET /content/type/{type}**
List series by type (manga, novel, etc.).
- **Query Params**: `page` (int), `per_page` (int).
- **Response**: `[ContentDto]`

#### **GET /content/{type}/{slug}**
Get full details of a series.
- **Response**: `ContentDto` (includes metadata like author, description).

#### **GET /content/{type}/{slug}/chapters**
List all chapters for a series.
- **Response**: `[ChapterDto]`

#### **GET /chapter/{chapterNumber}**
Get reading data for a chapter.
- **Query Params**: `slug` (required), `type` (required).
- **Response**:
  - `type`: "text" (Markdown) | "image" (Pipe-separated URLs).
  - `data`: string.

#### **GET /search**
Search series by title.
- **Query Params**: `q` (string).
- **Response**: `[ContentDto]`

#### **GET /search/suggest**
Lightweight search suggestions for autocomplete.
- **Query Params**: `q` (string, minimum 2 chars).
- **Response**: `[ContentDto]`

#### **GET /genres**
Paginated public genre listing.

#### **GET /tags**
Paginated public tag listing.

#### **GET /genre/{slug}**
Paginated series listing for a genre slug.

#### **GET /tag/{slug}**
Paginated series listing for a tag slug.

#### **GET /latest-chapters**
Paginated latest chapter feed across all content types.

#### **GET /content/{type}/chapters**
Paginated latest chapter feed filtered by content type.

#### **GET /content/{type}/{slug}/chapter/{chapterNumber}**
Returns a chapter detail payload keyed by content type, slug, and chapter number.
- **Response**:
  - Chapter metadata
  - `access`: chapter/series unlock state for the current session user when available

#### **GET /shop/packages**
Lists active coin packages for the storefront.
- **Response**: `[ShopPackageDto]`

#### **GET /shop/features**
Lists active feature products that can be purchased with coins.
- **Response**: `[FeatureProductDto]`

#### **GET /blogs**
Lists published blog posts.

#### **GET /blogs/{slug}**
Returns a single blog post.

#### **GET /blogs/{slug}/comments**
Lists blog comments.

#### **GET /chapter/{chapterId}/comments**
Lists chapter comments.

#### **GET /content/{type}/{slug}/comments**
Lists series comments.

#### **GET /profile/{person}**
Returns a public profile view for a username or user ID.

#### **GET /i18n/{lang}**
Returns frontend translation strings for the requested language.

---

## 3. Auth & Identity

#### **POST /auth/register**
- **Payload**:
  ```json
  { "username": "...", "email": "...", "password": "..." }
  ```

#### **POST /auth/login**
- **Payload**:
  ```json
  { "email": "...", "password": "..." }
  ```
- **Response**:
  ```json
  {
    "id": "char(8)",
    "username": "string",
    "api_token": "char(64)",
    "roles": ["string"],
    "permissions": ["string"]
  }
  ```

#### **POST /auth/refresh**
Refreshes the authenticated session/token pair.

#### **GET|POST /auth/logout**
Ends the current authenticated session.

---

## 4. Protected Endpoints (Requires Auth)

Note: this route group is protected by both auth and CSRF middleware.

#### **POST /content/{type}/{slug}/follow**
Follow a series.

#### **DELETE /content/{type}/{slug}/follow**
Unfollow a series.

#### **POST /content/{type}/{slug}/rate**
- **Payload**: `{ "rating": 5 }` (int 1-5).

#### **POST /content/{type}/{slug}/comment**
Creates a series comment.

#### **POST /chapter/{chapterId}/comment**
- **Payload**: `{ "body": "..." }`

#### **POST /comments/{commentId}/vote**
Votes on a chapter/series comment.

#### **GET /user/profile**
Returns the authenticated user's private profile payload.

#### **POST /user/profile**
Updates public-facing profile fields.

#### **GET /user/history**
Returns paginated reading history.

#### **GET /user/preferences**
Returns reader/site preferences for the authenticated user.

#### **PUT /user/preferences**
Updates reader/site preferences.

#### **GET /user/follows**
Returns paginated followed series/library entries.

#### **POST /user/activity**
Pulsing for reading duration.
- **Payload**: `{ "chapter_id": "...", "duration": 30 }`

#### **GET /user/notifications**
Retrieve paginated notifications for the current user.
- **Query Params**: `page` (int), `per_page` (int).

#### **POST /user/notifications/read**
Mark all unread notifications as read for the current user.

#### **GET /user/follows/users**
Returns paginated followed-user relationships.

#### **POST /user/follows/{person}**
Follows another user.

#### **DELETE /user/follows/{person}**
Unfollows another user.

#### **GET /user/wallet**
Returns the authenticated user's coin wallet summary.

#### **GET /user/wallet/transactions**
Returns paginated wallet ledger entries.
- **Query Params**: `page` (int), `per_page` (int).

#### **GET /user/unlocks/series**
Returns paginated list of purchased series unlocks.

#### **GET /user/unlocks/chapters**
Returns paginated list of purchased chapter unlocks.

#### **POST /content/{type}/{slug}/unlock**
Unlocks a full series with coins.

#### **POST /chapter/{chapterId}/unlock**
Unlocks an individual chapter with coins when chapter-level pricing exists.

#### **GET /user/blogs**
Returns the authenticated user's own blog entries.

#### **POST /blogs**
Creates a blog post.

#### **POST /blogs/image**
Uploads an editor image for blog content.

#### **POST /blogs/{slug}/vote**
Votes on a blog post.

#### **POST /blogs/{slug}/comments**
Creates a blog comment.

#### **POST /blogs/{slug}/comments/{commentId}/vote**
Votes on a blog comment.

#### **GET /auth/sessions**
Lists active sessions for the authenticated user.

#### **DELETE /auth/sessions/{sessionKey}**
Revokes a specific authenticated session.

#### **GET /user/features**
Returns the current user's active feature status (for example ad-free access).

#### **GET /user/features/entitlements**
Returns paginated feature entitlement history.

#### **POST /user/features/ad-free/purchase**
Purchases ad-free site usage with coins.

---

## 5. Admin Console API (Requires admin.panel.access)

Note: the admin route group is additionally protected by auth and CSRF middleware. Some endpoints require stronger permissions than `admin.panel.access`, and maintenance triggers for backup, sitemap, cache warmup, and analytics are runtime-restricted to `ROOT_USER`.

#### **GET /admin/overview**
Dashboard KPIs and Health Funnel.

#### **GET /admin/series**
Full series list with administrative controls.

#### **GET /admin/contents**
Alias of the main content list endpoint.

#### **GET /admin/content**
Alias of the main content list endpoint.

#### **GET /admin/genres**
Returns all taxonomy genres for admin tooling.

#### **GET /admin/tags**
Returns all taxonomy tags for admin tooling.

#### **GET /admin/users**
User list with role management.

#### **GET /admin/uploads**
List all system-wide file uploads with uploader details and image previews.
- **Query Params**: `page` (int), `per_page` (int).

#### **DELETE /admin/uploads/{id}**
Delete a specific upload record from the database.

#### **PUT /admin/users/{id}**
Update user role/ban status.
- **Payload**: `{ "role": "slug", "is_banned": bool }`

#### **GET /admin/rbac/roles**
Returns configured RBAC roles and permissions.

#### **GET /admin/rbac/assignments**
Returns paginated RBAC assignments.

#### **POST /admin/rbac/permissions/assign**
Assigns a permission to a role.

#### **GET /admin/queue/jobs**
Returns paginated queue jobs.

#### **POST /admin/queue/run-once**
Runs the queue worker inline for a bounded batch.
- **Payload**: `{ "limit": 10, "job_type": "optional-filter-hint" }`

#### **POST /admin/retention/cleanup**
Triggers retention cleanup.
- **Payload**: `{ "days": 30 }`

#### **POST /admin/maintenance/analytics**
Trigger manual analytics aggregation for the dashboard.
- **Runtime Restriction**: `ROOT_USER` only.

#### **POST /admin/maintenance/backup**
Trigger full system backup (**ROOT_USER ONLY**).

#### **POST /admin/maintenance/sitemap**
Trigger sitemap generation (**ROOT_USER ONLY**).

#### **POST /admin/maintenance/warmup**
Trigger cache warmup (**ROOT_USER ONLY**).

#### **GET /admin/shop/packages**
Lists all shop packages, including inactive ones.

#### **POST /admin/shop/packages**
Creates a new shop package.

#### **PUT /admin/shop/packages/{id}**
Updates an existing shop package.

#### **POST /admin/wallets/{userId}/grant-package**
Applies a configured package to a user's wallet and logs the package/cash metadata in the ledger.
- **Payload**: `{ "package_id": 1, "cash_amount": "99.90", "reason": "manual payment confirmation" }`

#### **POST /admin/wallets/{userId}/credit**
Adds coins to a user's wallet manually.
- **Payload**: `{ "amount": 100, "reason": "manual top-up" }`

#### **POST /admin/wallets/{userId}/debit**
Removes coins from a user's wallet manually.
- **Payload**: `{ "amount": 25, "reason": "correction" }`

#### **GET /admin/wallets/{userId}/transactions**
Returns paginated wallet ledger entries for a specific user.

#### **GET /admin/wallets/{userId}**
Returns wallet summary and feature status for a specific user.

#### **GET /admin/users/options**
Returns a lightweight list of users for admin selectors such as the monetization console.

#### **PUT /admin/series/{id}/pricing**
Sets or updates series-level coin unlock pricing.
- **Payload**: `{ "price_coin": 120, "is_active": true }`

#### **PUT /admin/chapters/{id}/pricing**
Sets or updates chapter-level coin unlock pricing.
- **Payload**: `{ "price_coin": 8, "is_active": true }`

#### **GET /admin/features**
Lists configured feature products.

#### **PUT /admin/features/ad-free**
Creates or updates the ad-free product.
- **Payload**: `{ "coin_price": 50, "duration_days": 30, "is_active": true, "name": "Ad Free 30 Days" }`

#### **GET /admin/maintenance/env**
Returns the current environment configuration snapshot for admin editing.

#### **POST /admin/maintenance/env**
Persists admin-edited environment configuration.

#### **GET /admin/audit-logs**
Returns paginated audit log entries.

#### **GET /admin/login-events**
Returns paginated login/session events.

#### **GET /admin/moderation-actions**
Returns paginated moderation action entries.

#### **POST /admin/moderation-actions**
Creates a manual moderation/audit entry.

#### **GET /admin/logs/access**
Returns paginated system access logs.

#### **GET /admin/logs/error**
Returns paginated system error logs.

#### **GET /admin/stats/visits**
Returns visit aggregates.

#### **GET /admin/stats/views**
Returns content/chapter view aggregates.

#### **GET /admin/stats/blogs**
Returns blog analytics aggregates.

#### **GET /admin/stats/reputation**
Returns reputation leaderboard style metrics.

#### **GET /admin/metrics**
Returns the dashboard metrics snapshot.

#### **GET /admin/dashboard**
Alias of the metrics snapshot endpoint.

#### **GET /admin/metrics/insights**
Returns a combined analytics payload for views, blogs, visits, and reputation.

#### **POST /admin/content**
Creates a content item.

#### **PUT /admin/content/{id}**
Updates a content item.

#### **PUT /admin/contents/{id}/taxonomy**
Updates content genre/tag assignments.

#### **POST /admin/upload-images**
Uploads chapter/content images.

#### **POST /admin/content/{id}/chapters**
Creates a chapter using a content ID.

#### **POST /admin/content/{type}/{slug}/chapters**
Creates a chapter using type and slug lookup.

#### **GET /admin/content/{id}/chapters**
Lists chapters for a content item.

#### **GET /admin/chapters/{id}**
Returns a single chapter for editing.

#### **PUT /admin/chapters/{id}**
Updates a chapter.

#### **DELETE /admin/chapters/{id}**
Deletes a chapter.

#### **POST /admin/series_genres**
Creates a genre taxonomy item.

#### **POST /admin/series_tags**
Creates a tag taxonomy item.

#### **GET /admin/blogs**
Returns paginated blog moderation data.

#### **GET /admin/blogs/pending**
Returns pending blog submissions.

#### **POST /admin/blogs/{id}/approve**
Approves a pending blog post.

#### **POST /admin/blogs/{id}/hide**
Hides a blog post.

#### **DELETE /admin/blogs/{id}**
Deletes a blog post.

---

## 6. Entity Data Transfer Objects (DTOs)

### ContentDto
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `char(6)` | Unique ID |
| `title` | `string` | Full title |
| `cover_image` | `string` | Full or relative URL |
| `type` | `string` | manga, novel, etc. |
| `status` | `string` | ongoing, completed |

### ChapterDto
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `char(6)` | Unique ID |
| `chapter_number` | `string` | e.g. "1", "1.5" |
| `title` | `string` | Chapter title |
| `username` | `string` | Uploader's username (Admin/Mod) |
| `created_at` | `string` | Upload timestamp |

### ShopPackageDto
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | Package identifier |
| `name` | `string` | Admin-defined package label |
| `coin_amount` | `int` | Base coin amount |
| `bonus_coin` | `int` | Promotional bonus coin |
| `total_coin` | `int` | `coin_amount + bonus_coin` |
| `display_price` | `string` | Presentational fiat price |
| `currency` | `string` | ISO 4217 code |

### Access Fields
Content and chapter payloads may now include:
- `series_unlock_price`
- `chapter_unlock_price`
- `is_series_unlocked`
- `is_chapter_unlocked`
- `has_any_premium`
- `is_locked`
- `access`

### FeatureProductDto
| Field | Type | Description |
| :--- | :--- | :--- |
| `feature_key` | `string` | Stable feature identifier such as `ad_free` |
| `name` | `string` | Display label |
| `coin_price` | `int` | Purchase cost in coins |
| `duration_days` | `int` | Access duration per purchase |
| `is_active` | `bool` | Whether the feature can currently be purchased |
