# NovelMangaReader API Specification

This document provides a comprehensive technical reference for the NMR API (v1).

---

## 1. Protocol & Conventions

### Base URL
Use the server `APP_URL` and append `/api/v1` for API calls.
Example (local): `http://localhost:8080/api/v1`

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

#### **GET /series_genres**
Alias for public genre listing.

#### **GET /tags**
Paginated public tag listing.

#### **GET /series_tags**
Alias for public tag listing.

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

#### **POST /log/error**
Endpoint for frontend JS error logging.
- **Payload**: `{ "message": "...", "url": "...", "stack": "...", "context": {} }`

---

## 3. Auth & Identity

#### **POST /auth/register**
- **Payload**:
  ```json
  { "username": "...", "email": "...", "password": "...", "turnstile_token": "..." }
  ```
  - `turnstile_token` is required only when Cloudflare Turnstile keys are configured.

#### **POST /auth/login**
- **Payload**:
  ```json
  { "email": "...", "password": "...", "turnstile_token": "..." }
  ```
  - `turnstile_token` is required only when Cloudflare Turnstile keys are configured.
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

## 5. Entity Data Transfer Objects (DTOs)

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

---

## 6. Administrative Endpoints (Requires Admin/Mod Permissions)

Note: All routes in this group are prefixed with `/api/v1/admin` and require appropriate permission nodes.

### 6.1 Content & Taxonomy
- **GET `/contents`**: Lists all series with administrative metadata.
- **POST `/content`**: Creates a new series.
- **PUT `/content/{id}`**: Updates series metadata.
- **PUT `/contents/{id}/taxonomy`**: Updates genres and tags for a series.
- **POST `/content/{id}/chapters`**: Creates a chapter for a series ID.
- **GET `/content/{id}/chapters`**: Lists all chapters for a series.
- **GET `/chapters/{id}`**: Returns single chapter detail for editing.
- **PUT `/chapters/{id}`**: Updates chapter data/metadata.
- **DELETE `/chapters/{id}`**: Soft-deletes a chapter.
- **GET `/genres`**: Returns non-paginated list of all genres for modals.
- **POST `/series_genres`**: Creates a new genre.
- **GET `/tags`**: Returns non-paginated list of all tags for modals.
- **POST `/series_tags`**: Creates a new tag.
- **POST `/upload-images`**: Bulk upload endpoint for chapter images or covers.

### 6.2 User & RBAC
- **GET `/users`**: Paginated list of all registered users.
- **GET `/users/options`**: Lightweight user list for selection dropdowns.
- **PUT `/users/{id}`**: Updates user roles, email, bio, or ban status.
- **GET `/rbac/roles`**: Lists all defined roles and their priorities.
- **GET `/rbac/assignments`**: Lists current role-to-permission mappings.
- **POST `/rbac/permissions/assign`**: Grants a permission node to a role.

### 6.3 Moderation
- **GET `/blogs`**: Lists all blog posts for moderation.
- **GET `/blogs/pending`**: Lists blog posts awaiting approval.
- **POST `/blogs/{id}/approve`**: Approves a pending blog post.
- **POST `/blogs/{id}/hide`**: Hides a published blog post.
- **DELETE `/blogs/{id}`**: Soft-deletes a blog post.
- **GET `/comments`**: Lists all system-wide comments.
- **DELETE `/comments/{id}`**: Deletes a comment.
- **GET `/moderation-actions`**: Lists historical moderation audit entries.
- **POST `/moderation-actions`**: Manually records a moderation action.

### 6.4 Monetization
- **GET `/shop/packages`**: Admin view of coin packages.
- **POST `/shop/packages`**: Creates a new package.
- **PUT `/shop/packages/{id}`**: Updates package details.
- **POST `/wallets/{userId}/grant-package`**: Credits a specific package to a user.
- **POST `/wallets/{userId}/credit`**: Manually adds coins to a wallet.
- **POST `/wallets/{userId}/debit`**: Manually removes coins from a wallet.
- **GET `/wallets/{userId}`**: Returns wallet summary for a user.
- **GET `/wallets/{userId}/transactions`**: Paginated ledger for a user.
- **PUT `/series/{id}/pricing`**: Sets/updates full-series unlock price.
- **PUT `/chapters/{id}/pricing`**: Sets/updates individual chapter price.
- **GET `/features`**: Lists site-wide feature products.
- **PUT `/features/ad-free`**: Configures pricing and duration for ad-free access.

### 6.5 System & Maintenance
- **GET `/overview`**: High-level dashboard stats.
- **GET `/metrics`**: Current KPI snapshot.
- **GET `/metrics/insights`**: Advanced retention and search quality metrics.
- **GET `/stats/visits` / `/stats/views`**: Granular traffic analytics.
- **GET `/queue/jobs`**: Lists pending and failed system jobs.
- **POST `/queue/run-once`**: Manually triggers queue worker.
- **POST `/maintenance/backup`**: Triggers a full system backup.
- **POST `/maintenance/sitemap`**: Regenerates `sitemap.xml`.
- **POST `/maintenance/warmup`**: Triggers cache warming.
- **POST `/maintenance/analytics`**: Manually triggers analytics aggregation.
- **GET `/maintenance/env`**: Reads sensitive `.env` configuration.
- **POST `/maintenance/env`**: Updates `.env` variables.
- **GET `/audit-logs`**: Access logs audit.
- **GET `/login-events`**: Authentication attempt logs.
- **GET `/logs/access` / `/logs/error`**: Raw system log viewer.
