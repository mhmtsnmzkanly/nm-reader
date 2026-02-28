<div id="homeApp">
  <div class="grid grid-2 home-grid gap-4">
    <!-- Explore Content Column (Left - 70%) -->
    <div class="flex flex-col gap-5">
      <div>
        <h2 class="mb-4">🔍 <?= $__t('ui.explore_content') ?></h2>
        <div class="grid grid-3 home-content-grid content-grid" id="homeExploreGrid">
          <div class="skeleton-card skeleton"></div>
          <div class="skeleton-card skeleton"></div>
          <div class="skeleton-card skeleton"></div>
        </div>
      </div>

      <div>
        <h2 class="mb-4">🆕 <?= $__t('ui.new_chapters') ?></h2>
        <div class="grid grid-3 home-content-grid content-grid" id="homeUpdatedGrid">
          <div class="skeleton-card skeleton"></div>
          <div class="skeleton-card skeleton"></div>
          <div class="skeleton-card skeleton"></div>
        </div>
      </div>

      <div>
        <h2 class="mb-4">✨ <?= $__t('ui.newly_added') ?></h2>
        <div class="grid grid-3 home-content-grid content-grid" id="homeAddedGrid">
          <div class="skeleton-card skeleton"></div>
          <div class="skeleton-card skeleton"></div>
          <div class="skeleton-card skeleton"></div>
        </div>
      </div>
    </div>

    <!-- Blogs Column (Right - 30%) -->
    <div class="flex flex-col gap-4">
      <!-- Popular Blogs (Top) -->
      <div>
        <h2 class="mb-4"><?= $__t('ui.popular_posts') ?></h2>
        <div class="card p-0 overflow-hidden" style="min-height: 400px;">
          <div class="blog-card-header"><?= $__t('ui.popular_blogs') ?></div>
          <div id="popularBlogsList">
             <div class="p-4"><div class="skeleton h-120"></div></div>
          </div>
        </div>
      </div>

      <!-- Latest Blogs (Bottom) -->
      <div class="mt-2">
        <h2 class="mb-4"><?= $__t('ui.recent_updates') ?></h2>
        <div class="card p-0 overflow-hidden" style="min-height: 400px;">
          <div class="blog-card-header"><?= $__t('ui.latest_updates') ?></div>
          <div id="latestBlogsList">
             <div class="p-4"><div class="skeleton h-120"></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
