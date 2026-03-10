<?php
/** @var string $q */
/** @var array $results */
/** @var array $footerGenres */
/** @var array $footerTags */
/** @var array $active_genres */
/** @var array $active_tags */
/** @var string $active_status */
/** @var string $active_sort */
/** @var Closure $url */

$query = (string) ($q ?? '');
$items = is_array($results ?? null) ? $results : [];
$activeGenres = is_array($active_genres ?? null) ? $active_genres : [];
$activeTags = is_array($active_tags ?? null) ? $active_tags : [];
$activeStatus = (string) ($active_status ?? 'TÜMÜ');
$activeSort = (string) ($active_sort ?? 'EN YENİLER');
?>

<style>
    .manga-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; }
    .tag-pill { transition: all 0.2s ease; cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.1); }
    .tag-pill.active { background: #3b82f6; color: white; border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
</style>

<main class="pt-12 pb-20 px-6 max-w-7xl mx-auto">
    <!-- Search Header -->
    <div class="mb-12">
        <h1 class="text-4xl md:text-5xl font-black uppercase tracking-tighter text-white mb-4">
            GELİŞMİŞ ARAMA
        </h1>
        <p class="text-gray-500 text-sm font-medium uppercase tracking-widest">
            Binlerce seri arasından hayalindeki hikayeyi bul.
        </p>
    </div>

    <form id="advancedSearchForm" action="<?= $url('search') ?>" method="GET" class="flex flex-col lg:flex-row gap-10">
        <!-- Hidden Inputs for Filters -->
        <input type="hidden" name="genres" id="genresInput" value="<?= htmlspecialchars(implode(',', $activeGenres)) ?>">
        <input type="hidden" name="tags" id="tagsInput" value="<?= htmlspecialchars(implode(',', $activeTags)) ?>">

        <!-- Sidebar Filters -->
        <aside class="w-full lg:w-80 shrink-0 space-y-8">
            <!-- Search Input -->
            <div class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Seri adı veya yazar..." class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 pl-12 pr-4 text-sm focus:outline-none focus:border-blue-500 transition-all text-white" />
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </div>
            </div>

            <!-- Genres -->
            <div>
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-500 mb-4">TÜRLER (GENRE)</h3>
                <div class="flex flex-wrap gap-2">
                    <span class="tag-pill-genre <?= empty($activeGenres) ? 'active' : '' ?> px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tag-pill" data-slug="">HEPSİ</span>
                    <?php foreach ($footerGenres as $genre): ?>
                    <span class="tag-pill-genre <?= in_array($genre['slug'], $activeGenres) ? 'active' : '' ?> px-3 py-1.5 bg-white/5 rounded-lg text-[10px] font-bold uppercase text-gray-400 hover:text-white transition-all tag-pill" data-slug="<?= htmlspecialchars((string)$genre['slug']) ?>">
                        <?= htmlspecialchars((string)$genre['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tags -->
            <div>
                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-500 mb-4">POPÜLER ETİKETLER</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($footerTags as $tag): ?>
                    <span class="tag-pill-tag <?= in_array($tag['slug'], $activeTags) ? 'active' : '' ?> px-3 py-1.5 bg-white/5 rounded-lg text-[10px] font-bold uppercase text-gray-400 hover:text-white transition-all tag-pill" data-slug="<?= htmlspecialchars((string)$tag['slug']) ?>">
                        #<?= htmlspecialchars((string)$tag['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status & Sort -->
            <div class="space-y-4 pt-4 border-t border-white/5">
                <div>
                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2">DURUM</label>
                    <select name="status" class="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 px-4 text-xs focus:outline-none text-white appearance-none">
                        <option value="TÜMÜ" <?= $activeStatus === 'TÜMÜ' ? 'selected' : '' ?>>TÜMÜ</option>
                        <option value="ONGOING" <?= $activeStatus === 'ONGOING' ? 'selected' : '' ?>>DEVAM EDİYOR</option>
                        <option value="COMPLETED" <?= $activeStatus === 'COMPLETED' ? 'selected' : '' ?>>TAMAMLANDI</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block mb-2">SIRALAMA</label>
                    <select name="sort" class="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 px-4 text-xs focus:outline-none text-white appearance-none">
                        <option value="EN YENİLER" <?= $activeSort === 'EN YENİLER' ? 'selected' : '' ?>>EN YENİLER</option>
                        <option value="EN ÇOK OKUNAN" <?= $activeSort === 'EN ÇOK OKUNAN' ? 'selected' : '' ?>>EN ÇOK OKUNANLAR</option>
                        <option value="EN YÜKSEK PUAN" <?= $activeSort === 'EN YÜKSEK PUAN' ? 'selected' : '' ?>>EN YÜKSEK PUAN</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="w-full py-4 bg-blue-600 rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-blue-600/20 hover:bg-blue-500 transition-all text-white">
                FİLTRELERİ UYGULA
            </button>
        </aside>

        <!-- Results Area -->
        <div class="flex-1">
            <div class="flex items-center justify-between mb-8">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                    TOPLAM <span class="text-white"><?= count($items) ?></span> SONUÇ BULUNDU
                </p>
                <div class="flex gap-2">
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 text-blue-500">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    </button>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500">
                        <i data-lucide="list" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Results Grid -->
            <div class="manga-grid">
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <div class="group cursor-pointer" onclick="location.href='<?= $url((string) ($item['url_path'] ?? '')) ?>'">
                        <div class="relative aspect-[3/4.5] rounded-3xl overflow-hidden mb-4 border border-white/5 shadow-2xl group-hover:-translate-y-2 transition-all duration-300 bg-zinc-900">
                            <img src="<?= htmlspecialchars((string)($item['cover_image'] ?? '')) ?>" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-4">
                                <p class="text-[10px] text-blue-400 font-black uppercase mb-1">
                                    <?= htmlspecialchars((string) ($item['type_path'] ?? $item['type'] ?? '')) ?>
                                </p>
                                <p class="text-xs text-white/70 line-clamp-2">
                                    <?= htmlspecialchars((string) ($item['description'] ?? '')) ?>
                                </p>
                            </div>
                            <div class="absolute top-3 left-3 px-2 py-1 bg-blue-600 rounded-lg text-[9px] font-black text-white shadow-xl">
                                <?= number_format((float)($item['rating_avg'] ?? 0), 1) ?>
                            </div>
                        </div>
                        <h4 class="text-sm font-black uppercase tracking-tight text-white group-hover:text-blue-500 transition-colors mb-1">
                            <?= htmlspecialchars((string) ($item['title'] ?? '')) ?>
                        </h4>
                        <p class="text-[10px] text-gray-600 font-bold uppercase tracking-widest">
                            <?= htmlspecialchars((string) ($item['author'] ?? 'Bilinmiyor')) ?> • <?= htmlspecialchars((string) ($item['chapter_count'] ?? '0')) ?> Bölüm
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-20 text-center">
                        <i data-lucide="search-x" class="w-12 h-12 text-gray-600 mx-auto mb-4"></i>
                        <p class="text-gray-500">Aradığınız kriterlere uygun sonuç bulunamadı.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</main>

<script>
    $(document).ready(function () {
        lucide.createIcons();

        function updateInputs() {
            const genres = [];
            $(".tag-pill-genre.active").each(function() {
                const slug = $(this).data("slug");
                if (slug) genres.push(slug);
            });
            $("#genresInput").val(genres.join(','));

            const tags = [];
            $(".tag-pill-tag.active").each(function() {
                const slug = $(this).data("slug");
                if (slug) tags.push(slug);
            });
            $("#tagsInput").val(tags.join(','));
        }

        // Genre selection
        $(".tag-pill-genre").click(function () {
            const slug = $(this).data("slug");
            if (slug === "") {
                $(".tag-pill-genre").removeClass("active bg-blue-600 text-white").addClass("bg-white/5 text-gray-400");
                $(this).addClass("active").removeClass("bg-white/5 text-gray-400");
            } else {
                $(".tag-pill-genre[data-slug='']").removeClass("active bg-blue-600 text-white").addClass("bg-white/5 text-gray-400");
                $(this).toggleClass("active bg-white/5 text-gray-400");

                if ($(".tag-pill-genre.active").length === 0) {
                    $(".tag-pill-genre[data-slug='']").addClass("active").removeClass("bg-white/5 text-gray-400");
                }
            }
            updateInputs();
        });

        // Tag selection
        $(".tag-pill-tag").click(function () {
            $(this).toggleClass("active bg-white/5 text-gray-400");
            updateInputs();
        });
    });
</script>
