<?php
/** @var array $profile */
/** @var bool $isMe */
/** @var array $history */
/** @var array $library */
/** @var array $preferences */
/** @var string $person */
/** @var callable $__t */

$user = $profile["user"] ?? [];
$stats = $profile["statistics"] ?? [];
$isFollowing = $profile["is_following"] ?? false;
$blogs = $profile["blogs"] ?? [];
$comments = $profile["recent_comments"] ?? [];

$defaultAvatar = "//api.dicebear.com/9.x/bottts/svg";
$defaultCover = "//placehold.co/1200x400?text=Profile\nCover";
?>

<div class="container py-5" id="profileApp">
    <!-- Cover & Avatar Header -->
    <div class="card p-0 overflow-hidden mb-5 border-0 shadow-lg">
        <div class="position-relative h-200" style="height: 200px; background: url('<?= htmlspecialchars((string)($user["cover_image"] ?: $defaultCover)) ?>') center/cover no-repeat;">
            <div class="position-absolute bottom-0 left-0 w-100 p-4" style="background: linear-gradient(transparent, rgba(0,0,0,0.8))">
                <div class="d-flex items-end gap-3 flex-sm-col items-sm-center text-sm-center">
                    <img src="<?= htmlspecialchars((string)($user["profile_image"] ?: $defaultAvatar)) ?>" 
                         class="rounded-full border-4 border-white shadow-lg" 
                         style="width: 120px; height: 120px; object-fit: cover; margin-bottom: -40px;">
                    <div class="text-white pb-2">
                        <h1 class="m-0 text-3xl font-bold"><?= htmlspecialchars((string)($user["username"] ?? "")) ?></h1>
                        <p class="m-0 opacity-75"><?= htmlspecialchars((string)($user["bio"] ?: $__t('no_bio'))) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 pt-5 d-flex justify-between items-center flex-md-col gap-4">
            <div class="d-flex gap-5 font-semibold">
                <div class="text-center">
                    <div class="text-primary text-xl"><?= number_format((int)($stats["score"] ?? 0)) ?></div>
                    <div class="text-muted uppercase text-xs"><?= $__t('score') ?></div>
                </div>
                <div class="text-center">
                    <div id="sFollowers" class="text-primary text-xl"><?= number_format((int)($stats["followers_count"] ?? 0)) ?></div>
                    <div class="text-muted uppercase text-xs"><?= $__t('followers') ?></div>
                </div>
                <div class="text-center">
                    <div class="text-primary text-xl"><?= number_format((int)($stats["following_count"] ?? 0)) ?></div>
                    <div class="text-muted uppercase text-xs"><?= $__t('following') ?></div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if (!$isMe): ?>
                    <button id="followBtn" data-status="<?= $isFollowing ? "following" : "none" ?>" class="btn <?= $isFollowing ? "btn-secondary" : "btn-primary" ?> px-4">
                        <?= $isFollowing ? $__t('following') : $__t('follow') ?>
                    </button>
                    <button class="btn btn-outline px-3"><?= $__t('message') ?></button>
                <?php else: ?>
                    <button class="btn btn-outline px-4" onclick="openModal('userSettingsModal')"><?= $__t('edit_profile') ?></button>
                    <button class="btn btn-secondary px-3" onclick="openModal('userSettingsModal')"><?= $__t('settings') ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="grid grid-3 grid-md-1 gap-4">
        <!-- Feed Section -->
        <div style="grid-column: span 2 / span 2;" class="d-md-block profile-feed-span-2">
            <div class="card h-100 d-flex flex-col">
                <h3 class="card-header border-b pb-3 mb-4 d-flex items-center gap-2">
                    <span class="text-primary">✦</span> <?= $__t('activity_feed') ?>
                </h3>
                <div class="d-flex flex-col gap-4 flex-1">
                    <?php if (empty($blogs) && empty($comments)): ?>
                        <div class="text-center py-5">
                            <div class="text-4xl mb-2 opacity-20">📭</div>
                            <p class="text-muted"><?= $__t('no_activity') ?></p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($blogs as $b): ?>
                        <div class="p-4 bg-surface rounded-lg hover-lift border border-transparent hover-border-primary">
                            <div class="d-flex justify-between items-start mb-2">
                                <span class="bg-primary-light text-primary text-xs font-bold px-2 py-1 rounded uppercase">Blog</span>
                                <span class="text-xs text-muted"><?= htmlspecialchars((string)($b["approved_at"] ?? $b["created_at"] ?? "")) ?></span>
                            </div>
                            <h4 class="m-0 text-xl font-bold"><?= htmlspecialchars((string)($b["title"] ?? "")) ?></h4>
                            <p class="text-muted text-sm mt-2 line-clamp-2"><?= $__t('view_more') ?></p>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($comments as $c): ?>
                        <div class="p-3 border-l-4 border-primary bg-surface-elevated rounded-r-lg hover-bg">
                            <div class="text-xs text-muted mb-1"><?= htmlspecialchars((string)($c["created_at"] ?? "")) ?> • <?= $__t('commented_on_story') ?></div>
                            <p class="m-0 italic text-body">"<?= htmlspecialchars((string)($c["body"] ?? "")) ?>"</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Section -->
        <div class="d-flex flex-col gap-4">
            <!-- Library -->
            <div class="card">
                <h3 class="card-header border-b pb-3 mb-4 d-flex justify-between items-center">
                    <span><?= $__t('library') ?></span>
                    <span class="text-xs text-primary cursor-pointer hover-underline"><?= $__t('see_all') ?></span>
                </h3>
                <div class="grid grid-2 gap-3">
                    <?php if (empty($library)): ?>
                        <p class="text-muted text-xs text-center p-4 grid-col-2"><?= $__t('empty_library') ?></p>
                    <?php endif; ?>
                    <?php foreach (array_slice($library, 0, 4) as $item): ?>
                        <div class="position-relative group rounded-lg overflow-hidden shadow-sm hover-scale transition-all" style="aspect-ratio: 2/3;">
                            <img src="<?= htmlspecialchars((string)($item["cover_image"] ?? "")) ?>" class="w-100 h-100 object-cover">
                            <div class="view-overlay position-absolute top-0 left-0 w-100 h-100 d-flex items-center justify-center opacity-0 group-hover-opacity-100 transition-all" style="background: rgba(0,0,0,0.4)">
                                <button class="btn btn-sm btn-primary py-1 px-2"><?= $__t('view') ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Reading History -->
            <div class="card">
                <h3 class="card-header border-b pb-3 mb-4"><?= $__t('reading_history') ?></h3>
                <div class="d-flex flex-col gap-3">
                    <?php if (empty($history)): ?>
                        <p class="text-muted text-xs text-center p-2"><?= $__t('no_history') ?></p>
                    <?php endif; ?>
                    <?php foreach (array_slice($history, 0, 4) as $h): ?>
                        <div class="d-flex items-center gap-3 p-2 rounded hover-bg transition-all">
                            <div class="bg-primary-light text-primary rounded-1 p-2 font-bold text-xs">HP</div>
                            <div class="overflow-hidden">
                                <div class="font-bold text-sm truncate"><?= htmlspecialchars((string)($h["content_title"] ?? "")) ?></div>
                                <div class="text-muted text-xs"><?= $__t('chapter') ?> <?= htmlspecialchars((string)($h["chapter_number"] ?? "")) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline w-100 mt-4"><?= $__t('full_history') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    window.__NMR_CONTEXT = Object.assign(window.__NMR_CONTEXT || {}, {
        person: <?= json_encode($person ?? "") ?>,
        user: <?= json_encode($user) ?>,
        preferences: <?= json_encode($preferences) ?>
    });
</script>

<?php /* Styles moved to site.css */ ?>
