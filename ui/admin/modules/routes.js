export const panelPageHeaderKeys = Object.freeze({
  "series-new": ["admin.series.new_title", "Yeni seri oluştur", "admin.series.description", "Seri bilgilerini ve sınıflandırmasını yönetin."],
  "series-edit": ["admin.series.edit_title", "Seriyi düzenle", "admin.series.description", "Seri bilgilerini ve sınıflandırmasını yönetin."],
  "report-detail": ["admin.moderation.report_title", "Rapor detayı", "admin.moderation.report_description", "Raporu inceleyin ve moderasyon kararını kaydedin."],
  dashboard: ["admin.page.dashboard.title", "Genel Bakış & İstatistikler", "admin.page.dashboard.description", "Platformun anlık metrikleri ve sistem durumu"],
  series: ["admin.page.series.title", "İçerik & Bölüm Yönetimi", "admin.page.series.description", "Manga, Novel, Webtoon serileri ve bölümlerini yönetin"],
  user: ["admin.page.user.title", "Kullanıcılar & Yetkilendirme (RBAC)", "admin.page.user.description", "Kullanıcı hesapları, roller, yasaklamalar ve bakiye yönetimi"],
  blogs: ["admin.page.blogs.title", "Blog & Gönderi Moderasyonu", "admin.page.blogs.description", "Kullanıcı blog gönderilerini onaylayın veya gizleyin"],
  comments: ["admin.page.comments.title", "Yorum Moderasyonu", "admin.page.comments.description", "Bölüm ve seri yorumlarını inceleyin"],
  reports: ["admin.page.reports.title", "Raporlar & Şikâyetler", "admin.page.reports.description", "İçerik ve topluluk raporlarını inceleyip sonuçlandırın"],
  monetization: ["admin.page.monetization.title", "Para Kazanma & Coin Paketleri", "admin.page.monetization.description", "Mağaza paketleri ve bakiye yapılandırması"],
  finance: ["admin.page.finance.title", "Finans & Cüzdan Hareketleri", "admin.page.finance.description", "Son 30 gün özeti, işlem defteri ve kontrollü iadeler"],
  ops: ["admin.page.ops.title", "Kuyruk & Sistem Operasyonları", "admin.page.ops.description", "Arka plan işleri, önbellek ısıtma ve temizlik araçları"],
  logs: ["admin.page.logs.title", "Sistem & Denetim Logları", "admin.page.logs.description", "Güvenlik denetimleri ve moderasyon hareketleri"],
  uploads: ["admin.page.uploads.title", "Sistem Yüklemeleri", "admin.page.uploads.description", "Görsel önizleme, teknik bilgiler, çoklu bağlantı takibi ve orphan temizliği"],
  help: ["admin.page.help.title", "Panel Kullanım Kılavuzu", "admin.page.help.description", "Yönetim işlemleri için kısa başvuru"],
  config: ["admin.page.config.title", "Site Yapılandırması", "admin.page.config.description", "Genel site ayarları, tema, güvenlik ve e-posta parametreleri"],
  webhook: ["admin.page.webhook.title", "Webhook Yönetimi", "admin.page.webhook.description", "Olay bildirimlerini yönetin ve bağlantıları test edin."],
  "config-env": ["admin.page.config_env.title", "Ortam Değişkenleri (.env)", "admin.page.config_env.description", "Uygulama, adres, güvenlik ve entegrasyon değişkenleri."],
});

export const panelRootRoutes = new Set([
  "dashboard", "series", "user", "blogs", "comments", "reports",
  "monetization", "finance", "ops", "logs", "uploads", "help", "config",
  "webhook", "config-env",
]);

export function createPanelRoutes({ loaders = {}, page: pageFactory } = {}) {
  const {
    loadSeriesData,
    loadSeriesEditorPage,
    loadOwnershipPage,
    loadSeriesPreviewPage,
    loadSeriesRevisionsPage,
    loadChaptersPage,
    loadChapterPage,
    loadChapterPreviewPage,
    loadTeamPage,
    loadTaxonomyPage,
    loadUsersData,
    loadUserDetailPage,
    loadUserPenaltyPage,
    loadUserWalletPage,
    loadRbacPage,
    loadBlogsData,
    loadBlogPreviewPage,
    loadLikersPage,
    loadCommentsData,
    loadReportsData,
    loadReportDetailPage,
    loadPackagesData,
    renderPackagePage,
    loadPackagePage,
    loadAdFreePage,
    loadPricingPage,
    loadFinanceData,
    loadQueueJobsData,
    loadLogsData,
    loadModerationPage,
    loadLogPage,
    loadAuditPage,
    loadUploadsData,
    loadConfigData,
    loadWebhookPage,
    loadEnvPage,
    loadDashboardData,
  } = loaders;
  const panelPage = pageFactory ||
    ((view, options = {}) => ({
      view,
      permissions: ["admin.panel.access"],
      ...options,
    }));
  const panelRoutes = {
    segment: "",
    branches: [
      {
        segment: "dashboard",
        index: panelPage("panel-dashboard", {
          route: "dashboard",
          section: "dashboard",
          permissions: ["admin.metrics.view"],
          load: () => loadDashboardData(),
        }),
      },
      {
        segment: "series",
        index: panelPage("panel-series", {
          route: "series",
          section: "series",
          load: () => loadSeriesData(),
        }),
        branches: [
          {
            segment: "new",
            index: panelPage("panel-series-editor", {
              route: "series-new",
              section: "series",
              permissions: ["admin.content.create"],
              load: () => loadSeriesEditorPage("new"),
            }),
          },
          {
            segment: "ownership",
            index: panelPage(null, {
              route: "series-ownership",
              section: "series",
              permissions: ["admin.panel.access"],
              load: () => loadOwnershipPage(),
            }),
          },
          {
            segment: /^(?<seriesId>[a-zA-Z0-9_-]+)$/,
            param: "seriesId",
            branches: [
              {
                segment: "edit",
                index: panelPage("panel-series-editor", {
                  route: "series-edit",
                  section: "series",
                  permissions: ["admin.content.update"],
                  load: ({ seriesId }) =>
                    loadSeriesEditorPage("edit", seriesId),
                }),
              },
              {
                segment: "preview",
                index: panelPage(null, {
                  route: "series-preview",
                  section: "series",
                  permissions: ["admin.panel.access"],
                  load: ({ seriesId }) => loadSeriesPreviewPage(seriesId),
                }),
              },
              {
                segment: "revisions",
                index: panelPage(null, {
                  route: "series-revisions",
                  section: "series",
                  permissions: ["admin.panel.access"],
                  load: ({ seriesId }) => loadSeriesRevisionsPage(seriesId),
                }),
              },
              {
                segment: "chapters",
                index: panelPage(null, {
                  route: "series-chapters",
                  section: "series",
                  permissions: ["admin.panel.access"],
                  load: ({ seriesId }) => loadChaptersPage(seriesId),
                }),
                branches: [
                  {
                    segment: "new",
                    index: panelPage(null, {
                      route: "chapter-new",
                      section: "series",
                      permissions: ["admin.chapter.create"],
                      load: ({ seriesId }) => loadChapterPage(seriesId),
                    }),
                  },
                  {
                    segment: /^(?<chapterId>[a-zA-Z0-9_-]+)$/,
                    param: "chapterId",
                    branches: [
                      {
                        segment: "edit",
                        index: panelPage(null, {
                          route: "chapter-edit",
                          section: "series",
                          permissions: ["admin.content.update"],
                          load: ({ seriesId, chapterId }) =>
                            loadChapterPage(seriesId, chapterId),
                        }),
                      },
                      {
                        segment: "preview",
                        index: panelPage(null, {
                          route: "chapter-preview",
                          section: "series",
                          permissions: ["admin.panel.access"],
                          load: ({ seriesId, chapterId }) =>
                            loadChapterPreviewPage(seriesId, chapterId),
                        }),
                      },
                    ],
                  },
                ],
              },
              {
                segment: "team",
                index: panelPage(null, {
                  route: "series-team",
                  section: "series",
                  permissions: ["admin.panel.access"],
                  load: ({ seriesId }) => loadTeamPage(seriesId),
                }),
              },
            ],
          },
        ],
      },
      {
        segment: "taxonomies",
        index: panelPage(null, {
          route: "taxonomies",
          section: "taxonomies",
          permissions: ["admin.content.create", "admin.content.update"],
          load: () => loadTaxonomyPage(),
        }),
      },
      {
        segment: "user",
        index: panelPage("panel-user", {
          route: "user",
          section: "user",
          load: () => loadUsersData(),
        }),
        branches: [
          {
            segment: "roles",
            index: panelPage(null, {
              route: "user-roles",
              section: "user",
              permissions: ["admin.panel.access"],
              load: () => loadRbacPage(),
            }),
          },
          {
            segment: /^(?<userId>[a-zA-Z0-9_-]+)$/,
            param: "userId",
            index: panelPage(null, {
              route: "user-detail",
              section: "user",
              permissions: ["admin.users.manage"],
              load: ({ userId }) => loadUserDetailPage(userId),
            }),
            branches: [
              {
                segment: "penalty",
                index: panelPage(null, {
                  route: "user-penalty",
                  section: "user",
                  permissions: ["admin.users.manage"],
                  load: ({ userId }) => loadUserPenaltyPage(userId),
                }),
              },
              {
                segment: "wallet",
                index: panelPage(null, {
                  route: "user-wallet",
                  section: "user",
                  permissions: ["admin.wallet.view"],
                  load: ({ userId }) => loadUserWalletPage(userId),
                }),
              },
            ],
          },
        ],
      },
      {
        segment: "blogs",
        index: panelPage("panel-blogs", {
          route: "blogs",
          section: "blogs",
          load: () => loadBlogsData(),
        }),
        branches: [
          {
            segment: /^(?<blogId>[a-zA-Z0-9_-]+)$/,
            param: "blogId",
            branches: [
              {
                segment: "preview",
                index: panelPage(null, {
                  route: "blog-preview",
                  section: "blogs",
                  permissions: ["admin.panel.access"],
                  load: ({ blogId }) => loadBlogPreviewPage(blogId),
                }),
              },
              {
                segment: "likers",
                index: panelPage(null, {
                  route: "blog-likers",
                  section: "blogs",
                  permissions: ["admin.panel.access"],
                  load: ({ blogId }) => loadLikersPage("blog", blogId),
                }),
              },
            ],
          },
        ],
      },
      {
        segment: "comments",
        index: panelPage("panel-comments", {
          route: "comments",
          section: "comments",
          load: () => loadCommentsData(),
        }),
        branches: [
          {
            segment: /^(?<commentId>[0-9]+)$/,
            param: "commentId",
            branches: [
              {
                segment: "likers",
                index: panelPage(null, {
                  route: "comment-likers",
                  section: "comments",
                  permissions: ["admin.panel.access"],
                  load: ({ commentId }) => loadLikersPage("comment", commentId),
                }),
              },
            ],
          },
        ],
      },
      {
        segment: "reports",
        index: panelPage("panel-reports", {
          route: "reports",
          section: "reports",
          permissions: ["admin.reports.view"],
          load: () => loadReportsData(),
        }),
        branches: [
          {
            segment: /^(?<reportId>[0-9]+)$/,
            param: "reportId",
            index: panelPage("panel-report-detail", {
              route: "report-detail",
              section: "reports",
              permissions: ["admin.reports.view"],
              load: ({ reportId }) => loadReportDetailPage(reportId),
            }),
          },
        ],
      },
      {
        segment: "monetization",
        index: panelPage("panel-monetization", {
          route: "monetization",
          section: "monetization",
          permissions: ["admin.shop.manage"],
          load: () => loadPackagesData(),
        }),
        branches: [
          {
            segment: "package",
            branches: [
              {
                segment: "new",
                index: panelPage(null, {
                  route: "package-new",
                  section: "monetization",
                  permissions: ["admin.shop.manage"],
                  load: () => renderPackagePage(),
                }),
              },
              {
                segment: /^(?<packageId>[a-zA-Z0-9_-]+)$/,
                param: "packageId",
                branches: [
                  {
                    segment: "edit",
                    index: panelPage(null, {
                      route: "package-edit",
                      section: "monetization",
                      permissions: ["admin.shop.manage"],
                      load: ({ packageId }) => loadPackagePage(packageId),
                    }),
                  },
                ],
              },
            ],
          },
          {
            segment: "ad-free",
            index: panelPage(null, {
              route: "ad-free",
              section: "monetization",
              permissions: ["admin.shop.manage"],
              load: () => loadAdFreePage(),
            }),
          },
          {
            segment: "pricing",
            index: panelPage(null, {
              route: "pricing",
              section: "monetization",
              permissions: ["admin.shop.manage"],
              load: () => loadPricingPage(),
            }),
          },
        ],
      },
      {
        segment: "finance",
        index: panelPage("panel-finance", {
          route: "finance",
          section: "finance",
          permissions: ["admin.finance.view"],
          load: () => loadFinanceData(),
        }),
      },
      {
        segment: "ops",
        index: panelPage("panel-ops", {
          route: "ops",
          section: "ops",
          permissions: ["admin.panel.access"],
          load: () => loadQueueJobsData(),
        }),
      },
      {
        segment: "logs",
        index: panelPage("panel-logs", {
          route: "logs",
          section: "logs",
          permissions: ["admin.logs.view"],
          load: () => loadLogsData(),
        }),
        branches: [
          {
            segment: "moderation",
            index: panelPage(null, {
              route: "moderation",
              section: "logs",
              permissions: ["admin.logs.view"],
              load: () => loadModerationPage(),
            }),
          },
          {
            segment: "viewer",
            branches: [
              {
                segment: /^(?<logSlug>[a-zA-Z0-9_-]+)$/,
                param: "logSlug",
                index: panelPage(null, {
                  route: "log-viewer",
                  section: "logs",
                  permissions: ["admin.logs.view"],
                  load: ({ logSlug }) =>
                    loadLogPage(logSlug === "error" ? "logs/error" : logSlug),
                }),
              },
            ],
          },
          {
            segment: "audit",
            branches: [
              {
                segment: /^(?<auditId>[0-9]+)$/,
                param: "auditId",
                index: panelPage(null, {
                  route: "audit-log",
                  section: "logs",
                  permissions: ["admin.logs.view"],
                  load: ({ auditId }) => loadAuditPage(auditId),
                }),
              },
            ],
          },
        ],
      },
      {
        segment: "uploads",
        index: panelPage("panel-uploads", {
          route: "uploads",
          section: "uploads",
          permissions: ["admin.uploads.view"],
          load: () => loadUploadsData(),
        }),
      },
      {
        segment: "config",
        index: panelPage("panel-config", {
          route: "config",
          section: "config",
          permissions: ["admin.settings.modify"],
          load: () => loadConfigData(),
        }),
      },
      {
        segment: "webhook",
        index: panelPage("panel-webhook", {
          route: "webhook",
          section: "webhook",
          permissions: ["admin.settings.modify"],
          load: () => loadWebhookPage(),
        }),
      },
      {
        segment: "config-env",
        index: panelPage("panel-config-env", {
          route: "config-env",
          section: "config-env",
          permissions: ["admin.settings.modify"],
          load: () => loadEnvPage(),
        }),
      },
      {
        segment: "help",
        index: panelPage("panel-help", { route: "help", section: "help" }),
      },
    ],
    index: panelPage("panel-dashboard", {
      route: "dashboard",
      section: "dashboard",
      permissions: ["admin.metrics.view"],
      load: () => loadDashboardData(),
    }),
    fallback: panelPage("panel-dashboard", {
      route: "dashboard",
      section: "dashboard",
      permissions: ["admin.metrics.view"],
      redirect: "/panel",
      load: () => loadDashboardData(),
    }),
  };

  return panelRoutes;
}
