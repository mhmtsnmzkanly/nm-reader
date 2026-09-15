<?php

declare(strict_types=1);

namespace App\Services\Queue;

/**
 * Contract for background job handlers.
 *
 * Each job type in the queue system implements this interface, isolating its
 * domain logic from the queue infrastructure (adheres to Single Responsibility
 * and Open/Closed principles).
 */
interface JobHandlerInterface
{
    /**
     * Executes the background task with the provided payload.
     *
     * @param array<string, mixed> $payload Unserialized JSON job payload.
     * @throws \Throwable If processing fails (triggers retry/failure logic in QueueService).
     */
    public function handle(array $payload): void;

    /**
     * Returns the granular permission code required to execute this specific job type,
     * or null if the job can be run by any authorized worker/administrator.
     */
    public function requiredPermission(): ?string;
}
