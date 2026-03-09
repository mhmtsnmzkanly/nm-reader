<?php
$heading = (string) ($page_heading ?? $value ?? 'listing');
$items = is_array($items ?? null) ? $items : [];
$latestItems = is_array($latest_items ?? null) ? $latest_items : [];
?>

<?php if (!empty($breadcrumbs)): ?>
  <nav aria-label="breadcrumb">
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

<h1><?= htmlspecialchars($heading) ?></h1>
<p><?= htmlspecialchars((string) ($list_type ?? 'listing')) ?></p>

<section>
  <h2>items</h2>
  <?php foreach ($items as $item): ?>
    <article>
      <h3><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></h3>
      <p><?= htmlspecialchars((string) ($item['type_path'] ?? $item['type'] ?? '')) ?></p>
      <p><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></p>
    </article>
  <?php endforeach; ?>
</section>

<?php if ($latestItems !== []): ?>
  <section>
    <h2>latest chapters</h2>
    <ul>
      <?php foreach ($latestItems as $chapter): ?>
        <li>
          <a href="<?= $url('/' . (string) ($chapter['type_path'] ?? 'novel') . '/' . (string) ($chapter['slug'] ?? '') . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? ''))) ?>">
            <?= htmlspecialchars((string) ($chapter['series_title'] ?? '')) ?> / chapter <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? '')) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
