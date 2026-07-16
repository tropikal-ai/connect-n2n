<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\exception;

/** A signed request failed verification (bad signature, timestamp, or nonce). */
final class SignatureException extends \RuntimeException {}
