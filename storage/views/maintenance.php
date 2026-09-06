<?php
/** @var string $siteName */
/** @var string $siteLogo */
/** @var string $panelUrl */

$siteNameEscaped = htmlspecialchars($siteName ?? 'NM Reader');
$panelUrlEscaped = htmlspecialchars($panelUrl ?? '/panel');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Modu — <?= $siteNameEscaped ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0b0f19;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            text-align: center;
        }
        .card {
            max-width: 520px;
            width: 100%;
            padding: 48px 36px;
            background: #131b2e;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            border: 1px solid #1e293b;
        }
        .icon {
            font-size: 52px;
            margin-bottom: 24px;
            display: inline-block;
            animation: pulse 2.5s infinite ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 14px;
            color: #38bdf8;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.65;
            margin-bottom: 28px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #334155;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            background: #38bdf8;
            color: #0b0f19;
            border-color: #38bdf8;
            transform: translateY(-1px);
        }
        .footer {
            margin-top: 36px;
            font-size: 12px;
            color: #64748b;
        }

        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(5, 8, 16, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Modal Card */
        .modal-card {
            max-width: 440px;
            width: 100%;
            background: #131b2e;
            border: 1px solid #1e293b;
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85);
            text-align: left;
            position: relative;
            transform: scale(0.96);
            transition: transform 0.2s ease-out;
        }
        .modal-overlay.active .modal-card {
            transform: scale(1);
        }
        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .modal-header p {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 0;
        }
        .close-btn {
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
            padding: 4px 8px;
            border-radius: 8px;
            transition: color 0.15s;
        }
        .close-btn:hover {
            color: #f8fafc;
            background: #1e293b;
        }

        /* Form fields */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            background: #0b0f19;
            border: 1px solid #334155;
            border-radius: 10px;
            color: #f8fafc;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            font-size: 13px;
            color: #94a3b8;
            cursor: pointer;
            user-select: none;
        }
        .checkbox-group input {
            cursor: pointer;
            accent-color: #38bdf8;
            width: 16px;
            height: 16px;
        }
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: #0284c7;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover:not(:disabled) {
            background: #38bdf8;
            color: #0b0f19;
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Error Box */
        .error-alert {
            display: none;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #f87171;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🛠️</div>
        <h1>Sistem Bakımda</h1>
        <p><?= $siteNameEscaped ?> şu anda planlı bakım ve altyapı güncellemesi nedeniyle geçici olarak hizmet dışıdır. Lütfen kısa süre sonra tekrar deneyiniz.</p>
        <div>
            <button type="button" id="btnOpenLogin" class="btn-action">
                <span>🔐</span>
                <span>Yönetici Girişi</span>
            </button>
        </div>
        <div class="footer">
            © <?= date('Y') ?> <?= $siteNameEscaped ?> · Tüm hakları saklıdır.
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h2>Yönetici Girişi</h2>
                    <p>Bakım sırasında sisteme erişmek için kimliğinizi doğrulayın.</p>
                </div>
                <button type="button" id="btnCloseModal" class="close-btn" aria-label="Kapat">&times;</button>
            </div>

            <div id="loginError" class="error-alert"></div>

            <form id="adminLoginForm">
                <div class="form-group">
                    <label for="m_identity">E-posta veya Kullanıcı Adı</label>
                    <input type="text" id="m_identity" name="identity" class="form-control" autocomplete="username" placeholder="admin@novastrum.xyz" required>
                </div>

                <div class="form-group">
                    <label for="m_password">Şifre</label>
                    <input type="password" id="m_password" name="password" class="form-control" autocomplete="current-password" placeholder="••••••••" required>
                </div>

                <label class="checkbox-group">
                    <input type="checkbox" id="m_remember" checked>
                    <span>Beni Hatırla</span>
                </label>

                <button type="submit" id="m_submitBtn" class="btn-submit">Giriş Yap</button>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('loginModal');
            const openBtn = document.getElementById('btnOpenLogin');
            const closeBtn = document.getElementById('btnCloseModal');
            const form = document.getElementById('adminLoginForm');
            const errorAlert = document.getElementById('loginError');
            const submitBtn = document.getElementById('m_submitBtn');
            const identityInput = document.getElementById('m_identity');

            function openModal() {
                modal.classList.add('active');
                errorAlert.style.display = 'none';
                setTimeout(() => identityInput.focus(), 50);
            }

            function closeModal() {
                modal.classList.remove('active');
                errorAlert.style.display = 'none';
            }

            openBtn.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeModal();
                }
            });

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                errorAlert.style.display = 'none';
                errorAlert.innerText = '';

                const identity = identityInput.value.trim();
                const password = document.getElementById('m_password').value;
                const remember = document.getElementById('m_remember').checked;

                if (!identity || !password) return;

                submitBtn.disabled = true;
                submitBtn.innerText = 'Giriş Yapılıyor...';

                try {
                    const response = await fetch('/api/v1/auth/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            identity: identity,
                            email: identity,
                            password: password,
                            remember: remember
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.status === 'success') {
                        const user = result.data || {};
                        const roles = Array.isArray(user.roles) ? user.roles : [];
                        const permissions = Array.isArray(user.permissions) ? user.permissions : [];
                        const hasAccess = permissions.includes('admin.panel.access')
                            || roles.includes('admin')
                            || roles.includes('superadmin');

                        if (!hasAccess) {
                            // User is a valid reader/member, but not an admin/staff
                            try {
                                fetch('/api/v1/auth/logout', { method: 'POST' });
                            } catch (ignore) {}

                            errorAlert.innerText = 'Bu hesabın yönetim paneline erişim yetkisi bulunmamaktadır. Bakım modunda yalnızca yetkili yöneticiler sisteme giriş yapabilir.';
                            errorAlert.style.display = 'block';
                            submitBtn.disabled = false;
                            submitBtn.innerText = 'Giriş Yap';
                            return;
                        }

                        submitBtn.innerText = 'Giriş Başarılı! Yönlendiriliyor...';
                        submitBtn.style.background = '#10b981';
                        setTimeout(function() {
                            window.location.href = '<?= $panelUrlEscaped ?>';
                        }, 500);
                    } else {
                        const message = (result && result.error && result.error.message) 
                            ? result.error.message 
                            : 'Giriş başarısız. Lütfen bilgilerinizi kontrol ediniz.';
                        errorAlert.innerText = message;
                        errorAlert.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Giriş Yap';
                    }
                } catch (err) {
                    errorAlert.innerText = 'Sunucuya bağlanırken bir hata oluştu. Lütfen tekrar deneyin.';
                    errorAlert.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Giriş Yap';
                }
            });
        })();
    </script>
</body>
</html>
