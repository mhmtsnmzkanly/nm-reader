/** Taxonomy administration page controller. */
export function createTaxonomyPageController({
  api,
  getPageEpoch,
  assertCurrentPage,
  loadTaxonomies,
  mountEditorPage,
  mountPartial,
  confirmAction = () => false,
  promptValue = () => null,
  showToast,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
} = {}) {
  async function loadTaxonomyPage() {
    const requestEpoch = getPageEpoch();
    const { genres, tags } = await loadTaxonomies();
    assertCurrentPage(requestEpoch);
    const normalize = (items) =>
      items.map((item) => ({
        id: item.id,
        name: item.name || "-",
        slug: item.slug || "",
        usage_count: Number(item.usage_count || 0),
        sort_order: Number(item.sort_order || 0),
      }));
    const page = mountEditorPage(
      translate("admin.taxonomy.title", "Tür ve Etiket Yönetimi"),
      { name: "panel-taxonomy", context: {} },
      async () => {
        const items = Array.from(
          page.querySelectorAll("[data-taxonomy-row]"),
        ).map((row) => ({
          id: Number(row.dataset.taxonomyRow),
          sort_order: Number(
            row.querySelector("[data-taxonomy-order]").value || 0,
          ),
        }));
        await api("/taxonomies/order", { method: "PUT", body: { items } });
        showToast(translate("admin.taxonomy.order_saved", "Taksonomi sırası kaydedildi"));
        await loadTaxonomyPage();
      },
    );
    mountPartial(
      "panel-rows-taxonomy",
      page.querySelector("#panel-taxonomy-genres"),
      { items: normalize(genres), has_items: genres.length > 0 },
    );
    mountPartial(
      "panel-rows-taxonomy",
      page.querySelector("#panel-taxonomy-tags"),
      { items: normalize(tags), has_items: tags.length > 0 },
    );
    const submit = page.querySelector('button[type="submit"]');
    if (submit) submit.textContent = translate("admin.taxonomy.save_order", "Sıralamayı Kaydet");
    const onClick = async (event) => {
      try {
        const createButton = event.target.closest("[data-create-taxonomy]");
        if (createButton) {
          const kind = createButton.dataset.createTaxonomy;
          const name = promptValue(
            kind === "genre"
              ? translate("admin.taxonomy.new_genre", "Yeni tür adı:")
              : translate("admin.taxonomy.new_tag", "Yeni etiket adı:"),
          );
          if (!name?.trim()) return;
          await api(kind === "genre" ? "/series_genres" : "/series_tags", {
            method: "POST",
            body: { name: name.trim() },
          });
          showToast(
            kind === "genre"
              ? translate("admin.taxonomy.genre_created", "Tür oluşturuldu")
              : translate("admin.taxonomy.tag_created", "Etiket oluşturuldu"),
          );
          await loadTaxonomyPage();
          return;
        }
        const editButton = event.target.closest("[data-edit-taxonomy]");
        if (editButton) {
          const name = promptValue(
            translate("admin.taxonomy.new_name", "Yeni ad:"),
            editButton.dataset.name || "",
          );
          if (!name?.trim() || name.trim() === editButton.dataset.name) return;
          await api(`/taxonomies/${editButton.dataset.editTaxonomy}`, {
            method: "PUT",
            body: { name: name.trim() },
          });
          showToast(translate("admin.taxonomy.updated", "Taksonomi güncellendi"));
          await loadTaxonomyPage();
          return;
        }
        const mergeButton = event.target.closest("[data-merge-taxonomy]");
        if (mergeButton) {
          const targetId = Number(
            promptValue(
              translate(
                "admin.taxonomy.merge_target",
                "Bu kaydın birleştirileceği hedef taksonomi ID:",
              ),
            ),
          );
          if (!targetId) return;
          await api("/taxonomies/merge", {
            method: "POST",
            body: {
              source_id: Number(mergeButton.dataset.mergeTaxonomy),
              target_id: targetId,
            },
          });
          showToast(translate("admin.taxonomy.merged", "Taksonomiler birleştirildi"));
          await loadTaxonomyPage();
          return;
        }
        const deleteButton = event.target.closest("[data-delete-taxonomy]");
        if (deleteButton) {
          if (Number(deleteButton.dataset.usage || 0) > 0) {
            throw new Error(
              translate(
                "admin.taxonomy.cannot_delete_used",
                "Kullanılan bir kayıt silinemez; önce başka bir kayda birleştirin.",
              ),
            );
          }
          if (
            !confirmAction(
              translate("admin.taxonomy.delete_confirm", "“{name}” silinsin mi?", {
                name: deleteButton.dataset.name || "-",
              }),
            )
          ) return;
          await api(`/taxonomies/${deleteButton.dataset.deleteTaxonomy}`, {
            method: "DELETE",
          });
          showToast(translate("admin.taxonomy.deleted", "Taksonomi silindi"));
          await loadTaxonomyPage();
        }
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    };
    page.addEventListener("click", onClick);
    registerPageCleanup?.(() => page.removeEventListener("click", onClick));
  }

  return Object.freeze({ loadTaxonomyPage });
}
