<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\NativeRedisClient;
use Amtgard\PHPUnit\AmtgardTestCase;

class NativeRedisClientTest extends AmtgardTestCase
{
    public function testBuild_whenEnvIsMissing_doesNotConnect(): void
    {
        $client = $this->withoutEnv(['REDIS_HOST', 'REDIS_PORT'], function () {
            return NativeRedisClient::builder()
                ->redisFactory(function () {
                    throw new \RuntimeException('socket opened');
                })
                ->build();
        });

        self::assertInstanceOf(NativeRedisClient::class, $client);
        self::assertFalse((new \ReflectionProperty(NativeRedisClient::class, 'redis'))->isInitialized($client));
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
                throw new \RuntimeException('redis get failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redis get failed');
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
                throw new \RuntimeException('redis set failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redis set failed');
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

    public function testGet_whenRedisReturnsValue_returnsValue(): void
    {
        $spy = new RedisCommandSpy();
        $spy->getResult = 'document';
        $client = $this->onRedis($spy);

        self::assertSame('document', $client->get('schema'));
        self::assertSame(['schema'], $spy->gets);
        self::assertSame(0, $spy->connects);
    }

    public function testGet_whenRedisReturnsFalse_returnsFalse(): void
    {
        $spy = new RedisCommandSpy();
        $spy->getResult = false;
        $client = $this->onRedis($spy);

        self::assertFalse($client->get('missing'));
    }

    public function testGet_whenRedisThrows_propagatesException(): void
    {
        $spy = new RedisCommandSpy();
        $spy->getThrows = true;
        $client = $this->onRedis($spy);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redis get failed');
        $client->get('schema');
    }

    public function testGet_whenCallableInjected_doesNotCallRedis(): void
    {
        $spy = new RedisCommandSpy();
        $spy->getResult = 'from-redis';
        $client = NativeRedisClient::builder()
            ->redis($spy)
            ->redisFactory(fn () => throw new \RuntimeException('socket opened'))
            ->get(fn (string $key): string => 'from-callable')
            ->build();

        self::assertSame('from-callable', $client->get('schema'));
        self::assertSame([], $spy->gets);
    }

    public function testSet_whenTtlIsNull_omitsExpiry(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->onRedis($spy);

        self::assertTrue($client->set('schema', 'document', null));
        self::assertSame([['schema', 'document']], $spy->sets);
        self::assertSame([], $spy->deletes);
    }

    public function testSet_whenTtlIsPositive_usesExpireSeconds(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->onRedis($spy);

        self::assertTrue($client->set('schema', 'document', 30));
        self::assertSame([['schema', 'document', ['EX' => 30]]], $spy->sets);
    }

    public function testSet_whenTtlIsZeroOrNegative_deletesInsteadOfExpiring(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->onRedis($spy);

        self::assertTrue($client->set('schema', 'document', 0));
        self::assertTrue($client->set('other', 'document', -1));
        self::assertSame([], $spy->sets);
        self::assertSame(['schema', 'other'], $spy->deletes);
    }

    public function testSet_whenRedisReturnsFalse_returnsFalse(): void
    {
        $spy = new RedisCommandSpy();
        $spy->setResult = false;
        $client = $this->onRedis($spy);

        self::assertFalse($client->set('schema', 'document', null));
        self::assertFalse($client->set('schema', 'document', 5));
    }

    public function testSet_whenRedisThrows_propagatesException(): void
    {
        $spy = new RedisCommandSpy();
        $spy->setThrows = true;
        $client = $this->onRedis($spy);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redis set failed');
        $client->set('schema', 'document', 5);
    }

    public function testDelete_whenRedisDeletes_returnsTrue(): void
    {
        $spy = new RedisCommandSpy();
        $spy->delResult = 1;
        $client = $this->onRedis($spy);

        self::assertTrue($client->delete('schema'));
        self::assertSame(['schema'], $spy->deletes);
    }

    public function testDelete_whenRedisDeletesNothing_returnsTrue(): void
    {
        $spy = new RedisCommandSpy();
        $spy->delResult = 0;
        $client = $this->onRedis($spy);

        self::assertTrue($client->delete('missing'));
    }

    public function testDelete_whenRedisReturnsFalse_returnsFalse(): void
    {
        $spy = new RedisCommandSpy();
        $spy->delResult = false;
        $client = $this->onRedis($spy);

        self::assertFalse($client->delete('schema'));
    }

    public function testClear_whenRedisFlushes_returnsTrue(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->onRedis($spy);

        self::assertTrue($client->clear());
        self::assertSame(1, $spy->flushes);
    }

    public function testClear_whenRedisReturnsFalse_returnsFalse(): void
    {
        $spy = new RedisCommandSpy();
        $spy->flushResult = false;
        $client = $this->onRedis($spy);

        self::assertFalse($client->clear());
    }

    public function testGet_readsDefaultHostAndPortWhenConnecting(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->opening($spy);

        $this->withoutEnv(['REDIS_HOST', 'REDIS_PORT'], function () use ($client) {
            $client->get('schema');
        });

        self::assertSame('127.0.0.1', $spy->host);
        self::assertSame(6379, $spy->port);
        self::assertSame(1, $spy->connects);
    }

    public function testGet_readsEnvHostAndPortWhenConnecting(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->withoutEnv(['REDIS_HOST', 'REDIS_PORT'], function () use ($spy) {
            return $this->opening($spy);
        });

        $this->withEnv([
            'REDIS_HOST' => 'cache.internal',
            'REDIS_PORT' => '6380',
        ], function () use ($client) {
            $client->get('schema');
        });

        self::assertSame('cache.internal', $spy->host);
        self::assertSame(6380, $spy->port);
    }

    public function testGet_blankOrInvalidEnvUsesDefaultHostAndPort(): void
    {
        $blank = new RedisCommandSpy();
        $blankClient = $this->opening($blank);
        $this->withEnv([
            'REDIS_HOST' => '',
            'REDIS_PORT' => '0',
        ], function () use ($blankClient) {
            $blankClient->get('schema');
        });
        self::assertSame('127.0.0.1', $blank->host);
        self::assertSame(6379, $blank->port);

        $invalid = new RedisCommandSpy();
        $invalidClient = $this->opening($invalid);
        $this->withEnv([
            'REDIS_HOST' => ['127.0.0.1'],
            'REDIS_PORT' => 'nope',
        ], function () use ($invalidClient) {
            $invalidClient->get('schema');
        });
        self::assertSame('127.0.0.1', $invalid->host);
        self::assertSame(6379, $invalid->port);

        $numeric = new RedisCommandSpy();
        $numericClient = $this->opening($numeric);
        $this->withEnv([
            'REDIS_HOST' => 'cache.internal',
            'REDIS_PORT' => 6380,
        ], function () use ($numericClient) {
            $numericClient->get('schema');
        });
        self::assertSame('cache.internal', $numeric->host);
        self::assertSame(6379, $numeric->port);
    }

    public function testGet_whenConnectFails_throwsWithoutReading(): void
    {
        $spy = new RedisCommandSpy();
        $spy->connectReturns = false;
        $spy->getResult = 'should-not-read';
        $client = $this->opening($spy);

        try {
            $client->get('schema');
            self::fail('expected connection failure');
        } catch (\RuntimeException $error) {
            self::assertSame('Redis connection failed', $error->getMessage());
        }

        self::assertSame([], $spy->gets);
        self::assertSame(1, $spy->connects);
    }

    public function testGet_reusesOpenConnection(): void
    {
        $spy = new RedisCommandSpy();
        $client = $this->opening($spy);

        $this->withoutEnv(['REDIS_HOST', 'REDIS_PORT'], function () use ($client) {
            $client->get('first');
            $client->get('second');
        });

        self::assertSame(1, $spy->connects);
        self::assertSame(['first', 'second'], $spy->gets);
    }

    public function testNewRedis_withoutFactory_returnsDisconnectedRedis(): void
    {
        $client = NativeRedisClient::builder()->build();
        $method = new \ReflectionMethod(NativeRedisClient::class, 'newRedis');
        $method->setAccessible(true);
        $redis = $method->invoke($client);

        self::assertInstanceOf(\Redis::class, $redis);
        self::assertFalse($redis->isConnected());
    }

    private function clientWith(array $callables): NativeRedisClient
    {
        $builder = NativeRedisClient::builder()
            ->redisFactory(fn () => throw new \RuntimeException('socket opened'));
        foreach ($callables as $name => $callable) {
            $builder->$name($callable);
        }
        return $builder->build();
    }

    private function onRedis(RedisCommandSpy $spy): NativeRedisClient
    {
        return NativeRedisClient::builder()
            ->redis($spy)
            ->redisFactory(fn () => throw new \RuntimeException('socket opened'))
            ->build();
    }

    private function opening(RedisCommandSpy $spy): NativeRedisClient
    {
        return NativeRedisClient::builder()
            ->redisFactory(fn () => $spy)
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
