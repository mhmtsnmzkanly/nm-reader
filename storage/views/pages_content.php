<?php
$content = is_array($ssr_data ?? null) ? $ssr_data : [];
$chapterItems = is_array($chapters ?? null) ? $chapters : [];
$genres = is_array($content['series_genres'] ?? null) ? $content['series_genres'] : [];
$tags = is_array($content['series_tags'] ?? null) ? $content['series_tags'] : [];
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

<section class="split">
  <div class="stack">
    <header>
      <h1 class="page-title"><?= htmlspecialchars((string) ($content['title'] ?? 'content')) ?></h1>
      <p class="page-intro"><?= htmlspecialchars((string) ($content['type_path'] ?? $content['type'] ?? '')) ?></p>
    </header>

    <div class="panel">
      <p><?= htmlspecialchars((string) ($content['description'] ?? '')) ?></p>
    </div>

    <?php if ($chapterItems !== []): ?>
      <section class="section-block">
        <h2 class="section-title">chapters</h2>
        <ol class="plain-list">
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
  </div>

  <aside class="stack">
    <div class="meta-grid">
      <div><strong>author</strong><br><?= htmlspecialchars((string) ($content['author'] ?? '-')) ?></div>
      <div><strong>artist</strong><br><?= htmlspecialchars((string) ($content['artist'] ?? '-')) ?></div>
      <div><strong>status</strong><br><?= htmlspecialchars((string) ($content['status'] ?? '-')) ?></div>
      <div><strong>release year</strong><br><?= htmlspecialchars((string) ($content['release_year'] ?? '-')) ?></div>
      <div><strong>rating</strong><br><?= htmlspecialchars((string) ($content['rating_avg'] ?? '-')) ?></div>
      <div><strong>chapter count</strong><br><?= htmlspecialchars((string) ($content['chapter_count'] ?? '0')) ?></div>
    </div>

    <?php if ($genres !== []): ?>
      <section class="section-block">
        <h2 class="section-title">genres</h2>
        <div class="chip-row">
          <?php foreach ($genres as $genre): ?>
            <a class="chip" href="<?= $url('/genre/' . (string) ($genre['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($genre['name'] ?? '')) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($tags !== []): ?>
      <section class="section-block">
        <h2 class="section-title">tags</h2>
        <div class="chip-row">
          <?php foreach ($tags as $tag): ?>
            <a class="chip" href="<?= $url('/tag/' . (string) ($tag['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($tag['name'] ?? '')) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </aside>
</section>
