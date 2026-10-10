<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\NativeMemcachedClient;
use Amtgard\PHPUnit\AmtgardTestCase;

class NativeMemcachedClientTest extends AmtgardTestCase
{
    public function testBuild_whenEnvIsMissing_doesNotConnect(): void
    {
        $client = $this->withoutEnv(['MEMCACHED_HOST', 'MEMCACHED_PORT'], function () {
            return NativeMemcachedClient::builder()
                ->memcachedFactory(function () {
                    throw new \RuntimeException('socket opened');
                })
                ->build();
        });

        self::assertInstanceOf(NativeMemcachedClient::class, $client);
        self::assertFalse((new \ReflectionProperty(NativeMemcachedClient::class, 'memcached'))->isInitialized($client));
    }

    public function testGet_whenCallableSucceeds_returnsValue(): void
    {
        $client = $this->clientWith([
            'get' => fn (string $key): mixed => $key === 'schema' ? 'document' : false,
        ]);

        self::assertSame('document', $client->get('schema'));
        self::assertFalse($client->get('missing'));
    }

    public function testGet_whenCallableThrows_propagatesException(): void
    {
        $client = $this->clientWith([
            'get' => function (string $key): mixed {
                throw new \RuntimeException('memcached get failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('memcached get failed');
        $client->get('schema');
    }

    public function testSet_whenCallableSucceeds_returnsTrue(): void
    {
        $seen = [];
        $client = $this->clientWith([
            'set' => function (string $key, string $value, ?int $ttl) use (&$seen): bool {
                $seen[] = [$key, $value, $ttl];
                return true;
            },
        ]);

        self::assertTrue($client->set('schema', 'document', null));
        self::assertTrue($client->set('schema', 'document', 12));
        self::assertSame([
            ['schema', 'document', null],
            ['schema', 'document', 12],
        ], $seen);
    }

    public function testSet_whenCallableReturnsFalse_returnsFalse(): void
    {
        $client = $this->clientWith([
            'set' => fn (string $key, string $value, ?int $ttl): bool => false,
        ]);

        self::assertFalse($client->set('schema', 'document', 12));
    }

    public function testSet_whenCallableThrows_propagatesException(): void
    {
        $client = $this->clientWith([
            'set' => function (string $key, string $value, ?int $ttl): bool {
                throw new \RuntimeException('memcached set failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('memcached set failed');
        $client->set('schema', 'document', 12);
    }

    public function testDelete_whenCallableReturns_returnsThatResult(): void
    {
        $client = $this->clientWith([
            'delete' => fn (string $key): bool => $key === 'schema',
        ]);

        self::assertTrue($client->delete('schema'));
        self::assertFalse($client->delete('other'));
    }

    public function testClear_whenCallableReturns_returnsThatResult(): void
    {
        $succeed = false;
        $client = $this->clientWith([
            'clear' => function () use (&$succeed): bool {
                return $succeed;
            },
        ]);

        self::assertFalse($client->clear());
        $succeed = true;
        self::assertTrue($client->clear());
    }

    public function testGet_whenMemcachedReturnsValue_returnsValue(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->getResult = 'document';
        $client = $this->onMemcached($spy);

        self::assertSame('document', $client->get('schema'));
        self::assertSame(['schema'], $spy->gets);
        self::assertSame(0, $spy->servers);
    }

    public function testGet_whenMemcachedReturnsFalse_returnsFalse(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->getResult = false;
        $client = $this->onMemcached($spy);

        self::assertFalse($client->get('missing'));
    }

    public function testGet_whenMemcachedThrows_propagatesException(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->getThrows = true;
        $client = $this->onMemcached($spy);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('memcached get failed');
        $client->get('schema');
    }

    public function testGet_whenCallableInjected_doesNotCallMemcached(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->getResult = 'from-memcached';
        $client = NativeMemcachedClient::builder()
            ->memcached($spy)
            ->memcachedFactory(fn () => throw new \RuntimeException('socket opened'))
            ->get(fn (string $key): string => 'from-callable')
            ->build();

        self::assertSame('from-callable', $client->get('schema'));
        self::assertSame([], $spy->gets);
    }

    public function testSet_whenTtlIsNull_usesNeverExpire(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->onMemcached($spy);

        self::assertTrue($client->set('schema', 'document', null));
        self::assertSame([['schema', 'document', 0]], $spy->sets);
        self::assertSame([], $spy->deletes);
    }

    public function testSet_whenTtlIsPositive_passesThatExpiration(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->onMemcached($spy);

        self::assertTrue($client->set('schema', 'document', 30));
        self::assertSame([['schema', 'document', 30]], $spy->sets);
    }

    public function testSet_whenTtlIsThirtyDays_staysRelative(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->onMemcached($spy);

        self::assertTrue($client->set('schema', 'document', 2_592_000));
        self::assertSame([['schema', 'document', 2_592_000]], $spy->sets);
    }

    public function testSet_whenTtlExceedsThirtyDays_usesUnixTimestamp(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->onMemcached($spy);
        $before = time();

        self::assertTrue($client->set('schema', 'document', 2_592_001));
        $after = time();

        self::assertCount(1, $spy->sets);
        self::assertSame('schema', $spy->sets[0][0]);
        self::assertSame('document', $spy->sets[0][1]);
        self::assertGreaterThanOrEqual($before + 2_592_001, $spy->sets[0][2]);
        self::assertLessThanOrEqual($after + 2_592_001, $spy->sets[0][2]);
        self::assertSame([], $spy->deletes);
    }

    public function testSet_whenTtlIsZeroOrNegative_deletesInsteadOfExpiring(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->onMemcached($spy);

        self::assertTrue($client->set('schema', 'document', 0));
        self::assertTrue($client->set('other', 'document', -1));
        self::assertSame([], $spy->sets);
        self::assertSame(['schema', 'other'], $spy->deletes);
    }

    public function testSet_whenMemcachedReturnsFalse_returnsFalse(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->setResult = false;
        $client = $this->onMemcached($spy);

        self::assertFalse($client->set('schema', 'document', null));
        self::assertFalse($client->set('schema', 'document', 5));
    }

    public function testSet_whenMemcachedThrows_propagatesException(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->setThrows = true;
        $client = $this->onMemcached($spy);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('memcached set failed');
        $client->set('schema', 'document', 5);
    }

    public function testDelete_whenMemcachedDeletes_returnsTrue(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->deleteResult = true;
        $spy->resultCode = 1;
        $client = $this->onMemcached($spy);

        self::assertTrue($client->delete('schema'));
        self::assertSame(['schema'], $spy->deletes);
        self::assertSame(0, $spy->resultCodeReads);
    }

    public function testDelete_whenKeyIsMissing_returnsTrue(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->deleteResult = false;
        $spy->resultCode = 16;
        $client = $this->onMemcached($spy);

        self::assertTrue($client->delete('missing'));
        self::assertSame(1, $spy->resultCodeReads);
    }

    public function testDelete_whenMemcachedReturnsFalse_returnsFalse(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->deleteResult = false;
        $spy->resultCode = 1;
        $client = $this->onMemcached($spy);

        self::assertFalse($client->delete('schema'));
        self::assertSame(1, $spy->resultCodeReads);
    }

    public function testClear_whenMemcachedFlushes_returnsTrue(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->onMemcached($spy);

        self::assertTrue($client->clear());
        self::assertSame(1, $spy->flushes);
    }

    public function testClear_whenMemcachedReturnsFalse_returnsFalse(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->flushResult = false;
        $client = $this->onMemcached($spy);

        self::assertFalse($client->clear());
    }

    public function testGet_readsDefaultHostAndPortWhenConnecting(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->opening($spy);

        $this->withoutEnv(['MEMCACHED_HOST', 'MEMCACHED_PORT'], function () use ($client) {
            $client->get('schema');
        });

        self::assertSame('127.0.0.1', $spy->host);
        self::assertSame(11211, $spy->port);
        self::assertSame(1, $spy->servers);
    }

    public function testGet_readsEnvHostAndPortWhenConnecting(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->withoutEnv(['MEMCACHED_HOST', 'MEMCACHED_PORT'], function () use ($spy) {
            return $this->opening($spy);
        });

        $this->withEnv([
            'MEMCACHED_HOST' => 'cache.internal',
            'MEMCACHED_PORT' => '11212',
        ], function () use ($client) {
            $client->get('schema');
        });

        self::assertSame('cache.internal', $spy->host);
        self::assertSame(11212, $spy->port);
    }

    public function testGet_blankOrInvalidEnvUsesDefaultHostAndPort(): void
    {
        $blank = new MemcachedCommandSpy();
        $blankClient = $this->opening($blank);
        $this->withEnv([
            'MEMCACHED_HOST' => '',
            'MEMCACHED_PORT' => '0',
        ], function () use ($blankClient) {
            $blankClient->get('schema');
        });
        self::assertSame('127.0.0.1', $blank->host);
        self::assertSame(11211, $blank->port);

        $invalid = new MemcachedCommandSpy();
        $invalidClient = $this->opening($invalid);
        $this->withEnv([
            'MEMCACHED_HOST' => ['127.0.0.1'],
            'MEMCACHED_PORT' => 'nope',
        ], function () use ($invalidClient) {
            $invalidClient->get('schema');
        });
        self::assertSame('127.0.0.1', $invalid->host);
        self::assertSame(11211, $invalid->port);

        $numeric = new MemcachedCommandSpy();
        $numericClient = $this->opening($numeric);
        $this->withEnv([
            'MEMCACHED_HOST' => 'cache.internal',
            'MEMCACHED_PORT' => 11212,
        ], function () use ($numericClient) {
            $numericClient->get('schema');
        });
        self::assertSame('cache.internal', $numeric->host);
        self::assertSame(11211, $numeric->port);
    }

    public function testGet_whenAddServerFails_throwsWithoutReading(): void
    {
        $spy = new MemcachedCommandSpy();
        $spy->addServerReturns = false;
        $spy->getResult = 'should-not-read';
        $client = $this->opening($spy);

        try {
            $client->get('schema');
            self::fail('expected connection failure');
        } catch (\RuntimeException $error) {
            self::assertSame('Memcached connection failed', $error->getMessage());
        }

        self::assertSame([], $spy->gets);
        self::assertSame(1, $spy->servers);
    }

    public function testGet_reusesOpenConnection(): void
    {
        $spy = new MemcachedCommandSpy();
        $client = $this->opening($spy);

        $this->withoutEnv(['MEMCACHED_HOST', 'MEMCACHED_PORT'], function () use ($client) {
            $client->get('first');
            $client->get('second');
        });

        self::assertSame(1, $spy->servers);
        self::assertSame(['first', 'second'], $spy->gets);
    }

    private function clientWith(array $callables): NativeMemcachedClient
    {
        $builder = NativeMemcachedClient::builder()
            ->memcachedFactory(fn () => throw new \RuntimeException('socket opened'));
        foreach ($callables as $name => $callable) {
            $builder->$name($callable);
        }
        return $builder->build();
    }

    private function onMemcached(MemcachedCommandSpy $spy): NativeMemcachedClient
    {
        return NativeMemcachedClient::builder()
            ->memcached($spy)
            ->memcachedFactory(fn () => throw new \RuntimeException('socket opened'))
            ->build();
    }

    private function opening(MemcachedCommandSpy $spy): NativeMemcachedClient
    {
        return NativeMemcachedClient::builder()
            ->memcachedFactory(fn () => $spy)
            ->build();
    }

    private function withEnv(array $values, callable $callback): mixed
    {
        $previous = [];
        $missing = [];
        foreach ($values as $name => $value) {
            if (array_key_exists($name, $_ENV)) {
                $previous[$name] = $_ENV[$name];
            } else {
                $missing[] = $name;
            }
            $_ENV[$name] = $value;
        }
        try {
            return $callback();
        } finally {
            foreach ($previous as $name => $value) {
                $_ENV[$name] = $value;
            }
            foreach ($missing as $name) {
                unset($_ENV[$name]);
            }
        }
    }

    private function withoutEnv(array $names, callable $callback): mixed
    {
        $previous = [];
        foreach ($names as $name) {
            if (array_key_exists($name, $_ENV)) {
                $previous[$name] = $_ENV[$name];
                unset($_ENV[$name]);
            }
        }
        try {
            return $callback();
        } finally {
            foreach ($previous as $name => $value) {
                $_ENV[$name] = $value;
            }
        }
    }
}
