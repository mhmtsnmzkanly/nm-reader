/**
 * NMR Reader - Global Logic
 */

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
    // Initialize Icons
    if (window.lucide) lucide.createIcons();

    // Auth Button
    $("#openAuthBtn").on("click", function() {
        openModal('loginModal');
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

    const loadComments = function() {
        const $container = $("#commentsList");
        if (!$container.length) return;

        const context = $container.data('context');
        const slug = $container.data('slug');
        const type = $container.data('type');
        const chapterId = $container.data('id');

        let apiUrl = '';
        if (context === 'content') {
            apiUrl = `/content/${type}/${slug}/comments`;
        } else if (context === 'chapter') {
            apiUrl = `/chapter/${chapterId}/comments`;
        } else if (context === 'blog') {
            apiUrl = `/blogs/${slug}/comments`;
        }

        if (!apiUrl || !window.NMRData) return;

        window.NMRData.get(apiUrl)
            .then(res => {
                renderComments(res.data || []);
            })
            .catch(err => {
                console.error('Comments failed:', err);
                $container.html(`<p class="text-red-500 text-sm">Yorumlar yüklenemedi.</p>`);
            });
    };

    const renderComments = function(comments) {
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
        $container.html(html);
    };

    loadComments();

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
    $(".tab-btn").on("click", function () {
        const target = $(this).data("tab");
        $(".tab-btn").removeClass("tab-active text-white").addClass("text-gray-500");
        $(this).addClass("tab-active text-white").removeClass("text-gray-500");
        $(".tab-content").addClass("hidden");
        $("#tab-" + target).removeClass("hidden");
        if (target === 'blogs') $("#tab-blogs").addClass("grid"); else $("#tab-blogs").removeClass("grid");
    });

    // 3. Search Pills
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

    // 4. Reader Progress
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

    // Simple entry animations
    $(".chapter-row, .blog-card").css("opacity", "0");
    $(".chapter-row, .blog-card").each(function (i) {
        $(this).delay(50 * i).animate({ opacity: 1 }, 300);
    });
});
