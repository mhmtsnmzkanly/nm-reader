(function () {
  "use strict";

  const ctx = window.__NMR_CONTEXT || {};
  const baseApiUrl = "/api/v1";

  function buildHeaders(headers, body) {
    const nextHeaders = Object.assign({ "X-Requested-With": "XMLHttpRequest" }, headers || {});
    const csrfToken = ctx.auth && ctx.auth.csrf_token ? ctx.auth.csrf_token : null;

    if (csrfToken) {
      nextHeaders["X-CSRF-Token"] = csrfToken;
    }

    if (body && !(body instanceof FormData) && !nextHeaders["Content-Type"]) {
      nextHeaders["Content-Type"] = "application/json";
    }

    return nextHeaders;
  }

  async function request(path, options) {
    const requestOptions = Object.assign({}, options || {});
    requestOptions.headers = buildHeaders(requestOptions.headers, requestOptions.body);
    requestOptions.credentials = "include";

    const response = await fetch(baseApiUrl + path, requestOptions);
    const contentType = response.headers.get("content-type") || "";
    const payload = contentType.includes("application/json")
      ? await response.json()
      : { status: response.ok ? "success" : "error", data: null };

    if (!response.ok || payload.status === "error") {
      const errMsg = (payload && payload.error && payload.error.message)
        ? payload.error.message
        : (payload && payload.message ? payload.message : ("Request failed: " + response.status));
      throw new Error(errMsg);
    }

    return payload;
  }

  window.NMRData = {
    request,
    get(path) {
      return request(path, { method: "GET" });
    },
    post(path, data) {
      return request(path, { method: "POST", body: JSON.stringify(data || {}) });
    },
    put(path, data) {
      return request(path, { method: "PUT", body: JSON.stringify(data || {}) });
    },
    delete(path, data) {
      return request(path, {
        method: "DELETE",
        body: data ? JSON.stringify(data) : undefined,
      });
    },
  };
})();
