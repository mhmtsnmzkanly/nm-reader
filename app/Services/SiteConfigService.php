<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SiteConfigService
{
    private const CACHE_KEY = 'site_config:all:v1';
    private const CACHE_TTL = 600;

    /**
     * @var array<string, array{type:string,default:mixed,max?:int,allowed?:array<int,string>}>
     */
    private const DEFINITIONS = [
        'site_name' => ['type' => 'string', 'default' => 'NM Reader', 'max' => 120],
        'site_slogan' => ['type' => 'string', 'default' => 'En İyi Çevrimiçi Manga ve Novel Okuyucusu', 'max' => 255],
        'site_abbreviation' => ['type' => 'string', 'default' => 'NMR', 'max' => 20],
        'site_logo' => ['type' => 'string', 'default' => '/assets/img/logo.svg', 'max' => 255],
        'favicon_url' => ['type' => 'string', 'default' => '/favicon.ico', 'max' => 255],
        'footer_text' => ['type' => 'string', 'default' => '© 2026 NM Reader. Tüm hakları saklıdır.', 'max' => 500],
        'site_description' => ['type' => 'string', 'default' => 'Read manga, manhwa, webtoon and novels.', 'max' => 1000],
        'enforce_https' => ['type' => 'bool', 'default' => false],
        'site_address' => ['type' => 'string', 'default' => '', 'max' => 255],
        'default_language' => ['type' => 'string', 'default' => 'tr', 'allowed' => ['tr', 'en']],
        'default_theme' => ['type' => 'string', 'default' => 'dark', 'allowed' => ['default', 'dark', 'royal', 'bootstrap', 'material', 'apple', 'glass']],
        'default_profile_image' => ['type' => 'string', 'default' => '/assets/img/default-profile.png', 'max' => 255],
        'default_content_cover_image' => ['type' => 'string', 'default' => '/assets/img/covers/placeholder.svg', 'max' => 255],
        'maintenance_mode' => ['type' => 'bool', 'default' => false],
        'maintenance_whitelist_ips' => ['type' => 'json', 'default' => ['127.0.0.1', '::1']],
        'integrations' => ['type' => 'json', 'default' => [
            'google_analytics_id' => '',
            'google_recaptcha_site_key' => '',
            'google_recaptcha_secret_key' => '',
        ]],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CacheService $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $settings = $this->defaults();
        try {
            $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM system_settings');
            if ($stmt !== false) {
                $rows = $stmt->fetchAll();
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!is_array($row) || !isset($row['setting_key'])) {
                            continue;
                        }
                        $key = (string) $row['setting_key'];
                        $raw = $row['setting_value'] ?? null;
                        if (isset(self::DEFINITIONS[$key])) {
                            $settings[$key] = $this->castValue($key, $raw);
                        } else {
                            $settings[$key] = $raw;
                        }
                    }
                }
            }
        } catch (\Throwable) {}

        $this->cache->set(self::CACHE_KEY, $settings, self::CACHE_TTL);
        return $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function siteName(): string
    {
        return (string) $this->get('site_name', self::DEFINITIONS['site_name']['default']);
    }

    public function siteSlogan(): string
    {
        return (string) $this->get('site_slogan', self::DEFINITIONS['site_slogan']['default']);
    }

    public function siteLogo(): string
    {
        return (string) $this->get('site_logo', self::DEFINITIONS['site_logo']['default']);
    }

    public function faviconUrl(): string
    {
        return (string) $this->get('favicon_url', self::DEFINITIONS['favicon_url']['default']);
    }

    public function footerText(): string
    {
        return (string) $this->get('footer_text', self::DEFINITIONS['footer_text']['default']);
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance_mode', false);
    }

    public function maintenanceWhitelistIps(): array
    {
        $val = $this->get('maintenance_whitelist_ips', ['127.0.0.1', '::1']);
        return is_array($val) ? $val : ['127.0.0.1', '::1'];
    }

    public function siteAbbreviation(): string
    {
        return (string) $this->get('site_abbreviation', self::DEFINITIONS['site_abbreviation']['default']);
    }

    public function siteDescription(): string
    {
        return (string) $this->get('site_description', self::DEFINITIONS['site_description']['default']);
    }

    public function enforceHttps(): bool
    {
        return (bool) $this->get('enforce_https', self::DEFINITIONS['enforce_https']['default']);
    }

    public function siteAddress(): string
    {
        return (string) $this->get('site_address', self::DEFINITIONS['site_address']['default']);
    }

    public function defaultLanguage(): string
    {
        return (string) $this->get('default_language', self::DEFINITIONS['default_language']['default']);
    }

    public function defaultTheme(): string
    {
        return (string) $this->get('default_theme', self::DEFINITIONS['default_theme']['default']);
    }

    public function defaultProfileImage(): string
    {
        return (string) $this->get('default_profile_image', self::DEFINITIONS['default_profile_image']['default']);
    }

    public function defaultContentCoverImage(): string
    {
        return (string) $this->get('default_content_cover_image', self::DEFINITIONS['default_content_cover_image']['default']);
    }

    /**
     * @return array<string, mixed>
     */
    public function integrations(): array
    {
        $value = $this->get('integrations', self::DEFINITIONS['integrations']['default']);
        return is_array($value) ? $value : (array) self::DEFINITIONS['integrations']['default'];
    }

    public function update(array $payload, ?string $moderatorId = null): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at)
             VALUES (:key, :val, :grp, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );

        foreach ($payload as $key => $val) {
            $key = (string) $key;
            if (!isset(self::DEFINITIONS[$key])) {
                continue;
            }

            $serialized = $this->normalizeForWrite($key, $val);
            $group = in_array($key, ['maintenance_mode', 'maintenance_whitelist_ips'], true) ? 'security' : (in_array($key, ['default_theme', 'site_logo', 'favicon_url'], true) ? 'appearance' : 'general');
            $stmt->execute([
                'key' => $key,
                'val' => $serialized,
                'grp' => $group,
            ]);
        }

        $this->cache->delete(self::CACHE_KEY);
        return $this->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * @return array<string, array{type:string,default:mixed,max?:int,allowed?:array<int,string>}>
     */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    private function castValue(string $key, mixed $raw): mixed
    {
        $definition = self::DEFINITIONS[$key];

        return match ($definition['type']) {
            'bool' => $this->toBool($raw),
            'json' => $this->toJson($raw, $definition['default']),
            default => $this->toString($raw, $definition),
        };
    }

    private function normalizeForWrite(string $key, mixed $value): string
    {
        $definition = self::DEFINITIONS[$key];
        $type = $definition['type'];

        if ($type === 'bool') {
            return $this->toBool($value) ? '1' : '0';
        }

        if ($type === 'json') {
            if (!is_array($value)) {
                throw new \InvalidArgumentException(sprintf('%s must be an object/array', $key));
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \InvalidArgumentException(sprintf('%s contains invalid JSON data', $key));
            }

            return $encoded;
        }

        $string = trim((string) $value);

        if (isset($definition['allowed']) && !in_array($string, $definition['allowed'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid %s value', $key));
        }

        if (isset($definition['max']) && mb_strlen($string) > (int) $definition['max']) {
            throw new \InvalidArgumentException(sprintf('%s exceeds max length %d', $key, (int) $definition['max']));
        }

        return $string;
    }

    /**
     * @param array{type:string,default:mixed,max?:int,allowed?:array<int,string>} $definition
     */
    private function toString(mixed $raw, array $definition): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            $value = (string) $definition['default'];
        }

        if (isset($definition['allowed']) && !in_array($value, $definition['allowed'], true)) {
            return (string) $definition['default'];
        }

        if (isset($definition['max']) && mb_strlen($value) > (int) $definition['max']) {
            return mb_substr($value, 0, (int) $definition['max']);
        }

        return $value;
    }

    private function toBool(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        $value = strtolower(trim((string) $raw));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function toJson(mixed $raw, mixed $fallback): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return is_array($fallback) ? $fallback : [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : (is_array($fallback) ? $fallback : []);
    }
}
