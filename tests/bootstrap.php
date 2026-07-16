<?php

/**
 * Test bootstrap: composer autoloading plus a booted n2n application context
 * (the same pattern n2n's own packages use), so integration tests can drive
 * real routed HTTP requests through {@link \n2n\test\TestEnv}.
 */

declare(strict_types=1);

use n2n\core\cache\impl\N2nCaches;
use n2n\core\N2N;
use n2n\core\TypeLoader;

ini_set('display_errors', '1');
error_reporting(E_ALL);

define('N2N_STAGE', 'test');

require __DIR__.'/../vendor/autoload.php';

TypeLoader::register(
    true,
    require __DIR__.'/../vendor/composer/autoload_psr4.php',
    require __DIR__.'/../vendor/composer/autoload_classmap.php',
);

N2N::initialize(
    __DIR__.'/testenv/pub',
    __DIR__.'/testenv/var',
    n2nCache: N2nCaches::ephemeral(),
    enableExceptionHandler: false,
);
