<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\core\service\PublicPayloadGuard;
use tropikal\connect\n2n\dto\EntityDescriptor;
use tropikal\connect\n2n\dto\FieldDescriptor;

final class BoundaryAndPayloadTest extends TestCase
{
    public function test_public_payload_guard_rejects_secret_shaped_keys_recursively(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PublicPayloadGuard::assertPublicPayload([
            'entity_key' => 'page',
            'nested' => ['refresh_token' => 'secret'],
        ]);
    }

    public function test_entity_descriptor_rejects_secret_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EntityDescriptor('page', 'Pages', true, true, false, [
            'api_key' => new FieldDescriptor('api_key', 'API Key'),
        ], ['list']);
    }

    public function test_package_does_not_contain_forbidden_public_terms(): void
    {
        $root = dirname(__DIR__);
        $terms = [
            'bridge'.'-'.'filament',
            'manual'.' '.'token',
            'copy'.' '.'token',
            'hmac'.' '.'secret',
            'api'.'\.'.'tropikal'.'\.'.'dev',
            'ops'.'\.'.'tropikal'.'\.'.'ai',
            'id'.'\.'.'tropikal'.'\.'.'ai',
            'website'.'\.'.'tropikal'.'\.'.'ai',
            'tp'.'kl',
        ];
        $forbidden = '/'.implode('|', $terms).'/i';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || str_contains($file->getPathname(), '/vendor/')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression($forbidden, (string) file_get_contents($file->getPathname()), $file->getPathname());
        }
    }
}
