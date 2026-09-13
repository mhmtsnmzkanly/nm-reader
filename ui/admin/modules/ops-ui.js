export function createOpsOperationUi(
  { documentRef = globalThis.document, store } = {},
) {
  function set(message, type = "info", loading = false) {
    store?.set("opsOperation", {
      message: String(message || ""),
      type,
      loading,
    });
    const output = documentRef?.getElementById("panel-ops-operation");
    const buttons = documentRef?.querySelectorAll("[data-ops-operation]") || [];
    if (output) {
      output.hidden = !message;
      output.className = `alert alert-${type} d-flex align-items-center gap-2`;
      output.replaceChildren();
      if (loading) {
        const spinner = documentRef.createElement("span");
        spinner.className = "lime-spinner";
        spinner.setAttribute("aria-hidden", "true");
        output.append(spinner);
      }
      output.append(documentRef.createTextNode(String(message || "")));
    }
    buttons.forEach((button) => {
      button.disabled = loading;
    });
  }
  return Object.freeze({ set });
}
