<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\NativeApcuStore;
use Amtgard\PHPUnit\AmtgardTestCase;

class NativeApcuStoreTest extends AmtgardTestCase
{
    public function testBuild_withoutCallables_usesApcuFunctionNames(): void
    {
        $store = NativeApcuStore::builder()->build();

        self::assertSame('apcu_fetch', $this->callableNamed($store, 'fetch'));
        self::assertSame('apcu_store', $this->callableNamed($store, 'store'));
        self::assertSame('apcu_delete', $this->callableNamed($store, 'delete'));
        self::assertSame('apcu_exists', $this->callableNamed($store, 'exists'));
        self::assertSame('apcu_clear_cache', $this->callableNamed($store, 'clear'));
    }

    public function testFetch_whenCallableSucceeds_returnsValue(): void
    {
        $store = $this->storeWith([
            'fetch' => fn (string $key): mixed => $key === 'schema' ? 'document' : false,
        ]);

        self::assertSame('document', $store->fetch('schema'));
        self::assertFalse($store->fetch('missing'));
    }

    public function testFetch_whenCallableFails_returnsFalse(): void
    {
        $store = $this->storeWith([
            'fetch' => fn (string $key): mixed => false,
        ]);

        self::assertFalse($store->fetch('schema'));
    }

    public function testStore_whenCallableSucceeds_returnsTrue(): void
    {
        $seen = [];
        $store = $this->storeWith([
            'store' => function (string $key, mixed $value, int $ttl) use (&$seen): bool {
                $seen = [$key, $value, $ttl];
                return true;
            },
        ]);

        self::assertTrue($store->store('schema', 'document', 12));
        self::assertSame(['schema', 'document', 12], $seen);
    }

    public function testStore_whenCallableFails_returnsFalse(): void
    {
        $store = $this->storeWith([
            'store' => fn (string $key, mixed $value, int $ttl): bool => false,
        ]);

        self::assertFalse($store->store('schema', 'document', 12));
    }

    public function testDelete_returnsCallableResult(): void
    {
        $store = $this->storeWith([
            'delete' => fn (string $key): bool => $key === 'schema',
        ]);

        self::assertTrue($store->delete('schema'));
        self::assertFalse($store->delete('other'));
    }

    public function testExists_returnsCallableResult(): void
    {
        $store = $this->storeWith([
            'exists' => fn (string $key): bool => $key === 'schema',
        ]);

        self::assertTrue($store->exists('schema'));
        self::assertFalse($store->exists('other'));
    }

    public function testClear_returnsCallableResult(): void
    {
        $succeed = false;
        $store = $this->storeWith([
            'clear' => function () use (&$succeed): bool {
                return $succeed;
            },
        ]);

        self::assertFalse($store->clear());
        $succeed = true;
        self::assertTrue($store->clear());
    }

    private function storeWith(array $callables): NativeApcuStore
    {
        $builder = NativeApcuStore::builder();
        foreach ($callables as $name => $callable) {
            $builder->$name($callable);
        }
        return $builder->build();
    }

    private function callableNamed(NativeApcuStore $store, string $name): mixed
    {
        return (new \ReflectionProperty(NativeApcuStore::class, $name))->getValue($store);
    }
}
