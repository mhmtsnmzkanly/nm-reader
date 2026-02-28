<?php

declare(strict_types=1);

namespace App\DTO;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Data Transfer Object for Uploaded Files.
 */
final class UploadDto
{
    public function __construct(
        public readonly string $userId,
        public readonly UploadedFileInterface $file,
        public readonly ?string $targetSubdir = 'misc'
    ) {
    }
}
