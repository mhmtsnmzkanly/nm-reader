/** Penalty form and submission; violation history remains on the profile page. */
export function createUserPenaltyController({
  store, api, getPageEpoch, assertCurrentPage, mountPage,
  bindFormAction, registerPageCleanup, panelNavigate, showToast,
  translate = (_key, fallback) => fallback,
} = {}) {
  function violationScopeForTarget(targetType) {
    return targetType === "comment"
      ? "comment"
      : targetType === "blog"
      ? "blog"
      : "general";
  }

  async function loadUserPenaltyPage(userId) {
    const requestEpoch = getPageEpoch();
    if (!userId) {
      throw new Error(
        translate("admin.user.id_missing", "Kullanıcı kimliği bulunamadı"),
      );
    }
    let overview = store.get("userDetail");
    if (
      String(store.get("userDetailId")) !== String(userId) || !overview?.user
    ) {
      overview =
        (await api(`/users/${encodeURIComponent(userId)}/overview`))?.data ||
        {};
      assertCurrentPage(requestEpoch);
    }
    const user = overview.user || {};
    const page = mountPage("panel-user-penalty-content", {
      user_id: encodeURIComponent(userId),
      username: user.username || userId,
      header: {
        title: translate("admin.user.penalty_title", "Kullanıcıya ceza ver"),
        description: translate(
          "admin.user.penalty_description",
          "Kullanıcı için etkileşim kapsamını belirleyin.",
        ),
        parent_path: `/panel/user/${encodeURIComponent(userId)}`,
        show_back: true,
      },
    });
    const form = page.querySelector("#panel-user-penalty-form");
    if (bindFormAction) registerPageCleanup?.(bindFormAction(form, async (data) => {
        const payload = Object.fromEntries(data.entries());
        payload.auto_escalate = form.elements.auto_escalate.checked;
        payload.scope = violationScopeForTarget(payload.target_type);
        if (payload.auto_escalate) payload.level = "warning";
        await api(`/users/${encodeURIComponent(userId)}/violations`, {
          method: "POST",
          body: payload,
        });
        showToast(translate("admin.user.penalty_created", "Ceza kaydı oluşturuldu"));
        panelNavigate(`/panel/user/${encodeURIComponent(userId)}`);
      }, { onError: (error) => showToast(error.message, "danger") }));
    const auto = form?.elements.auto_escalate;
    const level = form?.elements.level;
    const sync = () => {
      if (level) level.disabled = Boolean(auto?.checked);
    };
    auto?.addEventListener("change", sync);
    if (auto) registerPageCleanup?.(() => auto.removeEventListener("change", sync));
    sync();
  }


  return Object.freeze({ loadUserPenaltyPage });
}
