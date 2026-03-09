<?php
$data = is_array($ssr_data ?? null) ? $ssr_data : [];
$isList = isset($data['blog_list']);
$blogList = $isList && is_array($data['blog_list']) ? $data['blog_list'] : [];
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

<?php if ($isList): ?>
  <h1>blogs</h1>
  <?php foreach ($blogList as $blog): ?>
    <article>
      <h2><a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($blog['title'] ?? '')) ?></a></h2>
      <p><?= htmlspecialchars((string) ($blog['author_username'] ?? '')) ?> | <?= htmlspecialchars((string) ($blog['approved_at'] ?? $blog['created_at'] ?? '')) ?></p>
      <p><?= htmlspecialchars(mb_substr(trim(strip_tags((string) ($blog['body'] ?? ''))), 0, 280)) ?></p>
    </article>
  <?php endforeach; ?>
<?php else: ?>
  <article>
    <header>
      <h1><?= htmlspecialchars((string) ($data['title'] ?? 'blog')) ?></h1>
      <p><?= htmlspecialchars((string) ($data['author_username'] ?? '')) ?> | <?= htmlspecialchars((string) ($data['approved_at'] ?? $data['created_at'] ?? '')) ?></p>
    </header>
    <pre><?= htmlspecialchars((string) ($data['body'] ?? '')) ?></pre>
  </article>
<?php endif; ?>
