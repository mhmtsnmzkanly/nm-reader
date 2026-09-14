import { createCollectionTableRenderer } from "./collection-tables.js";

/** Users list page controller; profile/penalty/wallet controllers stay separate. */
export function createUsersListController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch,
  assertCurrentPage,
  requestGate,
  hasPermission,
  setTableRows,
  renderPager,
  // Used only for failures so a stale/empty table is not mistaken for a
  // successful empty result.
  showToast,
  translate = (_key, fallback) => fallback,
  documentRef = globalThis.document,
} = {}) {
  const renderCollectionTable = createCollectionTableRenderer(setTableRows);
  function renderUsersTable() {
    const canInspect = hasPermission("admin.users.manage");
    const items = (store.get("usersList") || []).map((user) => ({
      ...user,
      can_inspect: canInspect,
      inspect_class: canInspect ? "" : "d-none",
      profile_url: `/panel/user/${encodeURIComponent(user.id)}`,
    }));
    renderCollectionTable("users", items);
    renderPager(
      "panel-users-pager",
      store.get("usersMeta"),
      "previousUsersPage",
      "nextUsersPage",
    );
  }

  async function loadUsersData(page = 1) {
    const requestEpoch = getPageEpoch();
    const requestToken = requestGate.begin();
    renderCollectionTable("users", [], { loading: true });
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "20",
      });
      const values = {
        q: documentRef.getElementById("panel-users-search")?.value || "",
        status: documentRef.getElementById("panel-users-status")?.value || "",
        role: documentRef.getElementById("panel-users-role")?.value || "",
        sort: documentRef.getElementById("panel-users-sort")?.value || "newest",
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const res = await api(`/users?${params.toString()}`);
      assertCurrentPage(requestEpoch);
      if (!requestGate.isCurrent(requestToken)) return;
      const items = responseItems(res).map((user) => ({
        ...user,
        role_names: user.role_names || "user",
        account_status: Number(user.is_banned) === 1
          ? translate("admin.status.banned", "Yasaklı")
          : translate("admin.status.active", "Aktif"),
        account_badge: Number(user.is_banned) === 1
          ? "bg-danger-subtle text-danger border border-danger-subtle"
          : "bg-success-subtle text-success border border-success-subtle",
      }));
      store.batch(() => {
        store.set("usersList", items);
        store.set("usersMeta", responseMeta(res));
      });
      renderUsersTable();
      return true;
    } catch (error) {
      if (error?.name === "AbortError") return;
      if (!requestGate.isCurrent(requestToken)) return;
      renderCollectionTable("users", [], {
        error_message: error.message ||
          translate("admin.error.users_load", "Kullanıcılar yüklenemedi."),
      });
      showToast(
        translate("admin.error.users_load_detail", "Kullanıcılar yüklenemedi: {message}", {
          message: error.message,
        }),
        "danger",
      );
    }
  }

  return Object.freeze({ renderUsersTable, loadUsersData });
}
