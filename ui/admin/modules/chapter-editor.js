/** Chapter form and image-page editor controller. */
export function createChapterEditorController({
  api,
  getPageEpoch,
  assertCurrentPage,
  mountEditorPage,
  mountPartial,
  registerPageCleanup,
  safeLocalPath,
  confirmAction = () => false,
  hasPermission,
  showToast,
  panelNavigate,
  translate = (_key, fallback) => fallback,
  documentRef = globalThis.document,
  storage = globalThis.localStorage,
} = {}) {
  const document = documentRef;
  const t = (key, fallback, params = {}) => {
    const value = typeof translate === "function"
      ? translate(key, fallback, params)
      : fallback;
    return value ?? fallback ?? key;
  };
  async function uploadImages(files, type) {
    const requestEpoch = getPageEpoch();
    if (!files?.length) return [];
    const body = new FormData();
    Array.from(files).forEach((file) => body.append("images[]", file));
    const response = await api(
      `/upload-images?type=${encodeURIComponent(type)}`,
      { method: "POST", detached: true, body },
    );
    const paths = response?.data?.paths || [];
    if (requestEpoch !== getPageEpoch()) {
      if (paths.length) {
        await api("/uploads/cleanup", {
          method: "POST",
          detached: true,
          body: { paths },
        });
      }
      assertCurrentPage(requestEpoch);
    }
    return paths;
  }

  function chapterPayload(formData, form) {
    const payload = Object.fromEntries(formData.entries());
    delete payload.page_files;
    if (Object.prototype.hasOwnProperty.call(payload, "price_amount")) {
      payload.price_amount = Number(payload.price_amount || 0);
    }
    payload.is_members_only = form.elements.is_members_only?.checked ? 1 : 0;
    if (payload.type === "image") {
      payload.pages = String(payload.pages || "")
        .split("\n")
        .map((value) => value.trim())
        .filter(Boolean);
      delete payload.body;
    } else {
      delete payload.pages;
    }
    return payload;
  }

  async function renderChapterPage(content, chapterId = null) {
    const requestEpoch = getPageEpoch();
    const chapter = chapterId
      ? (await api(`/chapters/${chapterId}`))?.data || {}
      : {};
    assertCurrentPage(requestEpoch);
    const draftKey = `nm_chapter_draft_${content.id}_${chapterId || "new"}`;
    let page = null;
    const clearDraft = () => {
      try {
        storage?.removeItem?.(draftKey);
      } catch {}
      const draftAlert = page?.querySelector?.("#panel-chapter-draft-alert");
      if (draftAlert) draftAlert.hidden = true;
    };
    const uploadedPaths = [];
    const originalPrice = chapterId
      ? Number(chapter.pricing?.base_price ?? chapter.price_amount ?? 0)
      : null;
    const dateValue = (value) =>
      value ? String(value).replace(" ", "T").slice(0, 16) : "";
    page = mountEditorPage(
      chapterId
        ? t("admin.chapter.edit_title", "Bölümü Düzenle: {number}", {
          number: chapter.chapter_number,
        })
        : t("admin.chapter.new_title", "{title} — Yeni Bölüm", {
          title: content.title,
        }),
      {
        name: "panel-chapter-form",
        context: {
          chapter_number: chapter.chapter_number || "",
          title: chapter.title || "",
          price_amount: chapter.pricing?.base_price ?? chapter.price_amount ??
            0,
          published_at: dateValue(
            chapter.pricing?.published_at ?? chapter.published_at,
          ),
          is_free_after: dateValue(
            chapter.pricing?.is_free_after ?? chapter.is_free_after,
          ),
          translator_note: chapter.translator_note || "",
          body: chapter.body || "",
          pages: Array.isArray(chapter.pages) ? chapter.pages.join("\n") : "",
        },
      },
      async (formData, form) => {
        const payload = chapterPayload(formData, form);
        const requestedPrice = chapterId &&
            !Object.prototype.hasOwnProperty.call(payload, "price_amount")
          ? originalPrice
          : Number(payload.price_amount || 0);
        if (chapterId) {
          if (
            requestedPrice !== originalPrice &&
            !hasPermission("admin.shop.manage")
          ) {
            throw new Error(
              t(
                "admin.chapter.price_permission",
                "Bölüm fiyatını değiştirmek için admin.shop.manage izni gerekir.",
              ),
            );
          }
          // Price changes use the dedicated pricing endpoint so its audit trail
          // and price_last_update semantics remain consistent with other shop
          // operations. The content update keeps the existing price otherwise.
          delete payload.price_amount;
        }
        const res = await api(
          chapterId
            ? `/chapters/${chapterId}`
            : `/content/${content.id}/chapters`,
          {
            method: chapterId ? "PUT" : "POST",
            body: payload,
          },
        );
        if (chapterId && requestedPrice !== originalPrice) {
          await api(`/chapters/${chapterId}/pricing`, {
            method: "PUT",
            body: { price_coin: requestedPrice, is_active: requestedPrice > 0 },
          });
        }
        const queueJobIds = Array.isArray(res?.data?.queue_job_ids)
          ? res.data.queue_job_ids
          : [];
        if (!chapterId && queueJobIds.length > 0) {
          showToast(
            t(
              "admin.chapter.created_queue_processing",
              "Bölüm oluşturuldu, arka plan bildirimleri işleniyor...",
            ),
          );
          for (const jobId of queueJobIds) {
            void api(`/queue/jobs/${encodeURIComponent(jobId)}/run`, {
              method: "POST",
              detached: true,
            }).catch(() => {});
          }
        } else {
          showToast(
            chapterId
              ? t("admin.chapter.updated", "Bölüm güncellendi")
              : t("admin.chapter.created", "Bölüm oluşturuldu"),
          );
        }
        clearDraft();
        panelNavigate(
          "/panel/series/" + encodeURIComponent(content.id) + "/chapters",
        );
      },
    );
    const typeInput = page.querySelector("#panel-chapter-type");
    if (typeInput) {
      typeInput.value = chapter.type === "image" ? "image" : "text";
    }
    const bodyTextarea = page.querySelector('[name="body"]');
    const noteTextarea = page.querySelector('[name="translator_note"]');
    const draftAlert = page.querySelector("#panel-chapter-draft-alert");
    const draftRestoreBtn = page.querySelector("#panel-chapter-draft-restore");
    const draftDiscardBtn = page.querySelector("#panel-chapter-draft-discard");

    try {
      const savedDraftRaw = storage?.getItem?.(draftKey);
      if (savedDraftRaw) {
        const savedDraft = JSON.parse(savedDraftRaw);
        const currentBody = bodyTextarea?.value || "";
        const currentNote = noteTextarea?.value || "";
        const hasBodyDiff = savedDraft.body && savedDraft.body !== currentBody;
        const hasNoteDiff = savedDraft.translator_note && savedDraft.translator_note !== currentNote;
        if (hasBodyDiff || hasNoteDiff) {
          if (draftAlert) {
            draftAlert.hidden = false;
            draftRestoreBtn?.addEventListener("click", () => {
              const EventCtor = document.defaultView?.Event || globalThis.Event;
              if (bodyTextarea && savedDraft.body !== undefined) {
                bodyTextarea.value = savedDraft.body;
                bodyTextarea.dispatchEvent(new EventCtor("input", { bubbles: true }));
              }
              if (noteTextarea && savedDraft.translator_note !== undefined) {
                noteTextarea.value = savedDraft.translator_note;
                noteTextarea.dispatchEvent(new EventCtor("input", { bubbles: true }));
              }
              draftAlert.hidden = true;
              showToast(t("admin.chapter.draft_restored", "Taslak geri yüklendi."), "success");
            }, { once: true });
            draftDiscardBtn?.addEventListener("click", () => {
              clearDraft();
            }, { once: true });
          }
        }
      }
    } catch {}

    let draftTimer = null;
    const onDraftInput = () => {
      if (typeInput?.value === "image") return;
      clearTimeout(draftTimer);
      draftTimer = setTimeout(() => {
        try {
          const body = bodyTextarea?.value || "";
          const note = noteTextarea?.value || "";
          if (body || note) {
            storage?.setItem?.(
              draftKey,
              JSON.stringify({ body, translator_note: note, savedAt: Date.now() })
            );
          }
        } catch {}
      }, 500);
    };
    bodyTextarea?.addEventListener("input", onDraftInput);
    noteTextarea?.addEventListener("input", onDraftInput);
    registerPageCleanup(() => {
      clearTimeout(draftTimer);
      bodyTextarea?.removeEventListener("input", onDraftInput);
      noteTextarea?.removeEventListener("input", onDraftInput);
    });
    const membersInput = page.querySelector('[name="is_members_only"]');
    if (membersInput) {
      membersInput.checked = Number(chapter.is_members_only) === 1;
    }
    const priceInput = page.querySelector('[name="price_amount"]');
    if (chapterId && priceInput && !hasPermission("admin.shop.manage")) {
      priceInput.disabled = true;
      priceInput.title =
        t(
          "admin.chapter.price_permission",
          "Fiyat değiştirmek için admin.shop.manage izni gerekir.",
        );
    }
    const pagesInput = page.querySelector('[name="pages"]');
    const pagePreview = page.querySelector("[data-page-preview]");
    const pagePaths = () =>
      pagesInput.value
        .split(/\r?\n/)
        .map((value) => value.trim())
        .filter(Boolean);
    const renderPagePreview = () => {
      const paths = pagePaths();
      const items = paths.map((path, index) => {
        const localPath = safeLocalPath(path);
        return {
          path,
          url: localPath === "#"
            ? "/assets/img/covers/placeholder.svg"
            : localPath,
          index,
          number: index + 1,
          first_disabled: index === 0 ? "disabled" : "",
          last_disabled: index === paths.length - 1 ? "disabled" : "",
        };
      });
      mountPartial("panel-chapter-pages-preview", pagePreview, {
        items,
        has_items: items.length > 0,
      });
      pagePreview
        .querySelectorAll('[data-page-disabled="disabled"]')
        .forEach((button) => {
          button.disabled = true;
        });
      pagePreview.querySelectorAll("[data-page-image]").forEach((image) => {
        image.addEventListener(
          "error",
          () => {
            const fallback = document.createElement("div");
            fallback.className =
              "d-flex align-items-center justify-content-center text-secondary small p-2";
            fallback.textContent = t("admin.chapter.no_preview", "Önizleme yok");
            image.replaceWith(fallback);
          },
          { once: true },
        );
      });
    };
    const onPreviewClick = (event) => {
      const button = event.target.closest(
        "[data-page-move], [data-page-remove]",
      );
      if (!button) return;
      const card = button.closest("[data-page-card]");
      const index = Number(card?.dataset.pageIndex);
      const paths = pagePaths();
      if (!Number.isInteger(index) || !paths[index]) return;
      if (button.hasAttribute("data-page-remove")) {
        paths.splice(index, 1);
      } else {
        const direction = button.dataset.pageMove === "up" ? -1 : 1;
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= paths.length) return;
        [paths[index], paths[targetIndex]] = [paths[targetIndex], paths[index]];
      }
      pagesInput.value = paths.join("\n");
      renderPagePreview();
    };
    pagePreview.addEventListener("click", onPreviewClick);
    registerPageCleanup(() => pagePreview.removeEventListener("click", onPreviewClick));
    const onPagesInput = () => renderPagePreview();
    pagesInput.addEventListener("input", onPagesInput);
    registerPageCleanup(() => pagesInput.removeEventListener("input", onPagesInput));
    let previousType = typeInput.value;
    const syncChapterFields = () => {
      const image = typeInput.value === "image";
      page.querySelector("[data-chapter-body]").hidden = image;
      page.querySelector("[data-chapter-pages]").hidden = !image;
    };
    const onTypeChange = () => {
      const nextType = typeInput.value;
      if (nextType !== previousType) {
        const warning = nextType === "image"
          ? t(
            "admin.chapter.convert_to_image",
            "Metin içeriği görsel bölüme çevrilecek. Kaydederseniz metin içeriği kaldırılır. Devam edilsin mi?",
          )
          : t(
            "admin.chapter.convert_to_text",
            "Görsel sayfaları metin bölüme çevrilecek. Kaydederseniz görsel sayfaları kaldırılır. Devam edilsin mi?",
          );
        if (!confirmAction(warning)) {
          typeInput.value = previousType;
          return;
        }
        previousType = nextType;
      }
      syncChapterFields();
    };
    typeInput.addEventListener("change", onTypeChange);
    registerPageCleanup(() => typeInput.removeEventListener("change", onTypeChange));
    const pageFiles = page.querySelector('[name="page_files"]');
    const onPageFilesChange = async (event) => {
      const files = Array.from(event.target.files || []);
      const zipFiles = files.filter((file) => /\.zip$/i.test(file.name));
      if (zipFiles.length > 0 && files.length > 1) {
        showToast(
          t(
            "admin.chapter.zip_selection",
            "ZIP ile diğer görselleri aynı anda seçmeyin; önce ZIP veya görsellerden birini yükleyin.",
          ),
          "danger",
        );
        event.target.value = "";
        return;
      }
      try {
        const paths = await uploadImages(files, "chapters");
        uploadedPaths.push(...paths);
        const textarea = page.querySelector('[name="pages"]');
        const existing = textarea.value
          .split("\n")
          .map((value) => value.trim())
          .filter(Boolean);
        textarea.value = [...existing, ...paths].join("\n");
        renderPagePreview();
        showToast(
          t("admin.chapter.images_uploaded", "{count} görsel yüklendi", {
            count: paths.length,
          }),
        );
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
      event.target.value = "";
    };
    pageFiles?.addEventListener("change", onPageFilesChange);
    if (pageFiles) {
      registerPageCleanup(() => pageFiles.removeEventListener("change", onPageFilesChange));
    }
    registerPageCleanup(() => {
      if (uploadedPaths.length > 0) {
        void api("/uploads/cleanup", {
          method: "POST",
          detached: true,
          body: { paths: uploadedPaths },
        }).catch(() => {});
      }
    });
    syncChapterFields();
    renderPagePreview();
  }

  return Object.freeze({ renderChapterPage, uploadImages });
}
