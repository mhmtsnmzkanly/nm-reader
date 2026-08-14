# NM-Reader SEO & Structured Data Contract

**Version:** 1.0.0  
**Status:** CANONICAL SPECIFICATION  
**Scope:** Server-side metadata generation, HTML shell placeholder injection, JSON-LD structured data schemas, social graph tags, security, and route indexability policies.

---

## 1. SEO Architecture

The NM-Reader SEO architecture operates strictly server-side through the PHP entry layer and `SeoService`. Metadata is injected into the HTML document head before serving the React Single Page Application (SPA) shell:

```
Browser / Crawler Request
          │
          ▼
PHP Router (`Config.php`)
          │
          ▼
`WebController` (Aggregates domain data)
          │
          ▼
`SeoService` (Sanitizes & generates title, meta, OG, Twitter, JSON-LD)
          │
          ▼
`public/app.html` (Replaces placeholders, eliminates duplicate tags)
          │
          ▼
Complete, Crawler-Ready HTML Document Response
          │
          ▼
React Client Hydration / CSR
```

**Key Principle:** React client components do **NOT** modify or render SEO `<head>` tags. All search engine and social crawler metadata is generated server-side in PHP to ensure instant indexing, optimal Core Web Vitals, and zero JavaScript rendering penalty.

---

## 2. app.html Slot System & Replacement

The React build artifact ([`public/app.html`](file:///home/duldul/Belgeler/nm-reader/public/app.html)) defines standard comment placeholders:

| Placeholder | Injected Elements |
|:---|:---|
| `<!-- SEO:TITLE -->` | `<title>{Escaped Title}</title>` |
| `<!-- SEO:META -->` | `<meta name="description" content="..." />`<br>`<meta name="robots" content="..." />` |
| `<!-- SEO:CANONICAL -->` | `<link rel="canonical" href="..." />` (omitted if empty) |
| `<!-- SEO:OG -->` | `og:title`, `og:description`, `og:url`, `og:image`, `og:type`, `og:site_name` |
| `<!-- SEO:TWITTER -->` | `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` |
| `<!-- SEO:JSONLD -->` | `<script type="application/ld+json">{Valid JSON-LD}</script>` |

### Placeholder Hygiene
1. `SeoService::renderShell()` strips any default static title/meta tags to prevent duplicates.
2. After injection, a cleanup regex (`/<!--\s*SEO:[A-Z_]+\s*-->/`) removes any remaining unreplaced placeholder comments.

---

## 3. Title Generation Strategy

Titles are formatted dynamically per route type with the site brand suffix:

- **Homepage:** `{Site Name} — Novel & Manga Okuma Platformu`
- **Content Detail:** `{Series Title} - {Type} Oku — {Site Name}`
- **Reader:** `{Series Title} - Bölüm {Number} — {Site Name}`
- **Blog Listing:** `Blog — {Site Name}`
- **Blog Detail:** `{Post Title} - Blog — {Site Name}`
- **Search Results:** `Arama: {query} — {Site Name}`
- **Public Profile:** `{Username} — {Site Name}`
- **Private Profile:** `Profilim — {Site Name}`
- **404 Error:** `Error 404 — {Site Name}`

All titles pass through `htmlspecialchars($title, ENT_QUOTES, 'UTF-8')`.

---

## 4. Meta Description Policy

- **Content Detail:** Truncated synopsis (max 160 characters, word boundary safe).
- **Blog Detail:** Excerpt / summary.
- **Homepage:** Canonical site description (`site_description`).
- **Reader:** `Read {Series Title} chapter {Number} online.`
- **Sanitization:** All HTML tags are stripped (`strip_tags`) and whitespace collapsed.

---

## 5. Canonical URL Generation

- **Source:** Canonical URLs are resolved using the trusted application base URL config (`app.url` / request scheme + host).
- **Query Strings:** Query parameters (`?q=...`, `?page=...`) are excluded from canonical URLs unless explicitly intended.
- **Normalization:** Paths are lowercase, trimmed, and protocol-relative paths are resolved against the active scheme.

---

## 6. Robots & Indexability Matrix

| Route Category | Example Path | Robots Directive | Indexable? | Rationale |
|:---|:---|:---|:---:|:---|
| **Home** | `/`, `/tr`, `/en` | `index, follow` | YES | Public primary landing |
| **Browse / Listing** | `/browse`, `/manga`, `/novel` | `index, follow` | YES | Public category directory |
| **Taxonomy** | `/genre/{slug}`, `/tag/{slug}` | `index, follow` | YES | Public taxonomy archives |
| **Content Detail** | `/manga/solo-leveling` | `index, follow` | YES | High-value search landing |
| **Chapter Reader** | `/manga/solo-leveling/chapter/1` | `index, follow` | YES | Chapter content discovery |
| **Blog Listing** | `/blogs` | `index, follow` | YES | Editorial articles |
| **Blog Detail** | `/blogs/top-10-manhwa` | `index, follow` | YES | Editorial article landing |
| **Public User Profile**| `/profile/sungjinwoo` | `index, follow` | YES | Public community creator profile |
| **Search** | `/search?q=solo` | `noindex, follow` | NO | Prevents infinite faceted search bloat |
| **Private Profile** | `/profile` (me) | `noindex, nofollow` | NO | User personal dashboard |
| **Library / History** | `/library`, `/history` | `noindex, nofollow` | NO | User private telemetry |
| **Wallet / Shop** | `/wallet`, `/shop` | `noindex, nofollow` | NO | Financial / transactional |
| **Auth** | `/login`, `/register` | `noindex, nofollow` | NO | Authentication entry points |
| **404 / 500** | `/invalid-url` | `noindex, nofollow` | NO | Error state |

---

## 7. Open Graph Protocols

- `og:site_name`: Configured site name (`NM-Reader`).
- `og:type`: `website` (Home, Browse), `book` / `article` (Series, Chapters, Blogs).
- `og:title`: Route title.
- `og:description`: Route meta description.
- `og:url`: Route canonical URL.
- `og:image`: Public media URL (`/media/public/cover.*.webp`). **Protected chapter tokens are strictly forbidden.**

---

## 8. Twitter Cards

- `twitter:card`: `summary_large_image`.
- `twitter:title`: Route title.
- `twitter:description`: Truncated description.
- `twitter:image`: Public media URL only.

---

## 9. Structured Data (JSON-LD) Schemas

JSON-LD blocks are generated as valid Schema.org entities and encoded using `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`:

### Supported Schemas
1. **`WebSite`** (Homepage): Includes `potentialAction` (`SearchAction`) with search query template.
2. **`CreativeWorkSeries` / `Book`** (Content Detail): Includes title, description, cover image, genres, author entity, and rating aggregates.
3. **`BlogPosting`** (Blog Detail): Includes headline, description, cover image, publication timestamp, and author person entity.
4. **`BreadcrumbList`** (All Public Pages): Hierarchical list items with 1-based positioning.

When multiple schemas exist on a page (e.g. `CreativeWorkSeries` + `BreadcrumbList`), they are combined into a clean Schema.org `@graph` container.

---

## 10. Security & Protected Token Isolation

1. **XSS Protection:** All user-supplied inputs (titles, synopses, author names) pass through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
2. **Token Leakage Guard:** `SeoService::sanitizeMediaUrl()` strips any URL matching `/media/chapter/` or starting with `t_`. Temporary HMAC chapter tokens **NEVER** appear in HTML head, Open Graph, Twitter cards, or JSON-LD structures.
3. **Host Header Validation:** Canonical URLs rely on validated server configuration rather than untrusted client Host headers.

---

## 11. Automated Verification

- **Automated Test Suite:** [`app/Console/run_seo_test.php`](file:///home/duldul/Belgeler/nm-reader/app/Console/run_seo_test.php)
- **Execution:** `composer test:seo`
- **Result:** **32 / 32 PASS (100% Green)**
