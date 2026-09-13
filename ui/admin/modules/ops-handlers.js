/** Operations handlers for queue, maintenance and site configuration. */
export function createOpsHandlers({
  api,
  showToast,
  translate = (_key, fallback) => fallback,
  confirmAction = () => false,
  setOpsOperation,
  loadQueueJobsData,
  documentRef = globalThis.document,
} = {}) {
  const document = documentRef;
  return {
    async runQueueWorker() {
      setOpsOperation(translate("admin.ops.queue_running", "Kuyruk işleri çalıştırılıyor…"), "info", true);
      try {
        const limit = Number(
          document.getElementById("panel-queue-limit")?.value || 20,
        );
        const jobType =
          document.getElementById("panel-queue-run-type")?.value || null;
        const res = await api("/queue/run-once", {
          method: "POST",
          body: { limit, job_type: jobType || undefined },
        });
        const result = res?.data || {};
        await loadQueueJobsData();
        setOpsOperation(
          translate("admin.ops.queue_summary", "Kuyruk tamamlandı · {processed} işlendi · {failed} hata", {
            processed: Number(result.processed || 0),
            failed: Number(result.failed || 0),
          }),
          Number(result.failed || 0) > 0 ? "warning" : "success",
        );
        showToast(translate("admin.ops.queue_done", "Kuyruk çalıştırıldı"));
      } catch (err) {
        if (err?.name === "AbortError") return;
        setOpsOperation(err.message, "danger");
        showToast(err.message, "danger");
      }
    },

    async runRetentionCleanup() {
      const days = Number(
        document.getElementById("panel-cleanup-days")?.value || 30,
      );
      if (!Number.isFinite(days) || days < 7) {
        showToast(translate("admin.ops.cleanup_min_days", "Temizlik için en az 7 gün seçin."), "danger");
        return;
      }
      if (
        !confirmAction(
          translate(
            "admin.ops.cleanup_confirm",
            "{days} günden eski oturum, log ve tamamlanmış kuyruk kayıtları silinecek. Devam edilsin mi?",
            { days },
          ),
        )
      ) {
        return;
      }
      setOpsOperation(translate("admin.ops.cleanup_running", "Sistem temizliği çalıştırılıyor…"), "info", true);
      try {
        const response = await api("/retention/cleanup", {
          method: "POST",
          body: { days },
        });
        const result = response?.data || {};
        await loadQueueJobsData();
        setOpsOperation(
          translate(
            "admin.ops.cleanup_summary",
            "Temizlik tamamlandı · {count} kayıt silindi ({days} gün saklama)",
            { count: Number(result.total_deleted || 0), days },
          ),
          "success",
        );
        showToast(translate("admin.ops.cleanup_done", "Sistem temizliği başarıyla tamamlandı"));
      } catch (err) {
        if (err?.name === "AbortError") return;
        setOpsOperation(err.message, "danger");
        showToast(err.message, "danger");
      }
    },

    async runCacheWarmup() {
      setOpsOperation(translate("admin.ops.cache_running", "Önbellek ısıtılıyor…"), "info", true);
      try {
        const response = await api("/maintenance/warmup", { method: "POST" });
        const result = response?.data || {};
        setOpsOperation(
          Array.isArray(result.output)
            ? result.output[result.output.length - 1] || translate("admin.ops.cache_done", "Önbellek ısıtıldı")
            : translate("admin.ops.cache_done", "Önbellek başarıyla ısıtıldı"),
          result.success === false ? "danger" : "success",
        );
        showToast(
          result.success === false
            ? translate("admin.ops.cache_failed", "Önbellek ısıtma başarısız oldu")
            : translate("admin.ops.cache_done", "Önbellek başarıyla ısıtıldı"),
          result.success === false ? "danger" : "success",
        );
      } catch (err) {
        if (err?.name === "AbortError") return;
        setOpsOperation(err.message, "danger");
        showToast(err.message, "danger");
      }
    },

    async generateSitemap() {
      setOpsOperation(translate("admin.ops.sitemap_running", "Sitemap oluşturuluyor…"), "info", true);
      try {
        await api("/maintenance/sitemap", { method: "POST" });
        setOpsOperation(translate("admin.ops.sitemap_done", "Sitemap başarıyla oluşturuldu."), "success");
        showToast(translate("admin.ops.sitemap_done", "Sitemap başarıyla üretildi"));
      } catch (err) {
        if (err?.name === "AbortError") return;
        setOpsOperation(err.message, "danger");
        showToast(err.message, "danger");
      }
    },

    async runMaintenance({ element: el }) {
      const output = document.getElementById("panel-maintenance-output");
      if (output) output.textContent = translate("admin.ops.operation_running", "İşlem çalışıyor...");
      setOpsOperation(translate("admin.ops.maintenance_running", "Bakım işlemi çalışıyor…"), "info", true);
      try {
        const response = await api(`/maintenance/${el.dataset.task}`, {
          method: "POST",
        });
        const result = response?.data || {};
        if (output) {
          output.textContent = Array.isArray(result.output)
            ? result.output.join("\n")
            : JSON.stringify(result, null, 2);
        }
        if (result.success === false) {
          setOpsOperation(translate("admin.ops.maintenance_failed", "Bakım işlemi başarısız oldu."), "danger");
          showToast(translate("admin.ops.maintenance_failed", "Bakım işlemi başarısız oldu"), "danger");
        } else {
          setOpsOperation(translate("admin.ops.maintenance_done", "Bakım işlemi tamamlandı."), "success");
          showToast(translate("admin.ops.maintenance_done", "Bakım işlemi tamamlandı"));
        }
      } catch (error) {
        if (error?.name === "AbortError") return;
        if (output) output.textContent = error.message;
        setOpsOperation(error.message, "danger");
        showToast(error.message, "danger");
      }
    },

  };
}
