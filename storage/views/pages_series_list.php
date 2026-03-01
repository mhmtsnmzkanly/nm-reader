<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumb-nav mb-4" aria-label="breadcrumb">
    <ol class="nmr-breadcrumb mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
            <li class="nmr-breadcrumb-item <?= $bc['url'] ? '' : 'active' ?>" 
                itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"
                <?= !$bc['url'] ? 'aria-current="page"' : '' ?>>
                <?php if ($bc['url']): ?>
                    <a href="<?= htmlspecialchars($bc['url']) ?>" itemprop="item">
                        <span itemprop="name"><?= htmlspecialchars($bc['title']) ?></span>
                    </a>
                <?php else: ?>
                    <span itemprop="name"><?= htmlspecialchars($bc['title']) ?></span>
                <?php endif; ?>
                <meta itemprop="position" content="<?= $i + 1 ?>" />
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<h2 id="listingTitle" class="mb-4"><?= $__t('browse') ?></h2>

<div id="listingLoading" class="text-center py-5">
  <div class="btn btn-lg btn-outline border-none"><?= $__t('loading') ?></div>
</div>

<div id="listingApp" class="hidden">
  <div id="listingGrid"></div>
</div>
