<?php
$content = is_array($ssr_data ?? null) ? $ssr_data : [];
$recommendations = is_array($recommended_items ?? null) ? $recommended_items : [];
$contentType = (string) ($type ?? '');
$contentSlug = (string) ($slug ?? '');
$startChapter = (string) ($start_chapter_number ?? '');
$coverImage = (string) ($content['cover_image'] ?? '/assets/img/covers/one-piece.jpg');
$genres = is_array($content['series_genres'] ?? null) ? $content['series_genres'] : [];
$tags = is_array($content['series_tags'] ?? null) ? $content['series_tags'] : [];
$chips = [];
foreach ($genres as $genre) {
    $chips[] = ['kind' => 'genre', 'item' => $genre];
}
foreach ($tags as $tag) {
    $chips[] = ['kind' => 'tag', 'item' => $tag];
}
$meltBreadcrumbUrl = static function ($rawUrl) use ($langCode): string {
    $rawUrl = (string) ($rawUrl ?? '');
    if ($rawUrl === '' || str_contains($rawUrl, '/' . $langCode . '/melt/')) {
        return $rawUrl;
    }

    $prefix = '/' . $langCode;
    if ($rawUrl === $prefix) {
        return $prefix . '/melt';
    }

    if (str_starts_with($rawUrl, $prefix . '/')) {
        return $prefix . '/melt' . substr($rawUrl, strlen($prefix));
    }

    return $rawUrl;
};
?>

<?php if (!empty($breadcrumbs)): ?>
  <nav class="breadcrumb-nav mb-4" aria-label="breadcrumb">
    <ol class="nmr-breadcrumb mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">
      <?php foreach ($breadcrumbs as $i => $bc): ?>
        <li class="nmr-breadcrumb-item <?= $bc['url'] ? '' : 'active' ?>" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" <?= !$bc['url'] ? 'aria-current="page"' : '' ?>>
          <?php if ($bc['url']): ?>
            <a href="<?= htmlspecialchars($meltBreadcrumbUrl($bc['url']), ENT_QUOTES, 'UTF-8') ?>" itemprop="item"><span itemprop="name"><?= htmlspecialchars((string) $bc['title'], ENT_QUOTES, 'UTF-8') ?></span></a>
          <?php else: ?>
            <span itemprop="name"><?= htmlspecialchars((string) $bc['title'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <meta itemprop="position" content="<?= $i + 1 ?>">
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>
<?php endif; ?>

<?php if ($content === []): ?>
  <div class="melt-empty-state">
    <h2><?= $__t('content_not_found') ?></h2>
    <p>This series could not be rendered in the Melt interface.</p>
  </div>
<?php else: ?>
<div class="melt-detail-page" id="meltContentPage" data-type="<?= htmlspecialchars($contentType, ENT_QUOTES, 'UTF-8') ?>" data-slug="<?= htmlspecialchars($contentSlug, ENT_QUOTES, 'UTF-8') ?>">
  <section class="melt-detail-hero">
    <div class="melt-detail-hero__backdrop" style="background-image:url('<?= htmlspecialchars($coverImage, ENT_QUOTES, 'UTF-8') ?>')"></div>
    <div class="melt-detail-hero__overlay"></div>
    <div class="melt-detail-hero__content">
      <div class="melt-detail-hero__poster">
        <img src="<?= htmlspecialchars($coverImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($content['title'] ?? 'Content'), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="melt-detail-hero__copy">
        <div class="melt-detail-hero__eyebrow">
          <span class="melt-badge"><?= htmlspecialchars((string) strtoupper($contentType), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="melt-score-pill">★ <?= htmlspecialchars((string) ($content['rating_avg'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="melt-score-pill"><?= $__t('chapters') ?> <?= htmlspecialchars((string) ($content['chapter_count'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <h1><?= htmlspecialchars((string) ($content['title'] ?? 'Content'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars((string) ($content['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

        <div class="melt-meta-grid">
          <div><span><?= $__t('author') ?></span><strong><?= htmlspecialchars((string) ($content['author'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div><span><?= $__t('artist') ?></span><strong><?= htmlspecialchars((string) ($content['artist'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div><span><?= $__t('status') ?></span><strong><?= htmlspecialchars((string) ($content['status'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div><span><?= $__t('release') ?></span><strong><?= htmlspecialchars((string) ($content['release_year'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></strong></div>
        </div>

        <div class="melt-action-row">
          <?php if (!empty($content['reading_progress'])): ?>
            <a href="<?= $meltUrl('/' . $contentType . '/' . $contentSlug . '/chapter/' . rawurlencode((string) ($content['reading_progress']['chapter_number'] ?? '1'))) ?>" class="btn btn-primary"><?= $__t('continue_reading') ?></a>
          <?php else: ?>
            <a href="<?= $startChapter !== '' ? $meltUrl('/' . $contentType . '/' . $contentSlug . '/chapter/' . rawurlencode($startChapter)) : '#' ?>" class="btn btn-primary<?= $startChapter === '' ? ' disabled' : '' ?>"><?= $__t('start_reading') ?></a>
          <?php endif; ?>
          <button id="followBtn" class="btn btn-outline" data-active="<?= !empty($content['is_followed']) ? 'true' : 'false' ?>"><?= !empty($content['is_followed']) ? $__t('unfollow') : $__t('follow') ?></button>
          <div class="dropdown">
            <button class="btn btn-outline dropdown-toggle"><?= $__t('rate') ?></button>
            <div class="dropdown-menu card p-2 min-w-120">
              <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
                <button class="btn-none dropdown-item rate-opt" data-val="<?= $n ?>">★ <?= $n ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if (($content['requires_series_unlock'] ?? false) === true): ?>
            <button type="button" class="btn btn-outline" id="meltUnlockSeriesBtn" data-price="<?= (int) ($content['series_unlock_price'] ?? 0) ?>">Unlock <?= (int) ($content['series_unlock_price'] ?? 0) ?>c</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="melt-detail-grid">
    <div class="melt-detail-grid__main">
      <?php if (!empty($content['alternative_titles'])): ?>
        <div class="melt-section-card">
          <div class="melt-section-card__head"><h2>Alt titles</h2></div>
          <p class="melt-alt-copy"><?= htmlspecialchars((string) $content['alternative_titles'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      <?php endif; ?>

      <div class="melt-section-card">
        <div class="melt-section-card__head">
          <h2><?= $__t('summary') ?></h2>
        </div>
        <div class="markdown-body melt-summary-body" id="contentDescription"><?= htmlspecialchars((string) ($content['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($chips !== []): ?>
          <div class="melt-chip-cloud">
            <?php foreach ($chips as $chipData): ?>
              <?php
                $chip = (array) ($chipData['item'] ?? []);
                $config = is_array($chip['ui_config'] ?? null) ? $chip['ui_config'] : [];
                $rawColor = (string) ($config['color'] ?? ($chipData['kind'] === 'tag' ? 'primary' : 'success'));
                $chipColor = (str_starts_with($rawColor, '#') || str_starts_with($rawColor, 'rgb')) ? $rawColor : 'var(--' . $rawColor . ')';
                $href = $meltUrl('/' . $chipData['kind'] . '/' . (string) ($chip['slug'] ?? ''));
              ?>
              <a href="<?= $href ?>" class="tag-chip" style="--chip-color: <?= htmlspecialchars($chipColor, ENT_QUOTES, 'UTF-8') ?>;">
                <?php if (!empty($config['icon'])): ?>
                  <i class="bi <?= htmlspecialchars((string) $config['icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                <?php endif; ?>
                <?= htmlspecialchars((string) ($chip['name'] ?? 'item'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="melt-section-card">
        <div class="melt-section-card__head">
          <h2><?= $__t('chapters') ?></h2>
        </div>
        <div id="chapterListTarget" class="melt-chapter-list">
          <div class="melt-loading-row"><?= $__t('loading') ?></div>
        </div>
      </div>

      <div class="melt-section-card" id="commentsTarget">
        <div class="melt-section-card__head">
          <h2><?= $__t('comments') ?></h2>
          <span id="commentsBadgeCount" class="melt-score-pill">0</span>
        </div>
        <form id="contentCommentForm" class="melt-comment-form">
          <textarea id="contentCommentInput" class="form-item" rows="4" placeholder="<?= $__t('comments') ?>..."></textarea>
          <div id="commentPreview" class="melt-comment-preview markdown-body">
            <span><?= $__t('preview_will_appear') ?></span>
          </div>
          <div class="melt-comment-form__actions">
            <button type="submit" class="btn btn-primary"><?= $__t('post_comment') ?></button>
          </div>
        </form>
        <div id="contentCommentsList" class="melt-comments-list">
          <div class="melt-loading-row"><?= $__t('loading') ?></div>
        </div>
      </div>
    </div>

    <aside class="melt-detail-grid__side">
      <div class="melt-side-card">
        <div class="melt-side-card__head"><h3>Quick facts</h3></div>
        <dl class="melt-facts">
          <div><dt><?= $__t('country') ?></dt><dd><?= htmlspecialchars((string) ($content['country'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt><?= $__t('created') ?></dt><dd><?= htmlspecialchars(explode(' ', (string) ($content['created_at'] ?? ''))[0] ?? $__t('unknown'), ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Rating count</dt><dd><?= htmlspecialchars((string) ($content['rating_count'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Comments</dt><dd><?= htmlspecialchars((string) ($content['comment_count'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></dd></div>
        </dl>
      </div>

      <?php if ($recommendations !== []): ?>
        <div class="melt-side-card">
          <div class="melt-side-card__head"><h3>Recommended</h3></div>
          <div class="melt-related-grid">
            <?php foreach ($recommendations as $item): ?>
              <?php $href = $meltUrl('/' . (string) ($item['type_path'] ?? $item['type'] ?? 'novel') . '/' . (string) ($item['slug'] ?? '')); ?>
              <a href="<?= $href ?>" class="melt-related-card">
                <img src="<?= htmlspecialchars((string) ($item['cover_image'] ?? '/assets/img/covers/one-piece.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?>">
                <div>
                  <strong><?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></strong>
                  <span><?= $__t('chapters') ?> <?= htmlspecialchars((string) ($item['chapter_count'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </aside>
  </section>
</div>
<?php endif; ?>
