<?php
$home = is_array($home_data ?? null) ? $home_data : [];
$heroItems = is_array($home['explore'] ?? null) ? $home['explore'] : [];
$recentChapters = is_array($home['recent_chapters'] ?? null) ? $home['recent_chapters'] : [];
$recentlyAdded = is_array($home['recently_added'] ?? null) ? $home['recently_added'] : [];
$popularBlogs = is_array($home['popular_blogs'] ?? null) ? $home['popular_blogs'] : [];
$latestBlogs = is_array($home['latest_blogs'] ?? null) ? $home['latest_blogs'] : [];
?>

<div class="melt-home-page" id="meltHomePage">
  <section class="melt-hero">
    <?php $activeHero = $heroItems[0] ?? null; ?>
    <?php if ($activeHero): ?>
      <div class="melt-hero__backdrop" style="background-image:url('<?= htmlspecialchars((string) ($activeHero['cover_image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')"></div>
    <?php endif; ?>
    <div class="melt-hero__overlay"></div>
    <div class="melt-hero__inner">
      <div class="melt-hero__copy">
        <span class="melt-kicker">Flores-inspired layout / Melt rebuild</span>
        <h1><?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'NovelMangaReader'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars((string) ($siteConfig['site_description'] ?? 'Discover manga, webtoon, and novel content with a modern reading interface.'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="melt-hero__actions">
          <a href="<?= $meltUrl('/manga') ?>" class="btn btn-primary">Browse manga</a>
          <a href="<?= $meltUrl('/novel') ?>" class="btn btn-outline">Browse novels</a>
        </div>
      </div>
      <div class="melt-hero__rail" id="meltHeroRail">
        <?php foreach ($heroItems as $index => $item): ?>
          <?php
            $typePath = (string) ($item['type_path'] ?? $item['type'] ?? 'novel');
            $itemHref = $meltUrl('/' . $typePath . '/' . (string) ($item['slug'] ?? ''));
          ?>
          <a href="<?= $itemHref ?>" class="melt-hero-card<?= $index === 0 ? ' is-active' : '' ?>" data-hero-card style="--cover:url('<?= htmlspecialchars((string) ($item['cover_image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
            <img src="<?= htmlspecialchars((string) ($item['cover_image'] ?? '/assets/img/covers/one-piece.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="melt-hero-card__meta">
              <span><?= htmlspecialchars(strtoupper((string) $typePath), ENT_QUOTES, 'UTF-8') ?></span>
              <strong><?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="melt-home-grid">
    <div class="melt-home-grid__main">
      <div class="melt-section-card">
        <div class="melt-section-card__head">
          <h2><?= $__t('ui.explore_content') ?></h2>
          <a href="<?= $meltUrl('/manga') ?>">View all</a>
        </div>
        <div class="melt-poster-grid">
          <?php foreach ($heroItems as $item): ?>
            <?php
              $typePath = (string) ($item['type_path'] ?? $item['type'] ?? 'novel');
              $itemHref = $meltUrl('/' . $typePath . '/' . (string) ($item['slug'] ?? ''));
            ?>
            <article class="melt-poster-card">
              <a href="<?= $itemHref ?>" class="melt-poster-card__cover">
                <img src="<?= htmlspecialchars((string) ($item['cover_image'] ?? '/assets/img/covers/one-piece.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="melt-badge"><?= htmlspecialchars((string) strtoupper($typePath), ENT_QUOTES, 'UTF-8') ?></span>
              </a>
              <div class="melt-poster-card__body">
                <a href="<?= $itemHref ?>" class="melt-poster-card__title"><?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></a>
                <div class="melt-poster-card__meta">
                  <span><?= htmlspecialchars((string) ($item['author'] ?? $__t('unknown')), ENT_QUOTES, 'UTF-8') ?></span>
                  <span>★ <?= htmlspecialchars((string) ($item['rating_avg'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="melt-section-card">
        <div class="melt-section-card__head">
          <h2><?= $__t('ui.new_chapters') ?></h2>
        </div>
        <div class="melt-chapter-feed">
          <?php foreach ($recentChapters as $chapter): ?>
            <?php
              $seriesPath = $meltUrl('/' . (string) ($chapter['type_path'] ?? 'novel') . '/' . (string) ($chapter['series_slug'] ?? ''));
              $chapterPath = $meltUrl('/' . (string) ($chapter['type_path'] ?? 'novel') . '/' . (string) ($chapter['series_slug'] ?? '') . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? '1')));
            ?>
            <article class="melt-feed-card">
              <a href="<?= $chapterPath ?>" class="melt-feed-card__cover">
                <img src="<?= htmlspecialchars((string) ($chapter['cover_image'] ?? '/assets/img/covers/one-piece.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($chapter['series_title'] ?? 'Chapter'), ENT_QUOTES, 'UTF-8') ?>">
              </a>
              <div class="melt-feed-card__body">
                <a href="<?= $seriesPath ?>" class="melt-feed-card__series"><?= htmlspecialchars((string) ($chapter['series_title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= $chapterPath ?>" class="melt-feed-card__chapter"><?= $__t('chapters') ?> <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                <p><?= htmlspecialchars((string) ($chapter['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="melt-section-card">
        <div class="melt-section-card__head">
          <h2><?= $__t('ui.newly_added') ?></h2>
        </div>
        <div class="melt-poster-grid melt-poster-grid--compact">
          <?php foreach ($recentlyAdded as $item): ?>
            <?php $itemHref = $meltUrl('/' . (string) ($item['type_path'] ?? $item['type'] ?? 'novel') . '/' . (string) ($item['slug'] ?? '')); ?>
            <article class="melt-poster-card melt-poster-card--compact">
              <a href="<?= $itemHref ?>" class="melt-poster-card__cover">
                <img src="<?= htmlspecialchars((string) ($item['cover_image'] ?? '/assets/img/covers/one-piece.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?>">
              </a>
              <div class="melt-poster-card__body">
                <a href="<?= $itemHref ?>" class="melt-poster-card__title"><?= htmlspecialchars((string) ($item['title'] ?? 'Series'), ENT_QUOTES, 'UTF-8') ?></a>
                <div class="melt-poster-card__meta">
                  <span><?= htmlspecialchars((string) strtoupper((string) ($item['type_path'] ?? $item['type'] ?? 'novel')), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <aside class="melt-home-grid__side">
      <div class="melt-side-card">
        <div class="melt-side-card__head">
          <h3><?= $__t('ui.popular_posts') ?></h3>
        </div>
        <div class="melt-blog-stack">
          <?php foreach ($popularBlogs as $blog): ?>
            <a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>" class="melt-blog-row">
              <strong><?= htmlspecialchars((string) ($blog['title'] ?? 'Blog'), ENT_QUOTES, 'UTF-8') ?></strong>
              <span>@<?= htmlspecialchars((string) ($blog['author_username'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="melt-side-card">
        <div class="melt-side-card__head">
          <h3><?= $__t('ui.recent_updates') ?></h3>
        </div>
        <div class="melt-blog-stack">
          <?php foreach ($latestBlogs as $blog): ?>
            <a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>" class="melt-blog-row">
              <strong><?= htmlspecialchars((string) ($blog['title'] ?? 'Blog'), ENT_QUOTES, 'UTF-8') ?></strong>
              <span>@<?= htmlspecialchars((string) ($blog['author_username'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>
  </section>
</div>
