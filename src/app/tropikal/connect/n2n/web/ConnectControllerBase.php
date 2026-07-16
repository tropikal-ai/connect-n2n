<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use n2n\persistence\orm\EntityManager;
use n2n\web\http\controller\ControllerAdapter;
use n2n\web\http\Method;
use tropikal\connect\n2n\application\ApiResult;
use tropikal\connect\n2n\domain\exception\SignatureException;
use tropikal\connect\n2n\domain\installation\Installation;
use TropikalAI\Connect\Domain\Security\SignedRequest;

/**
 * Base for the connect controllers. The host application subclasses each
 * controller once and implements composition() — its composition root — wiring
 * the engine to its own resources, stores, and admin gate.
 */
abstract class ConnectControllerBase extends ControllerAdapter
{
    protected ConnectComposition $comp;

    abstract protected function composition(EntityManager $em): ConnectComposition;

    // n2n magic init: must be private. Dispatches to the subclass composition().
    private function _init(EntityManager $em): void
    {
        $this->comp = $this->composition($em);
    }

    /**
     * Verifies the TROPIKAL signature on the current request. Returns the
     * connected installation, or null after sending a 401.
     */
    protected function verifiedInstallation(): ?Installation
    {
        $request = $this->getRequest();
        $installation = $this->comp->installations->current();

        try {
            $this->comp->guard->verify(
                $installation,
                Method::toString($request->getMethod()),
                $request->getPath()->toRealString(true, false),
                $request->getQuery()->toArray(),
                $request->getBody(),
                $this->signingHeaders(),
            );
        } catch (SignatureException $e) {
            $this->respond(ApiResult::error('invalid_signature', $e->getMessage(), 401));

            return null;
        }

        return $installation;
    }

    protected function respond(ApiResult $result): void
    {
        $this->getResponse()->setStatus($result->status);
        $this->sendJson($result->body);
    }

    /** @return array<string, string> */
    private function signingHeaders(): array
    {
        $request = $this->getRequest();
        $headers = [];
        foreach ([
            SignedRequest::INSTALLATION_HEADER,
            SignedRequest::TIMESTAMP_HEADER,
            SignedRequest::NONCE_HEADER,
            SignedRequest::BODY_HASH_HEADER,
            SignedRequest::SIGNATURE_HEADER,
        ] as $name) {
            $value = $request->getHeader($name);
            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
