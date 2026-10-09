<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\ApcuCache;
use Amtgard\ActiveRecordOrm\Cache\CacheBackendFactory;
use Amtgard\ActiveRecordOrm\Cache\FilesystemCache;
use Amtgard\ActiveRecordOrm\Cache\InMemoryCache;
use Amtgard\ActiveRecordOrm\Cache\MemcachedCache;
use Amtgard\ActiveRecordOrm\Cache\NativeApcuStore;
use Amtgard\ActiveRecordOrm\Cache\NativeMemcachedClient;
use Amtgard\ActiveRecordOrm\Cache\NativeRedisClient;
use Amtgard\ActiveRecordOrm\Cache\RedisCache;
use Amtgard\PHPUnit\AmtgardTestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheBackendFactoryTest extends AmtgardTestCase
{
    public function testFromEnvironment_whenControlIsMemory_returnsInMemoryCache(): void
    {
        $cache = $this->withEnv(['CACHE_CONTROL' => 'memory'], function () {
            return $this->factory()->fromEnvironment();
        });

        self::assertInstanceOf(InMemoryCache::class, $cache);
        self::assertTrue($cache->set('schema', 'document'));
        self::assertSame('document', $cache->get('schema'));
    }

    public function testFromEnvironment_whenControlIsMemory_doesNotRequireCachePath(): void
    {
        $cache = $this->withoutEnv(['CACHE_PATH'], function () {
            return $this->withEnv(['CACHE_CONTROL' => 'memory'], function () {
                return $this->factory()->fromEnvironment();
            });
        });

        self::assertInstanceOf(InMemoryCache::class, $cache);
    }

    public function testFromEnvironment_whenControlIsFile_returnsFilesystemCacheAtCachePath(): void
    {
        $path = sys_get_temp_dir() . '/aaro-c05-factory-' . bin2hex(random_bytes(8));
        try {
            $cache = $this->withEnv([
                'CACHE_CONTROL' => 'file',
                'CACHE_PATH' => $path,
            ], function () {
                return $this->factory()->fromEnvironment();
            });

            self::assertInstanceOf(FilesystemCache::class, $cache);
            $directory = new \ReflectionProperty(FilesystemCache::class, 'directory');
            self::assertSame($path, $directory->getValue($cache));
            self::assertTrue($cache->set('amtgard_orm:ork:schema:users', 'document'));
            self::assertSame('document', $cache->get('amtgard_orm:ork:schema:users'));
            self::assertFileExists($path . '/aaro/' . hash('sha256', 'amtgard_orm:ork:schema:users'));
            self::assertFileDoesNotExist($path . '/users');
        } finally {
            $this->removeTree($path);
        }
    }

    public function testFromEnvironment_whenControlIsApcuAndProbeIsTrue_returnsApcuCache(): void
    {
        $logger = new RecordingLogger();
        $cache = $this->withoutEnv(['CACHE_PATH'], function () use ($logger) {
            return $this->withEnv(['CACHE_CONTROL' => 'apcu'], function () use ($logger) {
                return $this->factoryWithProbe(static fn (): bool => true, $logger)->fromEnvironment();
            });
        });

        self::assertInstanceOf(ApcuCache::class, $cache);
        $store = (new \ReflectionProperty(ApcuCache::class, 'store'))->getValue($cache);
        self::assertInstanceOf(NativeApcuStore::class, $store);
        self::assertSame('apcu_fetch', (new \ReflectionProperty(NativeApcuStore::class, 'fetch'))->getValue($store));
        self::assertSame('apcu_store', (new \ReflectionProperty(NativeApcuStore::class, 'store'))->getValue($store));
        self::assertSame('apcu_delete', (new \ReflectionProperty(NativeApcuStore::class, 'delete'))->getValue($store));
        self::assertSame('apcu_exists', (new \ReflectionProperty(NativeApcuStore::class, 'exists'))->getValue($store));
        self::assertSame('apcu_clear_cache', (new \ReflectionProperty(NativeApcuStore::class, 'clear'))->getValue($store));
        self::assertSame($logger, (new \ReflectionProperty(ApcuCache::class, 'logger'))->getValue($cache));
    }

    public function testFromEnvironment_whenControlIsApcuAndProbeIsFalse_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-apcu is not loaded');

        $this->withoutEnv(['CACHE_PATH'], function () {
            $this->withEnv(['CACHE_CONTROL' => 'apcu'], function () {
                $this->factoryWithProbe(static fn (): bool => false)->fromEnvironment();
            });
        });
    }

    public function testFromEnvironment_whenControlIsApcu_defaultProbeMatchesExtension(): void
    {
        $loaded = extension_loaded('apcu');
        if ($loaded) {
            $cache = $this->withEnv(['CACHE_CONTROL' => 'apcu'], function () {
                return $this->factory()->fromEnvironment();
            });
            self::assertInstanceOf(ApcuCache::class, $cache);
            return;
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-apcu is not loaded');
        $this->withEnv(['CACHE_CONTROL' => 'apcu'], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenControlIsRedisAndProbeIsTrue_returnsRedisCacheWithoutConnecting(): void
    {
        $logger = new RecordingLogger();
        $cache = $this->withoutEnv(['CACHE_PATH', 'REDIS_HOST', 'REDIS_PORT'], function () use ($logger) {
            return $this->withEnv(['CACHE_CONTROL' => 'redis'], function () use ($logger) {
                return $this->factoryWithRedisProbe(static fn (): bool => true, $logger)->fromEnvironment();
            });
        });

        self::assertInstanceOf(RedisCache::class, $cache);
        $client = (new \ReflectionProperty(RedisCache::class, 'client'))->getValue($cache);
        self::assertInstanceOf(NativeRedisClient::class, $client);
        self::assertFalse((new \ReflectionProperty(NativeRedisClient::class, 'redis'))->isInitialized($client));
        self::assertSame($logger, (new \ReflectionProperty(RedisCache::class, 'logger'))->getValue($cache));
    }

    public function testFromEnvironment_whenControlIsRedisAndProbeIsFalse_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-redis is not loaded');

        $this->withoutEnv(['CACHE_PATH', 'REDIS_HOST', 'REDIS_PORT'], function () {
            $this->withEnv(['CACHE_CONTROL' => 'redis'], function () {
                $this->factoryWithRedisProbe(static fn (): bool => false)->fromEnvironment();
            });
        });
    }

    public function testFromEnvironment_whenControlIsRedis_defaultProbeMatchesExtension(): void
    {
        $loaded = extension_loaded('redis');
        if ($loaded) {
            $cache = $this->withoutEnv(['REDIS_HOST', 'REDIS_PORT'], function () {
                return $this->withEnv(['CACHE_CONTROL' => 'redis'], function () {
                    return $this->factory()->fromEnvironment();
                });
            });
            self::assertInstanceOf(RedisCache::class, $cache);
            $client = (new \ReflectionProperty(RedisCache::class, 'client'))->getValue($cache);
            self::assertFalse((new \ReflectionProperty(NativeRedisClient::class, 'redis'))->isInitialized($client));
            return;
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-redis is not loaded');
        $this->withEnv(['CACHE_CONTROL' => 'redis'], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenControlIsMemcachedAndProbeIsTrue_returnsMemcachedCacheWithoutConnecting(): void
    {
        $logger = new RecordingLogger();
        $cache = $this->withoutEnv(['CACHE_PATH', 'MEMCACHED_HOST', 'MEMCACHED_PORT'], function () use ($logger) {
            return $this->withEnv(['CACHE_CONTROL' => 'memcached'], function () use ($logger) {
                return $this->factoryWithMemcachedProbe(static fn (): bool => true, $logger)->fromEnvironment();
            });
        });

        self::assertInstanceOf(MemcachedCache::class, $cache);
        $client = (new \ReflectionProperty(MemcachedCache::class, 'client'))->getValue($cache);
        self::assertInstanceOf(NativeMemcachedClient::class, $client);
        self::assertFalse((new \ReflectionProperty(NativeMemcachedClient::class, 'memcached'))->isInitialized($client));
        self::assertSame($logger, (new \ReflectionProperty(MemcachedCache::class, 'logger'))->getValue($cache));
    }

    public function testFromEnvironment_whenControlIsMemcachedAndProbeIsFalse_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-memcached is not loaded');

        $this->withoutEnv(['CACHE_PATH', 'MEMCACHED_HOST', 'MEMCACHED_PORT'], function () {
            $this->withEnv(['CACHE_CONTROL' => 'memcached'], function () {
                $this->factoryWithMemcachedProbe(static fn (): bool => false)->fromEnvironment();
            });
        });
    }

    public function testFromEnvironment_whenControlIsMemcached_defaultProbeMatchesExtension(): void
    {
        $loaded = extension_loaded('memcached');
        if ($loaded) {
            $cache = $this->withoutEnv(['MEMCACHED_HOST', 'MEMCACHED_PORT'], function () {
                return $this->withEnv(['CACHE_CONTROL' => 'memcached'], function () {
                    return $this->factory()->fromEnvironment();
                });
            });
            self::assertInstanceOf(MemcachedCache::class, $cache);
            $client = (new \ReflectionProperty(MemcachedCache::class, 'client'))->getValue($cache);
            self::assertFalse((new \ReflectionProperty(NativeMemcachedClient::class, 'memcached'))->isInitialized($client));
            return;
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ext-memcached is not loaded');
        $this->withEnv(['CACHE_CONTROL' => 'memcached'], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenControlIsNone_returnsNull(): void
    {
        $cache = $this->withoutEnv(['CACHE_PATH'], function () {
            return $this->withEnv(['CACHE_CONTROL' => 'none'], function () {
                return $this->factory()->fromEnvironment();
            });
        });

        self::assertNull($cache);
    }

    public function testFromEnvironment_whenCacheControlIsMissing_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_CONTROL is required');

        $this->withoutEnv(['CACHE_CONTROL'], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenCacheControlIsEmpty_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_CONTROL is required');

        $this->withEnv(['CACHE_CONTROL' => ''], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenCacheControlIsNotAString_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_CONTROL is required');

        $this->withEnv(['CACHE_CONTROL' => 1], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenFileControlHasNoCachePath_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_PATH is required');

        $this->withoutEnv(['CACHE_PATH'], function () {
            $this->withEnv(['CACHE_CONTROL' => 'file'], function () {
                $this->factory()->fromEnvironment();
            });
        });
    }

    public function testFromEnvironment_whenFileControlHasEmptyCachePath_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_PATH is required');

        $this->withEnv([
            'CACHE_CONTROL' => 'file',
            'CACHE_PATH' => '',
        ], function () {
            $this->factory()->fromEnvironment();
        });
    }

    public function testFromEnvironment_whenFileControlCachePathIsNotAString_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_PATH is required');

        $this->withEnv([
            'CACHE_CONTROL' => 'file',
            'CACHE_PATH' => ['/tmp/cache'],
        ], function () {
            $this->factory()->fromEnvironment();
        });
    }

    #[DataProvider('unsupportedControls')]
    public function testFromEnvironment_whenControlIsUnsupported_fails(string $control): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CACHE_CONTROL is not supported: ' . $control);

        $this->withEnv(['CACHE_CONTROL' => $control], function () {
            $this->factory()->fromEnvironment();
        });
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedControls(): array
    {
        return [
            'uppercase file' => ['File'],
            'uppercase none' => ['NONE'],
            'uppercase apcu' => ['APCU'],
            'unknown' => ['database'],
        ];
    }

    private function factory(): CacheBackendFactory
    {
        return CacheBackendFactory::builder()->build();
    }

    private function factoryWithProbe(callable $probe, ?LoggerInterface $logger = null): CacheBackendFactory
    {
        $builder = CacheBackendFactory::builder()->apcuAvailable($probe);
        if ($logger !== null) {
            $builder->logger($logger);
        }
        return $builder->build();
    }

    private function factoryWithRedisProbe(callable $probe, ?LoggerInterface $logger = null): CacheBackendFactory
    {
        $builder = CacheBackendFactory::builder()->redisAvailable($probe);
        if ($logger !== null) {
            $builder->logger($logger);
        }
        return $builder->build();
    }

    private function factoryWithMemcachedProbe(callable $probe, ?LoggerInterface $logger = null): CacheBackendFactory
    {
        $builder = CacheBackendFactory::builder()->memcachedAvailable($probe);
        if ($logger !== null) {
            $builder->logger($logger);
        }
        return $builder->build();
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

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $name);
        }
        rmdir($path);
    }
}
