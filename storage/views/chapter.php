<?php
$chapterData = is_array($chapter ?? null) ? $chapter : [];
$adjacent = is_array($chapterData['adjacent_chapters'] ?? null) ? $chapterData['adjacent_chapters'] : ['prev' => null, 'next' => null];
$chapterType = (string) ($chapterData['series_type'] ?? '');
$chapterSlug = (string) ($chapterData['series_slug'] ?? '');
$pages = is_array($chapterData['pages'] ?? null) ? $chapterData['pages'] : [];
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

<h1><?= htmlspecialchars((string) ($chapterData['series_title'] ?? 'chapter')) ?> / chapter <?= htmlspecialchars((string) ($chapterData['chapter_number'] ?? '')) ?></h1>
<p><?= htmlspecialchars((string) ($chapterData['title'] ?? '')) ?></p>
<p>
  <?php if (!empty($adjacent['prev'])): ?>
    <a href="<?= $url('/' . $chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode((string) $adjacent['prev'])) ?>">prev</a>
  <?php endif; ?>
  <a href="<?= $url('/' . $chapterType . '/' . $chapterSlug) ?>">content page</a>
  <?php if (!empty($adjacent['next'])): ?>
    <a href="<?= $url('/' . $chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode((string) $adjacent['next'])) ?>">next</a>
  <?php endif; ?>
</p>

<?php if (($chapterData['type'] ?? '') === 'text'): ?>
  <pre><?= htmlspecialchars((string) ($chapterData['body'] ?? '')) ?></pre>
<?php else: ?>
  <?php foreach ($pages as $page): ?>
    <p><img src="<?= htmlspecialchars((string) $page) ?>" alt="chapter page"></p>
  <?php endforeach; ?>
<?php endif; ?>
