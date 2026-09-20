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
  modalService = null,
  showToast,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
} = {}) {
  let currentTab = "genres";

  async function loadTaxonomyPage() {
    const requestEpoch = getPageEpoch();
    const { genres, tags } = await loadTaxonomies();
    assertCurrentPage(requestEpoch);
    const normalizeConfig = (config) => {
      if (config && typeof config === "object" && !Array.isArray(config)) return config;
      if (typeof config !== "string" || !config.trim()) return {};
      try {
        const parsed = JSON.parse(config);
        return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
      } catch {
        return {};
      }
    };
    const normalize = (items) =>
      items.map((item) => {
        const uiConfig = normalizeConfig(item.ui_config);
        return {
        id: item.id,
        name: item.name || "-",
        slug: item.slug || "",
        description: uiConfig.description || "",
        ui_config: uiConfig,
        usage_count: Number(item.usage_count || 0),
        sort_order: Number(item.sort_order || 0),
        };
      });
    const genreItems = normalize(genres);
    const tagItems = normalize(tags);
    const configById = new Map(
      [...genreItems, ...tagItems].map((item) => [String(item.id), item.ui_config]),
    );
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
      { items: genreItems, has_items: genreItems.length > 0 },
    );
    mountPartial(
      "panel-rows-taxonomy",
      page.querySelector("#panel-taxonomy-tags"),
      { items: tagItems, has_items: tagItems.length > 0 },
    );
    const genresCountEl = page.querySelector("#panel-taxonomy-genres-count");
    if (genresCountEl) genresCountEl.textContent = String(genreItems.length);
    const tagsCountEl = page.querySelector("#panel-taxonomy-tags-count");
    if (tagsCountEl) tagsCountEl.textContent = String(tagItems.length);

    const tabButtons = page.querySelectorAll("[data-taxonomy-tab]");
    const tabSections = page.querySelectorAll("[data-taxonomy-section]");
    const updateTabs = (tab) => {
      currentTab = tab;
      tabButtons.forEach((btn) => btn.classList.toggle("active", btn.dataset.taxonomyTab === tab));
      tabSections.forEach((sec) => {
        sec.hidden = sec.dataset.taxonomySection !== tab;
      });
    };
    if (tabButtons.length > 0) {
      updateTabs(currentTab);
      tabButtons.forEach((btn) => {
        const onTabClick = () => updateTabs(btn.dataset.taxonomyTab);
        btn.addEventListener("click", onTabClick);
        registerPageCleanup?.(() => btn.removeEventListener("click", onTabClick));
      });
    }

    const footerSubmitSpan = page.querySelector('.card-footer button[type="submit"] span');
    if (footerSubmitSpan) {
      footerSubmitSpan.textContent = translate("admin.taxonomy.save_order", "Sıralamayı Kaydet");
    } else {
      const submit = page.querySelector('button[type="submit"]');
      if (submit) submit.textContent = translate("admin.taxonomy.save_order", "Sıralamayı Kaydet");
    }
    const onClick = async (event) => {
      try {
        const createButton = event.target.closest("[data-create-taxonomy]");
        if (createButton) {
          const kind = createButton.dataset.createTaxonomy;
          let name, description;
          if (modalService?.dialog) {
            const result = await modalService.dialog({
              title: kind === "genre"
                ? translate("admin.taxonomy.new_genre", "Yeni tür adı:")
                : translate("admin.taxonomy.new_tag", "Yeni etiket adı:"),
              icon: "bi-tag-fill",
              variant: "primary",
              confirmText: translate("admin.modal.ok", "Tamam"),
              fields: [
                {
                  name: "name",
                  label: kind === "genre"
                    ? translate("admin.taxonomy.new_genre", "Yeni tür adı:")
                    : translate("admin.taxonomy.new_tag", "Yeni etiket adı:"),
                  type: "text",
                  required: true,
                },
                {
                  name: "description",
                  label: translate("admin.taxonomy.new_description", "Açıklama:"),
                  type: "textarea",
                  rows: 2,
                },
              ],
            });
            if (!result || !result.name?.trim()) return;
            name = result.name.trim();
            description = result.description?.trim() || "";
          } else {
            const rawName = await promptValue(
              kind === "genre"
                ? translate("admin.taxonomy.new_genre", "Yeni tür adı:")
                : translate("admin.taxonomy.new_tag", "Yeni etiket adı:"),
            );
            if (!rawName?.trim()) return;
            name = rawName.trim();
            const rawDesc = await promptValue(
              translate("admin.taxonomy.new_description", "Açıklama:"),
              "",
            );
            if (rawDesc === null) return;
            description = rawDesc.trim();
          }
          await api(kind === "genre" ? "/series_genres" : "/series_tags", {
            method: "POST",
            body: {
              name,
              ui_config: description ? { description } : {},
            },
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
          const uiConfig = {
            ...(configById.get(String(editButton.dataset.editTaxonomy)) || {}),
          };
          let name, description;
          if (modalService?.dialog) {
            const result = await modalService.dialog({
              title: translate("admin.taxonomy.new_name", "Yeni ad:"),
              icon: "bi-pencil-square",
              variant: "primary",
              confirmText: translate("admin.modal.ok", "Tamam"),
              fields: [
                {
                  name: "name",
                  label: translate("admin.taxonomy.new_name", "Yeni ad:"),
                  type: "text",
                  value: editButton.dataset.name || "",
                  required: true,
                },
                {
                  name: "description",
                  label: translate("admin.taxonomy.new_description", "Açıklama:"),
                  type: "textarea",
                  rows: 2,
                  value: uiConfig.description || "",
                },
              ],
            });
            if (!result || !result.name?.trim()) return;
            name = result.name.trim();
            description = result.description?.trim() || "";
          } else {
            const rawName = await promptValue(
              translate("admin.taxonomy.new_name", "Yeni ad:"),
              editButton.dataset.name || "",
            );
            if (!rawName?.trim()) return;
            name = rawName.trim();
            const rawDesc = await promptValue(
              translate("admin.taxonomy.new_description", "Açıklama:"),
              uiConfig.description || "",
            );
            if (rawDesc === null) return;
            description = rawDesc.trim();
          }
          if (description) {
            uiConfig.description = description;
          } else {
            delete uiConfig.description;
          }
          await api(`/taxonomies/${editButton.dataset.editTaxonomy}`, {
            method: "PUT",
            body: { name, ui_config: uiConfig },
          });
          showToast(translate("admin.taxonomy.updated", "Taksonomi güncellendi"));
          await loadTaxonomyPage();
          return;
        }
        const mergeButton = event.target.closest("[data-merge-taxonomy]");
        if (mergeButton) {
          const targetId = Number(
            await promptValue(
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
            !(await confirmAction(
              translate("admin.taxonomy.delete_confirm", "“{name}” silinsin mi?", {
                name: deleteButton.dataset.name || "-",
              }),
            ))
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
