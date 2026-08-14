# nm-reader — UI Implementation Specification

## 1. Mission

Build a **new, standalone React + TypeScript + Tailwind CSS user interface** for nm-reader, a reader for serialized manga, manhua, manhwa, webtoons, novels, light novels, and web novels. This is a visual/UX prototype: it must work entirely from a typed mock-service layer and must **not** call a PHP backend or any real API.

The implementation must preserve the confirmed product capabilities below, but it must not copy the existing server-rendered UI. Make a new, coherent reading product with a calm editorial visual language, clear hierarchy, generous whitespace, and strong reader ergonomics.

## 2. Critical Constraints

- Use React, TypeScript with `strict: true`, and Tailwind CSS.
- Do not connect to `/api/v1`, use `fetch`, add a PHP dependency, or implement real authentication/payment.
- Keep all domain data behind TypeScript service interfaces, with mock implementations selected by default.
- Do not invent purchase checkout, direct messaging, password reset, email verification, user blocking, chapter bookmarking, or admin screens. These are **UNKNOWN / REQUIRES VERIFICATION** as user-facing flows.
- Do not surface administration in this prototype. Backend administration exists separately and has permission-gated endpoints, but is out of scope.
- Preserve API field names and enum values exactly where they are specified below. IDs are strings: users use 8-character lowercase alphanumeric IDs; content and chapters use 6-character lowercase alphanumeric IDs; comments/transactions/packages use numeric IDs.

## 3. Technology Stack

- React + TypeScript + Vite.
- Tailwind CSS; no component library is required.
- React Router for routes.
- Local mock services returning `Promise`s with an optional artificial delay and configurable failures.
- Local component state/context is sufficient. Keep `AuthContext`, `PreferencesContext`, and a small query/cache layer separate from presentational components.
- Use an icon library already compatible with React (for example Lucide React); do not embed unlabeled icon-only controls.

## 4. Product Overview and Information Architecture

### Public discovery

The home feed has five confirmed sections: `explore`, `recent_chapters`, `recently_added`, `popular_blogs`, and `latest_blogs`. Content is typed by one of: `manga`, `manhua`, `manhwa`, `webtoon`, `light-novel`, `web-novel`, `novel`. URL type segments use the same values.

Content has a detail screen, paginated chapter list, taxonomy (genres and tags), search/autocomplete, ratings, follows, comments, and a type-aware reader. Image chapters render ordered image pages; text chapters render `body`. A chapter may be free, gated by a series unlock, gated by a chapter unlock, or unlocked for the current user.

Blogs are user-authored posts. Public lists/detail only expose approved posts. Logged-in users can submit a post, which is pending approval. Comments exist on content, chapters, and blogs; they support one parent reference and up/down/no vote.

### Account area

Authenticated users have profile editing, series library/follows, reading history, user follows, notifications, preferences, session management, wallet balance/transactions/unlocks/feature entitlements, and ad-free feature purchase. `Shop` shows coin packages and feature products, but no confirmed public purchase endpoint exists for coin packages: show packages as catalog cards with a disabled/informational action labelled `Checkout unavailable in prototype`.

### Explicitly not a normal-user page

Admin dashboard/content/blog/comment/user/operations/config/monetization/upload/log screens exist in the backend and legacy UI, but they require admin permissions. Do not build them.

## 5. Route Map

Use these React routes. They intentionally mirror confirmed public server routes without the optional legacy `/tr` or `/en` prefix. Store language in preferences; locale-prefixed routing is **UNKNOWN / REQUIRES VERIFICATION** for the new UI.

| React route | Page | API contract / mock method |
|---|---|---|
| `/` | Home | `GET /home` |
| `/browse/:type` | Type browse | `GET /content/type/:type` |
| `/genres` | Genre directory | `GET /genres` |
| `/genre/:slug` | Genre results | `GET /genre/:slug` |
| `/tags` | Tag directory | `GET /tags` |
| `/tag/:slug` | Tag results | `GET /tag/:slug` |
| `/search?q=` | Search | `GET /search` and `/search/suggest` |
| `/:type/:slug` | Content detail + initial chapters | `GET /content/:type/:slug`, `/chapters`, `/comments` |
| `/:type/:slug/chapters` | Chapter list | `GET /content/:type/:slug/chapters` |
| `/:type/:slug/chapter/:chapterNumber` | Reader | `GET /content/:type/:slug/chapter/:chapterNumber` |
| `/blogs` | Blog list | `GET /blogs` |
| `/blogs/:slug` | Blog detail + comments | `GET /blogs/:slug`, `/comments` |
| `/login` | Login | `POST /auth/login` mock only |
| `/register` | Register | `POST /auth/register` mock only |
| `/me` | Account overview/profile | `GET /user/profile`, `/wallet`, `/features` |
| `/library` | Followed content | `GET /user/follows` |
| `/history` | Reading history | `GET /user/history` |
| `/notifications` | Notifications | `GET /user/notifications` |
| `/preferences` | Preferences and sessions | `GET/PUT /user/preferences`, `GET/DELETE /auth/sessions` |
| `/wallet` | Wallet overview and transactions | `GET /user/wallet`, `/transactions`, `/unlocks/*`, `/features/entitlements` |
| `/shop` | Package/feature catalog | `GET /shop/packages`, `/shop/features` |
| `/profile/:person` | Public profile | `GET /profile/:person` |
| `/my-blogs` | Current user's blogs | `GET /user/blogs` |
| `/blogs/new` | Submit blog | `POST /blogs` mock only |

Redirect an authenticated user visiting `/login` or `/register` to `/me`. Guard account-only routes with an inline login prompt plus a CTA to `/login`, rather than an empty page.

## 6. Authentication Model

### Real contract to preserve in mocks

- Registration body: `{ username, email, password, turnstile_token? }`; it returns only `{ id, username, email }` and does **not** log the user in.
- Login body: `{ email, password, remember, turnstile_token? }`; response includes `id`, `username`, `email`, `csrf_token`, `refresh_token` (nullable unless remembered), `api_token`, `roles`, and `permissions`.
- Session authentication is required for protected endpoints. A bearer `api_token` can populate identity for optional/public/mobile flows, but does not replace the session requirement for protected routes.
- All protected state-changing requests require `X-CSRF-Token`, except logout. GET/HEAD/OPTIONS do not require it.
- `POST /auth/refresh` body is `{ refresh_token }`; it rotates the refresh token and returns a new auth payload plus `csrf_token` at controller level. Invalid/expired refresh returns `401`.
- `GET` or `POST /auth/logout` returns `{ logged_out: true }`; GET is a redirect in the real web app. Use a normal local logout action in the prototype.
- Auth errors are response-envelope errors (see below). Handle `401` by clearing mock session and showing a session-expired sign-in prompt; `403` means authenticated but not allowed; `419` means refresh/retry guidance.
- Login/register can require Cloudflare Turnstile in deployed configuration. Since its configuration is not guaranteed, include a clearly labelled mock-only `Security verification` checkbox/token field; do not integrate Turnstile.

Mock states must include guest, normal authenticated user, session-expired, moderator/editor, and a forbidden normal user. Do not show admin UI for moderator/editor.

## 7. API Contract

Every mock response must use this envelope:

```ts
type ApiSuccess<T> = { status: 'success'; data: T; meta: Record<string, unknown>; error: null };
type ApiError = {
  status: 'error'; data: null; meta: {};
  error: { code: number; key: string; message: string; params: Record<string, unknown> };
};
```

`meta` is always present. List API metadata often has only `page` and `per_page`; wallet unlock/package/entitlement list metadata also has `total`. Do **not** fabricate totals for other list endpoints. Standard query parameters: `page` (minimum 1) and `per_page` (1–50, default 20). Comment/notification reads optionally accept an opaque `cursor`; when a cursor was supplied and a full page is returned, meta can contain `next_cursor`.

### Endpoint map

| Method/path | Access | Request | Success `data` / UI use |
|---|---|---|---|
| `GET /home` | public | page/per_page | home sections |
| `GET /content/type/:type`, `/genre/:slug`, `/tag/:slug`, `/search` | public | page/per_page; search uses `q` | `ContentSummary[]`; search response meta includes `q` |
| `GET /search/suggest` | public | `q`; <2 chars yields `[]` | compact content objects |
| `GET /genres`, `/tags` | public | page/per_page | taxonomies |
| `GET /latest-chapters`, `/content/:type/chapters` | public | page/per_page | latest chapter rows |
| `GET /content/:type/:slug` | public, personalized if session exists | — | `ContentDetail` |
| `GET /content/:type/:slug/chapters` | public, personalized if identity present | page/per_page | `ChapterSummary[]` with access |
| `GET /content/:type/:slug/chapter/:chapterNumber` | public, personalized if session exists | — | `ChapterReader`; locked payload deliberately omits body/pages |
| `POST/DELETE /content/:type/:slug/follow` | session + CSRF | — | `{ followed: boolean }` |
| `POST /content/:type/:slug/rate` | session + CSRF | `{ rating: 1..5 }` | `{ rated: true }` |
| `POST /content/:type/:slug/unlock` | session + CSRF | — | series unlock result |
| `POST /chapter/:chapterId/unlock` | session + CSRF | — | chapter unlock result |
| `GET/POST /chapter/:id/comments`, `/content/:type/:slug/comments`, `/blogs/:slug/comments` | GET public; POST session + CSRF | POST `{ body, parent_id? }` | list/comment creation |
| `POST /comments/:id/vote`; `POST /blogs/:slug/comments/:id/vote` | session + CSRF | `{ vote: -1 | 0 | 1 }` | updated vote counts |
| `GET /blogs`, `/blogs/:slug` | public (detail can identify viewer) | page/per_page for list | approved blogs |
| `POST /blogs`, `/blogs/:slug/vote`, `/blogs/image` | session + CSRF | create `{title, body}`; vote `{vote}`; image multipart field `image` | pending blog, vote, `{path}` |
| `GET/POST /auth/*`, `GET/DELETE /auth/sessions/*` | described above | — | auth/session data |
| `GET/POST /user/profile`, `/history`, `/preferences`, `/follows`, `/notifications`, `/wallet`, `/unlocks/*`, `/features*` | session; state changes also CSRF | listed in page specs | account data |
| `GET /profile/:person` | public | `blog_page`, `blog_per_page`, `comment_page`, `comment_per_page` | public profile aggregate |
| `GET /shop/packages`, `/shop/features` | public | packages paginate; features do not | catalog only |
| `POST /user/features/ad-free/purchase` | session + CSRF | — | feature purchase result |

### Important request validation and errors

- `type` is one of the seven exact content types above; otherwise use `400 Invalid content type`.
- Registration: username 3–30 chars, letters/numbers/underscore; email valid; password 8–128 and includes upper/lower/number; duplicate email/username is `409`.
- Comments require a non-empty `body`; reply uses numeric `parent_id`.
- Votes allow only `-1`, `0`, or `1`; rating allows `1` through `5`.
- Unlocks can yield `402 PAYMENT_REQUIRED` with `Insufficient coin balance`; locked content is not an API error—the reader response uses `access.granted: false`.
- Applicable common error keys: `BAD_REQUEST` (400), `UNAUTHORIZED` (401), `PAYMENT_REQUIRED` (402), `FORBIDDEN` (403), `NOT_FOUND` (404), `CONFLICT` (409), `CSRF_INVALID` (419), `RATE_LIMITED` (429), `INTERNAL_ERROR`.

## 8. Data Shapes and Representative Mock Responses

Use ISO-like database datetime strings such as `2026-08-12 14:30:00`; nullable fields must remain `null`, not empty invented values.

```json
{"status":"success","data":{"id":"u8k2m4qz","username":"deniz","email":"deniz@example.test","csrf_token":"0123456789abcdef0123456789abcdef0123456789abcdef","refresh_token":"a-long-opaque-refresh-token-or-null","api_token":"a-long-opaque-api-token","roles":["user"],"permissions":[]},"meta":{},"error":null}
```

This is the login shape. Registration uses the same envelope but its `data` is only `{id,username,email}`. A representative expired-session/insufficient-coin error is:

```json
{"status":"error","data":null,"meta":{},"error":{"code":402,"key":"PAYMENT_REQUIRED","message":"Insufficient coin balance","params":{}}}
```

```json
{
  "status":"success","data":{
    "explore":[{"id":"a1b2c3","title":"The Glass Harbor","slug":"the-glass-harbor","type":"manga","status":"ongoing","rating_avg":4.62,"rating_count":1280,"chapter_count":52,"comment_count":87,"cover_image":"/uploads/covers/glass-harbor.jpg","accent_color":"#2a2a2a","is_followed":false,"author":"M. Kato","artist":"Rin Vale","alternative_titles":null,"country":"Japan","release_year":"2024","description":null,"created_at":null,"type_path":"manga","url_path":"/manga/the-glass-harbor"}],
    "recent_chapters":[{"chapter_number":"52","chapter_title":"Low Tide","series_title":"The Glass Harbor","series_slug":"the-glass-harbor","series_type":"manga","cover_image":"/uploads/covers/glass-harbor.jpg","created_at":"2026-08-10 08:00:00","type_path":"manga","slug":"the-glass-harbor"}],
    "recently_added":[],"popular_blogs":[],"latest_blogs":[]
  },"meta":{"page":1,"per_page":20},"error":null
}
```

```json
{
  "status":"success","data":{
    "id":"a1b2c3","title":"The Glass Harbor","slug":"the-glass-harbor","type":"manga","status":"ongoing","rating_avg":4.62,"rating_count":1280,"chapter_count":52,"comment_count":87,"cover_image":"/uploads/covers/glass-harbor.jpg","accent_color":"#2a2a2a","is_followed":true,"author":"M. Kato","artist":"Rin Vale","alternative_titles":"Glass Port","country":"Japan","release_year":"2024","description":"A long atmospheric synopsis.","created_at":"2026-01-05 10:00:00","series_genres":[{"name":"Fantasy","slug":"fantasy","ui_config":{"icon":"bi-stars"}}],"series_tags":[{"name":"Sea","slug":"sea","ui_config":{}}],"reading_progress":{"last_chapter_id":"ch52aa","last_page":14,"updated_at":"2026-08-10 09:00:00"},"series_unlock_price":0,"is_series_unlocked":false,"has_any_premium":true,"type_path":"manga","url_path":"/manga/the-glass-harbor"
  },"meta":{},"error":null
}
```

```json
{
  "status":"success","data":[
    {"id":"ch52aa","content_id":"a1b2c3","chapter_number":"52","title":"Low Tide","type":"image","created_at":"2026-08-10 08:00:00","body":null,"pages":[],"adjacent_chapters":{"next":null,"prev":"51"},"access":{"granted":false,"reason":"chapter_unlock_required","series_unlock_price":0,"chapter_unlock_price":12,"is_series_unlocked":false,"is_chapter_unlocked":false,"is_free":false,"requires_series_unlock":false,"requires_chapter_unlock":true},"price_coin":12,"is_locked":true},
    {"id":"ch51aa","content_id":"a1b2c3","chapter_number":"51","title":null,"type":"image","created_at":"2026-08-03 08:00:00","body":null,"pages":[],"adjacent_chapters":{"next":null,"prev":null},"access":{"granted":true,"reason":"granted","series_unlock_price":0,"chapter_unlock_price":0,"is_series_unlocked":false,"is_chapter_unlocked":false,"is_free":true,"requires_series_unlock":false,"requires_chapter_unlock":false},"price_coin":0,"is_locked":false}
  ],"meta":{"page":1,"per_page":50},"error":null
}
```

```json
{
  "status":"success","data":{"id":"ch51aa","content_id":"a1b2c3","chapter_number":"51","title":null,"type":"image","created_at":"2026-08-03 08:00:00","body":null,"pages":[{"image_path":"/uploads/chapters/a1b2c3/51/001.webp","page_order":1},{"image_path":"/uploads/chapters/a1b2c3/51/002.webp","page_order":2}],"adjacent_chapters":{"next":"52","prev":"50"},"access":{"granted":true,"reason":"granted","series_unlock_price":0,"chapter_unlock_price":0,"is_series_unlocked":false,"is_chapter_unlocked":false,"is_free":true,"requires_series_unlock":false,"requires_chapter_unlock":false},"price_coin":0,"is_locked":false},"meta":{},"error":null
}
```

For a text reader change `type` to `text`, set `body` to a long plain-text chapter string, and set `pages: []`. For a locked reader set `body: null`, `pages: []`, `is_locked: true`, and the access object above.

```json
{"status":"success","data":[{"id":1,"name":"Action","slug":"action","ui_config":{"icon":"bi-fire"}}],"meta":{"page":1,"per_page":20},"error":null}
```

Tags have the same fields plus `content_count`. Search suggestions are `{id,title,slug,type,cover_image}`; search results are `ContentSummary[]` and meta is `{q,page,per_page}`.

```json
{"status":"success","data":{"id":"u8k2m4qz","username":"deniz","email":"deniz@example.test","bio":"Reader and reviewer.","profile_image":null,"cover_image":"/uploads/profiles/deniz-cover.jpg","created_at":"2026-01-01 12:00:00","is_guest":false},"meta":{},"error":null}
```

Guest profile is an explicit successful shape: `is_guest:true`, `id:null`, `username:"guest"`, and email/bio/images/created_at all `null`.

```json
{"status":"success","data":{"lang":"tr","theme":"dark","reader":{"layout":"vertical","fontSize":"18","fontFamily":"var(--font-sans)","lineHeight":"1.8","fontWeight":"400","readingDirection":"ltr","imageFit":"width"},"account":{"is_logged_in":true,"email":"deniz@example.test","last_sync":"2026-08-12T10:00:00Z"}},"meta":{},"error":null}
```

Allowed preferences: `lang`: `tr|en`; `theme`: `default|dark|royal|bootstrap|material|apple|glass`; reader `layout`: `vertical|single|double`; `fontSize`: 12–32; `fontFamily`: `var(--font-sans)|serif|var(--font-mono)`; `lineHeight`: 1.2–3.0 in 0.1 increments; `fontWeight`: `300|400|600`; `readingDirection`: `ltr|rtl`; `imageFit`: `width|height|original`.

```json
{"status":"success","data":[{"chapter_id":"ch51aa","read_at":"2026-08-11 21:00:00","chapter_number":"51","chapter_title":null,"content_slug":"the-glass-harbor","content_title":"The Glass Harbor"}],"meta":{"page":1,"per_page":20},"error":null}
```

```json
{"status":"success","data":[{"id":42,"type":"comment_vote","title":"New vote","body":"Your comment received an upvote.","data":"{\"comment_id\":101}","is_read":0,"created_at":"2026-08-12 09:00:00","actor_user_id":"f7g8h9j0","actor_username":"mira"}],"meta":{"page":1,"per_page":20},"error":null}
```

`data` on notifications is a raw JSON string in the current API; parse defensively only for presentation, retaining raw fallback.

```json
{"status":"success","data":{"user_id":"u8k2m4qz","balance_coin":180,"total_coin_purchased":300,"total_coin_spent":120,"updated_at":"2026-08-12 09:00:00"},"meta":{},"error":null}
```

Wallet transaction: `{id: 17, type:"chapter_unlock", coin_delta:-12, balance_after:180, reference_type:"chapter", reference_id:"ch52aa", description:"Unlocked chapter 52", metadata:"{\"content_id\":\"a1b2c3\"}", created_by:null, created_at:"..."}`. Include positive `package_credit` / `manual_credit` and negative `series_unlock`, `chapter_unlock`, `feature_unlock`, and `manual_debit` rows. Wallet list meta includes `{page,per_page,total}`.

Shop package: `{id:1,name:"Explorer Pack",coin_amount:100,bonus_coin:10,display_price:"49.90",currency:"TRY",is_active:1,sort_order:1,created_at:"...",updated_at:"..."}`. Feature product: `{feature_key:"ad_free",name:"Reklamsiz Deneyim",coin_price:45,duration_days:30,is_active:1,updated_at:"..."}`. Feature status is keyed: `{ "ad_free": {feature_key:"ad_free",active:true,starts_at:"...",expires_at:"..."} }`.

Series unlock row: `{id:5,content_id:"a1b2c3",content_title:"The Glass Harbor",content_slug:"the-glass-harbor",content_type:"manga",price_coin:75,transaction_id:16,unlocked_at:"..."}`. Chapter unlock adds `chapter_id`, `chapter_number`, and nullable `chapter_title`. Entitlement row is `{id,feature_key,source_type,source_id,transaction_id,starts_at,expires_at,created_at}`.

```json
{"status":"success","data":{"id":"b1c2d3","user_id":"u8k2m4qz","title":"Why image pacing matters","slug":"why-image-pacing-matters","body":"Long blog body in plain text/markdown-like source.","approved":1,"approver_user_id":"a8d1m2n3","approved_at":"2026-08-01 10:00:00","created_at":"2026-07-30 10:00:00","updated_at":"2026-08-01 10:00:00","author_username":"deniz","approver_username":"editor","upvote_count":12,"downvote_count":1,"my_vote":1},"meta":{},"error":null}
```

Blog lists omit vote counts and viewer vote. A created blog is pending (`approved: false`); render a review-pending badge in My Blogs.

Comment list item: `{id:101,body:"Great chapter.",parent_id:null,upvote_count:5,downvote_count:1,created_at:"2026-08-12 08:00:00",user_id:"f7g8h9j0",username:"mira",profile_image:null,my_vote:1}`. `deleted_at` exists in storage but deleted comments are filtered from read lists, so a public deleted-comment display state is **UNKNOWN / REQUIRES VERIFICATION**; do not invent it. A creation response is `{comment_id:101}`. Vote response contains updated counts and `my_vote`; use a compatible mock shape `{comment_id,upvote_count,downvote_count,my_vote}`.

Public profile has `user` (id, username, bio, profile_image, cover_image, created_at), `stats` (including `score`, follower/following counts, vote/comment/blog counts), `blogs`, and `recent_comments`. Exact aggregate wrapper details should be treated as **UNKNOWN / REQUIRES VERIFICATION**; keep this screen driven by a typed mock profile aggregate and do not claim an unverified field as a real API guarantee.

## 9. Global Layout and Navigation

- Desktop: sticky top header with wordmark, Browse dropdown (all seven types), Genres, Tags, Blogs, search trigger, wallet balance chip for authenticated users, notification badge, and avatar menu. Do not make Chat a primary navigation item: legacy UI has a chat page but no confirmed API/implementation contract for it.
- Browse pages use a main content column plus optional right rail for taxonomy chips or compact latest chapters. Reader never uses the standard page rail.
- Mobile: compact sticky header, full-screen search overlay, and bottom navigation: Home, Browse, Library (or Login), Notifications, Profile. Put wallet, preferences, and logout in the profile/menu surface. Do not hide reader navigation behind the bottom bar.
- Search suggestions appear in an anchored combobox after two characters; keyboard arrows, Enter, Escape, and a clear control are required.
- Use a reusable `ContentCard` (cover, type badge, title, author/artist when present, rating, chapter count, status), `ChapterRow`, `Pagination`, `EmptyState`, `ErrorState`, `LoginPrompt`, `LockPanel`, `CommentThread`, and `WalletBalance`.

### Field-to-UI rules

| Field | Presentation / interaction |
|---|---|
| `cover_image` | card/detail cover; use neutral gradient + title initial when null |
| `title` | primary heading/card title; never truncate detail heading, line-clamp cards |
| `type`, `status` | compact badges; preserve raw enum value, title-case label |
| `rating_avg`, `rating_count` | star score plus count; show `New / no ratings` if count is 0 |
| `chapter_count`, `comment_count` | subdued metadata, not primary CTA |
| `series_genres`, `series_tags` | linkable chips to taxonomy result pages |
| `reading_progress` | Resume CTA; use `last_chapter_id` only to locate an available mock chapter |
| `is_followed` | toggle label `Follow`/`Following`; optimistic mock update with rollback error state |
| `access` and `is_locked` | lock icon, price, access explanation, and unlock CTA only for authenticated users |
| `body` | semantic prose, controlled reader typography/direction; never render HTML unsafely |
| `pages` | ordered by `page_order`, image alt `Chapter {number}, page {n}` |
| `coin_delta` | signed green/red amount, then `balance_after`; preserve transaction `type` as a badge |
| `approved` | My Blogs status badge; approved posts link public, pending posts do not appear public |
| `is_read` | unread emphasis and notification dot; `0/1` must be normalized as boolean |
| `profile_image`, `cover_image` | avatar/profile banner with robust null fallback |

## 10. Page Specifications

### PAGE: Home

**Access:** public. **Dependencies:** home mock. **Layout:** hero/featured `explore` card first, then horizontal/compact recent chapter feed, responsive card grid for recently added, and two small blog columns. **Actions:** open content/chapter/blog; type browse. **States:** skeleton per section, independently empty sections, whole-page retry error. On mobile, use horizontal card scrollers where appropriate and keep tap targets 44px minimum.

### PAGE: Browse, Genre, Tag, and Search

**Access:** public. **Dependencies:** respective content list/taxonomy/search mocks. **Layout:** page heading, active filters, grid/list toggle locally only, content cards, and page pagination. Search has the query field, suggestion combobox, selectable genre/tag chips, status filter, and sort values exactly `EN YENİLER`, `EN ÇOK OKUNAN`, `EN YÜKSEK PUAN`; use `TÜMÜ` as the unfiltered status sentinel. The confirmed search request only guarantees `q`; real filtered-search integration is **UNKNOWN / REQUIRES VERIFICATION**. Filters may operate against mock data for prototype exploration. **Empty:** query-specific no-results action to clear filters. Taxonomy directory card displays `name`; tags additionally display `content_count`.

### PAGE: Content Detail

**Access:** public; personal fields vary by auth. **Dependencies:** content detail, first chapter list, series comments. **Layout:** cover/metadata hero; description; primary `Start reading` or `Resume`; secondary follow; rating control only after login; genre/tag chips; compact access summary; recent chapter list; comments below. Show author, artist, alternative titles, country, release year only when non-null. First readable route must be derived from a chapter number, not an invented chapter ID route. If series-level price is >0, show its price and `Unlock series`; if the price is 0 but some chapter is premium, explain that individual chapters may require coins. Do not claim all chapters unlock when series is free.

### PAGE: Chapter List

**Access:** public. **Dependencies:** chapters endpoint. **Layout:** content title/back link, descending chapter rows, page pagination. Each row shows chapter number, nullable title, type (`text`/`image` as a secondary badge), date, and either `Read`, `Free`, `Unlocked`, `12 coins`, or `Locked` according to access. Locked click opens `LockPanel`, not the chapter payload. Include both free and locked examples.

### PAGE: Reader

**Access:** public; content visibility is access-controlled in payload. **Dependencies:** reader endpoint, chapter comments; mock unlock action. **Layout:** reduced chrome: back-to-series, title, chapter selector (a local list from chapters mock), reader settings drawer, previous/next buttons both top and bottom. `image` reader has a centered max-width page stack; `text` reader has a comfortable text column and respects typography/direction preferences. `vertical`, `single`, and `double` affect image layout; for text retain readable single-column prose. `imageFit` controls width/height/original styling. On locked response replace all reading content with lock panel, price, wallet balance, unlock action, and shop/login links. Prev/next are disabled for null neighbors. Comments remain after reader content/lock panel.

### PAGE: Login and Register

**Access:** guests only. **Dependencies:** auth mock. **Layout:** narrow, labelled form cards with link between pages. Login: email, password, remember checkbox, mock security token. Register: username, email, password, mock security token; state clearly that registration does not sign in automatically, then route to Login after success. Show field errors next to fields and rate-limit/general errors above submit. Never put tokens in visible UI.

### PAGE: Account Overview and Public Profile

**Access:** `/me` session required; `/profile/:person` public. **Dependencies:** profile/wallet/features; public profile. **Layout:** banner/avatar identity, bio, dates, compact reputation/social stats; tabs/links to Library, History, My Blogs, Wallet, Preferences. `/me` supplies edit profile form with `bio`, `profile_image`, `cover_image` (mock file/url controls); email is display-only. Public profile has Follow/Unfollow only for a different authenticated viewer, then approved blog and recent-comment lists. Do not pretend the public API proves whether the viewer follows this profile; model this as mock-only UI state.

### PAGE: Library and History

**Access:** session required. **Dependencies:** follows/history. **Layout:** library is a content-card grid with remove-follow action; history is chronological rows with content title, chapter number/title, read timestamp, and Resume CTA. History response omits `content_type`; a real type-aware resume URL cannot be guaranteed from this endpoint alone. In mock data add a UI-only resolved type only at the mock adapter boundary and label the real mapping **UNKNOWN / REQUIRES VERIFICATION** in developer documentation, not UI.

### PAGE: Notifications

**Access:** session required. **Dependencies:** notifications and mark-read. **Layout:** header with `Mark all read`, chronological rows, unread marker, actor avatar fallback, title/body/time. Parse notification `data` defensively for a contextual destination only if it contains a known route target; otherwise no link. Support cursor `Load more` and regular page pagination mock variants.

### PAGE: Preferences and Sessions

**Access:** session required. **Dependencies:** preferences and sessions. **Layout:** sections for Language, Theme, Text reader, Image reader, Account/session. Use controls only for allowed values listed above. Theme can apply live locally; Save persists to mock. Sessions show user agent, created, last seen, expiry, and a revoke action; allow the mock current session to be revoked and then transition to logged out.

### PAGE: Wallet and Shop

**Access:** wallet session required; shop public. **Dependencies:** wallet, transactions, series/chapter unlocks, entitlements; package/features; ad-free purchase mock. **Layout:** wallet balance hero, purchased/spent lifetime stats, tabs for transactions/unlocks/features, then shop CTA. Transactions are paginated. Unlocks link to known content/chapter mock routes. Shop presents package cards with coins, bonus, display price/currency and no fabricated checkout; feature cards show ad-free price/duration/active status. Logged-in user can run mock `Purchase ad-free`; insufficient balance must show a `402`-style inline error, not a false success.

### PAGE: Blogs and Blog Detail

**Access:** list/detail public; create/my blogs session required. **Dependencies:** blogs, detail, comments, votes, creation. **Layout:** editorial list cards with title, author, date, excerpt computed in UI from body, no assumed cover field (although schema has one, public blog queries do not return it). Detail renders safe plain text/markdown-like formatting, author link, vote controls/counts, and comment thread. New Blog has title/body; after mock submit show pending review and route to My Blogs. A blog detail whose mock data is not approved must yield not-found on public route.

### PAGE: Comments (embedded)

**Access:** reads public; composing/voting session required and can be restricted by server. **Dependencies:** context-specific list/create/vote. **Layout:** composer, ordered top-level comments, one-level visually indented replies, votes, Reply action. Read lists contain parent IDs but no guaranteed nested payload; construct the tree client-side. Guest action opens LoginPrompt. Include composer disabled/forbidden state to demonstrate `403` restrictions. Do not display a deleted comment state as real functionality.

## 11. Component Architecture

```text
src/
  app/                 router, providers, layouts
  pages/               route-level pages only
  components/
    navigation/        Header, MobileNav, SearchCombobox, AccountMenu
    content/           ContentCard, ContentHero, ChapterRow, TaxonomyChips
    reader/            ReaderChrome, ImageReader, TextReader, ReaderSettings, LockPanel
    account/           ProfileHeader, WalletBalance, TransactionList, SessionList
    social/            BlogCard, VoteControl, CommentThread, CommentComposer
    feedback/          Skeleton, EmptyState, ErrorState, LoginPrompt, Pagination
    ui/                Button, Badge, Dialog, FormField
  services/            contracts.ts, mock/, adapters/ (no real implementation now)
  mocks/               fixtures, scenarios, mockApi.ts
  types/               api.ts, domain.ts
  contexts/            AuthContext, PreferencesContext
```

Components receive typed data and callbacks, not direct mock imports. Keep `MockContentService`, `MockAuthService`, `MockUserService`, `MockWalletService`, and `MockBlogService` behind interfaces so a future API adapter can replace them without rewriting pages.

## 12. State, Mock Data, and Error Matrix

Create named scenarios selectable in development (for example query string `?scenario=locked` or a small dev-only switcher): normal guest, authenticated, session-expired, empty, network-error, forbidden-commenting, insufficient-coins, free chapter, chapter-unlocked, series-unlocked, locked chapter, image reader, text reader, long-content, and large-list/pagination.

- Lists: loading skeleton; empty description + contextual CTA; error with Retry; results with pagination; unauthorized LoginPrompt where required.
- Detail/reader: loading layout skeleton; 404 not-found; locked is a normal content state; `402` insufficient-balance dialog; `401` sign-in prompt; `403` disabled action explanation.
- Forms: submitting disabled state; inline validation errors; server error banner; optimistic follow/vote only with rollback on mock rejection.
- Image failures: show page-level image placeholder and retry, preserving other pages.
- Long titles/descriptions/bodies must wrap without overflow. Large chapter/comment/transaction lists must remain responsive.

Use realistic fixtures from the actual domain above: include at least manga image chapters and a light novel or web novel text chapter; statuses `ongoing`, `completed`, `hiatus`, `dropped`; null cover/title/artist scenarios; two users; a positive and negative wallet transaction; `ad_free` active and expired/inactive variants; a pending blog; replies and all three vote states; free and all lock states.

## 13. Responsive Rules

- **Desktop (>=1024px):** max-width content container, 3–5 content card columns depending on width, optional right rail, sticky header.
- **Tablet (640–1023px):** 2–3 card columns, controls wrap, right rail moves below primary content.
- **Mobile (<640px):** one/two narrow card columns depending on card minimum width; no persistent desktop nav; bottom nav; filters in a modal/drawer; fixed reader chrome only while useful and never over text/images.
- Reader image stack has no horizontal scrolling at `width`; `original` may permit deliberate horizontal pan only inside its page viewport. Text prose max-width remains readable on all sizes.
- Dialogs become bottom sheets on mobile. Keep all actions keyboard reachable on desktop and within safe visual areas on mobile.

## 14. Accessibility

- Semantic headings, lists, `nav`, `main`, `article`, and labelled forms.
- Visible focus rings, logical tab order, Escape closes overlays, focus returns to trigger.
- Buttons use labels; icon-only controls require `aria-label`.
- Search uses combobox/listbox semantics; alerts/errors announce with `aria-live`.
- Cover/avatar/page images have meaningful alt text or empty alt when decorative.
- Maintain sufficient contrast in every theme and do not encode locked/unread/vote state by color alone.

## 15. Build Requirements and Acceptance Criteria

- `npm install` followed by the project dev command starts without runtime console errors.
- The app has no real network/API calls and runs entirely from mocked, typed services.
- Every route in the Route Map is navigable with fixtures; guarded routes show an intentional guest state.
- Home, browse/search, detail, chapter list, image/text reader, login/register, account, library/history, notifications, preferences/sessions, wallet/shop, blogs, comments, and public profile have loading, empty, error, and relevant auth/lock states.
- Mock response envelopes, fields, IDs, enum values, nullable data, access state, and pagination follow this document.
- Free, chapter-locked, series-locked, individually unlocked, and insufficient-coin flows are visible and behave consistently.
- No admin screens, payment checkout, or unverified features are added.
- UI is responsive across desktop/tablet/mobile and meets the accessibility rules above.
- Keep all real-API integration placeholders isolated behind service interfaces; do not implement them in this phase.

# EXISTING UI REFERENCE — storage/views/

Before implementing the new React UI, inspect the existing server-rendered UI under:

storage/views/

This directory is an important reference source for understanding the current application's actual user-facing screens and flows.

## 1. Purpose

Use `storage/views/` to determine:

- which screens currently exist
- which user flows are currently exposed
- which data is displayed on each screen
- how data is grouped
- which actions are available
- which forms exist
- which navigation items exist
- which states are represented
- which content types have dedicated UI
- which components/patterns are reused
- which responsive/mobile behaviors are already implemented
- which server-rendered pages correspond to which backend functionality

Do NOT simply reproduce the existing HTML/CSS design.

The existing UI is a functional reference, not a visual design reference.

The goal is:

Existing UI behavior
        ↓
Understand functionality and information
        ↓
Reinterpret the information
        ↓
Create a significantly better React UI

---

# 2. Read ALL Relevant Views

Do not inspect only the obvious homepage templates.

Read the entire `storage/views/` structure and identify:

- layouts
- partials
- components
- pages
- authentication views
- content views
- chapter/reader views
- profile views
- wallet views
- blog views
- comment-related views
- error views
- empty states
- forms
- navigation
- mobile-specific templates
- reusable view fragments

If a view includes another partial/template, inspect that partial as well.

Build a mental map of:

```text
Layout
 ├── Header
 ├── Navigation
 ├── Main
 ├── Sidebar
 ├── Footer
 └── Shared components
```

and:

```text
Page
 ├── data displayed
 ├── actions
 ├── forms
 ├── links
 └── states
```

---

# 3. Create a View Inventory

Before coding, create an internal inventory of the existing views.

For each relevant view determine:

```text
View file
Purpose
Route/page
User type
Displayed data
Available actions
Forms
Links/navigation
Included partials
Related API/backend functionality
```

Do not expose this as unnecessary documentation inside the final UI unless useful.

Use it to make sure the new React UI does not accidentally remove existing functionality.

---

# 4. Compare Views With the Existing UI Specification

Compare the information found in `storage/views/` against the current UI implementation specification.

Look specifically for functionality that the specification may have missed.

Examples:

* an action available in an existing template
* an additional metadata field
* a secondary navigation path
* a filter
* a sorting option
* a form
* a modal
* a reader control
* a user action
* an authentication state
* an empty/error state
* a special content type
* a mobile-specific interaction

If something important exists in `storage/views/` but is missing from the current specification, incorporate it into the implementation where it is confirmed to be part of the product.

Do not blindly preserve obsolete or duplicated legacy behavior.

---

# 5. Inspect Existing Information Hierarchy

For each important page, identify how the current UI groups information.

For example:

```text
Content Detail

Identity
 ├── Cover
 ├── Title
 ├── Alternative titles
 └── Type

Metadata
 ├── Author
 ├── Artist
 ├── Status
 ├── Rating
 └── Release information

Taxonomy
 ├── Genres
 └── Tags

Actions
 ├── Read
 ├── Follow
 └── Rate

Content
 ├── Description
 └── Chapters

Community
 └── Comments
```

Use this information to improve the new layout.

Do not assume the existing grouping is optimal.

---

# 6. Inspect Existing Forms

Pay particular attention to forms.

Identify:

* fields
* labels
* placeholders
* validation messages
* required fields
* optional fields
* submit actions
* secondary actions
* authentication requirements
* error presentation

The new React UI should preserve confirmed functionality while improving the visual and interaction design.

---

# 7. Inspect Existing Reader Views Carefully

The reader is one of the most important parts of the application.

Read all existing reader-related templates.

Determine:

* image reader behavior
* text reader behavior
* chapter navigation
* previous/next controls
* chapter selector
* reading settings
* font settings
* image fitting
* reading direction
* layout modes
* comments
* locked chapter behavior
* authentication prompts
* unlock behavior
* mobile reader behavior

Do not redesign the reader based only on assumptions.

Use the existing reader templates together with the API specification to reconstruct the actual product behavior.

The new reader may look completely different, but it must not accidentally remove confirmed functionality.

---

# 8. Inspect Mobile-Specific Views

If `storage/views/` contains mobile-specific templates, partials, or conditional layouts, inspect them separately.

Determine whether they represent:

* genuinely different navigation
* different information hierarchy
* different controls
* different reader behavior
* mobile-only actions

Do not simply shrink the desktop UI to mobile.

Use confirmed mobile behavior as input for the new responsive design.

---

# 9. Inspect Existing UI States

Search the views for representations of:

```text
loading
empty
error
not found
unauthorized
forbidden
locked
unlocked
guest
authenticated
success
validation error
```

If the existing views demonstrate a state that is not currently represented in the mock UI, add that state where appropriate.

---

# 10. Do Not Copy Legacy Visual Design

Do NOT copy:

* legacy colors
* old typography
* old spacing
* old card styling
* old navigation styling
* old CSS structure
* old Bootstrap/Framework7 appearance
* old HTML structure

unless a behavior or accessibility requirement specifically depends on it.

The new React UI should have its own modern design system.

Use the existing views primarily to understand:

```text
WHAT exists
WHAT data is shown
WHAT the user can do
WHEN the user can do it
WHAT happens in different states
```

not:

```text
HOW the old UI looks
```

---

# 11. Existing UI vs New UI Decision Rule

For every element found in `storage/views/`, classify it as:

### PRESERVE

Confirmed functionality that must exist in the new UI.

### IMPROVE

Confirmed functionality that should exist, but whose presentation/interaction should be redesigned.

### OMIT

Legacy, duplicated, obsolete, or irrelevant UI that should not be carried into the new application.

### UNKNOWN

Something found in the template whose backend/product behavior cannot be confirmed.

Do not invent behavior for UNKNOWN items.

---

# 12. Final Requirement

Before considering the UI implementation complete, verify:

> Have I read the relevant `storage/views/` templates and ensured that the new React UI does not accidentally lose a confirmed user-facing capability?

The new UI should represent the **real functionality discovered in the existing views**, while using the new design system and layout defined in this specification.

The old UI is a reference for product behavior.

The new UI is a redesign.
