<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class SettingsController extends AdminController
{
    public function getEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->readEnv($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function saveEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $this->console->updateEnv($payload, $modId);
                return ResponseHelper::success(['saved' => true]);
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
    
    public function getSiteConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->siteConfig->all());
        }
    
    public function updateSiteConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $updated = $this->siteConfig->update($payload, $modId);
                return ResponseHelper::success($updated);
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(400, $e->getMessage());
            }
        }
    
    public function listWebhooks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->webhooks->listWebhooks());
        }
    
    public function createWebhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                return ResponseHelper::created($this->webhooks->createWebhook($payload));
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(400, $e->getMessage());
            }
        }
    
    public function updateWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $this->webhooks->updateWebhook((int) $args['id'], $payload);
                return ResponseHelper::success();
            } catch (\DomainException $e) {
                return ResponseHelper::error(404, $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(400, $e->getMessage());
            }
        }
    
    public function deleteWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $this->webhooks->deleteWebhook((int) $args['id']);
                return ResponseHelper::success(['deleted' => true]);
            } catch (\DomainException $e) {
                return ResponseHelper::error(404, $e->getMessage());
            }
        }
    
    public function testWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $result = $this->webhooks->testWebhook((int) $args['id']);
                return ResponseHelper::success($result);
            } catch (\DomainException $e) {
                return ResponseHelper::error(404, $e->getMessage());
            }
        }
}
