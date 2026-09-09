<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Repositories\UserListRepository;

/**
 * Validates and orchestrates user-owned named content lists.
 */
final class UserListService
{
    public function __construct(private readonly UserListRepository $lists)
    {
    }

    public function list(string $userId): array
    {
        return OutputSanitizer::sanitizeRows($this->lists->listForUser($userId), ['name', 'description', 'visibility']);
    }

    public function get(string $userId, int $listId): ?array
    {
        $list = $this->lists->findForUser($userId, $listId);
        if ($list === null) {
            return null;
        }
        $list['items'] = OutputSanitizer::sanitizeRows($list['items'] ?? [], ['title', 'slug', 'type', 'status']);
        return OutputSanitizer::sanitizeFields($list, ['name', 'description', 'visibility']);
    }

    public function create(string $userId, array $payload): array
    {
        [$name, $description, $visibility] = $this->validatePayload($payload);
        $id = $this->lists->create($userId, $name, $description, $visibility);
        return $this->get($userId, $id) ?? ['id' => $id, 'name' => $name, 'description' => $description, 'visibility' => $visibility, 'items' => []];
    }

    public function update(string $userId, int $listId, array $payload): ?array
    {
        if ($this->lists->findForUser($userId, $listId) === null) {
            return null;
        }
        [$name, $description, $visibility] = $this->validatePayload($payload);
        $this->lists->update($userId, $listId, $name, $description, $visibility);
        return $this->get($userId, $listId);
    }

    public function delete(string $userId, int $listId): bool
    {
        return $this->lists->delete($userId, $listId);
    }

    public function addItem(string $userId, int $listId, array $payload): bool
    {
        $contentId = trim((string) ($payload['content_id'] ?? $payload['contentId'] ?? ''));
        if (!preg_match('/^[a-z0-9]{6}$/', $contentId)) {
            throw new \InvalidArgumentException('Valid content_id is required');
        }
        if ($this->lists->findForUser($userId, $listId) === null) {
            throw new \DomainException('List not found');
        }
        return $this->lists->addItem($userId, $listId, $contentId, (int) ($payload['position'] ?? 0));
    }

    public function removeItem(string $userId, int $listId, string $contentId): bool
    {
        if (!preg_match('/^[a-z0-9]{6}$/', $contentId)) {
            throw new \InvalidArgumentException('Valid content_id is required');
        }
        return $this->lists->removeItem($userId, $listId, $contentId);
    }

    private function validatePayload(array $payload): array
    {
        $name = Validator::sanitizeText((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name is required');
        }
        if (mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('name must be at most 80 characters');
        }

        $description = Validator::sanitizeMultilineText((string) ($payload['description'] ?? ''));
        if (mb_strlen($description) > 500) {
            throw new \InvalidArgumentException('description must be at most 500 characters');
        }

        $visibility = strtolower(trim((string) ($payload['visibility'] ?? 'private')));
        if (!in_array($visibility, ['private', 'public'], true)) {
            throw new \InvalidArgumentException('visibility must be private or public');
        }

        return [$name, $description === '' ? null : $description, $visibility];
    }
}
