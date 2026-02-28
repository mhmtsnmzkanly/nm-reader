<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Helper for HTTP Request parameter validation.
 *
 * Provides specialized logic for extracting and clamping common request 
 * parameters like pagination directly from PSR-7 request objects.
 *
 * @package App\Helpers
 */
final class RequestValidator
{
    /**
     * Extracts and validates pagination parameters from the query string.
     *
     * @param ServerRequestInterface $request
     * @param int $defaultPerPage
     * @param int $maxPerPage
     * @return array<int, int> [page, perPage]
     */
    public static function pagination(ServerRequestInterface $request, int $defaultPerPage = 20, int $maxPerPage = 50): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min($maxPerPage, (int) ($query['per_page'] ?? $defaultPerPage)));

        return [$page, $perPage];
    }
}

