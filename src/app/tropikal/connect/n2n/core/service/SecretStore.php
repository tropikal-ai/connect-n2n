<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\core\model\ConnectN2nState;

interface SecretStore
{
    public function load(): ConnectN2nState;

    public function save(ConnectN2nState $state): void;

    public function delete(): void;
}
