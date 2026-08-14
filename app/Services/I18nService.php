<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;

final class I18nService
{
    private array $dictionaries = [];
    private string $basePath;
    private array $supportedLangs = ['tr', 'en'];
    private string $defaultLang = 'en';

    public function __construct(
        private readonly SiteConfigService $siteConfig,
        private readonly UserService $userService,
        string $rootPath
    ) {
        $this->basePath = $rootPath . '/storage/lang';
        $this->defaultLang = $this->siteConfig->defaultLanguage();
        // Standard supported set for now, can be made dynamic via SiteConfig later
    }

    public function resolveLocale(ServerRequestInterface $request, ?string $userId = null): string
    {
        // 1. Auth User Preference (canonical source of truth for authenticated users)
        if ($userId) {
            try {
                $prefs = $this->userService->preferences($userId);
                if (!empty($prefs['lang']) && in_array($prefs['lang'], $this->supportedLangs, true)) {
                    return $prefs['lang'];
                }
            } catch (\Throwable) {
                // Ignore and fallback
            }
        }

        // 2. Header (X-Lang) — frontend can send explicit lang header
        $xLang = strtolower($request->getHeaderLine('X-Lang'));
        if ($xLang && in_array($xLang, $this->supportedLangs, true)) {
            return $xLang;
        }

        // 3. Cookie — guest/browser preference
        $cookies = $request->getCookieParams();
        $cookieLang = $cookies['nm_reader_lang'] ?? null;
        if ($cookieLang && in_array($cookieLang, $this->supportedLangs, true)) {
            return $cookieLang;
        }

        // 4. Accept-Language Header — browser negotiation
        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        if ($acceptLanguage) {
            $negotiated = $this->parseAcceptLanguage($acceptLanguage);
            if ($negotiated) {
                return $negotiated;
            }
        }

        // 5. Site default
        return $this->defaultLang;
    }

    public function translate(string $locale, string $key, array $params = []): string
    {
        $dictionary = $this->getDictionary($locale);
        $message = $dictionary[$key] ?? null;

        if ($message === null && $locale !== $this->defaultLang) {
            $defaultDict = $this->getDictionary($this->defaultLang);
            $message = $defaultDict[$key] ?? null;
        }

        if ($message === null && $locale !== 'en' && $this->defaultLang !== 'en') {
            $enDict = $this->getDictionary('en');
            $message = $enDict[$key] ?? $key;
        }

        $message = $message ?? $key;

        foreach ($params as $k => $v) {
            $value = (string)$v;
            $message = str_replace('{' . $k . '}', $value, $message);
            $message = str_replace(':' . $k, $value, $message);
        }

        return $message;
    }

    public function getDictionary(string $locale): array
    {
        if (isset($this->dictionaries[$locale])) {
            return $this->dictionaries[$locale];
        }

        $path = $this->basePath . '/' . $locale . '.php';
        if (is_file($path)) {
            $this->dictionaries[$locale] = include $path;
        } else {
            $this->dictionaries[$locale] = [];
        }

        return $this->dictionaries[$locale];
    }

    public function getSupportedLanguages(): array
    {
        return $this->supportedLangs;
    }

    public function getDefaultLanguage(): string
    {
        return $this->defaultLang;
    }

    private function parseAcceptLanguage(string $header): ?string
    {
        $parts = explode(',', $header);
        $matches = [];
        foreach ($parts as $part) {
            $segments = explode(';', trim($part));
            $lang = strtolower(explode('-', $segments[0])[0]);
            if (in_array($lang, $this->supportedLangs, true)) {
                $q = 1.0;
                if (isset($segments[1]) && str_starts_with(trim($segments[1]), 'q=')) {
                    $q = (float) substr(trim($segments[1]), 2);
                }
                $matches[$lang] = $q;
            }
        }
        if ($matches === []) return null;
        arsort($matches);
        return (string) array_key_first($matches);
    }
}
