# NovelMangaReader Frontend API Documentation

This document provides a structured reference for the NMR API (v1) designed for frontend integration.

---

## 1. Authentication & Identity

### **Register**
#### **PATH**
`POST /api/v1/auth/register`
#### **REQUEST**
- **Body**: `{ "username": "...", "email": "...", "password": "..." }`
#### **RESPONSE**
Standard user object on success.
#### **ERROR**
- **400 Bad Request**: Validation failed or user exists.

### **Login**
#### **PATH**
`POST /api/v1/auth/login`
#### **REQUEST**
- **Body**: `{ "email": "...", "password": "...", "cf-turnstile-response": "..." }`
#### **RESPONSE**
```json
{ "status": "success", "data": { "id": "...", "username": "...", "api_token": "...", "roles": [] } }
```
#### **ERROR**
- **401 Unauthorized**: Invalid credentials.

### **Logout**
#### **PATH**
`GET|POST /api/v1/auth/logout`
#### **RESPONSE**
`{ "status": "success" }`

### **Refresh Token**
#### **PATH**
`POST /api/v1/auth/refresh`
#### **RESPONSE**
New API token and session metadata.

### **Active Sessions**
#### **PATH**
`GET /api/v1/auth/sessions`
#### **RESPONSE**
List of active devices/sessions for the user.

---

## 2. User Profile & Preferences

### **Get Profile**
#### **PATH**
`GET /api/v1/user/profile`
#### **RESPONSE**
Private profile data (email, bio, etc.).

### **Update Profile**
#### **PATH**
`POST /api/v1/user/profile`
#### **REQUEST**
- **Body (Multipart)**: `{ "bio": "...", "avatar": File, "cover": File }`

### **Get Preferences**
#### **PATH**
`GET /api/v1/user/preferences`
#### **RESPONSE**
Reader settings (theme, layout, font-size).

### **Update Preferences**
#### **PATH**
`PUT /api/v1/user/preferences`
#### **REQUEST**
- **Body**: `{ "theme": "dark", "reader_layout": "vertical", ... }`

---

## 3. Wallet & Monetization

### **Get Wallet**
#### **PATH**
`GET /api/v1/user/wallet`
#### **RESPONSE**
Current coin balance.

### **Transaction History**
#### **PATH**
`GET /api/v1/user/wallet/transactions`
#### **QUERY PARAMS**
`page`, `per_page`

### **Owned Unlocks (Series)**
#### **PATH**
`GET /api/v1/user/unlocks/series`

### **Owned Unlocks (Chapters)**
#### **PATH**
`GET /api/v1/user/unlocks/chapters`

### **Unlock Content**
#### **PATH**
`POST /api/v1/content/{type}/{slug}/unlock`
#### **ERROR**
- **402 Payment Required**: Insufficient coins.

### **Unlock Chapter**
#### **PATH**
`POST /api/v1/chapter/{chapterId}/unlock`

### **Feature Status**
#### **PATH**
`GET /api/v1/user/features`
#### **RESPONSE**
Active site features (e.g., `ad_free` status).

### **Purchase Ad-Free**
#### **PATH**
`POST /api/v1/user/features/ad-free/purchase`

---

## 4. Social & Library

### **Follow/Unfollow Series**
#### **PATH**
`POST|DELETE /api/v1/content/{type}/{slug}/follow`

### **Follow/Unfollow User**
#### **PATH**
`POST|DELETE /api/v1/user/follows/{person}`

### **Get Following (Users)**
#### **PATH**
`GET /api/v1/user/follows/users`

### **Get Library (Followed Content)**
#### **PATH**
`GET /api/v1/user/follows`

### **Reading History**
#### **PATH**
`GET /api/v1/user/history`

### **Notifications**
#### **PATH**
`GET /api/v1/user/notifications`

### **Mark Notifications Read**
#### **PATH**
`POST /api/v1/user/notifications/read`

---

## 5. Interactions

### **Rate Content**
#### **PATH**
`POST /api/v1/content/{type}/{slug}/rate`
#### **REQUEST**
- **Body**: `{ "rating": 5 }`

### **Comment on Series**
#### **PATH**
`POST /api/v1/content/{type}/{slug}/comment`
#### **REQUEST**
- **Body**: `{ "body": "...", "parent_id": null }`

### **Comment on Chapter**
#### **PATH**
`POST /api/v1/chapter/{chapterId}/comment`

### **Vote on Comment**
#### **PATH**
`POST /api/v1/comments/{commentId}/vote`
#### **REQUEST**
- **Body**: `{ "vote": 1 }` (1 or -1)

---

## 6. Blog Management

### **My Blogs**
#### **PATH**
`GET /api/v1/user/blogs`

### **Create Blog**
#### **PATH**
`POST /api/v1/blogs`
#### **REQUEST**
- **Body**: `{ "title": "...", "body": "...", "cover_image": "..." }`

### **Vote on Blog**
#### **PATH**
`POST /api/v1/blogs/{slug}/vote`
