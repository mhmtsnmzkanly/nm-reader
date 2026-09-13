/** Series, chapter and blog preview page controller. */
export function createContentPreviewsController({
  store,
  api,
  getPageEpoch,
  assertCurrentPage,
  safeLocalPath,
  mountEditorPage,
  translate = (_key, fallback) => fallback,
} = {}) {
  async function loadSeriesPreviewPage(contentId) {
    const requestEpoch = getPageEpoch();
    const response = await api(
      `/content/${encodeURIComponent(String(contentId))}/preview`,
    );
    assertCurrentPage(requestEpoch);
    const content = response?.data || {};
    const coverImage = safeLocalPath(content.cover_image);
    const configuredCover = safeLocalPath(
      store.get("config")?.default_content_cover_image,
    );
    const previewCover = coverImage !== "#"
      ? coverImage
      : configuredCover !== "#"
      ? configuredCover
      : "/assets/img/covers/placeholder.svg";
    const urlPath = safeLocalPath(content.url_path);
    const page = mountEditorPage(
      translate("admin.preview.content_title", "Önizleme: {title}", {
        title: content.title || contentId,
      }),
      {
        name: "panel-series-preview",
        context: {
          type: content.type || "-",
          status: content.status || "-",
          lifecycle_status: content.lifecycle_status || "-",
          title: content.title || translate("admin.content.untitled", "-"),
          alternative_titles: content.alternative_titles || "-",
          description: content.description ||
            translate("admin.content.no_description", "Açıklama yok"),
          author: content.author || "-",
          artist: content.artist || "-",
          scheduled_at: content.scheduled_at || "-",
          is_published: content.lifecycle_status === "published" &&
            urlPath !== "#",
          url_path: urlPath,
        },
      },
    );
    const cover = page.querySelector("[data-preview-cover]");
    if (cover) cover.setAttribute("src", previewCover);
    const liveLink = page.querySelector("[data-preview-live]");
    if (liveLink && urlPath !== "#") liveLink.setAttribute("href", urlPath);
  }

  async function loadChapterPreviewPage(seriesId, chapterId) {
    const requestEpoch = getPageEpoch();
    const response = await api(`/chapters/${encodeURIComponent(chapterId)}`);
    assertCurrentPage(requestEpoch);
    const chapter = response?.data || {};
    const chapterNumber = String(chapter.chapter_number || "-");
    const type = String(chapter.type || "text").toLowerCase();
    const pages = Array.isArray(chapter.pages)
      ? chapter.pages.map((page, index) => {
        const path = page && typeof page === "object"
          ? page.url || page.image_path || ""
          : page;
        const imagePath = safeLocalPath(path);
        return {
          number: index + 1,
          url: imagePath === "#"
            ? "/assets/img/covers/placeholder.svg"
            : imagePath,
        };
      })
      : [];
    const pricing = chapter.pricing || {};
    const effectivePrice = Number(
      pricing.price_coin ?? chapter.price_amount ?? 0,
    );
    const publishedAt = pricing.published_at || chapter.published_at || "";
    const isPublished = Boolean(publishedAt) &&
      String(chapter.series_lifecycle_status || "") === "published";
    const seriesType = String(chapter.series_type || "").replaceAll("_", "-");
    const seriesSlug = String(chapter.series_slug || "");
    const publicUrl = seriesType && seriesSlug && chapterNumber !== "-"
      ? `/${encodeURIComponent(seriesType)}/${
        encodeURIComponent(seriesSlug)
      }/chapter/${encodeURIComponent(chapterNumber)}`
      : "#";

    const page = mountEditorPage(
      translate("admin.preview.chapter_title", "Önizleme: Bölüm {number}", {
        number: chapterNumber,
      }),
      {
        name: "panel-chapter-preview",
        context: {
          type_label: type === "image"
            ? translate("admin.content.image", "Görsel")
            : translate("admin.content.text", "Metin"),
          publication_label: isPublished
            ? translate("admin.publication.published", "Yayınlandı")
            : publishedAt
            ? translate("admin.publication.scheduled", "Yayın bekliyor")
            : translate("admin.status.draft", "Taslak"),
          price_label: effectivePrice > 0
            ? `${effectivePrice} ${translate("admin.currency.coin", "coin")}`
            : translate("admin.access.free", "Ücretsiz"),
          access_label: Number(chapter.is_members_only) === 1
            ? translate("admin.access.members", "Sadece üyeler")
            : translate("admin.access.public", "Açık erişim"),
          heading: chapter.title
            ? translate("admin.chapter.heading", "Bölüm {number} — {title}", {
              number: chapterNumber,
              title: chapter.title,
            })
            : translate("admin.chapter.number", "Bölüm {number}", {
              number: chapterNumber,
            }),
          series_title: chapter.series_title || seriesId,
          chapter_number: chapterNumber,
          is_text: type === "text",
          body: chapter.body || translate(
            "admin.content.no_chapter_text",
            "Bu bölüm için metin içeriği bulunamadı.",
          ),
          has_pages: pages.length > 0,
          pages,
          has_translator_note: Boolean(
            String(chapter.translator_note || "").trim(),
          ),
          translator_note: chapter.translator_note || "",
          is_published: isPublished && publicUrl !== "#",
          public_url: publicUrl,
        },
      },
    );
    const liveLink = page.querySelector("[data-preview-live]");
    if (liveLink && isPublished && publicUrl !== "#") {
      liveLink.setAttribute("href", safeLocalPath(publicUrl));
    }
  }

  async function loadBlogPreviewPage(blogId) {
    const requestEpoch = getPageEpoch();
    const response = await api(`/blogs/${encodeURIComponent(blogId)}/preview`);
    assertCurrentPage(requestEpoch);
    const blog = response?.data || {};
    const coverImage = safeLocalPath(blog.cover_image);
    const isPublished = Number(blog.approved) === 1 &&
      String(blog.status || "") === "published" &&
      !blog.deleted_at &&
      Boolean(blog.slug);
    const statusLabels = {
      draft: translate("admin.status.draft", "Taslak"),
      pending: translate("admin.status.pending", "Bekliyor"),
      published: translate("admin.status.published", "Yayınlandı"),
      rejected: translate("admin.status.rejected", "Reddedildi"),
      hidden: translate("admin.status.hidden", "Gizli"),
    };
    const statusClasses = {
      draft: "bg-secondary-subtle text-secondary",
      pending: "bg-warning-subtle text-warning",
      published: "bg-success-subtle text-success",
      rejected: "bg-danger-subtle text-danger",
      hidden: "bg-dark-subtle text-dark",
    };

    const publicUrl = isPublished
      ? safeLocalPath(`/blogs/${encodeURIComponent(String(blog.slug))}`)
      : "#";
    const page = mountEditorPage(
      translate("admin.preview.blog_title", "Önizleme: {title}", {
        title: blog.title || blogId,
      }),
      {
        name: "panel-blog-preview",
        context: {
          has_cover: coverImage !== "#",
          cover_image: coverImage,
          title: blog.title ||
            translate("admin.blog.untitled", "Başlıksız blog"),
          username: blog.username || "-",
          created_at: blog.created_at || "-",
          body: blog.body ||
            translate("admin.content.not_found", "İçerik bulunamadı."),
          status_label: statusLabels[blog.status] || blog.status ||
            translate("admin.unknown", "Bilinmiyor"),
          status_badge: statusClasses[blog.status] ||
            "bg-secondary-subtle text-secondary",
          is_published: isPublished,
          public_url: publicUrl,
        },
      },
    );
    const liveLink = page.querySelector("[data-preview-live]");
    if (liveLink && publicUrl !== "#") liveLink.setAttribute("href", publicUrl);
  }

  return Object.freeze({
    loadSeriesPreviewPage,
    loadChapterPreviewPage,
    loadBlogPreviewPage,
  });
}
