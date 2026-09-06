<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CriticalActionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly int $ttlSeconds = 300)
    {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = (string)$request->getAttribute('user_id', '');
        $reauthUserId = (string)($_SESSION['admin_reauthenticated_user_id'] ?? '');
        $reauthAt = (int)($_SESSION['admin_reauthenticated_at'] ?? 0);
        if ($userId === '' || $reauthUserId !== $userId || $reauthAt < time() - $this->ttlSeconds) {
            return ResponseHelper::error(428, 'Bu kritik işlem için parolanızı yeniden doğrulamanız gerekir.', 'admin.reauthentication_required');
        }
        return $handler->handle($request);
    }
}
