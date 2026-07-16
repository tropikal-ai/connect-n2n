<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

/** GET /schema — the signed capability manifest the control plane imports. */
abstract class SchemaController extends ConnectControllerBase
{
    public function index(): void
    {
        $installation = $this->verifiedInstallation();
        if ($installation === null) {
            return;
        }

        $this->respond($this->comp->api->schema($installation));
    }
}
