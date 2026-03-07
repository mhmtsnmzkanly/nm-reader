<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(
      $title ?? "Admin Console",
      ENT_QUOTES,
      "UTF-8",
  ) ?></title>
  <meta name="robots" content="noindex,nofollow">

  <!-- Fonts -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
  <!-- Third Party Plugins -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <!-- AdminLTE -->
  <link rel="stylesheet" href="/assets/css/adminlte.css">
  <link rel="stylesheet" href="/assets/css/admin-custom.css">

  <script>window.__NMR_CONTEXT = <?= $contextJson ?? "{}" ?>;</script>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
  <!-- Header -->
  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
        </li>
        <li class="nav-item d-none d-md-block"><a href="<?= $url('/') ?>" class="nav-link"><?= htmlspecialchars((string) (($siteConfig['site_name'] ?? null) ?: 'Main Site'), ENT_QUOTES, 'UTF-8') ?></a></li>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown user-menu">
          <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
            <span class="d-none d-md-inline"><?= htmlspecialchars(
                (string) ($adminUsername ?? "Administrator"),
                ENT_QUOTES,
                "UTF-8",
            ) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
            <li class="user-header text-bg-primary">
              <p>
                <?= htmlspecialchars(
                    (string) ($adminUsername ?? "Administrator"),
                    ENT_QUOTES,
                    "UTF-8",
                ) ?>
                <small>System Administrator</small>
              </p>
            </li>
            <li class="user-footer">
              <a href="/logout" class="btn btn-default btn-flat float-end">Sign out</a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Sidebar -->
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="<?= $url('/admin') ?>" class="brand-link text-decoration-none">
        <span class="brand-text fw-light"><?= htmlspecialchars((string) (($siteConfig['site_abbreviation'] ?? null) ?: 'NMR'), ENT_QUOTES, 'UTF-8') ?> <strong>Admin</strong></span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <?php
      $authPermissions = is_array($authContext['permissions'] ?? null) ? $authContext['permissions'] : [];
      $can = static fn (string $perm): bool => in_array($perm, $authPermissions, true);
      $canAny = static function (array $perms) use ($authPermissions): bool {
          foreach ($perms as $perm) {
              if (in_array($perm, $authPermissions, true)) {
                  return true;
              }
          }
          return false;
      };
      ?>
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
          <?php if ($can('admin.panel.access')): ?>
            <li class="nav-item"><a href="<?= $url('/admin#dashboard-section') ?>" class="nav-link"><i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p></a></li>
          <?php endif; ?>
          <?php if ($can('admin.metrics.view')): ?>
            <li class="nav-item"><a href="<?= $url('/admin#metrics-section') ?>" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Analytics</p></a></li>
          <?php endif; ?>
          <?php if ($canAny(['admin.content.create', 'admin.content.update', 'admin.chapter.create', 'admin.section.create'])): ?>
            <li class="nav-item"><a href="<?= $url('/admin/content') ?>" class="nav-link"><i class="nav-icon bi bi-journal-text"></i><p>Content & Chapters</p></a></li>
          <?php endif; ?>
          <?php if ($can('admin.blog.hide')): ?>
            <li class="nav-item"><a href="<?= $url('/admin/blogs') ?>" class="nav-link"><i class="nav-icon bi bi-file-earmark-check"></i><p>Blog Moderation</p></a></li>
          <?php endif; ?>
          <?php if ($can('admin.comment.delete')): ?>
            <li class="nav-item"><a href="<?= $url('/admin/comments') ?>" class="nav-link"><i class="nav-icon bi bi-chat-dots"></i><p>Comments</p></a></li>
          <?php endif; ?>
          <?php if ($canAny(['admin.users.manage', 'admin.roles.assign'])): ?>
            <li class="nav-item"><a href="<?= $url('/admin/users') ?>" class="nav-link"><i class="nav-icon bi bi-people"></i><p>Users</p></a></li>
          <?php endif; ?>
          <?php if ($canAny(['admin.jobs.run', 'admin.settings.modify'])): ?>
            <li class="nav-item"><a href="<?= $url('/admin/ops') ?>" class="nav-link"><i class="nav-icon bi bi-gear"></i><p>System Ops</p></a></li>
          <?php endif; ?>
          <?php if (($_SESSION['user_id'] ?? null) === ($_ENV['ROOT_USER'] ?? getenv('ROOT_USER'))): ?>
            <li class="nav-item"><a href="<?= $url('/admin/config') ?>" class="nav-link"><i class="nav-icon bi bi-terminal-fill"></i><p>System Config</p></a></li>
          <?php endif; ?>
          <?php if ($canAny(['admin.logs.view', 'admin.metrics.view'])): ?>
            <li class="nav-item"><a href="<?= $url('/admin/logs') ?>" class="nav-link"><i class="nav-icon bi bi-shield-lock"></i><p>Logs & Security</p></a></li>
          <?php endif; ?>
          <?php if ($can('admin.panel.access')): ?>
            <li class="nav-item"><a href="<?= $url('/admin/uploads') ?>" class="nav-link"><i class="nav-icon bi bi-image"></i><p>Uploads</p></a></li>
          <?php endif; ?>
          <li class="nav-item"><a href="<?= $url('/admin/tutorial') ?>" class="nav-link"><i class="nav-icon bi bi-question-circle"></i><p><?= $__t('admin.tutorial') ?></p></a></li>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="app-main">
    <?= $content ?? "" ?>
  </main>

  <!-- Footer -->
  <footer class="app-footer">
    <strong><?= htmlspecialchars((string) (($siteConfig['site_name'] ?? null) ?: 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?> &copy; 2026</strong>
  </footer>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script src="/assets/js/admin-bundle.js"></script>

<script>
  const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
  const Default = {
    scrollbarTheme: 'os-theme-light',
    scrollbarAutoHide: 'leave',
    scrollbarClickScroll: true,
  };
  document.addEventListener('DOMContentLoaded', function () {
    const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
    if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
      OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
        scrollbars: {
          theme: Default.scrollbarTheme,
          autoHide: Default.scrollbarAutoHide,
          clickScroll: Default.scrollbarClickScroll,
        },
      });
    }
  });
</script>

<?php if (!empty($scripts)):
    foreach ($scripts as $script): ?>
  <script src="<?= htmlspecialchars(
      (string) $script,
      ENT_QUOTES,
      "UTF-8",
  ) ?>"></script>
<?php endforeach;
endif; ?>
</body>
</html>
