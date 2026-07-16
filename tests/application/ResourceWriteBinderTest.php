<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\application;

use PHPUnit\Framework\TestCase;
use tropikal\connect\n2n\application\ResourceWriteBinder;
use tropikal\connect\n2n\domain\exception\InvalidWriteException;
use tropikal\connect\n2n\tests\Support\SampleResources;

/**
 * The n2n-bind write gate: type-appropriate mappers per declared field, fail
 * closed on unknown fields, missing required fields, and type violations —
 * each with its stable wire code.
 */
final class ResourceWriteBinderTest extends TestCase
{
    private ResourceWriteBinder $binder;

    protected function setUp(): void
    {
        $this->binder = new ResourceWriteBinder;
    }

    public function test_binds_a_clean_payload(): void
    {
        $clean = $this->binder->bind(SampleResources::article(), [
            'title' => '  Saved by the bind  ',
            'online' => true,
            'categoryId' => 7,
        ], true);

        self::assertSame('Saved by the bind', $clean['title'], 'cleanString trims');
        self::assertTrue($clean['online']);
        self::assertSame(7, $clean['categoryId']);
    }

    public function test_rejects_unknown_fields(): void
    {
        try {
            $this->binder->bind(SampleResources::article(), ['title' => 'x', 'hax' => 1], true);
            self::fail('expected InvalidWriteException');
        } catch (InvalidWriteException $e) {
            self::assertSame('unknown_fields', $e->errorCode);
            self::assertSame(['hax'], $e->fields);
        }
    }

    public function test_rejects_missing_required_fields_on_create(): void
    {
        try {
            $this->binder->bind(SampleResources::article(), ['lead' => 'no title'], true);
            self::fail('expected InvalidWriteException');
        } catch (InvalidWriteException $e) {
            self::assertSame('missing_required', $e->errorCode);
            self::assertSame(['title'], $e->fields);
        }
    }

    public function test_allows_partial_payload_on_update(): void
    {
        $clean = $this->binder->bind(SampleResources::article(), ['lead' => 'only the lead'], false);

        self::assertSame(['lead' => 'only the lead'], $clean);
    }

    public function test_rejects_type_violations(): void
    {
        try {
            $this->binder->bind(SampleResources::article(), [
                'title' => 'ok',
                'online' => 'not-a-bool',
            ], true);
            self::fail('expected InvalidWriteException');
        } catch (InvalidWriteException $e) {
            self::assertSame('invalid_fields', $e->errorCode);
            self::assertContains('online', $e->fields);
        }
    }
}
