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
        $secret = trim((string) ($appSecret ?? ''));
        if (strlen($secret) < 32) {
            throw new RuntimeException('MEDIA_SECRET must be configured with at least 32 characters.');
        }
        $this->appSecret = $secret;
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
            'sid' => $userId === null ? $this->currentSessionFingerprint() : null,
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
     * @return array{cid: string, p: int, f: string, uid: ?string, sid: ?string, exp: int}|null
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

        $chapterId = (string) $data['cid'];
        $pageOrder = (int) $data['p'];
        $filename = (string) $data['f'];
        $userId = isset($data['uid']) && is_string($data['uid']) && $data['uid'] !== '' ? $data['uid'] : null;
        $sessionFingerprint = isset($data['sid']) && is_string($data['sid']) && $data['sid'] !== '' ? $data['sid'] : null;

        if ((int) $data['exp'] < time()
            || !preg_match('/^[a-z0-9]{6}$/', $chapterId)
            || $pageOrder < 1
            || !$this->isChapterMedia($filename)
            || ($userId === null && $sessionFingerprint === null)
        ) {
            return null; // Expired
        }

        return [
            'cid' => $chapterId,
            'p'   => $pageOrder,
            'f'   => $filename,
            'uid' => $userId,
            'sid' => $sessionFingerprint,
            'exp' => (int) $data['exp'],
        ];
    }

    /**
     * Ensures a signed URL can only be used by the user or guest session it was issued to.
     *
     * @param array{uid: ?string, sid: ?string} $tokenData
     */
    public function isTokenAudienceValid(array $tokenData, ?string $currentUserId): bool
    {
        $tokenUserId = $tokenData['uid'] ?? null;
        if (is_string($tokenUserId) && $tokenUserId !== '') {
            return $currentUserId !== null && hash_equals($tokenUserId, $currentUserId);
        }

        $tokenSession = $tokenData['sid'] ?? null;
        $currentSession = $this->currentSessionFingerprint();
        return is_string($tokenSession)
            && $tokenSession !== ''
            && $currentSession !== null
            && hash_equals($tokenSession, $currentSession);
    }

    private function currentSessionFingerprint(): ?string
    {
        $sessionId = session_id();
        if ($sessionId === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionId, $this->appSecret);
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
