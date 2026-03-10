<?php
/** @var string $page_heading */
/** @var string $list_type */
/** @var array $items */
/** @var array $breadcrumbs */
/** @var Closure $url */

$heading = (string) ($page_heading ?? 'Listeleme');
$items = is_array($items ?? null) ? $items : [];
?>

<style>
    .manga-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .manga-card:hover { transform: translateY(-10px); }
    .manga-image-container { position: relative; aspect-ratio: 2/3; border-radius: 24px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.05); background: #121212; }
    .manga-image-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s; }
    .manga-card:hover img { transform: scale(1.1); }
    .card-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, transparent 60%); opacity: 0; transition: opacity 0.3s; display: flex; align-items: flex-end; padding: 20px; }
    .manga-card:hover .card-overlay { opacity: 1; }
    .filter-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
</style>

<main class="pt-12 pb-20 px-6 max-w-7xl mx-auto">
    <!-- Category Header -->
    <div class="relative mb-16">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
            <div>
                <div class="flex items-center gap-2 text-blue-500 mb-4">
                    <i data-lucide="<?= $list_type === 'genre' ? 'swords' : 'hash' ?>" class="w-5 h-5"></i>
                    <span class="text-[10px] font-black uppercase tracking-[0.4em]"><?= $list_type === 'genre' ? 'Tür Dosyası' : 'Etiket Dosyası' ?></span>
                </div>
                <h1 class="text-5xl md:text-7xl font-black italic uppercase tracking-tighter text-white">
                    <?= htmlspecialchars($heading) ?>
                </h1>
                <p class="text-gray-500 mt-4 max-w-xl font-medium leading-relaxed italic">
                    <?= htmlspecialchars($heading) ?> ile ilgili en iyi serileri burada bulabilirsin. Adrenalin, gizem ve heyecan dolu bir yolculuğa hazır ol.
                </p>
            </div>
            <div class="flex items-center gap-4 bg-white/5 p-2 rounded-2xl border border-white/5">
                <div class="px-6 py-3 text-center border-r border-white/10">
                    <div class="text-xl font-black text-white italic">
                        <?= number_format(count($items)) ?>
                    </div>
                    <div class="text-[8px] font-black text-gray-500 uppercase tracking-widest">
                        SERİ
                    </div>
                </div>
                <div class="px-6 py-3 text-center">
                    <div class="text-xl font-black text-blue-500 italic">
                        4.8
                    </div>
                    <div class="text-[8px] font-black text-gray-500 uppercase tracking-widest">
                        ORT. PUAN
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Sorting (Mock for now, functional in Search) -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-12 border-b border-white/5 pb-8">
        <div class="flex items-center gap-3 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 no-scrollbar">
            <button class="filter-btn active px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all">
                Popüler
            </button>
            <button class="filter-btn px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all text-gray-500 hover:text-white hover:border-white/20">
                En Yeniler
            </button>
            <button class="filter-btn px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all text-gray-500 hover:text-white hover:border-white/20">
                Puan
            </button>
            <button class="filter-btn px-6 py-2.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all text-gray-500 hover:text-white hover:border-white/20">
                Tamamlanmış
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
                        <button class="w-full bg-white text-black py-3 rounded-xl font-black italic text-[10px] uppercase tracking-widest transform translate-y-4 group-hover:translate-y-0 transition-transform">
                            HEMEN OKU
                        </button>
                    </div>
                </div>
                <h3 class="font-black uppercase italic text-sm truncate text-white group-hover:text-blue-500 transition-colors">
                    <?= htmlspecialchars((string)$item['title']) ?>
                </h3>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">
                        <?= htmlspecialchars((string) ($item['chapter_count'] ?? '0')) ?> Bölüm
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
                <p class="text-gray-500 italic">Bu kategoride henüz içerik bulunmuyor.</p>
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

<script>
    $(document).ready(function () {
        lucide.createIcons();

        // Filter buttons toggle
        $(".filter-btn").click(function () {
            $(".filter-btn").removeClass("active text-white").addClass("text-gray-500");
            $(this).addClass("active text-white").removeClass("text-gray-500");
        });
    });
</script>
