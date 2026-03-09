<?php
$homeData = is_array($home_data ?? null) ? $home_data : [];
$explore = is_array($homeData['explore'] ?? null) ? $homeData['explore'] : [];
$recentChapters = is_array($homeData['recent_chapters'] ?? null) ? $homeData['recent_chapters'] : [];
$recentlyAdded = is_array($homeData['recently_added'] ?? null) ? $homeData['recently_added'] : [];
$popularBlogs = is_array($homeData['popular_blogs'] ?? null) ? $homeData['popular_blogs'] : [];
$latestBlogs = is_array($homeData['latest_blogs'] ?? null) ? $homeData['latest_blogs'] : [];
?>

<h1 class="page-title">home</h1>
<p class="page-intro">Ana sayfa sadece mevcut veriyi listeler.</p>

<section class="section-block">
  <h2 class="section-title">explore</h2>
  <div class="stack">
    <?php foreach ($explore as $item): ?>
      <article class="record">
        <h3><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></h3>
        <p class="record-meta"><?= htmlspecialchars((string) ($item['type_path'] ?? $item['type'] ?? '')) ?></p>
        <p><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section-block">
  <h2 class="section-title">recent chapters</h2>
  <ul class="plain-list">
    <?php foreach ($recentChapters as $chapter): ?>
      <li>
        <a href="<?= $url('/' . (string) ($chapter['type_path'] ?? 'novel') . '/' . (string) ($chapter['slug'] ?? '') . '/chapter/' . rawurlencode((string) ($chapter['chapter_number'] ?? ''))) ?>">
          <?= htmlspecialchars((string) ($chapter['series_title'] ?? '')) ?> / chapter <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? '')) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="section-block">
  <h2 class="section-title">recently added</h2>
  <ul class="plain-list">
    <?php foreach ($recentlyAdded as $item): ?>
      <li><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="split">
  <div class="section-block">
    <h2 class="section-title">popular blogs</h2>
    <div class="stack">
      <?php foreach ($popularBlogs as $blog): ?>
        <article class="record">
          <h3><a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($blog['title'] ?? '')) ?></a></h3>
          <p class="record-meta"><?= htmlspecialchars((string) ($blog['author_username'] ?? '')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section-block">
    <h2 class="section-title">latest blogs</h2>
    <div class="stack">
      <?php foreach ($latestBlogs as $blog): ?>
        <article class="record">
          <h3><a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($blog['title'] ?? '')) ?></a></h3>
          <p class="record-meta"><?= htmlspecialchars((string) ($blog['approved_at'] ?? $blog['created_at'] ?? '')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
