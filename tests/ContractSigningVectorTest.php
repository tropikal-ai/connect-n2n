<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\tests\Support\InMemoryNonceStore;
use TropikalAI\Connect\Application\SignedRequestVerifier;
use TropikalAI\Connect\Domain\Security\SignedRequest;

/**
 * Byte-for-byte cross-language interop guard. The shared contract fixture
 * (shared/tropikal-connect-contract/fixtures/v1/signed-request.vector.json)
 * pins the canonical string + HMAC that the ops backend (Python) and every
 * adapter must reproduce. If our signing canonicalization ever drifts, this
 * fails — even if our own round-trip still passes.
 */
final class ContractSigningVectorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function vector(): array
    {
        $vector = json_decode((string) file_get_contents(__DIR__.'/fixtures/signed-request.vector.json'), true);
        if (! is_array($vector)) {
            self::fail('vector fixture is not valid JSON');
        }

        return $vector;
    }

    public function test_body_hash_matches_the_contract_vector(): void
    {
        $v = $this->vector();
        self::assertSame($v['body_hash'], SignedRequest::bodyHash((string) $v['body']));
    }

    public function test_signature_matches_the_contract_vector_byte_for_byte(): void
    {
        $v = $this->vector();

        $signature = SignedRequest::sign(
            (string) $v['secret'],
            (string) $v['installation_id'],
            (string) $v['method'],
            (string) $v['path'],
            $v['query'] === '' ? null : (string) $v['query'],
            (int) $v['timestamp'],
            (string) $v['nonce'],
            (string) $v['body_hash'],
        );

        self::assertSame($v['signature'], $signature, 'canonicalization has drifted from the contract');
    }

    public function test_verifier_accepts_the_contract_signed_request(): void
    {
        $v = $this->vector();
        $headers = [
            SignedRequest::INSTALLATION_HEADER => $v['installation_id'],
            SignedRequest::TIMESTAMP_HEADER => (string) $v['timestamp'],
            SignedRequest::NONCE_HEADER => $v['nonce'],
            SignedRequest::BODY_HASH_HEADER => $v['body_hash'],
            SignedRequest::SIGNATURE_HEADER => $v['signature'],
        ];

        // freeze "now" to the vector timestamp so the tolerance window passes
        (new SignedRequestVerifier(new InMemoryNonceStore))->verify(
            (string) $v['secret'],
            (string) $v['installation_id'],
            (string) $v['method'],
            (string) $v['path'],
            $v['query'] === '' ? null : (string) $v['query'],
            (string) $v['body'],
            $headers,
            (int) $v['timestamp'],
        );

        $this->expectNotToPerformAssertions();
    }
}
