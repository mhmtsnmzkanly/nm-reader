<?php
/** @var array $profile */
/** @var array $library */
/** @var array $history */
/** @var bool $isMe */
/** @var Closure $url */

$user = is_array($profile['user'] ?? null) ? $profile['user'] : [];
$stats = is_array($profile['statistics'] ?? null) ? $profile['statistics'] : [];
$blogs = is_array($profile['blogs'] ?? null) ? $profile['blogs'] : [];
$comments = is_array($profile['recent_comments'] ?? null) ? $profile['recent_comments'] : [];
$username = (string) ($user['username'] ?? 'User');
?>

<main class="pb-24">
    <!-- Profile Hero Section -->
    <section class="profile-card pt-12 pb-8 px-6 border-b border-white/5">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row items-center md:items-end gap-8">
            <!-- Avatar -->
            <div class="relative group">
                <div class="w-32 h-32 md:w-40 md:h-40 rounded-[40px] overflow-hidden border-4 border-blue-600/20 shadow-2xl bg-blue-600 flex items-center justify-center text-4xl font-black text-white">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars((string)$user['avatar']) ?>" alt="Avatar" class="w-full h-full object-cover" />
                    <?php else: ?>
                        <?= strtoupper(substr($username, 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <?php if ($isMe): ?>
                <div class="absolute bottom-2 right-2 w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center cursor-pointer hover:scale-110 transition-transform shadow-lg shadow-blue-600/40">
                    <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="flex-1 text-center md:text-left">
                <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
                    <h2 class="text-3xl md:text-4xl font-black uppercase tracking-tighter text-white">
                        <?= htmlspecialchars($username) ?>
                    </h2>
                    <div class="flex items-center justify-center md:justify-start gap-2">
                        <span class="px-3 py-1 bg-blue-600/20 text-blue-500 rounded-lg text-[10px] font-black tracking-widest uppercase"><?= $__t('score') ?>: <?= number_format((int) ($stats['score'] ?? 0)) ?></span>
                    </div>
                </div>
                <p class="text-gray-400 text-sm max-w-xl mb-6">
                    <?= htmlspecialchars((string) ($user['bio'] ?? $__t('no_bio'))) ?>
                </p>

                <div class="flex items-center justify-center md:justify-start gap-8">
                    <div class="text-center md:text-left">
                        <span class="block text-xl font-black text-white"><?= number_format((int) ($stats['followers_count'] ?? 0)) ?></span>
                        <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest"><?= $__t('followers') ?></span>
                    </div>
                    <div class="text-center md:text-left">
                        <span class="block text-xl font-black text-white"><?= number_format((int) ($stats['following_count'] ?? 0)) ?></span>
                        <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest"><?= $__t('following') ?></span>
                    </div>
                    <div class="text-center md:text-left">
                        <span class="block text-xl font-black text-white"><?= number_format((int) ($stats['approved_blog_count'] ?? 0)) ?></span>
                        <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest"><?= $__t('blogs') ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <?php if ($isMe): ?>
                <button class="flex items-center gap-2 px-6 py-3 bg-blue-600 rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-600/20 hover:bg-blue-500 transition-all text-white">
                    <i data-lucide="edit-3" class="w-4 h-4"></i> <?= $__t('edit_profile') ?>
                </button>
                <?php else: ?>
                <button class="flex items-center gap-2 px-6 py-3 bg-blue-600 rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-600/20 hover:bg-blue-500 transition-all text-white">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> <?= $__t('follow') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Tabs Navigation -->
    <nav class="max-w-5xl mx-auto px-6 border-b border-white/5 flex gap-8 mb-8 overflow-x-auto no-scrollbar">
        <button class="tab-btn tab-active py-6 text-[11px] font-black uppercase tracking-[0.2em] whitespace-nowrap" data-tab="library">
            <?= $__t('library') ?>
        </button>
        <button class="tab-btn py-6 text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 whitespace-nowrap" data-tab="comments">
            <?= $__t('comments') ?>
        </button>
        <button class="tab-btn py-6 text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 whitespace-nowrap" data-tab="blogs">
            <?= $__t('blogs') ?>
        </button>
        <?php if ($isMe): ?>
        <button class="tab-btn py-6 text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 whitespace-nowrap" data-tab="history">
            <?= $__t('reading_history') ?>
        </button>
        <button class="tab-btn py-6 text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 whitespace-nowrap" data-tab="wallet">
            <?= $__t('ui.wallet_tab') ?>
        </button>
        <?php endif; ?>
    </nav>

    <!-- Tab Contents -->
    <section class="max-w-5xl mx-auto px-6">
        <!-- Library Tab -->
        <div id="tab-library" class="tab-content">
            <div class="manga-grid">
                <?php if (!empty($library)): ?>
                    <?php foreach ($library as $item): ?>
                    <div class="group cursor-pointer" onclick="location.href='<?= $url((string) ($item['url_path'] ?? '/')) ?>'">
                        <div class="relative aspect-[3/4] rounded-2xl overflow-hidden mb-3 border border-white/5 shadow-lg group-hover:scale-105 transition-all bg-zinc-900">
                            <img src="<?= htmlspecialchars((string)($item['cover_image'] ?? '')) ?>" class="w-full h-full object-cover" />
                        </div>
                        <h4 class="text-xs font-black uppercase tracking-tight text-white group-hover:text-blue-500 transition-colors">
                            <?= htmlspecialchars((string) ($item['title'] ?? '')) ?>
                        </h4>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-sm"><?= $__t('empty_library') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments Tab -->
        <div id="tab-comments" class="tab-content hidden space-y-4">
            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $comment): ?>
                <div class="glass p-6 rounded-3xl border border-white/5 hover:border-blue-500/30 transition-all">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest"><?= $__t('admin.comment') ?></span>
                        <span class="text-[9px] text-gray-600 font-bold"><?= date('d M Y', strtotime($comment['created_at'] ?? 'now')) ?></span>
                    </div>
                    <p class="text-sm text-gray-300 mb-4">
                        "<?= htmlspecialchars((string) ($comment['body'] ?? '')) ?>"
                    </p>
                    <?php if (!empty($comment['url_path'])): ?>
                    <a href="<?= $url((string) $comment['url_path']) ?>" class="text-[10px] font-black text-gray-500 hover:text-white uppercase tracking-widest flex items-center gap-2">
                        <?= $__t('ui.go_to_content') ?> <i data-lucide="external-link" class="w-3 h-3"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500 text-sm"><?= $__t('no_comments_yet') ?></p>
            <?php endif; ?>
        </div>

        <!-- Blogs Tab -->
        <div id="tab-blogs" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if (!empty($blogs)): ?>
                <?php foreach ($blogs as $blog): ?>
                <div onclick="location.href='<?= $url('blogs/' . (string) ($blog['slug'] ?? '')) ?>'" class="glass rounded-3xl overflow-hidden border border-white/5 group cursor-pointer hover:border-blue-500/30 transition-all">
                    <div class="h-40 overflow-hidden bg-zinc-900">
                        <img src="<?= htmlspecialchars((string)($blog['cover_image'] ?? '')) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                    </div>
                    <div class="p-6">
                        <h4 class="text-lg font-black uppercase tracking-tighter text-white mb-2 leading-tight">
                            <?= htmlspecialchars((string) ($blog['title'] ?? '')) ?>
                        </h4>
                        <p class="text-[10px] text-gray-500 font-bold uppercase"><?= date('d M Y', strtotime($blog['approved_at'] ?? $blog['created_at'] ?? 'now')) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500 text-sm"><?= $__t('no_blog_posts') ?></p>
            <?php endif; ?>
        </div>

        <!-- History Tab -->
        <?php if ($isMe): ?>
        <div id="tab-history" class="tab-content hidden space-y-3">
            <?php if (!empty($history)): ?>
                <?php foreach ($history as $row): ?>
                <div class="glass p-4 rounded-2xl flex items-center justify-between hover:border-blue-500/30 transition-all">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center text-blue-500 font-black text-xs">
                            <?= htmlspecialchars((string) ($row['chapter_number'] ?? '')) ?>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-white uppercase"><?= htmlspecialchars((string) ($row['content_title'] ?? '')) ?></h4>
                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest"><?= date('d M Y H:i', strtotime($row['created_at'] ?? 'now')) ?></p>
                        </div>
                    </div>
                    <a href="<?= $url((string) ($row['content_type'] ?? 'manga') . '/' . (string) ($row['content_slug'] ?? '') . '/chapter/' . rawurlencode((string) ($row['chapter_number'] ?? ''))) ?>" class="text-gray-500 hover:text-white">
                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500 text-sm"><?= $__t('no_history') ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Wallet Tab -->
        <?php if ($isMe): ?>
        <div id="tab-wallet" class="tab-content hidden space-y-6" data-loaded="0"
             data-msg-loading="<?= $__t('ui.wallet_loading') ?>"
             data-msg-load-failed="<?= $__t('ui.wallet_load_failed') ?>"
             data-msg-login="<?= $__t('ui.wallet_login_required') ?>"
             data-msg-empty-tx="<?= $__t('ui.wallet_empty_tx') ?>"
             data-msg-empty-packages="<?= $__t('ui.wallet_empty_packages') ?>"
             data-msg-empty-features="<?= $__t('ui.wallet_empty_features') ?>"
             data-msg-feature-active="<?= $__t('ui.wallet_feature_active') ?>"
             data-msg-feature-inactive="<?= $__t('ui.wallet_feature_inactive') ?>"
             data-msg-feature-until="<?= $__t('ui.wallet_feature_until') ?>">
            <div class="glass p-6 rounded-3xl border border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black uppercase tracking-widest text-white"><?= $__t('ui.wallet_balance') ?></h3>
                    <span class="text-[10px] font-bold text-gray-500 uppercase" id="walletUpdatedAt">--</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-600/10 rounded-2xl p-4">
                        <p class="text-[10px] text-blue-400 font-bold uppercase"><?= $__t('ui.available_coins') ?></p>
                        <p class="text-3xl font-black text-white" id="walletBalanceValue">--</p>
                    </div>
                    <div class="bg-white/5 rounded-2xl p-4">
                        <p class="text-[10px] text-gray-400 font-bold uppercase"><?= $__t('ui.wallet_total_purchased') ?></p>
                        <p class="text-xl font-black text-white" id="walletTotalPurchased">--</p>
                    </div>
                    <div class="bg-white/5 rounded-2xl p-4">
                        <p class="text-[10px] text-gray-400 font-bold uppercase"><?= $__t('ui.wallet_total_spent') ?></p>
                        <p class="text-xl font-black text-white" id="walletTotalSpent">--</p>
                    </div>
                </div>
                <div class="mt-4 text-[11px] text-gray-400 font-bold uppercase tracking-widest">
                    <?= $__t('ui.wallet_topup_note') ?>
                </div>
                <div id="walletStatus" class="mt-2 text-xs text-gray-500"><?= $__t('ui.wallet_loading') ?></div>
            </div>

            <div class="glass p-6 rounded-3xl border border-white/5">
                <h3 class="text-sm font-black uppercase tracking-widest text-white mb-4"><?= $__t('ui.wallet_packages') ?></h3>
                <div id="walletPackagesGrid" class="grid grid-cols-1 md:grid-cols-3 gap-4"></div>
            </div>

            <div class="glass p-6 rounded-3xl border border-white/5">
                <h3 class="text-sm font-black uppercase tracking-widest text-white mb-4"><?= $__t('ui.wallet_features') ?></h3>
                <div id="walletFeaturesGrid" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
            </div>

            <div class="glass p-6 rounded-3xl border border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black uppercase tracking-widest text-white"><?= $__t('ui.wallet_transactions') ?></h3>
                    <button class="text-[10px] font-bold text-blue-400 uppercase tracking-widest" id="walletLoadMoreBtn" type="button">
                        <?= $__t('ui.wallet_load_more') ?>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[11px] text-gray-400">
                        <thead>
                            <tr class="text-gray-500 uppercase text-[10px]">
                                <th class="text-left py-2"><?= $__t('ui.wallet_tx_time') ?></th>
                                <th class="text-left py-2"><?= $__t('ui.wallet_tx_desc') ?></th>
                                <th class="text-right py-2"><?= $__t('ui.wallet_tx_amount') ?></th>
                            </tr>
                        </thead>
                        <tbody id="walletTransactionsBody">
                            <tr><td colspan="3" class="py-4 text-center text-gray-500"><?= $__t('ui.wallet_loading') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>
