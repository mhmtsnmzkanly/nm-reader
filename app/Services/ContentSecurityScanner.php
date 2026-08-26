<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

/**
 * Service for Deep Malicious Content Scanning and Sanitization.
 *
 * Scans user and admin submitted texts (novel chapters, comments, blogs, profiles)
 * for XSS attack vectors, dangerous protocols, executable code embeds, and phishing patterns.
 */
final class ContentSecurityScanner
{
    /**
     * Dangerous HTML and script execution tags.
     */
    private const array DANGEROUS_TAG_PATTERNS = [
        '/<script\b[^>]*>(.*?)<\/script>/is' => 'script_tag',
        '/<iframe\b[^>]*>(.*?)<\/iframe>/is' => 'iframe_tag',
        '/<object\b[^>]*>(.*?)<\/object>/is' => 'object_tag',
        '/<embed\b[^>]*>(.*?)<\/embed>/is' => 'embed_tag',
        '/<applet\b[^>]*>(.*?)<\/applet>/is' => 'applet_tag',
        '/<form\b[^>]*>(.*?)<\/form>/is' => 'form_tag',
        '/<meta\b[^>]*http-equiv/is' => 'meta_refresh',
        '/<base\b[^>]*href/is' => 'base_href_injection',
    ];

    /**
     * Dangerous inline event handlers (XSS).
     */
    private const array DANGEROUS_EVENT_HANDLERS = [
        '/\bon[a-z]{3,20}\s*=\s*["\']?[^"\'>\s]+/i' => 'event_handler_xss',
    ];

    /**
     * Dangerous URL protocols and data schemes.
     */
    private const array DANGEROUS_PROTOCOLS = [
        '/javascript\s*:/i' => 'javascript_pseudo_protocol',
        '/vbscript\s*:/i' => 'vbscript_pseudo_protocol',
        '/data\s*:\s*(?:text\/html|application\/javascript|text\/javascript)/i' => 'dangerous_data_uri',
        '/php:\/\/(?:filter|input)/i' => 'php_wrapper_stream',
        '/phar:\/\//i' => 'phar_deserialization_uri',
    ];

    /**
     * Server-side executable code and shell injection attempts.
     */
    private const array EXECUTABLE_CODE_PATTERNS = [
        '/<\?php/i' => 'php_tag_open',
        '/<\?=/i' => 'php_tag_short_echo',
        '/<%/i' => 'asp_tag_open',
        '/eval\s*\(\s*base64_decode\s*\(/i' => 'obfuscated_eval_base64',
        '/system\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i' => 'shell_system_execution',
        '/passthru\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i' => 'shell_passthru_execution',
        '/shell_exec\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i' => 'shell_exec_execution',
        '/assert\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i' => 'shell_assert_execution',
    ];

    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    /**
     * Scans the given text for security threats.
     *
     * @param string $text Content to scan.
     * @return array{is_safe: bool, threats: string[], sanitized: string}
     */
    public function scan(string $text): array
    {
        $threats = [];
        $sanitized = $text;

        // 1. Remove NULL bytes and non-printable control characters (except tab, newline, carriage return)
        $sanitized = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        if ($sanitized !== $text) {
            $threats[] = 'null_byte_injection';
        }

        // 2. Check for Executable Code
        foreach (self::EXECUTABLE_CODE_PATTERNS as $pattern => $threatKey) {
            if (preg_match($pattern, $sanitized)) {
                $threats[] = $threatKey;
            }
        }

        // 3. Check for Dangerous HTML / Script Tags
        foreach (self::DANGEROUS_TAG_PATTERNS as $pattern => $threatKey) {
            if (preg_match($pattern, $sanitized)) {
                $threats[] = $threatKey;
                $sanitized = (string) preg_replace($pattern, '', $sanitized);
            }
        }

        // 4. Check for Dangerous Protocols
        foreach (self::DANGEROUS_PROTOCOLS as $pattern => $threatKey) {
            if (preg_match($pattern, $sanitized)) {
                $threats[] = $threatKey;
                $sanitized = (string) preg_replace($pattern, 'blocked-protocol:', $sanitized);
            }
        }

        // 5. Check for Inline Event Handlers
        foreach (self::DANGEROUS_EVENT_HANDLERS as $pattern => $threatKey) {
            if (preg_match($pattern, $sanitized)) {
                $threats[] = $threatKey;
                $sanitized = (string) preg_replace($pattern, '', $sanitized);
            }
        }

        $threats = array_values(array_unique($threats));
        $isSafe = count($threats) === 0;

        if (!$isSafe && $this->logger !== null) {
            $this->logger->warning('security.malicious_content_detected', [
                'threats' => $threats,
                'snippet' => substr($text, 0, 150),
            ]);
        }

        return [
            'is_safe' => $isSafe,
            'threats' => $threats,
            'sanitized' => trim($sanitized),
        ];
    }

    /**
     * Asserts that a text is safe, throwing an InvalidArgumentException if critical threats are found.
     *
     * @param string $text
     * @param string $context Context description (e.g. 'novel_body', 'comment', 'blog')
     * @return string Sanitized and safe text
     * @throws \InvalidArgumentException
     */
    public function assertSafe(string $text, string $context = 'content'): string
    {
        $result = $this->scan($text);

        if (!$result['is_safe']) {
            $critical = array_filter($result['threats'], static fn (string $t): bool => !in_array($t, ['null_byte_injection'], true));
            if (count($critical) > 0) {
                throw new \InvalidArgumentException(
                    sprintf('İçerikte güvenlik tehdidi tespit edildi (%s): %s', $context, implode(', ', $critical))
                );
            }
        }

        return $result['sanitized'];
    }

    /**
     * Sanitizes the text by neutralizing any malicious payloads without throwing exceptions.
     */
    public function sanitize(string $text): string
    {
        return $this->scan($text)['sanitized'];
    }
}
