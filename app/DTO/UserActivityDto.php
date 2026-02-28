<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for User Activity Submission.
 */
final readonly class UserActivityDto
{
    public function __construct(
        public string $userId,
        public string $tabId,
        public int $durationSeconds,
        public ?string $ipHash,
        public ?string $userAgent
    ) {
        if ($this->durationSeconds < 0) {
            throw new \InvalidArgumentException('Duration cannot be negative.');
        }
        
        if (trim($this->tabId) === '') {
            throw new \InvalidArgumentException('Tab ID cannot be empty.');
        }
    }
}
