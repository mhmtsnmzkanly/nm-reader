<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for blocking Restricted Actions by Banned Users.
 *
 * This middleware prevents users with a 'ban' status from performing 
 * specific sensitive actions (e.g., commenting, voting, blog creation).
 *
 * It checks the ban status in real-time via the UserRepository.
 *
 * @package App\Middleware
 */
final class RestrictedActionMiddleware implements MiddlewareInterface
{
    /**
     * @param UserRepository $users
     * @param string $actionName Semantic name of the action (e.g., 'commenting').
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly string $actionName
    ) {
    }

    /**
     * Checks if the authenticated user is currently restricted.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = (string) ($request->getAttribute('user_id') ?? '');
        if ($userId === '') {
            return ResponseHelper::error(401, 'Unauthorized');
        }

        if ($this->users->isBanned($userId)) {
            return ResponseHelper::error(403, sprintf('Your account is restricted from %s', $this->actionName));
        }

        return $handler->handle($request);
    }
}

