<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves transport and client information without trusting forwarded headers
 * from arbitrary internet clients.
 */
final class RequestSecurity
{
    /**
     * Returns whether the request arrived over HTTPS.
     *
     * X-Forwarded-Proto is accepted only when the direct peer is a configured
     * reverse proxy. This prevents clients from spoofing HTTPS by sending the
     * header themselves.
     *
     * @param list<string> $trustedProxies
     */
    public static function isSecure(ServerRequestInterface $request, array $trustedProxies = []): bool
    {
        if (strtolower((string) $request->getUri()->getScheme()) === 'https') {
            return true;
        }

        $remoteAddress = trim((string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        if (!self::isTrustedProxy($remoteAddress, $trustedProxies)) {
            return false;
        }

        $forwardedProto = strtolower(trim((string) explode(',', $request->getHeaderLine('X-Forwarded-Proto'))[0]));
        return $forwardedProto === 'https';
    }

    /**
     * Resolves the client IP, honoring forwarding headers only from trusted
     * proxy peers.
     *
     * @param list<string> $trustedProxies
     */
    public static function clientIp(ServerRequestInterface $request, array $trustedProxies = []): string
    {
        $remoteAddress = trim((string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '') {
            // Missing socket metadata must fail closed. Treating it as
            // loopback would accidentally match the default maintenance
            // whitelist and bypass the maintenance page.
            $remoteAddress = 'unknown';
        }

        if (!self::isTrustedProxy($remoteAddress, $trustedProxies)) {
            return $remoteAddress;
        }

        $cfIp = trim($request->getHeaderLine('CF-Connecting-IP'));
        if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP) !== false) {
            return $cfIp;
        }

        $forwardedFor = trim($request->getHeaderLine('X-Forwarded-For'));
        if ($forwardedFor !== '') {
            $candidate = trim((string) explode(',', $forwardedFor)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remoteAddress;
    }

    /**
     * @param list<string> $trustedProxies
     */
    private static function isTrustedProxy(string $address, array $trustedProxies): bool
    {
        if ($address === '' || filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $addressBytes = inet_pton($address);
        if ($addressBytes === false) {
            return false;
        }

        foreach ($trustedProxies as $configured) {
            $configured = trim((string) $configured);
            if ($configured === '') {
                continue;
            }

            if (!str_contains($configured, '/')) {
                if ($address === $configured) {
                    return true;
                }
                continue;
            }

            [$network, $prefix] = array_pad(explode('/', $configured, 2), 2, null);
            $networkBytes = is_string($network) ? inet_pton($network) : false;
            $prefixLength = is_numeric($prefix) ? (int) $prefix : -1;
            $maxPrefix = is_string($networkBytes) ? strlen($networkBytes) * 8 : -1;
            if ($networkBytes === false
                || strlen($addressBytes) !== strlen($networkBytes)
                || $prefixLength < 0
                || $prefixLength > $maxPrefix) {
                continue;
            }

            $fullBytes = intdiv($prefixLength, 8);
            $remainingBits = $prefixLength % 8;
            if ($fullBytes > 0 && substr($addressBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
                continue;
            }
            if ($remainingBits > 0) {
                $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
                if ((ord($addressBytes[$fullBytes]) & $mask) !== (ord($networkBytes[$fullBytes]) & $mask)) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }
}
