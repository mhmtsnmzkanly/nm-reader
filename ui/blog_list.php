<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manga.app | Blog</title>
    <!-- Tailwind CSS for Styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jQuery for Interactivity -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #080808;
            color: #E3E2E6;
        }

        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .blog-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .blog-card:hover {
            transform: translateY(-12px);
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(59, 130, 246, 0.03);
        }

        .blog-card img {
            transition: transform 0.6s ease;
        }

        .blog-card:hover img {
            transform: scale(1.05);
        }

        .category-pill.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
    </style>
</head>
<body class="overflow-x-hidden">

    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 h-20 glass border-b border-white/5">
        <div class="max-w-7xl mx-auto px-6 h-full flex items-center justify-between">
            <div class="flex items-center gap-3 cursor-pointer group" onclick="location.reload()">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center font-black italic text-white shadow-lg shadow-blue-600/20 group-hover:rotate-6 transition-transform">
                    M
                </div>
                <span class="font-black tracking-tighter text-xl uppercase italic hidden sm:inline text-white">MANGA.APP</span>
            </div>

            <nav class="hidden md:flex items-center gap-10">
                <a href="#" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 hover:text-white transition-colors">
                    <i data-lucide="compass" class="w-5 h-5"></i> KEŞFET
                </a>
                <a href="#" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-white transition-colors">
                    <i data-lucide="layout-grid" class="w-5 h-5 text-blue-500"></i> BLOG
                </a>
                <a href="#" class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-gray-500 hover:text-white transition-colors">
                    <i data-lucide="book-open" class="w-5 h-5"></i> KİTAPLIK
                </a>
            </nav>

            <div class="flex items-center gap-4">
                <button class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all">
                    <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                </button>
                <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center font-bold text-xs shadow-lg shadow-blue-600/20 cursor-pointer">
                    JD
                </div>
            </div>
        </div>
    </header>

    <!-- BLOG CONTENT -->
    <main class="pt-20">

        <!-- Hero Blog Section -->
        <section class="max-w-7xl mx-auto px-6 pt-12">
            <div class="relative w-full h-[400px] md:h-[500px] rounded-[40px] overflow-hidden group">
                <img src="https://images.unsplash.com/photo-1578632292335-df3abbb0d586?q=80&w=1200" class="w-full h-full object-cover" alt="Featured Blog">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
                <div class="absolute bottom-0 left-0 p-8 md:p-12 max-w-2xl">
                    <span class="px-4 py-1.5 bg-blue-600 text-white rounded-full text-[10px] font-black uppercase tracking-widest mb-4 inline-block">EDİTÖRÜN SEÇİMİ</span>
                    <h2 class="text-3xl md:text-5xl font-black italic uppercase tracking-tighter text-white mb-4 leading-none group-hover:text-blue-400 transition-colors cursor-pointer">
                        MANGA DÜNYASINDA BU HAFTA: YENİ SEZON REHBERİ
                    </h2>
                    <p class="text-gray-300 text-sm md:text-base line-clamp-2 mb-6">
                        Solo Leveling'in muazzam finalinden sonra hangi serilere başlamalısınız? Editörlerimiz sizler için en iyi 10 yeni nesil mangayı listeledi.
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-zinc-800 border border-white/10 overflow-hidden">
                            <img src="https://i.pravatar.cc/100?u=1" alt="Author">
                        </div>
                        <span class="text-xs font-bold text-white">Caner Yıldız</span>
                        <span class="text-xs text-gray-500 font-bold uppercase">• 5 DK OKUMA</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Blog Grid -->
        <section class="max-w-7xl mx-auto px-6 pb-24 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                <!-- Blog Item 1 -->
                <article class="blog-card glass rounded-[32px] overflow-hidden border border-white/5 flex flex-col cursor-pointer">
                    <div class="relative h-56 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?q=80&w=800" class="w-full h-full object-cover" alt="Blog 1">
                        <div class="absolute top-4 left-4 px-3 py-1 bg-black/60 backdrop-blur-md rounded-lg text-[9px] font-black text-white uppercase">İNCELEME</div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="text-xl font-black italic uppercase tracking-tighter text-white mb-3 line-clamp-2 leading-tight">
                            One Piece Final Saga: Neler Bekliyoruz?
                        </h3>
                        <p class="text-gray-500 text-sm line-clamp-3 mb-6 flex-1">
                            Eiichiro Oda'nın efsanevi eseri final dönemine girerken, hayran teorilerini ve kesinleşen detayları inceliyoruz.
                        </p>
                        <div class="flex items-center justify-between pt-4 border-t border-white/5">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">OKUMAYA DEVAM ET</span>
                            <span class="text-[9px] text-gray-600 font-bold uppercase">12 MART 2026</span>
                        </div>
                    </div>
                </article>

                <!-- Blog Item 2 -->
                <article class="blog-card glass rounded-[32px] overflow-hidden border border-white/5 flex flex-col cursor-pointer">
                    <div class="relative h-56 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1627389955805-72ff33037996?q=80&w=800" class="w-full h-full object-cover" alt="Blog 2">
                        <div class="absolute top-4 left-4 px-3 py-1 bg-black/60 backdrop-blur-md rounded-lg text-[9px] font-black text-white uppercase">HABER</div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="text-xl font-black italic uppercase tracking-tighter text-white mb-3 line-clamp-2 leading-tight">
                            Yeni Webtoon Uyarlamaları Duyuruldu
                        </h3>
                        <p class="text-gray-500 text-sm line-clamp-3 mb-6 flex-1">
                            Netflix ve Crunchyroll iş birliğiyle 2026'da ekranlara gelecek olan en popüler 5 Webtoon serisi belli oldu.
                        </p>
                        <div class="flex items-center justify-between pt-4 border-t border-white/5">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">OKUMAYA DEVAM ET</span>
                            <span class="text-[9px] text-gray-600 font-bold uppercase">10 MART 2026</span>
                        </div>
                    </div>
                </article>

                <!-- Blog Item 3 -->
                <article class="blog-card glass rounded-[32px] overflow-hidden border border-white/5 flex flex-col cursor-pointer">
                    <div class="relative h-56 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1541562232579-512a21360020?q=80&w=800" class="w-full h-full object-cover" alt="Blog 3">
                        <div class="absolute top-4 left-4 px-3 py-1 bg-black/60 backdrop-blur-md rounded-lg text-[9px] font-black text-white uppercase">LİSTE</div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="text-xl font-black italic uppercase tracking-tighter text-white mb-3 line-clamp-2 leading-tight">
                            Karanlık Temalı En İyi 5 Seinen Manga
                        </h3>
                        <p class="text-gray-500 text-sm line-clamp-3 mb-6 flex-1">
                            Psikolojik gerilim ve derin hikayeleri seven okurlarımız için kaçırılmaması gereken kült eserler.
                        </p>
                        <div class="flex items-center justify-between pt-4 border-t border-white/5">
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">OKUMAYA DEVAM ET</span>
                            <span class="text-[9px] text-gray-600 font-bold uppercase">8 MART 2026</span>
                        </div>
                    </div>
                </article>

            </div>

            <!-- Pagination -->
            <div class="mt-20 flex justify-center gap-4">
                <button class="w-12 h-12 rounded-2xl glass border border-white/5 flex items-center justify-center text-gray-500 hover:border-blue-500 hover:text-white transition-all">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </button>
                <button class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center font-black text-sm">1</button>
                <button class="w-12 h-12 rounded-2xl glass border border-white/5 flex items-center justify-center font-black text-sm text-gray-500 hover:text-white transition-all">2</button>
                <button class="w-12 h-12 rounded-2xl glass border border-white/5 flex items-center justify-center font-black text-sm text-gray-500 hover:text-white transition-all">3</button>
                <button class="w-12 h-12 rounded-2xl glass border border-white/5 flex items-center justify-center text-gray-500 hover:border-blue-500 hover:text-white transition-all">
                    <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </button>
            </div>
        </section>

    </main>

    <!-- FOOTER -->
    <footer class="py-20 border-t border-white/5 px-6 text-center bg-[#050505]">
        <div class="font-black italic text-white/20 uppercase tracking-[0.5em] text-sm mb-4">
            MANGA.APP EXPERIENCE
        </div>
        <p class="text-gray-600 text-[10px] font-bold uppercase tracking-widest">
            © 2026 Tüm Hakları Saklıdır.
        </p>
    </footer>

    <!-- SCRIPTS -->
    <script>
        $(document).ready(function() {
            lucide.createIcons();

            // Blog kartları giriş animasyonu
            $('.blog-card').css('opacity', '0');
            $('.blog-card').each(function(i) {
                $(this).delay(100 * i).animate({opacity: 1}, 500);
            });

            // Kategori seçimi
            $('.category-pill').on('click', function() {
                $('.category-pill').removeClass('active text-white').addClass('text-gray-400');
                $(this).addClass('active text-white').removeClass('text-gray-400');
            });
        });
    </script>
</body>
</html>
