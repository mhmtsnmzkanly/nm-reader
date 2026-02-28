<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\MetricsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for System Metrics and Analytics API endpoints.
 *
 * Provides endpoints for real-time snapshots, historical data insights,
 * and granular interest tracking (e.g., genre-based analytics).
 *
 * @package App\Controllers
 */
final class MetricsController
{
    public function __construct(private readonly MetricsService $metrics)
    {
    }

    /**
     * Retrieves a real-time snapshot of system KPIs.
     */
    public function snapshot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->metrics->snapshot());
    }

    /**
     * Retrieves historical activity insights for a given period.
     *
     * @param array $query 'days' parameter (default 7).
     */
    public function insights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        return ResponseHelper::success($this->metrics->insights($days));
    }

    /**
     * Tracks user interest trends for a specific genre.
     *
     * @param array $args Must contain 'slug'.
     */
    public function genreInterest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        $slug = (string) ($args['slug'] ?? '');

        try {
            return ResponseHelper::success($this->metrics->genreInterest($slug, $days));
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }
}
