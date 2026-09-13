/** Series create/edit page controller. */
export function createSeriesEditorController({
  store,
  api,
  responseItems,
  getPageEpoch,
  assertCurrentPage,
  loadTaxonomies,
  uploadImages,
  bindTaxonomyButtons,
  selectedValues,
  mountPartial,
  registerPageCleanup,
  bindFormAction,
  showToast,
  panelNavigate,
  nextYear,
  translate = (_key, fallback) => fallback,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  async function loadSeriesEditorPage(mode, id = null) {
    const requestEpoch = getPageEpoch();
    const target = document.getElementById("panel-series-editor-fields");
    const form = document.getElementById("panel-series-editor-form");
    if (!target || !form) return;
    try {
      let content = {};
      if (id) {
        let found = (store.get("seriesList") || []).find(
          (item) => String(item.id) === String(id),
        );
        if (!found) {
          found = responseItems(
            await api(`/series?q=${encodeURIComponent(id)}&per_page=100`),
          ).find((item) => String(item.id) === String(id));
        }
        if (!found) {
          throw new Error(translate("admin.content.not_found", "İçerik bulunamadı."));
        }
        content = found;
      }
      const { genres, tags } = await loadTaxonomies();
      assertCurrentPage(requestEpoch);
      const dateValue = (value) =>
        value ? String(value).replace(" ", "T").slice(0, 16) : "";
      const pageTitle = document.querySelector("[data-panel-title]");
      if (pageTitle) {
        pageTitle.textContent = mode === "edit"
          ? translate("admin.series.edit_title", "Seriyi düzenle")
          : translate("admin.series.new_title", "Yeni seri oluştur");
      }
      mountPartial(
        "panel-series-breadcrumb",
        document.getElementById("panel-series-breadcrumb"),
        {
          label: mode === "edit"
            ? translate("admin.series.edit_breadcrumb", "Düzenle")
            : translate("admin.series.new_breadcrumb", "Yeni"),
        },
      );
      mountPartial("panel-content-form", target, {
        title: content.title || "",
        slug: content.slug || "",
        type: String(content.type || "novel").replace("_", "-"),
        status: content.status || "ongoing",
        lifecycle_status: content.lifecycle_status || "published",
        scheduled_at: dateValue(content.scheduled_at),
        alternative_titles: content.alternative_titles || "",
        description: content.description || "",
        cover_image: content.cover_image || "",
        author: content.author || "",
        artist: content.artist || "",
        country: content.country || "",
        release_year: content.release_year || "",
        next_year: nextYear,
        genres,
        tags,
      });
      const setValue = (name, value) => {
        const input = form.elements[name];
        if (input && value != null) input.value = value;
      };
      setValue("type", String(content.type || "novel").replace("_", "-"));
      setValue("status", content.status || "ongoing");
      setValue("lifecycle_status", content.lifecycle_status || "published");
      if (id) {
        form.elements.type.disabled = true;
        form.elements.slug.disabled = true;
      }
      form.elements.is_adult.checked = Number(content.is_adult) === 1;
      form.elements.is_members_only.checked =
        Number(content.is_members_only) === 1;
      form.elements.disable_comments.checked =
        Number(content.disable_comments) === 1;
      const selectedGenres = new Set(
        String(content.genre_ids || "")
          .split(",")
          .filter(Boolean),
      );
      const selectedTags = new Set(
        String(content.tag_ids || "")
          .split(",")
          .filter(Boolean),
      );
      form
        .querySelectorAll('input[name="genres"], input[name="tags"]')
        .forEach((input) => {
          const selected = input.name === "genres"
            ? selectedGenres.has(String(input.value))
            : selectedTags.has(String(input.value));
          input.checked = selected;
          input.closest("label")?.classList.toggle("btn-primary", selected);
          input
            .closest("label")
            ?.classList.toggle("btn-outline-secondary", !selected);
        });
      registerPageCleanup(bindTaxonomyButtons(form));
      const uploadedPaths = [];
      registerPageCleanup(() => {
        if (!uploadedPaths.length) return;
        // Cleanup is detached from route teardown. A slow best-effort file
        // deletion must not delay the next panel page from mounting.
        void api("/uploads/cleanup", {
          method: "POST",
          detached: true,
          body: { paths: uploadedPaths },
        }).catch(() => {});
      });
      const coverFile = form.querySelector('[name="cover_file"]');
      const onCoverChange = async (event) => {
        try {
          const paths = await uploadImages(
            event.target.files,
            "series_cover",
          );
          uploadedPaths.push(...paths);
          if (paths[0]) {
            form.querySelector('[name="cover_image"]').value = paths[0];
          }
          showToast(translate("admin.series.cover_uploaded", "Kapak görseli yüklendi"));
        } catch (error) {
          if (error?.name === "AbortError") return;
          showToast(error.message, "danger");
        }
      };
      coverFile?.addEventListener("change", onCoverChange);
      if (coverFile) {
        registerPageCleanup(() => coverFile.removeEventListener("change", onCoverChange));
      }
      registerPageCleanup(bindFormAction(form, async (data) => {
        try {
          const selectedGenres = selectedValues(data, "genres");
          const selectedTags = selectedValues(data, "tags");
          const payload = Object.fromEntries(data.entries());
          delete payload.genres;
          delete payload.tags;
          delete payload.cover_file;
          payload.is_adult = form.elements.is_adult.checked ? 1 : 0;
          payload.is_members_only = form.elements.is_members_only.checked
            ? 1
            : 0;
          payload.disable_comments = form.elements.disable_comments.checked
            ? 1
            : 0;
          const response = mode === "edit"
            ? await api(`/content/${encodeURIComponent(id)}`, {
              method: "PUT",
              body: payload,
            })
            : await api("/content", { method: "POST", body: payload });
          const contentId = id || response?.data?.id;
          if (contentId) {
            try {
              await api(`/contents/${encodeURIComponent(contentId)}/taxonomy`, {
                method: "PUT",
                body: { genres: selectedGenres, tags: selectedTags },
              });
            } catch (taxonomyError) {
              if (taxonomyError?.name === "AbortError") throw taxonomyError;
              showToast(
                translate(
                  "admin.series.taxonomy_retry",
                  "İçerik kaydedildi ancak tür ve etiketler kaydedilemedi. Düzenleme sayfasından tekrar deneyin.",
                ),
                "warning",
              );
              panelNavigate(
                `/panel/series/${encodeURIComponent(contentId)}/edit`,
              );
              return;
            }
          }
          showToast(
            mode === "edit"
              ? translate("admin.series.updated", "İçerik güncellendi")
              : translate("admin.series.created", "İçerik oluşturuldu"),
          );
          panelNavigate("/panel/series");
        } catch (error) {
          if (error?.name === "AbortError") return;
          showToast(error.message, "danger");
        }
      }, {
        onError: (error) => showToast(error.message, "danger"),
      }));
    } catch (error) {
      if (error?.name === "AbortError") return;
      mountPartial("panel-page-error", target, {
        parent_path: "/panel/series",
        error_message: error.message,
      });
    }
  }

  return Object.freeze({ loadSeriesEditorPage });
}
