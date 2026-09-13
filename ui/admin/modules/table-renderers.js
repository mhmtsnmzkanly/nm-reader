/** Shared table presenters for panel collection pages. */
export function createPanelTableRenderers({
  store,
  hasPermission,
  setTableRows,
  renderPager,
  safeLocalPath,
  reportStatus,
  targetTypeLabel = (type) => String(type || "-") || "-",
  commentThreadFields,
  translate = (_key, fallback) => fallback,
  formatNumber = (value) => String(Number(value || 0)),
  formatDateTime = (value) => String(value || "-"),
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  function renderSeriesTable() {
    const lifecycleLabel = {
      draft: translate("admin.lifecycle.draft", "Taslak"),
      scheduled: translate("admin.lifecycle.scheduled", "Zamanlandı"),
      published: translate("admin.lifecycle.published", "Yayında"),
      archived: translate("admin.lifecycle.archived", "Arşivlendi"),
    };
    const lifecycleClass = {
      draft: "bg-secondary-subtle text-secondary",
      scheduled: "bg-info-subtle text-info",
      published: "bg-success-subtle text-success",
      archived: "bg-dark-subtle text-dark",
    };
    const items = (store.get("seriesList") || []).map((item) => {
      const lifecycle = item.lifecycle_status || "published";
      const lifecycleAction = lifecycle === "archived"
        ? "restore"
        : lifecycle === "published"
        ? "archive"
        : "publish";
      const lifecycleIcon = lifecycle === "archived"
        ? "arrow-counterclockwise"
        : lifecycle === "published"
        ? "archive"
        : "send-check";
      const canUpdate = hasPermission("admin.content.update");
      const seriesId = encodeURIComponent(String(item.id));
      return {
        ...item,
        series_id: seriesId,
        lifecycle_label: lifecycleLabel[lifecycle] || lifecycle,
        lifecycle_class: lifecycleClass[lifecycle] || "bg-light text-dark",
        lifecycle_action: lifecycleAction,
        lifecycle_icon: lifecycleIcon,
        scheduled_class: item.scheduled_at ? "" : "d-none",
        update_class: canUpdate ? "" : "d-none",
        can_update: canUpdate,
        edit_url: `/panel/series/${encodeURIComponent(item.id)}/edit`,
        revisions_url: `/panel/series/${seriesId}/revisions`,
        preview_url: `/panel/series/${seriesId}/preview`,
        chapters_url: `/panel/series/${seriesId}/chapters`,
      };
    });
    setTableRows("panel-series-list", "panel-rows-series", items, 6);
    renderPager(
      "panel-series-pager",
      store.get("seriesMeta"),
      "previousSeriesPage",
      "nextSeriesPage",
    );
  }

  function renderBlogsTable() {
    const canModerate = hasPermission("admin.blog.hide");
    const items = (store.get("blogsList") || []).map((blog) => {
      const canApprove = canModerate && Boolean(blog.can_approve);
      const canHide = canModerate && Boolean(blog.can_hide);
      return {
        ...blog,
        preview_url: `/panel/blogs/${
          encodeURIComponent(String(blog.id))
        }/preview`,
        likers_url: `/panel/blogs/${
          encodeURIComponent(String(blog.id))
        }/likers`,
        upvotes: Number(blog.upvote_count || blog.likes || 0),
        can_approve: canApprove,
        can_hide: canHide,
        can_delete: canModerate,
        approve_class: canApprove ? "" : "d-none",
        hide_class: canHide ? "" : "d-none",
        delete_class: canModerate ? "" : "d-none",
      };
    });
    setTableRows("panel-blogs-list", "panel-rows-blogs", items, 5);
    renderPager(
      "panel-blogs-pager",
      store.get("blogsMeta"),
      "previousBlogsPage",
      "nextBlogsPage",
    );
  }

  function renderCommentsTable() {
    const statusLabel = {
      pending: translate("admin.status.pending", "Bekliyor"),
      approved: translate("admin.status.approved", "Onaylı"),
      hidden: translate("admin.status.hidden", "Gizli"),
      deleted: translate("admin.status.deleted", "Silindi"),
    };
    const statusClass = {
      pending: "bg-warning-subtle text-warning",
      approved: "bg-success-subtle text-success",
      hidden: "bg-secondary-subtle text-secondary",
      deleted: "bg-danger-subtle text-danger",
    };
    const canModerate = hasPermission("admin.comment.delete");
    const items = (store.get("commentsList") || []).map((comment) => {
      const status = comment.moderation_status || "approved";
      const nextStatus = status === "approved" ? "hidden" : "approved";
      return {
        ...comment,
        ...commentThreadFields(comment),
        status_label: statusLabel[status] || status,
        status_class: statusClass[status] || "bg-light text-secondary",
        upvotes: Number(comment.upvote_count || 0),
        downvotes: Number(comment.downvote_count || 0),
        likers_url: `/panel/comments/${
          encodeURIComponent(String(comment.id))
        }/likers`,
        can_moderate: canModerate,
        moderate_class: canModerate ? "" : "d-none",
        next_status: nextStatus,
        next_label: nextStatus === "approved"
          ? translate("admin.action.approve", "Onayla")
          : translate("admin.action.hide", "Gizle"),
        next_class: nextStatus === "approved" ? "success" : "warning",
        next_icon: nextStatus === "approved" ? "check-circle" : "eye-slash",
      };
    });
    setTableRows("panel-comments-list", "panel-rows-comments", items, 7);
    renderPager(
      "panel-comments-pager",
      store.get("commentsMeta"),
      "previousCommentsPage",
      "nextCommentsPage",
    );
  }

  function renderReportsTable() {
    const items = (store.get("reportsList") || []).map((report) => {
      const status = reportStatus(report.status, translate);
      const targetUrl = safeLocalPath(report.target_url);
      return {
        ...report,
        target_type: targetTypeLabel(report.target_type, translate),
        status_label: status[0],
        status_class: status[1],
        target_label: report.target_title || report.comment_snippet ||
          report.target_id,
        has_target_url: targetUrl !== "#",
        target_url_safe: targetUrl,
        target_link_class: targetUrl === "#" ? "d-none" : "",
        detail_url: `/panel/reports/${Number(report.id)}`,
      };
    });
    setTableRows("panel-reports-list", "panel-rows-reports", items, 7);
    const meta = store.get("reportsMeta") || {};
    const counts = meta.counts || {};
    ["pending", "reviewing", "resolved", "rejected"].forEach((status) => {
      const element = document.getElementById(`panel-report-count-${status}`);
      if (element) element.textContent = String(Number(counts[status] || 0));
    });
    const page = Number(meta.page || 1);
    const totalPages = Math.max(1, Number(meta.total_pages || 1));
    const label = document.getElementById("panel-reports-page");
    if (label) {
      label.textContent = translate(
        "admin.pagination.summary",
        "Sayfa {page} / {pages} · {total} kayıt",
        { page, pages: totalPages, total: Number(meta.total || 0) },
      );
    }
    const previous = document.getElementById("panel-reports-prev");
    const next = document.getElementById("panel-reports-next");
    if (previous) previous.disabled = page <= 1;
    if (next) next.disabled = page >= totalPages;
  }

  function renderPackagesTable() {
    setTableRows(
      "panel-packages-list",
      "panel-rows-packages",
      (store.get("packagesList") || []).map((item) => ({ ...item })),
      6,
    );
  }

  function renderFinanceTable() {
    const eligible = new Set([
      "chapter_unlock",
      "series_unlock",
      "feature_unlock",
      "manual_debit",
    ]);
    const items = (store.get("financeList") || []).map((item) => {
      const canRefund = Number(item.coin_delta) < 0 &&
        eligible.has(item.type) &&
        Number(item.refunded_coin || 0) < Math.abs(Number(item.coin_delta));
      const canRefundAction = canRefund &&
        hasPermission("admin.finance.refund");
      const refunded = Number(item.refunded_coin || 0) > 0;
      return {
        ...item,
        delta: Number(item.coin_delta || 0),
        delta_prefix: Number(item.coin_delta) > 0 ? "+" : "",
        delta_class: Number(item.coin_delta) >= 0
          ? "text-success"
          : "text-danger",
        can_refund: canRefundAction,
        refund_class: canRefundAction ? "" : "d-none",
        refunded,
        refunded_class: refunded ? "" : "d-none",
        reference_label: `${item.reference_type || "-"} / ${
          item.reference_id || "-"
        }`,
        description_label: item.description || "-",
      };
    });
    setTableRows("panel-finance-list", "panel-rows-finance", items, 8);
    const summary = store.get("financeSummary") || {};
    const values = {
      circulating: summary.circulating_coin,
      credited: summary.credited_coin,
      spent: summary.spent_coin,
      refunded: summary.refunded_coin,
    };
    Object.entries(values).forEach(([key, value]) => {
      const element = document.getElementById(`panel-finance-${key}`);
      if (element) {
        element.textContent = formatNumber(value);
      }
    });
    renderPager(
      "panel-finance-pager",
      store.get("financeMeta"),
      "previousFinancePage",
      "nextFinancePage",
    );
  }

  function renderQueueTable() {
    const canManage = hasPermission("admin.jobs.run");
    const statusClasses = {
      pending: "bg-warning-subtle text-warning",
      processing: "bg-info-subtle text-info",
      done: "bg-success-subtle text-success",
      failed: "bg-danger-subtle text-danger",
      cancelled: "bg-secondary-subtle text-secondary",
    };
    const ageLabel = (value) => {
      if (!value) return "-";
      const date = new Date(String(value).replace(" ", "T"));
      if (Number.isNaN(date.getTime())) return "-";
      const minutes = Math.max(
        0,
        Math.floor((Date.now() - date.getTime()) / 60000),
      );
      if (minutes < 60) {
        return translate("admin.time.minutes", "{count} dk", { count: minutes });
      }
      const hours = Math.floor(minutes / 60);
      return hours < 24
        ? translate("admin.time.hours", "{count} sa", { count: hours })
        : translate("admin.time.days", "{count} gün", {
          count: Math.floor(hours / 24),
        });
    };
    const items = (store.get("queueJobsList") || []).map((job) => ({
      ...job,
      status_class: statusClasses[job.status] || "bg-light text-secondary",
      priority_label: Number(job.priority || 0),
      attempts_label: `${Number(job.attempts || 0)}/${
        Number(job.max_attempts || 0)
      }`,
      created_label: formatDateTime(job.created_at),
      available_label: formatDateTime(job.available_at),
      age_label: ageLabel(job.created_at),
      stale_label: job.status === "processing" && job.locked_until &&
          new Date(String(job.locked_until).replace(" ", "T")).getTime() <
            Date.now()
        ? translate("admin.queue.lock_expired", "Kilit süresi doldu")
        : "",
      can_retry: canManage && ["failed", "cancelled"].includes(job.status),
      can_cancel: canManage && job.status === "pending",
      retry_class: canManage && ["failed", "cancelled"].includes(job.status)
        ? ""
        : "d-none",
      cancel_class: canManage && job.status === "pending" ? "" : "d-none",
    }));
    setTableRows("panel-queue-jobs", "panel-rows-queue", items, 9);
    renderPager(
      "panel-queue-pager",
      store.get("queueMeta"),
      "previousQueuePage",
      "nextQueuePage",
    );
    const health = store.get("systemHealth") || {};
    const hasHealth = Object.keys(health).length > 0;
    const db = document.getElementById("panel-health-database");
    if (db) {
      db.textContent = !hasHealth
        ? translate("admin.health.permission_required", "Yetki gerekli")
        : health.database?.ok
        ? translate("admin.health.running", "Çalışıyor · {version}", {
          version: health.database.version || "",
        })
        : translate("admin.health.error", "Hata");
      db.className = `fw-bold ${
        !hasHealth
          ? "text-secondary"
          : health.database?.ok
          ? "text-success"
          : "text-danger"
      }`;
    }
    const storage = document.getElementById("panel-health-storage");
    if (storage) {
      storage.textContent = !hasHealth
        ? translate("admin.health.permission_required", "Yetki gerekli")
        : health.storage?.ok
        ? `${
          (Number(health.storage.free_bytes || 0) / 1073741824).toFixed(1)
        } GB boş · %${
          Number(health.storage.usage_pct || 0).toFixed(1)
        } kullanım`
        : translate("admin.health.write_error", "Yazma hatası");
      storage.className = `fw-bold ${
        !hasHealth
          ? "text-secondary"
          : health.storage?.ok
          ? "text-success"
          : "text-danger"
      }`;
    }
    const queue = document.getElementById("panel-health-queue");
    if (queue) {
      queue.textContent = !hasHealth
        ? translate("admin.health.permission_required", "Yetki gerekli")
        : translate(
          "admin.health.queue_summary",
          "{pending} bekleyen · {processing} işleniyor · {failed} hata",
          {
            pending: Number(health.queue?.pending || 0),
            processing: Number(health.queue?.processing || 0),
            failed: Number(health.queue?.failed || 0),
          },
        );
    }
    const queueDetail = document.getElementById("panel-health-queue-detail");
    if (queueDetail) {
      const oldest = health.queue?.oldest_pending_at;
      queueDetail.textContent = !hasHealth
        ? "-"
        : health.queue?.stale_processing
        ? translate("admin.health.queue_stale", "{count} kilitli · en eski {age}", {
          count: health.queue.stale_processing,
          age: ageLabel(oldest),
        })
        : translate("admin.health.queue_delayed", "Gecikmiş: {count} · en eski: {age}", {
          count: Number(health.queue?.delayed || 0),
          age: ageLabel(oldest),
        });
    }
    const backup = document.getElementById("panel-health-backup");
    if (backup) {
      backup.textContent = !hasHealth
        ? translate("admin.health.permission_required", "Yetki gerekli")
        : health.backup
        ? `${
          health.backup.complete
            ? translate("admin.health.complete", "Tam")
            : translate("admin.health.incomplete", "Eksik")
        } · ${health.backup.created_at}`
        : translate("admin.health.backup_missing", "Yedek bulunamadı");
    }
    const backupDetail = document.getElementById("panel-health-backup-detail");
    if (backupDetail) {
      backupDetail.textContent = !hasHealth
        ? "-"
        : health.backup
        ? `${health.backup.database_file ? "DB ✓" : "DB ✕"} · ${
          health.backup.media_file ? "Medya ✓" : "Medya ✕"
        }`
        : translate("admin.health.backup_none", "Henüz yedek alınmadı");
    }
    const migration = document.getElementById("panel-health-migration");
    if (migration) {
      migration.textContent = !hasHealth
        ? "-"
        : health.latest_migration?.version
        ? translate("admin.health.schema", "Şema: {version}", {
          version: health.latest_migration.version,
        })
        : translate("admin.health.schema_missing", "Şema bilgisi yok");
    }
    const advanced = document.getElementById("panel-advanced-maintenance");
    if (advanced) {
      advanced.hidden = !canManage ||
        health.capabilities?.root_maintenance !== true;
    }
    document.querySelectorAll("[data-developer-tool]").forEach((element) => {
      element.hidden = health.capabilities?.developer_tools !== true;
    });
  }

  function renderLogsTable() {
    const items = (store.get("logsList") || []).map((log) => ({
      ...log,
      status_class: Number(log.status_code) >= 500
        ? "bg-danger-subtle text-danger"
        : Number(log.status_code) >= 400
        ? "bg-warning-subtle text-warning"
        : "bg-success-subtle text-success",
      actor_label: log.username || log.user_id || "-",
      duration_label: `${Number(log.duration_ms || 0)}ms`,
    }));
    setTableRows("panel-audit-logs", "panel-rows-logs", items, 7);
    renderPager(
      "panel-logs-pager",
      store.get("logsMeta"),
      "previousLogsPage",
      "nextLogsPage",
    );
  }

  function renderUploadsTable() {
    const canDelete = hasPermission("admin.uploads.delete");
    const canOptimize = hasPermission("admin.uploads.optimize");
    const items = (store.get("uploadsList") || []).map((item) => {
      const references = Array.isArray(item.references) ? item.references : [];
      const refs = references.map((reference) => ({
        ...reference,
        label: `${reference.entity_type || translate("admin.target.record", "kayıt")} · ${
          reference.label || reference.entity_id || "-"
        }`,
        relation_label: reference.relation ? `(${reference.relation})` : "",
        url_safe: safeLocalPath(reference.url),
      }));
      refs.forEach((reference) => {
        reference.has_url = reference.url_safe !== "#";
      });
      return {
        ...item,
        id_number: Number(item.id),
        file_url: safeLocalPath(item.file_path),
        references: refs.map((reference) => ({
          ...reference,
          url_class: reference.has_url ? "" : "d-none",
        })),
        references_class: refs.length > 0 ? "" : "d-none",
        select_class: canDelete ? "" : "d-none",
        optimize_class: canOptimize ? "" : "d-none",
        delete_class: canDelete ? "" : "d-none",
        has_references: refs.length > 0,
        reference_count: refs.length,
        can_select: canDelete,
        can_optimize: canOptimize,
        can_delete: canDelete,
      };
    });
    setTableRows("panel-uploads-list", "panel-rows-uploads", items, 9);
    renderPager(
      "panel-uploads-pager",
      store.get("uploadsMeta"),
      "previousUploadsPage",
      "nextUploadsPage",
    );
    const stats = store.get("uploadsStats") || {};
    const count = document.getElementById("panel-upload-count");
    if (count) {
      count.textContent = formatNumber(stats.total_files);
    }
    const size = document.getElementById("panel-upload-size");
    if (size) {
      size.textContent = `${
        (Number(stats.total_bytes || 0) / 1048576).toFixed(1)
      } MB`;
    }
    const types = document.getElementById("panel-upload-types");
    if (types) {
      types.textContent = `JPEG ${Number(stats.jpeg_files || 0)} · PNG ${
        Number(stats.png_files || 0)
      } · WebP ${Number(stats.webp_files || 0)} · GIF ${
        Number(stats.gif_files || 0)
      }`;
    }
  }

  return Object.freeze({
    renderSeriesTable,
    renderBlogsTable,
    renderCommentsTable,
    renderReportsTable,
    renderPackagesTable,
    renderFinanceTable,
    renderQueueTable,
    renderLogsTable,
    renderUploadsTable,
  });
}
