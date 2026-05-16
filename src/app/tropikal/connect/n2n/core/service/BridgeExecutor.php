<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use Throwable;
use tropikal\connect\n2n\core\model\ConnectN2nState;
use tropikal\connect\n2n\dto\BridgeRequest;
use tropikal\connect\n2n\dto\BridgeResponse;
use tropikal\connect\n2n\dto\EntityDescriptor;
use tropikal\connect\n2n\exception\EntityGrantDeniedException;
use tropikal\connect\n2n\exception\UnsupportedRocketOperationException;

final readonly class BridgeExecutor
{
    public function __construct(
        private EntityDiscoveryService $discovery,
        private EntityFieldPolicy $fields,
        private RocketEntitySearcher $searcher,
        private RocketEntityReader $reader,
        private RocketEntityWriter $writer,
        private RocketEntityDeleter $deleter,
        private AuditLogger $audit,
    ) {}

    public function execute(BridgeRequest $request, ConnectN2nState $state): BridgeResponse
    {
        if (! $state->isConnected() || $state->installationId === null) {
            return BridgeResponse::error('unauthorized', 'Connect installation is not connected.', 403);
        }

        $entity = $this->discovery->discover()[$request->entityKey] ?? null;
        if (! $entity) {
            return BridgeResponse::error('entity_not_found', 'Rocket entity was not found.', 404);
        }

        try {
            $this->assertAllowed($state, $entity, $request->operation);
            $response = $this->executeAllowed($request, $entity);
            $this->auditMutation($request, $state, 'success');

            return $response;
        } catch (EntityGrantDeniedException $exception) {
            return BridgeResponse::error('entity_grant_denied', $exception->getMessage(), 403);
        } catch (UnsupportedRocketOperationException $exception) {
            return BridgeResponse::error('unsupported_operation', $exception->getMessage(), 400);
        } catch (\InvalidArgumentException $exception) {
            $this->auditMutation($request, $state, 'validation_error');

            return BridgeResponse::error('validation_error', $exception->getMessage(), 422);
        } catch (Throwable) {
            $this->auditMutation($request, $state, 'rocket_error');

            return BridgeResponse::error('rocket_error', 'Rocket operation failed.', 500);
        }
    }

    private function executeAllowed(BridgeRequest $request, EntityDescriptor $entity): BridgeResponse
    {
        return match ($request->operation) {
            'rocket.entity.list' => BridgeResponse::ok([
                'records' => array_map(
                    fn (array $record): array => $this->fields->project($entity, $record),
                    $this->searcher->list($entity, $request->payload),
                ),
            ]),
            'rocket.entity.get' => $this->get($request, $entity),
            'rocket.entity.create' => $this->create($request, $entity),
            'rocket.entity.update' => $this->update($request, $entity),
            'rocket.entity.delete' => $this->delete($request, $entity),
            default => throw new UnsupportedRocketOperationException('Rocket operation is not supported.'),
        };
    }

    private function get(BridgeRequest $request, EntityDescriptor $entity): BridgeResponse
    {
        $id = $this->requiredId($request->payload);
        $record = $this->reader->get($entity, $id);
        if ($record === null) {
            return BridgeResponse::error('entity_not_found', 'Rocket entity record was not found.', 404);
        }

        return BridgeResponse::ok($this->fields->project($entity, $record));
    }

    private function create(BridgeRequest $request, EntityDescriptor $entity): BridgeResponse
    {
        $payload = $this->fields->validateWrite($entity, $request->payload, true);

        return BridgeResponse::created($this->fields->project($entity, $this->writer->create($entity, $payload)));
    }

    private function update(BridgeRequest $request, EntityDescriptor $entity): BridgeResponse
    {
        $id = $this->requiredId($request->payload);
        $payload = $request->payload;
        unset($payload['id']);
        $payload = $this->fields->validateWrite($entity, $payload, false);

        return BridgeResponse::ok($this->fields->project($entity, $this->writer->update($entity, $id, $payload)));
    }

    private function delete(BridgeRequest $request, EntityDescriptor $entity): BridgeResponse
    {
        $id = $this->requiredId($request->payload);

        return BridgeResponse::ok([
            'id' => $id,
            'deleted' => $this->deleter->delete($entity, $id),
        ]);
    }

    private function assertAllowed(ConnectN2nState $state, EntityDescriptor $entity, string $operation): void
    {
        $localOperation = $this->localOperation($operation);
        if (! $entity->supports($localOperation)) {
            throw new UnsupportedRocketOperationException('Rocket entity does not support this operation.');
        }

        $grant = match ($operation) {
            'rocket.entity.list', 'rocket.entity.get' => 'read',
            'rocket.entity.create', 'rocket.entity.update' => 'write',
            'rocket.entity.delete' => 'delete',
            default => throw new UnsupportedRocketOperationException('Rocket operation is not supported.'),
        };

        if (! $state->allows($entity->key, $grant)) {
            throw new EntityGrantDeniedException('Rocket entity grant is not enabled.');
        }
    }

    private function localOperation(string $operation): string
    {
        return match ($operation) {
            'rocket.entity.list' => 'list',
            'rocket.entity.get' => 'get',
            'rocket.entity.create' => 'create',
            'rocket.entity.update' => 'update',
            'rocket.entity.delete' => 'delete',
            default => throw new UnsupportedRocketOperationException('Rocket operation is not supported.'),
        };
    }

    private function requiredId(array $payload): string
    {
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            throw new \InvalidArgumentException('Operation requires an id.');
        }

        return $id;
    }

    private function auditMutation(BridgeRequest $request, ConnectN2nState $state, string $status): void
    {
        if (! in_array($request->operation, ['rocket.entity.create', 'rocket.entity.update', 'rocket.entity.delete'], true)) {
            return;
        }
        if ($state->installationId === null) {
            return;
        }

        $this->audit->record($state->installationId, $request->entityKey, $request->operation, $status, [
            'correlation_id' => $request->correlationId,
            'payload' => $request->payload,
        ]);
    }
}
