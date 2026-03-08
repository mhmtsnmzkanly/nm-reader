<?php
$items = is_array($items ?? null) ? $items : [];
$latestItems = is_array($latest_items ?? null) ? $latest_items : [];
$heading = (string) ($page_heading ?? ucfirst((string) ($value ?? 'Browse')));
$meltBreadcrumbUrl = static function ($rawUrl) use ($langCode): string {
    $rawUrl = (string) ($rawUrl ?? '');
    if ($rawUrl === '' || str_contains($rawUrl, '/' . $langCode . '/melt/')) {
        return $rawUrl;
    }

    $prefix = '/' . $langCode;
    if ($rawUrl === $prefix) {
        return $prefix . '/melt';
    }

    if (str_starts_with($rawUrl, $prefix . '/')) {
        return $prefix . '/melt' . substr($rawUrl, strlen($prefix));
    }

    return $rawUrl;
};
?>

<?php if (!empty($breadcrumbs)): ?>
  <nav class="breadcrumb-nav mb-4" aria-label="breadcrumb">
    <ol class="nmr-breadcrumb mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">
      <?php foreach ($breadcrumbs as $i => $bc): ?>
        <li class="nmr-breadcrumb-item <?= $bc['url'] ? '' : 'active' ?>" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" <?= !$bc['url'] ? 'aria-current="page"' : '' ?>>
          <?php if ($bc['url']): ?>
            <a href="<?= htmlspecialchars($meltBreadcrumbUrl($bc['url']), ENT_QUOTES, 'UTF-8') ?>" itemprop="item"><span itemprop="name"><?= htmlspecialchars((string) $bc['title'], ENT_QUOTES, 'UTF-8') ?></span></a>
          <?php else: ?>
            <span itemprop="name"><?= htmlspecialchars((string) $bc['title'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <meta itemprop="position" content="<?= $i + 1 ?>">
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>
<?php endif; ?>

<div class="melt-listing-page">
  <section class="melt-page-head">
    <div>
      <span class="melt-kicker">Curated catalogue</span>
      <h1><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
      <p><?= htmlspecialchars((string) ($list_type ?? 'category'), ENT_QUOTES, 'UTF-8') ?> archive rendered server-side, with mobile-first browsing and quick reading entry points.</p>
    </div>
  </section>

  <?php if ($latestItems !== []): ?>
    <section class="melt-latest-strip">
      <div class="melt-section-card__head">
        <h2><?= $__t('ui.latest_chapters') ?></h2>
      </div>
      <div class="melt-latest-strip__track">
        <?php foreach ($latestItems as $chapter): ?>
          <a href="<?= $meltUrl('/' . (string) ($chapter['type_path'] ?? 'novel') . '/' . (string) ($chapter['series_slug'] ?? '') . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? '1'))) ?>" class="melt-latest-pill">
            <strong><?= htmlspecialchars((string) ($chapter['series_title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= $__t('chapters') ?> <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($items === []): ?>
    <div class="melt-empty-state">
      <h2><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
      <p>This catalogue is currently empty. Check another type, genre, or tag.</p>
    </div>
  <?php else: ?>
    <section class="melt-poster-grid">
      <?php foreach ($items as $item): ?>
        <?php $itemHref = $meltUrl('/' . (string) ($item['type_path'] ?? $item['type'] ?? 'novel') . '/' . (string) ($item['slug'] ?? '')); ?>
        <article class="melt-poster-card">
          <a href="<?= $itemHref ?>" class="melt-poster-card__cover">
            <img src="<?= htmlspecialchars((string) ($item['cover_image'] ?? '/assets/img/covers/one-piece.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="melt-badge"><?= htmlspecialchars((string) strtoupper((string) ($item['type_path'] ?? $item['type'] ?? 'novel')), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <div class="melt-poster-card__body">
            <a href="<?= $itemHref ?>" class="melt-poster-card__title"><?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></a>
            <div class="melt-poster-card__meta">
              <span><?= htmlspecialchars((string) ($item['author'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></span>
              <span>★ <?= htmlspecialchars((string) ($item['rating_avg'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="melt-poster-card__meta">
              <span><?= $__t('chapters') ?> <?= htmlspecialchars((string) ($item['chapter_count'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
              <span><?= htmlspecialchars((string) ($item['status'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
