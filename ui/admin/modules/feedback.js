export function createFeedback(options = {}) {
  const isDoc = options && (options.nodeType || typeof options.getElementById === "function");
  const documentRef = isDoc ? options : (options.documentRef || globalThis.document);
  const windowRef = isDoc ? globalThis.window : (options.windowRef || globalThis.window);
  const modalService = isDoc ? null : options.modalService;

  const confirmAction = (optionsOrMessage) => {
    if (modalService?.confirm) {
      return modalService.confirm(optionsOrMessage);
    }
    const message = typeof optionsOrMessage === "string" ? optionsOrMessage : (optionsOrMessage?.message || "");
    return typeof windowRef?.confirm === "function"
      ? Promise.resolve(Boolean(windowRef.confirm(String(message))))
      : Promise.resolve(true);
  };

  const promptValue = (optionsOrMessage, defaultValue = "") => {
    if (modalService?.prompt) {
      return modalService.prompt(optionsOrMessage, defaultValue);
    }
    const message = typeof optionsOrMessage === "string" ? optionsOrMessage : (optionsOrMessage?.message || optionsOrMessage?.label || "");
    const def = typeof optionsOrMessage === "object" && optionsOrMessage ? (optionsOrMessage.defaultValue ?? defaultValue) : defaultValue;
    return typeof windowRef?.prompt === "function"
      ? Promise.resolve(windowRef.prompt(String(message), String(def ?? "")))
      : Promise.resolve(null);
  };

  function toast(message, type = "success") {
    const container = documentRef?.getElementById("lime-toasts");
    if (!container) return;
    const item = documentRef.createElement("div");
    item.className =
      `alert alert-${type} shadow-lg py-2 px-3 mb-0 rounded-3 d-flex align-items-center gap-2 text-dark`;
    item.setAttribute("role", type === "danger" ? "alert" : "status");
    item.textContent = String(message ?? "");
    container.append(item);
    setTimeout(() => item.remove(), 4000);
  }

  return Object.freeze({ toast, confirmAction, promptValue, modal: modalService });
}
