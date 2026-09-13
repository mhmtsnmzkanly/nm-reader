function label(translate, key, fallback) {
  if (typeof translate !== "function") return fallback;
  const value = translate(key, fallback);
  return value === key ? fallback : value;
}

/**
 * Convert canonical API target codes to translated display labels.
 *
 * The raw code is deliberately left untouched in the source record; this
 * formatter is only for presentation and therefore cannot leak protocol or
 * storage values into the UI.
 */
export function targetTypeLabel(type, translate) {
  const definitions = {
    blog: ["admin.target.blog", "Blog"],
    chapter: ["admin.target.chapter", "Bölüm"],
    comment: ["admin.target.comment", "Yorum"],
    content: ["admin.target.content", "İçerik"],
    series: ["admin.target.content", "İçerik"],
    system: ["admin.scope.general", "Genel"],
    general: ["admin.scope.general", "Genel"],
  };
  const canonical = String(type || "").trim().toLowerCase();
  const definition = definitions[canonical];
  return definition ? label(translate, definition[0], definition[1]) : (
    canonical || label(translate, "admin.target.unknown", "Hedef")
  );
}

/** Translate moderation scopes while retaining their canonical API values. */
export function moderationScopeLabel(scope, translate) {
  const definitions = {
    general: ["admin.scope.general", "Genel"],
    system: ["admin.scope.general", "Genel"],
    comment: ["admin.scope.comment", "Yorum"],
    blog: ["admin.scope.blog", "Blog"],
    content: ["admin.scope.content", "İçerik"],
    series: ["admin.scope.content", "İçerik"],
    chapter: ["admin.scope.chapter", "Bölüm"],
  };
  const canonical = String(scope || "").trim().toLowerCase();
  const definition = definitions[canonical];
  return definition ? label(translate, definition[0], definition[1]) : (
    canonical || label(translate, "admin.scope.general", "Genel")
  );
}

/** Translate the canonical action persisted on a moderation violation. */
export function moderationActionLabel(action, translate) {
  const definitions = {
    warn: ["admin.violation.action.warn", "Uyar"],
    remove: ["admin.violation.action.remove", "İçeriği kaldır"],
    restrict: ["admin.violation.action.restrict", "Etkileşimi kısıtla"],
  };
  const canonical = String(action || "").trim().toLowerCase();
  const definition = definitions[canonical];
  return definition ? label(translate, definition[0], definition[1]) : (
    canonical || "-"
  );
}

/** Format user/API numbers without coupling page modules to a locale. */
export function formatNumber(value, locale = "en") {
  const number = Number(value ?? 0);
  if (!Number.isFinite(number)) return "0";
  try {
    return number.toLocaleString(locale);
  } catch {
    return number.toLocaleString("en");
  }
}

/** Format server timestamps consistently across table and detail pages. */
export function formatDateTime(value, locale = "en") {
  if (!value) return "-";
  const date = value instanceof Date
    ? value
    : new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return String(value);
  try {
    return date.toLocaleString(locale);
  } catch {
    return date.toLocaleString("en");
  }
}

export function userViolationLevel(level, translate) {
  const definitions = {
    warning: ["admin.violation.warning", "Uyarı", "bg-info-subtle text-info"],
    removal: [
      "admin.violation.removal",
      "İçerik kaldırma",
      "bg-warning-subtle text-warning",
    ],
    temporary: [
      "admin.violation.temporary",
      "Süreli engel",
      "bg-danger-subtle text-danger",
    ],
    permanent: [
      "admin.violation.permanent",
      "Kalıcı engel",
      "bg-dark text-white",
    ],
  };
  const definition = definitions[String(level || "")];
  return definition
    ? [label(translate, definition[0], definition[1]), definition[2]]
    : [String(level || "-"), "bg-secondary-subtle text-secondary"];
}

export function userModerationStatus(status, translate) {
  const definitions = {
    pending: ["admin.status.pending", "Bekliyor", "bg-warning-subtle text-warning"],
    approved: ["admin.status.approved", "Onaylı", "bg-success-subtle text-success"],
    hidden: ["admin.status.hidden", "Gizli", "bg-secondary-subtle text-secondary"],
    deleted: ["admin.status.deleted", "Silindi", "bg-danger-subtle text-danger"],
  };
  const definition = definitions[String(status || "")];
  return definition
    ? [label(translate, definition[0], definition[1]), definition[2]]
    : [String(status || "-"), "bg-light text-secondary"];
}

export function reportStatus(status, translate) {
  const definitions = {
    pending: ["admin.report.pending", "Bekleyen", "bg-warning-subtle text-warning"],
    reviewing: ["admin.report.reviewing", "İncelenen", "bg-info-subtle text-info"],
    resolved: ["admin.report.resolved", "Çözüldü", "bg-success-subtle text-success"],
    rejected: ["admin.report.rejected", "Reddedildi", "bg-secondary-subtle text-secondary"],
  };
  const definition = definitions[String(status || "")];
  return definition
    ? [label(translate, definition[0], definition[1]), definition[2]]
    : [String(status || "-"), "bg-light text-secondary"];
}

export function commentThreadFields(comment = {}) {
  const parentId = Number(comment.parent_id || 0);
  const parentUsername = String(comment.parent_username || "").trim();
  const isReply = Number.isInteger(parentId) && parentId > 0;
  return {
    is_reply: isReply,
    parent_reference: isReply
      ? `${parentUsername ? `@${parentUsername} · ` : ""}#${parentId}`
      : "",
    reply_badge_class: isReply ? "bg-info-subtle text-info me-1" : "d-none",
    reply_reference_class: isReply ? "d-block text-info mt-1" : "d-none",
  };
}
