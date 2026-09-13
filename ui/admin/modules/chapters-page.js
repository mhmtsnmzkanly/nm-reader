import { adjacentPage, paginationState } from "./pagination.js";

/** Chapter list and bulk-management page controller. */
export function createChaptersPageController({
  store,
  api,
  responseItems,
  responseMeta,
  getPageEpoch,
  assertCurrentPage,
  mountEditorPage,
  mountPartial,
  panelNavigate,
  confirmAction = () => false,
  promptValue = () => null,
  showToast,
  registerPageCleanup,
  loadSeriesData,
  translate = (_key, fallback) => fallback,
} = {}) {
  async function loadChaptersPage(contentId, pageNumber = 1) {
    const requestEpoch = getPageEpoch();
    let content = (store.get("seriesList") || []).find(
      (item) => String(item.id) === String(contentId),
    );
    if (!content) {
      content = responseItems(
        await api(`/series?q=${encodeURIComponent(contentId)}&per_page=100`),
      ).find((item) => String(item.id) === String(contentId));
    }
    if (!content) {
      throw new Error(translate("admin.content.not_found", "İçerik bulunamadı."));
    }
    const response = await api(
      `/content/${content.id}/chapters?page=${
        paginationState({ page: pageNumber }).page
      }&per_page=25`,
    );
    assertCurrentPage(requestEpoch);
    const chapters = responseItems(response).map((chapter) => ({
      id: chapter.id,
      preview_url: "/panel/series/" +
        encodeURIComponent(content.id) +
        "/chapters/" +
        encodeURIComponent(chapter.id) +
        "/preview",
      chapter_number: chapter.chapter_number || "-",
      title: chapter.title || "-",
      type: chapter.type || "-",
      price_amount: Number(chapter.price_amount || 0),
      price_label: Number(chapter.price_amount || 0) > 0
        ? `${Number(chapter.price_amount)} coin`
        : translate("admin.access.free", "Ücretsiz"),
      published_at: chapter.published_at || "-",
    }));
    const chapterMeta = paginationState(responseMeta(response));
    const page = mountEditorPage(
      translate(
        "admin.chapters.title",
        "{title} — Bölümler (Sayfa {page}/{total})",
        {
          title: content.title,
          page: chapterMeta.page,
          total: chapterMeta.total_pages,
        },
      ),
      {
        name: "panel-chapters",
        context: {
          total: Number(chapterMeta.total || chapters.length),
          previous_disabled: chapterMeta.has_previous ? "" : "disabled",
          next_disabled: chapterMeta.has_next ? "" : "disabled",
        },
      },
    );
    page
      .querySelectorAll('[data-chapter-page-disabled="disabled"]')
      .forEach((button) => {
        button.disabled = true;
      });
    mountPartial(
      "panel-rows-chapters",
      page.querySelector("#panel-chapter-rows"),
      { items: chapters, has_items: chapters.length > 0 },
    );
    const onClick = async (event) => {
      const pageButton = event.target.closest("[data-chapter-page]");
      if (pageButton && !pageButton.disabled) {
        const direction = pageButton.dataset.chapterPage;
        const nextPage = adjacentPage(
          chapterMeta,
          direction === "next" ? 1 : direction === "prev" ? -1 : 0,
        );
        if (nextPage !== null) await loadChaptersPage(content.id, nextPage);
        return;
      }
      const createButton = event.target.closest("[data-create-chapter]");
      const teamButton = event.target.closest("[data-manage-team]");
      const editButton = event.target.closest("[data-edit-chapter]");
      const deleteButton = event.target.closest("[data-delete-chapter]");
      const bulkButton = event.target.closest("[data-bulk-chapter]");
      if (createButton) {
        panelNavigate(
          "/panel/series/" + encodeURIComponent(content.id) + "/chapters/new",
        );
      }
      if (teamButton) {
        panelNavigate(
          "/panel/series/" + encodeURIComponent(content.id) + "/team",
        );
      }
      if (editButton) {
        panelNavigate(
          "/panel/series/" +
            encodeURIComponent(content.id) +
            "/chapters/" +
            encodeURIComponent(editButton.dataset.editChapter) +
            "/edit",
        );
      }
      if (
        deleteButton &&
        confirmAction(translate("admin.confirm.chapter_delete", "Bu bölümü silmek istediğinize emin misiniz?"))
      ) {
        try {
          await api(`/chapters/${deleteButton.dataset.deleteChapter}`, {
            method: "DELETE",
          });
          showToast(translate("admin.chapters.deleted", "Bölüm silindi"));
          await loadSeriesData();
          await loadChaptersPage(content.id);
        } catch (error) {
          if (error?.name === "AbortError") return;
          showToast(error.message, "danger");
        }
      }
      if (bulkButton) {
        const ids = Array.from(
          page.querySelectorAll("[data-chapter-select]:checked"),
        ).map((input) => input.value);
        if (!ids.length) {
          return showToast(translate("admin.chapters.select_one", "Önce en az bir bölüm seçin"), "danger");
        }
        const action = bulkButton.dataset.bulkChapter;
        const params = {};
        if (action === "schedule") {
          const publishedAt = promptValue(translate("admin.prompt.publish_at", "Yayın tarihi (YYYY-MM-DD HH:MM):"));
          if (!publishedAt) return;
          params.published_at = publishedAt;
        }
        if (action === "set_price") {
          const price = promptValue(translate("admin.prompt.coin_price", "Coin fiyatı:"), "0");
          if (price === null) return;
          params.price_amount = Number(price);
          const freeAfter = promptValue(translate("admin.prompt.free_after", "Ücretsiz olma tarihi (isteğe bağlı):"), "");
          if (freeAfter) params.is_free_after = freeAfter;
        }
        if (
          action === "delete" &&
          !confirmAction(translate("admin.confirm.chapter_bulk_delete", "{count} bölümü silmek istediğinize emin misiniz?", { count: ids.length }))
        ) {
          return;
        }
        try {
          const result = await api("/chapters/bulk", {
            method: "POST",
            body: { ids, action, params },
          });
          showToast(
            translate("admin.toast.chapters_updated", "{count} bölüm güncellendi", {
              count: result?.data?.affected || ids.length,
            }),
          );
          await loadSeriesData();
          await loadChaptersPage(content.id);
        } catch (error) {
          if (error?.name === "AbortError") return;
          showToast(error.message, "danger");
        }
      }
    };
    page.addEventListener("click", onClick);
    registerPageCleanup?.(() => page.removeEventListener("click", onClick));
    const selectAll = page.querySelector("[data-select-all-chapters]");
    const onSelectAll = (event) => {
        page.querySelectorAll("[data-chapter-select]").forEach((input) => {
          input.checked = event.target.checked;
        });
    };
    selectAll?.addEventListener("change", onSelectAll);
    if (selectAll) {
      registerPageCleanup?.(() => selectAll.removeEventListener("change", onSelectAll));
    }
  }

  return Object.freeze({ loadChaptersPage });
}
