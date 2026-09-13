export function createPanelUi(
  { documentRef = globalThis.document, mountPartial } = {},
) {
  // Keep URL values safe before they reach Lime's attribute binder. Media
  // services may return absolute HTTPS URLs, while local assets are
  // root-relative. Reject javascript/data/blob and protocol-relative URLs so
  // user/API supplied values cannot become executable attributes.
  const safeLocalPath = (value) => {
    const candidate = String(value ?? "").trim();
    if (!candidate) return "#";
    if (candidate.startsWith("#")) return candidate;
    if (candidate.startsWith("//")) return "#";
    if (candidate.startsWith("/") && !candidate.startsWith("//")) {
      return candidate;
    }
    try {
      const parsed = new URL(candidate, globalThis.location?.href);
      if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
        return "#";
      }
      return parsed.href;
    } catch {
      return "#";
    }
  };
  function setIconButtonLabel(button, iconName, label) {
    if (!button || !documentRef) return;
    const icon = documentRef.createElement("i");
    icon.className = `bi ${iconName} me-1`;
    const text = documentRef.createElement("span");
    text.textContent = label;
    button.replaceChildren(icon, text);
  }
  function mountHeaderCells(target, labels, className = "") {
    if (!target || !documentRef) return;
    target.replaceChildren(...labels.map((label) => {
      const cell = documentRef.createElement("th");
      cell.className = className;
      cell.textContent = String(label ?? "");
      return cell;
    }));
  }
  function setTableRows(id, partialName, items, colspan, extra = {}) {
    const target = documentRef?.getElementById(id);
    if (!target || typeof mountPartial !== "function") return;
    if (extra.loading) {
      return mountPartial("panel-table-loading", target, { colspan });
    }
    if (extra.error_message) {
      return mountPartial("panel-table-error", target, {
        colspan,
        error_message: extra.error_message,
      });
    }
    mountPartial(partialName, target, {
      items: Array.isArray(items) ? items : [],
      has_items: Array.isArray(items) && items.length > 0,
      colspan,
      ...extra,
    });
  }
  function renderPager(id, meta, previousHandler, nextHandler) {
    const target = documentRef?.getElementById(id);
    if (!target || typeof mountPartial !== "function") return;
    const page = Number(meta?.page || 1);
    const totalPages = Math.max(1, Number(meta?.total_pages || 1));
    mountPartial("panel-pager", target, {
      page,
      total_pages: totalPages,
      total: Number(meta?.total || 0),
      previous_handler: previousHandler,
      next_handler: nextHandler,
      has_previous: page > 1,
      has_next: page < totalPages,
    });
  }
  return Object.freeze({
    safeLocalPath,
    setIconButtonLabel,
    mountHeaderCells,
    setTableRows,
    renderPager,
  });
}
