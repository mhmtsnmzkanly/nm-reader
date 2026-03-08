<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($langCode ?? 'en'), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, "UTF-8") ?></title>
  <meta name="description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, "UTF-8") ?>">
  <meta name="keywords" content="<?= htmlspecialchars((string) $seoKeywords, ENT_QUOTES, "UTF-8") ?>">
  <meta name="robots" content="<?= htmlspecialchars((string) $seoRobots, ENT_QUOTES, "UTF-8") ?>">
  <link rel="canonical" href="<?= htmlspecialchars((string) $seoCanonical, ENT_QUOTES, "UTF-8") ?>">

  <meta property="og:site_name" content="<?= htmlspecialchars((string) $seoSiteName, ENT_QUOTES, "UTF-8") ?>">
  <meta property="og:locale" content="<?= htmlspecialchars((string) $seoLocale, ENT_QUOTES, "UTF-8") ?>">
  <meta property="og:type" content="<?= htmlspecialchars((string) $seoType, ENT_QUOTES, "UTF-8") ?>">
  <meta property="og:title" content="<?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, "UTF-8") ?>">
  <meta property="og:description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, "UTF-8") ?>">
  <meta property="og:url" content="<?= htmlspecialchars((string) $seoCanonical, ENT_QUOTES, "UTF-8") ?>">
  <meta property="og:image" content="<?= htmlspecialchars((string) $seoImage, ENT_QUOTES, "UTF-8") ?>">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, "UTF-8") ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, "UTF-8") ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars((string) $seoImage, ENT_QUOTES, "UTF-8") ?>">

  <link rel="stylesheet" href="/assets/css/melt.css">
  <link rel="stylesheet" href="/assets/css/site.css">
  <link rel="stylesheet" href="/assets/css/melt-nm.css?v=1">

  <?php
    $currentUri = (string) $request->getUri();
    $trUrl = preg_replace('/\/(tr|en)/', '/tr', $currentUri, 1);
    $enUrl = preg_replace('/\/(tr|en)/', '/en', $currentUri, 1);
  ?>
  <link rel="alternate" hreflang="tr" href="<?= htmlspecialchars((string) $trUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="alternate" hreflang="en" href="<?= htmlspecialchars((string) $enUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars((string) $trUrl, ENT_QUOTES, 'UTF-8') ?>">

  <?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json"><?= $jsonLd ?></script>
  <?php endif; ?>

  <script>window.__NMR_CONTEXT = <?= $contextJson ?? "{}" ?>;</script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>
  <script src="/assets/js/melt.js"></script>
  <script src="/assets/js/marked.min.js"></script>
  <script src="/assets/js/app-bundle.js?v=<?= time() ?>"></script>
  <script src="/assets/js/melt-front.js?v=1"></script>

  <?php if (!empty($siteConfig['integrations']['cloudflare_turnstile_site_key'] ?? '')): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <?php endif; ?>
</head>
<body theme="<?= htmlspecialchars($theme ?? "dark") ?>" class="melt-experience">
  <div class="melt-site-shell">
    <header class="melt-header">
      <div class="melt-header__bar">
        <div class="melt-header__brand">
          <button type="button" class="melt-mobile-toggle btn btn-outline btn-sm" id="meltMobileToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
          </button>
          <a href="<?= $meltUrl('') ?>" class="melt-brandmark">
            <?php if (!empty($siteConfig['site_logo'])): ?>
              <img src="<?= htmlspecialchars((string) $siteConfig['site_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
              <span><?= htmlspecialchars((string) ($siteConfig['site_abbreviation'] ?? 'NMR'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </a>
          <div class="melt-brandcopy">
            <a href="<?= $meltUrl('') ?>" class="melt-brandcopy__title"><?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?></a>
            <p class="melt-brandcopy__subtitle"><?= htmlspecialchars((string) ($siteConfig['site_description'] ?? 'Discover manga, webtoon, and novel content with a modern reading interface.'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>

        <nav class="melt-header__nav hide-lg">
          <a href="<?= $meltUrl('') ?>" class="melt-navlink">Home</a>
          <a href="<?= $meltUrl('/manga') ?>" class="melt-navlink">Manga</a>
          <a href="<?= $meltUrl('/novel') ?>" class="melt-navlink">Novel</a>
          <a href="<?= $meltUrl('/webtoon') ?>" class="melt-navlink">Webtoon</a>
          <a href="<?= $url('/blogs') ?>" class="melt-navlink">Blog</a>
        </nav>

        <div class="melt-header__actions">
          <form id="globalSearchForm" class="melt-search hide-md">
            <input type="text" id="globalSearchInput" class="form-item" placeholder="<?= $__t('search_placeholder') ?>" autocomplete="off">
            <button type="submit" class="melt-search__button" aria-label="Search">Search</button>
            <div id="searchSuggestions" class="melt-search__suggestions card hidden"></div>
          </form>

          <div class="melt-userdock" id="meltUserDock">
            <?php if (!empty($auth['is_logged_in'])): ?>
              <a href="<?= $url('/profile') ?>" class="melt-userdock__user"><?= htmlspecialchars((string) ($auth['username'] ?? 'reader'), ENT_QUOTES, 'UTF-8') ?></a>
              <?php if (!empty($auth['is_admin'])): ?>
                <a href="<?= $url('/admin') ?>" class="btn btn-sm btn-outline">Admin</a>
              <?php endif; ?>
              <a href="/logout" class="btn btn-sm btn-primary">Logout</a>
            <?php else: ?>
              <a href="#" class="btn btn-sm btn-outline" onclick="openModal('loginModal'); return false;"><?= $__t('login') ?></a>
              <a href="#" class="btn btn-sm btn-primary" onclick="openModal('registerModal'); return false;"><?= $__t('signup') ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="melt-mobile-panel" id="meltMobilePanel">
        <div class="melt-mobile-panel__head">
          <strong><?= htmlspecialchars((string) ($siteConfig['site_abbreviation'] ?? 'NMR'), ENT_QUOTES, 'UTF-8') ?></strong>
          <button type="button" class="btn btn-outline btn-sm" id="meltMobileClose">Close</button>
        </div>
        <form id="meltMobileSearchForm" class="melt-search melt-search--mobile">
          <input type="text" id="meltMobileSearchInput" class="form-item" placeholder="<?= $__t('search_placeholder') ?>" autocomplete="off">
          <button type="submit" class="melt-search__button">Go</button>
        </form>
        <nav class="melt-mobile-nav">
          <a href="<?= $meltUrl('') ?>">Home</a>
          <a href="<?= $meltUrl('/manga') ?>">Manga</a>
          <a href="<?= $meltUrl('/novel') ?>">Novel</a>
          <a href="<?= $meltUrl('/webtoon') ?>">Webtoon</a>
          <a href="<?= $url('/blogs') ?>">Blog</a>
        </nav>
      </div>
    </header>

    <main class="<?= htmlspecialchars((string) ($containerClass ?? 'melt-shell'), ENT_QUOTES, 'UTF-8') ?>">
      <?= $content ?? "" ?>
    </main>

    <footer class="melt-footer">
      <div class="melt-footer__grid">
        <section>
          <a href="<?= $meltUrl('') ?>" class="melt-footer__brand"><?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?></a>
          <p class="melt-footer__copy"><?= htmlspecialchars((string) ($siteConfig['site_description'] ?? 'Discover manga, webtoon, and novel content with a modern reading interface.'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
        <section>
          <h3>Browse</h3>
          <nav class="melt-footer__links">
            <a href="<?= $meltUrl('/manga') ?>">Manga</a>
            <a href="<?= $meltUrl('/manhwa') ?>">Manhwa</a>
            <a href="<?= $meltUrl('/webtoon') ?>">Webtoon</a>
            <a href="<?= $meltUrl('/novel') ?>">Novel</a>
          </nav>
        </section>
        <section>
          <h3><?= $__t('ui.genres_and_tags') ?></h3>
          <div class="melt-footer__chips">
            <?php
              $footerItems = array_merge(
                  array_map(static fn(array $item): array => $item + ['kind' => 'genre'], $footerGenres ?? []),
                  array_map(static fn(array $item): array => $item + ['kind' => 'tag'], $footerTags ?? [])
              );
              foreach (array_slice($footerItems, 0, 10) as $item):
                $target = '/' . ($item['kind'] === 'tag' ? 'tag' : 'genre') . '/' . (string) ($item['slug'] ?? '');
            ?>
              <a href="<?= $meltUrl($target) ?>" class="tag-chip"><?= htmlspecialchars((string) ($item['name'] ?? 'item'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
      <div class="melt-footer__bottom">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="melt-footer__langs">
          <a href="<?= htmlspecialchars((string) $trUrl, ENT_QUOTES, 'UTF-8') ?>">TR</a>
          <a href="<?= htmlspecialchars((string) $enUrl, ENT_QUOTES, 'UTF-8') ?>">EN</a>
        </div>
      </div>
    </footer>
  </div>

  <?php require __DIR__ . '/partials_modals.php'; ?>

  <div id="mainPopup" class="popup hidden">
    <span id="popupIcon"></span> <span id="popupMessage"></span>
  </div>
</body>
</html>
