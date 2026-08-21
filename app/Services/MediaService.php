<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Service for Media Handling, Secure Token Generation, and Storage Abstraction.
 *
 * Provides abstraction for Public Media URLs and Time-Bound/Protected Chapter Media URLs.
 * Ensures strict path traversal prevention and CDN-ready media routing.
 *
 * @package App\Services
 */
final class MediaService
{
    private const CHAPTER_TOKEN_PREFIX = 't_';
    private const DEFAULT_CHAPTER_TTL = 10800; // 3 hours

    private readonly string $appSecret;

    public function __construct(
        private readonly string $baseUploadDir = __DIR__ . '/../../storage/media/',
        ?string $appSecret = null
    ) {
        $this->appSecret = $appSecret ?: (string) ($_ENV['APP_SECRET'] ?? ($_ENV['MEDIA_SECRET'] ?? 'nm_reader_media_secret_key_v1_auth'));
    }

    /**
     * Checks if a filename belongs to protected chapter media.
     */
    public function isChapterMedia(string $filename): bool
    {
        return str_starts_with(basename($filename), 'chapter.');
    }

    /**
     * Generates a secure, signed temporary URL for protected chapter media pages.
     */
    public function generateChapterPageUrl(string $chapterId, int $pageOrder, string $rawPath, ?string $userId = null, int $ttl = self::DEFAULT_CHAPTER_TTL): string
    {
        $filename = basename(trim($rawPath));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new InvalidArgumentException('Invalid chapter page filename');
        }

        $payload = [
            'cid' => $chapterId,
            'p'   => $pageOrder,
            'f'   => $filename,
            'uid' => $userId,
            'exp' => time() + $ttl,
        ];

        $payloadJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadBase64, $this->appSecret);

        $token = self::CHAPTER_TOKEN_PREFIX . $payloadBase64 . '.' . $signature;

        return '/media/chapter/' . $token;
    }

    /**
     * Verifies and decodes a chapter media temporary token.
     *
     * @param string $token
     * @return array{cid: string, p: int, f: string, uid: ?string, exp: int}|null
     */
    public function verifyChapterToken(string $token): ?array
    {
        if (!str_starts_with($token, self::CHAPTER_TOKEN_PREFIX)) {
            return null;
        }

        $rawToken = substr($token, strlen(self::CHAPTER_TOKEN_PREFIX));
        $parts = explode('.', $rawToken);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadBase64, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payloadBase64, $this->appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $remainder = strlen($payloadBase64) % 4;
        if ($remainder) {
            $payloadBase64 .= str_repeat('=', 4 - $remainder);
        }
        $json = base64_decode(strtr($payloadBase64, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        if (!isset($data['cid'], $data['p'], $data['f'], $data['exp']) || !is_numeric($data['exp'])) {
            return null;
        }

        if ((int) $data['exp'] < time()) {
            return null; // Expired
        }

        return [
            'cid' => (string) $data['cid'],
            'p'   => (int) $data['p'],
            'f'   => (string) $data['f'],
            'uid' => isset($data['uid']) && is_string($data['uid']) ? $data['uid'] : null,
            'exp' => (int) $data['exp'],
        ];
    }

    /**
     * Formats a public media URL from a filename or path.
     */
    public function getPublicMediaUrl(string $pathOrFilename): string
    {
        $filename = basename(trim($pathOrFilename));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return '/assets/img/covers/placeholder.svg';
        }

        return '/media/public/' . $filename;
    }

    /**
     * Safely resolves a local storage file path with strict path-traversal prevention.
     */
    public function resolveFile(string $filename): ?string
    {
        $clean = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $clean)) {
            return null;
        }

        $baseDir = realpath($this->baseUploadDir);
        if ($baseDir === false) {
            return null;
        }

        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $clean;
        $realFile = realpath($fullPath);

        if ($realFile === false || !is_file($realFile)) {
            return null;
        }

        if (!str_starts_with($realFile, $baseDir . DIRECTORY_SEPARATOR)) {
            return null; // Traversal detected
        }

        return $realFile;
    }
}
