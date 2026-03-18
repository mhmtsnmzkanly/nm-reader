/**
 * NMR Reader - Global Logic
 */
console.log("NMR Main JS Initializing...");

// Global Modal Control
window.openModal = function(id) {
    $(".modal-overlay").removeClass("active");
    $("#" + id).addClass("active");
    $("body").addClass("overflow-hidden");
};

window.closeModal = function() {
    $(".modal-overlay").removeClass("active");
    $("body").removeClass("overflow-hidden");
};

// Chapter purchase / navigation
window.__NMR_PURCHASE_CONTEXT = null;

window.openPurchaseModal = function(ctx) {
    if (!ctx) return;
    window.__NMR_PURCHASE_CONTEXT = ctx;
    const priceEl = document.getElementById('modalPrice');
    if (priceEl) priceEl.textContent = String(ctx.price ?? 0);
    openModal('purchaseModal');
};

window.handleChapterClick = async function(el) {
    const node = el instanceof Element ? el : null;
    if (!node) return;

    const chapterId = node.getAttribute('data-chapter-id');
    const isLocked = node.getAttribute('data-locked') === '1';
    const price = parseInt(node.getAttribute('data-price') || '0', 10);
    const url = node.getAttribute('data-url') || '';

    if (!isLocked) {
        if (url) window.location.href = url;
        return;
    }

    const isLoggedIn = !!(window.__NMR_CONTEXT && window.__NMR_CONTEXT.auth && window.__NMR_CONTEXT.auth.is_logged_in);
    if (!isLoggedIn) {
        showFeedback('Bölümü açmak için giriş yapmalısınız.', 'error');
        openModal('loginModal');
        return;
    }

    if (!chapterId || !window.NMRData) {
        showFeedback('Bölüm bilgisi eksik.', 'error');
        return;
    }

    openPurchaseModal({ chapterId, price, url, node });
};

// Global Language Switcher
window.switchLanguage = function(newLang) {
    const currentPath = window.location.pathname;
    const parts = currentPath.split('/').filter(p => p !== '');
    
    // Check if the first part is a known language code
    const knownLangs = ['tr', 'en'];
    if (parts.length > 0 && knownLangs.includes(parts[0])) {
        parts[0] = newLang;
    } else {
        parts.unshift(newLang);
    }
    
    window.location.href = '/' + parts.join('/') + window.location.search;
};

// Global Feedback Notifications
window.showFeedback = function(message, type = 'success') {
    const $toast = $("#feedback-toast");
    $toast.stop(true, true).removeClass('success error').addClass(type).text(message).fadeIn(300);
    setTimeout(() => $toast.fadeOut(300), 4000);
};

// Global Logout
window.logout = function() {
    if (window.NMRData) {
        window.NMRData.post('/auth/logout', {})
            .then(() => {
                showFeedback('Başarıyla çıkış yapıldı.');
                setTimeout(() => location.href = '/', 1000);
            })
            .catch(() => {
                location.href = '/logout';
            });
    } else {
        location.href = '/logout';
    }
};

$(document).ready(function () {
    const templateName = $("body").data("template") || "";

    // Initialize Icons
    if (window.lucide) lucide.createIcons();

    // Auth Button
    $("#openAuthBtn").on("click", function() {
        openModal('loginModal');
    });

    $("#confirmPurchase").on("click", async function() {
        const ctx = window.__NMR_PURCHASE_CONTEXT;
        if (!ctx || !ctx.chapterId || !window.NMRData) {
            showFeedback('Satın alma bilgisi eksik.', 'error');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).addClass('opacity-70');
        try {
            const res = await window.NMRData.post(`/chapter/${ctx.chapterId}/unlock`, {});
            const wallet = res?.data?.wallet;
            if (wallet && typeof wallet.balance_coin !== 'undefined') {
                const el = document.getElementById('sidebarWalletDisplay');
                if (el) el.textContent = String(wallet.balance_coin);
            }

            if (ctx.node) {
                ctx.node.setAttribute('data-locked', '0');
                const badge = ctx.node.querySelector('.coin-badge');
                if (badge) badge.remove();
                const right = ctx.node.querySelector('div.flex.items-center.gap-4');
                if (right && !right.querySelector('[data-lucide="chevron-right"]')) {
                    right.insertAdjacentHTML('beforeend', '<i data-lucide="chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-white"></i>');
                    if (window.lucide) lucide.createIcons();
                }
                const numBadge = ctx.node.querySelector('span.w-12');
                if (numBadge) {
                    numBadge.classList.remove('bg-zinc-800', 'text-gray-500');
                    numBadge.classList.add('bg-blue-600/10', 'text-blue-500');
                }
                const title = ctx.node.querySelector('h4');
                if (title) {
                    title.classList.remove('group-hover:text-blue-500');
                    title.classList.add('text-blue-500');
                }
            }

            showFeedback('Bölüm açıldı.');
            closeModal();
            if (ctx.url) window.location.href = ctx.url;
        } catch (e) {
            showFeedback(e.message || 'Bölüm açılamadı.', 'error');
        } finally {
            window.__NMR_PURCHASE_CONTEXT = null;
            $btn.prop('disabled', false).removeClass('opacity-70');
        }
    });

    // Mobile Menu Toggle
    $("#menu-toggle").on("click", function () {
        $("#mobile-menu").fadeToggle(200);
        $("#user-modal").fadeOut(100);
    });

    // User Dropdown Toggle
    $("#user-btn").on("click", function (e) {
        e.stopPropagation();
        $("#user-modal").fadeToggle(150);
        $("#lang-modal").fadeOut(100);
        $("#mobile-menu").fadeOut(100);
    });

    // Language Dropdown Toggle
    $("#lang-btn").on("click", function (e) {
        e.stopPropagation();
        $("#lang-modal").fadeToggle(150);
        $("#user-modal").fadeOut(100);
        $("#mobile-menu").fadeOut(100);
    });

    // Close on click outside
    $(document).on("click", function () {
        $("#user-modal").fadeOut(150);
        $("#lang-modal").fadeOut(150);
    });

    $("#user-modal, #lang-modal").on("click", function (e) {
        e.stopPropagation();
    });

    $(".modal-overlay").on("click", function (e) {
        if (e.target === this) closeModal();
    });

    // --- COMMENTS LOGIC ---

    const loadComments = function(cursor = null, append = false) {
        const $container = $("#commentsList");
        console.log("loadComments trigger, container:", $container.length);
        if (!$container.length) return;

        const context = $container.data('context');
        const slug = $container.data('slug');
        const type = $container.data('type');
        const chapterId = $container.data('id');
        
        console.log("Comment Context:", { context, slug, type, chapterId });

        let apiUrl = '';
        if (context === 'content') {
            apiUrl = `/content/${type}/${slug}/comments`;
        } else if (context === 'chapter') {
            apiUrl = `/chapter/${chapterId}/comments`;
        } else if (context === 'blog') {
            apiUrl = `/blogs/${slug}/comments`;
        }

        console.log("Comment API URL:", apiUrl);

        if (!apiUrl || !window.NMRData) {
            console.warn("API URL or NMRData missing");
            return;
        }

        if (cursor) {
            apiUrl += `?cursor=${encodeURIComponent(cursor)}`;
        }

        window.NMRData.get(apiUrl)
            .then(res => {
                console.log("Comments received:", res.data?.length);
                renderComments(res.data || [], append);
                const nextCursor = res.meta?.next_cursor;
                const $loadMore = $("#commentsLoadMore");
                if (nextCursor) {
                    if (!$loadMore.length) {
                        $container.after('<button id="commentsLoadMore" class="mt-4 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white/80 border border-white/10 rounded-full hover:border-blue-500/40">Daha fazla</button>');
                    }
                    $("#commentsLoadMore").data('cursor', nextCursor).show();
                } else if ($loadMore.length) {
                    $loadMore.hide();
                }
            })
            .catch(err => {
                console.error('Comments failed:', err);
                $container.html(`<p class="text-red-500 text-sm">Yorumlar yüklenemedi.</p>`);
            });
    };

    const renderComments = function(comments, append = false) {
        const $container = $("#commentsList");
        if (!comments || !comments.length) {
            $container.html('<p class="text-gray-500 text-sm">Henüz yorum yapılmamış.</p>');
            return;
        }

        let html = '';
        comments.forEach(comment => {
            html += `
                <div class="glass p-6 rounded-3xl border border-white/5 hover:border-blue-500/30 transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-zinc-800 border border-white/10 flex items-center justify-center text-xs font-black">
                                ${(comment.username || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <p class="text-sm font-black text-white uppercase tracking-tight">${comment.username || 'Anonim'}</p>
                                <p class="text-[10px] text-gray-500 font-bold uppercase">${comment.created_at}</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-300 leading-relaxed">${comment.body}</p>
                </div>
            `;
        });
        if (append) {
            $container.append(html);
        } else {
            $container.html(html);
        }
    };

    loadComments();

    $(document).on('click', '#commentsLoadMore', function() {
        const cursor = $(this).data('cursor');
        if (!cursor) return;
        loadComments(cursor, true);
    });

    // --- MODAL LOGIC ---

    // 1. Reader Settings Tabs
    $("#readerTabSidebar button").on("click", function() {
        const tab = $(this).data('tab');
        $("#readerTabSidebar button").removeClass("bg-blue-600 text-white shadow-lg shadow-blue-600/20").addClass("text-gray-500 hover:bg-white/5 hover:text-white");
        $(this).addClass("bg-blue-600 text-white shadow-lg shadow-blue-600/20").removeClass("text-gray-500 hover:bg-white/5 hover:text-white");
        $(".settings-tab").addClass("hidden");
        $("#tab-" + tab).removeClass("hidden");
    });

    // 2. Reader Settings Range Input
    $('input[name="reader_font_size"]').on('input', function() {
        $('#fontSizeVal').text($(this).val() + 'px');
    });

    // 3. Reader Settings Theme Selection
    $('.theme-btn').on('click', function() {
        $('.theme-btn').removeClass('border-blue-600 bg-blue-600/10 text-white').addClass('text-gray-400');
        $(this).addClass('border-blue-600 bg-blue-600/10 text-white').removeClass('text-gray-400');
        // Actual theme application logic can go here or in save handler
    });

    // 4. Save Reader Settings
    $("#saveAllSettingsBtn").on("click", function() {
        const settings = {
            layout: $('select[name="reader_layout"]').val(),
            image_fit: $('select[name="reader_image_fit"]').val(),
            font_family: $('select[name="reader_font_family"]').val(),
            font_size: $('input[name="reader_font_size"]').val(),
            theme: $('.theme-btn.active').data('theme') || 'default'
        };
        localStorage.setItem('nm_reader_settings', JSON.stringify(settings));
        showFeedback('Ayarlar kaydedildi ve uygulandı.');
        setTimeout(() => location.reload(), 800);
    });

    // 5. Notifications Logic
    window.loadNotifications = function(cursor = null, append = false) {
        const $list = $("#notifModalList");
        if (!append) {
            $list.html('<div class="p-8 text-center text-gray-500 text-sm">Yükleniyor...</div>');
        }
        if (!window.NMRData) return;
        let apiUrl = '/user/notifications';
        if (cursor) {
            apiUrl += `?cursor=${encodeURIComponent(cursor)}`;
        }
        window.NMRData.get(apiUrl)
            .then(res => {
                renderNotifications(res.data || [], append);
                const nextCursor = res.meta?.next_cursor;
                const $loadMore = $("#notifLoadMore");
                if (nextCursor) {
                    if (!$loadMore.length) {
                        $list.after('<button id="notifLoadMore" class="mt-4 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white/80 border border-white/10 rounded-full hover:border-blue-500/40">Daha fazla</button>');
                    }
                    $("#notifLoadMore").data('cursor', nextCursor).show();
                } else if ($loadMore.length) {
                    $loadMore.hide();
                }
            })
            .catch(() => {
                if (!append) {
                    $list.html('<div class="p-8 text-center text-gray-500 text-sm">Bildirimler alınamadı.</div>');
                }
            });
    };

    const renderNotifications = function(notifs, append = false) {
        const $list = $("#notifModalList");
        if (!notifs.length) {
            $list.html('<div class="p-8 text-center text-gray-500 text-sm">Henüz bildiriminiz yok.</div>');
            return;
        }
        let html = '';
        notifs.forEach(n => {
            html += `
                <div class="p-4 border-b border-white/5 hover:bg-white/5 transition-all cursor-pointer ${n.is_read ? 'opacity-50' : ''}">
                    <p class="text-sm text-white mb-1">${n.message}</p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase">${n.created_at}</p>
                </div>
            `;
        });
        if (append) {
            $list.append(html);
        } else {
            $list.html(html);
        }
    };

    $(document).on('click', '#notifLoadMore', function() {
        const cursor = $(this).data('cursor');
        if (!cursor) return;
        window.loadNotifications(cursor, true);
    });

    $("#markAllReadBtn").on("click", function() {
        if (!window.NMRData) return;
        window.NMRData.post('/user/notifications/read')
            .then(() => {
                showFeedback('Tüm bildirimler okundu.');
                loadNotifications();
            });
    });

    // --- FORM HANDLERS ---

    $("#seriesCommentForm").on("submit", function(e) {
        e.preventDefault();
        if (!window.NMRData) return;
        
        const $container = $("#commentsList");
        const type = $container.data('type');
        const slug = $container.data('slug');
        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.text();
        const body = $(this).find('textarea[name="body"]').val();

        if (!body.trim()) {
            showFeedback('Lütfen bir yorum yazın.', 'error');
            return;
        }

        $btn.prop('disabled', true).text('...');

        window.NMRData.post(`/content/${type}/${slug}/comment`, { body })
            .then(() => {
                showFeedback('Yorum paylaşıldı.');
                $(this).find('textarea[name="body"]').val('');
                $btn.prop('disabled', false).text(originalText);
                loadComments();
            })
            .catch(err => {
                showFeedback(err.message || 'Yorum paylaşılamadı.', 'error');
                $btn.prop('disabled', false).text(originalText);
            });
    });

    $("#loginForm").on("submit", function(e) {
        e.preventDefault();
        if (!window.NMRData) return;
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
            .then(() => {
                showFeedback('Giriş başarılı! Yönlendiriliyorsunuz...');
                setTimeout(() => location.reload(), 1000);
            })
            .catch(err => {
                showFeedback(err.message || 'Giriş yapılamadı.', 'error');
                $btn.prop('disabled', false).text(originalText);
                if (typeof turnstile !== 'undefined') turnstile.reset();
            });
    });

    $("#registerForm").on("submit", function(e) {
        e.preventDefault();
        if (!window.NMRData) return;
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
            .then(() => {
                showFeedback('Kayıt başarılı! Giriş yapabilirsiniz.');
                setTimeout(() => openModal('loginModal'), 1500);
            })
            .catch(err => {
                showFeedback(err.message || 'Kayıt olunamadı.', 'error');
                $btn.prop('disabled', false).text(originalText);
                if (typeof turnstile !== 'undefined') turnstile.reset();
            });
    });

    $("#userSettingsForm").on("submit", function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('...');

        const formData = new FormData(this);
        $.ajax({
            url: '/api/v1/user/profile',
            method: 'POST',
            data: formData, processData: false, contentType: false,
            headers: { 'X-CSRF-Token': (window.__NMR_CONTEXT?.auth?.csrf_token) },
            success: function() {
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

    // --- PAGE SPECIFIC ---

    // 1. Home Slider
    const slides = $(".slide-item");
    const dots = $(".dot");
    if (slides.length > 0) {
        let currentSlide = 0;
        const showSlide = (n) => {
            slides.hide();
            dots.removeClass("bg-blue-600 w-8").addClass("bg-white/20 w-3");
            currentSlide = (n + slides.length) % slides.length;
            $(slides[currentSlide]).fadeIn(1000);
            $(dots[currentSlide]).removeClass("bg-white/20 w-3").addClass("bg-blue-600 w-8");
        };
        const nextSlide = () => showSlide(currentSlide + 1);
        let slideInterval = setInterval(nextSlide, 5000);
        dots.on("click", function () {
            clearInterval(slideInterval);
            showSlide($(this).data("idx"));
            slideInterval = setInterval(nextSlide, 5000);
        });
        showSlide(0);
    }

    // 2. Profile Tabs
    const loadWalletTab = async function() {
        const $tab = $("#tab-wallet");
        if (!$tab.length) return;
        if ($tab.data("loading")) return;
        if ($tab.data("loaded")) return;

        const msgLoading = $tab.data("msg-loading") || "Loading wallet data...";
        const msgFailed = $tab.data("msg-load-failed") || "Unable to load wallet data.";
        const msgLogin = $tab.data("msg-login") || "Please sign in.";
        const msgEmptyTx = $tab.data("msg-empty-tx") || "No transactions yet.";
        const msgEmptyPackages = $tab.data("msg-empty-packages") || "No active packages.";
        const msgEmptyFeatures = $tab.data("msg-empty-features") || "No active features.";
        const msgFeatureActive = $tab.data("msg-feature-active") || "Active";
        const msgFeatureInactive = $tab.data("msg-feature-inactive") || "Inactive";
        const msgFeatureUntil = $tab.data("msg-feature-until") || "Until";
        const msgFeatureCoin = $tab.data("msg-feature-coin") || "coin";
        const msgFeatureDay = $tab.data("msg-feature-day") || "day";

        const $status = $("#walletStatus");
        const $txBody = $("#walletTransactionsBody");
        const $packages = $("#walletPackagesGrid");
        const $features = $("#walletFeaturesGrid");

        $tab.data("loading", true);
        $status.text(msgLoading);

        const formatNumber = (value) => Number(value || 0).toLocaleString();
        const formatDate = (value) => {
            if (!value) return "--";
            const dt = new Date(value.replace(" ", "T"));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString();
        };

        let walletLoaded = false;

        if (window.NMRData) {
            try {
                const walletResp = await window.NMRData.get("/user/wallet");
                const wallet = walletResp.data || {};
                $("#walletBalanceValue").text(formatNumber(wallet.balance_coin));
                $("#walletTotalPurchased").text(formatNumber(wallet.total_coin_purchased));
                $("#walletTotalSpent").text(formatNumber(wallet.total_coin_spent));
                $("#walletUpdatedAt").text(formatDate(wallet.updated_at));
                walletLoaded = true;
            } catch (err) {
                $status.text(msgLogin);
            }
        } else {
            $status.text(msgFailed);
        }

        let txPage = 1;
        if (window.NMRData) {
            try {
                const txResp = await window.NMRData.get(`/user/wallet/transactions?page=${txPage}&per_page=10`);
                const items = (txResp.data && txResp.data.items) ? txResp.data.items : [];
                if (!items.length) {
                    $txBody.html(`<tr><td colspan="3" class="py-4 text-center text-gray-500">${msgEmptyTx}</td></tr>`);
                } else {
                    $txBody.html(items.map((t) => `
                        <tr class="border-b border-white/5">
                            <td class="py-3">${formatDate(t.created_at)}</td>
                            <td class="py-3 text-gray-300">${t.description || '-'}</td>
                            <td class="py-3 text-right ${Number(t.coin_delta) >= 0 ? 'text-emerald-400' : 'text-red-400'}">${formatNumber(t.coin_delta)}</td>
                        </tr>
                    `).join(""));
                }
                txPage += 1;
            } catch (err) {
                if (walletLoaded) {
                    $txBody.html(`<tr><td colspan="3" class="py-4 text-center text-gray-500">${msgEmptyTx}</td></tr>`);
                }
            }
        }

        if (window.NMRData) {
            try {
                const pkgResp = await window.NMRData.get("/shop/packages?page=1&per_page=12");
                const items = (pkgResp.data && pkgResp.data.items) ? pkgResp.data.items : [];
                if (!items.length) {
                    $packages.html(`<div class="text-sm text-gray-500">${msgEmptyPackages}</div>`);
                } else {
                    $packages.html(items.map((p) => `
                        <div class="bg-white/5 rounded-2xl p-4 border border-white/5">
                            <div class="text-xs text-gray-400 font-bold uppercase">${p.name || ''}</div>
                            <div class="text-2xl font-black text-white mt-2">${formatNumber(p.total_coin || (Number(p.coin_amount || 0) + Number(p.bonus_coin || 0)))} <span class="text-[10px] text-gray-400">coin</span></div>
                            <div class="text-[11px] text-gray-500 mt-2">${p.display_price ? `${p.display_price} ${p.currency || ''}` : ''}</div>
                        </div>
                    `).join(""));
                }
            } catch (err) {
                $packages.html(`<div class="text-sm text-gray-500">${msgEmptyPackages}</div>`);
            }
        }

        if (window.NMRData) {
            try {
                const featureResp = await window.NMRData.get("/shop/features");
                let features = featureResp.data || [];
                let userFeatures = {};
                try {
                    const statusResp = await window.NMRData.get("/user/features");
                    userFeatures = statusResp.data || {};
                } catch (err) {
                    userFeatures = {};
                }

                if (!features.length) {
                    $features.html(`<div class="text-sm text-gray-500">${msgEmptyFeatures}</div>`);
                } else {
                    $features.html(features.map((f) => {
                        const status = userFeatures[f.feature_key] || {};
                        const active = status.active ? true : false;
                        const label = active ? msgFeatureActive : msgFeatureInactive;
                        const dateLabel = status.expires_at ? `${msgFeatureUntil} ${formatDate(status.expires_at)}` : "";
                        return `
                            <div class="bg-white/5 rounded-2xl p-4 border border-white/5">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs text-gray-400 font-bold uppercase">${f.name || f.feature_key}</div>
                                    <span class="text-[10px] font-bold ${active ? 'text-emerald-400' : 'text-gray-500'} uppercase">${label}</span>
                                </div>
                                <div class="text-sm text-gray-300 mt-2">${formatNumber(f.coin_price || 0)} ${msgFeatureCoin} / ${formatNumber(f.duration_days || 0)} ${msgFeatureDay}</div>
                                <div class="text-[11px] text-gray-500 mt-1">${dateLabel}</div>
                            </div>
                        `;
                    }).join(""));
                }
            } catch (err) {
                $features.html(`<div class="text-sm text-gray-500">${msgEmptyFeatures}</div>`);
            }
        }

        if (walletLoaded) {
            $status.text("");
        } else {
            $status.text($status.text() || msgFailed);
        }

        $("#walletLoadMoreBtn").on("click", async function() {
            if (!window.NMRData) return;
            const $btn = $(this);
            if ($btn.data("loading")) return;
            $btn.data("loading", true);
            try {
                const txResp = await window.NMRData.get(`/user/wallet/transactions?page=${txPage}&per_page=10`);
                const items = (txResp.data && txResp.data.items) ? txResp.data.items : [];
                if (items.length) {
                    $txBody.append(items.map((t) => `
                        <tr class="border-b border-white/5">
                            <td class="py-3">${formatDate(t.created_at)}</td>
                            <td class="py-3 text-gray-300">${t.description || '-'}</td>
                            <td class="py-3 text-right ${Number(t.coin_delta) >= 0 ? 'text-emerald-400' : 'text-red-400'}">${formatNumber(t.coin_delta)}</td>
                        </tr>
                    `).join(""));
                    txPage += 1;
                }
            } catch (err) {
                // ignore load more failures
            }
            $btn.data("loading", false);
        });

        $tab.data("loaded", true);
        $tab.data("loading", false);
    };

    if (templateName === "profile.php") {
        $(".tab-btn").on("click", function () {
            const target = $(this).data("tab");
            $(".tab-btn").removeClass("tab-active text-white").addClass("text-gray-500");
            $(this).addClass("tab-active text-white").removeClass("text-gray-500");
            $(".tab-content").addClass("hidden");
            $("#tab-" + target).removeClass("hidden");
            if (target === 'blogs') $("#tab-blogs").addClass("grid"); else $("#tab-blogs").removeClass("grid");
            if (target === 'wallet') loadWalletTab();
        });
    }

    // 3. Search Pills
    if (templateName === "search.php") {
        $(".tag-pill-genre").on("click", function () {
            const slug = $(this).data("slug");
            if (slug === "") {
                $(".tag-pill-genre").removeClass("active bg-blue-600 text-white").addClass("bg-white/5 text-gray-400");
                $(this).addClass("active").removeClass("bg-white/5 text-gray-400");
            } else {
                $(".tag-pill-genre[data-slug='']").removeClass("active bg-blue-600 text-white").addClass("bg-white/5 text-gray-400");
                $(this).toggleClass("active bg-white/5 text-gray-400");
                if ($(".tag-pill-genre.active").length === 0) $(".tag-pill-genre[data-slug='']").addClass("active").removeClass("bg-white/5 text-gray-400");
            }
            if (window.updateSearchInputs) window.updateSearchInputs();
        });

        $(".tag-pill-tag").on("click", function () {
            $(this).toggleClass("active bg-white/5 text-gray-400");
            if (window.updateSearchInputs) window.updateSearchInputs();
        });

        window.updateSearchInputs = function() {
            const genres = []; $(".tag-pill-genre.active").each(function() { const s = $(this).data("slug"); if (s) genres.push(s); });
            $("#genresInput").val(genres.join(','));
            const tags = []; $(".tag-pill-tag.active").each(function() { const s = $(this).data("slug"); if (s) tags.push(s); });
            $("#tagsInput").val(tags.join(','));
        };
    }

    // 4. Reader Progress
    if (templateName === "chapter.php") {
        $(window).on("scroll", function () {
            const $bar = $("#reader-progress-bar");
            if ($bar.length) {
                let winHeight = $(window).height();
                let docHeight = $(document).height();
                let scrollTop = $(window).scrollTop();
                let progress = (scrollTop / (docHeight - winHeight)) * 100;
                $bar.css("width", progress + "%");
            }
            const $readProgress = $("#readingProgress");
            if ($readProgress.length) {
                let wintop = $(window).scrollTop(), docheight = $(document).height(), winheight = $(window).height();
                let scrolled = (wintop / (docheight - winheight)) * 100;
                $readProgress.css("width", scrolled + "%");
            }
        });
    }

    // Simple entry animations
    $(".chapter-row, .blog-card").css("opacity", "0");
    $(".chapter-row, .blog-card").each(function (i) {
        $(this).delay(50 * i).animate({ opacity: 1 }, 300);
    });
});
