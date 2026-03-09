<?php
$user = is_array($profile['user'] ?? null) ? $profile['user'] : [];
$stats = is_array($profile['statistics'] ?? null) ? $profile['statistics'] : [];
$blogs = is_array($profile['blogs'] ?? null) ? $profile['blogs'] : [];
$comments = is_array($profile['recent_comments'] ?? null) ? $profile['recent_comments'] : [];
?>

<h1><?= htmlspecialchars((string) ($user['username'] ?? 'profile')) ?></h1>
<p><?= htmlspecialchars((string) ($user['bio'] ?? '')) ?></p>

<section>
  <h2>statistics</h2>
  <dl>
    <dt>score</dt><dd><?= number_format((int) ($stats['score'] ?? 0)) ?></dd>
    <dt>followers</dt><dd><?= number_format((int) ($stats['followers_count'] ?? 0)) ?></dd>
    <dt>following</dt><dd><?= number_format((int) ($stats['following_count'] ?? 0)) ?></dd>
    <dt>approved blogs</dt><dd><?= number_format((int) ($stats['approved_blog_count'] ?? 0)) ?></dd>
    <dt>comments</dt><dd><?= number_format((int) ($stats['comment_count'] ?? 0)) ?></dd>
    <dt>votes cast</dt><dd><?= number_format((int) ($stats['votes_cast'] ?? 0)) ?></dd>
  </dl>
</section>

<section>
  <h2>blogs</h2>
  <?php foreach ($blogs as $blog): ?>
    <article>
      <h3><a href="<?= $url('/blogs/' . (string) ($blog['slug'] ?? '')) ?>"><?= htmlspecialchars((string) ($blog['title'] ?? '')) ?></a></h3>
      <p><?= htmlspecialchars((string) ($blog['approved_at'] ?? $blog['created_at'] ?? '')) ?></p>
    </article>
  <?php endforeach; ?>
</section>

<section>
  <h2>recent comments</h2>
  <?php foreach ($comments as $comment): ?>
    <article>
      <p><?= htmlspecialchars((string) ($comment['body'] ?? '')) ?></p>
      <?php if (!empty($comment['url_path'])): ?>
        <p><a href="<?= $url((string) $comment['url_path']) ?>">open related content</a></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

<?php if ($isMe && !empty($library)): ?>
  <section>
    <h2>library</h2>
    <ul>
      <?php foreach ($library as $item): ?>
        <li><a href="<?= $url((string) ($item['url_path'] ?? '/')) ?>"><?= htmlspecialchars((string) ($item['title'] ?? '')) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($isMe && !empty($history)): ?>
  <section>
    <h2>history</h2>
    <ul>
      <?php foreach ($history as $row): ?>
        <li><?= htmlspecialchars((string) ($row['content_title'] ?? '')) ?> / chapter <?= htmlspecialchars((string) ($row['chapter_number'] ?? '')) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
