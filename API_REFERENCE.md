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

#### **GET /shop/packages**
Lists active coin packages for the storefront.
- **Response**: `[ShopPackageDto]`

#### **GET /shop/features**
Lists active feature products that can be purchased with coins.
- **Response**: `[FeatureProductDto]`

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

---

## 4. Protected Endpoints (Requires Auth)

#### **POST /content/{type}/{slug}/follow**
Follow a series.

#### **POST /content/{type}/{slug}/rate**
- **Payload**: `{ "rating": 5 }` (int 1-5).

#### **POST /chapter/{chapterId}/comment**
- **Payload**: `{ "body": "..." }`

#### **POST /user/activity**
Pulsing for reading duration.
- **Payload**: `{ "chapter_id": "...", "duration": 30 }`

#### **GET /user/notifications**
Retrieve paginated notifications for the current user.
- **Query Params**: `page` (int), `per_page` (int).

#### **POST /user/notifications/read**
Mark all unread notifications as read for the current user.

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

#### **GET /user/features**
Returns the current user's active feature status (for example ad-free access).

#### **GET /user/features/entitlements**
Returns paginated feature entitlement history.

#### **POST /user/features/ad-free/purchase**
Purchases ad-free site usage with coins.

---

## 5. Admin Console API (Requires admin.panel.access)

#### **GET /admin/overview**
Dashboard KPIs and Health Funnel.

#### **GET /admin/series**
Full series list with administrative controls.

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

#### **POST /admin/maintenance/analytics**
Trigger manual analytics aggregation for the dashboard.

#### **POST /admin/maintenance/backup**
Trigger full system backup (**ROOT_USER ONLY**).

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
