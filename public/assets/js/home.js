/**
 * Home.js - Logic for rendering the Platform Homepage.
 *
 * This module fetches aggregated homepage data and populates various UI sections:
 * - Explore Grid: Popular series across the platform.
 * - Recently Updated: Latest chapter releases.
 * - Recently Added: Newest series entries.
 * - Blog Lists: Trending and latest moderator-approved posts.
 */
$(function () {
  const ctx = window.__NMR_CONTEXT || {};

  /**
   * Derives the URL segment from a content item.
   * @param {Object} item
   * @returns {string} e.g. 'light-novel'
   */
  const typeSegment = (item) => {
    if (item.type_path) return item.type_path;
    return String(item.type || "novel").replace(/_/g, "-");
  };

  /**
   * Returns the CSS color variable for a specific content type.
   * @param {string} type
   * @returns {string}
   */
  const getTypeColor = (type) => {
    switch (String(type || "").toLowerCase()) {
      case "manga":
        return "var(--primary)";
      case "novel":
      case "light_novel":
      case "web_novel":
        return "var(--success)";
      case "webtoon":
        return "var(--info)";
      case "manhwa":
        return "var(--warning)";
      default:
        return "var(--secondary)";
    }
  };

  /**
   * Renders a grid of series cards.
   * @param {Array} items
   * @param {string} targetId CSS selector.
   */
  const renderGrid = (items, targetId) => {
    const lang = window.NMR.getLangPrefix();
    const html = (items || []).map((item) => {
      const type = typeSegment(item);
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card" onclick="location.href='/${lang}/${type}/${item.slug}'">
        <div class="position-relative">
          <img src="${item.cover_image || "/assets/img/covers/one-piece.jpg"}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="w-100" alt="${item.title}" loading="lazy">
          <span class="badge position-absolute top-0 right-0 m-2 text-xs" style="background:${getTypeColor(item.type)}">${String(item.type || "").toUpperCase()}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1" title="${item.title}">${item.title}</h4>
          <div class="flex justify-between items-center text-xs text-muted">
            <span>⭐ ${item.rating_avg ?? "-"}</span>
            <span>${item.chapter_count ?? 0} Chapters</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $(targetId).addClass('content-grid').html(html || `<div class="text-muted">${window.NMR.__t('no_content_found')}</div>`);
  };

  /**
   * Renders a grid specifically for recently updated chapters.
   */
  const renderChapterGrid = (items, targetId) => {
    const lang = window.NMR.getLangPrefix();
    const html = (items || []).map((ch) => {
      const type = ch.type_path;
      const url = `/${lang}/${type}/${ch.series_slug}/chapter/${ch.chapter_number}`;
      return `
      <div class="card p-0 overflow-hidden hover-lift cursor-pointer content-card chapter-card" onclick="location.href='${url}'">
        <div class="position-relative">
          <img src="${ch.cover_image}" onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';" class="w-100" alt="${ch.series_title}" loading="lazy">
          <span class="badge position-absolute top-0 right-0 m-2 text-sm badge-chapter" style="background:var(--primary); padding: 0.4rem 0.8rem;">BÖLÜM ${ch.chapter_number}</span>
        </div>
        <div class="p-3">
          <h4 class="mb-1 text-md" title="${ch.series_title}">${ch.series_title}</h4>
          <div class="flex flex-col gap-1 mt-2">
            <span class="text-sm font-bold text-primary">${ch.chapter_title || 'Yeni Bölüm Yayında!'}</span>
            <span class="text-xs text-muted">${String(ch.created_at || '').split(' ')[0]}</span>
          </div>
        </div>
      </div>
    `;
    }).join('');

    $(targetId).addClass('content-grid').html(html || `<div class="text-muted">${window.NMR.__t('no_chapters')}</div>`);
  };

  /**
   * Renders trending and latest blog post lists.
   */
  const renderBlogs = (popular, latest) => {
    const lang = window.NMR.getLangPrefix();
    const renderItem = (b) => `
      <div class="blog-list-item cursor-pointer" onclick="location.href='/${lang}/blogs/${b.slug}'">
        <div class="blog-title">${b.title}</div>
        <div class="blog-meta mt-1">
          <span class="blog-author"><a href="/${lang}/profile/${b.author_username || "admin"}" style="color:inherit; text-decoration:none;" onclick="event.stopPropagation();">@${b.author_username || "admin"}</a></span>
          <span class="ms-2 opacity-75" style="font-size:0.75rem">${String(b.approved_at || b.created_at || "").split(" ")[0]}</span>
        </div>
      </div>
    `;

    $("#popularBlogsList").html(
      (popular || []).map(renderItem).join("") ||
        `<div class="p-4 text-muted text-sm">${window.NMR.__t('no_popular_posts')}</div>`,
    );
    $("#latestBlogsList").html(
      (latest || []).map(renderItem).join("") ||
        `<div class="p-4 text-muted text-sm">${window.NMR.__t('no_updates_yet')}</div>`,
    );
  };

  // Main Execution
  const init = async () => {
    // Ensure translations are ready
    if (window.NMR.waitForI18n) await window.NMR.waitForI18n();

    Connection.getHome()
      .then((res) => {
        renderGrid(res.data.explore, '#homeExploreGrid');
        renderChapterGrid(res.data.recent_chapters, '#homeUpdatedGrid');
        renderGrid(res.data.recently_added, '#homeAddedGrid');
        renderBlogs(res.data.popular_blogs, res.data.latest_blogs);
        $("#homeLoading").hide();
        $("#homeApp").removeClass("hidden").fadeIn();
      })
      .catch((err) => {
        $("#homeLoading").html(
          `<div class="text-danger">${err.message || window.NMR.__t('msg_load_failed')}</div>`,
        );
      });
  };

  init();
});
