<?php
$chapterData = $chapter ?? $ssr_chapter ?? null;
$chapter = is_array($chapterData) ? $chapterData : [];
$adj = $chapter['adjacent_chapters'] ?? ['next' => null, 'prev' => null];
$seriesTitle = (string) ($chapter['series_title'] ?? '');
$isLocked = (bool) ($chapter['is_locked'] ?? false);
$priceCoin = (int) ($chapter['price_coin'] ?? 0);
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
  <nav class="breadcrumb-nav mb-3" aria-label="breadcrumb">
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

<div class="melt-reader-page reader-container" id="readerApp" data-chapter-id="<?= htmlspecialchars((string) ($chapter['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <div class="melt-reader-toolbar card">
    <div class="melt-reader-toolbar__main">
      <a href="<?= $adj['prev'] ? $meltUrl('/' . $type . '/' . $slug . '/chapter/' . rawurlencode((string) $adj['prev'])) : '#' ?>" id="prevChapterBtn" class="btn btn-sm btn-outline<?= !$adj['prev'] ? ' disabled opacity-30' : '' ?>">Prev</a>
      <select class="form-item" id="chapterSelect">
        <option value="<?= htmlspecialchars((string) ($chapter['chapter_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" selected><?= $__t('chapters') ?> <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
      </select>
      <a href="<?= $adj['next'] ? $meltUrl('/' . $type . '/' . $slug . '/chapter/' . rawurlencode((string) $adj['next'])) : '#' ?>" id="nextChapterBtn" class="btn btn-sm btn-primary<?= !$adj['next'] ? ' disabled opacity-30' : '' ?>">Next</a>
    </div>
    <div class="melt-reader-toolbar__meta">
      <strong><?= htmlspecialchars($seriesTitle, ENT_QUOTES, 'UTF-8') ?></strong>
      <a href="<?= $meltUrl('/' . $type . '/' . $slug) ?>" class="btn btn-sm btn-outline">Series</a>
      <button class="btn btn-sm btn-outline" id="openReaderSettings" onclick="openModal('readerSettingsModal')">Settings</button>
    </div>
  </div>

  <?php if ($isLocked): ?>
    <div class="melt-lock-card card">
      <h1><?= htmlspecialchars($seriesTitle, ENT_QUOTES, 'UTF-8') ?> / <?= $__t('chapters') ?> <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
      <p>This chapter is locked for the current session.</p>
      <?php if ($priceCoin > 0): ?>
        <button type="button" class="btn btn-primary" id="meltUnlockChapterBtn" data-chapter-id="<?= htmlspecialchars((string) ($chapter['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-price="<?= $priceCoin ?>">Unlock <?= $priceCoin ?>c</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="reader-main">
    <div id="mangaView" class="card p-0 overflow-hidden<?= ($chapter['type'] ?? '') === 'image' ? '' : ' hidden' ?>">
      <div class="manga-pages"></div>
    </div>
    <div id="novelView" class="card<?= ($chapter['type'] ?? '') === 'text' ? '' : ' hidden' ?>">
      <div class="novel-content markdown-body">
        <?php if (($chapter['type'] ?? '') === 'text' && !$isLocked): ?>
          <?= htmlspecialchars((string) ($chapter['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <section class="melt-section-card">
    <div class="melt-section-card__head">
      <h2><?= $__t('comments') ?></h2>
    </div>
    <form id="readerCommentForm" class="melt-comment-form">
      <textarea id="readerCommentInput" class="form-item" rows="4" placeholder="<?= $__t('comments') ?>..."></textarea>
      <div id="commentPreview" class="melt-comment-preview markdown-body">
        <span><?= $__t('preview_will_appear') ?></span>
      </div>
      <div class="melt-comment-form__actions">
        <button type="submit" class="btn btn-primary"><?= $__t('post_comment') ?></button>
      </div>
    </form>
    <div id="readerCommentsList" class="melt-comments-list">
      <div class="melt-loading-row"><?= $__t('loading') ?></div>
    </div>
  </section>
</div>
