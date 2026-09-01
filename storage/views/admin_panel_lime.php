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
              <a href="/panel#config" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear me-1"></i> Ayarlar</a>
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
          <li class="nav-item">
            <a href="#monetization" class="nav-link rounded" data-route="monetization">
              <i class="nav-icon bi bi-coin me-2 text-warning"></i>
              <p class="mb-0">Para Kazanma & Mağaza</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#ops" class="nav-link rounded" data-route="ops">
              <i class="nav-icon bi bi-cpu-fill me-2 text-info"></i>
              <p class="mb-0">Kuyruk & Sistem Bakımı</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#logs" class="nav-link rounded" data-route="logs">
              <i class="nav-icon bi bi-terminal-fill me-2 text-secondary"></i>
              <p class="mb-0">Sistem Logları & Güvenlik</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#config" class="nav-link rounded" data-route="config">
              <i class="nav-icon bi bi-sliders me-2 text-light"></i>
              <p class="mb-0">Site Yapılandırması</p>
            </a>
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
                <p class="mb-1 text-white-50 small text-uppercase fw-semibold">Bekleyen Kuyruk İşi</p>
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
                  </tr>
                </thead>
                <tbody>
                  <for each="topContents" as="item" key="item.id" data-live>
                    <tr>
                      <td class="fw-semibold">${item.title}</td>
                      <td><span class="badge bg-secondary-subtle text-secondary border">${item.type}</span></td>
                      <td class="fw-bold text-primary">${item.views}</td>
                    </tr>
                  </for>
                </tbody>
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
              <a href="#ops" class="btn btn-outline-warning text-start"><i class="bi bi-play-circle me-2"></i> Kuyruğu Çalıştır</a>
              <a href="#logs" class="btn btn-outline-secondary text-start"><i class="bi bi-terminal me-2"></i> Denetim Loglarını İncele</a>
              <a href="#config" class="btn btn-outline-dark text-start"><i class="bi bi-sliders me-2"></i> Site Ayarlarını Düzenle</a>
            </div>
          </div>
        </div>
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
        <button class="btn btn-sm btn-primary" data-on-click="openCreateSeriesModal">
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
            <div class="col-md-6">
              <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0" placeholder="Başlık veya slug ile filtrele..." data-model="seriesSearch" data-on-input="filterSeries">
              </div>
            </div>
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
            <tbody>
              <for each="seriesList" as="item" key="item.id" data-live>
                <tr>
                  <td class="text-secondary small">${item.id}</td>
                  <td><span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase">${item.type}</span></td>
                  <td class="fw-bold">${item.title}</td>
                  <td class="text-secondary">${item.slug}</td>
                  <td>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">${item.status}</span>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-primary me-1" data-on-click="openChaptersDrawer" data-id="${item.id}" data-title="${item.title}">
                      <i class="bi bi-collection me-1"></i> Bölümler
                    </button>
                    <button class="btn btn-xs btn-outline-secondary me-1" data-on-click="openEditSeriesModal" data-id="${item.id}">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-danger" data-on-click="deleteSeries" data-id="${item.id}">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </for>
            </tbody>
          </table>
        </div>
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
      <button class="btn btn-sm btn-outline-secondary" data-on-click="loadUsers">
        <i class="bi bi-arrow-clockwise me-1"></i> Yenile
      </button>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
          <input type="text" class="form-control" placeholder="Kullanıcı adı veya e-posta ile ara..." data-model="userSearch" data-on-input="filterUsers">
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
                <th>Coin Bakiyesi</th>
                <th>Kayıt Tarihi</th>
                <th class="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              <for each="usersList" as="u" key="u.id" data-live>
                <tr>
                  <td class="fw-bold"><i class="bi bi-person me-1 text-secondary"></i> ${u.username}</td>
                  <td class="text-secondary">${u.email}</td>
                  <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">${u.role}</span></td>
                  <td class="fw-semibold text-warning"><i class="bi bi-coin me-1"></i> ${u.coin_balance}</td>
                  <td class="small text-secondary">${u.created_at}</td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-primary me-1" data-on-click="openEditUserModal" data-id="${u.id}">
                      <i class="bi bi-pencil me-1"></i> Düzenle
                    </button>
                    <button class="btn btn-xs btn-outline-warning" data-on-click="openWalletModal" data-id="${u.id}" data-username="${u.username}">
                      <i class="bi bi-cash-coin me-1"></i> Bakiye
                    </button>
                  </td>
                </tr>
              </for>
            </tbody>
          </table>
        </div>
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
            <tbody>
              <for each="blogsList" as="b" key="b.id" data-live>
                <tr>
                  <td class="fw-bold">${b.title}</td>
                  <td>${b.author}</td>
                  <td><span class="badge bg-info-subtle text-info border">${b.status}</span></td>
                  <td class="small text-secondary">${b.created_at}</td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-danger" data-on-click="deleteBlog" data-id="${b.id}">
                      <i class="bi bi-trash me-1"></i> Sil / Gizle
                    </button>
                  </td>
                </tr>
              </for>
            </tbody>
          </table>
        </div>
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
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Kullanıcı</th>
                <th>Yorum Metni</th>
                <th>Beğeni / Dislike</th>
                <th>Tarih</th>
                <th class="text-end">İşlem</th>
              </tr>
            </thead>
            <tbody>
              <for each="commentsList" as="c" key="c.id" data-live>
                <tr>
                  <td class="fw-bold">${c.username}</td>
                  <td><span class="text-wrap">${c.body}</span></td>
                  <td><span class="badge bg-success-subtle text-success">+${c.upvotes}</span> <span class="badge bg-danger-subtle text-danger">-${c.downvotes}</span></td>
                  <td class="small text-secondary">${c.created_at}</td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-danger" data-on-click="deleteComment" data-id="${c.id}">
                      <i class="bi bi-trash"></i> Sil
                    </button>
                  </td>
                </tr>
              </for>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<!-- 6. MONETIZATION & PACKAGES VIEW -->
<template id="tpl-panel-monetization">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Para Kazanma & Coin Paketleri</h3>
        <p class="text-secondary small mb-0">Mağaza paketleri ve bakiye yapılandırması</p>
      </div>
      <button class="btn btn-sm btn-primary" data-on-click="openCreatePackageModal">
        <i class="bi bi-plus-lg me-1"></i> Yeni Paket Ekle
      </button>
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
            <tbody>
              <for each="packagesList" as="p" key="p.id" data-live>
                <tr>
                  <td class="fw-bold">${p.name}</td>
                  <td class="text-warning fw-bold">${p.coin_amount}</td>
                  <td class="text-success">+${p.bonus_coin}</td>
                  <td><span class="badge bg-secondary-subtle text-secondary fs-7">${p.price} ${p.currency}</span></td>
                  <td><span class="badge bg-success-subtle text-success">Aktif</span></td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-outline-danger" data-on-click="deletePackage" data-id="${p.id}">
                      <i class="bi bi-trash"></i> Sil
                    </button>
                  </td>
                </tr>
              </for>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
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
      <div class="row g-4">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-cpu-fill text-primary me-2"></i>Kuyruk İşleyici (Queue Worker)</h5>
            </div>
            <div class="card-body px-4">
              <p class="text-secondary small">Bekleyen arka plan işlerini (bildirimler, e-postalar) tek adımda çalıştırır.</p>
              <button class="btn btn-primary" data-on-click="runQueueWorker">
                <i class="bi bi-play-fill me-1"></i> Kuyruğu Çalıştır (Run Once)
              </button>
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
              <button class="btn btn-outline-danger" data-on-click="runRetentionCleanup">
                <i class="bi bi-broom me-1"></i> Temizliği Başlat
              </button>
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
              <button class="btn btn-outline-warning" data-on-click="runCacheWarmup">
                <i class="bi bi-fire me-1"></i> Önbelleği Isıt
              </button>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-4">
              <h5 class="card-title fw-bold mb-0"><i class="bi bi-map-fill text-info me-2"></i>Sitemap & SEO Oluşturucu</h5>
            </div>
            <div class="card-body px-4">
              <p class="text-secondary small">Arama motorları için sitemap.xml dosyasını yeniden üretir.</p>
              <button class="btn btn-outline-info" data-on-click="generateSitemap">
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
      <button class="btn btn-sm btn-outline-secondary" data-on-click="loadLogs">
        <i class="bi bi-arrow-clockwise me-1"></i> Yenile
      </button>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm rounded-3">
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
              </tr>
            </thead>
            <tbody>
              <for each="logsList" as="l" key="l.id" data-live>
                <tr>
                  <td><span class="badge bg-secondary-subtle text-secondary">${l.method}</span></td>
                  <td class="fw-bold">${l.path}</td>
                  <td><span class="badge bg-success-subtle text-success">${l.status_code}</span></td>
                  <td>${l.user_id}</td>
                  <td>${l.duration_ms}ms</td>
                  <td class="text-secondary">${l.created_at}</td>
                </tr>
              </for>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<!-- 9. SITE CONFIG VIEW -->
<template id="tpl-panel-config">
  <div class="app-content-header py-3 px-4 bg-body border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0 fw-bold fs-4">Site Yapılandırması</h3>
        <p class="text-secondary small mb-0">Genel site ayarları, tema ve güvenlik parametreleri</p>
      </div>
      <button class="btn btn-sm btn-primary" data-on-click="saveConfig">
        <i class="bi bi-check2-circle me-1"></i> Ayarları Kaydet
      </button>
    </div>
  </div>

  <div class="app-content p-4">
    <div class="container-fluid">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
          <form data-on-submit="saveConfig">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Site Adı</label>
                <input type="text" class="form-control" data-model="config.site_name">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Site Kısaltması (Abbreviation)</label>
                <input type="text" class="form-control" data-model="config.site_abbreviation">
              </div>
              <div class="col-md-12">
                <label class="form-label fw-semibold">Site Sloganı</label>
                <input type="text" class="form-control" data-model="config.site_slogan">
              </div>
              <div class="col-md-12">
                <label class="form-label fw-semibold">Site Açıklaması (Meta Description)</label>
                <textarea class="form-control" rows="3" data-model="config.site_description"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Varsayılan Dil</label>
                <select class="form-select" data-model="config.default_language">
                  <option value="tr">Türkçe (tr)</option>
                  <option value="en">English (en)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Varsayılan Tema</label>
                <select class="form-select" data-model="config.default_theme">
                  <option value="dark">Dark (Karanlık)</option>
                  <option value="light">Light (Aydınlık)</option>
                  <option value="system">System (Sistem)</option>
                </select>
              </div>
            </div>
          </form>
        </div>
      </div>
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
    seriesList: [],
    seriesSearch: '',
    usersList: [],
    userSearch: '',
    blogsList: [],
    commentsList: [],
    packagesList: [],
    logsList: [],
    config: window.__NMR_CONTEXT?.site_config || {
      site_name: 'NMR',
      site_abbreviation: 'NMR',
      site_slogan: 'Manga & Novel Reader',
      site_description: 'Fast manga reader',
      default_language: 'tr',
      default_theme: 'dark'
    }
  });

  const csrfToken = window.__NMR_CONTEXT?.auth?.csrf_token || '';

  // 2. Toast Notification Helper
  function showToast(message, type = 'success') {
    const container = document.getElementById('lime-toasts');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} shadow-lg py-2 px-3 mb-0 rounded-3 d-flex align-items-center gap-2 text-dark`;
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill text-success' : 'exclamation-circle-fill text-danger'}"></i> <span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }

  // 3. Authenticated Fetch Helper
  async function api(path, options = {}) {
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
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err?.error?.message || `HTTP ${res.status}`);
    }
    return res.status === 204 ? null : res.json();
  }

  // 4. Data Fetchers
  async function loadDashboardData() {
    try {
      const data = await api('/overview');
      if (data?.data) {
        store.batch(() => {
          store.set('overview.total_users', data.data.kpis?.users_total || 0);
          store.set('overview.total_contents', data.data.kpis?.series_total || 0);
          store.set('overview.total_chapters', data.data.kpis?.chapters_total || 0);
          store.set('overview.queue_pending', data.data.kpis?.queue_pending_total || 0);
          store.set('topContents', data.data.top_series || []);
        });
      }
    } catch (e) {
      console.error('Dashboard load error:', e);
    }
  }

  async function loadSeriesData() {
    try {
      const res = await api('/series?per_page=50');
      store.set('seriesList', res?.data?.items || []);
    } catch (e) {
      showToast('İçerikler yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadUsersData() {
    try {
      const res = await api('/users?per_page=50');
      store.set('usersList', res?.data?.items || []);
    } catch (e) {
      showToast('Kullanıcılar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadBlogsData() {
    try {
      const res = await api('/blogs?per_page=50');
      store.set('blogsList', res?.data?.items || []);
    } catch (e) {
      showToast('Bloglar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadCommentsData() {
    try {
      const res = await api('/comments?per_page=50');
      store.set('commentsList', res?.data?.items || []);
    } catch (e) {
      showToast('Yorumlar yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadPackagesData() {
    try {
      const res = await api('/shop/packages');
      store.set('packagesList', res?.data?.items || []);
    } catch (e) {
      showToast('Paketler yüklenemedi: ' + e.message, 'danger');
    }
  }

  async function loadLogsData() {
    try {
      const res = await api('/audit-logs?per_page=50');
      store.set('logsList', res?.data?.items || []);
    } catch (e) {
      showToast('Loglar yüklenemedi: ' + e.message, 'danger');
    }
  }

  // 5. Global Action Handlers
  const handlers = {
    refreshDashboard() { loadDashboardData(); showToast('İstatistikler güncellendi'); },
    loadSeries() { loadSeriesData(); showToast('İçerik listesi yenilendi'); },
    loadUsers() { loadUsersData(); showToast('Kullanıcı listesi yenilendi'); },
    loadBlogs() { loadBlogsData(); showToast('Blog listesi yenilendi'); },
    loadComments() { loadCommentsData(); showToast('Yorum listesi yenilendi'); },
    loadLogs() { loadLogsData(); showToast('Loglar yenilendi'); },

    async deleteSeries(e, el) {
      const id = el.dataset.id;
      if (!confirm(`Bu içeriği (ID: ${id}) silmek istediğinize emin misiniz?`)) return;
      try {
        await api(`/series/${id}`, { method: 'DELETE' });
        showToast('İçerik başarıyla silindi');
        loadSeriesData();
      } catch (err) { showToast(err.message, 'danger'); }
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

    async deleteComment(e, el) {
      const id = el.dataset.id;
      if (!confirm(`Bu yorumu silmek istediğinize emin misiniz?`)) return;
      try {
        await api(`/comments/${id}`, { method: 'DELETE' });
        showToast('Yorum silindi');
        loadCommentsData();
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async runQueueWorker() {
      try {
        const res = await api('/queue/run-once', { method: 'POST', body: { limit: 20 } });
        showToast(`Kuyruk işlendi: ${res?.data?.processed || 0} iş tamamlandı`);
      } catch (err) { showToast(err.message, 'danger'); }
    },

    async runRetentionCleanup() {
      try {
        await api('/retention/cleanup', { method: 'POST' });
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

    async saveConfig(e) {
      if (e) e.preventDefault();
      try {
        await api('/config/site', { method: 'POST', body: store.get('config') });
        showToast('Site ayarları başarıyla kaydedildi!');
      } catch (err) { showToast(err.message, 'danger'); }
    }
  };

  // 6. Router & View Mount
  const target = document.getElementById('panel-app');
  let currentCleanup = null;

  function navigate() {
    const hash = (window.location.hash || '#dashboard').replace('#', '');
    const validRoutes = ['dashboard', 'series', 'users', 'blogs', 'comments', 'monetization', 'ops', 'logs', 'config'];
    const route = validRoutes.includes(hash) ? hash : 'dashboard';

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

    currentCleanup = mount(`panel-${route}`, {
      target,
      store,
      handlers
    });

    // Fetch view-specific data
    if (route === 'dashboard') loadDashboardData();
    else if (route === 'series') loadSeriesData();
    else if (route === 'users') loadUsersData();
    else if (route === 'blogs') loadBlogsData();
    else if (route === 'comments') loadCommentsData();
    else if (route === 'monetization') loadPackagesData();
    else if (route === 'logs') loadLogsData();
  }

  window.addEventListener('hashchange', navigate);
  navigate();
</script>
</body>
</html>
