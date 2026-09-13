/** Central authenticated admin API transport. */
export function createAdminApi({
  basePath = "/api/v1/admin",
  getEpoch,
  getSignal,
  getCsrfToken,
  assertCurrentPage,
  reauthenticate,
  fetchImpl = globalThis.fetch,
} = {}) {
  if (typeof fetchImpl !== "function") {
    throw new TypeError("createAdminApi requires a fetch implementation.");
  }
  const request = async function request(path, input = {}) {
    if (
      typeof path !== "string" || !path.startsWith("/") || path.startsWith("//")
    ) throw new TypeError("Admin API paths must be root-relative.");
    const options = { ...input };
    const detached = options.detached === true;
    const alreadyRetried = options._reauthAttempt === true;
    delete options.detached;
    delete options._reauthAttempt;
    if (!Object.hasOwn(options, "credentials")) {
      options.credentials = "same-origin";
    }
    const epoch = getEpoch?.();
    if (!detached) options.signal = getSignal?.();
    const headers = new Headers(options.headers || {});
    if (!headers.has("Accept")) headers.set("Accept", "application/json");
    if (!headers.has("X-Requested-With")) {
      headers.set("X-Requested-With", "XMLHttpRequest");
    }
    const csrf = getCsrfToken?.();
    if (csrf && !headers.has("X-CSRF-Token")) headers.set("X-CSRF-Token", csrf);
    if (
      options.body && typeof options.body === "object" &&
      !(typeof FormData !== "undefined" && options.body instanceof FormData) &&
      !(typeof Blob !== "undefined" && options.body instanceof Blob) &&
      !(typeof ArrayBuffer !== "undefined" &&
        options.body instanceof ArrayBuffer)
    ) {
      if (!headers.has("Content-Type")) {
        headers.set("Content-Type", "application/json");
      }
      options.body = JSON.stringify(options.body);
    }
    options.headers = headers;
    let response;
    try {
      response = await fetchImpl(`${basePath}${path}`, options);
    } catch (error) {
      // Preserve cancellation semantics so page-session navigation can stop
      // stale requests without surfacing a toast. Other transport failures
      // receive the same normalized shape as HTTP failures.
      if (error?.name === "AbortError") throw error;
      throw createApiError(
        0,
        "NETWORK_ERROR",
        error?.message || "Sunucuya ulaşılamadı.",
      );
    }
    if (!detached) assertCurrentPage?.(epoch);
    if (response.status === 428 && !alreadyRetried && path !== "/auth/reauth") {
      if (typeof reauthenticate !== "function") {
        throw createApiError(
          428,
          "REAUTH_REQUIRED",
          "Yeniden doğrulama gerekli.",
        );
      }
      if (!(await reauthenticate({ signal: getSignal?.(), epoch }))) {
        throw createApiError(
          428,
          "REAUTH_CANCELLED",
          "Kritik işlem iptal edildi.",
        );
      }
      if (!detached) assertCurrentPage?.(epoch);
      return request(path, { ...input, _reauthAttempt: true });
    }
    const payload = await parsePayload(response);
    // The response body is read asynchronously. Re-check the page epoch after
    // parsing as well, otherwise a route change during a slow body read could
    // surface an old error or payload on the new page.
    if (!detached) assertCurrentPage?.(epoch);
    // A 204 response is a valid empty success for mutation endpoints; other
    // successful responses must carry a JSON envelope so callers never render
    // an accidental HTML/empty response as if it were data.
    if (
      response.ok &&
      response.status !== 204 &&
      !isApiEnvelope(payload)
    ) {
      throw createApiError(
        response.status,
        "MALFORMED_RESPONSE",
        "Sunucudan geçersiz JSON yanıtı alındı.",
        [],
        payload,
      );
    }
    if (response.ok && payload?.status === "error") {
      throw createApiError(
        Number(payload.error?.code) || response.status,
        payload.error?.key || "API_ERROR",
        payload.error?.message || "API isteği başarısız.",
        payload.error?.params,
        payload,
      );
    }
    if (!response.ok) {
      throw createApiError(
        response.status,
        payload?.error?.key || "HTTP_ERROR",
        payload?.error?.message || `HTTP ${response.status}`,
        payload?.error?.params,
        payload,
      );
    }
    if (!detached) assertCurrentPage?.(epoch);
    return payload;
  };
  request.get = (path, options = {}) =>
    request(path, { ...options, method: "GET" });
  request.post = (path, body, options = {}) =>
    request(path, { ...options, method: "POST", body });
  request.put = (path, body, options = {}) =>
    request(path, { ...options, method: "PUT", body });
  request.patch = (path, body, options = {}) =>
    request(path, { ...options, method: "PATCH", body });
  request.delete = (path, options = {}) =>
    request(path, { ...options, method: "DELETE" });
  return Object.freeze(request);
}

/** Re-authentication transport used for endpoints that require step-up auth. */
export function createAdminReauth({
  fetchImpl = globalThis.fetch,
  getCsrfToken,
  promptImpl = globalThis.prompt,
  assertCurrentPage,
  translate = (_key, fallback) => fallback,
} = {}) {
  if (typeof fetchImpl !== "function") {
    throw new TypeError("createAdminReauth requires a fetch implementation.");
  }
  return async ({ signal, epoch } = {}) => {
    const password = typeof promptImpl === "function"
      ? promptImpl(
        translate(
          "admin.auth.reauth_prompt",
          "Bu kritik işlem için yönetici parolanızı yeniden girin:",
        ),
      )
      : "";
    if (!password) return false;
    const csrf = getCsrfToken?.();
    const headers = new Headers({
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    });
    if (csrf) headers.set("X-CSRF-Token", csrf);
    let response;
    try {
      response = await fetchImpl("/api/v1/admin/auth/reauth", {
        method: "POST",
        signal,
        headers,
        credentials: "same-origin",
        body: JSON.stringify({ password }),
      });
    } catch (error) {
      if (error?.name === "AbortError") throw error;
      throw createApiError(
        0,
        "NETWORK_ERROR",
        error?.message || "Sunucuya ulaşılamadı.",
      );
    }
    if (!response.ok) {
      const payload = await parsePayload(response);
      // Re-authentication can finish after a route change. Do not surface its
      // old failure on the new page or allow a stale retry flow to continue.
      assertCurrentPage?.(epoch);
      throw createApiError(
        response.status,
        payload?.error?.key || "REAUTH_FAILED",
        payload?.error?.message || "Parola doğrulanamadı.",
        payload?.error?.params,
        payload,
      );
    }
    const payload = await parsePayload(response);
    if (!isApiEnvelope(payload)) {
      throw createApiError(
        response.status,
        "MALFORMED_RESPONSE",
        "Yeniden doğrulama yanıtı geçersiz.",
        [],
        payload,
      );
    }
    if (payload.status === "error") {
      throw createApiError(
        Number(payload.error?.code) || response.status,
        payload.error?.key || "REAUTH_FAILED",
        payload.error?.message || "Parola doğrulanamadı.",
        payload?.error?.params,
        payload,
      );
    }
    assertCurrentPage?.(epoch);
    return true;
  };
}

// Both transports accept only the backend's explicit success/error states.
// HTTP 204 is handled separately by the regular API; reauth requires proof
// of success in its JSON envelope.
function isApiEnvelope(payload) {
  return payload !== null && typeof payload === "object" &&
    !Array.isArray(payload) && !payload.__malformed &&
    (payload.status === "success" || payload.status === "error");
}

async function parsePayload(response) {
  if (response.status === 204) return null;
  let text;
  try {
    text = await response.text();
  } catch {
    return { __malformed: true, raw: "", reason: "READ_FAILED" };
  }
  if (!text.trim()) {
    return { __malformed: true, raw: "", reason: "EMPTY" };
  }
  try {
    return JSON.parse(text);
  } catch {
    return { __malformed: true, raw: text, reason: "INVALID_JSON" };
  }
}
export function createApiError(
  status,
  key,
  message,
  params = [],
  payload = null,
) {
  const error = new Error(String(message || "API isteği başarısız."));
  error.name = "ApiError";
  error.status = Number(status) || 0;
  error.key = String(key || "HTTP_ERROR");
  error.params = Array.isArray(params) ? params : [];
  error.payload = payload;
  error.requestId = payload?.meta?.request_id || null;
  return error;
}
export function responseItems(response) {
  return Array.isArray(response?.data)
    ? response.data
    : Array.isArray(response?.data?.items)
    ? response.data.items
    : [];
}
export function responseMeta(response) {
  return {
    page: Number(response?.meta?.page || 1),
    total_pages: Math.max(1, Number(response?.meta?.total_pages || 1)),
    total: Number(response?.meta?.total || 0),
  };
}
