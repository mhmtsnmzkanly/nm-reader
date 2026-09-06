<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NMR Management Console (Lime-CSR)</title>
  <meta name="robots" content="noindex,nofollow">

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  
  <!-- AdminLTE Theme -->
  <link rel="stylesheet" href="/assets/css/adminlte.css">
  <link rel="stylesheet" href="/assets/css/admin-custom.css">

  <style>
    [data-show][hidden] { display: none !important; }
    .lime-badge { font-size: 0.75rem; padding: 0.25em 0.6em; border-radius: 9999px; }
    .lime-toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 1090; }
    .lime-spinner { display: inline-block; width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: lime-spin .75s linear infinite; }
    @keyframes lime-spin { to { transform: rotate(360deg); } }
    .active-nav-link { background-color: rgba(255,255,255,0.15) !important; color: #fff !important; font-weight: 600; }
    .cursor-pointer { cursor: pointer; }
    .modal-backdrop-custom { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center; }
  </style>

  <script>window.__NMR_CONTEXT = <?= $contextJson ?? "{}" ?>;</script>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

  <!-- App Header -->
  <nav class="app-header navbar navbar-expand bg-body border-bottom">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list fs-5"></i></a>
        </li>
        <li class="nav-item d-none d-md-block">
          <a href="/" class="nav-link"><i class="bi bi-box-arrow-up-right me-1"></i> <?= htmlspecialchars((string) (($siteConfig['site_name'] ?? null) ?: 'Main Site'), ENT_QUOTES, 'UTF-8') ?></a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center gap-3">
        <li class="nav-item">
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
            <i class="bi bi-lightning-charge-fill me-1"></i> Lime-CSR v0.2.0 SPA
          </span>
        </li>
        <li class="nav-item dropdown user-menu">
          <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle fs-5"></i>
            <span class="d-none d-md-inline fw-semibold"><?= htmlspecialchars((string) ($adminUsername ?? "Administrator"), ENT_QUOTES, "UTF-8") ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow">
            <li class="user-header text-bg-primary p-3 text-center">
              <i class="bi bi-shield-lock fs-1 mb-2"></i>
              <p class="mb-0 fw-bold"><?= htmlspecialchars((string) ($adminUsername ?? "Administrator"), ENT_QUOTES, "UTF-8") ?></p>
              <small class="opacity-75">System Administrator</small>
            </li>
            <li class="user-footer p-2 d-flex justify-content-between">
              <a href="/panel#config" class="btn btn-sm btn-outline-secondary" data-requires-permission="admin.settings.modify"><i class="bi bi-gear me-1"></i> Ayarlar</a>
              <a href="/logout" class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right me-1"></i> <?= $__t('logout') ?></a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>

  <!-- App Sidebar -->
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand p-3 border-bottom border-secondary">
      <a href="/panel" class="brand-link text-decoration-none d-flex align-items-center gap-2">
        <i class="bi bi-speedometer2 fs-4 text-warning"></i>
        <span class="brand-text fw-bold fs-5"><?= htmlspecialchars((string) (($siteConfig['site_abbreviation'] ?? null) ?: 'NMR'), ENT_QUOTES, 'UTF-8') ?> <span class="badge bg-warning text-dark fs-8">Console</span></span>
      </a>
    </div>
    <div class="sidebar-wrapper py-2">
      <nav>
        <ul class="nav sidebar-menu flex-column gap-1 px-2" id="panel-sidebar-nav">
          <li class="nav-item">
            <a href="#dashboard" class="nav-link rounded" data-route="dashboard">
              <i class="nav-icon bi bi-grid-1x2-fill me-2 text-info"></i>
              <p class="mb-0">Genel Bakış (Dashboard)</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#series" class="nav-link rounded" data-route="series">
              <i class="nav-icon bi bi-journal-richtext me-2 text-primary"></i>
              <p class="mb-0">İçerik & Bölümler</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#users" class="nav-link rounded" data-route="users">
              <i class="nav-icon bi bi-people-fill me-2 text-success"></i>
              <p class="mb-0">Kullanıcılar & Roller</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#blogs" class="nav-link rounded" data-route="blogs">
              <i class="nav-icon bi bi-newspaper me-2 text-warning"></i>
              <p class="mb-0">Blog Moderasyonu</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#comments" class="nav-link rounded" data-route="comments">
              <i class="nav-icon bi bi-chat-square-quote-fill me-2 text-danger"></i>
              <p class="mb-0">Yorum Moderasyonu</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.reports.view">
            <a href="#reports" class="nav-link rounded" data-route="reports">
              <i class="nav-icon bi bi-flag-fill me-2 text-danger"></i>
              <p class="mb-0">Raporlar & Şikâyetler</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.shop.manage">
            <a href="#monetization" class="nav-link rounded" data-route="monetization">
              <i class="nav-icon bi bi-coin me-2 text-warning"></i>
              <p class="mb-0">Para Kazanma & Mağaza</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.finance.view">
            <a href="#finance" class="nav-link rounded" data-route="finance">
              <i class="nav-icon bi bi-receipt-cutoff me-2 text-success"></i>
              <p class="mb-0">Finans & İşlemler</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.health.view">
            <a href="#ops" class="nav-link rounded" data-route="ops">
              <i class="nav-icon bi bi-cpu-fill me-2 text-info"></i>
              <p class="mb-0">Kuyruk & Sistem Bakımı</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.logs.view">
            <a href="#logs" class="nav-link rounded" data-route="logs">
              <i class="nav-icon bi bi-terminal-fill me-2 text-secondary"></i>
              <p class="mb-0">Sistem Logları & Güvenlik</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.uploads.view">
            <a href="#uploads" class="nav-link rounded" data-route="uploads">
              <i class="nav-icon bi bi-images me-2 text-primary"></i>
              <p class="mb-0">Yüklenen Dosyalar</p>
            </a>
          </li>
          <li class="nav-item" data-requires-permission="admin.settings.modify">
            <a href="#config" class="nav-link rounded" data-route="config">
              <i class="nav-icon bi bi-sliders me-2 text-light"></i>
              <p class="mb-0">Site Yapılandırması</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#help" class="nav-link rounded" data-route="help"><i class="nav-icon bi bi-question-circle me-2 text-info"></i><p class="mb-0">Yardım & Kullanım</p></a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- Main Content Target -->
  <main id="panel-app" class="app-main"></main>

  <!-- Toast Container -->
  <div id="lime-toasts" class="lime-toast-container d-flex flex-column gap-2"></div>
</div>

<!-- ========================================================================= -->
<!-- LIME-CSR TEMPLATES                                                        -->
<!-- ========================================================================= -->

<!-- 1. DASHBOARD VIEW -->
<template id="tpl-panel-dashboard">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Genel Bakış & İstatistikler</h3>
        <p class="text-secondary small mb-0">Platformun anlık metrikleri ve sistem durumu</p>
      </div>
      <button class="btn btn-sm btn-outline-primary" data-on-click="refreshDashboard">
        <i class="bi bi-arrow-clockwise me-1"></i> Yenile
      </button>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <!-- KPI Cards -->
      <div class="row g-3 mb-4">
        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm bg-primary text-white p-3 rounded-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="mb-1 text-white-50 small text-uppercase fw-semibold">Toplam Kullanıcı</p>
                <h3 class="mb-0 fw-bold" data-text="overview.total_users">0</h3>
              </div>
              <i class="bi bi-people-fill fs-1 opacity-50"></i>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm bg-success text-white p-3 rounded-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="mb-1 text-white-50 small text-uppercase fw-semibold">Toplam İçerik</p>
                <h3 class="mb-0 fw-bold" data-text="overview.total_contents">0</h3>
              </div>
              <i class="bi bi-journal-bookmark-fill fs-1 opacity-50"></i>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm bg-warning text-dark p-3 rounded-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="mb-1 text-dark-50 small text-uppercase fw-semibold">Toplam Bölüm</p>
                <h3 class="mb-0 fw-bold" data-text="overview.total_chapters">0</h3>
              </div>
              <i class="bi bi-collection-fill fs-1 opacity-50"></i>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm bg-danger text-white p-3 rounded-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="mb-1 text-white-50 small text-uppercase fw-semibold">Onay Bekleyen Blog</p>
                <h3 class="mb-0 fw-bold" data-text="overview.queue_pending">0</h3>
              </div>
              <i class="bi bi-cpu-fill fs-1 opacity-50"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Traffic & Top Content -->
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>En Çok Okunan Seriler (Son 7 Gün)</h5>
            </div>
            <div class="card-body p-0 table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Başlık</th>
                    <th>Tür</th>
                    <th>Görüntülenme</th>
                    <th>Yorum</th>
                  </tr>
                </thead>
                <tbody id="panel-top-contents"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-activity text-info me-2"></i>Hızlı İşlemler</h5>
            </div>
            <div class="card-body px-4 d-flex flex-column gap-2">
              <a href="#series" class="btn btn-outline-primary text-start"><i class="bi bi-plus-circle me-2"></i> Yeni İçerik Ekle</a>
              <a href="#users" class="btn btn-outline-success text-start"><i class="bi bi-shield-check me-2"></i> Rolleri Yönet</a>
              <a href="#ops" class="btn btn-outline-warning text-start" data-requires-permission="admin.jobs.run"><i class="bi bi-play-circle me-2"></i> Kuyruğu Çalıştır</a>
              <a href="#logs" class="btn btn-outline-secondary text-start" data-requires-permission="admin.logs.view"><i class="bi bi-terminal me-2"></i> Denetim Loglarını İncele</a>
              <a href="#config" class="btn btn-outline-dark text-start" data-requires-permission="admin.settings.modify"><i class="bi bi-sliders me-2"></i> Site Ayarlarını Düzenle</a>
            </div>
          </div>
        </div>
      </div>
      <div class="row g-4 mt-1">
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">Ziyaretler</h6></div><div class="card-body"><div class="d-flex justify-content-between"><span>24 saat</span><strong data-text="analytics.visits_daily">0</strong></div><div class="d-flex justify-content-between"><span>7 gün</span><strong data-text="analytics.visits_weekly">0</strong></div><div class="d-flex justify-content-between"><span>30 gün</span><strong data-text="analytics.visits_monthly">0</strong></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">Funnel ve Sağlık</h6></div><div class="card-body"><div class="d-flex justify-content-between"><span>Ana sayfa → içerik</span><strong data-text="analytics.home_to_content">0%</strong></div><div class="d-flex justify-content-between"><span>İçerik → bölüm</span><strong data-text="analytics.content_to_chapter">0%</strong></div><div class="d-flex justify-content-between"><span>5xx oranı</span><strong data-text="analytics.error_rate">0%</strong></div><div class="d-flex justify-content-between"><span>p95 süre</span><strong data-text="analytics.p95">0 ms</strong></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">Arama ve Tutundurma</h6></div><div class="card-body"><div class="d-flex justify-content-between"><span>7 günlük arama</span><strong data-text="analytics.search_total">0</strong></div><div class="d-flex justify-content-between"><span>Sıfır sonuç oranı</span><strong data-text="analytics.zero_result_pct">0%</strong></div><div class="d-flex justify-content-between"><span>D1 tutundurma</span><strong data-text="analytics.d1_retention">0%</strong></div><div class="d-flex justify-content-between"><span>Yeni kullanıcı</span><strong data-text="analytics.new_users">0</strong></div></div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent d-flex justify-content-between"><h6 class="mb-0">Gelir Analitiği (30 gün)</h6><span><strong data-text="analytics.total_coins">0</strong> coin / <strong data-text="analytics.total_unlocks">0</strong> kilit</span></div><div class="card-body p-0 table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Seri</th><th>Kilit</th><th>Coin</th></tr></thead><tbody id="panel-monetization-series"></tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">Sıfır Sonuçlu Aramalar</h6></div><div class="card-body p-0 table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Arama</th><th>Sayı</th><th>Son arama</th></tr></thead><tbody id="panel-zero-searches"></tbody></table></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">En Çok Okunan Türler</h6></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="panel-dashboard-genres"></tbody></table></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">En Çok Okunan Etiketler</h6></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="panel-dashboard-tags"></tbody></table></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">Blog Özeti (30 gün)</h6></div><div class="card-body"><div class="d-flex justify-content-between"><span>Toplam</span><strong data-text="analytics.blog_total">0</strong></div><div class="d-flex justify-content-between"><span>Görünür</span><strong data-text="analytics.blog_visible">0</strong></div><div class="d-flex justify-content-between"><span>Gizli</span><strong data-text="analytics.blog_hidden">0</strong></div><div class="d-flex justify-content-between"><span>Silinmiş</span><strong data-text="analytics.blog_deleted">0</strong></div><div class="d-flex justify-content-between"><span>Yeni / Onaylanan</span><strong><span data-text="analytics.blog_created">0</span> / <span data-text="analytics.blog_approved">0</span></strong></div></div></div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">Kullanıcı İtibarı</h6></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="panel-dashboard-reputation"></tbody></table></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">İçerik Türleri</h6></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="panel-dashboard-types"></tbody></table></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">En Çok Okunan Bölümler</h6></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="panel-dashboard-chapters"></tbody></table></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-transparent"><h6 class="mb-0">En Aktif Blog Yazarları</h6></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="panel-dashboard-blog-authors"></tbody></table></div></div></div>
        <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h6 class="mb-0">Günlük Blog Etkinliği (30 gün)</h6></div><div class="card-body p-0 table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Gün</th><th>Oluşturulan</th><th>Onaylanan</th></tr></thead><tbody id="panel-dashboard-blog-daily"></tbody></table></div></div></div>
      </div>
    </div>
  </div>
</template>

<!-- 2. SERIES & CONTENT VIEW -->
<template id="tpl-panel-series">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">İçerik & Bölüm Yönetimi</h3>
        <p class="text-secondary small mb-0">Manga, Novel, Webtoon serileri ve bölümlerini yönetin</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-on-click="openTaxonomyManager" data-requires-permission="admin.content.create">
          <i class="bi bi-tags me-1"></i> Tür & Etiketler
        </button>
        <button class="btn btn-sm btn-primary" data-on-click="openCreateSeriesModal" data-requires-permission="admin.content.create">
          <i class="bi bi-plus-lg me-1"></i> Yeni Seri Ekle
        </button>
        <button class="btn btn-sm btn-outline-secondary" data-on-click="loadSeries">
          <i class="bi bi-arrow-clockwise me-1"></i> Yenile
        </button>
      </div>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <!-- Search & Filters -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
          <div class="row g-2 align-items-center">
            <div class="col-md-3">
              <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control border-start-0" id="panel-series-search" placeholder="Başlık veya slug ile ara..." data-on-input="filterSeries">
              </div>
            </div>
            <div class="col-md-2"><select class="form-select" id="panel-series-status" data-on-change="filterSeries"><option value="">Eser durumu</option><option value="ongoing">Devam ediyor</option><option value="completed">Tamamlandı</option><option value="hiatus">Ara verdi</option><option value="dropped">Bırakıldı</option></select></div>
            <div class="col-md-2"><select class="form-select" id="panel-series-type" data-on-change="filterSeries"><option value="">Tüm türler</option><option value="manga">Manga</option><option value="manhua">Manhua</option><option value="manhwa">Manhwa</option><option value="webtoon">Webtoon</option><option value="novel">Novel</option><option value="light-novel">Light novel</option><option value="web-novel">Web novel</option></select></div>
            <div class="col-md-3"><select class="form-select" id="panel-series-lifecycle" data-on-change="filterSeries"><option value="">Tüm yayın durumları</option><option value="draft">Taslak</option><option value="scheduled">Zamanlandı</option><option value="published">Yayında</option><option value="archived">Arşivlendi</option></select></div>
            <div class="col-md-2"><select class="form-select" id="panel-series-sort" data-on-change="filterSeries"><option value="newest">En yeni</option><option value="oldest">En eski</option><option value="updated">Son güncellenen</option><option value="title">Başlık A-Z</option></select></div>
          </div>
        </div>
      </div>

      <!-- Series Table -->
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="w-10">ID</th>
                <th>Tür</th>
                <th>Başlık</th>
                <th>Slug</th>
                <th>Durum</th>
                <th class="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody id="panel-series-list"></tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent" id="panel-series-pager"></div>
      </div>
    </div>
  </div>
</template>

<!-- 3. USERS & ROLES VIEW -->
<template id="tpl-panel-users">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Kullanıcılar & Yetkilendirme (RBAC)</h3>
        <p class="text-secondary small mb-0">Kullanıcı hesapları, roller, yasaklamalar ve bakiye yönetimi</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-on-click="openRbacMatrix"><i class="bi bi-shield-lock me-1"></i>Yetki Matrisi</button>
        <button class="btn btn-sm btn-outline-secondary" data-on-click="loadUsers"><i class="bi bi-arrow-clockwise me-1"></i> Yenile</button>
      </div>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
          <div class="row g-2"><div class="col-md-5"><input type="search" class="form-control" id="panel-users-search" placeholder="Kullanıcı adı veya e-posta ile ara..." data-on-input="filterUsers"></div><div class="col-md-3"><select class="form-select" id="panel-users-status" data-on-change="filterUsers"><option value="">Tüm hesaplar</option><option value="active">Aktif</option><option value="banned">Yasaklı</option></select></div><div class="col-md-2"><select class="form-select" id="panel-users-role" data-on-change="filterUsers"><option value="">Tüm roller</option><option value="admin">Admin</option><option value="moderator">Moderatör</option><option value="editor">Editör</option><option value="user">Kullanıcı</option></select></div><div class="col-md-2"><select class="form-select" id="panel-users-sort" data-on-change="filterUsers"><option value="newest">En yeni</option><option value="oldest">En eski</option><option value="username">Kullanıcı A-Z</option></select></div></div>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Kullanıcı</th>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Hesap Durumu</th>
                <th>Kayıt Tarihi</th>
                <th class="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody id="panel-users-list"></tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent" id="panel-users-pager"></div>
      </div>
    </div>
  </div>
</template>

<!-- 4. BLOGS MODERATION VIEW -->
<template id="tpl-panel-blogs">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Blog & Gönderi Moderasyonu</h3>
        <p class="text-secondary small mb-0">Kullanıcı blog gönderilerini onaylayın veya gizleyin</p>
      </div>
      <button class="btn btn-sm btn-outline-secondary" data-on-click="loadBlogs">
        <i class="bi bi-arrow-clockwise me-1"></i> Yenile
      </button>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-2"><div class="col-md-5"><input type="search" class="form-control" id="panel-blogs-search" placeholder="Başlık, slug veya yazar ara..." data-on-input="filterBlogs"></div><div class="col-md-4"><select class="form-select" id="panel-blogs-status" data-on-change="filterBlogs"><option value="">Aktif kayıtlar</option><option value="draft">Taslak</option><option value="pending">Bekleyen</option><option value="published">Yayınlanan</option><option value="rejected">Reddedilen</option><option value="hidden">Gizlenen</option><option value="deleted">Silinen</option></select></div><div class="col-md-3"><select class="form-select" id="panel-blogs-sort" data-on-change="filterBlogs"><option value="newest">En yeni</option><option value="oldest">En eski</option></select></div></div></div></div>
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Başlık</th>
                <th>Yazar</th>
                <th>Durum</th>
                <th>Tarih</th>
                <th class="text-end">İşlem</th>
              </tr>
            </thead>
            <tbody id="panel-blogs-list"></tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent" id="panel-blogs-pager"></div>
      </div>
    </div>
  </div>
</template>

<!-- 5. COMMENTS MODERATION VIEW -->
<template id="tpl-panel-comments">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Yorum Moderasyonu</h3>
        <p class="text-secondary small mb-0">Bölüm ve seri yorumlarını inceleyin</p>
      </div>
      <button class="btn btn-sm btn-outline-secondary" data-on-click="loadComments">
        <i class="bi bi-arrow-clockwise me-1"></i> Yenile
      </button>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-2"><div class="col-md-6"><input type="search" class="form-control" id="panel-comments-search" placeholder="Yorum veya kullanıcı ara..." data-on-input="filterComments"></div><div class="col-md-3"><select class="form-select" id="panel-comments-target" data-on-change="filterComments"><option value="">Tüm hedefler</option><option value="series">Seri</option><option value="chapter">Bölüm</option><option value="blog">Blog</option></select></div><div class="col-md-3"><select class="form-select" id="panel-comments-sort" data-on-change="filterComments"><option value="newest">En yeni</option><option value="oldest">En eski</option></select></div></div></div></div>
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Kullanıcı</th>
                <th>Yorum Metni</th>
                <th>Bağlam</th>
                <th>Beğeni / Dislike</th>
                <th>Tarih</th>
                <th class="text-end">İşlem</th>
              </tr>
            </thead>
            <tbody id="panel-comments-list"></tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent" id="panel-comments-pager"></div>
      </div>
    </div>
  </div>
</template>

<!-- 6. REPORTS VIEW -->
<template id="tpl-panel-reports">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div><h3 class="mb-0 fw-bold fs-4">Raporlar & Şikâyetler</h3><p class="text-secondary small mb-0">İçerik ve topluluk raporlarını inceleyip sonuçlandırın</p></div>
      <button class="btn btn-sm btn-outline-secondary" data-on-click="loadReports"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</button>
    </div>
  </div>
  <div class="app-content p-4"><div class="container-fluid">
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Bekleyen</small><div class="fs-3 fw-bold text-warning" id="panel-report-count-pending">0</div></div></div></div>
      <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">İncelenen</small><div class="fs-3 fw-bold text-info" id="panel-report-count-reviewing">0</div></div></div></div>
      <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Çözülen</small><div class="fs-3 fw-bold text-success" id="panel-report-count-resolved">0</div></div></div></div>
      <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Reddedilen</small><div class="fs-3 fw-bold text-secondary" id="panel-report-count-rejected">0</div></div></div></div>
    </div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-2">
      <div class="col-md-4"><select class="form-select" id="panel-report-status" data-on-change="filterReports"><option value="">Tüm durumlar</option><option value="pending">Bekleyen</option><option value="reviewing">İncelenen</option><option value="resolved">Çözülen</option><option value="rejected">Reddedilen</option></select></div>
      <div class="col-md-4"><select class="form-select" id="panel-report-target" data-on-change="filterReports"><option value="">Tüm hedefler</option><option value="series">Seri</option><option value="chapter">Bölüm</option><option value="blog">Blog</option><option value="comment">Yorum</option></select></div>
    </div></div></div>
    <div class="card border-0 shadow-sm"><div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>#</th><th>Bildiren</th><th>Hedef</th><th>Neden</th><th>Durum</th><th>Tarih</th><th></th></tr></thead><tbody id="panel-reports-list"></tbody></table></div><div class="card-footer bg-transparent d-flex justify-content-between align-items-center"><button class="btn btn-sm btn-outline-secondary" data-on-click="previousReportsPage" id="panel-reports-prev">Önceki</button><span class="small text-secondary" id="panel-reports-page">Sayfa 1</span><button class="btn btn-sm btn-outline-secondary" data-on-click="nextReportsPage" id="panel-reports-next">Sonraki</button></div></div>
  </div></div>
</template>

<!-- 7. MONETIZATION & PACKAGES VIEW -->
<template id="tpl-panel-monetization">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Para Kazanma & Coin Paketleri</h3>
        <p class="text-secondary small mb-0">Mağaza paketleri ve bakiye yapılandırması</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-sm btn-outline-danger" data-on-click="openAdFreeModal"><i class="bi bi-badge-ad me-1"></i>Reklamsız Ürün</button>
        <button class="btn btn-sm btn-outline-secondary" data-on-click="openPricingModal"><i class="bi bi-tags me-1"></i>Fiyatlandırma</button>
        <button class="btn btn-sm btn-primary" data-on-click="openCreatePackageModal"><i class="bi bi-plus-lg me-1"></i> Yeni Paket Ekle</button>
      </div>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Paket Başlığı</th>
                <th>Coin Miktarı</th>
                <th>Bonus Coin</th>
                <th>Fiyat</th>
                <th>Durum</th>
                <th class="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody id="panel-packages-list"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<template id="tpl-panel-finance">
  <div class="app-content-header py-3 px-4 bg-body border-bottom"><div class="container-fluid"><h3 class="mb-0 fw-bold fs-4">Finans & Cüzdan Hareketleri</h3><p class="text-secondary small mb-0">Son 30 gün özeti, işlem defteri ve kontrollü iadeler</p></div></div>
  <div class="app-content p-4"><div class="container-fluid">
    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Dolaşımdaki Coin</small><div class="fs-3 fw-bold" id="panel-finance-circulating">0</div></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">30 Gün Kredi</small><div class="fs-3 fw-bold text-success" id="panel-finance-credited">0</div></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">30 Gün Harcama</small><div class="fs-3 fw-bold text-warning" id="panel-finance-spent">0</div></div></div></div>
      <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">30 Gün İade</small><div class="fs-3 fw-bold text-danger" id="panel-finance-refunded">0</div></div></div></div>
    </div>
    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><div class="row g-2">
      <div class="col-md-6"><input id="panel-finance-search" class="form-control form-control-sm" placeholder="Kullanıcı, açıklama veya referans ara" data-on-input="filterFinance"></div>
      <div class="col-md-3"><select id="panel-finance-type" class="form-select form-select-sm" data-on-change="filterFinance"><option value="">Tüm işlem türleri</option><option value="package_credit">Paket kredisi</option><option value="manual_credit">Manuel kredi</option><option value="manual_debit">Manuel borç</option><option value="chapter_unlock">Bölüm kilidi</option><option value="series_unlock">Seri kilidi</option><option value="feature_unlock">Özellik kilidi</option><option value="refund">İade</option></select></div>
      <div class="col-md-3"><select id="panel-finance-sort" class="form-select form-select-sm" data-on-change="filterFinance"><option value="newest">En yeni</option><option value="oldest">En eski</option><option value="amount_desc">Tutar: büyükten</option></select></div>
    </div></div><div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Kullanıcı</th><th>Tür</th><th>Coin</th><th>Bakiye</th><th>Referans / Açıklama</th><th>Tarih</th><th></th></tr></thead><tbody id="panel-finance-list"></tbody></table></div><div class="card-footer bg-transparent" id="panel-finance-pager"></div></div>
  </div></div>
</template>

<!-- 7. OPERATIONS & QUEUE VIEW -->
<template id="tpl-panel-ops">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Kuyruk & Sistem Operasyonları</h3>
        <p class="text-secondary small mb-0">Arka plan işleri, önbellek ısıtma ve temizlik araçları</p>
      </div>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Veritabanı</small><div class="fw-bold" id="panel-health-database">Kontrol ediliyor</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Depolama</small><div class="fw-bold" id="panel-health-storage">Kontrol ediliyor</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Kuyruk</small><div class="fw-bold" id="panel-health-queue">-</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Son Yedek</small><div class="fw-bold small" id="panel-health-backup">-</div></div></div></div>
      </div>
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent"><div class="row g-2 align-items-center"><div class="col-md-3"><h5 class="mb-0">Kuyruk İşleri</h5></div><div class="col-md-4"><input id="panel-queue-search" class="form-control form-control-sm" placeholder="İş türü veya hata ara" data-on-input="filterQueue"></div><div class="col-md-3"><select id="panel-queue-status" class="form-select form-select-sm" data-on-change="filterQueue"><option value="">Tüm durumlar</option><option value="pending">Bekleyen</option><option value="processing">İşleniyor</option><option value="failed">Başarısız</option><option value="done">Tamamlandı</option><option value="cancelled">İptal</option></select></div><div class="col-md-2 text-end"><button class="btn btn-sm btn-outline-secondary" data-on-click="loadQueueJobs">Yenile</button></div></div></div>
        <div class="card-body p-0 table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>ID</th><th>İş türü</th><th>Durum</th><th>Deneme</th><th>Son hata</th><th>Oluşturma</th><th></th></tr></thead><tbody id="panel-queue-jobs"></tbody></table></div><div class="card-footer bg-transparent" id="panel-queue-pager"></div>
      </div>
      <div class="row g-4">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-cpu-fill text-primary me-2"></i>Kuyruk İşleyici (Queue Worker)</h5>
            </div>
            <div class="card-body px-4">
              <p class="text-secondary small">Bekleyen arka plan işlerini (bildirimler, e-postalar) tek adımda çalıştırır.</p>
              <div class="input-group"><input type="number" min="1" max="100" value="20" class="form-control" id="panel-queue-limit"><button class="btn btn-primary" data-on-click="runQueueWorker" data-requires-permission="admin.jobs.run">
                <i class="bi bi-play-fill me-1"></i> Kuyruğu Çalıştır (Run Once)
              </button></div>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-trash3-fill text-danger me-2"></i>Veri Saklama & Temizlik (Retention)</h5>
            </div>
            <div class="card-body px-4">
              <p class="text-secondary small">Eski oturumları, tamamlanmış kuyruk işlerini ve geçici logları temizler.</p>
              <div class="input-group"><input type="number" min="1" max="3650" value="30" class="form-control" id="panel-cleanup-days"><button class="btn btn-outline-danger" data-on-click="runRetentionCleanup" data-requires-permission="admin.jobs.run">
                <i class="bi bi-broom me-1"></i> Temizliği Başlat
              </button></div>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Önbellek Isıtma (Cache Warmup)</h5>
            </div>
            <div class="card-body px-4">
              <p class="text-secondary small">Popüler seriler ve site yapılandırmasını önbelleğe yükler.</p>
              <button class="btn btn-outline-warning" data-on-click="runCacheWarmup" data-requires-permission="admin.jobs.run">
                <i class="bi bi-fire me-1"></i> Önbelleği Isıt
              </button>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-3" data-requires-permission="admin.jobs.run"><div class="card-header bg-transparent"><h5 class="mb-0">Gelişmiş Bakım Araçları</h5></div><div class="card-body"><div class="row g-2"><div class="col-md-4"><button class="btn btn-outline-info w-100" data-on-click="runMaintenance" data-task="backup">Tam Yedek</button></div><div class="col-md-4"><button class="btn btn-outline-secondary w-100" data-on-click="runMaintenance" data-task="analytics">Analytics Topla</button></div><div class="col-md-4"><button class="btn btn-outline-success w-100" data-on-click="runMaintenance" data-task="api-tests">API Testleri</button></div><div class="col-md-4"><button class="btn btn-outline-dark w-100" data-on-click="runMaintenance" data-task="openapi">OpenAPI Üret</button></div><div class="col-md-4"><button class="btn btn-outline-success w-100" data-on-click="runMaintenance" data-task="seed-data">Varsayılan Veriyi Ekle</button></div></div><pre class="bg-dark text-light rounded p-3 mt-3 mb-0" style="max-height:260px;overflow:auto" id="panel-maintenance-output">İşlem çıktısı burada gösterilecek.</pre></div></div>
        </div>

        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-map-fill text-info me-2"></i>Sitemap & SEO Oluşturucu</h5>
            </div>
            <div class="card-body px-4">
              <p class="text-secondary small">Arama motorları için sitemap.xml dosyasını yeniden üretir.</p>
              <button class="btn btn-outline-info" data-on-click="generateSitemap" data-requires-permission="admin.jobs.run">
                <i class="bi bi-arrow-repeat me-1"></i> Sitemap Oluştur
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<!-- 8. SYSTEM LOGS VIEW -->
<template id="tpl-panel-logs">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Sistem & Denetim Logları</h3>
        <p class="text-secondary small mb-0">Güvenlik denetimleri ve moderasyon hareketleri</p>
      </div>
      <div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-primary" data-on-click="openModerationAction"><i class="bi bi-plus-lg me-1"></i>Moderasyon Kaydı</button><button class="btn btn-sm btn-outline-info" data-on-click="openLogViewer" data-log="login-events">Girişler</button><button class="btn btn-sm btn-outline-danger" data-on-click="openLogViewer" data-log="logs/error">Hatalar</button><button class="btn btn-sm btn-outline-dark" data-on-click="openLogViewer" data-log="moderation-actions">Moderasyon</button><button class="btn btn-sm btn-outline-success" data-on-click="exportLogsCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button><button class="btn btn-sm btn-outline-secondary" data-on-click="toggleLogAutoRefresh" id="panel-log-auto"><i class="bi bi-broadcast me-1"></i>Otomatik: Kapalı</button><button class="btn btn-sm btn-outline-secondary" data-on-click="loadLogs"><i class="bi bi-arrow-clockwise me-1"></i> Yenile</button></div>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-transparent"><div class="row g-2"><div class="col-lg-4"><input id="panel-logs-search" class="form-control form-control-sm" placeholder="Yol, kullanıcı veya user-agent ara" data-on-input="filterLogs"></div><div class="col"><select id="panel-logs-method" class="form-select form-select-sm" data-on-change="filterLogs"><option value="">Metot</option><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select></div><div class="col"><select id="panel-logs-status" class="form-select form-select-sm" data-on-change="filterLogs"><option value="">Durum</option><option value="2xx">2xx</option><option value="4xx">4xx</option><option value="5xx">5xx</option></select></div><div class="col"><select id="panel-logs-sort" class="form-select form-select-sm" data-on-change="filterLogs"><option value="newest">En yeni</option><option value="oldest">En eski</option><option value="slowest">En yavaş</option></select></div><div class="col"><input type="date" id="panel-logs-from" class="form-control form-control-sm" data-on-change="filterLogs"></div><div class="col"><input type="date" id="panel-logs-to" class="form-control form-control-sm" data-on-change="filterLogs"></div></div></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0 font-monospace fs-8">
            <thead class="table-light">
              <tr>
                <th>Metot</th>
                <th>Yol (Path)</th>
                <th>Durum</th>
                <th>Kullanıcı ID</th>
                <th>Süre</th>
                <th>Tarih</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="panel-audit-logs"></tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent" id="panel-logs-pager"></div>
      </div>
    </div>
  </div>
</template>

<!-- 9. UPLOADS VIEW -->
<template id="tpl-panel-uploads">
  <div class="app-content-header py-3 px-4 bg-body border-bottom"><div class="container-fluid d-flex justify-content-between align-items-center"><div><h3 class="mb-0 fw-bold fs-4">Medya Kütüphanesi</h3><p class="text-secondary small mb-0">Önizleme, kullanım kontrolü, optimizasyon ve toplu temizlik</p></div><div class="d-flex gap-2"><button class="btn btn-sm btn-outline-danger" data-on-click="bulkDeleteUploads" data-requires-permission="admin.uploads.delete"><i class="bi bi-trash me-1"></i>Seçilenleri Sil</button><button class="btn btn-sm btn-outline-secondary" data-on-click="loadUploads"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</button></div></div></div>
  <div class="app-content p-4"><div class="container-fluid"><div class="row g-3 mb-3"><div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Dosya</small><div class="fs-4 fw-bold" id="panel-upload-count">0</div></div></div></div><div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Toplam Boyut</small><div class="fs-4 fw-bold" id="panel-upload-size">0 MB</div></div></div></div><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Dağılım</small><div class="fw-semibold" id="panel-upload-types">-</div></div></div></div></div><div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><div class="row g-2"><div class="col-md-6"><input id="panel-uploads-search" class="form-control form-control-sm" placeholder="Dosya, görsel ID veya kullanıcı ara" data-on-input="filterUploads"></div><div class="col-md-3"><select id="panel-uploads-mime" class="form-select form-select-sm" data-on-change="filterUploads"><option value="">Tüm türler</option><option value="image/jpeg">JPEG</option><option value="image/png">PNG</option><option value="image/webp">WebP</option><option value="image/gif">GIF</option></select></div><div class="col-md-3"><label class="form-check mt-1"><input id="panel-uploads-orphans" class="form-check-input" type="checkbox" data-on-change="filterUploads"><span class="form-check-label">Yalnız yetim dosyalar</span></label></div></div></div><div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th><input type="checkbox" data-on-change="toggleAllUploads"></th><th>Önizleme</th><th>Dosya</th><th>Tür</th><th>Boyut</th><th>Kullanım</th><th>Yükleyen</th><th>Tarih</th><th></th></tr></thead><tbody id="panel-uploads-list"></tbody></table></div><div class="card-footer bg-transparent" id="panel-uploads-pager"></div></div></div></div>
</template>

<!-- 10. HELP VIEW -->
<template id="tpl-panel-help">
  <div class="app-content-header py-3 px-4 bg-body border-bottom"><div class="container-fluid"><h3 class="mb-0 fw-bold fs-4">Panel Kullanım Kılavuzu</h3><p class="text-secondary small mb-0">Yönetim işlemleri için kısa başvuru</p></div></div>
  <div class="app-content p-4"><div class="container-fluid"><div class="row g-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h5><i class="bi bi-journal-richtext text-primary me-2"></i>İçerik ve Bölümler</h5><p>Seri ekleyin, metadata ve tür/etiketleri düzenleyin. Bölümler ekranından metin veya görsel bölüm oluşturabilir, ZIP/görsel yükleyebilir ve toplu işlemler uygulayabilirsiniz.</p></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h5><i class="bi bi-people text-success me-2"></i>Kullanıcılar ve Roller</h5><p>Kullanıcı bilgisi, rolü ve yasak durumunu düzenleyin. Yetki matrisi rollerin uygulama yapılandırmasından gelen izinlerini gösterir.</p></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h5><i class="bi bi-coin text-warning me-2"></i>Cüzdan ve Mağaza</h5><p>Bakiye işlemlerini gerekçeli yapın. Paket verme, paket yapılandırma, reklamsız ürün ve içerik fiyatlandırma araçları bu bölümde bulunur.</p></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h5><i class="bi bi-shield-exclamation text-danger me-2"></i>Güvenli İşlemler</h5><p>Silme, bakım, `.env`, yedek ve seed işlemleri kalıcı sonuçlar doğurabilir. Hedefi kontrol edin, işlem çıktısını inceleyin ve özellikle üretim ortamında yedek alın.</p></div></div></div>
    <div class="col-12"><div class="alert alert-info mb-0"><strong>API:</strong> Panel tüm işlemleri <code>/api/v1/admin</code> altındaki kimlik doğrulamalı uç noktalarla yapar. Görmediğiniz veya 403 dönen bir işlem için hesabınızın ilgili RBAC iznine sahip olduğunu doğrulayın.</div></div>
  </div></div></div>
</template>

<!-- 11. SITE CONFIG VIEW -->
<template id="tpl-panel-config">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Site Yapılandırması</h3>
        <p class="text-secondary small mb-0">Genel site ayarları, tema, güvenlik ve e-posta parametreleri</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-outline-info" data-on-click="openWebhooks"><i class="bi bi-broadcast me-1"></i>Webhooklar</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-on-click="openEnvEditor"><i class="bi bi-file-earmark-code me-1"></i>.env</button>
        <button type="button" class="btn btn-sm btn-primary" data-on-click="saveConfig"><i class="bi bi-check2-circle me-1"></i> Ayarları Kaydet</button>
      </div>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <form data-on-submit="saveConfig">
        <!-- 1. GENEL SİTE KİMLİĞİ -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
            <i class="bi bi-globe2 text-primary fs-5 me-2"></i>
            <h5 class="mb-0 fw-semibold">Genel Site Kimliği</h5>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Site Adı</label>
                <input type="text" class="form-control" data-model="config.site_name" maxlength="120" placeholder="NM Reader">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Site Kısaltması (Abbreviation)</label>
                <input type="text" class="form-control" data-model="config.site_abbreviation" maxlength="20" placeholder="NMR">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Varsayılan Dil</label>
                <select class="form-select" data-model="config.default_language">
                  <option value="tr">Türkçe (tr)</option>
                  <option value="en">English (en)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Site Sloganı</label>
                <input type="text" class="form-control" data-model="config.site_slogan" maxlength="255" placeholder="En İyi Çevrimiçi Manga ve Novel Okuyucusu">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Site Adresi (URL)</label>
                <input type="text" class="form-control" data-model="config.site_address" maxlength="255" placeholder="https://example.com">
                <div class="form-text text-secondary">Boş bırakılırsa gelen istek adresi otomatik algılanır.</div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Site Açıklaması (Meta Description)</label>
                <textarea class="form-control" rows="2" data-model="config.site_description" maxlength="1000" placeholder="Arama motorları için meta açıklama metni..."></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Altbilgi (Footer) Metni</label>
                <input type="text" class="form-control" data-model="config.footer_text" maxlength="500" placeholder="© 2026 NM Reader. Tüm hakları saklıdır.">
              </div>
            </div>
          </div>
        </div>

        <!-- 2. GÖRÜNÜM VE MEDYALAR -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
            <i class="bi bi-palette text-success fs-5 me-2"></i>
            <h5 class="mb-0 fw-semibold">Görünüm ve Medyalar</h5>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Varsayılan Tema</label>
                <select class="form-select" data-model="config.default_theme">
                  <option value="default">Default</option>
                  <option value="dark">Dark</option>
                  <option value="royal">Royal</option>
                  <option value="bootstrap">Bootstrap</option>
                  <option value="material">Material</option>
                  <option value="apple">Apple</option>
                  <option value="glass">Glass</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Site Logo URL</label>
                <input type="text" class="form-control" data-model="config.site_logo" maxlength="255" placeholder="/assets/img/logo.svg">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Favicon URL</label>
                <input type="text" class="form-control" data-model="config.favicon_url" maxlength="255" placeholder="/favicon.ico">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Varsayılan Profil Resmi</label>
                <input type="text" class="form-control" data-model="config.default_profile_image" maxlength="255" placeholder="/assets/img/default-profile.png">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Varsayılan İçerik Kapak Resmi</label>
                <input type="text" class="form-control" data-model="config.default_content_cover_image" maxlength="255" placeholder="/assets/img/covers/placeholder.svg">
              </div>
            </div>
          </div>
        </div>

        <!-- 3. GÜVENLİK VE BAKIM MODU -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
            <i class="bi bi-shield-lock text-warning fs-5 me-2"></i>
            <h5 class="mb-0 fw-semibold">Güvenlik ve Bakım Modu</h5>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="form-check form-switch p-3 border rounded-3 bg-body-tertiary">
                  <input class="form-check-input ms-0 me-2" type="checkbox" data-model="config.maintenance_mode" id="panel-maintenance-mode">
                  <label class="form-check-label fw-semibold" for="panel-maintenance-mode">Bakım Modu Aktif</label>
                  <div class="small text-secondary mt-1">Site ziyaretçilere kapatılır, sadece yöneticiler ve izinli IP'ler erişebilir.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch p-3 border rounded-3 bg-body-tertiary">
                  <input class="form-check-input ms-0 me-2" type="checkbox" data-model="config.enforce_https" id="panel-enforce-https">
                  <label class="form-check-label fw-semibold" for="panel-enforce-https">HTTPS Zorunlu</label>
                  <div class="small text-secondary mt-1">HTTP üzerinden gelen istekler güvenli HTTPS protokolüne yönlendirilir.</div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Bakım Modu IP Beyaz Listesi</label>
                <textarea class="form-control font-monospace" rows="3" data-model="config.maintenance_whitelist_text" placeholder="127.0.0.1&#10;::1"></textarea>
                <div class="form-text text-secondary">Her satıra bir IP adresi girin veya virgülle ayırın.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. E-POSTA VE BİLDİRİMLER -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center">
            <i class="bi bi-envelope-at text-info fs-5 me-2"></i>
            <h5 class="mb-0 fw-semibold">E-Posta ve Bildirimler</h5>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-check form-switch p-3 border rounded-3 bg-body-tertiary h-100">
                  <input class="form-check-input ms-0 me-2" type="checkbox" data-model="config.mail_enabled" id="panel-mail-enabled">
                  <label class="form-check-label fw-semibold" for="panel-mail-enabled">E-Posta Sistemi Aktif</label>
                  <div class="small text-secondary mt-1">Sistem genelinde e-posta gönderimini açar veya tamamen devre dışı bırakır.</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch p-3 border rounded-3 bg-body-tertiary h-100">
                  <input class="form-check-input ms-0 me-2" type="checkbox" data-model="config.mail_send_on_register" id="panel-mail-register">
                  <label class="form-check-label fw-semibold" for="panel-mail-register">Kayıtta E-Posta Gönder</label>
                  <div class="small text-secondary mt-1">Yeni kayıt olan kullanıcılara hoş geldin veya doğrulama postası iletir.</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch p-3 border rounded-3 bg-body-tertiary h-100">
                  <input class="form-check-input ms-0 me-2" type="checkbox" data-model="config.email_verification_required" id="panel-email-verify-req">
                  <label class="form-check-label fw-semibold" for="panel-email-verify-req">E-Posta Doğrulama Zorunlu</label>
                  <div class="small text-secondary mt-1">Kullanıcıların oturum açabilmesi için e-posta doğrulaması şart koşulur.</div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Gönderici Adı (From Name)</label>
                <input type="text" class="form-control" data-model="config.mail_from_name" maxlength="120" placeholder="NM Reader">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Gönderici E-Posta Adresi (From Address)</label>
                <input type="email" class="form-control" data-model="config.mail_from_address" maxlength="150" placeholder="noreply@nmreader.com">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Şifre Sıfırlama E-Posta Konusu</label>
                <input type="text" class="form-control" data-model="config.password_reset_subject" maxlength="255" placeholder="Şifre Sıfırlama Talebi">
                <div class="small text-secondary mt-1">Kullanılabilir değişkenler: <code>{{site_name}}</code>, <code>{{username}}</code></div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Şifre Sıfırlama HTML Şablonu</label>
                <textarea class="form-control font-monospace small" rows="5" data-model="config.password_reset_body" placeholder="HTML şablon içeriği..."></textarea>
                <div class="small text-secondary mt-1">Kullanılabilir değişkenler: <code>{{username}}</code>, <code>{{site_name}}</code>, <code>{{action_url}}</code>, <code>{{expires_in}}</code></div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">E-Posta Doğrulama Konusu</label>
                <input type="text" class="form-control" data-model="config.email_verification_subject" maxlength="255" placeholder="E-posta Adresinizi Doğrulayın">
                <div class="small text-secondary mt-1">Kullanılabilir değişkenler: <code>{{site_name}}</code>, <code>{{username}}</code></div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">E-Posta Doğrulama HTML Şablonu</label>
                <textarea class="form-control font-monospace small" rows="5" data-model="config.email_verification_body" placeholder="HTML şablon içeriği..."></textarea>
                <div class="small text-secondary mt-1">Kullanılabilir değişkenler: <code>{{username}}</code>, <code>{{site_name}}</code>, <code>{{action_url}}</code>, <code>{{expires_in}}</code></div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
          <button type="button" class="btn btn-outline-info" data-on-click="openWebhooks"><i class="bi bi-broadcast me-1"></i>Webhooklar</button>
          <button type="button" class="btn btn-outline-secondary" data-on-click="openEnvEditor"><i class="bi bi-file-earmark-code me-1"></i>.env</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Ayarları Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</template>

<!-- ========================================================================= -->
<!-- LIME-CSR SPA JAVASCRIPT CORE                                              -->
<!-- ========================================================================= -->

<script type="module">
  import { createStore, mount, unmount } from 'https://cdn.jsdelivr.net/npm/lime-csr-js@0.2.0/dist/index.min.js';

  // 1. Initial State Store
  const store = createStore({
    currentRoute: 'dashboard',
    loading: false,
    overview: {
      total_users: 0,
      total_contents: 0,
      total_chapters: 0,
      queue_pending: 0
    },
    topContents: [],
    analytics: {
      visits_daily: 0,
      visits_weekly: 0,
      visits_monthly: 0,
      home_to_content: '0%',
      content_to_chapter: '0%',
      error_rate: '0%',
      p95: '0 ms',
      search_total: 0,
      zero_result_pct: '0%',
      d1_retention: '0%',
      new_users: 0,
      total_coins: 0,
      total_unlocks: 0,
      blog_total: 0,
      blog_visible: 0,
      blog_hidden: 0,
      blog_deleted: 0,
      blog_created: 0,
      blog_approved: 0
    },
    dashboardGenres: [],
    dashboardTags: [],
    dashboardReputation: [],
    dashboardTypes: [],
    dashboardChapters: [],
    dashboardBlogAuthors: [],
    dashboardBlogDailyCreated: [],
    dashboardBlogDailyApproved: [],
    monetizationSeries: [],
    zeroResultSearches: [],
    allSeriesList: [],
    seriesList: [],
    seriesMeta: { page: 1, total_pages: 1, total: 0 },
    seriesSearch: '',
    allUsersList: [],
    usersList: [],
    usersMeta: { page: 1, total_pages: 1, total: 0 },
    userSearch: '',
    blogsList: [],
    blogsMeta: { page: 1, total_pages: 1, total: 0 },
    commentsList: [],
    commentsMeta: { page: 1, total_pages: 1, total: 0 },
    reportsList: [],
    reportsMeta: { page: 1, total_pages: 1, total: 0, counts: {} },
    packagesList: [],
    financeList: [],
    financeMeta: { page: 1, total_pages: 1, total: 0 },
    financeSummary: {},
    logsList: [],
    logsMeta: { page: 1, total_pages: 1, total: 0 },
    uploadsList: [],
    uploadsMeta: { page: 1, total_pages: 1, total: 0 },
    uploadsStats: {},
    queueJobsList: [],
    queueMeta: { page: 1, total_pages: 1, total: 0 },
    systemHealth: {},
    config: (() => {
      const initial = window.__NMR_CONTEXT?.site_config || {};
      return {
        site_name: 'NM Reader',
        site_abbreviation: 'NMR',
        site_slogan: 'En İyi Çevrimiçi Manga ve Novel Okuyucusu',
        site_description: 'Read manga, manhwa, webtoon and novels.',
        site_address: '',
        default_language: 'tr',
        footer_text: '© 2026 NM Reader. Tüm hakları saklıdır.',
        default_theme: 'dark',
        site_logo: '/assets/img/logo.svg',
        favicon_url: '/favicon.ico',
        default_profile_image: '/assets/img/default-profile.png',
        default_content_cover_image: '/assets/img/covers/placeholder.svg',
        maintenance_mode: false,
        maintenance_whitelist_text: Array.isArray(initial.maintenance_whitelist_ips) ? initial.maintenance_whitelist_ips.join('\n') : '127.0.0.1\n::1',
        enforce_https: false,
        mail_enabled: true,
        mail_send_on_register: true,
        email_verification_required: false,
        mail_from_name: 'NM Reader',
        mail_from_address: 'noreply@nmreader.com',
        password_reset_subject: 'Şifre Sıfırlama Talebi - {{site_name}}',
        password_reset_body: '',
        email_verification_subject: 'E-posta Adresinizi Doğrulayın - {{site_name}}',
        email_verification_body: '',
        ...initial
      };
    })()
  });

  const csrfToken = window.__NMR_CONTEXT?.auth?.csrf_token || '';
  const grantedPermissions = new Set(window.__NMR_CONTEXT?.auth?.permissions || []);

  function hasPermission(...permissions) {
    return grantedPermissions.has('*') || permissions.some(permission => grantedPermissions.has(permission));
  }

  function applyPermissionVisibility(root = document) {
    root.querySelectorAll('[data-requires-permission]').forEach(element => {
      const required = String(element.dataset.requiresPermission || '').split(',').map(value => value.trim()).filter(Boolean);
      element.hidden = required.length > 0 && !hasPermission(...required);
    });
  }

  // 2. Toast Notification Helper
  function showToast(message, type = 'success') {
    const container = document.getElementById('lime-toasts');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} shadow-lg py-2 px-3 mb-0 rounded-3 d-flex align-items-center gap-2 text-dark`;
    const icon = document.createElement('i');
    icon.className = `bi bi-${type === 'success' ? 'check-circle-fill text-success' : 'exclamation-circle-fill text-danger'}`;
    const text = document.createElement('span');
    text.textContent = String(message ?? '');
    toast.append(icon, text);
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }

  // 3. Authenticated Fetch Helper
  async function api(path, options = {}) {
    const alreadyRetried = options._reauthAttempt === true;
    delete options._reauthAttempt;
    options.headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': csrfToken,
      ...(options.headers || {})
    };
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    const res = await fetch('/api/v1/admin' + path, options);
    if (res.status === 428 && !alreadyRetried && path !== '/auth/reauth') {
      const password = prompt('Bu kritik işlem için yönetici parolanızı yeniden girin:');
      if (!password) throw new Error('Kritik işlem iptal edildi.');
      const verification = await fetch('/api/v1/admin/auth/reauth', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ password })
      });
      if (!verification.ok) {
        const error = await verification.json().catch(() => ({}));
        throw new Error(error?.error?.message || 'Parola doğrulanamadı.');
      }
      return api(path, { ...options, _reauthAttempt: true });
    }
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err?.error?.message || `HTTP ${res.status}`);
    }
    return res.status === 204 ? null : res.json();
  }

  function responseItems(response) {
    if (Array.isArray(response?.data)) return response.data;
    if (Array.isArray(response?.data?.items)) return response.data.items;
    return [];
  }

  function filterByNeedle(items, needle, fields) {
    const query = String(needle || '').trim().toLocaleLowerCase('tr-TR');
    if (!query) return items;

    return items.filter(item => fields.some(field =>
      String(item?.[field] || '').toLocaleLowerCase('tr-TR').includes(query)
    ));
  }

  const reloadTimers = new Map();
  function scheduleReload(key, callback) {
    clearTimeout(reloadTimers.get(key));
    reloadTimers.set(key, setTimeout(callback, 300));
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function safeLocalUrl(value) {
    const url = String(value || '');
    return url.startsWith('/') && !url.startsWith('//') ? escapeHtml(url) : '#';
  }

  function setTableRows(id, rows, colspan) {
    const target = document.getElementById(id);
    if (!target) return;
    target.innerHTML = rows || `<tr><td colspan="${colspan}" class="text-center text-secondary py-4">Kayıt bulunamadı</td></tr>`;
  }

  function responseMeta(response) {
    return {
      page: Number(response?.meta?.page || 1),
      total_pages: Math.max(1, Number(response?.meta?.total_pages || 1)),
      total: Number(response?.meta?.total || 0)
    };
  }

  function renderPager(id, meta, previousHandler, nextHandler) {
    const target = document.getElementById(id);
    if (!target) return;
    const page = Number(meta?.page || 1);
    const totalPages = Math.max(1, Number(meta?.total_pages || 1));
    target.innerHTML = `<div class="d-flex justify-content-between align-items-center"><button class="btn btn-sm btn-outline-secondary" data-on-click="${previousHandler}" ${page <= 1 ? 'disabled' : ''}>Önceki</button><span class="small text-secondary">Sayfa ${page} / ${totalPages} · ${Number(meta?.total || 0)} kayıt</span><button class="btn btn-sm btn-outline-secondary" data-on-click="${nextHandler}" ${page >= totalPages ? 'disabled' : ''}>Sonraki</button></div>`;
  }

  function renderDashboardTables() {
    setTableRows('panel-top-contents', (store.get('topContents') || []).map(item => `<tr><td class="fw-semibold">${escapeHtml(item.title)}</td><td><span class="badge bg-secondary-subtle text-secondary border">${escapeHtml(item.type)}</span></td><td class="fw-bold text-primary">${Number(item.view_count_7d || 0)}</td><td>${Number(item.comment_count_7d || 0)}</td></tr>`).join(''), 4);
    setTableRows('panel-monetization-series', (store.get('monetizationSeries') || []).map(item => `<tr><td>${escapeHtml(item.title)}</td><td>${Number(item.unlock_count || 0)}</td><td>${Number(item.total_coins || 0)}</td></tr>`).join(''), 3);
    setTableRows('panel-zero-searches', (store.get('zeroResultSearches') || []).map(item => `<tr><td>${escapeHtml(item.query)}</td><td>${Number(item.search_count || 0)}</td><td>${escapeHtml(item.last_searched_at)}</td></tr>`).join(''), 3);
    setTableRows('panel-dashboard-genres', (store.get('dashboardGenres') || []).map(item => `<tr><td>${escapeHtml(item.name)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
    setTableRows('panel-dashboard-tags', (store.get('dashboardTags') || []).map(item => `<tr><td>${escapeHtml(item.name)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
    setTableRows('panel-dashboard-reputation', (store.get('dashboardReputation') || []).map(item => `<tr><td>@${escapeHtml(item.username)}</td><td>${Number(item.comment_count || 0)} yorum</td><td class="text-end">${Number(item.score || 0).toFixed(1)}</td></tr>`).join(''), 3);
    setTableRows('panel-dashboard-types', (store.get('dashboardTypes') || []).map(item => `<tr><td class="text-uppercase">${escapeHtml(item.type)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
    setTableRows('panel-dashboard-chapters', (store.get('dashboardChapters') || []).map(item => `<tr><td>${escapeHtml(item.content_title)} #${escapeHtml(item.chapter_number)}</td><td class="text-end">${Number(item.view_total || 0)}</td></tr>`).join(''), 2);
    setTableRows('panel-dashboard-blog-authors', (store.get('dashboardBlogAuthors') || []).map(item => `<tr><td>@${escapeHtml(item.username)}</td><td>${Number(item.approved_total || 0)} onaylı</td><td class="text-end">${Number(item.blog_total || 0)}</td></tr>`).join(''), 3);
    const dailyBlog = new Map();
    (store.get('dashboardBlogDailyCreated') || []).forEach(item => dailyBlog.set(item.day, { day: item.day, created: Number(item.total || 0), approved: 0 }));
    (store.get('dashboardBlogDailyApproved') || []).forEach(item => {
      const current = dailyBlog.get(item.day) || { day: item.day, created: 0, approved: 0 };
      current.approved = Number(item.total || 0);
      dailyBlog.set(item.day, current);
    });
    setTableRows('panel-dashboard-blog-daily', Array.from(dailyBlog.values()).sort((a, b) => String(b.day).localeCompare(String(a.day))).map(item => `<tr><td>${escapeHtml(item.day)}</td><td>${item.created}</td><td>${item.approved}</td></tr>`).join(''), 3);
  }

  function renderSeriesTable() {
    const lifecycleLabel = { draft: 'Taslak', scheduled: 'Zamanlandı', published: 'Yayında', archived: 'Arşivlendi' };
    const lifecycleClass = { draft: 'bg-secondary-subtle text-secondary', scheduled: 'bg-info-subtle text-info', published: 'bg-success-subtle text-success', archived: 'bg-dark-subtle text-dark' };
    setTableRows('panel-series-list', (store.get('seriesList') || []).map(item => {
      const lifecycle = item.lifecycle_status || 'published';
      const lifecycleAction = lifecycle === 'archived' ? 'restore' : (lifecycle === 'published' ? 'archive' : 'publish');
      const lifecycleIcon = lifecycle === 'archived' ? 'arrow-counterclockwise' : (lifecycle === 'published' ? 'archive' : 'send-check');
      return `<tr><td class="text-secondary small">${escapeHtml(item.id)}</td><td><span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase">${escapeHtml(item.type)}</span></td><td class="fw-bold">${escapeHtml(item.title)}</td><td class="text-secondary">${escapeHtml(item.slug)}</td><td><span class="badge bg-light text-dark border me-1">${escapeHtml(item.status)}</span><span class="badge ${lifecycleClass[lifecycle] || 'bg-light text-dark'}">${lifecycleLabel[lifecycle] || lifecycle}</span>${item.scheduled_at ? `<small class="d-block text-secondary mt-1">${escapeHtml(item.scheduled_at)}</small>` : ''}</td><td class="text-end text-nowrap"><button class="btn btn-xs btn-outline-primary me-1" data-on-click="openChaptersDrawer" data-id="${escapeHtml(item.id)}"><i class="bi bi-collection me-1"></i>Bölümler</button><button class="btn btn-xs btn-outline-info me-1" data-on-click="previewSeries" data-id="${escapeHtml(item.id)}" title="Önizle"><i class="bi bi-eye"></i></button><button class="btn btn-xs btn-outline-dark me-1" data-on-click="viewSeriesRevisions" data-id="${escapeHtml(item.id)}" title="Revizyonlar"><i class="bi bi-clock-history"></i></button>${hasPermission('admin.content.update') ? `<button class="btn btn-xs btn-outline-warning me-1" data-on-click="changeSeriesLifecycle" data-id="${escapeHtml(item.id)}" data-action="${lifecycleAction}" title="${lifecycleAction}"><i class="bi bi-${lifecycleIcon}"></i></button><button class="btn btn-xs btn-outline-secondary" data-on-click="openEditSeriesModal" data-id="${escapeHtml(item.id)}"><i class="bi bi-pencil"></i></button>` : ''}</td></tr>`;
    }).join(''), 6);
    renderPager('panel-series-pager', store.get('seriesMeta'), 'previousSeriesPage', 'nextSeriesPage');
  }

  function renderUsersTable() {
    setTableRows('panel-users-list', (store.get('usersList') || []).map(user => `<tr><td class="fw-bold"><i class="bi bi-person me-1 text-secondary"></i>${escapeHtml(user.username)}</td><td class="text-secondary">${escapeHtml(user.email)}</td><td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">${escapeHtml(user.role_names)}</span></td><td><span class="badge ${escapeHtml(user.account_badge)}">${escapeHtml(user.account_status)}</span></td><td class="small text-secondary">${escapeHtml(user.created_at)}</td><td class="text-end">${hasPermission('admin.users.manage') ? `<button class="btn btn-xs btn-outline-primary me-1" data-on-click="openEditUserModal" data-id="${escapeHtml(user.id)}"><i class="bi bi-pencil me-1"></i>Düzenle</button>` : ''}${hasPermission('admin.wallet.view') ? `<button class="btn btn-xs btn-outline-warning" data-on-click="openWalletModal" data-id="${escapeHtml(user.id)}"><i class="bi bi-cash-coin me-1"></i>Bakiye</button>` : ''}</td></tr>`).join(''), 6);
    renderPager('panel-users-pager', store.get('usersMeta'), 'previousUsersPage', 'nextUsersPage');
  }

  function renderBlogsTable() {
    setTableRows('panel-blogs-list', (store.get('blogsList') || []).map(blog => `<tr><td class="fw-bold">${escapeHtml(blog.title)}</td><td>${escapeHtml(blog.username)}</td><td><span class="badge ${escapeHtml(blog.status_badge)}">${escapeHtml(blog.status_label)}</span></td><td class="small text-secondary">${escapeHtml(blog.created_at)}</td><td class="text-end">${hasPermission('admin.blog.hide') && blog.can_approve ? `<button class="btn btn-xs btn-outline-success me-1" data-on-click="approveBlog" data-id="${escapeHtml(blog.id)}"><i class="bi bi-check-circle me-1"></i>Onayla</button>` : ''}${hasPermission('admin.blog.hide') && blog.can_hide ? `<button class="btn btn-xs btn-outline-warning me-1" data-on-click="hideBlog" data-id="${escapeHtml(blog.id)}"><i class="bi bi-eye-slash me-1"></i>Gizle</button>` : ''}${hasPermission('admin.blog.hide') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="deleteBlog" data-id="${escapeHtml(blog.id)}"><i class="bi bi-trash me-1"></i>Sil</button>` : ''}</td></tr>`).join(''), 5);
    renderPager('panel-blogs-pager', store.get('blogsMeta'), 'previousBlogsPage', 'nextBlogsPage');
  }

  function renderCommentsTable() {
    setTableRows('panel-comments-list', (store.get('commentsList') || []).map(comment => `<tr><td class="fw-bold">${escapeHtml(comment.username)}</td><td class="text-wrap">${escapeHtml(comment.body)}</td><td><span class="small text-secondary">${escapeHtml(comment.context_label)}</span></td><td><span class="badge bg-success-subtle text-success">+${Number(comment.upvote_count || 0)}</span> <span class="badge bg-danger-subtle text-danger">-${Number(comment.downvote_count || 0)}</span></td><td class="small text-secondary">${escapeHtml(comment.created_at)}</td><td class="text-end">${hasPermission('admin.comment.delete') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="deleteComment" data-id="${escapeHtml(comment.id)}"><i class="bi bi-trash"></i> Sil</button>` : ''}</td></tr>`).join(''), 6);
    renderPager('panel-comments-pager', store.get('commentsMeta'), 'previousCommentsPage', 'nextCommentsPage');
  }

  function reportStatus(status) {
    return ({
      pending: ['Bekleyen', 'bg-warning-subtle text-warning'],
      reviewing: ['İncelenen', 'bg-info-subtle text-info'],
      resolved: ['Çözüldü', 'bg-success-subtle text-success'],
      rejected: ['Reddedildi', 'bg-secondary-subtle text-secondary']
    })[status] || [status || '-', 'bg-light text-secondary'];
  }

  function renderReportsTable() {
    setTableRows('panel-reports-list', (store.get('reportsList') || []).map(report => {
      const status = reportStatus(report.status);
      const target = report.target_url
        ? `<a href="${safeLocalUrl(report.target_url)}" target="_blank" rel="noopener">${escapeHtml(report.target_title || report.target_id)}</a>`
        : escapeHtml(report.target_title || report.comment_snippet || report.target_id);
      return `<tr><td>${Number(report.id)}</td><td><strong>@${escapeHtml(report.reporter_username)}</strong></td><td><span class="badge bg-light text-dark border me-1">${escapeHtml(report.target_type)}</span>${target}</td><td>${escapeHtml(report.reason)}</td><td><span class="badge ${status[1]}">${status[0]}</span></td><td class="small text-secondary">${escapeHtml(report.created_at)}</td><td class="text-end"><button class="btn btn-xs btn-outline-primary" data-on-click="openReport" data-id="${Number(report.id)}"><i class="bi bi-search me-1"></i>İncele</button></td></tr>`;
    }).join(''), 7);
    const meta = store.get('reportsMeta') || {};
    const counts = meta.counts || {};
    ['pending', 'reviewing', 'resolved', 'rejected'].forEach(status => {
      const element = document.getElementById(`panel-report-count-${status}`);
      if (element) element.textContent = String(Number(counts[status] || 0));
    });
    const page = Number(meta.page || 1);
    const totalPages = Math.max(1, Number(meta.total_pages || 1));
    const label = document.getElementById('panel-reports-page');
    if (label) label.textContent = `Sayfa ${page} / ${totalPages} · ${Number(meta.total || 0)} kayıt`;
    const previous = document.getElementById('panel-reports-prev');
    const next = document.getElementById('panel-reports-next');
    if (previous) previous.disabled = page <= 1;
    if (next) next.disabled = page >= totalPages;
  }

  function renderPackagesTable() {
    setTableRows('panel-packages-list', (store.get('packagesList') || []).map(item => `<tr><td class="fw-bold">${escapeHtml(item.name)}</td><td class="text-warning fw-bold">${Number(item.coin_amount || 0)}</td><td class="text-success">+${Number(item.bonus_coin || 0)}</td><td><span class="badge bg-secondary-subtle text-secondary">${escapeHtml(item.display_price)} ${escapeHtml(item.currency)}</span></td><td><span class="badge ${escapeHtml(item.status_badge)}">${escapeHtml(item.status_label)}</span></td><td class="text-end"><button class="btn btn-xs btn-outline-secondary" data-on-click="openEditPackageModal" data-id="${escapeHtml(item.id)}"><i class="bi bi-pencil"></i> Düzenle</button></td></tr>`).join(''), 6);
  }

  function renderFinanceTable() {
    const eligible = new Set(['chapter_unlock', 'series_unlock', 'feature_unlock', 'manual_debit']);
    setTableRows('panel-finance-list', (store.get('financeList') || []).map(item => {
      const canRefund = Number(item.coin_delta) < 0 && eligible.has(item.type) && Number(item.refunded_coin || 0) < Math.abs(Number(item.coin_delta));
      return `<tr><td>${Number(item.id)}</td><td><strong>@${escapeHtml(item.username)}</strong><small class="d-block text-secondary">${escapeHtml(item.user_id)}</small></td><td><span class="badge bg-light text-dark border">${escapeHtml(item.type)}</span></td><td class="fw-bold ${Number(item.coin_delta) >= 0 ? 'text-success' : 'text-danger'}">${Number(item.coin_delta) > 0 ? '+' : ''}${Number(item.coin_delta)}</td><td>${Number(item.balance_after)}</td><td><small>${escapeHtml(item.reference_type || '-')} / ${escapeHtml(item.reference_id || '-')}</small><span class="d-block text-secondary">${escapeHtml(item.description || '')}</span></td><td class="small text-secondary">${escapeHtml(item.created_at)}</td><td class="text-end">${canRefund && hasPermission('admin.finance.refund') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="refundFinanceTransaction" data-id="${Number(item.id)}"><i class="bi bi-arrow-counterclockwise me-1"></i>İade</button>` : (Number(item.refunded_coin || 0) > 0 ? '<span class="badge bg-danger-subtle text-danger">İade edildi</span>' : '')}</td></tr>`;
    }).join(''), 8);
    const summary = store.get('financeSummary') || {};
    const values = { circulating: summary.circulating_coin, credited: summary.credited_coin, spent: summary.spent_coin, refunded: summary.refunded_coin };
    Object.entries(values).forEach(([key, value]) => { const element = document.getElementById(`panel-finance-${key}`); if (element) element.textContent = Number(value || 0).toLocaleString('tr-TR'); });
    renderPager('panel-finance-pager', store.get('financeMeta'), 'previousFinancePage', 'nextFinancePage');
  }

  function renderQueueTable() {
    setTableRows('panel-queue-jobs', (store.get('queueJobsList') || []).map(job => `<tr><td>${escapeHtml(job.id)}</td><td>${escapeHtml(job.job_type)}</td><td><span class="badge bg-light text-dark border">${escapeHtml(job.status)}</span></td><td>${Number(job.attempts || 0)}</td><td class="text-danger small">${escapeHtml(job.last_error)}</td><td>${escapeHtml(job.created_at)}</td><td class="text-end">${hasPermission('admin.jobs.run') && ['failed', 'cancelled'].includes(job.status) ? `<button class="btn btn-xs btn-outline-primary me-1" data-on-click="retryQueueJob" data-id="${Number(job.id)}">Tekrarla</button>` : ''}${hasPermission('admin.jobs.run') && job.status === 'pending' ? `<button class="btn btn-xs btn-outline-danger" data-on-click="cancelQueueJob" data-id="${Number(job.id)}">İptal</button>` : ''}</td></tr>`).join(''), 7);
    renderPager('panel-queue-pager', store.get('queueMeta'), 'previousQueuePage', 'nextQueuePage');
    const health = store.get('systemHealth') || {};
    const db = document.getElementById('panel-health-database');
    if (db) { db.textContent = health.database?.ok ? `Çalışıyor · ${health.database.version || ''}` : 'Hata'; db.className = `fw-bold ${health.database?.ok ? 'text-success' : 'text-danger'}`; }
    const storage = document.getElementById('panel-health-storage');
    if (storage) { storage.textContent = health.storage?.ok ? `${(Number(health.storage.free_bytes || 0) / 1073741824).toFixed(1)} GB boş` : 'Yazma hatası'; storage.className = `fw-bold ${health.storage?.ok ? 'text-success' : 'text-danger'}`; }
    const queue = document.getElementById('panel-health-queue');
    if (queue) queue.textContent = `${Number(health.queue?.pending || 0)} bekleyen · ${Number(health.queue?.failed || 0)} hata`;
    const backup = document.getElementById('panel-health-backup');
    if (backup) backup.textContent = health.backup ? `${health.backup.file} · ${health.backup.created_at}` : 'Yedek bulunamadı';
  }

  function renderLogsTable() {
    setTableRows('panel-audit-logs', (store.get('logsList') || []).map(log => `<tr><td><span class="badge bg-secondary-subtle text-secondary">${escapeHtml(log.method)}</span></td><td class="fw-bold text-break">${escapeHtml(log.path)}</td><td><span class="badge ${Number(log.status_code) >= 500 ? 'bg-danger-subtle text-danger' : (Number(log.status_code) >= 400 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success')}">${Number(log.status_code || 0)}</span></td><td>${escapeHtml(log.username || log.user_id || '-')}</td><td>${Number(log.duration_ms || 0)}ms</td><td class="text-secondary">${escapeHtml(log.created_at)}</td><td><button class="btn btn-xs btn-outline-secondary" data-on-click="viewAuditLog" data-id="${Number(log.id)}"><i class="bi bi-braces"></i></button></td></tr>`).join(''), 7);
    renderPager('panel-logs-pager', store.get('logsMeta'), 'previousLogsPage', 'nextLogsPage');
  }

  function renderUploadsTable() {
    setTableRows('panel-uploads-list', (store.get('uploadsList') || []).map(item => `<tr><td>${hasPermission('admin.uploads.delete') ? `<input type="checkbox" class="form-check-input" data-upload-select value="${Number(item.id)}">` : ''}</td><td><a href="${safeLocalUrl(item.file_path)}" target="_blank" rel="noopener"><img src="${safeLocalUrl(item.file_path)}" alt="" loading="lazy" class="rounded border object-fit-cover" width="52" height="52"></a></td><td><strong>${escapeHtml(item.original_name)}</strong><small class="d-block text-secondary">${escapeHtml(item.image_id)}</small></td><td>${escapeHtml(item.mime_type)}</td><td>${escapeHtml(item.size_label)}</td><td>${Number(item.is_referenced) === 1 ? '<span class="badge bg-success-subtle text-success">Kullanımda</span>' : '<span class="badge bg-warning-subtle text-warning">Yetim</span>'}</td><td>${escapeHtml(item.username)}</td><td class="small text-secondary">${escapeHtml(item.created_at)}</td><td class="text-end text-nowrap">${hasPermission('admin.uploads.optimize') ? `<button class="btn btn-xs btn-outline-primary me-1" data-on-click="optimizeUpload" data-id="${Number(item.id)}" title="Optimize et"><i class="bi bi-lightning"></i></button>` : ''}${hasPermission('admin.uploads.delete') ? `<button class="btn btn-xs btn-outline-danger" data-on-click="deleteUpload" data-id="${Number(item.id)}"><i class="bi bi-trash"></i></button>` : ''}</td></tr>`).join(''), 9);
    renderPager('panel-uploads-pager', store.get('uploadsMeta'), 'previousUploadsPage', 'nextUploadsPage');
    const stats = store.get('uploadsStats') || {};
    const count = document.getElementById('panel-upload-count'); if (count) count.textContent = Number(stats.total_files || 0).toLocaleString('tr-TR');
    const size = document.getElementById('panel-upload-size'); if (size) size.textContent = `${(Number(stats.total_bytes || 0) / 1048576).toFixed(1)} MB`;
    const types = document.getElementById('panel-upload-types'); if (types) types.textContent = `JPEG ${Number(stats.jpeg_files || 0)} · PNG ${Number(stats.png_files || 0)} · WebP ${Number(stats.webp_files || 0)} · GIF ${Number(stats.gif_files || 0)}`;
  }

  function renderRouteTables(route) {
    if (route === 'dashboard') renderDashboardTables();
    if (route === 'series') renderSeriesTable();
    if (route === 'users') renderUsersTable();
    if (route === 'blogs') renderBlogsTable();
    if (route === 'comments') renderCommentsTable();
    if (route === 'reports') renderReportsTable();
    if (route === 'monetization') renderPackagesTable();
    if (route === 'finance') renderFinanceTable();
    if (route === 'ops') renderQueueTable();
    if (route === 'logs') renderLogsTable();
    if (route === 'uploads') renderUploadsTable();
  }

  function closeDialog() {
    document.getElementById('panel-dialog')?.remove();
  }

  function openDialog(title, body, onSubmit, size = 'modal-lg') {
    closeDialog();
    const overlay = document.createElement('div');
    overlay.id = 'panel-dialog';
    overlay.className = 'modal-backdrop-custom p-3';
    overlay.innerHTML = `
      <div class="modal-dialog ${size} m-0 w-100" role="dialog" aria-modal="true" aria-labelledby="panel-dialog-title">
        <div class="modal-content shadow-lg">
          <form id="panel-dialog-form">
            <div class="modal-header">
              <h5 class="modal-title" id="panel-dialog-title">${escapeHtml(title)}</h5>
              <button type="button" class="btn-close" data-dialog-close aria-label="Kapat"></button>
            </div>
            <div class="modal-body">${body}</div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-dialog-close>Vazgeç</button>
              <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
          </form>
        </div>
      </div>`;
    overlay.addEventListener('click', event => {
      if (event.target === overlay || event.target.closest('[data-dialog-close]')) closeDialog();
    });
    overlay.querySelector('form').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = event.submitter;
      if (submit) submit.disabled = true;
      try {
        await onSubmit(new FormData(event.currentTarget), event.currentTarget);
      } catch (error) {
        showToast(error.message, 'danger');
        if (submit) submit.disabled = false;
      }
    });
    document.body.appendChild(overlay);
    applyPermissionVisibility(overlay);
    overlay.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
    return overlay;
  }

  function taxonomyChoices(items, selectedIds, name) {
    const selected = new Set(String(selectedIds || '').split(',').filter(Boolean));
    return items.map(item => `
      <label class="btn btn-sm ${selected.has(String(item.id)) ? 'btn-primary' : 'btn-outline-secondary'}">
        <input class="visually-hidden" type="checkbox" name="${name}" value="${escapeHtml(item.id)}" ${selected.has(String(item.id)) ? 'checked' : ''}>
        ${escapeHtml(item.name)}
      </label>`).join('') || '<span class="text-secondary small">Kayıt bulunamadı.</span>';
  }

  function contentForm(content = {}, genres = [], tags = []) {
    const checked = value => Number(value) === 1 ? 'checked' : '';
    const dateValue = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
    return `
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Tür</label><select class="form-select" name="type" ${content.id ? 'disabled' : 'required'}>
          ${['novel', 'manga', 'manhwa', 'webtoon', 'light-novel', 'web-novel'].map(type => `<option value="${type}" ${String(content.type || '').replace('_', '-') === type ? 'selected' : ''}>${type}</option>`).join('')}
        </select></div>
        <div class="col-md-4"><label class="form-label">Başlık</label><input class="form-control" name="title" value="${escapeHtml(content.title)}" required></div>
        <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" value="${escapeHtml(content.slug)}" ${content.id ? 'disabled' : ''}></div>
        <div class="col-md-4"><label class="form-label">Durum</label><select class="form-select" name="status">
          <option value="ongoing" ${content.status === 'ongoing' ? 'selected' : ''}>Devam ediyor</option>
          <option value="completed" ${content.status === 'completed' ? 'selected' : ''}>Tamamlandı</option>
          <option value="hiatus" ${content.status === 'hiatus' ? 'selected' : ''}>Ara verdi</option>
          <option value="dropped" ${content.status === 'dropped' ? 'selected' : ''}>Bırakıldı</option>
        </select></div>
        <div class="col-md-4"><label class="form-label">Yayın durumu</label><select class="form-select" name="lifecycle_status" data-lifecycle-select><option value="draft" ${content.lifecycle_status === 'draft' ? 'selected' : ''}>Taslak</option><option value="scheduled" ${content.lifecycle_status === 'scheduled' ? 'selected' : ''}>Zamanlandı</option><option value="published" ${!content.lifecycle_status || content.lifecycle_status === 'published' ? 'selected' : ''}>Yayında</option><option value="archived" ${content.lifecycle_status === 'archived' ? 'selected' : ''}>Arşivlendi</option></select></div>
        <div class="col-md-4"><label class="form-label">Planlanan yayın</label><input type="datetime-local" class="form-control" name="scheduled_at" value="${escapeHtml(dateValue(content.scheduled_at))}"></div>
        <div class="col-md-8"><label class="form-label">Alternatif başlıklar</label><input class="form-control" name="alternative_titles" value="${escapeHtml(content.alternative_titles)}"></div>
        <div class="col-12"><label class="form-label">Açıklama</label><textarea class="form-control" name="description" rows="4">${escapeHtml(content.description)}</textarea></div>
        <div class="col-md-6"><label class="form-label">Kapak görseli yolu</label><input class="form-control" name="cover_image" value="${escapeHtml(content.cover_image)}"></div>
        <div class="col-md-6"><label class="form-label">Kapak yükle</label><input type="file" accept="image/*" class="form-control" name="cover_file"></div>
        <div class="col-md-3"><label class="form-label">Yazar</label><input class="form-control" name="author" value="${escapeHtml(content.author)}"></div>
        <div class="col-md-3"><label class="form-label">Çizer</label><input class="form-control" name="artist" value="${escapeHtml(content.artist)}"></div>
        <div class="col-md-3"><label class="form-label">Ülke</label><input class="form-control" name="country" value="${escapeHtml(content.country)}"></div>
        <div class="col-md-3"><label class="form-label">Yayın yılı</label><input type="number" class="form-control" name="release_year" min="1800" max="<?= date('Y') + 1 ?>" value="${escapeHtml(content.release_year)}"></div>
        <div class="col-md-3 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_adult" value="1" id="dialog-adult" ${checked(content.is_adult)}><label class="form-check-label" for="dialog-adult">Yetişkin içeriği</label></div>
        <div class="col-md-3 form-check"><input class="form-check-input" type="checkbox" name="is_members_only" value="1" id="dialog-members" ${checked(content.is_members_only)}><label class="form-check-label" for="dialog-members">Sadece üyeler</label></div>
        <div class="col-12"><label class="form-label d-block">Türler</label><div class="d-flex flex-wrap gap-2" data-taxonomy-choices>${taxonomyChoices(genres, content.genre_ids, 'genres')}</div></div>
        <div class="col-12"><label class="form-label d-block">Etiketler</label><div class="d-flex flex-wrap gap-2" data-taxonomy-choices>${taxonomyChoices(tags, content.tag_ids, 'tags')}</div></div>
      </div>`;
  }

  function chapterForm(chapter = {}) {
    const pricing = chapter.pricing || {};
    const dateValue = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
    return `
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Bölüm numarası</label><input class="form-control" name="chapter_number" value="${escapeHtml(chapter.chapter_number)}" required></div>
        <div class="col-md-4"><label class="form-label">Tür</label><select class="form-select" name="type" id="dialog-chapter-type"><option value="text" ${chapter.type !== 'image' ? 'selected' : ''}>Metin</option><option value="image" ${chapter.type === 'image' ? 'selected' : ''}>Görsel</option></select></div>
        <div class="col-md-4"><label class="form-label">Başlık</label><input class="form-control" name="title" value="${escapeHtml(chapter.title)}"></div>
        <div class="col-md-4"><label class="form-label">Coin fiyatı</label><input type="number" min="0" class="form-control" name="price_amount" value="${escapeHtml(pricing.base_price ?? chapter.price_amount ?? 0)}"></div>
        <div class="col-md-4"><label class="form-label">Yayın tarihi</label><input type="datetime-local" class="form-control" name="published_at" value="${escapeHtml(dateValue(pricing.published_at ?? chapter.published_at))}"></div>
        <div class="col-md-4"><label class="form-label">Ücretsiz olma tarihi</label><input type="datetime-local" class="form-control" name="is_free_after" value="${escapeHtml(dateValue(pricing.is_free_after ?? chapter.is_free_after))}"></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_members_only" value="1" id="dialog-chapter-members" ${Number(chapter.is_members_only) === 1 ? 'checked' : ''}><label class="form-check-label" for="dialog-chapter-members">Sadece üyeler</label></div>
        <div class="col-12" data-chapter-body><label class="form-label">Metin</label><textarea class="form-control" name="body" rows="8">${escapeHtml(chapter.body)}</textarea></div>
        <div class="col-12" data-chapter-pages><label class="form-label">Görsel yolları (satır başına bir tane)</label><input type="file" accept="image/*,.zip" multiple class="form-control mb-2" name="page_files"><textarea class="form-control" name="pages" rows="8">${escapeHtml((chapter.pages || []).join('\n'))}</textarea></div>
      </div>`;
  }

  async function loadTaxonomies() {
    const [genreResponse, tagResponse] = await Promise.all([api('/genres'), api('/tags')]);
    return { genres: responseItems(genreResponse), tags: responseItems(tagResponse) };
  }

  async function uploadImages(files, type) {
    if (!files?.length) return [];
    const body = new FormData();
    Array.from(files).forEach(file => body.append('images[]', file));
    const response = await api(`/upload-images?type=${encodeURIComponent(type)}`, { method: 'POST', body });
    return response?.data?.paths || [];
  }

  function bindTaxonomyButtons(overlay) {
    overlay.querySelectorAll('[data-taxonomy-choices] label').forEach(label => {
      label.addEventListener('click', () => {
        setTimeout(() => {
          const checked = label.querySelector('input').checked;
          label.classList.toggle('btn-primary', checked);
          label.classList.toggle('btn-outline-secondary', !checked);
        });
      });
    });
  }

  function selectedValues(formData, name) {
    return formData.getAll(name).map(String);
  }

  function chapterPayload(formData, form) {
    const payload = Object.fromEntries(formData.entries());
    delete payload.page_files;
    payload.price_amount = Number(payload.price_amount || 0);
    payload.is_members_only = form.elements.is_members_only?.checked ? 1 : 0;
    if (payload.type === 'image') {
      payload.pages = String(payload.pages || '').split('\n').map(value => value.trim()).filter(Boolean);
      delete payload.body;
    } else {
      delete payload.pages;
    }
    return payload;
  }

  async function openChapterEditor(content, chapterId = null) {
    const chapter = chapterId ? (await api(`/chapters/${chapterId}`))?.data || {} : {};
    const overlay = openDialog(
      chapterId ? `Bölümü Düzenle: ${chapter.chapter_number}` : `${content.title} — Yeni Bölüm`,
      chapterForm(chapter),
      async (formData, form) => {
        const payload = chapterPayload(formData, form);
        await api(chapterId ? `/chapters/${chapterId}` : `/content/${content.id}/chapters`, {
          method: chapterId ? 'PUT' : 'POST',
          body: payload
        });
        closeDialog();
        showToast(chapterId ? 'Bölüm güncellendi' : 'Bölüm oluşturuldu');
        await loadSeriesData();
        await openChaptersDialog(content.id);
      }
    );
    const typeInput = overlay.querySelector('#dialog-chapter-type');
    const syncChapterFields = () => {
      const image = typeInput.value === 'image';
      overlay.querySelector('[data-chapter-body]').hidden = image;
      overlay.querySelector('[data-chapter-pages]').hidden = !image;
    };
    typeInput.addEventListener('change', syncChapterFields);
    overlay.querySelector('[name="page_files"]').addEventListener('change', async event => {
      try {
        const paths = await uploadImages(event.target.files, 'chapters');
        const textarea = overlay.querySelector('[name="pages"]');
        const existing = textarea.value.split('\n').map(value => value.trim()).filter(Boolean);
        textarea.value = [...existing, ...paths].join('\n');
        showToast(`${paths.length} görsel yüklendi`);
      } catch (error) { showToast(error.message, 'danger'); }
      event.target.value = '';
    });
    syncChapterFields();
  }

  async function openSeriesPreview(contentId) {
    const response = await api(`/content/${contentId}/preview`);
    const content = response?.data || {};
    const overlay = openDialog(
      `Önizleme: ${content.title || contentId}`,
      `<div class="row g-4"><div class="col-md-4"><div class="ratio ratio-3x4 bg-body-tertiary rounded overflow-hidden">${content.cover_image ? `<img src="${safeLocalUrl(content.cover_image)}" class="w-100 h-100 object-fit-cover" alt="">` : '<div class="d-flex align-items-center justify-content-center text-secondary">Kapak yok</div>'}</div></div><div class="col-md-8"><div class="d-flex flex-wrap gap-2 mb-3"><span class="badge bg-primary">${escapeHtml(content.type)}</span><span class="badge bg-secondary">${escapeHtml(content.status)}</span><span class="badge bg-info text-dark">${escapeHtml(content.lifecycle_status)}</span></div><h3>${escapeHtml(content.title)}</h3><p class="text-secondary">${escapeHtml(content.alternative_titles)}</p><p>${escapeHtml(content.description || 'Açıklama yok')}</p><dl class="row small"><dt class="col-sm-3">Yazar</dt><dd class="col-sm-9">${escapeHtml(content.author || '-')}</dd><dt class="col-sm-3">Çizer</dt><dd class="col-sm-9">${escapeHtml(content.artist || '-')}</dd><dt class="col-sm-3">Planlanan yayın</dt><dd class="col-sm-9">${escapeHtml(content.scheduled_at || '-')}</dd></dl>${content.lifecycle_status === 'published' ? `<a href="${safeLocalUrl(content.url_path)}" target="_blank" rel="noopener" class="btn btn-outline-primary">Canlı sayfayı aç</a>` : '<div class="alert alert-warning small">Bu içerik halka açık değil; yalnızca yönetici önizlemesindesiniz.</div>'}</div></div>`,
      async () => {},
      'modal-xl'
    );
    overlay.querySelector('button[type="submit"]')?.remove();
  }

  async function openSeriesRevisions(contentId) {
    const response = await api(`/content/${contentId}/revisions?limit=50`);
    const rows = responseItems(response).map(revision => `<tr><td>${escapeHtml(revision.created_at)}</td><td>${escapeHtml(revision.moderator_username || revision.moderator_user_id || '-')}</td><td><span class="badge bg-secondary">${escapeHtml(revision.action)}</span></td><td>${escapeHtml(revision.snapshot?.title || '-')}</td><td>${escapeHtml(revision.snapshot?.status || '-')} / ${escapeHtml(revision.snapshot?.lifecycle_status || 'published')}</td></tr>`).join('');
    const overlay = openDialog('İçerik Revizyon Geçmişi', `<div class="table-responsive" style="max-height:70vh"><table class="table table-sm align-middle"><thead class="table-dark position-sticky top-0"><tr><th>Tarih</th><th>Yönetici</th><th>İşlem</th><th>Başlık</th><th>Durum</th></tr></thead><tbody>${rows || '<tr><td colspan="5" class="text-center text-secondary">Revizyon bulunamadı</td></tr>'}</tbody></table></div>`, async () => {}, 'modal-xl');
    overlay.querySelector('button[type="submit"]')?.remove();
  }

  async function openTaxonomyDialog() {
    const { genres, tags } = await loadTaxonomies();
    const renderRows = items => items.map(item => `<tr data-taxonomy-row="${escapeHtml(item.id)}"><td class="text-secondary">${escapeHtml(item.id)}</td><td><strong>${escapeHtml(item.name)}</strong><small class="d-block text-secondary">${escapeHtml(item.slug || '')}</small></td><td>${Number(item.usage_count || 0)}</td><td style="width:90px"><input type="number" min="0" class="form-control form-control-sm" data-taxonomy-order value="${Number(item.sort_order || 0)}"></td><td class="text-end text-nowrap"><button type="button" class="btn btn-xs btn-outline-secondary me-1" data-edit-taxonomy="${escapeHtml(item.id)}" data-name="${escapeHtml(item.name)}" title="Düzenle"><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-xs btn-outline-primary me-1" data-merge-taxonomy="${escapeHtml(item.id)}" title="Birleştir"><i class="bi bi-intersect"></i></button><button type="button" class="btn btn-xs btn-outline-danger" data-delete-taxonomy="${escapeHtml(item.id)}" data-name="${escapeHtml(item.name)}" data-usage="${Number(item.usage_count || 0)}" title="Sil"><i class="bi bi-trash"></i></button></td></tr>`).join('') || '<tr><td colspan="5" class="text-secondary">Kayıt bulunamadı</td></tr>';
    const overlay = openDialog(
      'Tür ve Etiket Yönetimi',
      `<div class="row g-4">
        <div class="col-md-6"><div class="d-flex justify-content-between mb-2"><h6>Türler</h6><button type="button" class="btn btn-sm btn-primary" data-create-taxonomy="genre">Yeni Tür</button></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Ad / Slug</th><th>Kullanım</th><th>Sıra</th><th></th></tr></thead><tbody>${renderRows(genres)}</tbody></table></div></div>
        <div class="col-md-6"><div class="d-flex justify-content-between mb-2"><h6>Etiketler</h6><button type="button" class="btn btn-sm btn-primary" data-create-taxonomy="tag">Yeni Etiket</button></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Ad / Slug</th><th>Kullanım</th><th>Sıra</th><th></th></tr></thead><tbody>${renderRows(tags)}</tbody></table></div></div>
      </div>`,
      async () => {
        const items = Array.from(overlay.querySelectorAll('[data-taxonomy-row]')).map(row => ({ id: Number(row.dataset.taxonomyRow), sort_order: Number(row.querySelector('[data-taxonomy-order]').value || 0) }));
        await api('/taxonomies/order', { method: 'PUT', body: { items } });
        showToast('Taksonomi sırası kaydedildi');
        await openTaxonomyDialog();
      },
      'modal-xl'
    );
    const submit = overlay.querySelector('button[type="submit"]');
    if (submit) submit.textContent = 'Sıralamayı Kaydet';
    overlay.addEventListener('click', async event => {
      try {
        const createButton = event.target.closest('[data-create-taxonomy]');
        if (createButton) {
          const kind = createButton.dataset.createTaxonomy;
          const name = prompt(kind === 'genre' ? 'Yeni tür adı:' : 'Yeni etiket adı:');
          if (!name?.trim()) return;
          await api(kind === 'genre' ? '/series_genres' : '/series_tags', { method: 'POST', body: { name: name.trim() } });
          showToast(kind === 'genre' ? 'Tür oluşturuldu' : 'Etiket oluşturuldu');
          await openTaxonomyDialog();
          return;
        }
        const editButton = event.target.closest('[data-edit-taxonomy]');
        if (editButton) {
          const name = prompt('Yeni ad:', editButton.dataset.name || '');
          if (!name?.trim() || name.trim() === editButton.dataset.name) return;
          await api(`/taxonomies/${editButton.dataset.editTaxonomy}`, { method: 'PUT', body: { name: name.trim() } });
          showToast('Taksonomi güncellendi');
          await openTaxonomyDialog();
          return;
        }
        const mergeButton = event.target.closest('[data-merge-taxonomy]');
        if (mergeButton) {
          const targetId = Number(prompt('Bu kaydın birleştirileceği hedef taksonomi ID:'));
          if (!targetId) return;
          await api('/taxonomies/merge', { method: 'POST', body: { source_id: Number(mergeButton.dataset.mergeTaxonomy), target_id: targetId } });
          showToast('Taksonomiler birleştirildi');
          await openTaxonomyDialog();
          return;
        }
        const deleteButton = event.target.closest('[data-delete-taxonomy]');
        if (deleteButton) {
          if (Number(deleteButton.dataset.usage || 0) > 0) throw new Error('Kullanılan bir kayıt silinemez; önce başka bir kayda birleştirin.');
          if (!confirm(`“${deleteButton.dataset.name}” silinsin mi?`)) return;
          await api(`/taxonomies/${deleteButton.dataset.deleteTaxonomy}`, { method: 'DELETE' });
          showToast('Taksonomi silindi');
          await openTaxonomyDialog();
        }
      } catch (error) { showToast(error.message, 'danger'); }
    });
  }

  async function openTeamDialog(content) {
    const response = await api(`/series/${content.id}/team`);
    const members = responseItems(response);
    const rows = members.map(member => `<tr><td><strong>@${escapeHtml(member.username)}</strong><br><small>${escapeHtml(member.user_id)}</small></td><td>${escapeHtml(member.role)}</td><td>${escapeHtml(member.created_at || '-')}</td><td class="text-end"><button type="button" class="btn btn-xs btn-outline-danger" data-remove-team="${member.id}"><i class="bi bi-trash"></i></button></td></tr>`).join('') || '<tr><td colspan="4" class="text-center text-secondary">Henüz ekip üyesi yok</td></tr>';
    const overlay = openDialog(
      `${content.title} — Ekip Yönetimi`,
      `<div class="row g-2 mb-4"><div class="col-md-7"><label class="form-label">Kullanıcı ID</label><input class="form-control" name="user_id" pattern="[a-z0-9]{8}" required></div><div class="col-md-5"><label class="form-label">Görev</label><select class="form-select" name="role"><option value="translator">Çevirmen</option><option value="proofreader">Kontrolör</option><option value="cleaner">Cleaner</option><option value="typesetter">Dizgici</option><option value="uploader">Uploader</option><option value="lead">Proje lideri</option></select></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Kullanıcı</th><th>Görev</th><th>Tarih</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`,
      async formData => {
        await api(`/series/${content.id}/team`, { method: 'POST', body: Object.fromEntries(formData.entries()) });
        showToast('Ekip üyesi atandı');
        await openTeamDialog(content);
      }
    );
    overlay.addEventListener('click', async event => {
      const button = event.target.closest('[data-remove-team]');
      if (!button || !confirm('Ekip üyesini çıkarmak istediğinize emin misiniz?')) return;
      try {
        await api(`/series/team/${button.dataset.removeTeam}`, { method: 'DELETE' });
        showToast('Ekip üyesi çıkarıldı');
        await openTeamDialog(content);
      } catch (error) { showToast(error.message, 'danger'); }
    });
  }

  async function openUserEditor(userId) {
    const user = (store.get('allUsersList') || []).find(item => String(item.id) === String(userId));
    if (!user) throw new Error('Kullanıcı bulunamadı');
    const rolesResponse = await api('/rbac/roles');
    const roles = responseItems(rolesResponse);
    const currentRole = String(user.role_names || 'user').split(',')[0].trim();
    const overlay = openDialog(
      `Kullanıcıyı Düzenle: ${user.username}`,
      `<div class="mb-3"><label class="form-label">Kullanıcı adı</label><input class="form-control" value="${escapeHtml(user.username)}" disabled></div>
       <div class="mb-3"><label class="form-label">E-posta</label><input type="email" class="form-control" name="email" value="${escapeHtml(user.email)}" required></div>
       <div class="mb-3"><label class="form-label">Biyografi</label><textarea class="form-control" name="bio" maxlength="1000" rows="4">${escapeHtml(user.bio)}</textarea></div>
       <div class="mb-3"><label class="form-label">Rol</label><select class="form-select" name="role">${roles.map(role => `<option value="${escapeHtml(role.slug)}" ${role.slug === currentRole ? 'selected' : ''}>${escapeHtml(role.name || role.slug)}</option>`).join('')}</select></div>
       <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_banned" value="1" id="dialog-user-banned" ${Number(user.is_banned) === 1 ? 'checked' : ''}><label class="form-check-label" for="dialog-user-banned">Kullanıcıyı yasakla</label></div>`,
      async (formData, form) => {
        const payload = Object.fromEntries(formData.entries());
        payload.is_banned = form.elements.is_banned.checked;
        await api(`/users/${user.id}`, { method: 'PUT', body: payload });
        closeDialog();
        showToast('Kullanıcı güncellendi');
        loadUsersData();
      }
    );
  }

  async function openRbacDialog() {
    const response = await api('/rbac/matrix');
    const roles = response?.data?.roles || [];
    const permissionGroups = response?.data?.permissions || {};
    const heading = roles.map(role => `<th class="text-center">${escapeHtml(role.name || role.slug)}</th>`).join('');
    let rows = '';
    Object.entries(permissionGroups).forEach(([group, permissions]) => {
      rows += `<tr class="table-secondary"><td colspan="${roles.length + 1}"><strong>${escapeHtml(group)}</strong></td></tr>`;
      Object.entries(permissions).forEach(([code, label]) => {
        rows += `<tr><td><code>${escapeHtml(code)}</code><br><small class="text-secondary">${escapeHtml(label)}</small></td>${roles.map(role => {
          const rolePermissions = String(role.permissions || '').split(',');
          const granted = role.slug === 'superadmin' || rolePermissions.includes('*') || rolePermissions.includes(code);
          const canChange = granted ? hasPermission('admin.permissions.revoke') : hasPermission('admin.permissions.grant');
          const locked = role.slug === 'admin' && code === 'admin.panel.access';
          return `<td class="text-center">${canChange && !locked ? `<button type="button" class="btn btn-sm border-0" data-toggle-permission data-role="${escapeHtml(role.slug)}" data-permission="${escapeHtml(code)}" data-granted="${granted ? '1' : '0'}" title="${granted ? 'İzni kaldır' : 'İzni ver'}"><i class="bi ${granted ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary'}"></i></button>` : `<i class="bi ${granted ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-secondary opacity-50'}"></i>`}</td>`;
        }).join('')}</tr>`;
      });
    });
    const overlay = openDialog(
      'Yetki ve Rol Matrisi',
      `<p class="text-secondary small">Temel rol izinleri uygulama yapılandırmasından, panelde yaptığınız ekleme ve kaldırmalar veritabanındaki RBAC geçersiz kılmalarından okunur. Kullanıcı rolünü kullanıcı düzenleme ekranından değiştirebilirsiniz.</p><div class="table-responsive" style="max-height:65vh"><table class="table table-sm table-bordered align-middle"><thead class="table-dark position-sticky top-0"><tr><th>İzin</th>${heading}</tr></thead><tbody>${rows}</tbody></table></div>`,
      async () => {},
      'modal-xl'
    );
    overlay.querySelector('button[type="submit"]')?.remove();
    overlay.addEventListener('click', async event => {
      const button = event.target.closest('[data-toggle-permission]');
      if (!button) return;
      const granted = button.dataset.granted === '1';
      if (!confirm(`${button.dataset.permission} izni ${button.dataset.role} rolü için ${granted ? 'kaldırılsın' : 'verilsin'} mi?`)) return;
      try {
        await api(granted ? '/rbac/permissions' : '/rbac/permissions/assign', {
          method: granted ? 'DELETE' : 'POST',
          body: { role: button.dataset.role, permission: button.dataset.permission }
        });
        showToast(granted ? 'İzin kaldırıldı' : 'İzin verildi');
        await openRbacDialog();
      } catch (error) { showToast(error.message, 'danger'); }
    });
  }

  function packageForm(packageItem = {}) {
    return `<div class="row g-3">
      <div class="col-md-6"><label class="form-label">Paket adı</label><input class="form-control" name="name" value="${escapeHtml(packageItem.name)}" required></div>
      <div class="col-md-3"><label class="form-label">Coin</label><input type="number" min="1" class="form-control" name="coin_amount" value="${escapeHtml(packageItem.coin_amount || '')}" required></div>
      <div class="col-md-3"><label class="form-label">Bonus coin</label><input type="number" min="0" class="form-control" name="bonus_coin" value="${escapeHtml(packageItem.bonus_coin || 0)}"></div>
      <div class="col-md-4"><label class="form-label">Fiyat</label><input type="number" min="0" step="0.01" class="form-control" name="display_price" value="${escapeHtml(packageItem.display_price || '0.00')}"></div>
      <div class="col-md-4"><label class="form-label">Para birimi</label><input class="form-control" name="currency" maxlength="3" value="${escapeHtml(packageItem.currency || 'TRY')}"></div>
      <div class="col-md-2"><label class="form-label">Sıra</label><input type="number" class="form-control" name="sort_order" value="${escapeHtml(packageItem.sort_order || 0)}"></div>
      <div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="is_active"><option value="1" ${Number(packageItem.is_active ?? 1) === 1 ? 'selected' : ''}>Aktif</option><option value="0" ${Number(packageItem.is_active) === 0 ? 'selected' : ''}>Pasif</option></select></div>
    </div>`;
  }

  function openPackageEditor(packageItem = null) {
    openDialog(packageItem ? 'Paketi Düzenle' : 'Yeni Coin Paketi', packageForm(packageItem || {}), async formData => {
      const payload = Object.fromEntries(formData.entries());
      payload.coin_amount = Number(payload.coin_amount);
      payload.bonus_coin = Number(payload.bonus_coin || 0);
      payload.sort_order = Number(payload.sort_order || 0);
      payload.is_active = payload.is_active === '1';
      await api(packageItem ? `/shop/packages/${packageItem.id}` : '/shop/packages', { method: packageItem ? 'PUT' : 'POST', body: payload });
      closeDialog();
      showToast(packageItem ? 'Paket güncellendi' : 'Paket oluşturuldu');
      loadPackagesData();
    });
  }

  async function openWalletDialog(userId) {
    const canManageWallet = hasPermission('admin.wallet.manage');
    const canLoadPackages = canManageWallet && hasPermission('admin.shop.manage');
    const [walletResponse, transactionResponse, packageResponse] = await Promise.all([
      api(`/wallets/${userId}`),
      api(`/wallets/${userId}/transactions?per_page=50`),
      canLoadPackages ? api('/shop/packages?per_page=100') : Promise.resolve(null)
    ]);
    const wallet = walletResponse?.data || {};
    const transactions = responseItems(transactionResponse);
    const packages = responseItems(packageResponse);
    const rows = transactions.map(item => `<tr><td>${escapeHtml(item.type)}</td><td class="${Number(item.coin_delta) >= 0 ? 'text-success' : 'text-danger'}">${Number(item.coin_delta)}</td><td>${Number(item.balance_after || 0)}</td><td>${escapeHtml([item.reference_type, item.reference_id].filter(Boolean).join(':') || '-')}</td><td>${escapeHtml(item.created_at)}</td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-secondary">İşlem bulunamadı</td></tr>';
    const overlay = openDialog(
      `Cüzdan: ${userId}`,
      `<div class="row g-3 mb-4"><div class="col-md-4"><div class="border rounded p-3"><small class="text-secondary">Bakiye</small><div class="fs-4 fw-bold text-warning">${Number(wallet.balance_coin || 0)} coin</div></div></div><div class="col-md-4"><div class="border rounded p-3"><small class="text-secondary">Toplam satın alınan</small><div class="fs-4 fw-bold">${Number(wallet.total_coin_purchased || 0)}</div></div></div><div class="col-md-4"><div class="border rounded p-3"><small class="text-secondary">Toplam harcanan</small><div class="fs-4 fw-bold">${Number(wallet.total_coin_spent || 0)}</div></div></div></div>
       <div data-requires-permission="admin.wallet.manage"><h6>Manuel bakiye işlemi</h6><div class="row g-2 mb-4"><div class="col-md-3"><select class="form-select" name="wallet_action"><option value="credit">Coin ekle</option><option value="debit">Coin düş</option></select></div><div class="col-md-3"><input type="number" min="1" class="form-control" name="amount" value="10" required></div><div class="col-md-6"><input class="form-control" name="reason" placeholder="İşlem nedeni" required></div></div></div>
       ${canLoadPackages ? `<div class="border rounded p-3 mb-4"><h6>Paket tanımla</h6><div class="row g-2"><div class="col-md-4"><select class="form-select" name="package_id">${packages.map(item => `<option value="${item.id}">${escapeHtml(item.name)} (${Number(item.total_coin || 0)} coin)</option>`).join('')}</select></div><div class="col-md-3"><input class="form-control" name="cash_amount" placeholder="Nakit tutarı"></div><div class="col-md-3"><input class="form-control" name="grant_reason" placeholder="Neden"></div><div class="col-md-2 d-grid"><button type="button" class="btn btn-info" data-grant-package>Paketi ver</button></div></div></div>` : ''}
       <h6>Son işlemler</h6><div class="table-responsive" style="max-height:280px"><table class="table table-sm"><thead><tr><th>Tür</th><th>Değişim</th><th>Son bakiye</th><th>Referans</th><th>Tarih</th></tr></thead><tbody>${rows}</tbody></table></div>`,
      async formData => {
        const action = String(formData.get('wallet_action')) === 'debit' ? 'debit' : 'credit';
        await api(`/wallets/${userId}/${action}`, { method: 'POST', body: { amount: Number(formData.get('amount')), reason: String(formData.get('reason') || '') } });
        showToast('Cüzdan güncellendi');
        await openWalletDialog(userId);
      },
      'modal-xl'
    );
    if (!canManageWallet) overlay.querySelector('button[type="submit"]')?.remove();
    overlay.addEventListener('click', async event => {
      if (!event.target.closest('[data-grant-package]')) return;
      const form = overlay.querySelector('form');
      try {
        await api(`/wallets/${userId}/grant-package`, { method: 'POST', body: { package_id: Number(form.elements.package_id.value), cash_amount: form.elements.cash_amount.value, reason: form.elements.grant_reason.value } });
        showToast('Paket kullanıcıya tanımlandı');
        await openWalletDialog(userId);
      } catch (error) { showToast(error.message, 'danger'); }
    });
  }

  async function openAdFreeDialog() {
    const response = await api('/features');
    const item = responseItems(response).find(feature => feature.feature_key === 'ad_free') || {};
    openDialog('Reklamsız Ürün Ayarı', `<div class="row g-3"><div class="col-md-6"><label class="form-label">Ürün adı</label><input class="form-control" name="name" value="${escapeHtml(item.name)}" required></div><div class="col-md-2"><label class="form-label">Coin fiyatı</label><input type="number" min="0" class="form-control" name="coin_price" value="${Number(item.coin_price || 0)}"></div><div class="col-md-2"><label class="form-label">Süre (gün)</label><input type="number" min="1" class="form-control" name="duration_days" value="${Number(item.duration_days || 30)}"></div><div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="is_active"><option value="1" ${Number(item.is_active ?? 1) === 1 ? 'selected' : ''}>Aktif</option><option value="0" ${Number(item.is_active) === 0 ? 'selected' : ''}>Pasif</option></select></div></div>`, async formData => {
      const payload = Object.fromEntries(formData.entries());
      payload.coin_price = Number(payload.coin_price || 0);
      payload.duration_days = Number(payload.duration_days || 30);
      payload.is_active = payload.is_active === '1';
      await api('/features/ad-free', { method: 'PUT', body: payload });
      closeDialog();
      showToast('Reklamsız ürün ayarı kaydedildi');
    });
  }

  function openPricingDialog() {
    openDialog('Seri / Bölüm Fiyatlandırması', `<div class="row g-3"><div class="col-md-4"><label class="form-label">Hedef</label><select class="form-select" name="target_type"><option value="series">Seri</option><option value="chapters">Bölüm</option></select></div><div class="col-md-4"><label class="form-label">Hedef ID</label><input class="form-control" name="target_id" pattern="[a-z0-9]{6}" required></div><div class="col-md-2"><label class="form-label">Coin fiyatı</label><input type="number" min="0" class="form-control" name="price_coin" value="0"></div><div class="col-md-2"><label class="form-label">Durum</label><select class="form-select" name="is_active"><option value="1">Aktif</option><option value="0">Pasif</option></select></div></div>`, async formData => {
      const targetType = String(formData.get('target_type'));
      const targetId = String(formData.get('target_id'));
      await api(`/${targetType}/${targetId}/pricing`, { method: 'PUT', body: { price_coin: Number(formData.get('price_coin') || 0), is_active: formData.get('is_active') === '1' } });
      closeDialog();
      showToast('Fiyatlandırma kaydedildi');
    });
  }

  async function openWebhooksDialog() {
    const response = await api('/webhooks');
    const items = responseItems(response);
    const rows = items.map(item => `<tr><td>${escapeHtml(item.platform)}</td><td>${escapeHtml(item.event)}</td><td class="text-break">${escapeHtml(item.webhook_url)}</td><td>${Number(item.is_active) === 1 ? 'Aktif' : 'Pasif'}</td><td class="text-end"><button type="button" class="btn btn-xs btn-outline-info" data-test-webhook="${item.id}">Test</button> <button type="button" class="btn btn-xs btn-outline-danger" data-delete-webhook="${item.id}">Sil</button></td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-secondary">Webhook bulunamadı</td></tr>';
    const overlay = openDialog('Webhook Yönetimi', `<div class="row g-2 mb-4"><div class="col-md-3"><select class="form-select" name="platform"><option value="discord">Discord</option><option value="telegram">Telegram</option><option value="custom">Özel HTTP</option></select></div><div class="col-md-3"><select class="form-select" name="event"><option value="chapter_published">Bölüm yayınlandı</option><option value="blog_approved">Blog onaylandı</option><option value="series_created">Seri oluşturuldu</option></select></div><div class="col-md-6"><input type="url" class="form-control" name="webhook_url" placeholder="https://..." required></div></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Platform</th><th>Olay</th><th>URL</th><th>Durum</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`, async formData => {
      await api('/webhooks', { method: 'POST', body: Object.fromEntries(formData.entries()) });
      showToast('Webhook oluşturuldu');
      await openWebhooksDialog();
    }, 'modal-xl');
    overlay.addEventListener('click', async event => {
      const testButton = event.target.closest('[data-test-webhook]');
      const deleteButton = event.target.closest('[data-delete-webhook]');
      try {
        if (testButton) {
          const result = await api(`/webhooks/${testButton.dataset.testWebhook}/test`, { method: 'POST' });
          showToast(result?.data?.success === false ? 'Webhook testi başarısız' : 'Webhook testi tamamlandı', result?.data?.success === false ? 'danger' : 'success');
        }
        if (deleteButton && confirm('Webhook silinsin mi?')) {
          await api(`/webhooks/${deleteButton.dataset.deleteWebhook}`, { method: 'DELETE' });
          showToast('Webhook silindi');
          await openWebhooksDialog();
        }
      } catch (error) { showToast(error.message, 'danger'); }
    });
  }

  async function openEnvDialog() {
    const response = await api('/maintenance/env');
    const values = response?.data || {};
    const textValue = Object.entries(values).map(([key, value]) => `${key}=${value}`).join('\n');
    openDialog('.env Değişkenleri', `<div class="alert alert-warning small">Bu alan yalnızca root yönetici içindir. Kaydetme sırasında mevcut değerler girilen değerlerle birleştirilir ve yedek alınır.</div><textarea class="form-control font-monospace" name="env_text" rows="22">${escapeHtml(textValue)}</textarea>`, async formData => {
      const payload = {};
      String(formData.get('env_text') || '').split('\n').forEach(line => {
        const separator = line.indexOf('=');
        if (separator <= 0 || line.trim().startsWith('#')) return;
        payload[line.slice(0, separator).trim()] = line.slice(separator + 1).trim();
      });
      await api('/maintenance/env', { method: 'POST', body: payload });
      closeDialog();
      showToast('.env kaydedildi');
    }, 'modal-xl');
  }

  async function openLogDialog(path) {
    const response = await api(`/${path}?per_page=100`);
    const items = responseItems(response);
    const columns = items.length ? Object.keys(items[0]).slice(0, 8) : [];
    const heading = columns.map(column => `<th>${escapeHtml(column)}</th>`).join('');
    const rows = items.map(item => `<tr>${columns.map(column => `<td class="text-break">${escapeHtml(typeof item[column] === 'object' ? JSON.stringify(item[column]) : item[column])}</td>`).join('')}</tr>`).join('') || `<tr><td class="text-center text-secondary">Kayıt bulunamadı</td></tr>`;
    const overlay = openDialog('Log Görüntüleyici', `<div class="table-responsive" style="max-height:70vh"><table class="table table-sm table-hover font-monospace"><thead class="table-dark position-sticky top-0"><tr>${heading}</tr></thead><tbody>${rows}</tbody></table></div>`, async () => {}, 'modal-xl');
    overlay.querySelector('button[type="submit"]')?.remove();
  }

  function openModerationActionDialog() {
    openDialog(
      'Yeni Moderasyon Kaydı',
      `<div class="row g-3">
        <div class="col-md-4"><label class="form-label">Hedef türü</label><select class="form-select" name="target_type" required><option value="user">Kullanıcı</option><option value="content">İçerik</option><option value="chapter">Bölüm</option><option value="blog">Blog</option><option value="comment">Yorum</option><option value="system">Sistem</option></select></div>
        <div class="col-md-4"><label class="form-label">Hedef ID</label><input class="form-control" name="target_id" required></div>
        <div class="col-md-4"><label class="form-label">İşlem</label><input class="form-control" name="action" placeholder="warn, hide, review..." required></div>
        <div class="col-12"><label class="form-label">Gerekçe</label><textarea class="form-control" name="reason" rows="4"></textarea></div>
      </div>`,
      async formData => {
        await api('/moderation-actions', { method: 'POST', body: Object.fromEntries(formData.entries()) });
        closeDialog();
        showToast('Moderasyon kaydı oluşturuldu');
        loadLogsData();
      }
    );
  }

  async function openReportDialog(reportId) {
    const response = await api(`/reports/${reportId}`);
    const report = response?.data || {};
    const target = report.target_url
      ? `<a class="btn btn-sm btn-outline-primary" href="${safeLocalUrl(report.target_url)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Hedefi aç</a>`
      : `<span class="text-secondary">Hedef bağlantısı mevcut değil</span>`;
    const overlay = openDialog(
      `Rapor #${Number(report.id || reportId)}`,
      `<div class="row g-3 mb-4">
        <div class="col-md-4"><small class="text-secondary d-block">Bildiren</small><strong>@${escapeHtml(report.reporter_username)}</strong></div>
        <div class="col-md-4"><small class="text-secondary d-block">Hedef</small><span>${escapeHtml(report.target_type)} / ${escapeHtml(report.target_title || report.target_id)}</span></div>
        <div class="col-md-4"><small class="text-secondary d-block">Neden</small><span>${escapeHtml(report.reason)}</span></div>
        <div class="col-12"><small class="text-secondary d-block">Açıklama</small><div class="border rounded p-3 bg-body-tertiary">${escapeHtml(report.description || report.comment_body || 'Açıklama yok')}</div></div>
        <div class="col-12">${target}</div>
        <div class="col-md-4"><label class="form-label">Durum</label><select class="form-select" name="status"><option value="pending" ${report.status === 'pending' ? 'selected' : ''}>Bekleyen</option><option value="reviewing" ${report.status === 'reviewing' ? 'selected' : ''}>İncelenen</option><option value="resolved" ${report.status === 'resolved' ? 'selected' : ''}>Çözüldü</option><option value="rejected" ${report.status === 'rejected' ? 'selected' : ''}>Reddedildi</option></select></div>
        <div class="col-md-8"><label class="form-label">Moderatör notu</label><textarea class="form-control" name="admin_note" rows="4" maxlength="2000">${escapeHtml(report.admin_note)}</textarea></div>
      </div>`,
      async formData => {
        await api(`/reports/${reportId}`, { method: 'PUT', body: Object.fromEntries(formData.entries()) });
        closeDialog();
        showToast('Rapor güncellendi');
        await loadReportsData(Number(store.get('reportsMeta')?.page || 1));
      }
    );
    if (!hasPermission('admin.reports.manage')) {
      overlay.querySelectorAll('select, textarea').forEach(field => { field.disabled = true; });
      overlay.querySelector('button[type="submit"]')?.remove();
    }
  }

  async function openChaptersDialog(contentId) {
    const content = (store.get('allSeriesList') || []).find(item => String(item.id) === String(contentId));
    if (!content) throw new Error('İçerik bulunamadı');
    const response = await api(`/content/${content.id}/chapters?per_page=100`);
    const chapters = responseItems(response);
    const rows = chapters.map(chapter => `
      <tr>
        <td><input type="checkbox" class="form-check-input" data-chapter-select value="${escapeHtml(chapter.id)}"></td>
        <td>${escapeHtml(chapter.chapter_number)}</td>
        <td>${escapeHtml(chapter.title || '-')}</td>
        <td>${escapeHtml(chapter.type)}</td>
        <td>${Number(chapter.price_amount || 0)} coin</td>
        <td>${escapeHtml(chapter.published_at || '-')}</td>
        <td class="text-end">
          <button type="button" class="btn btn-xs btn-outline-primary" data-edit-chapter="${escapeHtml(chapter.id)}" data-requires-permission="admin.content.update"><i class="bi bi-pencil"></i></button>
          <button type="button" class="btn btn-xs btn-outline-danger" data-delete-chapter="${escapeHtml(chapter.id)}" data-requires-permission="admin.content.update"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
    const overlay = openDialog(
      `${content.title} — Bölümler`,
      `<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div class="btn-group btn-group-sm" data-requires-permission="admin.content.update"><button type="button" class="btn btn-outline-success" data-bulk-chapter="publish">Yayınla</button><button type="button" class="btn btn-outline-warning" data-bulk-chapter="schedule">Zamanla</button><button type="button" class="btn btn-outline-info" data-bulk-chapter="set_price">Fiyatlandır</button><button type="button" class="btn btn-outline-danger" data-bulk-chapter="delete">Sil</button></div><div class="d-flex gap-2"><button type="button" class="btn btn-outline-primary" data-manage-team data-requires-permission="admin.content.update"><i class="bi bi-people me-1"></i>Ekip</button><button type="button" class="btn btn-primary" data-create-chapter data-requires-permission="admin.chapter.create"><i class="bi bi-plus-lg me-1"></i>Yeni Bölüm</button></div></div>
       <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><input type="checkbox" class="form-check-input" data-select-all-chapters></th><th>#</th><th>Başlık</th><th>Tür</th><th>Fiyat</th><th>Yayın</th><th></th></tr></thead><tbody>${rows || '<tr><td colspan="7" class="text-center text-secondary py-4">Bölüm bulunamadı</td></tr>'}</tbody></table></div>`,
      async () => {},
      'modal-xl'
    );
    overlay.querySelector('button[type="submit"]')?.remove();
    overlay.addEventListener('click', async event => {
      const createButton = event.target.closest('[data-create-chapter]');
      const teamButton = event.target.closest('[data-manage-team]');
      const editButton = event.target.closest('[data-edit-chapter]');
      const deleteButton = event.target.closest('[data-delete-chapter]');
      const bulkButton = event.target.closest('[data-bulk-chapter]');
      if (createButton) await openChapterEditor(content);
      if (teamButton) await openTeamDialog(content);
      if (editButton) await openChapterEditor(content, editButton.dataset.editChapter);
      if (deleteButton && confirm('Bu bölümü silmek istediğinize emin misiniz?')) {
        try {
          await api(`/chapters/${deleteButton.dataset.deleteChapter}`, { method: 'DELETE' });
          showToast('Bölüm silindi');
          await loadSeriesData();
          await openChaptersDialog(content.id);
        } catch (error) { showToast(error.message, 'danger'); }
      }
      if (bulkButton) {
        const ids = Array.from(overlay.querySelectorAll('[data-chapter-select]:checked')).map(input => input.value);
        if (!ids.length) return showToast('Önce en az bir bölüm seçin', 'danger');
        const action = bulkButton.dataset.bulkChapter;
        const params = {};
        if (action === 'schedule') {
          const publishedAt = prompt('Yayın tarihi (YYYY-MM-DD HH:MM):');
          if (!publishedAt) return;
          params.published_at = publishedAt;
        }
        if (action === 'set_price') {
          const price = prompt('Coin fiyatı:', '0');
          if (price === null) return;
          params.price_amount = Number(price);
          const freeAfter = prompt('Ücretsiz olma tarihi (isteğe bağlı):', '');
          if (freeAfter) params.is_free_after = freeAfter;
        }
        if (action === 'delete' && !confirm(`${ids.length} bölümü silmek istediğinize emin misiniz?`)) return;
        try {
          const result = await api('/chapters/bulk', { method: 'POST', body: { ids, action, params } });
          showToast(`${result?.data?.affected || ids.length} bölüm güncellendi`);
          await loadSeriesData();
          await openChaptersDialog(content.id);
        } catch (error) { showToast(error.message, 'danger'); }
      }
    });
    overlay.querySelector('[data-select-all-chapters]')?.addEventListener('change', event => {
      overlay.querySelectorAll('[data-chapter-select]').forEach(input => { input.checked = event.target.checked; });
    });
  }

  // 4. Data Fetchers
  async function loadDashboardData() {
    try {
      const [data, insights, monetization, searches] = await Promise.all([
        api('/overview'),
        api('/metrics/insights?days=30&limit=10').catch(() => null),
        api('/analytics/monetization?days=30').catch(() => null),
        api('/analytics/search-insights?days=30&limit=10').catch(() => null)
      ]);
      if (data?.data) {
        const metrics = data.data.metrics || {};
        const insightData = insights?.data || {};
        const visits = insightData.visits || {};
        const views = insightData.views || {};
        const blogSummary = insightData.blogs?.summary || {};
        const money = monetization?.data || {};
        const searchData = searches?.data || {};
        store.batch(() => {
          store.set('overview.total_users', data.data.kpis?.users_total || 0);
          store.set('overview.total_contents', data.data.kpis?.contents_total || 0);
          store.set('overview.total_chapters', data.data.kpis?.chapters_total || 0);
          store.set('overview.queue_pending', data.data.kpis?.blogs_pending_total || 0);
          store.set('topContents', metrics.top_contents_7d || []);
          store.set('analytics.visits_daily', visits.daily || 0);
          store.set('analytics.visits_weekly', visits.weekly || 0);
          store.set('analytics.visits_monthly', visits.monthly || 0);
          store.set('analytics.home_to_content', `${metrics.funnel?.home_to_content_pct || 0}%`);
          store.set('analytics.content_to_chapter', `${metrics.funnel?.content_to_chapter_pct || 0}%`);
          store.set('analytics.error_rate', `${metrics.performance_slo?.server_error_rate_pct_24h || 0}%`);
          store.set('analytics.p95', `${metrics.performance_slo?.p95_duration_ms_24h || 0} ms`);
          store.set('analytics.search_total', metrics.retention_search?.search_total_7d || 0);
          store.set('analytics.zero_result_pct', `${metrics.retention_search?.zero_result_pct_7d || 0}%`);
          store.set('analytics.d1_retention', `${metrics.retention_search?.d1_retention_pct || 0}%`);
          store.set('analytics.new_users', metrics.retention_search?.new_users_7d || 0);
          store.set('analytics.total_coins', money.total_coins_spent || 0);
          store.set('analytics.total_unlocks', money.total_unlocks || 0);
          store.set('analytics.blog_total', blogSummary.total || 0);
          store.set('analytics.blog_visible', blogSummary.visible_total || 0);
          store.set('analytics.blog_hidden', blogSummary.hidden_total || 0);
          store.set('analytics.blog_deleted', blogSummary.deleted_total || 0);
          store.set('analytics.blog_created', blogSummary.created_last_days || 0);
          store.set('analytics.blog_approved', blogSummary.approved_last_days || 0);
          store.set('dashboardGenres', views.series_genres || []);
          store.set('dashboardTags', views.series_tags || []);
          store.set('dashboardReputation', insightData.reputation || []);
          store.set('dashboardTypes', views.types || []);
          store.set('dashboardChapters', views.chapters || []);
          store.set('dashboardBlogAuthors', insightData.blogs?.top_authors || []);
          store.set('dashboardBlogDailyCreated', insightData.blogs?.daily_created || []);
          store.set('dashboardBlogDailyApproved', insightData.blogs?.daily_approved || []);
          store.set('monetizationSeries', money.top_series || []);
          store.set('zeroResultSearches', searchData.zero_result_searches || []);
        });
        renderDashboardTables();
      }
    } catch (e) {
      console.error('Dashboard load error:', e);
    }
  }

  async function loadSeriesData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      const values = {
        q: document.getElementById('panel-series-search')?.value || '',
        status: document.getElementById('panel-series-status')?.value || '',
        type: document.getElementById('panel-series-type')?.value || '',
        lifecycle: document.getElementById('panel-series-lifecycle')?.value || '',
        sort: document.getElementById('panel-series-sort')?.value || 'newest'
      };
      Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
      const res = await api(`/series?${params.toString()}`);
      const items = responseItems(res);
      store.batch(() => {
        store.set('allSeriesList', items);
        store.set('seriesList', items);
        store.set('seriesMeta', responseMeta(res));
      });
      renderSeriesTable();
    } catch (e) {
      showToast('İçerikler yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadUsersData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      const values = {
        q: document.getElementById('panel-users-search')?.value || '',
        status: document.getElementById('panel-users-status')?.value || '',
        role: document.getElementById('panel-users-role')?.value || '',
        sort: document.getElementById('panel-users-sort')?.value || 'newest'
      };
      Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
      const res = await api(`/users?${params.toString()}`);
      const items = responseItems(res).map(user => ({
        ...user,
        role_names: user.role_names || 'user',
        account_status: Number(user.is_banned) === 1 ? 'Yasaklı' : 'Aktif',
        account_badge: Number(user.is_banned) === 1
          ? 'bg-danger-subtle text-danger border border-danger-subtle'
          : 'bg-success-subtle text-success border border-success-subtle'
      }));
      store.batch(() => {
        store.set('allUsersList', items);
        store.set('usersList', items);
        store.set('usersMeta', responseMeta(res));
      });
      renderUsersTable();
    } catch (e) {
      showToast('Kullanıcılar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadBlogsData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      const values = { q: document.getElementById('panel-blogs-search')?.value || '', status: document.getElementById('panel-blogs-status')?.value || '', sort: document.getElementById('panel-blogs-sort')?.value || 'newest' };
      Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
      const res = await api(`/blogs?${params.toString()}`);
      const labels = { draft: 'Taslak', pending: 'Bekliyor', published: 'Yayınlandı', rejected: 'Reddedildi', hidden: 'Gizli' };
      store.batch(() => {
      store.set('blogsList', responseItems(res).map(blog => {
        const approved = Number(blog.approved) === 1;
        return {
          ...blog,
          status_label: labels[blog.status] || (approved ? 'Onaylı' : 'Bekliyor / Gizli'),
          status_badge: approved
            ? 'bg-success-subtle text-success border border-success-subtle'
            : 'bg-warning-subtle text-warning border border-warning-subtle',
          can_approve: approved ? false : true,
          can_hide: approved ? true : false
        };
      }));
      store.set('blogsMeta', responseMeta(res));
      });
      renderBlogsTable();
    } catch (e) {
      showToast('Bloglar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadCommentsData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      const values = { q: document.getElementById('panel-comments-search')?.value || '', target_type: document.getElementById('panel-comments-target')?.value || '', sort: document.getElementById('panel-comments-sort')?.value || 'newest' };
      Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
      const res = await api(`/comments?${params.toString()}`);
      store.batch(() => {
      store.set('commentsList', responseItems(res).map(comment => ({
        ...comment,
        context_label: comment.blog_title
          ? `Blog: ${comment.blog_title}`
          : comment.content_title
            ? `${comment.target_type === 'chapter' ? 'Bölüm' : 'İçerik'}: ${comment.content_title}${comment.chapter_number ? ` #${comment.chapter_number}` : ''}`
            : `${comment.target_type || 'Hedef'}: ${comment.target_id || '-'}`
      })));
      store.set('commentsMeta', responseMeta(res));
      });
      renderCommentsTable();
    } catch (e) {
      showToast('Yorumlar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadReportsData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(Math.max(1, Number(page) || 1)), per_page: '20' });
      const status = document.getElementById('panel-report-status')?.value || '';
      const targetType = document.getElementById('panel-report-target')?.value || '';
      if (status) params.set('status', status);
      if (targetType) params.set('target_type', targetType);
      const response = await api(`/reports?${params.toString()}`);
      store.batch(() => {
        store.set('reportsList', responseItems(response));
        store.set('reportsMeta', {
          page: Number(response?.meta?.page || 1),
          total_pages: Number(response?.meta?.total_pages || 1),
          total: Number(response?.meta?.total || 0),
          counts: response?.meta?.counts || {}
        });
      });
      renderReportsTable();
    } catch (error) { showToast('Raporlar yüklenemedi: ' + error.message, 'danger'); }
  }

  async function loadPackagesData() {
    try {
      const res = await api('/shop/packages');
      store.set('packagesList', responseItems(res).map(item => ({
        ...item,
        status_label: Number(item.is_active) === 1 ? 'Aktif' : 'Pasif',
        status_badge: Number(item.is_active) === 1
          ? 'bg-success-subtle text-success'
          : 'bg-secondary-subtle text-secondary'
      })));
      renderPackagesTable();
    } catch (e) {
      showToast('Paketler yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadFinanceData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '25' });
      const values = { q: document.getElementById('panel-finance-search')?.value || '', type: document.getElementById('panel-finance-type')?.value || '', sort: document.getElementById('panel-finance-sort')?.value || 'newest' };
      Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
      const response = await api(`/finance/transactions?${params.toString()}`);
      const data = response?.data || {};
      store.batch(() => {
        store.set('financeList', Array.isArray(data.items) ? data.items : []);
        store.set('financeSummary', data.summary || {});
        store.set('financeMeta', data.meta || { page: 1, total_pages: 1, total: 0 });
      });
      renderFinanceTable();
    } catch (error) { showToast('Finans hareketleri alınamadı: ' + error.message, 'danger'); }
  }

  async function loadLogsData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '50' });
      const values = { q: document.getElementById('panel-logs-search')?.value || '', method: document.getElementById('panel-logs-method')?.value || '', status: document.getElementById('panel-logs-status')?.value || '', sort: document.getElementById('panel-logs-sort')?.value || 'newest', date_from: document.getElementById('panel-logs-from')?.value || '', date_to: document.getElementById('panel-logs-to')?.value || '' };
      Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
      const res = await api(`/audit-logs?${params.toString()}`);
      store.batch(() => { store.set('logsList', responseItems(res)); store.set('logsMeta', responseMeta(res)); });
      renderLogsTable();
    } catch (e) {
      showToast('Loglar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadUploadsData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '30' });
      const query = document.getElementById('panel-uploads-search')?.value || '';
      const mime = document.getElementById('panel-uploads-mime')?.value || '';
      const orphans = document.getElementById('panel-uploads-orphans')?.checked || false;
      if (query) params.set('q', query);
      if (mime) params.set('mime', mime);
      if (orphans) params.set('orphans', '1');
      const response = await api(`/uploads?${params.toString()}`);
      store.batch(() => {
        store.set('uploadsList', responseItems(response).map(item => ({
          ...item,
          original_name: item.original_name || item.file_path || 'Dosya',
          username: item.username || item.user_id || '-',
          size_label: `${Math.max(0, Number(item.file_size || 0) / 1024).toFixed(1)} KB`
        })));
        store.set('uploadsMeta', responseMeta(response));
        store.set('uploadsStats', response?.meta?.stats || {});
      });
      renderUploadsTable();
    } catch (error) { showToast('Yüklemeler alınamadı: ' + error.message, 'danger'); }
  }

  async function loadQueueJobsData(page = 1) {
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '25' });
      const query = document.getElementById('panel-queue-search')?.value || '';
      const status = document.getElementById('panel-queue-status')?.value || '';
      if (query) params.set('q', query);
      if (status) params.set('status', status);
      const [response, health] = await Promise.all([api(`/queue/jobs?${params.toString()}`), api('/system/health')]);
      store.batch(() => {
        store.set('queueJobsList', responseItems(response));
        store.set('queueMeta', responseMeta(response));
        store.set('systemHealth', health?.data || {});
      });
      renderQueueTable();
    } catch (error) { showToast('Kuyruk alınamadı: ' + error.message, 'danger'); }
  }

  async function loadConfigData() {
    try {
      const response = await api('/config/site');
      const config = response?.data || {};
      config.maintenance_whitelist_text = Array.isArray(config.maintenance_whitelist_ips)
        ? config.maintenance_whitelist_ips.join('\n')
        : '';
      store.set('config', config);
    } catch (error) { showToast('Ayarlar alınamadı: ' + error.message, 'danger'); }
  }

  let logAutoRefreshTimer = null;

  // 5. Global Action Handlers
  const handlers = {
    refreshDashboard() { loadDashboardData(); showToast('İstatistikler güncellendi'); },
    loadSeries() { loadSeriesData(Number(store.get('seriesMeta')?.page || 1)); showToast('İçerik listesi yenilendi'); },
    loadUsers() { loadUsersData(Number(store.get('usersMeta')?.page || 1)); showToast('Kullanıcı listesi yenilendi'); },
    loadBlogs() { loadBlogsData(Number(store.get('blogsMeta')?.page || 1)); showToast('Blog listesi yenilendi'); },
    loadComments() { loadCommentsData(Number(store.get('commentsMeta')?.page || 1)); showToast('Yorum listesi yenilendi'); },
    loadReports() { loadReportsData(Number(store.get('reportsMeta')?.page || 1)); },
    loadLogs() { loadLogsData(Number(store.get('logsMeta')?.page || 1)); showToast('Loglar yenilendi'); },
    filterLogs() { scheduleReload('logs', () => loadLogsData(1)); },
    previousLogsPage() { const page = Number(store.get('logsMeta')?.page || 1); if (page > 1) loadLogsData(page - 1); },
    nextLogsPage() { const meta = store.get('logsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadLogsData(Number(meta.page) + 1); },
    viewAuditLog(e, el) {
      const item = (store.get('logsList') || []).find(log => String(log.id) === String(el.dataset.id));
      if (!item) return;
      const overlay = openDialog(`Denetim Kaydı #${item.id}`, `<pre class="bg-dark text-light rounded p-3 mb-0 text-wrap">${escapeHtml(JSON.stringify(item, null, 2))}</pre>`, async () => {}, 'modal-lg');
      overlay.querySelector('button[type="submit"]')?.remove();
    },
    exportLogsCsv() {
      const columns = ['id', 'method', 'path', 'status_code', 'user_id', 'username', 'duration_ms', 'created_at', 'user_agent'];
      const csvCell = value => { let textValue = String(value ?? ''); if (/^[=+@-]/.test(textValue)) textValue = "'" + textValue; return `"${textValue.replaceAll('"', '""')}"`; };
      const csv = [columns.join(','), ...(store.get('logsList') || []).map(row => columns.map(column => csvCell(row[column])).join(','))].join('\n');
      const url = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }));
      const link = document.createElement('a'); link.href = url; link.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url);
    },
    toggleLogAutoRefresh() {
      const button = document.getElementById('panel-log-auto');
      if (logAutoRefreshTimer) { clearInterval(logAutoRefreshTimer); logAutoRefreshTimer = null; if (button) button.innerHTML = '<i class="bi bi-broadcast me-1"></i>Otomatik: Kapalı'; return; }
      logAutoRefreshTimer = setInterval(() => loadLogsData(1), 15000);
      if (button) button.innerHTML = '<i class="bi bi-broadcast me-1"></i>Otomatik: 15 sn';
    },
    loadUploads() { loadUploadsData(); showToast('Yüklemeler yenilendi'); },
    loadQueueJobs() { loadQueueJobsData(); showToast('Kuyruk yenilendi'); },
    filterQueue() { scheduleReload('queue', () => loadQueueJobsData(1)); },
    previousQueuePage() { const page = Number(store.get('queueMeta')?.page || 1); if (page > 1) loadQueueJobsData(page - 1); },
    nextQueuePage() { const meta = store.get('queueMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadQueueJobsData(Number(meta.page) + 1); },
    async retryQueueJob(e, el) {
      try { await api(`/queue/jobs/${el.dataset.id}/retry`, { method: 'POST' }); showToast('İş yeniden kuyruğa alındı'); await loadQueueJobsData(Number(store.get('queueMeta')?.page || 1)); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async cancelQueueJob(e, el) {
      if (!confirm(`Kuyruk işi #${el.dataset.id} iptal edilsin mi?`)) return;
      try { await api(`/queue/jobs/${el.dataset.id}/cancel`, { method: 'POST' }); showToast('İş iptal edildi'); await loadQueueJobsData(Number(store.get('queueMeta')?.page || 1)); }
      catch (error) { showToast(error.message, 'danger'); }
    },

    async openCreateSeriesModal() {
      try {
        const { genres, tags } = await loadTaxonomies();
        const overlay = openDialog('Yeni İçerik', contentForm({}, genres, tags), async (formData, form) => {
        const selectedGenres = selectedValues(formData, 'genres');
        const selectedTags = selectedValues(formData, 'tags');
        const payload = Object.fromEntries(formData.entries());
        delete payload.genres;
        delete payload.tags;
        delete payload.cover_file;
        payload.is_adult = form.elements.is_adult.checked ? 1 : 0;
        payload.is_members_only = form.elements.is_members_only.checked ? 1 : 0;
        const response = await api('/content', { method: 'POST', body: payload });
        if (response?.data?.id) {
          await api(`/contents/${response.data.id}/taxonomy`, { method: 'PUT', body: { genres: selectedGenres, tags: selectedTags } });
        }
        closeDialog();
        showToast('İçerik oluşturuldu');
        loadSeriesData();
        });
        bindTaxonomyButtons(overlay);
        overlay.querySelector('[name="cover_file"]').addEventListener('change', async event => {
          try {
            const paths = await uploadImages(event.target.files, 'series_cover');
            if (paths[0]) overlay.querySelector('[name="cover_image"]').value = paths[0];
            showToast('Kapak görseli yüklendi');
          } catch (error) { showToast(error.message, 'danger'); }
        });
      } catch (error) { showToast(error.message, 'danger'); }
    },
    async openEditSeriesModal(e, el) {
      const content = (store.get('allSeriesList') || []).find(item => String(item.id) === String(el.dataset.id));
      if (!content) return showToast('İçerik bulunamadı', 'danger');
      try {
        const { genres, tags } = await loadTaxonomies();
        const overlay = openDialog('İçeriği Düzenle', contentForm(content, genres, tags), async (formData, form) => {
        const selectedGenres = selectedValues(formData, 'genres');
        const selectedTags = selectedValues(formData, 'tags');
        const payload = Object.fromEntries(formData.entries());
        delete payload.genres;
        delete payload.tags;
        delete payload.cover_file;
        payload.is_adult = form.elements.is_adult.checked ? 1 : 0;
        payload.is_members_only = form.elements.is_members_only.checked ? 1 : 0;
        await api(`/content/${content.id}`, { method: 'PUT', body: payload });
        await api(`/contents/${content.id}/taxonomy`, { method: 'PUT', body: { genres: selectedGenres, tags: selectedTags } });
        closeDialog();
        showToast('İçerik güncellendi');
        loadSeriesData();
        });
        bindTaxonomyButtons(overlay);
        overlay.querySelector('[name="cover_file"]').addEventListener('change', async event => {
          try {
            const paths = await uploadImages(event.target.files, 'series_cover');
            if (paths[0]) overlay.querySelector('[name="cover_image"]').value = paths[0];
            showToast('Kapak görseli yüklendi');
          } catch (error) { showToast(error.message, 'danger'); }
        });
      } catch (error) { showToast(error.message, 'danger'); }
    },
    async previewSeries(e, el) {
      try { await openSeriesPreview(el.dataset.id); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async viewSeriesRevisions(e, el) {
      try { await openSeriesRevisions(el.dataset.id); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async changeSeriesLifecycle(e, el) {
      const action = el.dataset.action;
      if (!confirm(`İçerik için ${action} işlemi uygulansın mı?`)) return;
      try {
        await api(`/content/${el.dataset.id}/lifecycle`, { method: 'POST', body: { action } });
        showToast('Yayın durumu güncellendi');
        await loadSeriesData(Number(store.get('seriesMeta')?.page || 1));
      } catch (error) { showToast(error.message, 'danger'); }
    },
    async openChaptersDrawer(e, el) {
      try { await openChaptersDialog(el.dataset.id); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async openTaxonomyManager() {
      try { await openTaxonomyDialog(); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    filterSeries() { scheduleReload('series', () => loadSeriesData(1)); },
    filterUsers() { scheduleReload('users', () => loadUsersData(1)); },
    filterBlogs() { scheduleReload('blogs', () => loadBlogsData(1)); },
    filterComments() { scheduleReload('comments', () => loadCommentsData(1)); },
    previousSeriesPage() { const page = Number(store.get('seriesMeta')?.page || 1); if (page > 1) loadSeriesData(page - 1); },
    nextSeriesPage() { const meta = store.get('seriesMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadSeriesData(Number(meta.page) + 1); },
    previousUsersPage() { const page = Number(store.get('usersMeta')?.page || 1); if (page > 1) loadUsersData(page - 1); },
    nextUsersPage() { const meta = store.get('usersMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUsersData(Number(meta.page) + 1); },
    previousBlogsPage() { const page = Number(store.get('blogsMeta')?.page || 1); if (page > 1) loadBlogsData(page - 1); },
    nextBlogsPage() { const meta = store.get('blogsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadBlogsData(Number(meta.page) + 1); },
    previousCommentsPage() { const page = Number(store.get('commentsMeta')?.page || 1); if (page > 1) loadCommentsData(page - 1); },
    nextCommentsPage() { const meta = store.get('commentsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadCommentsData(Number(meta.page) + 1); },
    filterReports() { loadReportsData(1); },
    previousReportsPage() {
      const page = Number(store.get('reportsMeta')?.page || 1);
      if (page > 1) loadReportsData(page - 1);
    },
    nextReportsPage() {
      const meta = store.get('reportsMeta') || {};
      const page = Number(meta.page || 1);
      if (page < Number(meta.total_pages || 1)) loadReportsData(page + 1);
    },
    async openReport(e, el) {
      try { await openReportDialog(el.dataset.id); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async openEditUserModal(e, el) {
      try { await openUserEditor(el.dataset.id); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async openRbacMatrix() {
      try { await openRbacDialog(); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async openWalletModal(e, el) {
      try { await openWalletDialog(el.dataset.id); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    openCreatePackageModal() { openPackageEditor(); },
    openEditPackageModal(e, el) {
      const item = (store.get('packagesList') || []).find(packageItem => String(packageItem.id) === String(el.dataset.id));
      if (!item) return showToast('Paket bulunamadı', 'danger');
      openPackageEditor(item);
    },
    async openAdFreeModal() {
      try { await openAdFreeDialog(); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    openPricingModal() { openPricingDialog(); },
    filterFinance() { scheduleReload('finance', () => loadFinanceData(1)); },
    previousFinancePage() { const page = Number(store.get('financeMeta')?.page || 1); if (page > 1) loadFinanceData(page - 1); },
    nextFinancePage() { const meta = store.get('financeMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadFinanceData(Number(meta.page) + 1); },
    async refundFinanceTransaction(e, el) {
      const reason = prompt('İade nedeni:');
      if (!reason?.trim()) return;
      if (!confirm(`İşlem #${el.dataset.id} için coin iadesi yapılsın ve ilgili erişim geri alınsın mı?`)) return;
      try {
        await api(`/finance/transactions/${el.dataset.id}/refund`, { method: 'POST', body: { reason: reason.trim() } });
        showToast('İade işlemi tamamlandı');
        await loadFinanceData(Number(store.get('financeMeta')?.page || 1));
      } catch (error) { showToast(error.message, 'danger'); }
    },

    async deleteBlog(e, el) {
      const id = el.dataset.id;
      if (!confirm(`Bu blog yazısını silmek istediğinize emin misiniz?`)) return;
      try {
        await api(`/blogs/${id}`, { method: 'DELETE' });
        showToast('Blog yazısı silindi');
        loadBlogsData();
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async approveBlog(e, el) {
      try {
        await api(`/blogs/${el.dataset.id}/approve`, { method: 'POST' });
        showToast('Blog yazısı onaylandı');
        loadBlogsData();
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async hideBlog(e, el) {
      if (!confirm('Bu blog yazısını gizlemek istediğinize emin misiniz?')) return;
      try {
        await api(`/blogs/${el.dataset.id}/hide`, { method: 'POST' });
        showToast('Blog yazısı gizlendi');
        loadBlogsData();
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async deleteComment(e, el) {
      const id = el.dataset.id;
      if (!confirm(`Bu yorumu silmek istediğinize emin misiniz?`)) return;
      try {
        await api(`/comments/${id}`, { method: 'DELETE' });
        showToast('Yorum silindi');
        loadCommentsData();
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async deleteUpload(e, el) {
      if (!confirm('Bu yükleme kaydı ve bağlı dosya silinsin mi?')) return;
      try {
        await api(`/uploads/${el.dataset.id}`, { method: 'DELETE' });
        showToast('Yükleme silindi');
        loadUploadsData(Number(store.get('uploadsMeta')?.page || 1));
      } catch (error) { showToast(error.message, 'danger'); }
    },
    filterUploads() { scheduleReload('uploads', () => loadUploadsData(1)); },
    previousUploadsPage() { const page = Number(store.get('uploadsMeta')?.page || 1); if (page > 1) loadUploadsData(page - 1); },
    nextUploadsPage() { const meta = store.get('uploadsMeta') || {}; if (Number(meta.page) < Number(meta.total_pages)) loadUploadsData(Number(meta.page) + 1); },
    toggleAllUploads(e, el) { document.querySelectorAll('[data-upload-select]').forEach(input => { input.checked = el.checked; }); },
    async bulkDeleteUploads() {
      const ids = Array.from(document.querySelectorAll('[data-upload-select]:checked')).map(input => Number(input.value));
      if (ids.length === 0) return showToast('Önce en az bir dosya seçin', 'danger');
      if (!confirm(`${ids.length} yükleme kaydı ve fiziksel dosyaları silinsin mi?`)) return;
      try { const response = await api('/uploads/bulk-delete', { method: 'POST', body: { ids } }); showToast(`${Number(response?.data?.deleted || 0)} dosya silindi`); await loadUploadsData(1); }
      catch (error) { showToast(error.message, 'danger'); }
    },
    async optimizeUpload(e, el) {
      try { const response = await api(`/uploads/${el.dataset.id}/optimize`, { method: 'POST' }); showToast(`${Number(response?.data?.saved_bytes || 0)} bayt kazanıldı`); await loadUploadsData(Number(store.get('uploadsMeta')?.page || 1)); }
      catch (error) { showToast(error.message, 'danger'); }
    },

    async runQueueWorker() {
      try {
        const limit = Number(document.getElementById('panel-queue-limit')?.value || 20);
        const res = await api('/queue/run-once', { method: 'POST', body: { limit } });
        showToast(`Kuyruk çalıştırıldı (limit: ${res?.data?.limit || limit})`);
        loadQueueJobsData();
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async runRetentionCleanup() {
      try {
        const days = Number(document.getElementById('panel-cleanup-days')?.value || 30);
        await api('/retention/cleanup', { method: 'POST', body: { days } });
        showToast('Sistem temizliği başarıyla tamamlandı');
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async runCacheWarmup() {
      try {
        await api('/maintenance/warmup', { method: 'POST' });
        showToast('Önbellek başarıyla ısıtıldı');
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async generateSitemap() {
      try {
        await api('/maintenance/sitemap', { method: 'POST' });
        showToast('Sitemap başarıyla üretildi');
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async runMaintenance(e, el) {
      const output = document.getElementById('panel-maintenance-output');
      if (output) output.textContent = 'İşlem çalışıyor...';
      try {
        const response = await api(`/maintenance/${el.dataset.task}`, { method: 'POST' });
        const result = response?.data || {};
        if (output) output.textContent = Array.isArray(result.output) ? result.output.join('\n') : JSON.stringify(result, null, 2);
        showToast('Bakım işlemi tamamlandı');
      } catch (error) {
        if (output) output.textContent = error.message;
        showToast(error.message, 'danger');
      }
    },

    async openLogViewer(e, el) {
      try { await openLogDialog(el.dataset.log); }
      catch (error) { showToast(error.message, 'danger'); }
    },

    openModerationAction() { openModerationActionDialog(); },

    async openWebhooks() {
      try { await openWebhooksDialog(); }
      catch (error) { showToast(error.message, 'danger'); }
    },

    async openEnvEditor() {
      try { await openEnvDialog(); }
      catch (error) { showToast(error.message, 'danger'); }
    },

    async saveConfig(e) {
      if (e) e.preventDefault();
      try {
        const payload = { ...store.get('config') };
        payload.maintenance_whitelist_ips = String(payload.maintenance_whitelist_text || '').split(/\r?\n|,/).map(value => value.trim()).filter(Boolean);
        delete payload.maintenance_whitelist_text;
        if (payload.site_logo) {
          payload.logo_url = payload.site_logo;
        }
        const res = await api('/config/site', { method: 'POST', body: payload });
        if (res?.data) {
          const updated = res.data;
          updated.maintenance_whitelist_text = Array.isArray(updated.maintenance_whitelist_ips)
            ? updated.maintenance_whitelist_ips.join('\n')
            : '';
          store.set('config', updated);
        }
        showToast('Site ayarları başarıyla kaydedildi!');
      } catch (err) { showToast(err.message, 'danger'); }
    }
  };

  // 6. Router & View Mount
  const target = document.getElementById('panel-app');
  let currentCleanup = null;
  const routePermissions = {
    monetization: ['admin.shop.manage'],
    finance: ['admin.finance.view'],
    reports: ['admin.reports.view'],
    uploads: ['admin.uploads.view'],
    ops: ['admin.health.view'],
    logs: ['admin.logs.view'],
    config: ['admin.settings.modify']
  };

  const validPanelRoutes = ['dashboard', 'series', 'users', 'blogs', 'comments', 'reports', 'monetization', 'finance', 'ops', 'logs', 'uploads', 'config', 'help'];

  function panelRoutePath(route) {
    return route === 'dashboard' ? '/panel' : `/panel/${route}`;
  }

  function navigate(forcedRoute = null) {
    const pathPart = decodeURIComponent(window.location.pathname.replace(/^\/panel\/?/, '')).split('/')[0];
    const hashPart = (window.location.hash || '').replace(/^#/, '');
    const candidate = forcedRoute || pathPart || hashPart || 'dashboard';
    const requestedRoute = validPanelRoutes.includes(candidate) ? candidate : 'dashboard';
    const required = routePermissions[requestedRoute] || [];
    const route = required.length === 0 || hasPermission(...required) ? requestedRoute : 'dashboard';

    if (window.location.pathname !== panelRoutePath(route) || window.location.hash) {
      history.replaceState({ route }, '', panelRoutePath(route));
    }

    // Update active nav link
    document.querySelectorAll('#panel-sidebar-nav a').forEach(a => {
      if (a.getAttribute('data-route') === route) {
        a.classList.add('active-nav-link');
      } else {
        a.classList.remove('active-nav-link');
      }
    });

    if (currentCleanup) {
      currentCleanup();
      currentCleanup = null;
    }
    if (route !== 'logs' && logAutoRefreshTimer) {
      clearInterval(logAutoRefreshTimer);
      logAutoRefreshTimer = null;
    }

    currentCleanup = mount(`panel-${route}`, {
      target,
      store,
      handlers
    });
    applyPermissionVisibility(target);
    renderRouteTables(route);

    // Fetch view-specific data
    if (route === 'dashboard') loadDashboardData();
    else if (route === 'series') loadSeriesData();
    else if (route === 'users') loadUsersData();
    else if (route === 'blogs') loadBlogsData();
    else if (route === 'comments') loadCommentsData();
    else if (route === 'reports') loadReportsData();
    else if (route === 'monetization') loadPackagesData();
    else if (route === 'finance') loadFinanceData();
    else if (route === 'ops') loadQueueJobsData();
    else if (route === 'logs') loadLogsData();
    else if (route === 'uploads') loadUploadsData();
    else if (route === 'config') loadConfigData();
  }

  applyPermissionVisibility(document);
  document.querySelectorAll('#panel-sidebar-nav a[data-route]').forEach(link => {
    const route = link.dataset.route;
    link.href = panelRoutePath(route);
    link.addEventListener('click', event => {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      history.pushState({ route }, '', panelRoutePath(route));
      navigate(route);
    });
  });
  window.addEventListener('popstate', () => navigate());
  window.addEventListener('hashchange', () => navigate());
  navigate();
</script>
</body>
</html>
