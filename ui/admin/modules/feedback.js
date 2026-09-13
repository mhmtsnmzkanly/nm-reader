export function createFeedback(documentRef = globalThis.document) {
  const confirmAction = (message) =>
    typeof globalThis.confirm === "function" &&
    globalThis.confirm(String(message));
  const promptValue = (message, defaultValue = "") =>
    typeof globalThis.prompt === "function"
      ? globalThis.prompt(String(message), String(defaultValue ?? ""))
      : null;
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
  return Object.freeze({ toast, confirmAction, promptValue });
}
