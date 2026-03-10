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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

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

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap");
        body { font-family: "Inter", sans-serif; background-color: #080808; color: #e3e2e6; display: flex; flex-direction: column; min-height: 100vh; }
        .glass { background: rgba(10, 10, 10, 0.7); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .modal-overlay { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(0, 0, 0, 0.85); 
            backdrop-filter: blur(8px); 
            z-index: 200; 
            align-items: center; 
            justify-content: center; 
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal.card {
            background: #121212;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalShow 0.3s ease-out forwards;
        }
        @keyframes modalShow { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        /* Feedback Toast */
        #feedback-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 300;
            display: none;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            animation: toastIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        @keyframes toastIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        #feedback-toast.success { background: #10b981; color: white; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); }
        #feedback-toast.error { background: #ef4444; color: white; box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3); }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 900; text-transform: uppercase; color: #3b82f6; margin-bottom: 0.5rem; letter-spacing: 0.05em; }
        .form-item { width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 1rem; padding: 0.75rem 1rem; color: white; outline: none; transition: all 0.2s; }
        .form-item:focus { border-color: #3b82f6; background: rgba(255, 255, 255, 0.08); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.5rem; border-radius: 1rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem; transition: all 0.2s; cursor: pointer; border: none; }
        .btn-primary { background: #3b82f6; color: white; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3); }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid rgba(255, 255, 255, 0.1); color: #94a3b8; }
        .btn-outline:hover { background: rgba(255, 255, 255, 0.05); color: white; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .modal-header h3 { font-weight: 900; text-transform: uppercase; font-style: italic; color: white; font-size: 1.25rem; margin: 0; }
        .modal-close { background: none; border: none; color: #64748b; font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
        .modal-close:hover { color: white; }
        .no-scrollbar::-webkit-scrollbar { display: none; }

        .user-menu-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 12px; transition: all 0.2s; color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .user-menu-item:hover { background: rgba(255, 255, 255, 0.05); color: white; }
        .user-menu-item.danger:hover { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Profile Edit Modal Specifics */
        .edit-input { width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 12px 16px; color: white; font-size: 13px; outline: none; transition: all 0.2s; }
        .edit-input:focus { border-color: #3b82f6; background: rgba(255, 255, 255, 0.08); }
        .edit-input-locked { background: rgba(255, 255, 255, 0.02); border-color: transparent; color: #64748b; cursor: not-allowed; }
        .edit-label { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: #4b5563; margin-bottom: 6px; display: block; }

        /* Footer New Styles */
        .footer-column-title { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.15em; color: #ffffff; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
        .footer-link { font-size: 13px; color: #94a3b8; transition: all 0.2s; display: block; margin-bottom: 0.75rem; font-weight: 500; }
        .footer-link:hover { color: #3b82f6; transform: translateX(5px); }
        
        .tag-cloud { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; padding: 20px 0; }
        .footer-tag { padding: 0.5rem 1rem; border-radius: 12px; font-size: 11px; font-weight: 800; text-transform: uppercase; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); color: #94a3b8; }
        .footer-tag:hover { transform: translateY(-3px); filter: brightness(1.3); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); background: #3b82f6; color: white; border-color: #3b82f6; }
        
        /* Tag Cloud Colors */
        .tag-blue { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .tag-purple { background: rgba(168, 85, 247, 0.1); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.2); }
        .tag-red { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .tag-green { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .tag-orange { background: rgba(249, 115, 22, 0.1); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.2); }
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
            <a href="<?= $url('/search') ?>" class="lg:hidden w-10 h-10 flex items-center justify-center text-gray-400">
                <i data-lucide="search" class="w-6 h-6"></i>
            </a>

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
                            <div onclick="openModal('userSettingsModal')" class="user-menu-item">
                                <i data-lucide="edit-3" class="w-4 h-4 text-emerald-500"></i> Profilimi Düzenle
                            </div>
                            <a href="<?= $url('/profile') ?>" class="user-menu-item">
                                <i data-lucide="book-open" class="w-4 h-4 text-gray-400"></i> Kütüphanem
                            </a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <a href="<?= $url('/admin/dashboard') ?>" class="user-menu-item">
                                <i data-lucide="shield-check" class="w-4 h-4 text-red-500"></i> Admin Panel
                            </a>
                            <?php endif; ?>
                            <div class="h-px bg-white/5 my-2"></div>
                            <a href="#" onclick="logout(); return false;" class="user-menu-item danger">
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
                        Hakkında
                    </h3>
                    <p class="text-gray-500 text-sm leading-relaxed mb-6 italic">
                        <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP')) ?>, en sevdiğiniz mangaları, manhwaları ve webtoonları en yüksek kalitede okumanız için tasarlanmış modern bir platformdur.
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
                        Popüler Türler
                    </h3>
                    <nav>
                        <?php foreach (array_slice($footerGenres ?? [], 0, 5) as $genre): ?>
                            <a href="<?= $url('genre/' . (string)$genre['slug']) ?>" class="footer-link"><?= htmlspecialchars((string)$genre['name']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Hızlı Menü -->
                <div>
                    <h3 class="footer-column-title">
                        <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
                        Hızlı Menü
                    </h3>
                    <nav>
                        <a href="<?= $url('/') ?>" class="footer-link">Anasayfa</a>
                        <a href="<?= $url('/search') ?>" class="footer-link">Keşfet</a>
                        <a href="<?= $url('/profile') ?>" class="footer-link">Kütüphane</a>
                        <a href="<?= $url('/blogs') ?>" class="footer-link">Blog</a>
                    </nav>
                </div>
            </div>

            <!-- Horizontal Divider -->
            <hr class="border-white/5 mb-12" />

            <!-- Genre & Tag Cloud Section -->
            <div class="text-center mb-12">
                <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gray-600 mb-8">
                    Kategorileri Keşfet
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
                <p class="text-gray-700 text-[10px] font-black uppercase tracking-widest italic">
                    © <?= date('Y') ?> <?= htmlspecialchars((string) ($siteConfig['site_name'] ?? 'MANGA.APP')) ?> EXPERIENCE • TÜM HAKLARI SAKLIDIR
                </p>
            </div>
        </div>
    </footer>

    <!-- MODALS -->
    <?php include dirname(__DIR__) . '/views/partials_modals.php'; ?>

    <!-- Feedback Toast Element -->
    <div id="feedback-toast"></div>

    <!-- CORE JS -->
    <script src="/assets/js/app-bundle.js"></script>

    <script>
        // GLOBAL MODAL LOGIC
        window.openModal = function(id) {
            $(".modal-overlay").removeClass("active");
            $("#" + id).addClass("active");
            $("body").addClass("overflow-hidden");
        };

        window.closeModal = function() {
            $(".modal-overlay").removeClass("active");
            $("body").removeClass("overflow-hidden");
        };

        window.showFeedback = function(message, type = 'success') {
            const $toast = $("#feedback-toast");
            $toast.stop(true, true).removeClass('success error').addClass(type).text(message).fadeIn(300);
            setTimeout(() => $toast.fadeOut(300), 4000);
        };

        window.logout = function() {
            window.NMRData.post('/auth/logout', {})
                .then(() => {
                    showFeedback('Başarıyla çıkış yapıldı.');
                    setTimeout(() => location.href = '/', 1000);
                })
                .catch(err => {
                    // Fallback if API call fails
                    location.href = '<?= $url("/logout") ?>';
                });
        };

        $(document).ready(function () {
            lucide.createIcons();
            
            // Handle Login Form
            $("#loginForm").on("submit", function(e) {
                e.preventDefault();
                const $btn = $(this).find('button[type="submit"]');
                const originalText = $btn.text();
                $btn.prop('disabled', true).text('...');

                const formData = {
                    email: $(this).find('input[name="email"]').val(),
                    password: $(this).find('input[name="password"]').val(),
                    remember: $(this).find('input[name="remember"]').is(':checked'),
                    'turnstile_token': $(this).find('[name="cf-turnstile-response"]').val()
                };

                window.NMRData.post('/auth/login', formData)
                    .then(res => {
                        showFeedback('Giriş başarılı! Yönlendiriliyorsunuz...');
                        setTimeout(() => location.reload(), 1000);
                    })
                    .catch(err => {
                        showFeedback(err.message || 'Giriş yapılamadı.', 'error');
                        $btn.prop('disabled', false).text(originalText);
                        if (typeof turnstile !== 'undefined') turnstile.reset();
                    });
            });

            // Handle Register Form
            $("#registerForm").on("submit", function(e) {
                e.preventDefault();
                const $btn = $(this).find('button[type="submit"]');
                const originalText = $btn.text();
                $btn.prop('disabled', true).text('...');

                const formData = {
                    username: $(this).find('input[name="username"]').val(),
                    email: $(this).find('input[name="email"]').val(),
                    password: $(this).find('input[name="password"]').val(),
                    'turnstile_token': $(this).find('[name="cf-turnstile-response"]').val()
                };

                window.NMRData.post('/auth/register', formData)
                    .then(res => {
                        showFeedback('Kayıt başarılı! Giriş yapabilirsiniz.');
                        setTimeout(() => openModal('loginModal'), 1500);
                    })
                    .catch(err => {
                        showFeedback(err.message || 'Kayıt olunamadı.', 'error');
                        $btn.prop('disabled', false).text(originalText);
                        if (typeof turnstile !== 'undefined') turnstile.reset();
                    });
            });

            // Handle Profile Edit Form
            $("#userSettingsForm").on("submit", function(e) {
                e.preventDefault();
                const $btn = $(this).find('button[type="submit"]');
                const originalText = $btn.text();
                $btn.prop('disabled', true).text('...');

                const formData = new FormData(this);

                $.ajax({
                    url: '/api/v1/user/profile',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-Token': window.__NMR_CONTEXT.auth.csrf_token
                    },
                    success: function(res) {
                        showFeedback('Profil başarıyla güncellendi.');
                        setTimeout(() => location.reload(), 1000);
                    },
                    error: function(xhr) {
                        const err = xhr.responseJSON || {};
                        showFeedback(err.message || 'Güncelleme başarısız.', 'error');
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Close modal on overlay click
            $(".modal-overlay").on("click", function (e) {
                if (e.target === this) closeModal();
            });

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
                openModal('loginModal');
            });
        });
    </script>
</body>
</html>
