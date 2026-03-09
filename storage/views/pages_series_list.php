<?php
$heading = (string) ($page_heading ?? $value ?? 'listing');
$items = is_array($items ?? null) ? $items : [];
$latestItems = is_array($latest_items ?? null) ? $latest_items : [];
?>

<?php if (!empty($breadcrumbs)): ?>
  <nav class="breadcrumbs" aria-label="breadcrumb">
    <ol>
      <?php foreach ($breadcrumbs as $crumb): ?>
        <li>
          <?php if (!empty($crumb['url'])): ?>
            <a href="<?= htmlspecialchars((string) $crumb['url']) ?>"><?= htmlspecialchars((string) $crumb['title']) ?></a>
          <?php else: ?>
            <?= htmlspecialchars((string) $crumb['title']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>
<?php endif; ?>

<h1 class="page-title"><?= htmlspecialchars($heading) ?></h1>
<p class="page-intro"><?= htmlspecialchars((string) ($list_type ?? 'listing')) ?></p>

<section class="split">
  <div class="section-block">
    <h2 class="section-title">items</h2>
    <div class="stack">
      <?php foreach ($items as $item): ?>
        <article class="record">
          <h3><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></h3>
          <p class="record-meta"><?= htmlspecialchars((string) ($item['type_path'] ?? $item['type'] ?? '')) ?></p>
          <p><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($latestItems !== []): ?>
    <aside class="section-block">
      <h2 class="section-title">latest chapters</h2>
      <ul class="plain-list">
        <?php foreach ($latestItems as $chapter): ?>
          <li>
            <a href="<?= $url('/' . (string) ($chapter['type_path'] ?? 'novel') . '/' . (string) ($chapter['slug'] ?? '') . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? ''))) ?>">
              <?= htmlspecialchars((string) ($chapter['series_title'] ?? '')) ?> / chapter <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? '')) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>
  <?php endif; ?>
</section>
