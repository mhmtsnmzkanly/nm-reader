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

    <!-- Assets -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap");
        body { font-family: "Inter", sans-serif; background-color: #080808; color: #e3e2e6; }
        .glass { background: rgba(10, 10, 10, 0.7); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .modal-overlay { background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        @keyframes modalShow { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-modal { animation: modalShow 0.3s ease-out forwards; }

        /* Mobile Menu Overlay */
        #mobile-menu, #user-modal { display: none; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .user-menu-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 12px; transition: all 0.2s; color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .user-menu-item:hover { background: rgba(255, 255, 255, 0.05); color: white; }
        .user-menu-item.danger:hover { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    </style>
</head>
<body class="overflow-x-hidden">
    <!-- HEADER -->
    <header class="fixed top-0 w-full z-[100] h-20 glass flex items-center px-4 md:px-8 justify-between">
        <!-- Logo -->
        <a href="<?= $url('/') ?>" class="flex items-center gap-3 group">
            <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center font-black italic text-white shadow-lg shadow-blue-600/20 group-hover:rotate-6 transition-transform">
                <?= strtoupper(substr($siteConfig['site_name'] ?? 'M', 0, 1)) ?>
            </div>
            <span class="font-black tracking-tighter text-xl uppercase italic hidden sm:inline text-white">
                <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP'), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </a>

        <!-- Desktop Navigation -->
        <nav class="hidden lg:flex items-center gap-10">
            <a href="<?= $url('/') ?>" class="text-[11px] font-black uppercase tracking-widest <?= ($currentPath === '/' || str_ends_with($currentPath, '/tr') || str_ends_with($currentPath, '/en')) ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors">ANASAYFA</a>
            <a href="<?= $url('/search') ?>" class="text-[11px] font-black uppercase tracking-widest <?= str_contains($currentPath, '/search') ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors">KEŞFET</a>
            <a href="<?= $url('/profile') ?>" class="text-[11px] font-black uppercase tracking-widest <?= str_contains($currentPath, '/profile') ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors">KÜTÜPHANE</a>
            <a href="<?= $url('/blogs') ?>" class="text-[11px] font-black uppercase tracking-widest <?= str_contains($currentPath, '/blogs') ? 'text-blue-500 border-b-2 border-blue-600 pb-1' : 'text-gray-400 hover:text-white' ?> transition-colors">BLOG</a>
        </nav>

        <!-- Right Actions -->
        <div class="flex items-center gap-3 md:gap-5">
            <!-- Search Button (Mobile Only) -->
            <a href="<?= $url('/search') ?>" class="lg:hidden w-10 h-10 flex items-center justify-center text-gray-400">
                <i data-lucide="search" class="w-6 h-6"></i>
            </a>

            <!-- User Profile Button -->
            <div class="relative">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button id="user-btn" class="flex items-center gap-2 p-1 pr-3 bg-white/5 border border-white/10 rounded-2xl hover:bg-white/10 transition-all">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-[10px] font-black text-white shadow-inner">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500 hidden md:block"></i>
                    </button>

                    <!-- User Modal / Dropdown -->
                    <div id="user-modal" class="absolute right-0 mt-4 w-64 bg-[#121212] border border-white/10 rounded-3xl shadow-2xl p-4 overflow-hidden">
                        <div class="px-3 py-4 border-b border-white/5 mb-2">
                            <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1">CÜZDAN: <?= $_SESSION['user_wallet']['balance'] ?? '0' ?> JETON</p>
                            <p class="text-sm font-black text-white italic uppercase tracking-tight"><?= htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı') ?></p>
                        </div>
                        <div class="space-y-1">
                            <a href="<?= $url('/profile') ?>" class="user-menu-item">
                                <i data-lucide="user" class="w-4 h-4 text-blue-500"></i> Profilim
                            </a>
                            <a href="<?= $url('/profile') ?>" class="user-menu-item">
                                <i data-lucide="book-open" class="w-4 h-4 text-gray-400"></i> Kütüphanem
                            </a>
                            <a href="<?= $url('/profile') ?>" class="user-menu-item">
                                <i data-lucide="settings" class="w-4 h-4 text-gray-400"></i> Ayarlar
                            </a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <a href="<?= $url('/admin/dashboard') ?>" class="user-menu-item">
                                <i data-lucide="shield-check" class="w-4 h-4 text-red-500"></i> Admin Panel
                            </a>
                            <?php endif; ?>
                            <div class="h-px bg-white/5 my-2"></div>
                            <form action="<?= $url('/logout') ?>" method="POST" id="logout-form" class="hidden"></form>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="user-menu-item danger">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Çıkış Yap
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="openAuthBtn" class="bg-white text-black px-6 py-2.5 rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all active:scale-95 shadow-xl shadow-white/5">
                        GİRİŞ YAP
                    </button>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle -->
            <button id="menu-toggle" class="lg:hidden w-10 h-10 bg-white/5 rounded-xl flex items-center justify-center border border-white/10">
                <i data-lucide="menu" class="w-6 h-6 text-white"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Navigation Overlay -->
    <div id="mobile-menu" class="fixed inset-0 z-[90] bg-[#080808] pt-24 px-6">
        <div class="flex flex-col gap-6">
            <a href="<?= $url('/') ?>" class="text-2xl font-black italic uppercase tracking-tighter <?= ($currentPath === '/' || str_ends_with($currentPath, '/tr') || str_ends_with($currentPath, '/en')) ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4">ANASAYFA</a>
            <a href="<?= $url('/search') ?>" class="text-2xl font-black italic uppercase tracking-tighter <?= str_contains($currentPath, '/search') ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4">KEŞFET</a>
            <a href="<?= $url('/profile') ?>" class="text-2xl font-black italic uppercase tracking-tighter <?= str_contains($currentPath, '/profile') ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4">KÜTÜPHANE</a>
            <a href="<?= $url('/blogs') ?>" class="text-2xl font-black italic uppercase tracking-tighter <?= str_contains($currentPath, '/blogs') ? 'text-blue-500' : 'text-white' ?> border-b border-white/5 pb-4">BLOG</a>
        </div>
    </div>

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
            
            // Mobile Menu Toggle
            $("#menu-toggle").click(function () {
                $("#mobile-menu").fadeToggle(200);
                $("#user-modal").fadeOut(100);
            });

            // User Modal Toggle
            $("#user-btn").click(function (e) {
                e.stopPropagation();
                $("#user-modal").fadeToggle(150);
                $("#mobile-menu").fadeOut(100);
            });

            // Close modals on click outside
            $(document).click(function () {
                $("#user-modal").fadeOut(150);
            });

            $("#user-modal").click(function (e) {
                e.stopPropagation();
            });

            // Auth system integration
            $("#openAuthBtn").on("click", function () {
                if (window.NMR && window.NMR.showAuthModal) {
                    window.NMR.showAuthModal();
                } else {
                    console.warn('Auth system not initialized');
                }
            });
        });
    </script>
</body>
</html>
