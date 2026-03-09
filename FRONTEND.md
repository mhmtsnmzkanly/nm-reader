# NovelMangaReader Frontend API Documentation

This document provides a structured reference for the NMR API (v1) designed for frontend integration.

---

## 1. Public Endpoints (Discovery & Content)

### **Home Feed**
#### **PATH**
`GET /api/v1/home`

#### **REQUEST**
- **Method**: `GET`
- **Query Params**: `page` (int), `per_page` (int)

#### **RESPONSE**
```json
{
  "status": "success",
  "data": {
    "explore": [/* ContentDto */],
    "recent_chapters": [/* ChapterDto */],
    "recently_added": [/* ContentDto */],
    "popular_blogs": [/* BlogDto */]
  },
  "meta": { "page": 1, "per_page": 20 }
}
```

#### **ERROR**
- **500 Internal Server Error**: Database or aggregation failure.

---

### **Content Detail**
#### **PATH**
`GET /api/v1/content/{type}/{slug}`

#### **REQUEST**
- **Method**: `GET`
- **Path Variables**: 
  - `type`: manga, novel, etc.
  - `slug`: series-unique-slug

#### **RESPONSE**
```json
{
  "status": "success",
  "data": {
    "id": "abc123",
    "title": "Series Title",
    "description": "...",
    "cover_image": "...",
    "type": "manga",
    "status": "ongoing",
    "rating_avg": 4.5,
    "chapter_count": 100,
    "metadata": { "author": "...", "artist": "..." },
    "genres": [],
    "tags": [],
    "access": { "is_locked": false, "unlock_price": 0 }
  }
}
```

#### **ERROR**
- **404 Not Found**: Series does not exist.
- **400 Bad Request**: Invalid content type.

---

### **Chapter Detail (Reader)**
#### **PATH**
`GET /api/v1/content/{type}/{slug}/chapter/{chapterNumber}`

#### **REQUEST**
- **Method**: `GET`
- **Path Variables**: 
  - `chapterNumber`: e.g., "1", "1.5"

#### **RESPONSE**
```json
{
  "status": "success",
  "data": {
    "id": "ch123",
    "chapter_number": "1",
    "title": "Chapter Title",
    "type": "image",
    "data": "url1|url2|url3",
    "access": { "granted": true, "is_locked": false }
  }
}
```

#### **ERROR**
- **404 Not Found**: Chapter not found.
- **402 Payment Required**: Chapter is locked and requires purchase.

---

### **Search**
#### **PATH**
`GET /api/v1/search`

#### **REQUEST**
- **Method**: `GET`
- **Query Params**: `q` (string, required)

#### **RESPONSE**
```json
{
  "status": "success",
  "data": [/* ContentDto */],
  "meta": { "q": "query", "page": 1 }
}
```

---

## 2. Authentication

### **User Login**
#### **PATH**
`POST /api/v1/auth/login`

#### **REQUEST**
- **Method**: `POST`
- **Body**:
```json
{
  "email": "user@example.com",
  "password": "password123",
  "cf-turnstile-response": "token"
}
```

#### **RESPONSE**
```json
{
  "status": "success",
  "data": {
    "id": "usr00001",
    "username": "user",
    "api_token": "...",
    "roles": ["user"]
  }
}
```

#### **ERROR**
- **401 Unauthorized**: Invalid credentials.
- **429 Too Many Requests**: Rate limit exceeded.

---

## 3. Protected Endpoints (Requires Auth)

### **Follow Series**
#### **PATH**
`POST /api/v1/content/{type}/{slug}/follow`

#### **REQUEST**
- **Method**: `POST`
- **Headers**: `X-CSRF-Token: ...` (if cookie-based) or `Authorization: Bearer ...`

#### **RESPONSE**
```json
{
  "status": "success",
  "data": { "followed": true }
}
```

#### **ERROR**
- **401 Unauthorized**: User not logged in.

---

### **Unlock Content (Coins)**
#### **PATH**
`POST /api/v1/content/{type}/{slug}/unlock`

#### **REQUEST**
- **Method**: `POST`

#### **RESPONSE**
```json
{
  "status": "success",
  "data": { "unlocked": true, "new_balance": 450 }
}
```

#### **ERROR**
- **402 Payment Required**: Insufficient coins in wallet.
- **404 Not Found**: Content not found.

---

### **Track Activity (Pulsing)**
#### **PATH**
`POST /api/v1/user/activity`

#### **REQUEST**
- **Method**: `POST`
- **Body**:
```json
{
  "tab_id": "unique_session_tab_id",
  "duration": 30
}
```

#### **RESPONSE**
```json
{
  "status": "success",
  "data": { "tracked": true }
}
```

---

## 4. User Data

### **User Profile**
#### **PATH**
`GET /api/v1/user/profile`

#### **REQUEST**
- **Method**: `GET`

#### **RESPONSE**
```json
{
  "status": "success",
  "data": {
    "is_guest": false,
    "id": "usr00001",
    "username": "user",
    "email": "user@example.com",
    "bio": "...",
    "profile_image": "...",
    "cover_image": "..."
  }
}
```
