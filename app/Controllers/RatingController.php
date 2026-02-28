<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\RatingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for Content Rating API endpoints.
 *
 * Provides functionality for users to rate series. Supports both 
 * general slug-based lookup and specific type-slug paired lookup.
 *
 * @package App\Controllers
 */
final class RatingController
{
    public function __construct(private readonly RatingService $ratings)
    {
    }

    /**
     * Submits or updates a user rating for a specific series.
     *
     * @param array $args Must contain 'slug'.
     */
    public function rate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $slug = (string) $args['slug'];
            $payload = (array) $request->getParsedBody();
            $rating = (int) ($payload['rating'] ?? 0);

            $this->ratings->rate($userId, $slug, $rating);
            return ResponseHelper::success(['rated' => true]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Submits or updates a user rating for a specific series type and slug.
     *
     * @param array $args Must contain 'type' and 'slug'.
     */
    public function rateByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $type = (string) $args['type'];
            $slug = (string) $args['slug'];
            $payload = (array) $request->getParsedBody();
            $rating = (int) ($payload['rating'] ?? 0);

            $this->ratings->rateByType($userId, $type, $slug, $rating);
            return ResponseHelper::success(['rated' => true]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            $code = $exception->getMessage() === 'Invalid content type' ? 400 : 404;
            return ResponseHelper::error($code, $exception->getMessage());
        }
    }
}
