<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use n2n\web\http\Method;
use tropikal\connect\n2n\application\ApiResult;
use tropikal\connect\n2n\domain\installation\Installation;
use tropikal\connect\n2n\domain\resource\ListQuery;

/**
 * The signed resource API a TROPIKAL job drives:
 *   GET    /resources/{slug}        list (page, per_page, search honoured)
 *   GET    /resources/{slug}/{id}   get
 *   POST   /resources/{slug}        create
 *   PUT    /resources/{slug}/{id}   update   (PATCH accepted too)
 *   DELETE /resources/{slug}/{id}   delete
 * Every call is HMAC-verified, then runs inside a transaction.
 */
abstract class ResourceController extends ConnectControllerBase
{
    public function index(?string $slug = null, ?string $id = null): void
    {
        $installation = $this->verifiedInstallation();
        if ($installation === null) {
            return;
        }
        if ($slug === null) {
            $this->respond(ApiResult::error('resource_not_found', 'Resource slug required.', 404));

            return;
        }

        $method = Method::toString($this->getRequest()->getMethod());
        $this->beginTransaction($method === 'GET');
        try {
            $result = $this->handle($installation, $method, $slug, $id);
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();
            error_log('[connect] '.$e::class.': '.$e->getMessage());
            $result = ApiResult::error('internal_error', 'Operation failed.', 500);
        }

        $this->respond($result);
    }

    private function handle(Installation $installation, string $method, string $slug, ?string $id): ApiResult
    {
        $api = $this->comp->api;
        $data = $this->decodedBody();

        return match ($method) {
            'GET' => $id === null
                ? $api->list($installation, $slug, $this->listQuery())
                : $api->get($installation, $slug, $id),
            'POST' => $api->create($installation, $slug, $data),
            'PUT', 'PATCH' => $id !== null
                ? $api->update($installation, $slug, $id, $data)
                : ApiResult::error('record_not_found', 'Record id required.', 404),
            'DELETE' => $id !== null
                ? $api->delete($installation, $slug, $id)
                : ApiResult::error('record_not_found', 'Record id required.', 404),
            default => ApiResult::error('method_not_allowed', 'Unsupported method.', 405),
        };
    }

    private function listQuery(): ListQuery
    {
        $q = $this->getRequest()->getQuery();
        $perPage = (int) ($q->get('per_page') ?? $q->get('limit') ?? 20);
        $search = trim((string) ($q->get('search') ?? ''));

        return new ListQuery(
            page: max(1, (int) ($q->get('page') ?? 1)),
            perPage: min(100, max(1, $perPage)),
            search: $search !== '' ? $search : null,
        );
    }

    /** @return array<string, mixed> */
    private function decodedBody(): array
    {
        $body = $this->getRequest()->getBody();
        if (trim($body) === '') {
            return [];
        }
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }
}
