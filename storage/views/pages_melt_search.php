<?php
$query = (string) ($q ?? '');
$items = is_array($results ?? null) ? $results : [];
?>

<div class="melt-search-page">
  <section class="melt-page-head">
    <div>
      <span class="melt-kicker">Search</span>
      <h1><?= $query !== '' ? htmlspecialchars($query, ENT_QUOTES, 'UTF-8') : 'Search' ?></h1>
      <p><?= $query !== '' ? count($items) . ' result(s) rendered server-side for faster discovery.' : 'Use the search bar to find series, authors, or artists.' ?></p>
    </div>
  </section>

  <?php if ($query === ''): ?>
    <div class="melt-empty-state">
      <h2><?= $__t('search_placeholder') ?></h2>
      <p>Open a category or type at least two characters in the header search.</p>
    </div>
  <?php elseif ($items === []): ?>
    <div class="melt-empty-state">
      <h2>No results</h2>
      <p>Try a different title, author, or content type.</p>
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
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
