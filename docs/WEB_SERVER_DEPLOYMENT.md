# Web Sunucusu Yapılandırma ve Dağıtım Rehberi (Nginx, Caddy, Apache)

Bu belge, **NM-Reader** uygulamasının canlı (production) veya yerel ortamlarda web sunucuları (Nginx, Caddy, Apache) ile nasıl güvenli, yüksek performanslı ve doğru şekilde çalıştırılacağını açıklamaktadır.

---

## 1. Temel Mimari İlkeler

NM-Reader mimarisi aşağıdaki güvenlik ve performans ilkeleri üzerine kuruludur:

1. **Document Root İzolasyonu:**
   * Web sunucusunun `root` (kök dizin) ayarı **kesinlikle `public/` alt klasörüne** ayarlanmalıdır (`/var/www/nm-reader/public`).
   * Bu sayede `.env`, `composer.json`, `storage/`, `app/`, `vendor/` ve `ui/` dizinleri doğrudan web erişimine tamamen kapatılmış olur.
2. **Eksik Statik Dosyalarda Doğrudan 404 (Direct 404 for Missing Assets):**
   * SPA (Single Page Application) yapılarında genel fallback (`try_files $uri /index.php`) nedeniyle diskte bulunmayan `.png`, `.jpg`, `.css`, `.js`, `.webp` gibi dosya istekleri `index.php`'ye yönlenerek gereksiz yere 30-50 KB'lık React HTML kabuğunu döndürebilir.
   * NM-Reader'da bu durum hem web sunucusu düzeyinde (`nginx`, `caddy`, `.htaccess`) hem de uygulama düzeyinde ([`app/middleware.php`](file:///home/duldul/Belgeler/nm-reader/app/middleware.php)) çift katmanlı olarak engellenmiştir. Bulunamayan statik dosyalar PHP veya React çalıştırılmadan doğrudan HTTP 404 yanıtı alır.
3. **Dahili HTML Şablon Koruması:**
   * `public/admin.html`, `public/install.html` ve `public/maintenance.html` dosyaları doğrudan indirilebilecek statik sayfalar değildir; PHP controller'ları ([`AdminShellController`](file:///home/duldul/Belgeler/nm-reader/app/Controllers/AdminShellController.php), [`InstallController`](file:///home/duldul/Belgeler/nm-reader/app/Controllers/InstallController.php)) tarafından dinamik olarak sunulur. Bu dosyalara doğrudan gelen istekler 404 ile reddedilir.
4. **Dinamik Sanal Dosyalar (Virtual Endpoints):**
   * `/robots.txt`, `/sitemap.xml`, `/media/*` ve `/api/*` rotaları, dosya uzantısına benzese dahi Slim/PHP tarafından dinamik üretilir ve statik dosya 404 filtrelerinin dışında tutulur.
5. **Yüksek Boyutlu Yükleme ve Timeout Desteği:**
   * Bölüm ZIP dosyası yüklemeleri için gövde sınırı en az **256MB** olmalıdır.
   * Yedekleme, sitemap üretimi ve önbellek ısıtma gibi uzun süren bakım işleri için FastCGI timeout süresi **300 saniye** olarak ayarlanmalıdır.

---

## 2. Nginx Yapılandırması

Proje kök dizininde hazır bir örnek bulunmaktadır: [`nginx.conf.example`](file:///home/duldul/Belgeler/nm-reader/nginx.conf.example)

### Kurulum Adımları
```bash
# 1. Örnek yapılandırmayı Nginx siteleri arasına kopyalayın
sudo cp nginx.conf.example /etc/nginx/sites-available/nm-reader.conf

# 2. Alan adı (server_name), root yolu ve PHP-FPM soketini düzenleyin
sudo nano /etc/nginx/sites-available/nm-reader.conf

# 3. Sembolik bağlantı oluşturarak siteyi aktif edin
sudo ln -s /etc/nginx/sites-available/nm-reader.conf /etc/nginx/sites-enabled/

# 4. Yapılandırmayı test edin ve Nginx'i yeniden yükleyin
sudo nginx -t
sudo systemctl reload nginx
```

### Örnek Nginx Bloğu
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;

    # Kök dizin public/ klasörünü göstermelidir
    root /var/www/nm-reader/public;
    index index.php index.html;
    charset utf-8;

    # Bölüm ZIP ve görsel yüklemeleri için sınır
    client_max_body_size 256M;

    # Güvenlik başlıkları
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Sıkıştırma (Gzip)
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript image/svg+xml;

    # 1. Dahili HTML şablonlarına doğrudan erişimi engelle
    location ~* ^/(?:admin|install|maintenance)\.html$ {
        return 404;
    }

    # 2. Gizli dosyaları engelle (.env, .git, .htaccess)
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # 3. Dinamik sanal rotalar (PHP tarafından üretilenler)
    location = /robots.txt { try_files $uri /index.php$is_args$args; }
    location = /sitemap.xml { try_files $uri /index.php$is_args$args; }
    location ^~ /api/ { try_files $uri /index.php$is_args$args; }
    location ^~ /media/ { try_files $uri /index.php$is_args$args; }

    # 4. Statik varlıklar ve doğrudan 404 koruması
    # Eksik statik dosyalarda index.php veya React UI çalıştırılmaz
    location ^~ /assets/ {
        try_files $uri =404;
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }

    location ~* \.(?:css|js|mjs|map|json|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|eot|otf|mp4|webm|pdf|zip)$ {
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        log_not_found off;
    }

    # 5. Front Controller (React SPA ve temiz URL'ler)
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # 6. PHP-FPM FastCGI
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock; # veya 127.0.0.1:9000
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        include fastcgi_params;

        fastcgi_read_timeout 300s;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
}
```

---

## 3. Caddy Yapılandırması

Proje kök dizininde hazır bir örnek bulunmaktadır: [`Caddyfile.example`](file:///home/duldul/Belgeler/nm-reader/Caddyfile.example)

Caddy, otomatik SSL sertifikası (Let's Encrypt / ZeroSSL) ve sade sözdizimi ile modern bir alternatiftir.

### Kurulum Adımları
```bash
# 1. Örnek yapılandırmayı Caddy yapılandırma dosyasına aktarın
sudo cp Caddyfile.example /etc/caddy/Caddyfile

# 2. Alan adı, root yolu ve PHP-FPM soketini düzenleyin
sudo nano /etc/caddy/Caddyfile

# 3. Yapılandırmayı doğrulayın ve Caddy'yi yeniden yükleyin
caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

### Örnek Caddyfile
```caddy
example.com {
    root * /var/www/nm-reader/public
    encode gzip zstd

    request_body {
        max_size 256MB
    }

    header {
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        Referrer-Policy "strict-origin-when-cross-origin"
        -Server
    }

    # Dahili şablonları doğrudan erişime kapat
    @protected_shells path /admin.html /install.html /maintenance.html
    respond @protected_shells 404

    # Gizli dosyaları engelle
    @dotfiles path */.*
    respond @dotfiles 404

    # Eksik statik dosyalarda doğrudan 404 (React'e yönlendirme yapmaz)
    @static_missing {
        not path /robots.txt /sitemap.xml /api/* /media/*
        path /assets/* *.css *.js *.mjs *.map *.json *.png *.jpg *.jpeg *.gif *.webp *.avif *.svg *.ico *.woff *.woff2 *.ttf *.eot *.otf *.mp4 *.webm *.pdf *.zip
        not file
    }
    respond @static_missing 404

    # Var olan statik dosyalara önbellek başlığı
    @static_assets {
        path /assets/* *.css *.js *.mjs *.map *.json *.png *.jpg *.jpeg *.gif *.webp *.avif *.svg *.ico *.woff *.woff2 *.ttf *.eot *.otf *.mp4 *.webm *.pdf *.zip
        file
    }
    header @static_assets Cache-Control "public, max-age=2592000"

    # PHP-FPM ve Front Controller yönlendirmesi
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
}
```

---

## 4. Apache (.htaccess) Desteği

Apache kullanılıyorsa, projedeki [`.htaccess`](file:///home/duldul/Belgeler/nm-reader/.htaccess) ve [`public/.htaccess`](file:///home/duldul/Belgeler/nm-reader/public/.htaccess) dosyaları otomatik olarak devreye girer.

* `mod_rewrite`, `mod_headers` ve `mod_mime` modüllerinin aktif olması gerekir.
* Apache Sanal Konak (VirtualHost) ayarında `AllowOverride All` tanımlanmalıdır:
```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/nm-reader/public

    <Directory /var/www/nm-reader/public>
        Options -MultiViews -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 5. Yerel Geliştirme Sunucusu (PHP Built-in Server)

Geliştirme ortamında PHP yerleşik sunucusu kullanılabilir:

```bash
composer serve
# veya:
php -S 127.0.0.1:8080 -t public public/index.php
```

[`public/index.php`](file:///home/duldul/Belgeler/nm-reader/public/index.php) içerisinde yer alan CLI server koruması sayesinde, yerel ortamda da eksik statik dosyalar Slim uygulamasını başlatmadan anında `404 Not Found` yanıtı döner.
