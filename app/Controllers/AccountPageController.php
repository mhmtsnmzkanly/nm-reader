<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\WebPageRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Authentication and public account pages. */
final class AccountPageController extends BasePageController
{
    public function __construct(
        WebPageRenderer $renderer,
        I18nService $i18n,
        SiteConfigService $siteConfig,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct($renderer, $i18n, $siteConfig);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteName = $this->siteConfig->siteName();
        return $this->render($request, $response, [], 'Login - ' . $siteName, [
            'title' => 'Giris Yap - ' . $siteName,
            'description' => 'Hesabina giris yaparak takip, yorum ve okuma ayarlarina eris.',
            'robots' => 'noindex,nofollow',
        ]);
    }

    public function profile(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $person = (string) ($args['person'] ?? '');
        $userId = (string) ($_SESSION['user_id'] ?? '');
        if ($person === '' && $userId === '') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $currentUsername = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
        if ($userId !== '' && $currentUsername === '') {
            $me = $this->userRepository->findById($userId);
            $currentUsername = (string) ($me['username'] ?? '');
        }
        $target = $person !== '' ? $person : $currentUsername;
        if ($target === '') return $response->withStatus(404);
        $profile = $this->userRepository->findPublicByPerson($target);
        if ($profile === null) return $response->withStatus(404);
        $username = (string) ($profile['username'] ?? 'User');
        $isMe = $currentUsername !== '' && strcasecmp($currentUsername, $target) === 0;
        return $this->render($request, $response, [], $username . ' - Profile', [
            'title' => $username . ' - ' . $this->siteConfig->siteName(),
            'description' => $this->truncateDescription((string) ($profile['bio'] ?? '')),
            'robots' => $isMe ? 'noindex,nofollow' : 'index,follow',
        ]);
    }
}
