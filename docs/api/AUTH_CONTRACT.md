# NM-Reader Authentication & Security Contract

**Version:** 1.0.0  
**Status:** CANONICAL SPECIFICATION  
**Date:** 2026-08-14  
**Scope:** Authoritative contract for React/CSR frontend integration with the NM-Reader authentication and session subsystem.

---

## 1. Core Architecture & Principles

1. **Session-First Architecture:** The application uses native HTTP cookie-backed PHP sessions (`nm_reader_session`) as the canonical authentication mechanism for browser/CSR clients.
2. **No Bearer/JWT Re-Architecture:** Bearer tokens (`api_token`) are supported for mobile/native clients, but the React web client relies strictly on session cookies + CSRF tokens. No custom JWT or OAuth2 layer is introduced.
3. **Stateful Security with CSRF:** All state-changing mutations (`POST`, `PUT`, `DELETE`, `PATCH`) in protected API groups strictly require the `X-CSRF-Token` HTTP header.
4. **Credentials Inclusion:** All `fetch` or `axios` HTTP calls from the React client MUST include credentials (`credentials: 'include'` / `withCredentials: true`).

---

## 2. Cookie Management Contract

| Cookie Name | Purpose | Attributes | Lifetime |
|---|---|---|---|
| `nm_reader_session` | Primary PHP Session Identity | `HttpOnly; Path=/; SameSite=Lax; Secure` (HTTPS) | Session / 7200 seconds (configurable) |
| `nm_remember` | Long-Lived Refresh Token | `HttpOnly; Path=/; SameSite=Lax; Secure` (HTTPS) | 30 Days (when `remember: true` at login) |

### Auto-Recovery via `nm_remember`:
If `nm_reader_session` expires or is deleted, the backend middleware automatically detects `nm_remember`, calls `AuthService::refresh()`, restores the session in `$_SESSION`, rotates the refresh token, and sends updated `Set-Cookie` and `X-CSRF-Token` headers back to the browser transparently.

---

## 3. CSRF Protection Protocol

### 3.1 Rules
- **Safe Methods:** `GET`, `HEAD`, `OPTIONS` do NOT require `X-CSRF-Token`.
- **Mutating Methods:** `POST`, `PUT`, `DELETE`, `PATCH` REQUIRE `X-CSRF-Token` matching `$_SESSION['csrf_token']`.
- **Exemptions:**
  - `POST /api/v1/auth/login` (Initial handshake)
  - `POST /api/v1/auth/register` (Initial handshake)
  - `POST /api/v1/auth/refresh` (Token exchange)
  - `POST /api/v1/auth/logout` / `GET /api/v1/auth/logout`
  - `POST /api/v1/log/error` (Anonymous error logging)

### 3.2 Acquiring and Storing the CSRF Token
The React frontend obtains the CSRF token via two mechanisms:
1. **Response Header:** The backend injects `X-CSRF-Token: <token>` in all HTTP responses from session-enabled routes.
2. **Auth Payloads:** `POST /api/v1/auth/login` and `POST /api/v1/auth/refresh` return `csrf_token` directly in `data.csrf_token`.

The React API client must maintain the latest known CSRF token in memory and attach it to every mutating request.

---

## 4. Authentication Endpoints Specification

### 4.1 Registration: `POST /api/v1/auth/register`
- **Purpose:** Creates a new user account.
- **Auth:** Public (Anonymous).
- **CSRF:** Not required.
- **Rate Limit:** 3 attempts per 10 minutes per email.
- **Request Body (`JSON`):**
  ```json
  {
    "username": "johndoe",
    "email": "john@example.com",
    "password": "Password123",
    "turnstile_token": "0.xxxx..." 
  }
  ```
  - `username` (`string`, required): 3–30 chars, `/^[a-zA-Z0-9_]{3,30}$/`.
  - `email` (`string`, required): Valid email format.
  - `password` (`string`, required): 8–128 chars, requires uppercase, lowercase, and digit.
  - `turnstile_token` (`string`, optional): Required if Cloudflare Turnstile integration is active.
- **Success Response (HTTP 201 Created):**
  ```json
  {
    "status": "success",
    "data": {
      "id": "usr00042",
      "username": "johndoe",
      "email": "john@example.com"
    },
    "meta": {},
    "error": null
  }
  ```
- **Note:** Registration does **NOT** log the user in automatically. The client must prompt the user to log in.
- **Errors:**
  - `400 BAD_REQUEST`: Validation failure (e.g. invalid password complexity).
  - `409 CONFLICT`: `Username already exists` or `Email already exists`.
  - `429 RATE_LIMITED`: Rate limit exceeded.

---

### 4.2 Login: `POST /api/v1/auth/login`
- **Purpose:** Authenticates user, establishes server session, issues CSRF token and optional remember-me cookie.
- **Auth:** Public (Anonymous).
- **CSRF:** Not required.
- **Rate Limit:** 10 attempts per minute per email.
- **Request Body (`JSON`):**
  ```json
  {
    "email": "john@example.com",
    "password": "Password123",
    "remember": true,
    "turnstile_token": "0.xxxx..."
  }
  ```
  - `email` (`string`, required): Registered email.
  - `password` (`string`, required): Plaintext password.
  - `remember` (`boolean`, optional, default: false): Issues 30-day `nm_remember` cookie when true.
- **Success Response (HTTP 200 OK):**
  ```json
  {
    "status": "success",
    "data": {
      "id": "usr00042",
      "username": "johndoe",
      "email": "john@example.com",
      "csrf_token": "4f8a92b...e31c",
      "refresh_token": "a1b2c3d4...9988",
      "api_token": "88e7b1a...f902",
      "roles": ["user"],
      "permissions": []
    },
    "meta": {},
    "error": null
  }
  ```
- **Side Effects:**
  - Sets `nm_reader_session` cookie.
  - Sets `nm_remember` cookie if `remember: true`.
  - Injects `X-CSRF-Token` header.
- **Errors:**
  - `401 UNAUTHORIZED`: `auth.invalid_credentials` (bad email or password).
  - `429 RATE_LIMITED`: `auth.rate_limited`.

---

### 4.3 Refresh Session: `POST /api/v1/auth/refresh`
- **Purpose:** Manually exchange a refresh token for new session identity and rotated refresh token.
- **Auth:** Public token exchange.
- **CSRF:** Not required.
- **Rate Limit:** 20 requests per minute.
- **Request Body (`JSON`):**
  ```json
  {
    "refresh_token": "a1b2c3d4...9988"
  }
  ```
- **Success Response (HTTP 200 OK):**
  ```json
  {
    "status": "success",
    "data": {
      "id": "usr00042",
      "username": "johndoe",
      "email": "john@example.com",
      "csrf_token": "7c1e5a...b29f",
      "refresh_token": "new_rotated_token...",
      "api_token": "88e7b1a...f902",
      "roles": ["user"],
      "permissions": []
    },
    "meta": {},
    "error": null
  }
  ```
- **Errors:**
  - `401 UNAUTHORIZED`: Invalid or expired refresh token.

---

### 4.4 Logout: `POST /api/v1/auth/logout` and `GET /api/v1/auth/logout`
- **Purpose:** Terminates current session, deletes session record in database, clears session and remember cookies.
- **Auth:** Authenticated or Guest.
- **CSRF:** Not required.
- **Success Response (HTTP 200 OK for POST, 302 Redirect for GET):**
  ```json
  {
    "status": "success",
    "data": {
      "logged_out": true
    },
    "meta": {},
    "error": null
  }
  ```
- **Side Effects:** Sends `Set-Cookie` with expired date (1970) for `nm_reader_session` and `nm_remember`.

---

### 4.5 Current User Profile Check: `GET /api/v1/user/profile`
- **Purpose:** Frontend bootstrap / auth state check on page load.
- **Auth:** Viewer-Aware (Never returns 401; returns guest shape if unauthenticated).
- **CSRF:** None.
- **Guest Response (`HTTP 200 OK`):**
  ```json
  {
    "status": "success",
    "data": {
      "is_guest": true,
      "id": null,
      "username": "guest",
      "email": null,
      "bio": null,
      "profile_image": null,
      "cover_image": null,
      "created_at": null
    },
    "meta": {},
    "error": null
  }
  ```
- **Authenticated Response (`HTTP 200 OK`):**
  ```json
  {
    "status": "success",
    "data": {
      "is_guest": false,
      "id": "usr00042",
      "username": "johndoe",
      "email": "john@example.com",
      "bio": "Reading manga all day.",
      "profile_image": "/uploads/user.profile.img001.webp",
      "cover_image": null,
      "created_at": "2026-08-10 14:22:00"
    },
    "meta": {},
    "error": null
  }
  ```

---

### 4.6 Active Sessions: `GET /api/v1/auth/sessions`
- **Purpose:** Lists user's active device sessions.
- **Auth:** Required (Session).
- **Response Data (`array<object>`):**
  ```json
  [
    {
      "session_key": "4a7f9c2e01b3",
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0 (X11; Linux x86_64)...",
      "last_active_at": "2026-08-14 12:00:00",
      "is_current": true
    }
  ]
  ```

### 4.7 Revoke Session: `DELETE /api/v1/auth/sessions/{sessionKey}`
- **Purpose:** Terminate a specific session remotely.
- **Auth:** Required (Session).
- **CSRF:** Required (`X-CSRF-Token`).
- **Response Data:** `{"revoked": true}`

---

## 5. Client-Side Authentication Flow & Lifecycle

```
[APP STARTUP]
     │
     ▼
GET /api/v1/user/profile (withCredentials: true)
     │
     ├── is_guest === false  ──► Set AuthContext: Logged-in User
     │                            Store received X-CSRF-Token header
     │
     └── is_guest === true   ──► Set AuthContext: Guest User
                                  Store received X-CSRF-Token header

[LOGIN EVENT]
     │
     ▼
POST /api/v1/auth/login { email, password, remember }
     │
     ├── Success (200) ──► Store User in AuthContext
     │                     Store csrf_token in ApiClient memory
     │                     Cookies automatically saved by browser
     │                     Navigate to target route / dashboard
     │
     └── Error (401)   ──► Show error message in Login form

[API ERROR INTERCEPTOR]
     │
     ├── 401 UNAUTHORIZED ──► Session expired or invalid:
     │                        1. Reset AuthContext to guest
     │                        2. Trigger Login Modal or redirect to /login
     │
     ├── 419 CSRF_INVALID ──► Stale CSRF Token:
     │                        1. Call GET /api/v1/user/profile to refresh token
     │                        2. Retry original failed mutation once
     │
     └── 403 FORBIDDEN    ──► Show permission denied notification
```

---

## 6. Security Guarantees & Summary

1. **Password Hashing:** Passwords hashed using standard `password_hash($password, PASSWORD_DEFAULT)` with secure salt.
2. **Session Fixation Prevention:** `session_regenerate_id(true)` executed upon successful authentication.
3. **Brute Force Protection:** Rate limiting keyed on email + IP hash (`assertLoginAllowed` in `AuthService`).
4. **Audit Trail:** All login attempts (success and failures) logged to `system_login_events`.
5. **CSRF Mitigation:** Constant-time token comparison (`hash_equals`) in `CsrfMiddleware`.
