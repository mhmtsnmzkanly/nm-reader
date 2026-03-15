<?php
/** @var array $ssr_data */
/** @var array $breadcrumbs */
/** @var Closure $url */

$data = is_array($ssr_data ?? null) ? $ssr_data : [];
$isList = isset($data['blog_list']);
$blogList = $isList && is_array($data['blog_list']) ? $data['blog_list'] : [];
?>

<?php if ($isList): ?>
    <!-- BLOG LIST VIEW -->
    <div class="max-w-7xl mx-auto px-6 py-12">
        <!-- Hero Blog Section (Featured) -->
        <?php if (!empty($blogList)): 
            $featured = $blogList[0];
        ?>
        <section class="mb-16">
            <div class="relative w-full h-[400px] md:h-[500px] rounded-[40px] overflow-hidden group shadow-2xl">
                <img src="<?= htmlspecialchars((string)($featured['cover_image'] ?: 'https://images.unsplash.com/photo-1578632292335-df3abbb0d586?q=80&w=1200')) ?>" class="w-full h-full object-cover" alt="Featured Blog">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
                <div class="absolute bottom-0 left-0 p-8 md:p-12 max-w-2xl">
                    <span class="px-4 py-1.5 bg-blue-600 text-white rounded-full text-[10px] font-black uppercase tracking-widest mb-4 inline-block"><?= $__t('ui.featured') ?></span>
                    <h2 onclick="location.href='<?= $url('blogs/' . (string)($featured['slug'] ?? '')) ?>'" class="text-3xl md:text-5xl font-black uppercase tracking-tighter text-white mb-4 leading-none group-hover:text-blue-400 transition-colors cursor-pointer">
                        <?= htmlspecialchars((string)($featured['title'] ?? '')) ?>
                    </h2>
                    <p class="text-gray-300 text-sm md:text-base line-clamp-2 mb-6">
                        <?= htmlspecialchars(mb_substr(trim(strip_tags((string) ($featured['body'] ?? ''))), 0, 200)) ?>...
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-zinc-800 border border-white/10 flex items-center justify-center text-[10px] font-black">
                            <?= strtoupper(substr($featured['author_username'] ?? 'U', 0, 1)) ?>
                        </div>
                        <span class="text-xs font-bold text-white"><?= htmlspecialchars((string)($featured['author_username'] ?? '')) ?></span>
                        <span class="text-xs text-gray-500 font-bold uppercase">• <?= date('d M Y', strtotime($featured['approved_at'] ?? $featured['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Blog Grid -->
        <section class="pb-24">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach (array_slice($blogList, 1) as $blog): ?>
                <article onclick="location.href='<?= $url('blogs/' . (string)($blog['slug'] ?? '')) ?>'" class="blog-card glass rounded-[32px] overflow-hidden border border-white/5 flex flex-col cursor-pointer">
                    <div class="relative h-56 overflow-hidden">
                        <img src="<?= htmlspecialchars((string)($blog['cover_image'] ?: 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?q=80&w=800')) ?>" class="w-full h-full object-cover" alt="Blog Thumb">
                        <div class="absolute top-4 left-4 px-3 py-1 bg-black/60 backdrop-blur-md rounded-lg text-[9px] font-black text-white uppercase"><?= $__t('ui.review') ?></div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white mb-3 line-clamp-2 leading-tight">
                            <?= htmlspecialchars((string)($blog['title'] ?? '')) ?>
                        </h3>
                        <p class="text-gray-500 text-sm line-clamp-3 mb-6 flex-1">
                            <?= htmlspecialchars(mb_substr(trim(strip_tags((string) ($blog['body'] ?? ''))), 0, 150)) ?>...
                        </p>
                        <div class="flex items-center justify-between pt-4 border-t border-white/5">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest"><?= $__t('ui.continue_reading_action') ?></span>
                            <span class="text-[9px] text-gray-600 font-bold uppercase"><?= date('d M Y', strtotime($blog['approved_at'] ?? $blog['created_at'] ?? 'now')) ?></span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

<?php else: ?>
    <!-- SINGLE BLOG POST VIEW -->
    <div class="progress-bar" id="readingProgress" style="width: 0%"></div>

    <main class="pt-12 pb-24">
        <!-- Article Header -->
        <header class="max-w-4xl mx-auto px-6 text-center mb-16">
            <div class="flex justify-center gap-3 mb-8">
                <span class="px-4 py-1 bg-blue-600/20 text-blue-500 border border-blue-500/20 rounded-full text-[10px] font-black uppercase tracking-[0.2em]"><?= $__t('ui.review') ?></span>
                <span class="text-gray-500 text-[10px] font-black uppercase tracking-[0.2em] flex items-center gap-2">
                    <i data-lucide="clock" class="w-3 h-3"></i> <?= $__t('ui.read_time_msg', ['time' => '5']) ?>
                </span>
            </div>

            <h1 class="text-4xl md:text-6xl font-black uppercase tracking-tighter text-white mb-8 leading-tight">
                <?= htmlspecialchars((string) ($data['title'] ?? '')) ?>
            </h1>

            <div class="flex items-center justify-center gap-4 py-8 border-y border-white/5">
                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-zinc-800 border border-white/10 flex items-center justify-center text-xs font-black">
                    <?= strtoupper(substr($data['author_username'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="text-left">
                    <p class="text-sm font-black text-white uppercase tracking-tighter"><?= htmlspecialchars((string) ($data['author_username'] ?? '')) ?></p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                        <?= $__t('ui.editor') ?> • <?= date('d F Y', strtotime($data['approved_at'] ?? $data['created_at'] ?? 'now')) ?>
                    </p>
                </div>
            </div>
        </header>

        <!-- Featured Image -->
        <div class="max-w-6xl mx-auto px-6 mb-16">
            <div class="aspect-[21/9] rounded-[48px] overflow-hidden border border-white/5 shadow-2xl bg-zinc-900">
                <img src="<?= htmlspecialchars((string)($data['cover_image'] ?: 'https://images.unsplash.com/photo-1614850523296-d8c1af93d400?q=80&w=1200')) ?>" class="w-full h-full object-cover" alt="Featured Image" />
            </div>
        </div>

        <!-- Article Content -->
        <article class="max-w-3xl mx-auto px-6 article-content serif-text">
            <div class="prose prose-invert max-w-none">
                <?= nl2br((string) ($data['body'] ?? '')) ?>
            </div>

            <?php /* Editor Note and Spoiler hidden as requested
            <div class="my-12 relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-600/10 to-indigo-600/10 rounded-[32px] blur-xl"></div>
                <div class="relative p-8 glass rounded-[32px] border border-blue-500/30 flex gap-6 items-start">
                    <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shrink-0 shadow-lg shadow-blue-600/30">
                        <i data-lucide="info" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-blue-500 uppercase text-xs mb-2 tracking-widest">EDİTÖRÜN KRİTİK NOTU</h4>
                        <p class="text-sm text-gray-200 leading-relaxed font-medium mb-0">Örnek editör notu...</p>
                    </div>
                </div>
            </div>

            <div class="my-12 spoiler-box glass border border-red-500/20 rounded-[32px] p-8" onclick="this.classList.add('revealed')">
                <div class="spoiler-overlay">
                    <div class="text-center">
                        <div class="w-14 h-14 bg-red-600/20 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30">
                            <i data-lucide="eye-off" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <p class="font-black text-[10px] uppercase tracking-[0.3em] text-red-500 mb-2">DİKKAT: SÜRPRİZBOZAN (SPOILER)</p>
                        <p class="text-xs text-gray-500 font-bold uppercase">GÖRMEK İÇİN TIKLAYIN</p>
                    </div>
                </div>
                <div class="spoiler-content text-gray-300">
                    <p class="mb-0">Örnek spoiler içeriği...</p>
                </div>
            </div>
            */ ?>
        </article>

        <!-- Social Share & Tags -->
        <footer class="max-w-3xl mx-auto px-6 mt-16 pt-12 border-t border-white/5 flex flex-wrap items-center justify-between gap-6">
            <div class="flex gap-2">
                <span class="px-3 py-1 bg-zinc-900 rounded-lg text-[10px] font-bold text-gray-400 uppercase">#BLOG</span>
                <span class="px-3 py-1 bg-zinc-900 rounded-lg text-[10px] font-bold text-gray-400 uppercase">#INCELEME</span>
            </div>
        </footer>
    </main>
<?php endif; ?>
