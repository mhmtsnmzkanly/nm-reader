<?php
$data = is_array($ssr_data ?? null) ? $ssr_data : [];
$isList = isset($data['blog_list']);
$blogList = $isList && is_array($data['blog_list']) ? $data['blog_list'] : [];
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

<?php if ($isList): ?>
  <h1 class="page-title">blogs</h1>
  <div class="stack">
    <?php foreach ($blogList as $blog): ?>
      <article class="record">
        <h2><a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($blog['title'] ?? '')) ?></a></h2>
        <p class="record-meta"><?= htmlspecialchars((string) ($blog['author_username'] ?? '')) ?> | <?= htmlspecialchars((string) ($blog['approved_at'] ?? $blog['created_at'] ?? '')) ?></p>
        <p><?= htmlspecialchars(mb_substr(trim(strip_tags((string) ($blog['body'] ?? ''))), 0, 280)) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <article class="stack">
    <header>
      <h1 class="page-title"><?= htmlspecialchars((string) ($data['title'] ?? 'blog')) ?></h1>
      <p class="page-intro"><?= htmlspecialchars((string) ($data['author_username'] ?? '')) ?> | <?= htmlspecialchars((string) ($data['approved_at'] ?? $data['created_at'] ?? '')) ?></p>
    </header>
    <div class="panel reader-body"><?= htmlspecialchars((string) ($data['body'] ?? '')) ?></div>
  </article>
<?php endif; ?>
