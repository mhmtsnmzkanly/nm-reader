<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SiteConfigService
{
    private const CACHE_KEY = 'site_config:all:v2';
    private const CACHE_TTL = 600;

    /** @var list<string> Settings safe to embed in public HTML/application context. */
    private const PUBLIC_KEYS = [
        'site_name',
        'site_slogan',
        'site_abbreviation',
        'site_description',
        'default_language',
        'footer_text',
        'default_theme',
        'site_logo',
        'logo_url',
        'favicon_url',
        'default_profile_image',
        'default_content_cover_image',
    ];

    private static ?array $memoryCache = null;

    /**
     * Definitions of all supported settings, their groups, types, and defaults.
     *
     * @var array<string, array{group:string,type:'string'|'int'|'bool'|'json',default:mixed,max?:int,allowed?:array<int,string>}>
     */
    private const DEFINITIONS = [
        // General Group
        'site_name' => ['group' => 'general', 'type' => 'string', 'default' => 'NM Reader', 'max' => 120],
        'site_slogan' => ['group' => 'general', 'type' => 'string', 'default' => 'En İyi Çevrimiçi Manga ve Novel Okuyucusu', 'max' => 255],
        'site_abbreviation' => ['group' => 'general', 'type' => 'string', 'default' => 'NMR', 'max' => 20],
        'site_description' => ['group' => 'general', 'type' => 'string', 'default' => 'Read manga, manhwa, webtoon and novels.', 'max' => 1000],
        'default_language' => ['group' => 'general', 'type' => 'string', 'default' => 'tr', 'allowed' => ['tr', 'en']],
        'footer_text' => ['group' => 'general', 'type' => 'string', 'default' => '© 2026 NM Reader. Tüm hakları saklıdır.', 'max' => 500],

        // Appearance Group
        'default_theme' => ['group' => 'appearance', 'type' => 'string', 'default' => 'dark', 'allowed' => ['default', 'dark', 'royal', 'bootstrap', 'material', 'apple', 'glass']],
        'site_logo' => ['group' => 'appearance', 'type' => 'string', 'default' => '/assets/img/logo-header.svg', 'max' => 255],
        'logo_url' => ['group' => 'appearance', 'type' => 'string', 'default' => '/assets/img/logo-footer.svg', 'max' => 255],
        'favicon_url' => ['group' => 'appearance', 'type' => 'string', 'default' => '/favicon.ico', 'max' => 255],
        'default_profile_image' => ['group' => 'appearance', 'type' => 'string', 'default' => '/assets/img/default-profile.png', 'max' => 255],
        'default_content_cover_image' => ['group' => 'appearance', 'type' => 'string', 'default' => '/assets/img/covers/placeholder.svg', 'max' => 255],

        // Security Group
        'maintenance_mode' => ['group' => 'security', 'type' => 'bool', 'default' => false],
        'maintenance_whitelist_ips' => ['group' => 'security', 'type' => 'json', 'default' => ['127.0.0.1', '::1']],

        // Mail Group
        'mail_enabled' => ['group' => 'mail', 'type' => 'bool', 'default' => true],
        'mail_send_on_register' => ['group' => 'mail', 'type' => 'bool', 'default' => true],
        'email_verification_required' => ['group' => 'mail', 'type' => 'bool', 'default' => false],
        'mail_from_name' => ['group' => 'mail', 'type' => 'string', 'default' => 'NM Reader', 'max' => 120],
        'mail_from_address' => ['group' => 'mail', 'type' => 'string', 'default' => 'noreply@nmreader.com', 'max' => 150],
        'password_reset_subject' => ['group' => 'mail', 'type' => 'string', 'default' => 'Şifre Sıfırlama Talebi - {{site_name}}', 'max' => 255],
        'password_reset_body' => ['group' => 'mail', 'type' => 'string', 'default' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">Şifre Sıfırlama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} hesabınız için bir şifre sıfırlama talebi aldık. Şifrenizi yenilemek için aşağıdaki butona tıklayabilirsiniz:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">Şifremi Sıfırla</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir. Talebi siz yapmadıysanız bu e-postayı güvenle silebilirsiniz.</p></div>'],
        'email_verification_subject' => ['group' => 'mail', 'type' => 'string', 'default' => 'E-posta Adresinizi Doğrulayın - {{site_name}}', 'max' => 255],
        'email_verification_body' => ['group' => 'mail', 'type' => 'string', 'default' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">E-posta Doğrulama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} ailesine hoş geldiniz! Hesabınızı doğrulamak ve güvenliğinizi sağlamak için lütfen aşağıdaki butona tıklayın:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #e11d48; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">E-postamı Doğrula</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir.</p></div>'],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CacheService $cache,
    ) {
    }

    /**
     * Returns all settings mapped with their casted values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (self::$memoryCache !== null) {
            return self::$memoryCache;
        }

        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            // Older cache entries may contain rows that are no longer part of
            // the supported schema. Never return those entries to callers.
            $cached = array_intersect_key($cached, self::DEFINITIONS);
            self::$memoryCache = $cached;
            return $cached;
        }

        $settings = $this->defaults();
        try {
            $stmt = $this->pdo->query('SELECT `group`, `key`, `type`, `value` FROM system_settings');
            if ($stmt !== false) {
                $rows = $stmt->fetchAll();
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!is_array($row) || !isset($row['key'])) {
                            continue;
                        }
                        $key = (string) $row['key'];
                        // Unknown rows must never be exposed through the
                        // settings payload (they may contain future secrets).
                        if (!isset(self::DEFINITIONS[$key])) {
                            continue;
                        }
                        $type = (string) ($row['type'] ?? (self::DEFINITIONS[$key]['type'] ?? 'string'));
                        $raw = $row['value'] ?? null;
                        $settings[$key] = $this->castValueByType($key, $type, $raw);
                    }
                }
            }
        } catch (\Throwable) {
            // Return defaults on DB error
        }

        $this->cache->set(self::CACHE_KEY, $settings, self::CACHE_TTL);
        self::$memoryCache = $settings;
        return $settings;
    }

    /**
     * Returns only settings safe for public HTML/application bootstrap.
     * Operational and mail settings remain server-side and are available to
     * authenticated administrators through the admin configuration API.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return array_intersect_key($this->all(), array_flip(self::PUBLIC_KEYS));
    }

    /**
     * Gets a single setting value with an optional fallback default.
     */
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
        $envValue = $_ENV['ENFORCE_HTTPS'] ?? ($_SERVER['ENFORCE_HTTPS'] ?? getenv('ENFORCE_HTTPS'));
        if ($envValue !== false && $envValue !== null && $envValue !== '') {
            $normalized = strtolower(trim((string) $envValue));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }

    public function siteAddress(): string
    {
        $envValue = $_ENV['SITE_ADDRESS'] ?? ($_SERVER['SITE_ADDRESS'] ?? getenv('SITE_ADDRESS'));
        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }
        return '';
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
     * Updates site settings with strict type checking and normalization.
     *
     * @param array<string, mixed> $payload
     * @param string|null $moderatorId
     * @return array<string, mixed>
     * @throws \InvalidArgumentException If a value does not match its required type.
     */
    public function update(array $payload, ?string $moderatorId = null): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO system_settings (`group`, `key`, `type`, `value`, updated_at)
             VALUES (:grp, :key, :typ, :val, NOW())
             ON DUPLICATE KEY UPDATE `group` = VALUES(`group`), `type` = VALUES(`type`), `value` = VALUES(`value`), updated_at = NOW()'
        );

        foreach ($payload as $key => $val) {
            $key = (string) $key;
            if (!isset(self::DEFINITIONS[$key])) {
                continue;
            }

            $definition = self::DEFINITIONS[$key];
            $group = $definition['group'];
            $type = $definition['type'];

            $serialized = $this->normalizeForWrite($key, $val, $type, $definition);

            $stmt->execute([
                'grp' => $group,
                'key' => $key,
                'typ' => $type,
                'val' => $serialized,
            ]);
        }

        $this->cache->delete(self::CACHE_KEY);
        self::$memoryCache = null;
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
     * @return array<string, array{group:string,type:string,default:mixed,max?:int,allowed?:array<int,string>}>
     */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * Exposes the canonical setting definitions to install-time validators.
     * This method is pure and does not require a database connection.
     *
     * @return array<string, array{group:string,type:string,default:mixed,max?:int,allowed?:array<int,string>}>
     */
    public static function settingDefinitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * Returns default values without constructing the database-backed service.
     *
     * @return array<string, mixed>
     */
    public static function defaultValues(): array
    {
        $defaults = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * Validates and normalizes a site settings payload without writing it.
     * Unknown settings are ignored to preserve forward compatibility with
     * future clients, while known settings always use the same rules as the
     * runtime configuration service.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function validateInput(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            $key = (string) $key;
            if (!isset(self::DEFINITIONS[$key])) {
                continue;
            }

            $definition = self::DEFINITIONS[$key];
            $type = $definition['type'];
            $normalized[$key] = match ($type) {
                'int' => self::validateIntInput($key, $value),
                'bool' => self::validateBoolInput($key, $value),
                'json' => self::validateJsonInput($key, $value),
                default => self::validateStringInput($key, $value, $definition),
            };
        }

        return $normalized;
    }

    private static function validateIntInput(string $key, mixed $value): int
    {
        if (is_int($value)) return $value;
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }
        throw new \InvalidArgumentException(sprintf("Setting '%s' must be an integer.", $key));
    }

    private static function validateBoolInput(string $key, mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) && ($value === 0 || $value === 1)) return $value === 1;
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) return true;
            if (in_array($normalized, ['false', '0', 'no', 'off', ''], true)) return false;
        }
        throw new \InvalidArgumentException(sprintf("Setting '%s' must be a boolean (true/false).", $key));
    }

    private static function validateJsonInput(string $key, mixed $value): array|string
    {
        if (is_array($value)) {
            json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $value;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf("Setting '%s' must be valid JSON.", $key));
        }
        $trimmed = trim($value);
        if ($trimmed === '') return [];
        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \InvalidArgumentException(sprintf("Setting '%s' must be a JSON array or object.", $key));
        }
        return $decoded;
    }

    private static function validateStringInput(string $key, mixed $value, array $definition): string
    {
        if (!is_string($value) && !is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf("Setting '%s' must be a string.", $key));
        }
        $string = trim((string) $value);
        if (isset($definition['allowed']) && !in_array($string, $definition['allowed'], true)) {
            throw new \InvalidArgumentException(sprintf("Invalid value '%s' for setting '%s'.", $string, $key));
        }
        if (isset($definition['max']) && mb_strlen($string) > (int) $definition['max']) {
            throw new \InvalidArgumentException(sprintf("Setting '%s' exceeds its maximum length.", $key));
        }
        return $string;
    }

    private function castValueByType(string $key, string $type, mixed $raw): mixed
    {
        $definition = self::DEFINITIONS[$key] ?? ['default' => null, 'type' => $type];

        return match ($type) {
            'int' => $this->toInt($raw, $definition['default'] ?? 0),
            'bool' => $this->toBool($raw),
            'json' => $this->toJson($raw, $definition['default'] ?? []),
            default => $this->toString($raw, $definition),
        };
    }

    private function normalizeForWrite(string $key, mixed $value, string $type, array $definition): string
    {
        return match ($type) {
            'int' => $this->normalizeInt($key, $value),
            'bool' => $this->normalizeBool($key, $value),
            'json' => $this->normalizeJson($key, $value),
            default => $this->normalizeString($key, $value, $definition),
        };
    }

    private function normalizeInt(string $key, mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (string) (int) $value;
        }

        throw new \InvalidArgumentException(sprintf("Setting '%s' must be an integer.", $key));
    }

    private function normalizeBool(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            if ($value === 1) return 'true';
            if ($value === 0) return 'false';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                return 'true';
            }
            if (in_array($normalized, ['false', '0', 'no', 'off', ''], true)) {
                return 'false';
            }
        }

        throw new \InvalidArgumentException(sprintf("Setting '%s' must be a boolean (true/false).", $key));
    }

    private function normalizeJson(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \InvalidArgumentException(sprintf("Setting '%s' contains invalid JSON data.", $key));
            }
            return $encoded;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '{}';
            }

            // Validate JSON
            if (function_exists('json_validate')) {
                if (!json_validate($trimmed)) {
                    throw new \InvalidArgumentException(sprintf("Setting '%s' must be valid JSON.", $key));
                }
            } else {
                json_decode($trimmed);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException(sprintf("Setting '%s' must be valid JSON.", $key));
                }
            }

            return $trimmed;
        }

        throw new \InvalidArgumentException(sprintf("Setting '%s' must be a JSON array, object, or valid JSON string.", $key));
    }

    private function normalizeString(string $key, mixed $value, array $definition): string
    {
        if (!is_string($value) && !is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf("Setting '%s' must be a string.", $key));
        }

        $string = trim((string) $value);

        if (isset($definition['allowed']) && !in_array($string, $definition['allowed'], true)) {
            throw new \InvalidArgumentException(sprintf("Invalid value '%s' for setting '%s'. Allowed: %s", $string, $key, implode(', ', $definition['allowed'])));
        }

        if (isset($definition['max']) && mb_strlen($string) > (int) $definition['max']) {
            throw new \InvalidArgumentException(sprintf("Setting '%s' exceeds maximum allowed length of %d characters.", $key, (int) $definition['max']));
        }

        return $string;
    }

    private function toInt(mixed $raw, mixed $fallback): int
    {
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && filter_var($raw, FILTER_VALIDATE_INT) !== false) {
            return (int) $raw;
        }
        return is_int($fallback) ? $fallback : 0;
    }

    private function toString(mixed $raw, array $definition): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            $value = (string) ($definition['default'] ?? '');
        }

        if (isset($definition['allowed']) && !in_array($value, $definition['allowed'], true)) {
            return (string) ($definition['default'] ?? '');
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
