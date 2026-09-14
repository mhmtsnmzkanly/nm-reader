export function panelTitle(route = "dashboard", context = {}) {
  const label = String(route).split("-").map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(" ");
  const username = context.username_label || context.username;
  if (username && ["user-detail", "user-penalty", "user-wallet"].includes(route)) {
    const suffix = route === "user-penalty" ? " / Penalty" : route === "user-wallet" ? " / Wallet" : "";
    return `User: ${username}${suffix} - Panel`;
  }
  return `${label} - Panel`;
}

export function initializeShell(documentRef = globalThis.document) {
  const link = documentRef.getElementById("panel-site-link");
  if (link) link.href = new URL("/", documentRef.defaultView.location.href).href;
}
