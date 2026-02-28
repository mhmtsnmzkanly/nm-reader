<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\UserActivityDto;
use App\Repositories\UserActivityRepository;

/**
 * Service orchestrating user activity and duration tracking.
 */
class UserActivityService
{
    private UserActivityRepository $repository;

    public function __construct(UserActivityRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Records or updates a user's activity session duration.
     * Enforces boundaries and orchestrates repository calls.
     *
     * @param UserActivityDto $dto Data Transfer Object containing activity info.
     * @return void
     */
    public function logActivity(UserActivityDto $dto): void
    {
        // Enforce maximum heartbeat duration (e.g., 5 mins) to prevent anomalous data
        // from skewing reputation scores if the client hangs or bugs out.
        $safeDuration = max(0, min(300, $dto->durationSeconds));

        $this->repository->upsertActivity(
            $dto->userId,
            $dto->tabId,
            $safeDuration,
            $dto->ipHash,
            $dto->userAgent
        );
    }
}
