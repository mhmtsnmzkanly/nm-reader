<?php
/** @var array $ssr_data */
/** @var array $chapters */
/** @var array $breadcrumbs */
/** @var Closure $url */

$content = is_array($ssr_data ?? null) ? $ssr_data : [];
$chapterItems = is_array($chapters ?? null) ? $chapters : [];
$genres = is_array($content['series_genres'] ?? null) ? $content['series_genres'] : [];
$tags = is_array($content['series_tags'] ?? null) ? $content['series_tags'] : [];
?>

<style>
    .bg-mesh {
        background-image: radial-gradient(at 0% 0%, hsla(225, 39%, 30%, 1) 0, transparent 50%),
                          radial-gradient(at 50% 0%, hsla(225, 39%, 20%, 1) 0, transparent 50%);
    }
    .chapter-row:hover { background: rgba(255, 255, 255, 0.05); transform: translateX(8px); }
    @keyframes pop { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    .animate-pop { animation: pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
</style>

<!-- Hero Section -->
<section class="relative w-full h-[500px] sm:h-[650px] flex items-end bg-mesh">
    <!-- Background Image Blur -->
    <div class="absolute inset-0 z-0">
        <img src="<?= htmlspecialchars((string)($content['cover_image'] ?? '')) ?>" class="w-full h-full object-cover opacity-20 blur-3xl" alt="Background" />
        <div class="absolute inset-0 bg-gradient-to-t from-[#080808] via-transparent to-transparent"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-6 w-full pb-12">
        <div class="flex flex-col md:flex-row gap-10 items-end">
            <!-- Poster -->
            <div class="hidden md:block w-72 aspect-[2/3] rounded-[40px] overflow-hidden shadow-2xl border-4 border-white/5 shrink-0 bg-zinc-900">
                <img src="<?= htmlspecialchars((string)($content['cover_image'] ?? '')) ?>" class="w-full h-full object-cover" alt="Poster" />
            </div>

            <!-- Info -->
            <div class="flex-1">
                <div class="flex flex-wrap gap-2 mb-6">
                    <?php foreach (array_slice($genres, 0, 3) as $genre): ?>
                    <span class="px-4 py-1.5 bg-blue-600/20 text-blue-500 border border-blue-500/20 rounded-full text-[10px] font-black uppercase tracking-widest">
                        <?= htmlspecialchars((string)($genre['name'] ?? '')) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($breadcrumbs)): ?>
                <nav class="flex gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <a href="<?= htmlspecialchars((string)($crumb['url'] ?? '#')) ?>" class="hover:text-blue-500"><?= htmlspecialchars((string)$crumb['title']) ?></a>
                        <span class="last:hidden">/</span>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>

                <h1 class="text-5xl sm:text-7xl font-black italic uppercase tracking-tighter text-white mb-6 leading-none truncate max-w-full">
                    <?= htmlspecialchars((string) ($content['title'] ?? '')) ?>
                </h1>

                <div class="flex flex-wrap items-center gap-8 mb-8">
                    <div class="flex items-center gap-2">
                        <i data-lucide="star" class="w-6 h-6 text-yellow-500 fill-current"></i>
                        <span class="text-2xl font-black"><?= number_format((float)($content['rating_avg'] ?? 0), 2) ?></span>
                        <span class="text-gray-500 text-xs font-bold uppercase">(<?= number_format((int)($content['rating_count'] ?? 0)) ?> Oy)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="eye" class="w-6 h-6 text-blue-500"></i>
                        <span class="text-2xl font-black"><?= number_format((int)($content['view_count'] ?? 0)) ?></span>
                        <span class="text-gray-500 text-xs font-bold uppercase">İzlenme</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400">
                        <i data-lucide="book-open" class="w-6 h-6"></i>
                        <span class="text-2xl font-black"><?= htmlspecialchars((string) ($content['chapter_count'] ?? '0')) ?> Bölüm</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <?php 
                    $firstChapterUrl = "#";
                    if (!empty($chapterItems)) {
                        $firstChapter = end($chapterItems); // Chapters are usually DESC, so last one is first
                        $firstChapterUrl = $url(sprintf('%s/%s/chapter/%s', 
                            (string)($content['type_path'] ?? 'novel'), 
                            (string)($content['slug'] ?? ''), 
                            rawurlencode((string)($firstChapter['chapter_number'] ?? '1'))
                        ));
                    }
                    ?>
                    <a href="<?= $firstChapterUrl ?>" class="bg-blue-600 text-white px-10 py-5 rounded-3xl font-black uppercase italic text-xs shadow-2xl hover:bg-white hover:text-black transition-all flex items-center gap-3">
                        <i data-lucide="play" class="w-4 h-4 fill-current"></i> İLK BÖLÜMÜ OKU
                    </a>
                    <button id="toggleFollowBtn" class="bg-white/5 border border-white/10 text-white px-10 py-5 rounded-3xl font-black uppercase italic text-xs hover:bg-white/10 transition-all flex items-center gap-3">
                        <i data-lucide="plus" class="w-4 h-4"></i> LİSTEYE EKLE
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Body Content -->
<section class="max-w-7xl mx-auto px-6 py-16 grid grid-cols-1 lg:grid-cols-3 gap-16">
    <!-- Left: Description & Chapters -->
    <div class="lg:col-span-2 space-y-16">
        <!-- Synopsis -->
        <div>
            <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white mb-6 flex items-center gap-3">
                <div class="w-2 h-8 bg-blue-600 rounded-full"></div> ÖZET
            </h2>
            <p class="text-gray-400 leading-relaxed text-lg italic">
                <?= htmlspecialchars((string) ($content['description'] ?? 'Açıklama bulunmuyor.')) ?>
            </p>
        </div>

        <!-- Chapters -->
        <div>
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white flex items-center gap-3">
                    <div class="w-2 h-8 bg-blue-600 rounded-full"></div> BÖLÜMLER
                </h2>
                <span class="text-xs font-black text-gray-500 tracking-widest uppercase"><?= count($chapterItems) ?> BÖLÜM TOPLAM</span>
            </div>

            <div id="chapterList" class="space-y-3">
                <?php foreach ($chapterItems as $chapter): 
                    $isLocked = ($chapter['access']['is_locked'] ?? false);
                    $price = (int)($chapter['chapter_unlock_price'] ?? 0);
                    $chapterPath = sprintf('%s/%s/chapter/%s', 
                        (string)($content['type_path'] ?? 'novel'), 
                        (string)($content['slug'] ?? ''), 
                        rawurlencode((string)($chapter['chapter_number'] ?? ''))
                    );
                    $fullChapterUrl = $url($chapterPath);
                ?>
                <div class="chapter-row flex items-center justify-between p-6 glass rounded-[24px] cursor-pointer transition-all border border-white/5 group"
                     onclick="handleChapterClick('<?= $chapter['id'] ?>', <?= $isLocked ? 'true' : 'false' ?>, <?= $price ?>, '<?= $fullChapterUrl ?>')">
                    <div class="flex items-center gap-6">
                        <span class="w-12 h-12 flex items-center justify-center <?= $isLocked ? 'bg-zinc-800 text-gray-500' : 'bg-blue-600/10 text-blue-500' ?> rounded-2xl font-black italic">
                            <?= htmlspecialchars((string) ($chapter['chapter_number'] ?? '')) ?>
                        </span>
                        <div>
                            <h4 class="font-black uppercase text-sm <?= $isLocked ? 'group-hover:text-blue-500' : 'text-blue-500' ?> transition-colors">
                                <?= htmlspecialchars((string) ($chapter['title'] ?? 'Bölüm ' . $chapter['chapter_number'])) ?>
                            </h4>
                            <p class="text-[10px] text-gray-500 font-bold uppercase mt-1">
                                <?= date('d.m.Y', strtotime($chapter['created_at'] ?? 'now')) ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if ($isLocked): ?>
                        <div class="coin-badge flex items-center gap-2 bg-yellow-500 text-black px-3 py-1.5 rounded-xl font-black text-[10px]">
                            <i data-lucide="lock" class="w-3 h-3"></i> <?= $price ?> JETON
                        </div>
                        <?php else: ?>
                        <i data-lucide="chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-white"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Comments Placeholder -->
        <div>
            <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white mb-8 flex items-center gap-3">
                <div class="w-2 h-8 bg-blue-600 rounded-full"></div> YORUMLAR
            </h2>
            <div id="commentsContainer" class="space-y-8">
                <!-- Comments will be loaded via JS -->
                <p class="text-gray-500 italic text-sm">Yorumlar yükleniyor...</p>
            </div>
        </div>
    </div>

    <!-- Right: Metadata & Sidebar -->
    <div class="space-y-12">
        <!-- Wallet Box -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="glass rounded-[40px] p-8 border border-white/5">
            <h3 class="font-black italic uppercase text-sm mb-4 text-yellow-500 tracking-widest">CÜZDANINIZ</h3>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i data-lucide="coins" class="w-8 h-8 text-yellow-500"></i>
                    <div>
                        <p id="sidebarWalletDisplay" class="text-3xl font-black"><?= $_SESSION['user_wallet']['balance'] ?? '0' ?></p>
                        <p class="text-[9px] text-gray-500 font-bold uppercase">Mevcut Jeton</p>
                    </div>
                </div>
                <button class="bg-white text-black p-3 rounded-2xl hover:bg-yellow-500 transition-colors">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="glass rounded-[40px] p-8 border border-white/5">
            <h3 class="font-black italic uppercase text-sm mb-6 text-blue-500 tracking-widest">DETAYLI BİLGİ</h3>
            <div class="space-y-6">
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase">YAZAR</span>
                    <span class="text-sm font-bold"><?= htmlspecialchars((string) ($content['author'] ?? 'Bilinmiyor')) ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase">ÇİZİM</span>
                    <span class="text-sm font-bold"><?= htmlspecialchars((string) ($content['artist'] ?? 'Bilinmiyor')) ?></span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase">DURUM</span>
                    <span class="text-sm font-bold <?= (strtolower($content['status'] ?? '') === 'ongoing') ? 'text-green-500' : 'text-blue-500' ?> uppercase">
                        <?= htmlspecialchars((string) ($content['status'] ?? '-')) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-4">
                    <span class="text-[10px] font-black text-gray-500 uppercase">TİPİ</span>
                    <span class="text-sm font-bold uppercase"><?= htmlspecialchars((string) ($content['type_path'] ?? $content['type'] ?? '')) ?></span>
                </div>
            </div>
        </div>

        <!-- Tags -->
        <?php if ($tags !== []): ?>
        <div class="glass rounded-[40px] p-8 border border-white/5">
            <h3 class="font-black italic uppercase text-sm mb-4 text-gray-500">ETİKETLER</h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($tags as $tag): ?>
                <a href="<?= $url('tag/' . (string) ($tag['slug'] ?? '')) ?>" class="text-[10px] font-black uppercase text-gray-400 hover:text-white transition-colors">#<?= htmlspecialchars((string) ($tag['name'] ?? '')) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Share -->
        <div class="p-8 bg-blue-600 rounded-[40px] text-center shadow-2xl shadow-blue-600/20">
            <h4 class="font-black italic uppercase text-sm mb-4 text-white">BU SERİYİ PAYLAŞ</h4>
            <div class="flex justify-center gap-4">
                <button class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center hover:bg-white/40 transition-all">
                    <i data-lucide="facebook" class="w-5 h-5 text-white"></i>
                </button>
                <button class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center hover:bg-white/40 transition-all">
                    <i data-lucide="twitter" class="w-5 h-5 text-white"></i>
                </button>
                <button class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center hover:bg-white/40 transition-all">
                    <i data-lucide="link" class="w-5 h-5 text-white"></i>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Purchase Modal -->
<div id="purchaseModal" class="fixed inset-0 z-[100] modal-overlay hidden items-center justify-center p-4">
    <div class="w-full max-w-sm glass border border-white/10 rounded-[40px] p-8 text-center animate-pop">
        <div class="w-20 h-20 bg-yellow-500/20 rounded-3xl flex items-center justify-center mx-auto mb-6">
            <i data-lucide="shopping-cart" class="w-10 h-10 text-yellow-500"></i>
        </div>
        <h3 class="text-2xl font-black italic uppercase mb-2 text-white">BÖLÜMÜ AL?</h3>
        <p class="text-gray-400 text-sm mb-8">Bu bölümü açmak için <span id="modalPrice" class="text-yellow-500 font-bold">--</span> jeton harcanacak.</p>
        <div class="flex gap-4">
            <button onclick="closeModal('purchaseModal')" class="flex-1 py-4 bg-white/5 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-white/10 text-white">VAZGEÇ</button>
            <button id="confirmPurchase" class="flex-1 py-4 bg-yellow-500 text-black rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-yellow-500/20">SATIN AL</button>
        </div>
    </div>
</div>

<script>
let selectedChapter = null;

function handleChapterClick(id, isLocked, price, redirectUrl) {
    if (!isLocked) {
        location.href = redirectUrl;
        return;
    }
    
    <?php if (!isset($_SESSION['user_id'])): ?>
        if (window.NMR && window.NMR.showAuthModal) {
            window.NMR.showAuthModal();
        } else {
            alert('Lütfen giriş yapın.');
        }
        return;
    <?php endif; ?>

    selectedChapter = { id, price, url: redirectUrl };
    $('#modalPrice').text(price);
    $('#purchaseModal').removeClass('hidden').addClass('flex');
}

function closeModal(id) {
    $(`#${id}`).addClass('hidden').removeClass('flex');
}

$(document).ready(function() {
    lucide.createIcons();

    $('#confirmPurchase').on('click', function() {
        if (!selectedChapter) return;
        
        closeModal('purchaseModal');
        
        $.ajax({
            url: `/api/v1/chapter/${selectedChapter.id}/unlock`,
            method: 'POST',
            success: function(response) {
                if (response.status === 'success') {
                    location.href = selectedChapter.url;
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Bir hata oluştu.';
                alert(msg);
            }
        });
    });

    // Animations
    $(".chapter-row").css("opacity", "0");
    $(".chapter-row").each(function (i) {
        $(this).delay(50 * i).animate({ opacity: 1 }, 300);
    });
});
</script>
