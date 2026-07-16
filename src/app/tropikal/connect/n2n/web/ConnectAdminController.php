<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\web;

use tropikal\connect\n2n\domain\exception\OAuthException;

/**
 * The one-click connect surface, mirroring the Filament setup flow:
 *
 *   GET /admin            — status page with the Connect button (admin-gated)
 *   GET /admin/connect    — begins OAuth: redirects to the authorization server
 *   GET /admin/callback   — completes OAuth: code exchange + registration, then
 *                           redirects back to the status page. Not admin-gated;
 *                           it is protected by the single-use hashed OAuth state.
 */
abstract class ConnectAdminController extends ConnectControllerBase
{
    public function index(): void
    {
        if (! $this->assertAdmin()) {
            return;
        }

        $installation = $this->comp->installations->current();
        $params = $this->comp->adminGate->linkParams($this->getRequest());
        $connectUrl = $this->getUrlToController('connect', $params);

        $status = $installation->isConnected()
            ? '<p class="ok">Connected — installation <code>'.htmlspecialchars((string) $installation->installationId).'</code></p>'
              .'<p>Exposed resources: <code>'.htmlspecialchars(implode(', ', $installation->allowedResources)).'</code></p>'
            : '<p class="off">Not connected.</p>';

        $label = $installation->isConnected() ? 'Reconnect' : 'Connect to TROPIKAL';

        $this->sendHtml('<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<title>TROPIKAL Connect</title><style>'
            .'body{font-family:system-ui,sans-serif;max-width:560px;margin:4rem auto;padding:0 1rem;color:#1a2b2b}'
            .'.ok{color:#0f766e}.off{color:#8a6d3b}code{background:#eef3f2;padding:.1rem .4rem;border-radius:4px}'
            .'a.btn{display:inline-block;background:#0f766e;color:#fff;padding:.6rem 1.2rem;border-radius:8px;text-decoration:none}'
            .'</style></head><body><h1>TROPIKAL Connect</h1>'.$status
            .'<p><a class="btn" href="'.htmlspecialchars((string) $connectUrl).'">'.$label.'</a></p>'
            .'</body></html>');
    }

    public function getDoConnect(): void
    {
        if (! $this->assertAdmin()) {
            return;
        }

        $this->redirect($this->comp->flow->begin());
    }

    public function getDoCallback(): void
    {
        $request = $this->getRequest();
        $query = $request->getQuery();

        try {
            $this->comp->flow->complete(
                trim((string) ($query->get('state') ?? '')),
                trim((string) ($query->get('code') ?? '')),
                (string) $request->getUrl(),
            );
        } catch (OAuthException $e) {
            $this->getResponse()->setStatus(400);
            $this->sendHtml('<!doctype html><p>Connect failed: '.htmlspecialchars($e->getMessage()).'</p>');

            return;
        }

        $this->redirect($this->getUrlToController(null, $this->comp->adminGate->linkParams($request)));
    }

    private function assertAdmin(): bool
    {
        if ($this->comp->adminGate->allows($this->getRequest())) {
            return true;
        }

        $this->getResponse()->setStatus(403);
        $this->sendJson(['error' => 'forbidden', 'message' => 'Admin access required.']);

        return false;
    }
}
