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
  <meta name="twitter:image" content="<?= htmlspecialchars(
      $seoImage ?? "",
      ENT_QUOTES,
      "UTF-8",
  ) ?>">
  <link rel="stylesheet" href="/assets/css/melt.css">
  <link rel="stylesheet" href="/assets/css/site.css">
  
  <?php
    $currentUri = (string)$request->getUri();
    $trUrl = preg_replace('/\/(tr|en)/', '/tr', $currentUri, 1);
    $enUrl = preg_replace('/\/(tr|en)/', '/en', $currentUri, 1);
  ?>
  <link rel="alternate" hreflang="tr" href="<?= $trUrl ?>">
  <link rel="alternate" hreflang="en" href="<?= $enUrl ?>">
  <link rel="alternate" hreflang="x-default" href="<?= $trUrl ?>">

  <?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json"><?= $jsonLd ?></script>
  <?php endif; ?>
  <script>window.__NMR_CONTEXT = <?= $contextJson ?? "{}" ?>;</script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
  <script src="/assets/js/melt.js"></script>
  <script src="/assets/js/marked.min.js"></script>
  <script src="/assets/js/app-bundle.js?v=<?= time() ?>"></script>
  
  <?php if (!empty($siteConfig['integrations']['cloudflare_turnstile_site_key'] ?? '')): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <?php endif; ?>
  
  <?php if (!empty($siteConfig['integrations']['google_analytics_id'] ?? '')): ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($siteConfig['integrations']['google_analytics_id']) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($siteConfig['integrations']['google_analytics_id']) ?>');
    </script>
  <?php endif; ?>

  <?php if (!empty($scripts)):
      foreach ($scripts as $script): ?>
    <script src="<?= $script ?>"></script>
  <?php endforeach;
  endif; ?>
</head>
<body theme="<?= htmlspecialchars($theme ?? "dark") ?>">
  <app>
    <header>
      <div class="container flex items-center justify-between gap-4">
        <a href="<?= $url('/') ?>" class="nav-link logo-text">
          <?php if (!empty($siteConfig['site_logo'])): ?>
            <img src="<?= htmlspecialchars((string) $siteConfig['site_logo'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($siteConfig['site_abbreviation'] ?? 'NMR'), ENT_QUOTES, 'UTF-8') ?>" class="site-logo-img">
          <?php else: ?>
            <?= htmlspecialchars((string) ($siteConfig['site_abbreviation'] ?? 'NMR'), ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
        </a>

        <div class="flex-grow max-w-md hide-md">
          <form id="globalSearchForm" class="position-relative">
            <input type="text" id="globalSearchInput" class="form-item pr-5" placeholder="<?= $__t('search_placeholder') ?>" autocomplete="off">
            <button type="submit" class="search-icon-btn">🔍</button>
            <div id="searchSuggestions" class="search-suggestions-dropdown card hidden"></div>
          </form>
        </div>

        <div class="flex items-center gap-4">
                              <menu id="mainMenu" class="flex items-center gap-2">
                                <div class="mobile-menu-header">
                                  <a href="<?= $url('/') ?>" class="nav-link logo-text"><?= htmlspecialchars((string) ($siteConfig['site_abbreviation'] ?? 'NMR'), ENT_QUOTES, 'UTF-8') ?></a>
                                  <button class="mobile-close" onclick="NMR.closeMenu()">✕</button>
                                </div>
                                
                                            <div class="dropdown">
                                              <button class="nav-link dropdown-toggle btn-none">📚 <?= $__t('library') ?></button>
                                              <div class="dropdown-menu card p-2">                                    <a href="<?= $url('/light-novel') ?>" class="dropdown-item">📖 Light Novel</a>
                                    <a href="<?= $url('/web-novel') ?>" class="dropdown-item">🌐 Web Novel</a>
                                    <a href="<?= $url('/novel') ?>" class="dropdown-item">📝 Novel</a>
                                    <hr class="my-1 border-0 border-t opacity-10">
                                    <a href="<?= $url('/manga') ?>" class="dropdown-item">🎨 Manga</a>
                                    <a href="<?= $url('/manhua') ?>" class="dropdown-item">⛩️ Manhua</a>
                                    <a href="<?= $url('/manhwa') ?>" class="dropdown-item">🇰🇷 Manhwa</a>
                                    <a href="<?= $url('/webtoon') ?>" class="dropdown-item">📱 Webtoon</a>
                                  </div>
                                </div>
                                <a href="<?= $url('/blogs') ?>" class="nav-link">✍️ <?= $__t('blogs') ?></a>
                                
                                <span class="header-divider hide-md"></span>

                                <div class="dropdown">
                                  <button class="nav-link dropdown-toggle btn-none">🌐 <?= strtoupper($langCode ?? 'TR') ?></button>
                                  <div class="dropdown-menu card p-1 min-w-80">
                                    <a class="dropdown-item py-1" href="<?= $trUrl ?>" onclick="NMR.changeLanguage('tr');">🇹🇷 TR</a>
                                    <a class="dropdown-item py-1" href="<?= $enUrl ?>" onclick="NMR.changeLanguage('en');">🇺🇸 EN</a>
                                  </div>
                                </div>

                                <span class="header-divider hide-md"></span>
                                
                                <div id="headerAuthLinks" class="flex items-center gap-2">
                                  <!-- JS will fill this based on auth state -->
                                  <a href="#" class="nav-link" onclick="openModal('loginModal');return false;"><?= $__t('login') ?></a>
                                  <a href="#" class="btn btn-sm btn-primary" onclick="openModal('registerModal');return false;"><?= $__t('signup') ?></a>
                                </div>
                              </menu>
                    
                              <button class="mobile-toggle" id="mobileToggle" onclick="NMR.openMenu()">☰</button>        </div>
      </div>
    </header>

    <main class="<?= $containerClass ?? "container" ?> pt-5 pb-4">
      <?= $content ?? "" ?>
    </main>

    <footer>
      <div class="container">
        <div class="grid grid-3 gap-4 mb-5">
          <div class="col-span-1">
            <h3 class="mb-3"><?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="text-muted text-sm leading-relaxed">
              <?= htmlspecialchars((string) ($siteConfig['site_description'] ?? 'Discover manga, webtoon, and novel content with a modern reading interface and a global reader community.'), ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>
          <div>
            <h4 class="mb-3">🔥 <?= $__t('ui.popular_content') ?></h4>
            <nav class="flex-col gap-1 text-sm" id="footerPopular"></nav>
          </div>
          <div>
            <h4 class="mb-3">🆕 <?= $__t('ui.latest_chapters') ?></h4>
            <nav class="flex-col gap-1 text-sm" id="footerLatest"></nav>
          </div>
        </div>

        <div class="border-t pt-4">
          <div class="text-center text-xs text-muted mb-3 font-bold uppercase tracking-widest">📚 <?= $__t('ui.genres_and_tags') ?></div>
          <div class="flex flex-wrap justify-center gap-1" id="footerTags">
            <?php
            $combined = array_merge(
                array_map(
                    fn($g) => [
                        "n" => $g["name"],
                        "s" => $g["slug"],
                        "t" => "genre",
                    ],
                    $footerGenres ?? [],
                ),
                array_map(
                    fn($t) => [
                        "n" => $t["name"],
                        "s" => $t["slug"],
                        "t" => "tag",
                    ],
                    $footerTags ?? [],
                ),
            );
            shuffle($combined);
            foreach ($combined as $item):

                $colorKey = $item["t"] === "genre" ? "success" : "primary";
                $color = "var(--$colorKey)";
                $itemPath = $item["t"] === "genre" ? "/genre/{$item["s"]}" : "/tag/{$item["s"]}";
                $localizedItemUrl = $url($itemPath);
                ?>
              <a href="<?= $localizedItemUrl ?>" class="tag-chip" style="--chip-color: <?= $color ?>"><?= htmlspecialchars($item["n"]) ?></a>
            <?php
            endforeach;
            ?>
          </div>
        </div>
      </div>
    </footer>

    <?php require __DIR__ . '/partials_modals.php'; ?>
  </app>

  <div id="mainPopup" class="popup hidden">
    <span id="popupIcon"></span> <span id="popupMessage"></span>
  </div>
</body>
</html>
