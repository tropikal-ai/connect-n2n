<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

/** GET /health — public liveness only; discloses nothing about the installation. */
abstract class HealthController extends ConnectControllerBase
{
    public function index(): void
    {
        $this->sendJson(['status' => 'ok']);
    }
}
