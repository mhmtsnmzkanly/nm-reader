<?php
$user = is_array($profile['user'] ?? null) ? $profile['user'] : [];
$stats = is_array($profile['statistics'] ?? null) ? $profile['statistics'] : [];
$blogs = is_array($profile['blogs'] ?? null) ? $profile['blogs'] : [];
$comments = is_array($profile['recent_comments'] ?? null) ? $profile['recent_comments'] : [];
?>

<section class="section-block">
  <h1 class="page-title"><?= htmlspecialchars((string) ($user['username'] ?? 'profile')) ?></h1>
  <p class="page-intro"><?= htmlspecialchars((string) ($user['bio'] ?? '')) ?></p>
</section>

<section class="split">
  <div class="stack">
    <section class="section-block">
      <h2 class="section-title">blogs</h2>
      <div class="stack">
        <?php foreach ($blogs as $blog): ?>
          <article class="record">
            <h3><a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($blog['title'] ?? '')) ?></a></h3>
            <p class="record-meta"><?= htmlspecialchars((string) ($blog['approved_at'] ?? $blog['created_at'] ?? '')) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section-block">
      <h2 class="section-title">recent comments</h2>
      <div class="stack">
        <?php foreach ($comments as $comment): ?>
          <article class="record">
            <p><?= htmlspecialchars((string) ($comment['body'] ?? '')) ?></p>
            <?php if (!empty($comment['url_path'])): ?>
              <p class="record-meta"><a href="<?= $url((string) $comment['url_path']) ?>">open related content</a></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <aside class="stack">
    <div class="meta-grid">
      <div><strong>score</strong><br><?= number_format((int) ($stats['score'] ?? 0)) ?></div>
      <div><strong>followers</strong><br><?= number_format((int) ($stats['followers_count'] ?? 0)) ?></div>
      <div><strong>following</strong><br><?= number_format((int) ($stats['following_count'] ?? 0)) ?></div>
      <div><strong>approved blogs</strong><br><?= number_format((int) ($stats['approved_blog_count'] ?? 0)) ?></div>
      <div><strong>comments</strong><br><?= number_format((int) ($stats['comment_count'] ?? 0)) ?></div>
      <div><strong>votes cast</strong><br><?= number_format((int) ($stats['votes_cast'] ?? 0)) ?></div>
    </div>

    <?php if ($isMe && !empty($library)): ?>
      <section class="section-block">
        <h2 class="section-title">library</h2>
        <ul class="plain-list">
          <?php foreach ($library as $item): ?>
            <li><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($isMe && !empty($history)): ?>
      <section class="section-block">
        <h2 class="section-title">history</h2>
        <ul class="plain-list">
          <?php foreach ($history as $row): ?>
            <li><?= htmlspecialchars((string) ($row['content_title'] ?? '')) ?> / chapter <?= htmlspecialchars((string) ($row['chapter_number'] ?? '')) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </aside>
</section>
