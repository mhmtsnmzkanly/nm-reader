<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovelMangaReader Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0b0e14; color: #fff; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .setup-card { background: #1a1e26; border: 1px solid #2d343f; border-radius: 12px; padding: 2rem; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .form-label { color: #8a94a6; font-size: 0.9rem; margin-bottom: 0.3rem; }
        .form-control { background-color: #0b0e14; border: 1px solid #2d343f; color: #fff; border-radius: 8px; padding: 0.6rem 1rem; }
        .form-control:focus { background-color: #0b0e14; border-color: #0d6efd; color: #fff; box-shadow: none; }
        .btn-primary { background-color: #0d6efd; border: none; border-radius: 8px; padding: 0.8rem; font-weight: 600; width: 100%; margin-top: 1rem; }
        .setup-header { text-align: center; margin-bottom: 2rem; }
        .setup-header h1 { font-size: 1.5rem; font-weight: 700; color: #0d6efd; margin-bottom: 0.5rem; }
        .setup-header p { color: #8a94a6; font-size: 0.9rem; }
        .step-title { border-bottom: 1px solid #2d343f; padding-bottom: 0.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: #fff; }
        #status-msg { display: none; margin-top: 1rem; padding: 1rem; border-radius: 8px; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header">
        <h1>NovelMangaReader</h1>
        <p>System Installation Wizard</p>
    </div>

    <form id="setup-form">
        <div class="step-title">Database Configuration</div>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Host</label>
                <input type="text" name="db[host]" class="form-control" value="127.0.0.1" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Port</label>
                <input type="text" name="db[port]" class="form-control" value="3306" required>
            </div>
            <div class="col-12">
                <label class="form-label">Database Name</label>
                <input type="text" name="db[database]" class="form-control" value="nm-reader" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="db[username]" class="form-control" value="root" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="db[password]" class="form-control" placeholder="Optional">
            </div>
        </div>

        <div class="step-title mt-4">Administrator Account</div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Admin Username</label>
                <input type="text" name="admin[username]" class="form-control" value="admin" required>
            </div>
            <div class="col-12">
                <label class="form-label">Admin Email</label>
                <input type="email" name="admin[email]" class="form-control" value="admin@example.com" required>
            </div>
            <div class="col-12">
                <label class="form-label">Admin Password</label>
                <input type="password" name="admin[password]" class="form-control" required minlength="8">
            </div>
        </div>

        <button type="submit" id="submit-btn" class="btn btn-primary">Start Installation</button>
    </form>

    <div id="status-msg"></div>
</div>

<script>
    document.getElementById('setup-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        const msg = document.getElementById('status-msg');
        const formData = new FormData(e.target);
        
        btn.disabled = true;
        btn.innerText = 'Installing...';
        msg.style.display = 'none';

        // Convert form data to nested object for the backend
        const payload = { db: {}, admin: {} };
        formData.forEach((value, key) => {
            const parts = key.split(/\[|\]/).filter(p => p !== '');
            payload[parts[0]][parts[1]] = value;
        });

        try {
            const res = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.status === 'success') {
                msg.className = 'alert alert-success';
                msg.innerText = data.data.message;
                msg.style.display = 'block';
                setTimeout(() => window.location.href = '/', 2000);
            } else {
                throw new Error(data.error.message || 'Unknown error');
            }
        } catch (err) {
            msg.className = 'alert alert-danger';
            msg.innerText = err.message;
            msg.style.display = 'block';
            btn.disabled = false;
            btn.innerText = 'Start Installation';
        }
    });
</script>

</body>
</html>
