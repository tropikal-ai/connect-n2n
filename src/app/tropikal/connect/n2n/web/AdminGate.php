<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use n2n\web\http\Request;

/**
 * Guards the browser-facing connect admin surface (the Connect button). The
 * host application binds its own authentication here; QueryKeyAdminGate is a
 * simple key-based default for demos and single-admin sites.
 */
interface AdminGate
{
    public function allows(Request $request): bool;

    /** Query params to carry through admin links so the gate stays satisfied. */
    public function linkParams(Request $request): array;
}
