<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Repositories\UserRepository;
use App\Services\MailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that enforces email verification if required by site settings and mail is configured.
 */
final class RequireVerifiedEmailMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MailService $mailService
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->mailService->isEmailVerificationRequired()) {
            return $handler->handle($request);
        }

        $userId = (string) ($request->getAttribute('user_id') ?? '');
        if ($userId === '') {
            return ResponseHelper::error(401, 'Unauthorized');
        }

        $user = $this->users->findById($userId);
        if ($user === null || empty($user['email_verified_at'])) {
            return ResponseHelper::error(
                403,
                'Bu işlemi gerçekleştirmek için lütfen e-posta adresinizi doğrulayın.',
                'EMAIL_VERIFICATION_REQUIRED'
            );
        }

        return $handler->handle($request);
    }
}
