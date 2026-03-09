<?php
$chapterData = is_array($chapter ?? null) ? $chapter : [];
$adjacent = is_array($chapterData['adjacent_chapters'] ?? null) ? $chapterData['adjacent_chapters'] : ['prev' => null, 'next' => null];
$chapterType = (string) ($chapterData['series_type'] ?? '');
$chapterSlug = (string) ($chapterData['series_slug'] ?? '');
$pages = is_array($chapterData['pages'] ?? null) ? $chapterData['pages'] : [];
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

<header class="section-block">
  <h1 class="page-title"><?= htmlspecialchars((string) ($chapterData['series_title'] ?? 'chapter')) ?> / chapter <?= htmlspecialchars((string) ($chapterData['chapter_number'] ?? '')) ?></h1>
  <p class="page-intro"><?= htmlspecialchars((string) ($chapterData['title'] ?? '')) ?></p>
  <div class="chip-row">
    <?php if (!empty($adjacent['prev'])): ?>
      <a class="chip" href="<?= $url('/' . $chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode((string) $adjacent['prev'])) ?>">prev</a>
    <?php endif; ?>
    <a class="chip" href="<?= $url('/' . $chapterType . '/' . $chapterSlug) ?>">content page</a>
    <?php if (!empty($adjacent['next'])): ?>
      <a class="chip" href="<?= $url('/' . $chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode((string) $adjacent['next'])) ?>">next</a>
    <?php endif; ?>
  </div>
</header>

<?php if (($chapterData['type'] ?? '') === 'text'): ?>
  <div class="panel reader-body"><?= htmlspecialchars((string) ($chapterData['body'] ?? '')) ?></div>
<?php else: ?>
  <div class="image-stack">
    <?php foreach ($pages as $page): ?>
      <div class="panel"><img src="<?= htmlspecialchars((string) $page) ?>" alt="chapter page"></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
