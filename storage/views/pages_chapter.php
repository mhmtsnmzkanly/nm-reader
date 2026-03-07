<?php
$chapter = is_array($ssr_chapter ?? null) ? $ssr_chapter : [];
$adj = $chapter['adjacent_chapters'] ?? ['next' => null, 'prev' => null];
$type = (string) ($type ?? '');
$slug = (string) ($slug ?? '');
?>

<div class="reader-container mx-auto max-w-1400" id="readerApp" data-chapter-id="<?= htmlspecialchars((string)($chapter['id'] ?? '')) ?>">
  <?php if (!empty($breadcrumbs)): ?>
    <nav class="breadcrumb-nav mb-2 px-2" aria-label="breadcrumb">
      <ol class="nmr-breadcrumb mb-0 small" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
          <li class="nmr-breadcrumb-item <?= $bc['url'] ? '' : 'active text-truncate' ?>" 
              style="max-width: 200px;"
              itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"
              <?= !$bc['url'] ? 'aria-current="page"' : '' ?>>
            <?php if ($bc['url']): ?>
              <a href="<?= htmlspecialchars($bc['url']) ?>" itemprop="item" class="text-muted">
                <span itemprop="name"><?= htmlspecialchars($bc['title']) ?></span>
              </a>
            <?php else: ?>
              <span itemprop="name" class="fw-bold"><?= htmlspecialchars($bc['title']) ?></span>
            <?php endif; ?>
            <meta itemprop="position" content="<?= $i + 1 ?>" />
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
  <?php endif; ?>

  <div class="card reader-toolbar mb-4 flex items-center justify-between p-2 sticky top-0 z-10">
    <div class="flex items-center gap-2">
      <a href="<?= $adj['prev'] ? $url('/' . $type . '/' . $slug . '/chapter/' . rawurlencode((string)$adj['prev'])) : '#' ?>" 
         id="prevChapterBtn" 
         class="btn btn-sm btn-outline <?= !$adj['prev'] ? 'disabled opacity-30' : '' ?>">
         &laquo; <?= $__t('prev') ?>
      </a>
      
      <select class="form-item py-1 px-3 w-180" id="chapterSelect" onchange="location.href=this.value">
          <option value="" selected><?= $__t('chapter') ?> <?= $chapter['chapter_number'] ?? '' ?></option>
      </select>

      <a href="<?= $adj['next'] ? $url('/' . $type . '/' . $slug . '/chapter/' . rawurlencode((string)$adj['next'])) : '#' ?>" 
         id="nextChapterBtn" 
         class="btn btn-sm btn-primary <?= !$adj['next'] ? 'disabled opacity-30' : '' ?>">
         <?= $__t('next') ?> &raquo;
      </a>
    </div>
    
    <div class="flex items-center gap-2">
      <a href="<?= $url('/' . $type . '/' . $slug) ?>" class="btn btn-sm btn-outline hide-md">🏠 <?= $__t('series_home') ?></a>
      <button class="btn btn-sm btn-outline" data-bs-toggle="modal" data-bs-target="#readerSettingsModal">⚙️</button>
    </div>
  </div>

  <!-- Reader Settings Modal -->
  <div class="modal fade" id="readerSettingsModal" tabindex="-1" aria-labelledby="readerSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="readerSettingsModalLabel"><?= $__t('reader_settings') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-4">
            <label class="form-label font-bold mb-2 d-block"><?= $__t('reading_mode') ?></label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="layoutMode" id="layoutVertical" value="vertical" autocomplete="off">
              <label class="btn btn-outline-primary" for="layoutVertical"><?= $__t('vertical') ?></label>

              <input type="radio" class="btn-check" name="layoutMode" id="layoutSingle" value="single" autocomplete="off">
              <label class="btn btn-outline-primary" for="layoutSingle"><?= $__t('single_page') ?></label>

              <input type="radio" class="btn-check" name="layoutMode" id="layoutDouble" value="double" autocomplete="off">
              <label class="btn btn-outline-primary" for="layoutDouble"><?= $__t('double_page') ?></label>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label font-bold mb-2 d-block"><?= $__t('image_fit') ?></label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="imageFit" id="fitWidth" value="width" autocomplete="off">
              <label class="btn btn-outline-primary" for="fitWidth"><?= $__t('fit_width') ?></label>

              <input type="radio" class="btn-check" name="imageFit" id="fitHeight" value="height" autocomplete="off">
              <label class="btn btn-outline-primary" for="fitHeight"><?= $__t('fit_height') ?></label>

              <input type="radio" class="btn-check" name="imageFit" id="fitOriginal" value="original" autocomplete="off">
              <label class="btn btn-outline-primary" for="fitOriginal"><?= $__t('original') ?></label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="reader-main">
    <div id="mangaView" class="card p-0 overflow-hidden hidden border-0 shadow-none bg-transparent unselectable">
      <div class="manga-pages"></div>
    </div>
    <div id="novelView" class="card unselectable <?= isset($ssr_chapter) &&
    $ssr_chapter["type"] === "text"
        ? ""
        : "hidden" ?> fs-1-2 lh-2">
      <div class="novel-content markdown-body p-4 p-md-5">
        <?php if (isset($ssr_chapter) && $ssr_chapter["type"] === "text"): ?>
          <?= $ssr_chapter["body"]
            // Note: Assuming body is already sanitized or will be parsed by JS hydration
            ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="reader-comments mt-5 max-w-900 mx-auto">
    <div class="card">
      <div class="card-header border-bottom bg-surface p-3 m-0">
        <h3 class="m-0">Chapter Comments</h3>
      </div>

      <div class="p-4">
        <form id="readerCommentForm" class="mb-4">
          <div class="flex flex-col gap-3 mb-3">
            <textarea id="readerCommentInput" class="form-item" placeholder="Write a comment (Markdown supported)..." rows="4"></textarea>
            <div class="text-xs text-muted font-bold uppercase tracking-wider">Preview</div>
            <div id="commentPreview" class="form-item bg-surface overflow-auto markdown-body p-3 min-h-80 border-dashed">
              <span class="text-muted italic">Preview will appear here...</span>
            </div>
          </div>
          <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Post Comment</button>
          </div>
        </form>

        <div id="readerCommentsList" class="flex flex-col gap-4">
          <div class="text-center py-3 text-muted">Loading comments...</div>
        </div>
      </div>
    </div>
  </div>
</div>
