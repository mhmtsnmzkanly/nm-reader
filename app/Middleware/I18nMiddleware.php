<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\I18nService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for Global Internationalization (i18n).
 *
 * This middleware manages the platform's multi-language support. It:
 * - Resolves the current locale (URL, Session, or Browser) via I18nService.
 * - Attaches the resolved 'locale' to the request attributes.
 * - Post-processes JSON error responses to automatically provide localized messages.
 * - Sets the 'Content-Language' header in the final response.
 *
 * @package App\Middleware
 */
final class I18nMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly I18nService $i18n)
    {
    }

    /**
     * Processes locale detection and response localization.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $_SESSION['user_id'] ?? null;
        $locale = $this->i18n->resolveLocale($request, $userId ? (string)$userId : null);

        $request = $request->withAttribute('locale', $locale);

        $response = $handler->handle($request);

        // Intercept and localize JSON error messages.
        if ($response->getHeaderLine('Content-Type') === 'application/json') {
            $body = (string)$response->getBody();
            $data = json_decode($body, true);

            if (isset($data['status']) && $data['status'] === 'error' && isset($data['error']['key'])) {
                $error = $data['error'];
                $localizedMessage = $this->i18n->translate(
                    $error['key'],
                    $locale,
                    $error['params'] ?? []
                );

                // Only replace if translation was found.
                if ($localizedMessage !== $error['key']) {
                    $data['error']['message'] = $localizedMessage;
                    $newBody = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $response->getBody()->rewind();
                    $response->getBody()->write($newBody);
                }
            }
        }

        return $response->withHeader('Content-Language', $locale);
    }
}
