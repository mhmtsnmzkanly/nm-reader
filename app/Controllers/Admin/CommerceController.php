<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class CommerceController extends AdminController
{
    public function shopPackages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->wallets->packages($page, $perPage, false);
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function createShopPackage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::created($this->wallets->createPackage($payload, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            }
        }
    
    public function updateShopPackage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->wallets->updatePackage((int) $args['id'], $payload, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            }
        }
    
    public function grantShopPackage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $packageId = (int) ($payload['package_id'] ?? 0);
                $cashAmount = isset($payload['cash_amount']) ? (string) $payload['cash_amount'] : null;
                $reason = (string) ($payload['reason'] ?? '');
                return ResponseHelper::success($this->wallets->grantPackageToUser((string) $args['userId'], $packageId, $cashAmount, $reason, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                $code = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
                return ResponseHelper::error($code, $exception->getMessage());
            }
        }
    
    public function creditWallet(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $amount = (int) ($payload['amount'] ?? 0);
                $reason = (string) ($payload['reason'] ?? '');
                return ResponseHelper::success($this->wallets->creditCoins((string) $args['userId'], $amount, $reason, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function debitWallet(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $amount = (int) ($payload['amount'] ?? 0);
                $reason = (string) ($payload['reason'] ?? '');
                return ResponseHelper::success($this->wallets->debitCoins((string) $args['userId'], $amount, $reason, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                $code = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 402;
                return ResponseHelper::error($code, $exception->getMessage());
            }
        }
    
    public function walletTransactions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                [$page, $perPage] = $this->pagination($request);
                $result = $this->wallets->transactions((string) $args['userId'], $page, $perPage);
                return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function walletSummary(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                return ResponseHelper::success($this->wallets->wallet((string) $args['userId']));
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function updateSeriesPricing(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->wallets->updateSeriesPricing((string) $args['id'], $payload, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function updateChapterPricing(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->wallets->updateChapterPricing((string) $args['id'], $payload, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function featureProducts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->wallets->featureProducts(false));
        }
    
    public function configureAdFree(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                return ResponseHelper::success($this->wallets->configureAdFree($payload, $modId));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            }
        }
    
    public function financeTransactions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->wallets->adminFinance($page, $perPage, (string)($q['q'] ?? ''), isset($q['type']) ? (string)$q['type'] : null, (string)($q['sort'] ?? 'newest')));
        }
    
    public function refundFinanceTransaction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array)$request->getParsedBody();
                return ResponseHelper::success($this->wallets->refundTransaction((int)$args['id'], (string)($payload['reason'] ?? ''), (string)$request->getAttribute('user_id')));
            } catch (\InvalidArgumentException|\DomainException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
}
