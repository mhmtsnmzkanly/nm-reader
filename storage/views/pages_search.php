<?php
$query = (string) ($q ?? '');
$items = is_array($results ?? null) ? $results : [];
?>

<h1 class="page-title">search</h1>
<p class="page-intro">Query: <?= htmlspecialchars($query) ?></p>

<?php if ($query === ''): ?>
  <div class="panel">Arama terimi girilmedi.</div>
<?php else: ?>
  <div class="stack">
    <?php foreach ($items as $item): ?>
      <article class="record">
        <h2><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></h2>
        <p class="record-meta"><?= htmlspecialchars((string) ($item['type_path'] ?? $item['type'] ?? '')) ?></p>
        <p><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
