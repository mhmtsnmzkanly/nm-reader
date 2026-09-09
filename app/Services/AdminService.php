<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Admin\ChapterAdminService;
use App\Services\Admin\ContentAdminService;
use App\Services\Admin\TaxonomyAdminService;

/**
 * @deprecated Use the domain services under App\Services\Admin.
 *
 * This compatibility facade preserves the existing service contract while
 * content, chapter and taxonomy workflows live in separate services.
 */
final class AdminService
{
    /** @var list<object> */
    private array $delegates;

    public function __construct(
        ContentAdminService $content,
        ChapterAdminService $chapters,
        TaxonomyAdminService $taxonomy,
    ) {
        $this->delegates = [$content, $chapters, $taxonomy];
    }

    public function __call(string $name, array $arguments): mixed
    {
        foreach ($this->delegates as $delegate) {
            if (method_exists($delegate, $name)) {
                return $delegate->{$name}(...$arguments);
            }
        }

        throw new \BadMethodCallException(sprintf('Unknown admin service action: %s', $name));
    }
}
