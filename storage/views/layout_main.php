<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($langCode ?? 'en'), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="keywords" content="<?= htmlspecialchars((string) $seoKeywords, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="<?= htmlspecialchars((string) $seoRobots, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="canonical" href="<?= htmlspecialchars((string) $seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars((string) $seoSiteName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:locale" content="<?= htmlspecialchars((string) $seoLocale, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="<?= htmlspecialchars((string) $seoType, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars((string) $seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars((string) $seoImage, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars((string) $seoImage, ENT_QUOTES, 'UTF-8') ?>">
  <?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json"><?= $jsonLd ?></script>
  <?php endif; ?>
</head>
<body>
  <header>
    <div>
      <a href="<?= $url('/') ?>"><?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?></a>
      <nav aria-label="Main">
          <a href="<?= $url('/') ?>">home</a>
          <a href="<?= $url('/blogs') ?>">blogs</a>
          <a href="<?= $url('/search') ?>">search</a>
          <a href="<?= $url('/profile') ?>">profile</a>
      </nav>
      <form action="<?= $url('/search') ?>" method="get">
          <input type="search" name="q" value="<?= htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="search">
          <button type="submit">go</button>
      </form>
    </div>
  </header>

  <main>
    <?= $content ?? '' ?>
  </main>
</body>
</html>
