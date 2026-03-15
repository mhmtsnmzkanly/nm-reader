<?php
/** @var string $langCode */
/** @var string $seoTitle */
/** @var string $seoDescription */
/** @var string $seoKeywords */
/** @var string $seoRobots */
/** @var string $seoCanonical */
/** @var string $seoSiteName */
/** @var string $seoLocale */
/** @var string $seoType */
/** @var string $seoImage */
/** @var string $jsonLd */
/** @var string $content */
/** @var array $siteConfig */
/** @var array $footerGenres */
/** @var array $footerTags */
/** @var array $footerPopular */
/** @var array $footerLatestChapters */
/** @var string $contextJson */
/** @var Closure $url */

$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($langCode ?? 'en'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars((string) $seoKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars((string) $seoRobots, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars((string) $seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Open Graph -->
    <meta property="og:site_name" content="<?= htmlspecialchars((string) $seoSiteName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:locale" content="<?= htmlspecialchars((string) $seoLocale, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars((string) $seoType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars((string) $seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars((string) $seoImage, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars((string) $seoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars((string) $seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars((string) $seoImage, ENT_QUOTES, 'UTF-8') ?>">

    <?php if (!empty($jsonLd)): ?>
        <script type="application/ld+json"><?= $jsonLd ?></script>
    <?php endif; ?>

    <script>
        window.__NMR_CONTEXT = <?= $contextJson ?? '{}' ?>;
    </script>

    <!-- Assets -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">

    <?php if (!empty($siteConfig['integrations']['google_analytics_id'])): ?>
        <!-- Google Analytics (GA4) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($siteConfig['integrations']['google_analytics_id']) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?= htmlspecialchars($siteConfig['integrations']['google_analytics_id']) ?>');
        </script>
    <?php endif; ?>

    <?php if (!empty($siteConfig['integrations']['cloudflare_turnstile_site_key'])): ?>
        <!-- Cloudflare Turnstile -->
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="overflow-x-hidden">
    <!-- HEADER -->
    <header class="fixed top-0 w-full z-[100] h-20 glass flex items-center px-4 md:px-8 justify-between">
        <!-- Logo -->
        <a href="<?= $url('/') ?>" class="flex items-center gap-3 group">
            <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center font-black text-white shadow-lg shadow-blue-600/20 group-hover:rotate-6 transition-transform">
                <?= strtoupper(substr($siteConfig['site_name'] ?? 'M', 0, 1)) ?>
            </div>
            <span class="font-black tracking-tighter text-xl uppercase hidden sm:inline text-white">
                <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP'), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </a>

        <!-- Desktop Navigation -->
        <nav class="hidden lg:flex items-center gap-10">
            <a href="<?= $url('/') ?>" class="text-[11px] font-black uppercase tracking-widest <?= ($currentPath === '/' || str_ends_with($currentPath, '/tr') || str_ends_with($currentPath, '/en')) ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors"><?= $__t('home') ?></a>
            <a href="<?= $url('/search') ?>" class="text-[11px] font-black uppercase tracking-widest <?= str_contains($currentPath, '/search') ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors"><?= $__t('browse') ?></a>
            <a href="<?= $url('/profile') ?>" class="text-[11px] font-black uppercase tracking-widest <?= str_contains($currentPath, '/profile') ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors"><?= $__t('library') ?></a>
            <a href="<?= $url('/blogs') ?>" class="text-[11px] font-black uppercase tracking-widest <?= str_contains($currentPath, '/blogs') ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors"><?= $__t('blogs') ?></a>
        </nav>

        <!-- Right Actions -->
        <div class="flex items-center gap-3 md:gap-5">
            <a href="<?= $url('/search') ?>" class="lg:hidden w-10 h-10 flex items-center justify-center text-gray-400">
                <i data-lucide="search" class="w-6 h-6"></i>
            </a>

            <!-- Language Selector -->
            <div class="relative hidden sm:block">
                <button id="lang-btn" class="flex items-center gap-2 bg-white/5 border border-white/10 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-all">
                    <i data-lucide="languages" class="w-3.5 h-3.5"></i>
                    <span><?= strtoupper($langCode) ?></span>
                    <i data-lucide="chevron-down" class="w-3 h-3 text-gray-600"></i>
                </button>

                <div id="lang-modal" class="lang-dropdown shadow-2xl">
                    <button class="lang-item <?= $langCode === 'tr' ? 'active' : 'inactive' ?>" onclick="switchLanguage('tr')">
                        Türkçe <span>🇹🇷</span>
                    </button>
                    <button class="lang-item <?= $langCode === 'en' ? 'active' : 'inactive' ?>" onclick="switchLanguage('en')">
                        English <span>🇺🇸</span>
                    </button>
                </div>
            </div>

            <div class="relative">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="flex items-center gap-3">
                        <button onclick="openModal('notifModal'); loadNotifications();" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-gray-400 hover:text-white transition-all">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                        </button>

                        <button id="user-btn" class="flex items-center gap-2 p-1 pr-3 bg-white/5 border border-white/10 rounded-2xl hover:bg-white/10 transition-all">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-[10px] font-black text-white shadow-inner">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500 hidden md:block"></i>
                    </button>

                    <!-- User Modal / Dropdown -->
                    <div id="user-modal" style="display: none;" class="absolute right-0 mt-4 w-64 bg-[#121212] border border-white/10 rounded-3xl shadow-2xl p-4 overflow-hidden">
                        <div class="px-3 py-4 border-b border-white/5 mb-2">
                            <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1"><?= $__t('ui.wallet_balance_msg', [':coins' => $_SESSION['user_wallet']['balance'] ?? '0']) ?></p>
                            <p class="text-sm font-black text-white uppercase tracking-tight"><?= htmlspecialchars($_SESSION['username'] ?? $__t('user')) ?></p>
                        </div>
                        <div class="space-y-1">
                            <a href="<?= $url('/profile') ?>" class="user-menu-item">
                                <i data-lucide="user" class="w-4 h-4 text-blue-500"></i> <?= $__t('my_profile') ?>
                            </a>
                            <div onclick="openModal('userSettingsModal')" class="user-menu-item">
                                <i data-lucide="edit-3" class="w-4 h-4 text-emerald-500"></i> <?= $__t('edit_profile') ?>
                            </div>
                            <a href="<?= $url('/profile') ?>" class="user-menu-item">
                                <i data-lucide="book-open" class="w-4 h-4 text-gray-400"></i> <?= $__t('library') ?>
                            </a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <a href="<?= $url('/admin/dashboard') ?>" class="user-menu-item">
                                <i data-lucide="shield-check" class="w-4 h-4 text-red-500"></i> <?= $__t('admin_panel') ?>
                            </a>
                            <?php endif; ?>
                            <div class="h-px bg-white/5 my-2"></div>
                            <a href="#" onclick="logout(); return false;" class="user-menu-item danger">
                                <i data-lucide="log-out" class="w-4 h-4"></i> <?= $__t('logout') ?>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="openAuthBtn" class="bg-white text-black px-6 py-2.5 rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all active:scale-95 shadow-xl shadow-white/5">
                        <?= $__t('login') ?>
                    </button>
                <?php endif; ?>
            </div>

            <button id="menu-toggle" class="lg:hidden w-10 h-10 bg-white/5 rounded-xl flex items-center justify-center border border-white/10">
                <i data-lucide="menu" class="w-6 h-6 text-white"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Navigation Overlay -->
    <div id="mobile-menu" style="display: none;" class="fixed inset-0 z-[90] bg-[#080808] pt-24 px-6">
        <div class="flex flex-col gap-6">
            <a href="<?= $url('/') ?>" class="text-2xl font-black uppercase tracking-tighter <?= ($currentPath === '/' || str_ends_with($currentPath, '/tr') || str_ends_with($currentPath, '/en')) ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4"><?= $__t('home') ?></a>
            <a href="<?= $url('/search') ?>" class="text-2xl font-black uppercase tracking-tighter <?= str_contains($currentPath, '/search') ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4"><?= $__t('browse') ?></a>
            <a href="<?= $url('/profile') ?>" class="text-2xl font-black uppercase tracking-tighter <?= str_contains($currentPath, '/profile') ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4"><?= $__t('library') ?></a>
            <a href="<?= $url('/blogs') ?>" class="text-2xl font-black uppercase tracking-tighter <?= str_contains($currentPath, '/blogs') ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4"><?= $__t('blogs') ?></a>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <main id="content" class="min-h-screen pt-20 flex-grow">
        <?= $content ?? '' ?>
    </main>

    <!-- FOOTER -->
    <footer class="pt-20 pb-12 bg-[#050505] border-t border-white/5 px-6 md:px-12">
        <div class="max-w-7xl mx-auto">
            <!-- Footer Content Columns -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-16">
                <!-- About Section -->
                <div>
                    <h3 class="footer-column-title">
                        <i data-lucide="info" class="w-4 h-4 text-blue-500"></i>
                        <?= $__t('ui.about') ?>
                    </h3>
                    <p class="text-gray-500 text-sm leading-relaxed mb-6">
                        <?= $__t('ui.about_desc', [':site_name' => htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP'))]) ?>
                    </p>
                    <div class="flex gap-4">
                        <a href="#" class="text-gray-600 hover:text-blue-500 transition-colors"><i data-lucide="twitter" class="w-5 h-5"></i></a>
                        <a href="#" class="text-gray-600 hover:text-blue-500 transition-colors"><i data-lucide="github" class="w-5 h-5"></i></a>
                    </div>
                </div>

                <!-- Popular Content -->
                <div>
                    <h3 class="footer-column-title">
                        <i data-lucide="trending-up" class="w-4 h-4 text-orange-500"></i>
                        <?= $__t('ui.popular_content') ?>
                    </h3>
                    <nav class="space-y-3">
                        <?php foreach (array_slice($footerPopular ?? [], 0, 5) as $pop): ?>
                            <a href="<?= $url((string)($pop['url_path'] ?? '')) ?>" class="footer-link flex items-center gap-2">
                                <span class="text-[10px] bg-blue-600/10 text-blue-500 px-1.5 py-0.5 rounded uppercase font-black italic"><?= htmlspecialchars((string)($pop['type_path'] ?? $pop['type'] ?? '')) ?></span>
                                <span class="truncate"><?= htmlspecialchars((string)$pop['title']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- New Chapters -->
                <div>
                    <h3 class="footer-column-title">
                        <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
                        <?= $__t('ui.latest_chapters') ?>
                    </h3>
                    <nav class="space-y-4">
                        <?php 
                        $typeColors = [
                            'manga' => 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]',
                            'novel' => 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.5)]',
                            'web-novel' => 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.5)]',
                            'light-novel' => 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.5)]',
                            'webtoon' => 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]'
                        ];
                        foreach (array_slice($footerLatestChapters ?? [], 0, 4) as $lat): 
                            $rawType = strtolower((string)($lat['series_type'] ?? ''));
                            $dotColor = $typeColors[$rawType] ?? 'bg-gray-500';
                        ?>
                            <a href="<?= $url((string)($lat['series_type'] ?? 'novel') . '/' . (string)($lat['series_slug'] ?? '') . '/chapter/' . rawurlencode((string)($lat['chapter_number'] ?? ''))) ?>" class="footer-link group flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="type-dot <?= $dotColor ?>"></span>
                                    <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest"><?= htmlspecialchars((string)($lat['series_type'] ?? '')) ?></span>
                                </div>
                                <p class="text-[11px] font-black text-white uppercase group-hover:text-blue-500 transition-colors leading-tight">
                                    <?= htmlspecialchars((string)($lat['series_title'] ?? '')) ?> - <?= $__t('chapter') ?> <?= htmlspecialchars((string)($lat['chapter_number'] ?? '')) ?>
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>

            <!-- Horizontal Divider -->
            <hr class="border-white/5 mb-12" />

            <!-- Genre & Tag Cloud Section -->
            <div class="text-center mb-12">
                <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gray-600 mb-8">
                    <?= $__t('ui.explore_categories') ?>
                </p>
                <div class="tag-cloud">
                    <?php 
                    $tagColors = ['tag-blue', 'tag-purple', 'tag-red', 'tag-green', 'tag-orange'];
                    foreach ($footerTags ?? [] as $idx => $tag): 
                        $colorClass = $tagColors[$idx % count($tagColors)];
                    ?>
                        <a href="<?= $url('tag/' . (string)$tag['slug']) ?>" class="footer-tag <?= $colorClass ?>">#<?= htmlspecialchars((string)$tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="text-center pt-8 border-t border-white/5">
                <p class="text-gray-700 text-[10px] font-black uppercase tracking-widest">
                    © <?= date('Y') ?> <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP')) ?> EXPERIENCE • <?= $__t('ui.all_rights_reserved') ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- MODALS -->
    <?php include dirname(__DIR__) . '/views/partials_modals.php'; ?>

    <!-- Feedback Toast Element -->
    <div id="feedback-toast"></div>

    <!-- CORE JS -->
    <script src="/assets/js/app-bundle.js?v=<?= time() ?>"></script>
    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
