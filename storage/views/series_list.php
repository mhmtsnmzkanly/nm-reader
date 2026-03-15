<?php
/** @var string $page_heading */
/** @var string $list_type */
/** @var array $items */
/** @var array $breadcrumbs */
/** @var Closure $url */

$heading = (string) ($page_heading ?? 'Listeleme');
$items = is_array($items ?? null) ? $items : [];
?>

<main class="pt-12 pb-20 px-6 max-w-7xl mx-auto">
    <!-- Category Header -->
    <div class="relative mb-16">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
            <div>
                <div class="flex items-center gap-2 text-blue-500 mb-4">
                    <i data-lucide="<?= $list_type === 'genre' ? 'swords' : 'hash' ?>" class="w-5 h-5"></i>
                    <span class="text-[10px] font-black uppercase tracking-[0.4em]"><?= $list_type === 'genre' ? $__t('ui.genre_file') : $__t('ui.tag_file') ?></span>
                </div>
                <h1 class="text-5xl md:text-7xl font-black uppercase tracking-tighter text-white">
                    <?= htmlspecialchars($heading) ?>
                </h1>
                <p class="text-gray-500 mt-4 max-w-xl font-medium leading-relaxed">
                    <?= $__t('ui.category_desc_msg', [':category' => htmlspecialchars($heading)]) ?>
                </p>
            </div>
            <div class="flex items-center gap-4 bg-white/5 p-2 rounded-2xl border border-white/5">
                <div class="px-6 py-3 text-center border-r border-white/10">
                    <div class="text-xl font-black text-white">
                        <?= number_format(count($items)) ?>
                    </div>
                    <div class="text-[8px] font-black text-gray-500 uppercase tracking-widest">
                        <?= $__t('ui.series_count') ?>
                    </div>
                </div>
                <div class="px-6 py-3 text-center">
                    <div class="text-xl font-black text-blue-500">
                        4.8
                    </div>
                    <div class="text-[8px] font-black text-gray-500 uppercase tracking-widest">
                        <?= $__t('ui.avg_rating') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Sorting (Mock for now, functional in Search) -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-12 border-b border-white/5 pb-8">
        <div class="flex items-center gap-3 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 no-scrollbar">
            <button class="filter-btn active px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all">
                <?= $__t('ui.popular') ?>
            </button>
            <button class="filter-btn px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all text-gray-500 hover:text-white hover:border-white/20">
                <?= $__t('ui.newest') ?>
            </button>
            <button class="filter-btn px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all text-gray-500 hover:text-white hover:border-white/20">
                <?= $__t('score') ?>
            </button>
            <button class="filter-btn px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all text-gray-500 hover:text-white hover:border-white/20">
                <?= $__t('completed') ?>
            </button>
        </div>
    </div>

    <!-- Manga Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
        <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): ?>
            <div class="manga-card group cursor-pointer" onclick="location.href='<?= $url((string) ($item['url_path'] ?? '')) ?>'">
                <div class="manga-image-container mb-4 shadow-2xl">
                    <img src="<?= htmlspecialchars((string)($item['cover_image'] ?? '')) ?>" alt="<?= htmlspecialchars((string)$item['title']) ?>" />
                    <div class="card-overlay">
                        <button class="w-full bg-white text-black py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transform translate-y-4 group-hover:translate-y-0 transition-transform">
                            <?= $__t('ui.read_now') ?>
                        </button>
                    </div>
                </div>
                <h3 class="font-black uppercase text-sm truncate text-white group-hover:text-blue-500 transition-colors">
                    <?= htmlspecialchars((string)$item['title']) ?>
                </h3>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">
                        <?= htmlspecialchars((string) ($item['chapter_count'] ?? '0')) ?> <?= $__t('chapter') ?>
                    </p>
                    <div class="flex items-center gap-1 text-[10px] text-yellow-500 font-black">
                        <i data-lucide="star" class="w-3 h-3 fill-current"></i>
                        <?= number_format((float)($item['rating_avg'] ?? 0), 1) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-20 text-center">
                <i data-lucide="info" class="w-12 h-12 text-gray-600 mx-auto mb-4"></i>
                <p class="text-gray-500"><?= $__t('ui.no_content_in_category') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination (Static for now) -->
    <div class="mt-20 flex justify-center items-center gap-2">
        <button class="w-12 h-12 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-gray-500 hover:bg-white/10 transition-all">
            <i data-lucide="chevron-left" class="w-5 h-5"></i>
        </button>
        <button class="w-12 h-12 rounded-xl bg-blue-600 text-white font-black text-sm shadow-lg shadow-blue-600/20">
            1
        </button>
        <button class="w-12 h-12 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-gray-500 hover:bg-white/10 transition-all">
            <i data-lucide="chevron-right" class="w-5 h-5"></i>
        </button>
    </div>
</main>
