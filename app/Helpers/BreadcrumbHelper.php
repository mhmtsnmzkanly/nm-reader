<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper for generating SEO-friendly breadcrumbs.
 */
final class BreadcrumbHelper
{
    /**
     * Generates a breadcrumb array for different page types.
     *
     * @param string $langCode Current language code.
     * @param array $__t Language dictionary for translations.
     * @param callable $url URL generator function.
     * @param string $type Page type (home, content, chapter, genre, tag, blog).
     * @param array $data Context data (e.g., content title, chapter number).
     * @return array List of breadcrumb items [title, url].
     */
    public static function generate(string $langCode, array $__t, callable $url, string $type, array $data = []): array
    {
        $items = [];
        
        // Always start with Home
        $items[] = ['title' => $__t['home'] ?? 'Home', 'url' => $url('/')];

        switch ($type) {
            case 'content':
                $items[] = ['title' => ucwords(str_replace('-', ' ', (string)($data['type'] ?? ''))), 'url' => $url('/' . ($data['type'] ?? ''))];
                $items[] = ['title' => (string)($data['title'] ?? ''), 'url' => null];
                break;

            case 'chapter':
                $contentType = (string)($data['content_type'] ?? '');
                $items[] = ['title' => ucwords(str_replace('-', ' ', $contentType)), 'url' => $url('/' . $contentType)];
                $items[] = ['title' => (string)($data['content_title'] ?? ''), 'url' => $url('/' . $contentType . '/' . ($data['content_slug'] ?? ''))];
                $items[] = ['title' => ($__t['chapter'] ?? 'Chapter') . ' ' . ($data['chapter_number'] ?? ''), 'url' => null];
                break;

            case 'genre':
                $items[] = ['title' => $__t['genres'] ?? 'Genres', 'url' => '#'];
                $items[] = ['title' => (string)($data['name'] ?? ''), 'url' => null];
                break;

            case 'tag':
                $items[] = ['title' => $__t['tags'] ?? 'Tags', 'url' => '#'];
                $items[] = ['title' => (string)($data['name'] ?? ''), 'url' => null];
                break;

            case 'blog':
                $items[] = ['title' => 'Blog', 'url' => $url('/blogs')];
                if (!empty($data['title'])) {
                    $items[] = ['title' => (string)$data['title'], 'url' => null];
                }
                break;
        }

        return $items;
    }
}
