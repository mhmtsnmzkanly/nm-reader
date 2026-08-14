<?php

declare(strict_types=1);

namespace App\Helpers;

use Slim\Psr7\Response;

/**
 * Helper for Standardized API Responses.
 *
 * Ensures all API communication follows a consistent JSON structure.
 * Standard format: { "status": "...", "data": ..., "meta": ..., "error": ... }
 *
 * @package App\Helpers
 */
final class ResponseHelper
{
    /**
     * Returns a standard 200 OK success response.
     *
     * @param array $data Main payload.
     * @param array $meta Additional metadata (pagination, etc.).
     * @return Response
     */
    public static function success(array $data = [], array $meta = []): Response
    {
        return self::json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
            'error' => null,
        ], 200);
    }

    /**
     * Returns a 200 OK paginated success response with standardized meta.pagination envelope.
     */
    public static function paginate(array $items, int $page, int $perPage, ?int $total = null, array $extraMeta = []): Response
    {
        $pagination = [
            'type' => 'offset',
            'page' => $page,
            'per_page' => $perPage,
        ];
        if ($total !== null) {
            $pagination['total'] = $total;
            $pagination['total_pages'] = (int) ceil($total / max(1, $perPage));
        }

        $meta = array_merge(['pagination' => $pagination], $extraMeta);
        $meta['page'] = $page;
        $meta['per_page'] = $perPage;
        if ($total !== null) {
            $meta['total'] = $total;
            $meta['total_pages'] = $pagination['total_pages'];
        }

        return self::json([
            'status' => 'success',
            'data' => $items,
            'meta' => $meta,
            'error' => null,
        ], 200);
    }

    /**
     * Returns a 200 OK cursor-paginated success response with standardized meta.pagination envelope.
     */
    public static function cursorPaginate(array $items, int $perPage, ?string $nextCursor = null, array $extraMeta = []): Response
    {
        $pagination = [
            'type' => 'cursor',
            'per_page' => $perPage,
            'next_cursor' => $nextCursor,
            'has_more' => $nextCursor !== null && $nextCursor !== '',
        ];

        $meta = array_merge(['pagination' => $pagination], $extraMeta);
        if ($nextCursor !== null) {
            $meta['next_cursor'] = $nextCursor;
        }

        return self::json([
            'status' => 'success',
            'data' => $items,
            'meta' => $meta,
            'error' => null,
        ], 200);
    }

    /**
     * Returns a 201 Created success response.
     */
    public static function created(array $data = [], array $meta = []): Response
    {
        return self::json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
            'error' => null,
        ], 201);
    }

    /**
     * Returns a structured error response.
     *
     * @param int $code HTTP status code.
     * @param string $message Human-readable error message.
     * @param string|null $key Machine-readable error identifier (for frontend i18n).
     * @param array $params Optional parameters for i18n placeholders.
     * @return Response
     */
    public static function error(int $code, string $message, ?string $key = null, array $params = []): Response
    {
        $errorKey = $key ?? self::defaultErrorKey($code);
        return self::json([
            'status' => 'error',
            'data' => null,
            'meta' => [],
            'error' => [
                'code' => $code,
                'key' => $errorKey,
                'message' => $message,
                'params' => $params,
            ],
        ], $code);
    }

    /**
     * Low-level helper to write JSON to a Slim Response.
     */
    public static function json(array $payload, int $statusCode): Response
    {
        $response = new Response($statusCode);
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Maps common HTTP status codes to uppercase error keys.
     */
    private static function defaultErrorKey(int $code): string
    {
        return match ($code) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            402 => 'PAYMENT_REQUIRED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            419 => 'CSRF_INVALID',
            429 => 'RATE_LIMITED',
            default => 'INTERNAL_ERROR',
        };
    }
}
