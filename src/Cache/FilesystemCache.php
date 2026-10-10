<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\ActiveRecordOrm\Exception\NotImplementedException;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\PostInit;
use Optional\Optional;
use Psr\SimpleCache\CacheInterface;

/** Adapter */
class FilesystemCache implements CacheInterface
{
    use Builder;

    private const ENTRY_DIRECTORY = 'aaro';

    private string $directory;

    private Clock $clock;

    private function __construct()
    {
    }

    #[PostInit]
    private function postInit(): void
    {
        $this->clock = Optional::ofNullable(isset($this->clock) ? $this->clock : null)
            ->orElseGet(fn () => SystemClock::builder()->build());
        $directory = isset($this->directory) ? $this->directory : '';
        if ($directory === '') {
            throw new \InvalidArgumentException('Filesystem cache directory is required');
        }
        $this->directory = $directory;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->liveEntry($key);
        return $entry['present'] ? $entry['value'] : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        if ($this->ttlDeletesImmediately($ttl)) {
            $this->delete($key);
            return true;
        }
        $this->writeEntry($key, $value, $this->expiresAt($ttl));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $this->removeFile($this->pathFor($key));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $entryDirectory = $this->entryDirectory();
        if (!is_dir($entryDirectory)) {
            return true;
        }
        foreach (scandir($entryDirectory) as $name) {
            $this->removeOwned($entryDirectory, $name);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        throw new NotImplementedException("getMultiple is not implemented yet.");
    }

    /**
     * @param mixed[] $values
     * @inheritDoc
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        throw new NotImplementedException("setMultiple is not implemented yet.");
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        throw new NotImplementedException("deleteMultiple is not implemented yet.");
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->liveEntry($key)['present'];
    }

    /**
     * @return array{present: bool, value: mixed}
     */
    private function liveEntry(string $key): array
    {
        $path = $this->pathFor($key);
        $stored = $this->readStored($path);
        if ($stored === null) {
            return ['present' => false, 'value' => null];
        }
        if ($this->timestampHasPassed($stored['expiresAt'])) {
            $this->removeFile($path);
            return ['present' => false, 'value' => null];
        }
        return ['present' => true, 'value' => $stored['value']];
    }

    private function writeEntry(string $key, mixed $value, ?int $expiresAt): void
    {
        $this->ensureDirectory();
        file_put_contents(
            $this->pathFor($key),
            json_encode(
                ['value' => $value, 'expiresAt' => $expiresAt],
                JSON_THROW_ON_ERROR
            )
        );
    }

    private function readStored(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }
        if (!array_key_exists('value', $decoded) || !array_key_exists('expiresAt', $decoded)) {
            return null;
        }
        if ($decoded['expiresAt'] !== null && !is_int($decoded['expiresAt'])) {
            return null;
        }
        return $decoded;
    }

    private function ensureDirectory(): void
    {
        $entryDirectory = $this->entryDirectory();
        if (is_dir($entryDirectory)) {
            return;
        }
        mkdir($entryDirectory, 0777, true);
    }

    private function removeOwned(string $entryDirectory, string $name): void
    {
        if (!$this->ownedName($name)) {
            return;
        }
        $this->removeFile($entryDirectory . DIRECTORY_SEPARATOR . $name);
    }

    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function ownedName(string $name): bool
    {
        return strlen($name) === 64 && ctype_xdigit($name);
    }

    private function entryDirectory(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::ENTRY_DIRECTORY;
    }

    private function pathFor(string $key): string
    {
        return $this->entryDirectory() . DIRECTORY_SEPARATOR . hash('sha256', $key);
    }

    private function ttlDeletesImmediately(\DateInterval|int|null $ttl): bool
    {
        if ($ttl === null) {
            return false;
        }
        if (is_int($ttl)) {
            return $ttl <= 0;
        }
        return $this->timestampHasPassed($this->expiresAt($ttl));
    }

    private function expiresAt(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if (is_int($ttl)) {
            return $this->clock->now() + $ttl;
        }
        return (new \DateTimeImmutable('@' . $this->clock->now()))
            ->add(clone $ttl)
            ->getTimestamp();
    }

    private function timestampHasPassed(?int $expiresAt): bool
    {
        return Optional::ofNullable($expiresAt)
            ->map(fn (int $expiresAt) => $expiresAt <= $this->clock->now())
            ->orElse(false);
    }
}
