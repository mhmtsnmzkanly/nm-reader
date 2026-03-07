<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Repositories\UserRepository;
use Closure;
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
        private UserRepository|Closure $users,
        private readonly string $actionName
    ) {
        if (!$this->users instanceof UserRepository) {
            $this->users = $this->users instanceof Closure
                ? $this->users
                : Closure::fromCallable($this->users);
        }
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

        if ($this->users()->isBanned($userId)) {
            return ResponseHelper::error(403, sprintf('Your account is restricted from %s', $this->actionName));
        }

        return $handler->handle($request);
    }

    private function users(): UserRepository
    {
        if ($this->users instanceof UserRepository) {
            return $this->users;
        }

        $resolved = ($this->users)();
        if (!$resolved instanceof UserRepository) {
            throw new \RuntimeException('RestrictedActionMiddleware user resolver must return UserRepository');
        }

        $this->users = $resolved;
        return $resolved;
    }
}
