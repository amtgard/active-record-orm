<?php

namespace Tests\Unit\Cache;

class MemcachedCommandSpy
{
    /** @var array<int, string> */
    public array $gets = [];

    /** @var array<int, array<int, mixed>> */
    public array $sets = [];

    /** @var array<int, string> */
    public array $deletes = [];

    public int $servers = 0;

    public int $flushes = 0;

    public int $resultCodeReads = 0;

    public ?string $host = null;

    public ?int $port = null;

    public bool $addServerReturns = true;

    public mixed $getResult = false;

    public bool $getThrows = false;

    public mixed $setResult = true;

    public bool $setThrows = false;

    public mixed $deleteResult = true;

    public int $resultCode = 0;

    public mixed $flushResult = true;

    public function get(string $key): mixed
    {
        if ($this->getThrows) {
            throw new \RuntimeException('memcached get failed');
        }
        $this->gets[] = $key;
        return $this->getResult;
    }

    public function set(string $key, mixed $value, int $expiration = 0): mixed
    {
        if ($this->setThrows) {
            throw new \RuntimeException('memcached set failed');
        }
        $this->sets[] = func_get_args();
        return $this->setResult;
    }

    public function delete(string $key, int $time = 0): mixed
    {
        $this->deletes[] = $key;
        return $this->deleteResult;
    }

    public function flush(int $delay = 0): mixed
    {
        $this->flushes++;
        return $this->flushResult;
    }

    public function addServer(string $host, int $port = 11211, int $weight = 0): bool
    {
        $this->servers++;
        $this->host = $host;
        $this->port = $port;
        return $this->addServerReturns;
    }

    public function getResultCode(): int
    {
        $this->resultCodeReads++;
        return $this->resultCode;
    }
}
