# NM-Reader Frontend API Client Contract

**Version:** 1.0.0  
**Status:** CANONICAL SPECIFICATION  
**Scope:** Architecture, transport, envelope normalization, authentication, CSRF retry, media resolution, and domain services for the React CSR frontend (`ui/src/api/`).

---

## 1. Architecture Overview

The NM-Reader frontend adopts a strict 3-tier service architecture to decouple UI rendering from low-level network transport:

```
┌─────────────────────────────────────────────────────────────┐
│                    React UI Components                      │
│         (Pages, Modals, Forms, Readers, Library)            │
└──────────────────────────────┬──────────────────────────────┘
                               │ (Calls domain interfaces)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   Domain Service Layer                      │
│   (authService, contentService, blogService, walletService) │
└──────────────────────────────┬──────────────────────────────┘
                               │ (Invokes HTTP operations)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                  Centralized API Client                     │
│    (HttpClient: CSRF injection, 419 retry, Envelope parse)  │
└──────────────────────────────┬──────────────────────────────┘
                               │ (Native Fetch + credentials)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│               Backend REST API (/api/v1/*)                  │
└─────────────────────────────────────────────────────────────┘
```

**Key Principle:** React UI components **NEVER** invoke `fetch` directly. All network requests flow through domain services and the centralized `HttpClient`.

---

## 2. API Client Abstraction

- **Location:** [`ui/src/api/`](file:///home/duldul/Belgeler/nm-reader/ui/src/api/)
- **Core Files:**
  - `client.ts`: `HttpClient` class and singleton `apiClient`.
  - `types.ts`: Protocol types, request options, envelope generics.
  - `errors.ts`: `ApiClientError`, `NetworkError`, `TimeoutError`.
  - `config.ts`: Base URL and timeout configuration.
  - `auth.ts`: In-memory CSRF token cache, auth listeners, refresh locking.
  - `media.ts`: Media URL resolver (`resolveMediaUrl`, `isProtectedMedia`).
  - `services/*`: Domain-specific API services.

---

## 3. Base URL & Environment Configuration

Configuration is managed via [`ui/src/api/config.ts`](file:///home/duldul/Belgeler/nm-reader/ui/src/api/config.ts):

- **Default Base URL:** `/api/v1` (relative path matching same-origin production deployment).
- **Environment Override:** `VITE_API_BASE_URL` (e.g. `http://localhost:8080/api/v1` for local decoupled dev).
- **Default Timeout:** 15,000 ms.
- **Credentials:** `credentials: "include"` enabled by default on all requests.

---

## 4. Request Handling

The `HttpClient` provides typed helper methods:
- `apiClient.get<T>(endpoint, options)`
- `apiClient.post<T>(endpoint, body, options)`
- `apiClient.put<T>(endpoint, body, options)`
- `apiClient.patch<T>(endpoint, body, options)`
- `apiClient.delete<T>(endpoint, options)`

### Query Serialization
URL search parameters passed via `options.params` are automatically filtered (ignoring `null` and `undefined`) and serialized using `URLSearchParams`.

### Content Types
- Default `Content-Type: application/json` with automatic `JSON.stringify(body)`.
- If body is `FormData` (e.g. image uploads), `Content-Type` is omitted to allow the browser to generate the multipart boundary automatically.

---

## 5. Response Envelope Normalization

Every successful and error response adheres to the NM-Reader unified envelope:

### Success Envelope (`ApiSuccess<T>`)
```json
{
  "status": "success",
  "data": { ... },
  "meta": {
    "pagination": {
      "type": "offset",
      "page": 1,
      "per_page": 20,
      "total": 100,
      "total_pages": 5
    }
  },
  "error": null
}
```

### Error Envelope (`ApiError`)
```json
{
  "status": "error",
  "data": null,
  "meta": {},
  "error": {
    "code": 422,
    "key": "VALIDATION_FAILED",
    "message": "The email field is invalid.",
    "fields": {
      "email": ["Invalid email format"]
    }
  }
}
```

---

## 6. Error Handling Strategy

When a response returns HTTP `>= 400` or invalid JSON, `HttpClient` throws an `ApiClientError`:

| HTTP Status | Error Key | Description | Client Action |
|:---|:---|:---|:---|
| `400` | `BAD_REQUEST` | Malformed parameters | Displays inline parameter error |
| `401` | `UNAUTHORIZED` | Unauthenticated / Session Expired | Prompts login modal via `notifyUnauthorized()` |
| `403` | `FORBIDDEN` | RBAC or locked chapter | Prompts unlock or permission notice |
| `404` | `NOT_FOUND` | Resource does not exist | Renders empty / not found view |
| `419` | `CSRF_ERROR` | Session / CSRF token expired | Client executes automatic single retry |
| `422` | `VALIDATION_FAILED` | Form validation failure | Maps `error.fields` directly to form inputs |
| `429` | `RATE_LIMITED` | Rate limit triggered | Displays retry-after cooldown warning |
| `500+` | `SERVER_ERROR` | Internal server exception | Displays generic friendly error toast |

---

## 7. Authentication Contract

- **Session Cookie:** Browser sends HttpOnly `nm_reader_session` with `credentials: "include"`.
- **Remember-Me Cookie:** Long-lived `nm_remember` auto-recovers expired sessions on the backend transparently.
- **CSRF Extraction:**
  1. Header `X-CSRF-Token` from any response automatically updates the in-memory token.
  2. `data.csrf_token` in `authService.login()` or `authService.refresh()` payloads updates the token cache.

---

## 8. CSRF Protection Protocol

- **Safe Methods (`GET`, `HEAD`, `OPTIONS`):** Do NOT attach `X-CSRF-Token`.
- **Mutating Methods (`POST`, `PUT`, `PATCH`, `DELETE`):** Automatically inject `X-CSRF-Token: <token>` from in-memory cache.
- **Exempt Routes:** `/auth/login`, `/auth/register`, `/auth/refresh`, `/auth/logout`, `/log/error`.

---

## 9. 419 CSRF Automatic Retry & Refresh Lock

If a mutating request fails with HTTP `419`:
1. `HttpClient` intercepts the `419` response.
2. Acquires `withRefreshLock()` (ensuring multiple concurrent failed requests share a single refresh call).
3. Calls `POST /api/v1/auth/refresh` without CSRF requirements.
4. If a fresh token is acquired, re-executes the original mutating request with the new `X-CSRF-Token`.
5. If retry fails, throws `ApiClientError(419)`. Loop prevention ensures each request is only retried once.

---

## 10. Media URL Resolution

Strictly adheres to `docs/api/MEDIA_CONTRACT.md`:

```typescript
import { resolveMediaUrl } from '@/api';

// 1. Public Media (Covers, Avatars, Blog Images)
resolveMediaUrl('cover.8k2ma7qx4.webp');
// Output: "/media/public/cover.8k2ma7qx4.webp"

// 2. Protected Chapter Media (HMAC Signed Tokens)
resolveMediaUrl('t_eyJjaWQiOiJjaDEyMyIsImV4cCI6MTc3MTIzNDU2N30.sig');
// Output: "/media/chapter/t_eyJjaWQiOiJjaDEyMyIsImV4cCI6MTc3MTIzNDU2N30.sig"

// 3. Placeholders and external URLs
resolveMediaUrl(''); // Output: "/assets/img/covers/placeholder.svg"
resolveMediaUrl('https://cdn.example.com/pic.jpg'); // Preserved
```

---

## 11. Domain Services

| Service | File | Canonical Methods |
|:---|:---|:---|
| `authService` | `src/api/services/authService.ts` | `login`, `register`, `refresh`, `logout`, `getSessions`, `revokeSession` |
| `contentService` | `src/api/services/contentService.ts` | `getHome`, `getContentByType`, `getContentDetail`, `getChapters`, `getChapterReader`, `rateContent`, `toggleFollow`, `unlockSeries`, `unlockChapter` |
| `taxonomyService` | `src/api/services/taxonomyService.ts` | `getGenres`, `getGenreContents`, `getTags`, `getTagContents`, `getSeriesGenres`, `getSeriesTags` |
| `blogService` | `src/api/services/blogService.ts` | `getBlogs`, `getBlogBySlug`, `getUserBlogs`, `createBlog`, `uploadBlogImage`, `voteBlog` |
| `commentService` | `src/api/services/commentService.ts` | `getComments`, `postComment`, `voteComment` |
| `userService` | `src/api/services/userService.ts` | `getProfile`, `updateProfile`, `getPublicProfile`, `getPreferences`, `updatePreferences`, `getLibrary`, `toggleLibrary`, `getHistory`, `recordHistory`, `getNotifications`, `markNotificationsRead`, `toggleFollowUser` |
| `walletService` | `src/api/services/walletService.ts` | `getWallet`, `getTransactions`, `getSeriesUnlocks`, `getChapterUnlocks`, `getFeatureEntitlements`, `getShopPackages`, `getShopFeatures`, `purchasePackage`, `purchaseChapter`, `purchaseAdFree` |
| `searchService` | `src/api/services/searchService.ts` | `search`, `searchSuggest` |

---

## 12. Real Service Boundary

- All new domain services implement the exact same interfaces (`IContentService`, `IAuthService`, `IBlogService`, `ICommentService`, `IUserService`, `IWalletService`) defined in [`ui/src/services/contracts.ts`](file:///home/duldul/Belgeler/nm-reader/ui/src/services/contracts.ts).
- `ui/src/services/provider.ts` instantiates only the API-backed implementations.
- Production source contains no runtime mock selector, fixture provider, or `VITE_USE_MOCK` branch. Test doubles are isolated to verification scripts and are never bundled.

---

## 13. Testing & Verification

Run automated tests via npm:

```bash
# Run API client test suite (25 test cases)
npm run test:client

# Run TypeScript type check
npm run typecheck

# Run Production build
npm run build
```

---

## 14. Usage Examples

### Fetching Content Detail
```typescript
import { contentService } from '@/api';

try {
  const response = await contentService.getContentDetail('manga', 'solo-leveling');
  if (response.status === 'success') {
    console.log('Title:', response.data.title);
    console.log('Chapters:', response.data.chapters);
  }
} catch (error) {
  if (error instanceof ApiClientError && error.isNotFound) {
    console.error('Series not found');
  }
}
```

### Unlocking a Chapter
```typescript
import { contentService, ApiClientError } from '@/api';

try {
  const res = await contentService.unlockChapter('ch0001');
  if (res.status === 'success') {
    console.log('Unlocked! Transaction ID:', res.data.transaction_id);
  }
} catch (err) {
  if (err instanceof ApiClientError) {
    if (err.isUnauthorized) {
      // Prompt login
    } else if (err.status === 402) {
      // Insufficient coins, open TopUpModal
    }
  }
}
```
