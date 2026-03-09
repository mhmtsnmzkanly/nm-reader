<?php
$query = (string) ($q ?? '');
$items = is_array($results ?? null) ? $results : [];
?>

<h1>search</h1>
<p>Query: <?= htmlspecialchars($query) ?></p>

<?php if ($query === ''): ?>
  <p>Arama terimi girilmedi.</p>
<?php else: ?>
  <?php foreach ($items as $item): ?>
    <article>
      <h2><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></h2>
      <p><?= htmlspecialchars((string) ($item['type_path'] ?? $item['type'] ?? '')) ?></p>
      <p><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></p>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
