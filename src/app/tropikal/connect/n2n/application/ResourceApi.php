<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\application;

use tropikal\connect\n2n\domain\exception\InvalidWriteException;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\port\AuditRecorder;
use tropikal\connect\n2n\domain\port\ResourceCatalog;
use tropikal\connect\n2n\domain\port\ResourceStore;
use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\domain\resource\Operation;
use tropikal\connect\n2n\domain\resource\ResourceSpec;
use tropikal\connect\n2n\domain\service\CapabilityFactory;
use tropikal\connect\n2n\domain\service\FieldProjection;

/**
 * The connect resource API a TROPIKAL job drives: list/get/create/update/delete
 * over the site's resources plus schema discovery. Every call is authorised
 * against the installation's grants and passes writes through the field policy
 * before touching the store. Returns HTTP-agnostic {@see ApiResult}s.
 */
final readonly class ResourceApi
{
    public function __construct(
        private ResourceCatalog $catalog,
        private ResourceStore $store,
        private FieldProjection $fields,
        private CapabilityFactory $capabilities,
        private AuditRecorder $audit,
        private ResourceWriteBinder $binder = new ResourceWriteBinder,
    ) {}

    public function list(Installation $installation, string $slug, ListQuery $query): ApiResult
    {
        [$spec, $error] = $this->authorize($installation, $slug, Operation::List);
        if ($error !== null) {
            return $error;
        }

        $page = $this->store->list($spec, $query);
        $perPage = max(1, $query->perPage);
        $total = $page['total'];

        return ApiResult::ok([
            'data' => array_map(fn (array $record): array => $this->fields->project($spec, $record), $page['records']),
            'meta' => [
                'current_page' => $query->page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function get(Installation $installation, string $slug, string $id): ApiResult
    {
        [$spec, $error] = $this->authorize($installation, $slug, Operation::Get);
        if ($error !== null) {
            return $error;
        }

        $record = $this->store->get($spec, $id);
        if ($record === null) {
            return ApiResult::error('record_not_found', 'Record not found.', 404);
        }

        return ApiResult::ok(['data' => $this->fields->project($spec, $record)]);
    }

    /** @param array<string, mixed> $data */
    public function create(Installation $installation, string $slug, array $data): ApiResult
    {
        [$spec, $error] = $this->authorize($installation, $slug, Operation::Create);
        if ($error !== null) {
            return $error;
        }

        try {
            $validated = $this->binder->bind($spec, $data, true);
        } catch (InvalidWriteException $exception) {
            return $this->writeError($exception);
        }

        $record = $this->store->create($spec, $validated);
        $this->audit->record($installation->installationId, $slug, $record['id'] ?? null, 'create', ['fields' => array_keys($validated)]);

        return ApiResult::created(['data' => $this->fields->project($spec, $record)]);
    }

    /** @param array<string, mixed> $data */
    public function update(Installation $installation, string $slug, string $id, array $data): ApiResult
    {
        [$spec, $error] = $this->authorize($installation, $slug, Operation::Update);
        if ($error !== null) {
            return $error;
        }
        if ($this->store->get($spec, $id) === null) {
            return ApiResult::error('record_not_found', 'Record not found.', 404);
        }

        try {
            $validated = $this->binder->bind($spec, $data, false);
        } catch (InvalidWriteException $exception) {
            return $this->writeError($exception);
        }

        $record = $this->store->update($spec, $id, $validated);
        $this->audit->record($installation->installationId, $slug, $id, 'update', ['fields' => array_keys($validated)]);

        return ApiResult::ok(['data' => $this->fields->project($spec, $record)]);
    }

    public function delete(Installation $installation, string $slug, string $id): ApiResult
    {
        [$spec, $error] = $this->authorize($installation, $slug, Operation::Delete);
        if ($error !== null) {
            return $error;
        }
        if ($this->store->get($spec, $id) === null) {
            return ApiResult::error('record_not_found', 'Record not found.', 404);
        }

        $deleted = $this->store->delete($spec, $id);
        $this->audit->record($installation->installationId, $slug, $id, 'delete', []);

        return ApiResult::ok(['data' => ['id' => $id, 'deleted' => $deleted]]);
    }

    public function schema(Installation $installation): ApiResult
    {
        $resources = [];
        foreach ($this->catalog->all() as $slug => $spec) {
            if (! $installation->allowsResource($slug)) {
                continue;
            }
            $permissions = $installation->permissionsFor($slug);
            if ($permissions === []) {
                continue;
            }
            $resources[] = $this->capabilities->forResource($spec, $permissions)->toArray();
        }

        return ApiResult::ok(['resources' => $resources]);
    }

    /**
     * @return array{0: ResourceSpec, 1: null}|array{0: null, 1: ApiResult}
     */
    private function authorize(Installation $installation, string $slug, Operation $operation): array
    {
        if (! $installation->isConnected()) {
            return [null, ApiResult::error('not_connected', 'Connect installation is not connected.', 403)];
        }
        $spec = $this->catalog->get($slug);
        if ($spec === null) {
            return [null, ApiResult::error('resource_not_found', 'Resource not found.', 404)];
        }
        if (! $installation->allowsResource($slug)) {
            return [null, ApiResult::error('resource_not_allowed', 'Resource is not exposed.', 403)];
        }
        if (! $installation->allows($slug, $operation->requiredPermission())) {
            return [null, ApiResult::error('permission_denied', 'Permission denied.', 403)];
        }

        return [$spec, null];
    }

    private function writeError(InvalidWriteException $exception): ApiResult
    {
        return ApiResult::error($exception->errorCode, $exception->getMessage(), 422, ['fields' => $exception->fields]);
    }
}
