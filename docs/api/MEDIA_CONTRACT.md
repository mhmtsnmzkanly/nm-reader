# NM-READER — MEDIA & STORAGE CONTRACT (V1)

**Document Version:** 1.0.0  
**Status:** FROZEN FOR REACT INTEGRATION  
**CDN Mode:** CDN-Agnostic / Local Abstraction (No external CDN vendor dependency)

---

## 1. Media Architecture

NM-Reader implements a clean separation between **Public Media** and **Protected Chapter Media**:

```
                          MEDIA SYSTEM
                               │
               ┌───────────────┴───────────────┐
               │                               │
          PUBLIC MEDIA                 PROTECTED CHAPTER MEDIA
               │                               │
        Persistent ID                    Temporary Signed Token
               │                               │
     Cover / Avatar / Blog              Chapter Manga/Novel Pages
               │                               │
    Long-Lived HTTP Cache              Strict Authorization & Expiry
               │                               │
        Immutable URLs                  Time-Bound Stream / CDN Ready
```

---

## 2. Public Media

Public media assets are accessible without authorization tokens:
- **Content Covers**: `/media/public/cover.{imageId}.webp`
- **User Avatars**: `/media/public/user.profile.{imageId}.webp`
- **Blog Images**: `/media/public/content.cover.{imageId}.webp`
- **Placeholder Fallbacks**: `/assets/img/covers/placeholder.svg`

### Characteristics
- Persistent, immutable ID scheme (`image_id` generated as 32-character base36 string).
- No temporary IDs or session tokens required.
- High-efficiency HTTP caching enabled.
- Storage path abstraction hides internal server directory layout.

---

## 3. Protected Chapter Media

Chapter pages (manga/manhwa/webtoon/manhua image streams) are protected digital assets:
- Direct filesystem paths (e.g. `/var/www/...` or raw `/uploads/...`) are **NEVER** exposed to client API responses.
- Access requires a cryptographically signed, time-bound temporary token (`t_...`).
- URL Format: `GET /media/chapter/{token}`

### Token Lifecycle
1. Reader requests chapter via `GET /api/v1/content/{type}/{slug}/chapter/{chapterNumber}`.
2. Server verifies user authorization via `WalletService::chapterAccess($contentId, $chapterId, $userId)`.
3. If access is granted:
   - Server computes HMAC-SHA256 signed token containing `chapter_id`, `page_order`, filename `f`, viewer `uid`, and expiration timestamp `exp` (default TTL: 3 hours / 10,800s).
   - Response returns array of signed URLs:
     ```json
     {
       "pages": [
         {
           "page_order": 1,
           "url": "/media/chapter/t_eyJjaWQiOiJjaDEyMyIsInAiOjEsImYiOiJjaGFwdGVyLmFiYy53ZWJwIiwiZXhwIjoxNzcxMjM0NTY3fQ.9a8b7c6d..."
         }
       ]
     }
     ```
4. When browser requests the image URL:
   - `MediaController::serveChapterMedia()` verifies signature with application secret.
   - Verifies `exp >= time()`.
   - Resolves file strictly within upload directory with path-traversal prevention.
   - Streams image with `Cache-Control: private, no-store, no-cache, must-revalidate`.
5. If token is invalid, tampered, or expired: returns `403 Forbidden` (`INVALID_OR_EXPIRED_MEDIA_TOKEN`).

---

## 4. Media IDs

| Entity Type | ID Generation Pattern | Storage Pattern | Example ID |
| :--- | :--- | :--- | :--- |
| **Series Cover** | Base36 (32 chars) | `cover.{id}.webp` | `cover.8k2ma7qx4...webp` |
| **User Profile Avatar** | Base36 (32 chars) | `user.profile.{id}.webp` | `user.profile.9x7a1b...webp` |
| **Blog Featured Image**| Base36 (32 chars) | `content.cover.{id}.webp` | `content.cover.3m9k2...webp` |
| **Chapter Page** | Base36 (32 chars) | `chapter.{id}.webp` | `chapter.a7b9c1d2...webp` |

---

## 5. Temporary IDs & Cryptographic Token Scheme

- **Prefix:** `t_`
- **Format:** `t_{Base64URL(Payload)}.{HMAC-SHA256 Signature}`
- **Payload Schema:**
  ```json
  {
    "cid": "6-char chapter ID",
    "p": 1,
    "f": "chapter.imgId.webp",
    "uid": "optional user ID",
    "exp": 1771234567
  }
  ```
- **Signing Algorithm:** `hash_hmac('sha256', $payloadBase64, $secret)`
- **Verification:** Constant-time `hash_equals()` comparison.

---

## 6. URL Contract

| Endpoint | Method | Auth Required | Cache Policy | Description |
| :--- | :--- | :--- | :--- | :--- |
| `/media/public/{filename}` | `GET` | No | `public, max-age=86400, immutable` | Serves public images with ETag/304 support |
| `/api/v1/media/public/{filename}` | `GET` | No | `public, max-age=86400, immutable` | API mirror of public media |
| `/media/chapter/{token}` | `GET` | Signed Token | `private, no-store, no-cache` | Streams protected chapter image |
| `/api/v1/media/chapter/{token}` | `GET` | Signed Token | `private, no-store, no-cache` | API mirror of chapter media stream |
| `/api/v1/blogs/image` | `POST` | Yes (Bearer/Cookie) | `no-cache` | Uploads blog image |
| `/api/v1/admin/upload-images` | `POST` | Admin Perm | `no-cache` | Bulk image/ZIP upload for chapters/covers |

---

## 7. Storage Abstraction

The media system uses a decoupled storage interface:
1. **`UploadService`**: Validates MIME types, verifies image headers, strips malicious payloads, converts to WebP/optimized JPEG/PNG, and writes to storage root.
2. **`MediaService`**: Handles token signing, URL creation, and strict path resolution.
3. **`MediaController`**: Handles HTTP streaming, conditional caching (ETag/Last-Modified), and response headers.

*Future CDN/Object Storage readiness:* When migrating to Cloudflare R2 / AWS S3, only `MediaService::resolveFile()` and `UploadService::processImagePath()` need adapter substitution without altering the public API contract or React client code.

---

## 8. Upload Contract

### Supported Input Types
- Multipart form upload (`UploadedFileInterface`).
- Bulk multipart file array.
- Clean ZIP archive containing sorted chapter image files (`.zip` / `application/zip`).

### Limits & Constraints
- Maximum single file size: 20 MB.
- Maximum ZIP size: 200 MB (up to 500 files per archive).
- Automatic sorting: Natural alphanumeric case-insensitive sort (`strnatcasecmp`) preserves page order.

---

## 9. MIME & File Validation Security

- **Extension Spoofing Prevention:** File extensions are ignored; actual MIME type is determined via `finfo(FILEINFO_MIME_TYPE)`.
- **Allowed MIME Types:**
  - `image/jpeg` → `.jpg`
  - `image/png` → `.png`
  - `image/webp` → `.webp`
  - `image/gif` → `.gif`
- **Image Validity Check:** Every uploaded file is parsed with `getimagesize()` and re-encoded via `imagecreatefromstring()` / GD to destroy any embedded polyglot PHP payloads or hidden executable segments.
- **Path Traversal Shield:** Filenames are sanitized via `basename()` and strictly matched against `^[a-zA-Z0-9_\-\.]+$`. Symlinks and traversal attempts (`../`, `..\`, null bytes) trigger instant rejection.

---

## 10. Authentication & Authorization

- **Public Media:** Open read access.
- **Chapter Media:** Token-authenticated access granted exclusively upon valid wallet chapter unlock or free chapter status.
- **Upload Actions:**
  - User Avatar / Profile: Authenticated user.
  - Blog Images: Authenticated user with `blog creation` permission.
  - Chapter & Cover Uploads: Admin / Moderator with `admin.content.create` permission.

---

## 11. Cache Policy

### Public Media Headers
```http
HTTP/1.1 200 OK
Content-Type: image/webp
Content-Length: 124560
Last-Modified: Fri, 14 Aug 2026 12:00:00 GMT
ETag: "1a2b3c-4d5e6f"
Cache-Control: public, max-age=86400, immutable
```

### Protected Chapter Media Headers
```http
HTTP/1.1 200 OK
Content-Type: image/webp
Content-Length: 354120
Cache-Control: private, no-store, no-cache, must-revalidate
Pragma: no-cache
```

---

## 12. Error Contract

Media errors adhere to the standard API error envelope:

```json
{
  "status": "error",
  "data": null,
  "meta": {},
  "error": {
    "code": 403,
    "key": "FORBIDDEN",
    "message": "Invalid or expired chapter media token",
    "params": []
  }
}
```

- `400 BAD_REQUEST`: Corrupt upload payload or unsupported MIME type.
- `403 FORBIDDEN`: Invalid HMAC signature or expired chapter media token.
- `404 NOT_FOUND`: Media file not found in storage.
- `500 INTERNAL_ERROR`: Storage read failure.

---

## 13. Ownership & Multi-Tenant Security

- A user cannot delete or overwrite another user's uploads.
- Upload records in `system_uploads` track `user_id`, `image_id`, `original_name`, `mime_type`, `file_size`, and `file_path`.
- Admin upload listing requires `admin.uploads.view`; deletion requires `admin.uploads.delete` plus recent password reauthentication and logs an audit trail. Bulk deletion and image optimization use the dedicated `/api/v1/admin/uploads/bulk-delete` and `/api/v1/admin/uploads/{id}/optimize` endpoints.

---

## 14. Orphan Media Strategy

- **Content / Chapter Deletion:** Database records use soft delete (`deleted_at`).
- **Physical Files:** Retained for retention window (configurable via `cleanupRetention` console job).
- **Cleanup Job:** Admin console retention runner matches `system_uploads` against active DB references to identify and purge orphaned files safely.

---

## 15. CDN Readiness

The architecture is 100% CDN-ready without current external dependencies:
1. All public URLs are deterministic and immutable.
2. Chapter URLs contain time-bound signed tokens compatible with Cloudflare Signed URLs / Akamai EdgeAuth.
3. Zero absolute filesystem paths leak to API consumers.
