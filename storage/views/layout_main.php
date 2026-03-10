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
/** @var Closure $url */
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

    <!-- Assets -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap");
        body { font-family: "Inter", sans-serif; background-color: #080808; color: #e3e2e6; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .modal-overlay { background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        @keyframes modalShow { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-modal { animation: modalShow 0.3s ease-out forwards; }
    </style>
</head>
<body class="overflow-x-hidden">
    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 h-20 glass border-b border-white/5">
        <div class="max-w-7xl mx-auto px-6 h-full flex items-center justify-between">
            <!-- Logo -->
            <a href="<?= $url('/') ?>" class="flex items-center gap-3 group">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center font-black italic text-white shadow-lg shadow-blue-600/20 group-hover:rotate-6 transition-transform">
                    <?= strtoupper(substr($siteConfig['site_name'] ?? 'M', 0, 1)) ?>
                </div>
                <span class="font-black tracking-tighter text-xl uppercase italic hidden sm:inline text-white">
                    <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center gap-10">
                <a href="<?= $url('/') ?>" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] <?= $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/tr' || $_SERVER['REQUEST_URI'] === '/en' ? 'text-blue-500' : 'text-gray-500 hover:text-white' ?> transition-colors">
                    <i data-lucide="compass" class="w-5 h-5"></i> KEŞFET
                </a>
                <a href="<?= $url('/blogs') ?>" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/blogs') ? 'text-blue-500' : 'text-gray-500 hover:text-white' ?> transition-colors">
                    <i data-lucide="newspaper" class="w-5 h-5"></i> BLOG
                </a>
                <a href="<?= $url('/profile') ?>" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/profile') ? 'text-blue-500' : 'text-gray-500 hover:text-white' ?> transition-colors">
                    <i data-lucide="book-open" class="w-5 h-5"></i> KİTAPLIK
                </a>
                <a href="<?= $url('/search') ?>" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/search') ? 'text-blue-500' : 'text-gray-500 hover:text-white' ?> transition-colors">
                    <i data-lucide="search" class="w-5 h-5"></i> ARA
                </a>
            </nav>

            <!-- Auth Actions -->
            <div class="flex items-center gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- User Coins -->
                    <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 px-4 py-2 rounded-2xl">
                        <i data-lucide="coins" class="w-4 h-4 text-yellow-500"></i>
                        <span id="headerUserBalance" class="text-xs font-black text-yellow-500"><?= $_SESSION['user_wallet']['balance'] ?? '0' ?></span>
                    </div>
                    <a href="<?= $url('/profile') ?>" class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center font-bold text-xs shadow-lg shadow-blue-600/20 cursor-pointer text-white">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
                    </a>
                <?php else: ?>
                    <button id="openAuthBtn" class="bg-white text-black px-6 py-2.5 rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all active:scale-95 shadow-xl shadow-white/5">
                        GİRİŞ YAP
                    </button>
                <?php endif; ?>
                <button class="md:hidden p-2 text-gray-400">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT AREA -->
    <main id="content" class="min-h-screen pt-20">
        <?= $content ?? '' ?>
    </main>

    <!-- FOOTER -->
    <footer class="py-20 border-t border-white/5 px-6 text-center bg-[#050505]">
        <div class="font-black italic text-white/20 uppercase tracking-[0.5em] text-sm mb-4">
            <?= htmlspecialchars(strtoupper($siteConfig['site_name'] ?? 'MANGA.APP'), ENT_QUOTES, 'UTF-8') ?> EXPERIENCE
        </div>
        <p class="text-gray-600 text-[10px] font-bold uppercase tracking-widest">
            © <?= date('Y') ?> Tüm Hakları Saklıdır.
        </p>
        <div class="flex justify-center gap-6 mt-8">
            <a href="#" class="text-gray-500 hover:text-blue-500 transition-colors"><i data-lucide="twitter" class="w-5 h-5"></i></a>
            <a href="#" class="text-gray-500 hover:text-blue-500 transition-colors"><i data-lucide="instagram" class="w-5 h-5"></i></a>
            <a href="#" class="text-gray-500 hover:text-blue-500 transition-colors"><i data-lucide="github" class="w-5 h-5"></i></a>
        </div>
    </footer>

    <script>
        $(document).ready(function () {
            lucide.createIcons();
            
            $("#openAuthBtn").on("click", function () {
                if (window.NMR && window.NMR.showAuthModal) {
                    window.NMR.showAuthModal();
                } else {
                    console.warn('Auth system not initialized in app-bundle.js');
                }
            });
        });
    </script>
</body>
</html>
