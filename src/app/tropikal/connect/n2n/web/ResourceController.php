<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use n2n\bind\build\impl\Bind;
use n2n\bind\mapper\impl\Mappers;
use n2n\reflection\annotation\AnnoInit;
use n2n\web\http\annotation\AnnoDelete;
use n2n\web\http\annotation\AnnoGet;
use n2n\web\http\annotation\AnnoPath;
use n2n\web\http\annotation\AnnoPost;
use n2n\web\http\annotation\AnnoPut;
use tropikal\connect\n2n\domain\resource\ListQuery;

/**
 * The signed resource API a TROPIKAL job drives, routed natively per HTTP
 * verb via n2n path annotations:
 *
 *   GET    /resources/{slug}        list (page, per_page, search honoured)
 *   GET    /resources/{slug}/{id}   detail
 *   POST   /resources/{slug}        create
 *   PUT    /resources/{slug}/{id}   update
 *   DELETE /resources/{slug}/{id}   remove
 *
 * Every call is HMAC-verified, then runs inside a transaction (read-only for
 * GET). Payloads and query parameters pass through n2n-bind before they reach
 * the application layer.
 */
abstract class ResourceController extends ConnectControllerBase
{
    private static function _annos(AnnoInit $ai): void
    {
        $ai->m('list', new AnnoPath('slug:*'), new AnnoGet);
        $ai->m('detail', new AnnoPath('slug:*/id:*'), new AnnoGet);
        $ai->m('create', new AnnoPath('slug:*'), new AnnoPost);
        $ai->m('update', new AnnoPath('slug:*/id:*'), new AnnoPut);
        $ai->m('remove', new AnnoPath('slug:*/id:*'), new AnnoDelete);
    }

    public function list(string $slug): void
    {
        $query = $this->listQuery();
        $this->guardedApi(true, fn ($installation) => $this->comp->api->list($installation, $slug, $query));
    }

    public function detail(string $slug, string $id): void
    {
        $this->guardedApi(true, fn ($installation) => $this->comp->api->get($installation, $slug, $id));
    }

    public function create(string $slug): void
    {
        $data = $this->decodedBody();
        $this->guardedApi(false, fn ($installation) => $this->comp->api->create($installation, $slug, $data));
    }

    public function update(string $slug, string $id): void
    {
        $data = $this->decodedBody();
        $this->guardedApi(false, fn ($installation) => $this->comp->api->update($installation, $slug, $id, $data));
    }

    public function remove(string $slug, string $id): void
    {
        $this->guardedApi(false, fn ($installation) => $this->comp->api->delete($installation, $slug, $id));
    }

    /**
     * Query parameters bound through n2n-bind: garbage is rejected by the
     * mappers, out-of-range values are clamped to the API's limits.
     */
    private function listQuery(): ListQuery
    {
        $result = Bind::attrs($this->getRequest()->getQuery()->toArray())
            ->optProp('page', Mappers::int(min: null, max: null))
            ->optProp('per_page', Mappers::int(min: null, max: null))
            ->optProp('limit', Mappers::int(min: null, max: null))
            ->optProp('search', Mappers::cleanString(maxlength: 255))
            ->toArray()
            ->exec();

        $q = $result->isValid() ? $result->get() : [];
        $perPage = (int) ($q['per_page'] ?? $q['limit'] ?? 20);
        $search = trim((string) ($q['search'] ?? ''));

        return new ListQuery(
            page: max(1, (int) ($q['page'] ?? 1)),
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
