<?php
$content = is_array($ssr_data ?? null) ? $ssr_data : [];
$startChapter = isset($start_chapter_number) ? (string) $start_chapter_number : '';
$contentType = (string) ($type ?? '');
$contentSlug = (string) ($slug ?? '');
$genres = is_array($content['series_genres'] ?? null) ? $content['series_genres'] : [];
$tags = is_array($content['series_tags'] ?? null) ? $content['series_tags'] : [];

$chipItems = [];
foreach ($genres as $g) {
    $cfg = $g['ui_config'] ?? [];
    $color = $cfg['color'] ?? 'success';
    $colorValue = (str_starts_with($color, '#') || str_starts_with($color, 'rgb')) ? $color : "var(--$color)";
    $chipItems[] = [
        'name' => (string) $g['name'],
        'url' => $url('/genre/' . (string)($g['slug'] ?? '')),
        'color' => $colorValue,
        'icon' => $cfg['icon'] ?? null,
    ];
}
foreach ($tags as $t) {
    $cfg = $t['ui_config'] ?? [];
    $color = $cfg['color'] ?? 'primary';
    $colorValue = (str_starts_with($color, '#') || str_starts_with($color, 'rgb')) ? $color : "var(--$color)";
    $chipItems[] = [
        'name' => (string) $t['name'],
        'url' => $url('/tag/' . (string)($t['slug'] ?? '')),
        'color' => $colorValue,
        'icon' => $cfg['icon'] ?? null,
    ];
}
shuffle($chipItems);

$coverImage = htmlspecialchars((string) ($content['cover_image'] ?? '/assets/img/covers/one-piece.jpg'));
?>

<?php if ($content === []): ?>
    <div id="contentDetailTarget">
        <div class="card p-4 text-center text-danger"><?= $__t('content_not_found') ?></div>
    </div>
<?php else: ?>
    <!-- Hero Section -->
    <div class="content-hero-wrapper">
        <div class="hero-backdrop" style="background-image: url('<?= $coverImage ?>')"></div>
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <div class="hero-side">
                    <div class="hero-cover-container">
                        <img
                            src="<?= $coverImage ?>"
                            onerror="this.onerror=null;this.src='/assets/img/covers/one-piece.jpg';"
                            onload="this.classList.add('loaded')"
                            class="hero-cover rounded-lg shadow-lg"
                            alt="<?= htmlspecialchars((string) ($content['title'] ?? 'Content')) ?>"
                            loading="lazy"
                        >
                    </div>
                </div>
                <div class="hero-main">
                    <div class="hero-badges">
                        <span class="badge bg-primary px-3 py-2 rounded-md uppercase badge-lg"><?= htmlspecialchars((string) ($content['type'] ?? '')) ?></span>
                        <span class="meta-pill">⭐ <?= htmlspecialchars((string) ($content['rating_avg'] ?? '-')) ?></span>
                    </div>
                    <h1 class="hero-title"><?= htmlspecialchars((string) ($content['title'] ?? '')) ?></h1>
                    <?php if (!empty($content['alternative_titles'])): ?>
                        <div class="mb-3 opacity-70 text-sm">
                            <i class="bi bi-translate me-1"></i>
                            <?= htmlspecialchars((string) $content['alternative_titles']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="hero-meta-strip">
                        <div class="flex items-center gap-2">
                            <span class="text-white-50 opacity-70"><?= $__t('author') ?>:</span>
                            <?php if (!empty($content['author'])): ?>
                                <a href="<?= $url('/search?q=' . urlencode((string)$content['author'])) ?>" class="font-bold text-white hover-primary transition-all">
                                    <?= htmlspecialchars((string)$content['author']) ?>
                                </a>
                            <?php else: ?>
                                <span class="font-bold"><?= $__t('unknown') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-white-50 opacity-70"><?= $__t('status') ?>:</span>
                            <span class="text-success font-bold"><?= !empty($content['status']) ? htmlspecialchars((string)$content['status']) : $__t('unknown') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Detail -->
    <div id="contentDetailTarget">
        <div class="content-info-grid container">
            <!-- Sidebar with Actions -->
            <div class="content-sidebar">
                <div class="sidebar-actions">
                    <a
                        id="startReadingBtn"
                        href="<?= $startChapter !== '' ? $url('/' . $contentType . '/' . $contentSlug . '/chapter/' . rawurlencode($startChapter)) : '#' ?>"
                        class="btn btn-primary w-100 justify-center py-3 fs-1-2<?= $startChapter === '' ? ' disabled' : '' ?>"
                        <?= $startChapter === '' ? 'aria-disabled="true"' : '' ?>
                    >
                        🚀 <?= $__t('start_reading') ?>
                    </a>
                    
                    <div class="flex gap-2">
                        <button id="followBtn" class="btn btn-outline flex-grow py-2">🤍 <?= $__t('follow') ?></button>
                        <div class="dropdown flex-grow">
                            <button class="btn btn-outline w-100 py-2 dropdown-toggle">⭐ <?= $__t('rate') ?></button>
                            <div class="dropdown-menu card p-2 min-w-120">
                                <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
                                    <button class="btn-none dropdown-item rate-opt" data-val="<?= $n ?>">⭐ <?= $n ?> <?= $__t('stars') ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body with Info -->
            <div class="content-body-main">
                <div class="main-desc-card">
                    <div class="desc-title"><i class="bi bi-journal-text me-2"></i> <?= $__t('summary') ?></div>
                    <div class="desc-text markdown-body" id="contentDescription"><?= htmlspecialchars((string) ($content['description'] ?? '')) ?></div>
                    
                    <div class="flex flex-wrap gap-2 mt-4">
                        <?php foreach ($chipItems as $chip): ?>
                            <a href="<?= htmlspecialchars($chip['url'], ENT_QUOTES, 'UTF-8') ?>"
                               class="tag-chip"
                               style="--chip-color: <?= $chip['color'] ?>;"
                            >
                                <?php if (!empty($chip['icon'])): ?>
                                    <i class="bi <?= htmlspecialchars($chip['icon']) ?> me-1"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($chip['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="info-stat-grid">
                        <div class="stat-item">
                            <span class="stat-label"><i class="bi bi-journals me-1"></i> <?= $__t('chapters') ?></span>
                            <span class="stat-value"><?= !empty($content['chapter_count']) ? htmlspecialchars((string)$content['chapter_count']) : '0' ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="bi bi-calendar-event me-1"></i> <?= $__t('created') ?></span>
                            <span class="stat-value"><?= !empty($content['created_at']) ? htmlspecialchars(explode(' ', (string)$content['created_at'])[0]) : $__t('unknown') ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="bi bi-palette me-1"></i> <?= $__t('artist') ?></span>
                            <?php if (!empty($content['artist'])): ?>
                                <a href="<?= $url('/search?q=' . urlencode((string)$content['artist'])) ?>" class="stat-value hover-primary transition-all">
                                    <?= htmlspecialchars((string)$content['artist']) ?>
                                </a>
                            <?php else: ?>
                                <span class="stat-value"><?= $__t('unknown') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="bi bi-geo-alt me-1"></i> <?= $__t('country') ?></span>
                            <?php if (!empty($content['country'])): ?>
                                <a href="<?= $url('/search?q=' . urlencode((string)$content['country'])) ?>" class="stat-value hover-primary transition-all">
                                    <?= htmlspecialchars((string)$content['country']) ?>
                                </a>
                            <?php else: ?>
                                <span class="stat-value"><?= $__t('unknown') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><i class="bi bi-rocket-takeoff me-1"></i> <?= $__t('release') ?></span>
                            <?php if (!empty($content['release_year']) && $content['release_year'] !== '0'): ?>
                                <a href="<?= $url('/search?q=' . urlencode((string)$content['release_year'])) ?>" class="stat-value hover-primary transition-all">
                                    <?= htmlspecialchars((string)$content['release_year']) ?>
                                </a>
                            <?php else: ?>
                                <span class="stat-value"><?= $__t('unknown') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="main-content-grid container mt-5">
  <div id="chapterListTarget"></div>

  <!-- Comments Section (Separate Box) -->
  <div id="commentsTarget">
    <div class="card p-4 shadow-lg border-0">
      <div class="card-header border-bottom mb-4 bg-transparent p-0 pb-3 flex justify-between items-center">
        <h3 class="m-0">💬 <?= $__t('comments') ?> <span id="commentsBadgeCount" class="badge bg-primary text-xs ml-2"></span></h3>
      </div>
      
      <form id="contentCommentForm" class="mb-5 bg-surface-elevated p-4 rounded-lg border border-primary-10">
        <div class="flex flex-col gap-3 mb-3">
          <textarea id="contentCommentInput" class="form-item border-0 focus-ring" placeholder="<?= $__t('comments') ?>... (Markdown)" rows="4"></textarea>
          <div class="flex items-center gap-2 mb-1 mt-2">
            <span class="text-xs text-muted font-bold uppercase tracking-wider">👁️ <?= $__t('preview') ?></span>
          </div>
          <div id="commentPreview" class="form-item bg-surface overflow-auto markdown-body p-3 min-h-80 border-dashed opacity-80">
            <span class="text-muted italic"><?= $__t('preview_will_appear') ?></span>
          </div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary px-5 py-2 rounded-full shadow-primary"><?= $__t('post_comment') ?></button>
        </div>
      </form>

      <div id="contentCommentsList" class="flex flex-col gap-5">
        <div class="text-center py-5">
            <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 rounded-full text-primary" role="status"></div>
            <div class="mt-2 text-muted"><?= $__t('loading') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
