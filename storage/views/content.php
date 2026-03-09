<?php
$content = is_array($ssr_data ?? null) ? $ssr_data : [];
$chapterItems = is_array($chapters ?? null) ? $chapters : [];
$genres = is_array($content['series_genres'] ?? null) ? $content['series_genres'] : [];
$tags = is_array($content['series_tags'] ?? null) ? $content['series_tags'] : [];
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

<h1><?= htmlspecialchars((string) ($content['title'] ?? 'content')) ?></h1>
<p><?= htmlspecialchars((string) ($content['type_path'] ?? $content['type'] ?? '')) ?></p>
<p><?= htmlspecialchars((string) ($content['description'] ?? '')) ?></p>

<section>
  <h2>metadata</h2>
  <dl>
    <dt>author</dt><dd><?= htmlspecialchars((string) ($content['author'] ?? '-')) ?></dd>
    <dt>artist</dt><dd><?= htmlspecialchars((string) ($content['artist'] ?? '-')) ?></dd>
    <dt>status</dt><dd><?= htmlspecialchars((string) ($content['status'] ?? '-')) ?></dd>
    <dt>release year</dt><dd><?= htmlspecialchars((string) ($content['release_year'] ?? '-')) ?></dd>
    <dt>rating</dt><dd><?= htmlspecialchars((string) ($content['rating_avg'] ?? '-')) ?></dd>
    <dt>chapter count</dt><dd><?= htmlspecialchars((string) ($content['chapter_count'] ?? '0')) ?></dd>
  </dl>
</section>

<?php if ($genres !== []): ?>
  <section>
    <h2>genres</h2>
    <p>
      <?php foreach ($genres as $genre): ?>
        <a href="<?= $url('/genre/' . (string) ($genre['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($genre['name'] ?? '')) ?></a>
      <?php endforeach; ?>
    </p>
  </section>
<?php endif; ?>

<?php if ($tags !== []): ?>
  <section>
    <h2>tags</h2>
    <p>
      <?php foreach ($tags as $tag): ?>
        <a href="<?= $url('/tag/' . (string) ($tag['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($tag['name'] ?? '')) ?></a>
      <?php endforeach; ?>
    </p>
  </section>
<?php endif; ?>

<?php if ($chapterItems !== []): ?>
  <section>
    <h2>chapters</h2>
    <ol>
      <?php foreach ($chapterItems as $chapter): ?>
        <li>
          <a href="<?= $url('/' . (string) ($content['type_path'] ?? $type ?? 'novel') . '/' . (string) ($content['slug'] ?? $slug ?? '') . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? ''))) ?>">
            chapter <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? '')) ?>
            <?php if (!empty($chapter['title'])): ?>
              - <?= htmlspecialchars((string) $chapter['title']) ?>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php endif; ?>
