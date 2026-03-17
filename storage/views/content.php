<?php
/** @var array $ssr_data */
/** @var array $chapters */
/** @var array $breadcrumbs */
/** @var Closure $url */

$content = is_array($ssr_data ?? null) ? $ssr_data : [];
$chapterItems = is_array($chapters ?? null) ? $chapters : [];
$genres = is_array($content['series_genres'] ?? null) ? $content['series_genres'] : [];
$tags = is_array($content['series_tags'] ?? null) ? $content['series_tags'] : [];

$type = (string)($content['type_path'] ?? $content['type'] ?? 'novel');
$slug = (string)($content['slug'] ?? '');
?>

<!-- Hero Section -->
<section class="relative w-full min-h-[500px] sm:min-h-[650px] flex items-end bg-mesh py-12">
    <div class="absolute inset-0 z-0">
        <img src="<?= htmlspecialchars((string)($content['cover_image'] ?? '')) ?>" class="w-full h-full object-cover opacity-20 blur-3xl" alt="Background" loading="lazy" decoding="async" />
        <div class="absolute inset-0 bg-gradient-to-t from-[#080808] via-transparent to-transparent"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-6 w-full">
        <div class="flex flex-col md:flex-row gap-10 items-end">
            <!-- Poster -->
            <div class="hidden md:block w-72 aspect-[2/3] rounded-[40px] overflow-hidden shadow-2xl border-4 border-white/5 shrink-0 bg-zinc-900">
                <img src="<?= htmlspecialchars((string)($content['cover_image'] ?? '')) ?>" class="w-full h-full object-cover" alt="Poster" loading="lazy" decoding="async" />
            </div>

            <!-- Info -->
            <div class="flex-1 w-full">
                <?php if (!empty($breadcrumbs)): ?>
                <nav class="flex flex-wrap gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <a href="<?= htmlspecialchars((string)($crumb['url'] ?? '#')) ?>" class="hover:text-blue-500"><?= htmlspecialchars((string)$crumb['title']) ?></a>
                        <span class="last:hidden">/</span>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>

                <!-- Title Section -->
                <h1 class="dynamic-title text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black uppercase tracking-tighter text-white mb-4">
                    <?= htmlspecialchars((string) ($content['title'] ?? '')) ?>
                </h1>

                <!-- Tags under title -->
                <?php if (!empty($tags)): ?>
                <div class="flex flex-wrap gap-2 mb-6">
                    <?php foreach (array_slice($tags, 0, 8) as $tag): ?>
                    <a href="<?= $url('tag/' . (string) ($tag['slug'] ?? '')) ?>" class="text-[10px] font-black uppercase text-blue-500/80 hover:text-blue-400 transition-colors">
                        #<?= htmlspecialchars((string) ($tag['name'] ?? '')) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="flex flex-wrap items-center gap-6 mb-8">
                    <div class="flex items-center gap-2">
                        <i data-lucide="star" class="w-5 h-5 text-yellow-500 fill-current"></i>
                        <span class="text-xl font-black"><?= number_format((float)($content['rating_avg'] ?? 0), 2) ?></span>
                        <span class="text-gray-500 text-[10px] font-bold uppercase">(<?= number_format((int)($content['rating_count'] ?? 0)) ?> <?= $__t('ui.votes') ?>)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="eye" class="w-5 h-5 text-blue-500"></i>
                        <span class="text-xl font-black"><?= number_format((int)($content['view_count'] ?? 0)) ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400">
                        <i data-lucide="book-open" class="w-5 h-5"></i>
                        <span class="text-xl font-black"><?= htmlspecialchars((string) ($content['chapter_count'] ?? '0')) ?> <?= $__t('chapter') ?></span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <?php 
                    $firstChapterUrl = "#";
                    if (!empty($chapterItems)) {
                        $firstChapter = end($chapterItems);
                        $firstChapterUrl = $url(sprintf('%s/%s/chapter/%s', $type, $slug, rawurlencode((string)($firstChapter['chapter_number'] ?? '1'))));
                    }
                    ?>
                    <a href="<?= $firstChapterUrl ?>" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs shadow-2xl hover:bg-white hover:text-black transition-all flex items-center gap-3">
                        <i data-lucide="play" class="w-4 h-4 fill-current"></i> <?= $__t('ui.first_chapter') ?>
                    </a>
                    <button id="toggleFollowBtn" class="bg-white/5 border border-white/10 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs hover:bg-white/10 transition-all flex items-center gap-3">
                        <i data-lucide="plus" class="w-4 h-4"></i> <?= $__t('ui.add_to_library') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Body Content -->
<section class="max-w-7xl mx-auto px-6 py-16 grid grid-cols-1 lg:grid-cols-3 gap-16">
    <div class="lg:col-span-2 space-y-16">
        <!-- Synopsis -->
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tighter text-white mb-6 flex items-center gap-3">
                <div class="w-2 h-8 bg-blue-600 rounded-full"></div> <?= $__t('ui.synopsis') ?>
            </h2>
            <p class="text-gray-400 leading-relaxed text-lg">
                <?= htmlspecialchars((string) ($content['description'] ?? $__t('ui.no_description'))) ?>
            </p>
        </div>

        <!-- Chapters -->
        <div>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-black uppercase tracking-tighter text-white flex items-center gap-3">
                    <div class="w-2 h-8 bg-blue-600 rounded-full"></div> <?= $__t('ui.chapters_list') ?>
                </h2>
                <span class="text-xs font-black text-gray-500 tracking-widest uppercase"><?= count($chapterItems) ?> <?= $__t('ui.total_chapters') ?></span>
            </div>

            <div id="chapterList" class="space-y-3">
                <?php foreach ($chapterItems as $chapter): 
                    $isLocked = ($chapter['access']['is_locked'] ?? false);
                    $price = (int)($chapter['chapter_unlock_price'] ?? 0);
                    $chapterPath = sprintf('%s/%s/chapter/%s', $type, $slug, rawurlencode((string)($chapter['chapter_number'] ?? '')));
                    $fullChapterUrl = $url($chapterPath);
                ?>
                <div class="chapter-row flex items-center justify-between p-6 glass rounded-[24px] cursor-pointer transition-all border border-white/5 group"
                     onclick="handleChapterClick('<?= $chapter['id'] ?>', <?= $isLocked ? 'true' : 'false' ?>, <?= $price ?>, '<?= $fullChapterUrl ?>')">
                    <div class="flex items-center gap-6">
                        <span class="w-12 h-12 flex items-center justify-center <?= $isLocked ? 'bg-zinc-800 text-gray-500' : 'bg-blue-600/10 text-blue-500' ?> rounded-2xl font-black">
                            <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? '')) ?>
                        </span>
                        <div>
                            <h4 class="font-black uppercase text-sm <?= $isLocked ? 'group-hover:text-blue-500' : 'text-blue-500' ?> transition-colors">
                                <?= htmlspecialchars((string) ($chapter['title'] ?? $__t('chapter') . ' ' . $chapter['chapter_number'])) ?>
                            </h4>
                            <p class="text-[10px] text-gray-500 font-bold uppercase mt-1">
                                <?= date('d.m.Y', strtotime($chapter['created_at'] ?? 'now')) ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if ($isLocked): ?>
                        <div class="coin-badge flex items-center gap-2 bg-yellow-500 text-black px-3 py-1.5 rounded-xl font-black text-[10px]">
                            <i data-lucide="lock" class="w-3 h-3"></i> <?= $price ?> <?= $__t('ui.coins') ?>
                        </div>
                        <?php else: ?>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-white"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- YORUMLAR -->
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tighter text-white mb-8 flex items-center gap-3">
                <div class="w-2 h-8 bg-blue-600 rounded-full"></div> <?= $__t('comments') ?>
            </h2>
            
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="mb-10 glass p-6 rounded-[32px] border border-white/5">
                <form id="seriesCommentForm" class="space-y-4">
                    <textarea name="body" class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-sm text-white outline-none focus:border-blue-500 min-h-[100px] resize-none" placeholder="<?= $__t('type_your_message') ?>"></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-blue-500 transition-all"><?= $__t('post_comment') ?></button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="mb-10 p-6 glass rounded-[32px] border border-white/5 text-center">
                <p class="text-gray-500 text-sm mb-4"><?= $__t('msg_login_required') ?></p>
                <button onclick="openModal('loginModal')" class="btn btn-sm btn-primary"><?= $__t('login') ?></button>
            </div>
            <?php endif; ?>

            <div id="commentsList" class="space-y-6" data-context="content" data-type="<?= $type ?>" data-slug="<?= $slug ?>">
                <p class="text-gray-500 text-sm"><?= $__t('loading') ?></p>
            </div>
        </div>
    </div>

    <!-- Right: Metadata & Sidebar -->
    <div class="space-y-12">
        <!-- Wallet Box -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="glass rounded-[40px] p-8 border border-white/5">
            <h3 class="font-black uppercase text-sm mb-4 text-yellow-500 tracking-widest"><?= $__t('ui.wallet') ?></h3>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i data-lucide="coins" class="w-8 h-8 text-yellow-500"></i>
                    <div>
                        <p id="sidebarWalletDisplay" class="text-3xl font-black"><?= $_SESSION['user_wallet']['balance'] ?? '0' ?></p>
                        <p class="text-[9px] text-gray-500 font-bold uppercase"><?= $__t('ui.available_coins') ?></p>
                    </div>
                </div>
                <button class="bg-white text-black p-3 rounded-2xl hover:bg-yellow-500 transition-colors">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="glass rounded-[40px] p-8 border border-white/5">
            <h3 class="font-black uppercase text-sm mb-6 text-blue-500 tracking-widest"><?= $__t('ui.detailed_info') ?></h3>
            <div class="space-y-6">
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase"><?= $__t('author') ?></span>
                    <span class="text-sm font-bold"><?= htmlspecialchars((string) ($content['author'] ?? $__t('unknown'))) ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase"><?= $__t('artist') ?></span>
                    <span class="text-sm font-bold"><?= htmlspecialchars((string) ($content['artist'] ?? $__t('unknown'))) ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase"><?= $__t('status') ?></span>
                    <span class="text-sm font-bold <?= (strtolower($content['status'] ?? '') === 'ongoing') ? 'text-green-500' : 'text-blue-500' ?> uppercase">
                        <?= htmlspecialchars((string) ($content['status'] ?? '-')) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase"><?= $__t('ui.type') ?></span>
                    <span class="text-sm font-bold uppercase"><?= htmlspecialchars((string) ($content['type_path'] ?? $content['type'] ?? '')) ?></span>
                </div>
            </div>
        </div>

        <!-- Genres in Sidebar -->
        <?php if (!empty($genres)): ?>
        <div class="glass rounded-[40px] p-8 border border-white/5">
            <h3 class="font-black uppercase text-sm mb-4 text-gray-500"><?= $__t('genres') ?></h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($genres as $genre): ?>
                <a href="<?= $url('genre/' . (string) ($genre['slug'] ?? '')) ?>" class="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-[10px] font-black uppercase text-gray-400 hover:text-white transition-all">
                    <?= htmlspecialchars((string) ($genre['name'] ?? '')) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Purchase Modal -->
<div id="purchaseModal" class="fixed inset-0 z-[100] modal-overlay hidden items-center justify-center p-4">
    <div class="w-full max-w-sm glass border border-white/10 rounded-[40px] p-8 text-center animate-pop">
        <div class="w-20 h-20 bg-yellow-500/20 rounded-3xl flex items-center justify-center mx-auto mb-6">
            <i data-lucide="shopping-cart" class="w-10 h-10 text-yellow-500"></i>
        </div>
        <h3 class="text-2xl font-black uppercase mb-2 text-white"><?= $__t('ui.purchase_chapter') ?></h3>
        <p class="text-gray-400 text-sm mb-8"><?= $__t('ui.purchase_confirm_msg', ['coins' => '<span id="modalPrice" class="text-yellow-500 font-bold">--</span>']) ?></p>
        <div class="flex gap-4">
            <button onclick="closeModal('purchaseModal')" class="flex-1 py-4 bg-white/5 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-white/10 text-white"><?= $__t('ui.cancel') ?></button>
            <button id="confirmPurchase" class="flex-1 py-4 bg-yellow-500 text-black rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-yellow-500/20"><?= $__t('ui.purchase') ?></button>
        </div>
    </div>
</div>
