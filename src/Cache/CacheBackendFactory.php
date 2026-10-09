<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/** Factory */
class CacheBackendFactory
{
    use Builder;

    private LoggerInterface $logger;

    /** @var callable(): bool */
    private $apcuAvailable;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->logger = Optional::ofNullable(isset($this->logger) ? $this->logger : null)
            ->orElseGet(fn () => new NullLogger());
    }

    public function fromEnvironment(): ?CacheInterface
    {
        $control = $this->requiredEnv('CACHE_CONTROL');
        $cache = $this->backend($control);
        $this->logger->info('backend selected', ['control' => $control]);
        return $cache;
    }

    private function backend(string $control): ?CacheInterface
    {
        if ($control === 'memory') {
            return InMemoryCache::builder()->build();
        }
        if ($control === 'file') {
            return FilesystemCache::builder()
                ->directory($this->requiredEnv('CACHE_PATH'))
                ->build();
        }
        if ($control === 'none') {
            return null;
        }
        if ($control === 'apcu') {
            return $this->apcuCache();
        }
        throw new \InvalidArgumentException('CACHE_CONTROL is not supported: ' . $control);
    }

    private function apcuCache(): ApcuCache
    {
        if (!$this->apcuIsLoaded()) {
            throw new \InvalidArgumentException('ext-apcu is not loaded');
        }
        return ApcuCache::builder()
            ->logger($this->logger)
            ->build();
    }

    private function apcuIsLoaded(): bool
    {
        $probe = Optional::ofNullable(isset($this->apcuAvailable) ? $this->apcuAvailable : null)
            ->orElse($this->extensionLoadedApcu(...));
        return $probe();
    }

    private function extensionLoadedApcu(): bool
    {
        return extension_loaded('apcu');
    }

    private function requiredEnv(string $name): string
    {
        if (!array_key_exists($name, $_ENV)) {
            throw new \InvalidArgumentException($name . ' is required');
        }
        $value = $_ENV[$name];
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException($name . ' is required');
        }
        return $value;
    }
}
