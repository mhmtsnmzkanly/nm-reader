<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumb-nav mb-4 px-2" aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 small" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
            <li class="breadcrumb-item <?= $bc['url'] ? '' : 'active' ?>" 
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

<div id="blogLoading" class="text-center py-5 <?= !empty($ssr_data) ? 'hidden' : '' ?>">
  <div class="spinner-border animate-spin text-primary"></div>
  <div class="mt-2 text-muted"><?= $__t('loading') ?></div>
</div>

<div id="blogListArea" class="hidden">
  <div class="mb-5 text-center">
    <h1 class="text-4xl font-black mb-2"><?= $__t('news_and_articles') ?></h1>
    <p class="text-muted max-w-520 mx-auto"><?= $__t('latest_updates_desc') ?></p>
  </div>
  <div class="grid grid-3 gap-4" id="blogGrid"></div>
</div>

<div id="blogDetailArea" class="<?= empty($ssr_data) ? 'hidden' : '' ?>">
  <?php if (!empty($ssr_data)): 
      $post = $ssr_data;
      $score = (int)($post['upvote_count'] ?? 0) - (int)($post['downvote_count'] ?? 0);
      $img = !empty($post['cover_image']) ? $post['cover_image'] : '/assets/img/covers/one-piece.jpg';
      $myVote = (int)($post['my_vote'] ?? 0);
  ?>
    <div id="blogPostContent">
      <div class="blog-hero-wrapper">
        <div class="blog-hero-backdrop" style="background-image: url('<?= htmlspecialchars((string)$img) ?>')"></div>
        <div class="blog-hero-overlay"></div>
        <div class="container blog-hero-content">
          <button class="blog-meta-pill btn-none mb-4 cursor-pointer hover-lift" id="backToBlog">← <?= $__t('back') ?></button>
          <h1 class="blog-hero-title"><?= htmlspecialchars((string)$post['title']) ?></h1>
          <div class="blog-hero-meta">
             <a href="<?= $url('/profile/' . ($post['author_username'] ?? '')) ?>" class="blog-meta-pill no-underline">
                 👤 @<?= htmlspecialchars((string)($post['author_username'] ?? '-')) ?>
             </a>
             <div class="blog-meta-pill">
                 📅 <?= htmlspecialchars(explode(' ', (string)($post['approved_at'] ?? $post['created_at'] ?? ''))[0]) ?>
             </div>
             <div class="blog-meta-pill">
                 🔥 <span class="blog-score-val"><?= $score ?></span>
             </div>
          </div>
        </div>
      </div>

      <div class="container blog-post-container" data-slug="<?= htmlspecialchars((string)$post['slug']) ?>">
        <!-- Floating Vote for Desktop -->
        <div class="blog-vote-floating">
          <button class="btn-none blog-vote-btn upvote <?= $myVote === 1 ? 'text-primary' : '' ?>" data-vote="1" style="font-size:1.5rem">▲</button>
          <span class="font-bold blog-score-val"><?= $score ?></span>
          <button class="btn-none blog-vote-btn downvote <?= $myVote === -1 ? 'text-danger' : '' ?>" data-vote="-1" style="font-size:1.5rem">▼</button>
        </div>

        <div class="blog-content-card">
          <div class="blog-content-main markdown-body" id="ssr-blog-body">
            <?= htmlspecialchars((string)($post['body'] ?? '')) ?>
          </div>
          
          <div class="mt-5 pt-5 border-t">
            <div id="blogCommentsArea">
              <h3 class="mb-4">💬 <?= $__t('comments') ?> <span id="blogCommentCount" class="badge bg-primary ml-2"></span></h3>
              
              <form id="blogCommentForm" class="mb-5 bg-surface-elevated p-4 rounded-xl border">
                <div class="flex flex-col gap-3">
                  <textarea id="blogCommentInput" class="form-item border-none focus-ring" placeholder="<?= $__t('comments') ?>..." rows="4"></textarea>
                  <div class="text-xs text-muted font-bold uppercase tracking-wider">👁️ <?= $__t('preview') ?></div>
                  <div id="blogCommentPreview" class="form-item bg-surface overflow-auto markdown-body p-3 min-h-80 border-dashed opacity-80">
                    <span class="text-muted italic"><?= $__t('preview_will_appear') ?></span>
                  </div>
                  <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-full"><?= $__t('post_comment') ?></button>
                  </div>
                </div>
              </form>
              
              <div id="blogCommentsList" class="flex flex-col gap-5">
                <div class="text-center py-5">
                    <div class="spinner-border animate-spin text-primary"></div>
                    <div class="mt-2 text-muted"><?= $__t('loading') ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <script>
        (function() {
          const el = document.getElementById('ssr-blog-body');
          if (el && window.NMR && window.NMR.parseMarkdown) {
            const raw = el.textContent || el.innerText;
            el.innerHTML = window.NMR.parseMarkdown(raw);
          }
        })();
      </script>
    </div>
  <?php else: ?>
    <div id="blogPostContent"></div>
  <?php endif; ?>
</div>

