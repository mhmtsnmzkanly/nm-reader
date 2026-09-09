<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class OperationsController extends AdminController
{
    public function queueJobs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->console->listQueueJobs($page, $perPage, isset($query['status']) ? (string)$query['status'] : null, (string)($query['q'] ?? ''));
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function systemHealth(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->systemHealth());
        }
    
    public function retryQueueJob(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $this->console->retryQueueJob((int)$args['id'], (string)$request->getAttribute('user_id'));
                return ResponseHelper::success();
            } catch (\DomainException $e) { return ResponseHelper::error(409, $e->getMessage()); }
        }
    
    public function cancelQueueJob(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $this->console->cancelQueueJob((int)$args['id'], (string)$request->getAttribute('user_id'));
                return ResponseHelper::success();
            } catch (\DomainException $e) { return ResponseHelper::error(409, $e->getMessage()); }
        }
    
    public function runQueueOnce(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $limit = max(1, min(100, (int) ($payload['limit'] ?? 10)));
            $jobType = isset($payload['job_type']) ? (string) $payload['job_type'] : null;
    
            return ResponseHelper::success(
                $this->console->runQueueOnce($jobType, $limit, $modId),
                ['job_type' => $jobType, 'requested_limit' => $limit, 'moderator_id' => $modId]
            );
        }
    
    public function cleanupRetention(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $days = max(1, min(3650, (int) ($payload['days'] ?? 30)));
            return ResponseHelper::success($this->console->cleanupRetention($days));
        }
    
    public function triggerBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerBackup($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
    
    public function triggerSitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerSitemap($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function triggerCacheWarmup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerCacheWarmup($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function triggerAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerAnalytics($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function triggerApiTests(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerApiTests($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function triggerOpenApi(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerOpenApi($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function triggerSeedData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->console->triggerSeedData($modId));
            } catch (\DomainException $e) {
                return ResponseHelper::error(403, $e->getMessage());
            }
        }
    
    public function systemAccessLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->console->listSystemAccessLogs($page, $perPage);
            return ResponseHelper::success($result['items'], $result['meta']);
        }
    
    public function systemErrorLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->console->listSystemErrorLogs($page, $perPage);
            return ResponseHelper::success($result['items'], $result['meta']);
        }
}
