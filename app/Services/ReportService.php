<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Repositories\AdminConsoleRepository;
use App\Repositories\ReportRepository;
use DomainException;
use InvalidArgumentException;

/**
 * Service for Content Reporting and Moderation.
 *
 * Handles reporting of chapters, series, blogs, and comments by users,
 * and review workflows by administrative staff.
 *
 * @package App\Services
 */
final class ReportService
{
    public const ALLOWED_TARGET_TYPES = ['series', 'chapter', 'blog', 'comment'];

    public const ALLOWED_STATUSES = ['pending', 'reviewing', 'resolved', 'rejected'];

    public const CONTENT_REASONS = [
        'broken_image',
        'missing_content',
        'wrong_chapter',
        'misinformation',
        'wrong_order',
        'copyright',
        'other',
    ];

    public const SOCIAL_REASONS = [
        'spam',
        'harassment',
        'insult',
        'hate_speech',
        'misinformation',
        'copyright',
        'sexual_content',
        'illegal_content',
        'other',
    ];

    public function __construct(
        private readonly ReportRepository $reports,
        private readonly AdminConsoleRepository $adminConsole,
        private readonly ContentSecurityScanner $scanner
    ) {
    }

    /**
     * Creates a new user report with strict reason and target validation.
     *
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function createReport(string $userId, array $payload): array
    {
        $error = Validator::requireFields($payload, ['target_type', 'target_id', 'reason']);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        $targetType = strtolower(trim((string) $payload['target_type']));
        if (!in_array($targetType, self::ALLOWED_TARGET_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid target_type. Allowed: %s', implode(', ', self::ALLOWED_TARGET_TYPES)));
        }

        $targetId = trim((string) $payload['target_id']);
        if ($targetId === '' || strlen($targetId) > 32) {
            throw new InvalidArgumentException('Invalid target_id');
        }

        $reason = strtolower(trim((string) $payload['reason']));
        $allowedReasons = in_array($targetType, ['series', 'chapter'], true)
            ? self::CONTENT_REASONS
            : self::SOCIAL_REASONS;

        if (!in_array($reason, $allowedReasons, true)) {
            throw new InvalidArgumentException(sprintf('Invalid reason for %s. Allowed: %s', $targetType, implode(', ', $allowedReasons)));
        }

        $description = null;
        if (!empty($payload['description'])) {
            $rawDesc = Validator::sanitizeMultilineText((string) $payload['description']);
            $description = $this->scanner->assertSafe($rawDesc, 'report_description');
            if (mb_strlen($description) > 1000) {
                throw new InvalidArgumentException('Description must not exceed 1000 characters');
            }
        }

        if ($this->reports->exists($userId, $targetType, $targetId, $reason)) {
            throw new DomainException('You have already submitted a report for this content with the same reason.');
        }

        $reportId = $this->reports->create(
            userId: $userId,
            targetType: $targetType,
            targetId: $targetId,
            reason: $reason,
            description: $description
        );

        return [
            'id' => $reportId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'status' => 'pending',
        ];
    }

    /**
     * Lists reports for admin review with filtering and pagination.
     */
    public function listReports(int $page, int $perPage, ?string $status = null, ?string $targetType = null): array
    {
        if ($status !== null && !in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status filter');
        }

        if ($targetType !== null && !in_array($targetType, self::ALLOWED_TARGET_TYPES, true)) {
            throw new InvalidArgumentException('Invalid target_type filter');
        }

        $result = $this->reports->listForAdmin($page, $perPage, $status, $targetType);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['description', 'admin_note', 'target_title', 'comment_snippet', 'reporter_username', 'reviewer_username']);

        return [
            'items' => $items,
            'counts' => $this->reports->countByStatus(),
            'meta' => [
                'total' => (int) ($result['total'] ?? 0),
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * Retrieves a single report detail by ID.
     */
    public function getReport(int $id): ?array
    {
        $report = $this->reports->findById($id);
        if ($report === null) {
            return null;
        }

        return OutputSanitizer::sanitizeFields($report, ['description', 'admin_note', 'target_title', 'comment_body', 'reporter_username', 'reviewer_username']);
    }

    /**
     * Updates the status and admin note of a report.
     *
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function updateReport(int $id, string $status, ?string $adminNote, string $reviewedBy): array
    {
        $report = $this->reports->findById($id);
        if ($report === null) {
            throw new DomainException('Report not found');
        }

        $status = strtolower(trim($status));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid status. Allowed: %s', implode(', ', self::ALLOWED_STATUSES)));
        }

        $sanitizedNote = null;
        if ($adminNote !== null && trim($adminNote) !== '') {
            $sanitizedNote = Validator::sanitizeMultilineText($adminNote);
            if (mb_strlen($sanitizedNote) > 2000) {
                throw new InvalidArgumentException('Admin note must not exceed 2000 characters');
            }
        }

        $this->reports->updateStatus($id, $status, $sanitizedNote, $reviewedBy);

        $this->adminConsole->createModerationAction(
            moderatorId: $reviewedBy,
            targetType: 'report',
            targetId: (string) $id,
            action: 'review',
            details: sprintf('Report #%d status updated to "%s". Note: %s', $id, $status, $sanitizedNote ?? 'None')
        );

        return [
            'id' => $id,
            'status' => $status,
            'admin_note' => $sanitizedNote,
            'reviewed_by' => $reviewedBy,
        ];
    }
}
