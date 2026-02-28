# NMR API Reference - Mobile & CSR Development Guide

This document defines the REST API contract for the NovelMangaReader platform. It is optimized for frontend developers and AI agents building mobile (Flutter/RN) or web (React/Vue) applications.

---

## 1. General Standards

### Base URL
`http://localhost:8080/api/v1`

### Authentication
- **Method**: Bearer Token.
- **Header**: `Authorization: Bearer <your_api_token>`
- **Token Source**: Obtained via `/auth/login`.

### Response Structure
All API responses follow this standard JSON envelope:
```json
{
  "status": "success",
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

---

## 2. Authentication Endpoints

### Login
- **Endpoint**: `POST /auth/login`
- **Request**:
  ```json
  { "email": "user@example.com", "password": "password123" }
  ```
- **Key Response Fields**: `api_token`, `roles`, `permissions`.

### Register
- **Endpoint**: `POST /auth/register`
- **Request**:
  ```json
  { "username": "newuser", "email": "new@example.com", "password": "password123" }
  ```

---

## 3. Content Discovery

### Homepage Aggregation
- **Endpoint**: `GET /home`
- **Response Data**:
  - `explore`: Popular series.
  - `recent_chapters`: Latest chapter updates.
  - `recently_added`: New series entries.
  - `popular_blogs`: Top trending blog posts.

### Series Details
- **Endpoint**: `GET /content/{type}/{slug}`
- **Parameters**: `type` (manga|novel|etc.), `slug` (unique-title).
- **Output**: Full metadata (author, artist, rating, status).

### Chapter Listing
- **Endpoint**: `GET /content/{type}/{slug}/chapters`
- **Output**: List of available chapters with IDs and numbers.

### Chapter Reading
- **Endpoint**: `GET /chapter/{chapterNumber}`
- **Response Data**:
  - `type`: "text" or "image".
  - `data`: Markdown content (for text) or Pipe-separated (`|`) image URLs.

---

## 4. User & Interaction

### Public Profile
- **Endpoint**: `GET /profile/{username}`
- **Output**: User bio, statistics (comments, follows), and recent activity.

### Follow/Unfollow
- **Endpoint**: `POST /content/{type}/{slug}/follow` (To Follow)
- **Endpoint**: `DELETE /content/{type}/{slug}/follow` (To Unfollow)

### Activity Tracking (Pulsing)
- **Endpoint**: `POST /user/activity`
- **Purpose**: Tracks reading duration.
- **Payload**: `{"chapter_id": "...", "duration": 30}` (seconds).

---

## 5. Error Codes

| Code | Key | Description |
| :--- | :--- | :--- |
| 400 | `VALIDATION_FAILED` | Input fields are missing or invalid. |
| 401 | `UNAUTHORIZED` | Bearer token is missing or expired. |
| 403 | `FORBIDDEN` | User does not have required permissions. |
| 404 | `NOT_FOUND` | The requested entity (Series/Chapter) does not exist. |
| 429 | `RATE_LIMIT` | Too many requests from this IP/Account. |
| 500 | `INTERNAL_ERROR` | Server-side crash or SQL failure. |

---

## 6. Development Tips for AI Agents
- **Images**: If `cover_image` is relative, prefix it with the base domain.
- **Caching**: The `/home` and `/content` endpoints are cached for 5 minutes.
- **Markdown**: Blog bodies and Text chapters use GitHub-flavored Markdown.
- **Types**: Always check the `type` field in content lists to route to `/manga/` or `/novel/` pages.
