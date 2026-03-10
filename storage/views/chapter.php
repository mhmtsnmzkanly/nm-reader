<?php
/** @var array $chapter */
/** @var array $breadcrumbs */
/** @var Closure $url */

$chapterData = is_array($chapter ?? null) ? $chapter : [];
$adjacent = is_array($chapterData['adjacent_chapters'] ?? null) ? $chapterData['adjacent_chapters'] : ['prev' => null, 'next' => null];
$chapterType = (string) ($chapterData['series_type'] ?? '');
$chapterSlug = (string) ($chapterData['series_slug'] ?? '');
$pages = is_array($chapterData['pages'] ?? null) ? $chapterData['pages'] : [];
$isText = ($chapterData['type'] ?? '') === 'text';

$contentPageUrl = $url($chapterType . '/' . $chapterSlug);
$prevUrl = !empty($adjacent['prev']) ? $url($chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode((string) $adjacent['prev'])) : null;
$nextUrl = !empty($adjacent['next']) ? $url($chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode((string) $adjacent['next'])) : null;
?>

<style>
    @import url("https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,700;1,400&display=swap");
    .novel-text { font-family: "Lora", serif; font-size: 1.25rem; line-height: 2; max-width: 800px; margin: 0 auto; color: #d1d1d1; }
    .novel-text p { margin-bottom: 1.5rem; }
    .manga-vertical img { width: 100%; display: block; margin: 0 auto; }
    .reader-controls { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); z-index: 100; }
    .glass-nav { background: rgba(15, 15, 15, 0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
</style>

<!-- TOP NAVIGATION OVERRIDE (Hidden by CSS, logic in layout might need tweak but for now we put it inside main) -->
<div class="fixed top-0 left-0 w-full z-[60] h-16 glass-nav flex items-center px-6 justify-between">
    <div class="flex items-center gap-4">
        <a href="<?= $contentPageUrl ?>" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white/5 transition-all text-white">
            <i data-lucide="chevron-left" class="w-6 h-6"></i>
        </a>
        <div>
            <h1 class="text-sm font-black uppercase tracking-tighter text-white leading-none">
                <?= htmlspecialchars((string) ($chapterData['series_title'] ?? '')) ?>
            </h1>
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">
                Bölüm <?= htmlspecialchars((string) ($chapterData['chapter_number'] ?? '')) ?>: <?= htmlspecialchars((string) ($chapterData['title'] ?? '')) ?>
            </p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white/5 text-gray-400">
            <i data-lucide="settings-2" class="w-5 h-5"></i>
        </button>
        <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-600/20 text-white">
            <i data-lucide="bookmark" class="w-5 h-5"></i>
        </button>
    </div>
</div>

<main class="pt-24 pb-32 min-h-screen">
    <?php if ($isText): ?>
        <!-- NOVEL MODE VIEW -->
        <section class="px-6">
            <article class="novel-text prose prose-invert">
                <?= nl2br(htmlspecialchars((string) ($chapterData['body'] ?? ''))) ?>
            </article>
        </section>
    <?php else: ?>
        <!-- MANGA MODE VIEW -->
        <section class="manga-vertical flex flex-col items-center">
            <?php foreach ($pages as $page): ?>
                <img src="<?= htmlspecialchars((string) $page) ?>" alt="Page" class="max-w-4xl border-b border-black loading-lazy" />
            <?php endforeach; ?>
            <div class="py-12 text-center text-gray-600 text-[10px] font-black tracking-widest uppercase">
                Bölüm Sonu - Okuduğunuz İçin Teşekkürler
            </div>
        </section>
    <?php endif; ?>
</main>

<!-- READER CONTROLS -->
<div class="reader-controls flex items-center gap-4">
    <div class="glass border border-white/10 p-2 rounded-[24px] flex items-center gap-2 shadow-2xl">
        <a href="<?= $prevUrl ?: '#' ?>" class="<?= !$prevUrl ? 'pointer-events-none opacity-20' : '' ?> w-12 h-12 flex items-center justify-center rounded-2xl hover:bg-white/5 transition-all text-white">
            <i data-lucide="chevron-left" class="w-5 h-5"></i>
        </a>

        <div class="px-6 flex flex-col items-center">
            <span class="text-[9px] font-black text-blue-500 tracking-[0.2em] uppercase mb-1">İLERLEME</span>
            <div class="flex items-center gap-3">
                <div class="w-32 h-1 bg-white/5 rounded-full overflow-hidden">
                    <div id="reader-progress-bar" class="h-full bg-blue-600" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <a href="<?= $nextUrl ?: '#' ?>" class="<?= !$nextUrl ? 'pointer-events-none opacity-20' : '' ?> w-12 h-12 flex items-center justify-center rounded-2xl hover:bg-white/5 transition-all text-white">
            <i data-lucide="chevron-right" class="w-5 h-5"></i>
        </a>
    </div>
</div>

<script>
    $(document).ready(function () {
        lucide.createIcons();

        // Progress tracking
        $(window).scroll(function () {
            let winHeight = $(window).height();
            let docHeight = $(document).height();
            let scrollTop = $(window).scrollTop();
            let progress = (scrollTop / (docHeight - winHeight)) * 100;
            $("#reader-progress-bar").css("width", progress + "%");
        });
    });
</script>
