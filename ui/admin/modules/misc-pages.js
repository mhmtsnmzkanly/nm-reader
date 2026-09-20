/** Controllers for the smaller admin pages that do not justify separate domains. */
export function createMiscPagesController({
  store,
  api,
  responseItems,
  getPageEpoch,
  assertCurrentPage,
  mountEditorPage,
  mountPartial,
  mountHeaderCells,
  panelNavigate,
  confirmAction = () => false,
  reloadCurrentRoute,
  showToast,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
} = {}) {
  const reload = () => {
    if (typeof reloadCurrentRoute === "function") {
      reloadCurrentRoute();
      return;
    }
    if (typeof panelNavigate === "function" && globalThis.location?.pathname) {
      panelNavigate(globalThis.location.pathname + globalThis.location.search);
    }
  };

  async function loadSeriesRevisionsPage(contentId) {
    const requestEpoch = getPageEpoch();
    const response = await api(
      `/content/${encodeURIComponent(String(contentId))}/revisions?limit=50`,
    );
    assertCurrentPage(requestEpoch);
    const items = responseItems(response).map((revision) => ({
      created_at: revision.created_at || "-",
      moderator: revision.moderator_username || revision.moderator_user_id || "-",
      action: revision.action || "-",
      title: revision.snapshot?.title || "-",
      status: revision.snapshot?.status || "-",
      lifecycle_status: revision.snapshot?.lifecycle_status || "published",
    }));
    const page = mountEditorPage(
      translate("admin.revisions.title", "İçerik Revizyon Geçmişi"),
      {
        name: "panel-series-revisions",
        context: { items, has_items: items.length > 0 },
      },
    );
    mountPartial(
      "panel-rows-series-revisions",
      page.querySelector("#panel-series-revisions-rows"),
      { items, has_items: items.length > 0 },
    );
  }

  async function renderTeamPage(content) {
    const requestEpoch = getPageEpoch();
    const response = await api(
      `/series/${encodeURIComponent(String(content.id))}/team`,
    );
    assertCurrentPage(requestEpoch);
    const members = responseItems(response).map((member) => ({
      id: member.id,
      username: member.username || "-",
      user_id: member.user_id || "-",
      role: member.role || "-",
      created_at: member.created_at || "-",
    }));
    const page = mountEditorPage(
      translate("admin.team.title", "{title} — Ekip Yönetimi", {
        title: content.title,
      }),
      { name: "panel-team", context: {} },
      async (formData) => {
        await api(`/series/${encodeURIComponent(String(content.id))}/team`, {
          method: "POST",
          body: Object.fromEntries(formData.entries()),
        });
        showToast(translate("admin.team.assigned", "Ekip üyesi atandı"));
        reload();
      },
    );
    mountPartial("panel-rows-team", page.querySelector("#panel-team-rows"), {
      items: members,
      has_items: members.length > 0,
    });
    const onClick = async (event) => {
      const button = event.target.closest("[data-remove-team]");
      if (
        !button ||
        !(await confirmAction(translate("admin.confirm.team_remove", "Ekip üyesini çıkarmak istediğinize emin misiniz?")))
      ) {
        return;
      }
      try {
        await api(`/series/team/${encodeURIComponent(button.dataset.removeTeam)}`, {
          method: "DELETE",
        });
        showToast(translate("admin.team.removed", "Ekip üyesi çıkarıldı"));
        reload();
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    };
    page.addEventListener("click", onClick);
    registerPageCleanup?.(() => page.removeEventListener("click", onClick));
  }

  async function loadTeamPage(id) {
    const requestEpoch = getPageEpoch();
    let content = (store.get("seriesList") || []).find(
      (item) => String(item.id) === String(id),
    );
    if (!content) {
      content = responseItems(
        await api("/series?q=" + encodeURIComponent(id) + "&per_page=100"),
      ).find((item) => String(item.id) === String(id));
    }
    if (!content) {
      throw new Error(translate("admin.content.not_found", "İçerik bulunamadı."));
    }
    await renderTeamPage(content);
    assertCurrentPage(requestEpoch);
  }

  function renderPackagePage(packageItem = null) {
    const item = packageItem || {};
    const page = mountEditorPage(
      packageItem
        ? translate("admin.package.edit_title", "Paketi Düzenle")
        : translate("admin.package.new_title", "Yeni Coin Paketi"),
      {
        name: "panel-package-form",
        context: {
          name: item.name || "",
          coin_amount: item.coin_amount || "",
          bonus_coin: item.bonus_coin || 0,
          display_price: item.display_price || "0.00",
          currency: item.currency || "TRY",
          sort_order: item.sort_order || 0,
        },
      },
      async (formData) => {
        const payload = Object.fromEntries(formData.entries());
        payload.coin_amount = Number(payload.coin_amount);
        payload.bonus_coin = Number(payload.bonus_coin || 0);
        payload.sort_order = Number(payload.sort_order || 0);
        payload.is_active = payload.is_active === "1";
        await api(
          packageItem ? `/shop/packages/${packageItem.id}` : "/shop/packages",
          { method: packageItem ? "PUT" : "POST", body: payload },
        );
        panelNavigate("/panel/monetization");
        showToast(
          packageItem
            ? translate("admin.package.updated", "Paket güncellendi")
            : translate("admin.package.created", "Paket oluşturuldu"),
        );
      },
    );
    const active = page.querySelector('[name="is_active"]');
    if (active) active.value = Number(item.is_active ?? 1) === 1 ? "1" : "0";
  }

  async function loadPackagePage(id) {
    let item = (store.get("packagesList") || []).find(
      (candidate) => String(candidate.id) === String(id),
    );
    if (!item) {
      item = responseItems(await api("/shop/packages?per_page=100")).find(
        (candidate) => String(candidate.id) === String(id),
      );
    }
    if (!item) {
      throw new Error(translate("admin.package.not_found", "Paket bulunamadı"));
    }
    renderPackagePage(item);
  }

  async function loadAdFreePage() {
    const requestEpoch = getPageEpoch();
    const response = await api("/features");
    assertCurrentPage(requestEpoch);
    const item = responseItems(response).find(
      (feature) => feature.feature_key === "ad_free",
    ) || {};
    const page = mountEditorPage(
      translate("admin.ad_free.title", "Reklamsız Ürün Ayarı"),
      {
        name: "panel-ad-free-form",
        context: {
          name: item.name || "",
          coin_price: Number(item.coin_price || 0),
          duration_days: Number(item.duration_days || 30),
        },
      },
      async (formData) => {
        const payload = Object.fromEntries(formData.entries());
        payload.coin_price = Number(payload.coin_price || 0);
        payload.duration_days = Number(payload.duration_days || 30);
        payload.is_active = payload.is_active === "1";
        await api("/features/ad-free", { method: "PUT", body: payload });
        panelNavigate("/panel/monetization");
        showToast(translate("admin.ad_free.updated", "Reklamsız ürün ayarı kaydedildi"));
      },
    );
    const active = page.querySelector('[name="is_active"]');
    if (active) active.value = Number(item.is_active ?? 1) === 1 ? "1" : "0";
  }

  function loadPricingPage() {
    mountEditorPage(
      translate("admin.pricing.title", "Seri / Bölüm Fiyatlandırması"),
      { name: "panel-pricing-form", context: {} },
      async (formData) => {
        const targetType = String(formData.get("target_type"));
        const targetId = String(formData.get("target_id"));
        const targetPath = {
          series: "/series",
          chapters: "/chapters",
        }[targetType];
        if (!targetPath || !/^[a-z0-9]{6}$/.test(targetId)) {
          throw new Error(
            translate(
              "admin.pricing.invalid_target",
              "Geçerli bir seri veya bölüm kimliği girin.",
            ),
          );
        }
        const price = Number(formData.get("price_coin"));
        if (!Number.isFinite(price) || price < 0) {
          throw new Error(
            translate(
              "admin.pricing.invalid_price",
              "Coin fiyatı sıfır veya daha büyük bir sayı olmalıdır.",
            ),
          );
        }
        await api(`${targetPath}/${encodeURIComponent(targetId)}/pricing`, {
          method: "PUT",
          body: {
            price_coin: price,
            is_active: formData.get("is_active") === "1",
          },
        });
        panelNavigate("/panel/monetization");
        showToast(translate("admin.pricing.updated", "Fiyatlandırma kaydedildi"));
      },
    );
  }

  async function loadLogPage(path) {
    const requestEpoch = getPageEpoch();
    const response = await api(`/${path}?per_page=100`);
    assertCurrentPage(requestEpoch);
    const items = responseItems(response);
    const columns = items.length ? Object.keys(items[0]).slice(0, 8) : [];
    const rows = items.map((item) => ({
      cells: columns.map((column) => ({
        value: typeof item[column] === "object"
          ? JSON.stringify(item[column])
          : item[column],
      })),
    }));
    const page = mountEditorPage(translate("admin.logs.viewer_title", "Log Görüntüleyici"), {
      name: "panel-log",
      context: { columns, rows, has_rows: rows.length > 0 },
    });
    mountHeaderCells(page.querySelector("#panel-log-head"), columns);
    mountPartial("panel-rows-log", page.querySelector("#panel-log-body"), {
      rows,
      has_rows: rows.length > 0,
    });
  }

  async function loadAuditPage(id) {
    let item = (store.get("logsList") || []).find(
      (candidate) => String(candidate.id) === String(id),
    );
    if (!item) {
      item = responseItems(await api("/audit-logs?per_page=100")).find(
        (candidate) => String(candidate.id) === String(id),
      );
    }
    if (!item) {
      throw new Error(translate("admin.audit.not_found", "Denetim kaydı bulunamadı"));
    }
    mountEditorPage(
      translate("admin.audit.title", "Denetim Kaydı #{id}", { id: item.id }),
      {
        name: "panel-audit-json",
        context: { json: JSON.stringify(item, null, 2) },
      },
    );
  }

  function loadModerationPage() {
    mountEditorPage(
      translate("admin.moderation.new_title", "Yeni Moderasyon Kaydı"),
      { name: "panel-moderation-form", context: {} },
      async (formData) => {
        await api("/moderation-actions", {
          method: "POST",
          body: Object.fromEntries(formData.entries()),
        });
        panelNavigate("/panel/logs/moderation");
        showToast(translate("admin.moderation.created", "Moderasyon kaydı oluşturuldu"));
      },
    );
  }

  return Object.freeze({
    loadSeriesRevisionsPage,
    loadTeamPage,
    renderPackagePage,
    loadPackagePage,
    loadAdFreePage,
    loadPricingPage,
    loadLogPage,
    loadAuditPage,
    loadModerationPage,
  });
}
