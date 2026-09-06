<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\ReportService;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for Content Reporting endpoints.
 *
 * Handles submission of issue reports by authenticated users
 * and moderation review queue for administrators.
 *
 * @package App\Controllers
 */
final class ReportController
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * Submits a new content/community issue report.
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();

            $created = $this->reports->createReport($userId, $payload);
            return ResponseHelper::created($created);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (DomainException $e) {
            return ResponseHelper::error(409, $e->getMessage());
        }
    }

    /**
     * Lists reports for admin review with filtering and pagination.
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $query = $request->getQueryParams();
            $page = max(1, (int) ($query['page'] ?? 1));
            $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
            $status = !empty($query['status']) ? (string) $query['status'] : null;
            $targetType = !empty($query['target_type']) ? (string) $query['target_type'] : null;

            $result = $this->reports->listReports($page, $perPage, $status, $targetType);

            return ResponseHelper::paginate(
                $result['items'],
                $page,
                $perPage,
                $result['meta']['total'],
                ['counts' => $result['counts']]
            );
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    /**
     * Retrieves full detail of a specific report.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $report = $this->reports->getReport($id);
        if ($report === null) {
            return ResponseHelper::error(404, 'Report not found');
        }

        return ResponseHelper::success($report);
    }

    /**
     * Updates the review status and admin notes of a report.
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = (int) $args['id'];
            $moderatorId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();

            $status = (string) ($payload['status'] ?? '');
            $adminNote = isset($payload['admin_note']) ? (string) $payload['admin_note'] : null;

            $updated = $this->reports->updateReport($id, $status, $adminNote, $moderatorId);
            return ResponseHelper::success($updated);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }
}
