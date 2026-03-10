<?php
/** @var array $chapter */
/** @var array $breadcrumbs */
/** @var Closure $url */

$chapterData = is_array($chapter ?? null) ? $chapter : [];
$adjacent = is_array($chapterData['adjacent_chapters'] ?? null) ? $chapterData['adjacent_chapters'] : ['prev' => null, 'next' => null];
$chapterType = (string) ($chapterData['series_type'] ?? '');
$chapterSlug = (string) ($chapterData['series_slug'] ?? '');
$pages = is_array($chapterData['pages'] ?? null) ? $chapterData['pages'] : [];

// Check if it's text-based (Novel)
$isText = (isset($chapterData['type']) && $chapterData['type'] === 'text');

$contentPageUrl = $url($chapterType . '/' . $chapterSlug);
$prevUrl = (!empty($adjacent['prev']) && is_string($adjacent['prev'])) ? $url($chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode($adjacent['prev'])) : null;
$nextUrl = (!empty($adjacent['next']) && is_string($adjacent['next'])) ? $url($chapterType . '/' . $chapterSlug . '/chapter/' . rawurlencode($adjacent['next'])) : null;
?>

<!-- TOP NAVIGATION -->
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
    <?php elseif (!empty($pages)): ?>
        <!-- MANGA MODE VIEW -->
        <section class="manga-vertical flex flex-col items-center">
            <?php foreach ($pages as $page): ?>
                <?php 
                    $src = is_array($page) ? ($page['image_path'] ?? '') : (string)$page; 
                    if (empty($src)) continue;
                ?>
                <img src="<?= htmlspecialchars((string) $src) ?>" alt="Page" class="max-w-4xl border-b border-black loading-lazy" />
            <?php endforeach; ?>
            <div class="py-12 text-center text-gray-600 text-[10px] font-black tracking-widest uppercase">
                Bölüm Sonu - Okuduğunuz İçin Teşekkürler
            </div>
        </section>
    <?php else: ?>
        <div class="py-20 text-center text-gray-500">Bölüm içeriği yüklenemedi.</div>
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
