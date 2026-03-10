<?php
/** @var array $home_data */
/** @var Closure $url */

$homeData = is_array($home_data ?? null) ? $home_data : [];
$explore = is_array($homeData['explore'] ?? null) ? $homeData['explore'] : [];
$recentChapters = is_array($homeData['recent_chapters'] ?? null) ? $homeData['recent_chapters'] : [];
$recentlyAdded = is_array($homeData['recently_added'] ?? null) ? $homeData['recently_added'] : [];
$popularBlogs = is_array($homeData['popular_blogs'] ?? null) ? $homeData['popular_blogs'] : [];
$latestBlogs = is_array($homeData['latest_blogs'] ?? null) ? $homeData['latest_blogs'] : [];
?>

<style>
    /* Slide Transition */
    .slide-item { display: none; animation: fadeEffect 1s; }
    @keyframes fadeEffect { from { opacity: 0.4; } to { opacity: 1; } }

    /* Manga Card Hover Effect */
    .manga-card:hover .card-overlay { opacity: 1; }
    .manga-card:hover img { transform: scale(1.1); }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
    <!-- TREND SLIDER (Explore Data) -->
    <?php if (!empty($explore)): ?>
    <section id="trendSlider" class="relative w-full h-[400px] sm:h-[550px] rounded-[40px] sm:rounded-[60px] overflow-hidden mb-16 shadow-2xl bg-zinc-900">
        <?php foreach (array_slice($explore, 0, 5) as $idx => $item): ?>
        <div class="slide-item relative h-full w-full">
            <img src="<?= htmlspecialchars((string)($item['cover_image'] ?? '')) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars((string)($item['title'] ?? '')) ?>" />
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent flex flex-col justify-end p-8 sm:p-16">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="flame" class="text-orange-500 w-5 h-5 fill-current"></i>
                    <span class="text-orange-500 text-[10px] font-black uppercase tracking-[0.3em]">Trend #<?= $idx + 1 ?></span>
                </div>
                <h1 class="text-5xl sm:text-7xl font-black italic uppercase tracking-tighter text-white mb-6 truncate max-w-full">
                    <?= htmlspecialchars((string)($item['title'] ?? '')) ?>
                </h1>
                <p class="text-gray-300 max-w-lg text-sm sm:text-base italic mb-10 line-clamp-2">
                    <?= htmlspecialchars((string)($item['description'] ?? '')) ?>
                </p>
                <a href="<?= $url((string)($item['url_path'] ?? '')) ?>" class="bg-blue-600 text-white px-10 py-5 rounded-2xl font-black uppercase italic text-xs w-fit shadow-2xl hover:bg-white hover:text-black transition-all flex items-center gap-3">
                    <i data-lucide="play" class="w-4 h-4 fill-current"></i> ŞİMDİ OKU
                </a>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Slider Controls -->
        <div class="absolute bottom-8 right-8 sm:right-16 flex gap-3">
            <?php foreach (array_slice($explore, 0, 5) as $idx => $item): ?>
            <div class="dot w-3 h-3 rounded-full bg-white/20 cursor-pointer transition-all hover:bg-white/50" data-idx="<?= $idx ?>"></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- RECENT CHAPTERS (Dynamic List) -->
    <section class="mb-16">
        <div class="flex items-center gap-3 mb-8">
            <div class="p-2 bg-orange-600/20 rounded-xl">
                <i data-lucide="zap" class="text-orange-600 w-5 h-5"></i>
            </div>
            <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white">Yeni Bölümler</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach (array_slice($recentChapters, 0, 9) as $chapter): 
                $chapterUrl = sprintf('%s/%s/chapter/%s', 
                    (string)($chapter['type_path'] ?? 'novel'), 
                    (string)($chapter['slug'] ?? ''), 
                    rawurlencode((string)($chapter['chapter_number'] ?? ''))
                );
            ?>
            <a href="<?= $url($chapterUrl) ?>" 
               class="glass p-4 rounded-2xl flex items-center gap-4 hover:border-blue-500/50 transition-all group">
                <div class="w-12 h-16 bg-zinc-800 rounded-lg overflow-hidden flex-shrink-0">
                    <img src="<?= htmlspecialchars((string)($chapter['cover_image'] ?? '')) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform" />
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-sm font-black text-white/90 uppercase italic truncate"><?= htmlspecialchars((string)($chapter['series_title'] ?? '')) ?></h4>
                    <p class="text-[10px] text-blue-500 font-black uppercase tracking-widest mt-1">Bölüm <?= htmlspecialchars((string)($chapter['chapter_number'] ?? '')) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- RECENTLY ADDED (Manga Grid) -->
    <section class="mb-16">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-600/20 rounded-xl">
                    <i data-lucide="compass" class="text-blue-600 w-5 h-5"></i>
                </div>
                <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white">Son Eklenenler</h2>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6 sm:gap-8">
            <?php foreach ($recentlyAdded as $item): ?>
            <div class="manga-card group cursor-pointer" onclick="location.href='<?= $url((string)($item['url_path'] ?? '')) ?>'">
                <div class="aspect-[2/3] rounded-[32px] sm:rounded-[40px] overflow-hidden border border-white/5 bg-zinc-900 mb-4 relative shadow-xl">
                    <img src="<?= htmlspecialchars((string)($item['cover_image'] ?? '')) ?>" class="w-full h-full object-cover transition-transform duration-500" alt="<?= htmlspecialchars((string)($item['title'] ?? '')) ?>" />
                    <div class="card-overlay absolute inset-0 bg-black/60 opacity-0 transition-opacity flex items-center justify-center p-4">
                        <span class="bg-blue-600 text-white w-full py-3 rounded-2xl text-[10px] font-black text-center uppercase tracking-widest translate-y-4 group-hover:translate-y-0 transition-transform">
                            OKUMAYA BAŞLA
                        </span>
                    </div>
                    <?php if (!empty($item['chapter_count'])): ?>
                    <div class="absolute top-4 left-4 bg-blue-600/90 text-[8px] font-black px-2 py-1 rounded-lg uppercase backdrop-blur-sm">
                        <?= $item['chapter_count'] ?> BÖLÜM
                    </div>
                    <?php endif; ?>
                </div>
                <h3 class="font-black uppercase italic text-sm truncate text-white/90"><?= htmlspecialchars((string)($item['title'] ?? '')) ?></h3>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-[10px] text-gray-500 font-bold uppercase"><?= htmlspecialchars((string)($item['type_path'] ?? $item['type'] ?? '')) ?></p>
                    <div class="flex items-center gap-1 text-[10px] text-yellow-500 font-black">
                        <i data-lucide="star" class="w-3 h-3 fill-current"></i>
                        <?= number_format((float)($item['rating_avg'] ?? 0), 1) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- BLOGS SECTION -->
    <section>
        <div class="flex items-center gap-3 mb-8">
            <div class="p-2 bg-purple-600/20 rounded-xl">
                <i data-lucide="newspaper" class="text-purple-600 w-5 h-5"></i>
            </div>
            <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white">Popüler Bloglar</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($popularBlogs as $blog): ?>
            <a href="<?= $url('blogs/' . (string) ($blog['slug'] ?? '')) ?>" class="glass p-6 rounded-[32px] flex flex-col gap-4 hover:border-purple-500/50 transition-all">
                <h3 class="text-lg font-black text-white uppercase italic leading-tight"><?= htmlspecialchars((string)($blog['title'] ?? '')) ?></h3>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-[10px] font-black">
                        <?= strtoupper(substr($blog['author_username'] ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?= htmlspecialchars((string)($blog['author_username'] ?? '')) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
    $(document).ready(function () {
        // SLIDESHOW LOGIC
        let currentSlide = 0;
        const slides = $(".slide-item");
        const dots = $(".dot");

        if (slides.length > 0) {
            function showSlide(n) {
                slides.hide();
                dots.removeClass("bg-blue-600 w-8").addClass("bg-white/20 w-3");
                currentSlide = (n + slides.length) % slides.length;
                $(slides[currentSlide]).fadeIn(1000);
                $(dots[currentSlide]).removeClass("bg-white/20 w-3").addClass("bg-blue-600 w-8");
            }

            function nextSlide() { showSlide(currentSlide + 1); }
            let slideInterval = setInterval(nextSlide, 5000);

            dots.on("click", function () {
                clearInterval(slideInterval);
                showSlide($(this).data("idx"));
                slideInterval = setInterval(nextSlide, 5000);
            });

            showSlide(0);
        }
    });
</script>
