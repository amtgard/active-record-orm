<?php

namespace Tests\Unit\Cache;

use Amtgard\ActiveRecordOrm\Cache\CacheBackendFactory;
use Amtgard\PHPUnit\AmtgardTestCase;

class CacheBackendFactoryLoggingTest extends AmtgardTestCase
{
    public function testLogsBackendSelected(): void
    {
        $logger = new RecordingLogger();
        $factory = CacheBackendFactory::builder()
            ->logger($logger)
            ->apcuAvailable(static fn (): bool => true)
            ->build();
        $path = sys_get_temp_dir() . '/aaro-c05-log-' . bin2hex(random_bytes(8));

        $this->withEnv(['CACHE_CONTROL' => 'memory'], function () use ($factory) {
            $factory->fromEnvironment();
        });
        $this->withEnv(['CACHE_CONTROL' => 'none'], function () use ($factory) {
            $factory->fromEnvironment();
        });
        $this->withEnv([
            'CACHE_CONTROL' => 'file',
            'CACHE_PATH' => $path,
        ], function () use ($factory) {
            $factory->fromEnvironment();
        });
        $this->withEnv(['CACHE_CONTROL' => 'apcu'], function () use ($factory) {
            $factory->fromEnvironment();
        });

        self::assertSame([
            [
                'level' => 'info',
                'message' => 'backend selected',
                'context' => ['control' => 'memory'],
            ],
            [
                'level' => 'info',
                'message' => 'backend selected',
                'context' => ['control' => 'none'],
            ],
            [
                'level' => 'info',
                'message' => 'backend selected',
                'context' => ['control' => 'file'],
            ],
            [
                'level' => 'info',
                'message' => 'backend selected',
                'context' => ['control' => 'apcu'],
            ],
        ], $logger->records);
    }

    private function withEnv(array $values, callable $callback): void
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
            $callback();
        } finally {
            foreach ($previous as $name => $value) {
                $_ENV[$name] = $value;
            }
            foreach ($missing as $name) {
                unset($_ENV[$name]);
            }
        }
    }
}
