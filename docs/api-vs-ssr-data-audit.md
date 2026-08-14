# API vs SSR Data Audit

**Repository:** `mhmtsnmzkanly/nm-reader`  
**Audit date:** 2026-08-12  
**Scope:** implementation audit only; no application code was changed.

## 1. Executive Summary

The public API and the PHP-rendered UI share much of the same repository/service layer, but they are **not interchangeable contracts**. The principal pattern is that SSR calls services directly and injects an initial context into HTML, while API callers receive `ResponseHelper` envelopes. This creates important differences in pagination, personalization, localization, formatting, and error behavior.

The highest-impact confirmed issue is the chapter reader. SSR calls `SeriesService::chapterDetailByTypeSlugAndNumber()`; that method loads chapter text/image paths without any `WalletService::chapterAccess()` check. The API calls `ChapterService::getByTypeSlugAndNumber()`, which calculates access and returns no body/pages when access is denied. Therefore a premium chapter can render in SSR while being locked in the API.

Other important differences:

- SSR content pages load up to 200 chapter rows and include session-derived access; the chapter API defaults to 20 and, because the route is public, reads only the request `user_id` attribute rather than the session. This produces anonymous access state for a session-authenticated browser.
- SSR search implements genre/tag/status/sort filters, whereas `GET /api/v1/search` reads only `q`, `page`, and `per_page` and drops the filter query parameters.
- SSR profiles combine public profile, own history, library, and preferences in one HTML response. The API deliberately splits those into separate protected endpoints; SSR also omits several API-only private resources (notifications, sessions, transactions, unlocks, entitlements).
- SSR is presentation-ready (Turkish date/number formatting, translated labels, localized paths, SEO/JSON-LD, URL fields), whereas API payloads are mostly raw database/DTO fields plus localized **error messages**.
- Several SSR “capabilities” are shells which fetch the API after HTML render (comments, notifications, wallet); they must not be interpreted as SSR-supplied data.

## 2. Methodology

The audit traced executable paths rather than relying on `PROJECT.md`:

1. Routes in `app/Config.php` were mapped to `WebController` (HTML) and API controllers.
2. Controller calls were followed through services, DTOs, repositories, and relevant middleware.
3. Each `storage/views/*.php` template and shared layout/modal was inspected for values actually rendered versus values loaded by browser JavaScript.
4. Database-field origin was verified from repository SQL where a difference depends on the source shape.
5. Findings are classified as **intentional representation difference**, **possible inconsistency**, or **confirmed inconsistency**. “No SSR equivalent” means no server-rendered initial data for the capability; a later browser API request is called out separately.

Relevant implementation entry points: `app/Config.php:159-278`, `app/Controllers/WebController.php:101-730`, `app/Controllers/ContentController.php:35-225`, `app/Helpers/ResponseHelper.php:17-100`, and `storage/views/`.

## 3. Architecture / Data Flow

### API

`database → Repository SQL → Service/DTO → API controller → ResponseHelper JSON envelope`

`ResponseHelper::success()` and `::error()` always emit `{status, data, meta, error}` (`app/Helpers/ResponseHelper.php:26-81`). Public data routes are registered in `app/Config.php:207-236`; protected routes have `AuthMiddleware` plus `CsrfMiddleware` (`app/Config.php:242-277`).

### SSR

`database → Repository SQL → Service → WebController context → template → layout → HTML`

`WebController::render()` adds an HTML-only context: `auth`, translated dictionary, locale, URL helper, site configuration, footer taxonomy/popular/latest data, SEO fields, and serialized `window.__NMR_CONTEXT` (`app/Controllers/WebController.php:1202-1397`). It is not an API envelope.

The main layout loads `public/assets/js/app-bundle.js`; that client wraps requests to `/api/v1` and uses the injected CSRF token (`storage/views/layout_main.php:120`, `public/assets/js/app-bundle.js:1-55`). Thus some visible SSR areas are populated asynchronously by the API.

## 4. Page-by-Page Comparison

| SSR route / controller / view | Equivalent API | SSR data and behavior | API data and behavior | Difference / severity |
|---|---|---|---|---|
| `/[lang]` — `WebController::home` — `home.php` | `GET /api/v1/home` | Calls `SeriesService::home(1,20)`; view uses explore, recent chapters, recently added, popular/latest blogs. | Same service, request `page/per_page` (1–50), JSON envelope. | **PAGINATION_DIFFERENCE**: SSR fixed 20, API configurable/default 20. Same service fields; API includes meta. Low. |
| `/{type}` — `listing` — `series_list.php` | `GET /content/type/{type}`, `GET /content/{type}/chapters` | Content list fixed 50 and a separate latest-by-type slice fixed 12, although template only consumes `items`. | Each endpoint returns one list, default 20, max 50. | SSR has a non-rendered `latest_items`; API metadata/no totals. Low. |
| `/genre/{slug}`, `/tag/{slug}` — `genre`/`tag` — `series_list.php` | `GET /genre/{slug}`, `GET /tag/{slug}` | Same `SeriesService` list functions at fixed 50; localized breadcrumbs/heading are controller-derived. | Same list function, default 20/max 50 and JSON meta. | **PAGINATION_DIFFERENCE**, **COMPUTED_PRESENTATION**, **LOCALIZATION_DIFFERENCE**. Low. |
| `/search` — `search` — `search.php` | `GET /search`, `GET /search/suggest` | Parses `q`, CSV `genres`, CSV `tags`, `status`, `sort`; calls `SeriesService::search(q,1,50,filters)`. | Controller passes only `q` to `search`; it does not forward filters. Suggest has no SSR equivalent. | **CONFIRMED INCONSISTENCY**, High: same URL/query shape has materially different results. |
| `/{type}/{slug}` — `content` — `content.php` | `GET /content/{type}/{slug}`, `GET …/chapters`, comments/unlock/follow/rate APIs | SSR detail uses session user ID; loads 200 chapter rows, first chapter number, taxonomies, reading progress, content access, SEO/JSON-LD. | Detail endpoint uses session user ID; chapter list endpoint uses request attribute and therefore sees guest on public route. Both use DTO fields. | **PERSONALIZATION_DIFFERENCE**, **PAGINATION_DIFFERENCE**, High for chapter access state; SSR also has SSR-only SEO/breadcrumb/context. |
| `/{type}/{slug}/chapter/{number}` — `chapter` — `chapter.php` | `GET /content/{type}/{slug}/chapter/{number}` | `SeriesService` loads body/pages unconditionally; no access object, no mark-read. | `ChapterService` calculates access; locks body/pages; adds `access`, `price_coin`, `is_locked`; marks read after granted response for signed-in user. | **CONFIRMED INCONSISTENCY**, Critical; also API-only access/reading-history effects. |
| `/blogs`, `/blogs/{slug}` — `blog` — `blog.php` | `GET /blogs`, `GET /blogs/{slug}` | List calls repository directly at 20. Detail calls `findApprovedBySlug(slug)` with no viewer ID. | List routes through `BlogService` at default 20. Detail uses optional auth middleware and passes request user ID to service. | Detail **PERSONALIZATION_DIFFERENCE**: SSR always `my_vote=0`; API supplies viewer vote when session exists. Medium. |
| `/profile`, `/profile/{person}` — `profile` — `profile.php` | `GET /profile/{person}`, `GET /user/profile`, `/history`, `/follows`, `/preferences` | Public profile (blogs 5/comments 10); when viewing own profile SSR also embeds history 50, library 100, preferences. | Public profile has independently configurable sub-pagination; private information is separate protected endpoints. | **STRUCTURE/PAGINATION/PERSONALIZATION_DIFFERENCE**. SSR own-profile aggregation is richer at first render; API exposes more private operations. Medium. |
| `/login` — `login.php` + `partials_modals.php` | `POST /auth/login`, `/auth/register`, `/auth/refresh`, `/auth/logout` | HTML only, forms and optionally Turnstile key. Form errors are JS/API outcomes. | JSON auth result/errors, rate limiting; sessions/token fields from `AuthService`. | **NO SSR DATA EQUIVALENT** for API auth response/session list. Intentional. |
| Global comments on content/chapter/blog | comment list/create/vote endpoints | Templates render composer/login prompt and “loading”; comments are fetched after load. | Paginated/cursor API data including current viewer vote. | **API_ONLY initial data**; SSR is an API-backed shell, not a data path. Medium for a React migration. |
| Global wallet, notifications, preferences modal, reader settings | wallet/transactions/features/unlocks/notifications/preferences/session APIs | Header only reads session wallet balance; profile wallet tab and modals render placeholders then request APIs. | Protected structured resources and mutations. | **API_ONLY** as SSR initial data; session values may be stale/partial. Medium. |
| `/chat` — `chat.php` | No API/chat backend route found | Static chat input/online-count placeholder. | No equivalent API found. | **SSR_ONLY / NO API EQUIVALENT**. Medium if the UI promises chat. |
| `/admin/*` — `WebController::admin*` — `admin_*.php` | `/api/v1/admin/*` | Controllers supply an authorized HTML shell with auth/lang context only. | Admin API is the data/mutation contract, permission-protected per endpoint. | Intentional shell/API split; no SSR data-equivalent to audit beyond context. |

## 5. Entity / Field-Level Comparison

### Content and taxonomy

Database source: `series` + `series_metadata` + genre/tag mapping in `SeriesRepository::findContentByTypeAndSlug()` (`app/Repositories/SeriesRepository.php:173-215`). `ContentDto` casts base fields (`app/DTO/ContentDto.php:64-119`).

| Field | SSR representation | API representation | Difference |
|---|---|---|---|
| `id`, `title`, `slug`, `type`, `status` | Detail/list DTO; HTML escapes values. | Same DTO fields in `data`. | Same semantic values; HTML versus JSON. |
| `rating_avg`, `rating_count`, `chapter_count`, `comment_count` | Number-formatted in templates (`content.php:60-69`). | Numeric DTO values. | **FORMAT_DIFFERENCE / COMPUTED_PRESENTATION**. |
| `cover_image` | Raw path used in `<img>` and SEO made absolute. | Nullable raw DTO path. | **NULLABILITY/FORMAT**: SSR sometimes falls back at presentation; API preserves null. |
| `accent_color` | Present in SSR context but not rendered by inspected public views. | DTO field. | **API_ONLY** useful field. |
| `author`, `artist`, `alternative_titles`, `country`, `release_year`, `description`, `created_at` | Detail context includes all; template visibly uses author/artist/description and controller uses others for SEO. | Same detail DTO keys. | Same underlying values; SSR has SEO-derived values. `release_year` is coerced to string by DTO even if DB numeric. |
| `series_genres[]`, `series_tags[]` | Parsed `{name,slug,ui_config}` objects, rendered as chips. | Same service result. | Same detail shape; raw SQL `*_raw` never exposed. |
| `type_path`, `url_path` | Added by `SeriesService::appendTypePathFields()` and used for localized URL construction. | Added to all mapped content lists/detail. | Same fields, but API paths are locale-neutral and SSR prefixes locale via URL closure. |
| `is_followed`, `reading_progress`, `series_unlock_price`, `is_series_unlocked`, `has_any_premium` | Detail SSR receives them based on `$_SESSION['user_id']`. | Type detail receives them based on session too. | Same for the detail route; list APIs have no follow state. |

### Chapter / reader

Source: `chapters` and its `data` column. `ChapterRepository::findChapterPages()` turns pipe-separated data into ordered `{image_path,page_order}` (`app/Repositories/ChapterRepository.php:183-199`).

| Field/capability | SSR | API | Difference |
|---|---|---|---|
| Metadata (`id`, `content_id`, `chapter_number`, `title`, `type`, `created_at`) | SSR reader service returns repository row; list uses `ChapterDto`. | `ChapterDto` output. | Same strings; chapter number normalized. |
| `body`/`pages` | SSR reader always obtains content by type. | Present only when `access.granted`; otherwise `body:null`, `pages:[]`. | **CONFIRMED INCONSISTENCY / PERMISSION_DIFFERENCE**. |
| `pages` structure | `{image_path,page_order}`. | Same when granted. | Same. |
| `series_title`, `series_slug`, `series_type` | Added by SSR `SeriesService` for template route/navigation. | Not added by `ChapterService`. | **MISSING_FROM_API**, Medium for a standalone reader UI. |
| `adjacent_chapters` | `{prev,next}` chapter-number strings. | Same. | Same. |
| `access`, `price_coin`, `is_locked` | Missing from chapter page context. | Explicit nested access + convenience fields. | **MISSING_FROM_SSR**, Critical alongside unlocked content leak. |
| reading history | SSR chapter render does not call `markRead`. | Controller calls `markRead` after granted response. | **CONFIRMED INCONSISTENCY**, High. |

### User, profile, preferences, session

| Field/capability | SSR | API | Difference |
|---|---|---|---|
| Private profile | Header/session exposes selected `username`, roles, permissions, CSRF, preferences; profile modal uses session bio/email/avatar. | `GET /user/profile` returns private user fields and an explicit guest object. | **STRUCTURE / DEFAULT_VALUE**: SSR guest has absent fields; API guest has `is_guest:true`, literal `guest`, and null fields. |
| Public user | `{user:{…, avatar}, is_following, statistics, blogs, recent_comments, meta}`. | Same `UserService::publicProfile` result. | Same data; both derive `avatar` from `profile_image`, score, counts, and comment URLs. |
| Own history/library | SSR embeds 50 history and 100 follows only when `isMe`. | Separate protected `/user/history` default 20 and `/user/follows` default 20. | **PAGINATION / STRUCTURE**. |
| Preferences | SSR render loads preferences for every signed-in page and URL language can write it. | Explicit protected GET/PUT; nested reader values. | **BEHAVIOR/PERSONALIZATION**; SSR has a side-effect on GET. |
| Sessions | Not rendered or supplied. | `/auth/sessions`, revoke endpoint. | **API_ONLY**. |

`UserService::preferences()` defaults are `lang:"tr"`, `theme:"default"`, reader layout `vertical`, font-size/line-height/weight as **strings** (`app/Services/UserService.php:88-115`); the update path validates numeric input and writes values that the next read stringifies. This is a **TYPE_DIFFERENCE** between input semantics and delivered preferences, not an SSR/API disagreement.

### Comment, blog, notification

| Entity / fields | SSR representation | API representation | Difference |
|---|---|---|---|
| Comment | Content SSR has no list in initial context; profile embeds own recent comments with derived `url_path`/`score`; chapter has no comment section. | Context list APIs return repository comments, pagination/cursor metadata and viewer-specific `my_vote`; create/vote APIs mutate. | **API_ONLY** general thread, votes, cursor. Profile subset is SSR-only aggregation. |
| Blog list | Repository `listApproved(1,20)` put under `ssr_data.blog_list`; HTML computes excerpt/date and substitutes remote fallback image. | `BlogService::listApproved` at API requested page/per-page; raw fields. | **COMPUTED_PRESENTATION / DEFAULT_VALUE / PAGINATION**. |
| Blog detail | Direct repository query with `userId=null`; title/body/date/author rendered; body `nl2br` but not sanitized at output. | `BlogService::getApprovedBySlug(slug,userId)` adds viewer-aware `my_vote` from repository. | **PERSONALIZATION_DIFFERENCE** and likely output-sanitization concern outside scope. |
| Notification | Placeholder in modal; no SSR list/context. | Protected cursor/page list; sanitized `title`, `body`, `actor_username`; `is_read`, actor, data fields come from repository. | **API_ONLY**. |

### Wallet, transaction, unlock, entitlement

| Field/capability | SSR | API | Difference |
|---|---|---|---|
| Header balance | `$_SESSION['user_wallet']['balance'] ?? '0'` (string fallback). | `balance_coin:int`, purchase/spent totals, features, updated timestamp. | **FIELD_NAME / TYPE / DEFAULT_VALUE**; SSR can be stale and lacks totals/features. |
| Wallet/transactions | Profile tab is placeholders then JS API calls. | Protected wallet and transaction list (meta includes `total`). | **API_ONLY initial data**. |
| Series/chapter access | SSR detail/list consumes access; reader does not. | Detail/list/reader supply structured access as described above. | See reader and chapter-list findings. |
| Unlocks/entitlements | Not server-rendered. | Protected paginated endpoints with `total`. | **API_ONLY**. |
| Shop packages/features | Not initial SSR; client-side profile tab requests public endpoints. | Packages add computed `total_coin`; active feature products are public. | **API_ONLY initial data / DERIVED_DATA**. |

## 6. API-Only Data

- Standard response `status`, `meta`, and structured `error` fields.
- Explicit reader `access`, pricing, lock flags, and the read-recording side effect.
- Suggestion results (`GET /search/suggest`).
- Guest-profile object and full private profile response.
- Sessions, notifications, transaction/unlock/entitlement collections, feature status, and protected mutations.
- Comment thread pagination/cursor and viewer `my_vote`.
- Blog detail `my_vote`; blog votes and creation states.
- Admin data contracts (SSR pages are only authorized shells).

## 7. SSR-Only Data

- Translated labels/dictionary, localized route helper, breadcrumb arrays, main navigation/footer taxonomy/popular/latest slices.
- SEO title/description/canonical/image, OpenGraph tags, and content/blog JSON-LD (`WebController::content()` and `::blog()`).
- `window.__NMR_CONTEXT` carrying auth (including CSRF token), site configuration, locale, and the particular page context.
- SSR-owned profile aggregation (own library/history/preferences) and profile comment URL/score presentation.
- `/chat` shell; no API equivalent was found.
- HTML redirect/canonical/cache/ETag behavior.

## 8. Representation Differences

- **Envelope:** API wraps all payloads; SSR injects named variables, serialized context, then HTML.
- **Names:** profile public view aliases `profile_image` to `user.avatar`; header wallet uses `balance` while API wallet uses `balance_coin`.
- **Structure:** API preferences are a nested JSON object; SSR spreads preferences into auth context and also reads legacy session keys directly in modal markup.
- **Types:** DTO numeric fields are JSON numbers but SSR formats them into locale/display strings. User preference reader values are strings at delivery. Locked flags rendered into `data-*` attributes are strings `"1"`/`"0"`.
- **Null/defaults:** API profile guest explicitly provides null keys; SSR mostly omits absent state and template defaults. Templates use placeholder/remote fallback images which are not data-level API defaults.
- **Routes:** API `url_path` is locale-free; SSR prepends resolved language. SSR uses `/tr` or `/en`, while API locale comes from middleware resolution rather than an API route segment.

## 9. Transformation Differences

| Transformation | SSR | API | Evidence |
|---|---|---|---|
| Content DTO/casts | Same service path for type detail/list. | Same. | `ContentDto::toArray()` / `SeriesService`. |
| Taxonomy | SQL `GROUP_CONCAT` parsed to `{name,slug,ui_config}`. | Same on type detail. | `SeriesService.php:166-194`. |
| Chapter number | Normalized for links/display. | Normalized DTO field. | `ChapterNumber`; `ChapterService.php:82`. |
| Date/number | `date('d.m.Y')`, `date('d M Y')`, `number_format`. | Raw DB date strings and numeric JSON. | `storage/views/content.php:137`, `profile.php:130`. |
| Search sort/filter | SSR forwards translated display tokens into SQL filters. | API drops all except `q`. | `WebController.php:556-575`; `ContentController.php:141-149`. |
| Blog excerpt/image | HTML strips/takes substrings; supplies Unsplash fallback. | Raw body and no cover field in repository select. | `storage/views/blog.php`. |
| HTML safe output | Templates usually `htmlspecialchars`; text reader uses `nl2br(htmlspecialchars(body))`. | Services sanitize particular fields before JSON for profiles/notifications. | `chapter.php:51`; `UserService.php:250,393`. |
| URL/SEO | Breadcrumbs, absolute URLs, canonical, JSON-LD. | No equivalent fields. | `WebController.php:163-264,1202-1374`. |

## 10. Personalization Differences

| Subject | Anonymous SSR | Authenticated SSR | Anonymous API | Authenticated API | Assessment |
|---|---|---|---|---|---|
| Content follow/progress/access | no follow/progress; access price summary | session-aware detail and up to 200 session-aware chapters | detail session-aware; chapter-list endpoint gets null request attr | chapter-list still gets null request attr because its public route has no optional auth middleware | **Confirmed chapter-list access mismatch**. |
| Reader entitlement | ignored; body/pages rendered | still ignored | locked payload when needed | granted payload + mark-read | **Confirmed critical mismatch**. |
| Blog vote | `my_vote=0` | still `0` because SSR calls repository without viewer | optional auth route permits guest | viewer `my_vote` supplied | **Confirmed mismatch**. |
| Public-profile follow | `is_following=false` | session-aware service calculation | same service/session-based behavior | same | Same capability, route not bearer-driven. |
| Wallet/header | no wallet block | session balance only | protected resources unavailable | full wallet/features | Intentional representation split. |
| Notifications/sessions | no data | placeholders/API after load | protected unavailable | API only | Intentional split. |

## 11. Authentication / Permission Differences

SSR uses PHP session directly (`$_SESSION`) and redirects unauthenticated `/profile` to `/login` (`WebController.php:375-382`); admin pages redirect to `/` when `canAccessAdminPanel()` is false (`WebController.php:733-919`). API protected routes return `401` from `AuthMiddleware`; admin permission routes return `403` from `PermissionMiddleware`.

| Behavior | SSR | API | Classification |
|---|---|---|---|
| Guest private profile | 302 login redirect. | Protected endpoint 401; `/user/profile` is itself in secure group despite guest fallback code. | Intentional transport difference. |
| State-changing protected calls | HTML/JS uses injected session CSRF token. | Requires `X-CSRF-Token`, returns structured 419. | Intentional. |
| Blog detail | Public SSR direct query. | Optional auth middleware enables personalized vote. | Confirmed personalization difference. |
| Reader access | No authorization check in SSR data path. | Enforced by `chapterAccess`. | Confirmed inconsistency. |
| Admin | Redirect based on WebController authorization. | Per-endpoint RBAC, 403 JSON. | Intentional representation difference; possible policy divergence because web gate is broad and API permissions are granular. |

The inspected `AuthMiddleware` is session-based; no bearer/API-token parsing is implemented in it (`app/Middleware/AuthMiddleware.php:48-104`). Therefore “API-token behavior” is **unknown/not implemented in the inspected request path**, rather than an API-versus-SSR difference.

## 12. Reader Comparison

### Image and text payloads

Both paths get a chapter by `(type, slug, number)`, normalize number, compute adjacent chapter numbers, and represent image pages as ordered `{image_path,page_order}`. SSR adds series identity fields used by the view; API does not. Text SSR renders escaped body with `nl2br`; it receives raw text in JSON only when unlocked.

### Locked content

| Path | Locked behavior |
|---|---|
| SSR `WebController::chapter` → `SeriesService::chapterDetailByTypeSlugAndNumber` | Always fetches body/pages (`SeriesService.php:552-558`), returns no access data, and renders it (`storage/views/chapter.php:47-65`). |
| API `ContentController::chapterDetail` → `ChapterService::getByTypeSlugAndNumber` | Calculates `WalletService::chapterAccess`; if denied returns metadata, null body, empty pages, and `{access,price_coin,is_locked}` (`ChapterService.php:84-105`). |

**Confirmed inconsistency (CRITICAL):** server rendering bypasses the API’s paid-content access rule.

### Navigation, settings, comments

SSR has prev/next links but no chapter selector. Reader settings are a shared modal (layout/image fit/font settings) saved through APIs; no reader preference values are supplied specifically by the chapter controller beyond global auth preferences. The `chapter.php` view has no comment container, unlike content detail; API supports chapter comments. These are **MISSING_FROM_SSR** capabilities, not necessarily backend defects.

## 13. Search / Filter Comparison

SSR supports CSV `genres`, CSV `tags`, `status`, and Turkish sort tokens `EN YENİLER`, `EN ÇOK OKUNAN`, `EN YÜKSEK PUAN`; it uses 50 results (`WebController.php:556-587`, `storage/views/search.php:31-93`). Repository SQL implements those filters (`SeriesRepository::search`).

The API endpoint accepts query parameters mechanically but only forwards `q` (`ContentController.php:141-149`). Thus all SSR filters are silently ignored by the API. API-only autocomplete needs a two-character query and has its own limit. This is a **confirmed functional inconsistency**, not merely a UI representation difference.

## 14. Pagination Comparison

| Flow | SSR | API |
|---|---|---|
| Home | fixed 20; template slices several sections further | default 20, max 50, meta page/per_page |
| Type/genre/tag/search | fixed 50, no rendered pagination/total | default 20, max 50, meta page/per_page but no total |
| Content chapters | fixed 200 | default 20, max 50, no total |
| Profile own history/library | 50 / 100 | default 20, max 50 |
| Public profile blogs/comments | 5 / 10 | configurable 5 / 10 defaults, max 20 / 50; returned nested meta |
| Blogs | fixed 20 | default 20, max 50 |
| Comments/notifications | no initial SSR list | default 20, max 50; optional cursor `next_cursor` only when caller supplied cursor |
| Wallet transactions/unlocks/entitlements | API-loaded shell | default 20, max 50; totals included |

Most are intentional delivery differences, but the 200-versus-20 content chapter split matters because SSR and API-driven views may show different available chapters/access states.

## 15. Localization Comparison

SSR language is route-driven (`/tr` or `/en`), otherwise resolved from URL/session/browser/default. `WebController::render()` writes a logged-in user’s URL locale to preferences on a GET (`WebController.php:1223-1244`) and uses translation dictionaries in labels/breadcrumbs. It sets `Content-Language`, SEO locale, and locale-prefixed links.

API routes do not take a language parameter (apart from the dictionary endpoint `GET /api/v1/i18n/{lang}`); `I18nMiddleware` resolves the same locale source and only post-processes structured **error messages** (`app/Middleware/I18nMiddleware.php:189-228`). Content/entity fields are not translated. This is an intentional API-vs-presentation difference, with one behavior concern: SSR locale URLs mutate the saved preference while API requests do not.

## 16. Error / Status Behavior Comparison

| Logical condition | SSR | API |
|---|---|---|
| Not found from content/chapter/profile | Controller often returns bare status 404; middleware can render `error.php` for thrown/not-routed errors. | `{status:"error",data:null,meta:{},error:{code:404,key:"NOT_FOUND",message,…}}`. |
| Unauthorized | redirects for profile/admin pages; guests see conditional UI. | 401 JSON from middleware. |
| Forbidden | admin is redirected; comment restriction is represented by UI/API response. | 403 JSON. |
| Invalid CSRF | HTML shell supplies token; browser JS sends it. | 419 JSON for protected unsafe verbs. |
| Insufficient coins | SSR reader lacks lock state; content modal relies on later API response. | Unlock and ad-free purchase return 402 JSON. |
| Validation | SSR forms have native required fields and API-result display handled by JS. | Controller usually returns 400 JSON message; no structured field-error map was found. |

The global error handler returns JSON for `/api/*` or `Accept: application/json`; it renders branded HTML otherwise (`app/middleware.php:250-294`). This is intentional transport behavior.

## 17. Confirmed Inconsistencies

1. **CRITICAL — SSR paid reader bypass.** `WebController::chapter` calls `SeriesService::chapterDetailByTypeSlugAndNumber` (`WebController.php:286-340`), which fetches reader data regardless of entitlement (`SeriesService.php:552-558`). The equivalent API uses `ChapterService`, blocks data when denied, and emits access state (`ChapterService.php:84-105`).
2. **HIGH — SSR reader does not record authenticated reading history.** API controller marks a granted chapter read (`ContentController.php:106-109`); SSR chapter controller has no corresponding call.
3. **HIGH — Search filters are accepted/rendered in SSR but ignored by API.** SSR passes filters (`WebController.php:556-575`); API does not (`ContentController.php:141-149`).
4. **HIGH — Chapter-list access can differ for an authenticated session.** SSR passes `$_SESSION['user_id']` to `chaptersByType` (`WebController.php:258`); public API chapter list obtains only request attribute without optional auth middleware (`ContentController.php:87-94`).
5. **MEDIUM — SSR blog detail suppresses current viewer vote.** SSR repository call has no user (`WebController.php:465-494`); API optional-auth call passes one (`BlogController.php:808-817`).

## 18. Possible Inconsistencies

- **Admin authorization granularity:** HTML only checks the broad `canAccessAdminPanel()` gate, whereas API actions enforce individual permission codes. The templates are shells, so whether unauthorized controls are shown cannot be proven from the inspected controller alone.
- **Session wallet header:** SSR reads `$_SESSION['user_wallet']['balance']` with a string `"0"` fallback, while canonical wallet data is repository/API `balance_coin`. Session hydration/update was not found in this audit, so stale display is plausible but unproven.
- **Blog cover image:** templates expect `cover_image` and substitute remote fallback URLs, while public repository selects do not include a cover column. This is a presentation fallback rather than proof that persisted cover data is missing.

## 19. Unknown / Requires Verification

- There is no server-rendered register route; registration is a shared modal/API workflow. Browser-side form-error presentation depends on bundled JS behavior not represented in PHP routes.
- No public SSR pagination controls were found for fixed-size lists; whether they are intentionally omitted or unfinished needs product confirmation.
- `PROJECT.md` references an older mobile API path; actual mobile source must be validated separately for parity. The `/mobile` route serves a Framework7 shell, not PHP view data (`WebController.php:67-96`).
- No bearer-token authentication implementation was found in the inspected middleware; confirm intended external-client auth before treating the JSON API as token-based.
- Legacy `SeriesService::contentDetail()` and `chapters()` are not routed by the current typed public page/API routes; their differences are not assigned to a user-facing equivalent.

## 20. Impact on React UI

A React frontend should treat the typed API as the future data source, but it cannot reproduce all current SSR behavior without reconciliation:

- It must not use SSR reader behavior as an access specification; use the API lock contract until the SSR leak is fixed.
- It needs API support for search filters and reader series identity, or must derive these from known route data safely.
- It should request private resources independently (profile, history, follows, preferences, wallet, notifications, sessions), instead of assuming the SSR profile aggregation is an API object.
- It must model `ResponseHelper` envelopes, 401/403/402/419, pagination, and null/default differences explicitly.
- It should render locale labels/dates/URLs client-side; API returns data rather than SSR’s localized presentation/SEO context.

## 21. Recommendations

1. Make reader authorization a single shared service path; SSR should use `ChapterService::getByTypeSlugAndNumber()` or equivalent before rendering body/pages.
2. Add optional authentication middleware (or consistently read session) to public personalized API endpoints, especially chapter lists; document cookie/session requirements.
3. Extend `GET /api/v1/search` to validate and forward `genres`, `tags`, `status`, and `sort`, with stable enum values rather than translated display strings.
4. Decide whether SSR blog details should receive viewer identity; if yes, pass it to `findApprovedBySlug`/`BlogService`.
5. Publish an explicit API schema for nullable fields, numeric/string representations, preference value types, access state, pagination totals/cursors, and error-field validation format.
6. Separate canonical data from presentation: keep locale/SEO/breadcrumb generation as frontend/SSR adapters, and avoid using session cache values as wallet truth.
7. For the React migration, inventory API-loaded SSR placeholders separately from initial SSR variables so that “visible today” is not mistaken for SSR data availability.

## 22. Complete Difference Matrix

| Area | SSR | API | Difference | Classification | Severity | Evidence |
|---|---|---|---|---|---|---|
| Response envelope | Named variables → HTML/context JSON | `{status,data,meta,error}` | Transport and error shape differ | Intentional representation difference | INFO | `WebController::render`; `ResponseHelper` |
| Home | fixed `home(1,20)` | query page/per_page | configurable API page size/meta | PAGINATION_DIFFERENCE | LOW | `WebController.php:105-114`; `ContentController.php:35-41` |
| Content listing | SSR 50; no UI pagination | default 20/max 50 | result window differs | PAGINATION_DIFFERENCE | MEDIUM | `WebController.php:614-615`; `ContentController.php:44-49` |
| Content detail fields | DTO + taxonomy + session access/progress + SEO | DTO + taxonomy + session access/progress | SSR-only SEO/localized URL | COMPUTED_PRESENTATION | LOW | `SeriesService.php:151-194`; `WebController.php:163-264` |
| Content chapter list | SSR 200 with session ID | 20 public route gets attribute ID | page size and entitlement state can differ | CONFIRMED INCONSISTENCY | HIGH | `WebController.php:258`; `ContentController.php:87-94` |
| Reader lock | content always loaded | body/pages removed when denied | contradictory access behavior | CONFIRMED INCONSISTENCY | CRITICAL | `SeriesService.php:533-562`; `ChapterService.php:63-105` |
| Reader progress | no mark-read | granted read is recorded | history diverges | CONFIRMED INCONSISTENCY | HIGH | `ContentController.php:106-109`; SSR chapter method |
| Reader series identity | `series_title/slug/type` | absent | API cannot directly build backlink/title | MISSING_FROM_API | MEDIUM | `SeriesService.php:545-550`; `ChapterService.php:100-105` |
| Search | filter-aware, 50 | q-only, default 20 | filter requests silently differ | CONFIRMED INCONSISTENCY | HIGH | `WebController.php:556-575`; `ContentController.php:141-149` |
| Search suggestions | none found in SSR | dedicated API | API feature only | API_ONLY | LOW | `Config.php:227-228` |
| Blog detail vote | always anonymous query | optional-auth viewer vote | `my_vote` differs | CONFIRMED INCONSISTENCY | MEDIUM | `WebController.php:465-494`; `BlogController.php:808-817` |
| Blog excerpt/image | computed text + fallback image | raw fields | presentation/fallback differs | COMPUTED_PRESENTATION, DEFAULT_VALUE | LOW | `storage/views/blog.php`; `BlogRepository.php:34-63` |
| Profile own resources | embedded history/library/preferences | split protected endpoints | shape and initial page sizes differ | STRUCTURE_DIFFERENCE | MEDIUM | `WebController.php:400-432`; `Config.php:249-258` |
| Profile guest | absent/redirect-based | explicit guest object | null/default semantics differ | DEFAULT_VALUE_DIFFERENCE | LOW | `WebController.php:378-382`; `UserController.php:37-74` |
| Public profile | same service result | same service result | API sub-pagination configurable | PAGINATION_DIFFERENCE | LOW | `UserService.php:207-303`; `UserController.php:131-145` |
| Wallet header | session `balance` / string fallback | canonical `balance_coin:int` plus totals/features | name/type/completeness differ | FIELD_NAME_DIFFERENCE, TYPE_DIFFERENCE | MEDIUM | `layout_main.php:260-269`; `WalletService.php:28-40` |
| Wallet data | placeholders then JS | protected collections | no SSR initial data | API_ONLY | MEDIUM | `profile.php:194+`; `UserController.php:265-323` |
| Comments | SSR loading shells/profile subset | paginated threads + viewer votes | initial source and capabilities differ | API_ONLY, PERSONALIZATION_DIFFERENCE | MEDIUM | `content.php:158-178`; `UserInteractionController` |
| Notifications/sessions | modal placeholders/no initial collection | protected JSON resources | API data-only | API_ONLY | MEDIUM | `partials_modals.php`; `Config.php:270-276` |
| Preferences | global auth context; URL GET writes lang | explicit GET/PUT JSON | side effect + delivery structure differ | PERSONALIZATION_DIFFERENCE | MEDIUM | `WebController.php:1229-1246`; `UserService.php:88-191` |
| Localization | URL locale, translated HTML, locale URLs | resolved locale affects errors only | data labels/presentation not equal | LOCALIZATION_DIFFERENCE | MEDIUM | `WebController::render`; `I18nMiddleware` |
| Errors/auth | redirects/branded HTML/bare 404 | JSON 401/403/402/419/404 envelope | logical failures represented differently | Intentional representation difference | MEDIUM | `middleware.php:279-293`; middleware classes |
| Admin pages | authorized HTML shells | granular permission data API | rendering/data split | Intentional, possible policy divergence | LOW | `WebController.php:733-919`; `Config.php:288-355` |
| Chat | SSR shell exists | no API endpoint found | capability lacks API contract | SSR_ONLY / NO API EQUIVALENT | MEDIUM | `Config.php:165`; `storage/views/chat.php` |
