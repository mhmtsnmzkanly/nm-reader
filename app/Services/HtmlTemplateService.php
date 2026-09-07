<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads HTML templates from the public shell directory and replaces the
 * explicit %%NMR_*%% placeholders used by server-rendered entrypoints.
 *
 * The files remain HTML (and therefore contain no executable PHP), while the
 * controllers retain responsibility for access control and dynamic values.
 */
final class HtmlTemplateService
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array<string, scalar|null> $replacements
     */
    public function render(string $template, array $replacements = []): ?string
    {
        $relativePath = ltrim($template, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }
        $path = rtrim($this->basePath, '/') . '/public/' . $relativePath;
        if (!is_file($path)) {
            return null;
        }

        $html = file_get_contents($path);
        if ($html === false) {
            return null;
        }

        foreach ($replacements as $name => $value) {
            $token = '%%NMR_' . strtoupper(trim($name, "%_ ")) . '%%';
            $html = str_replace($token, (string) ($value ?? ''), $html);
        }

        return $html;
    }
}
