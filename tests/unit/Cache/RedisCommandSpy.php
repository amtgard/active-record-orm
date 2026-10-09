<?php

namespace Tests\Unit\Cache;

class RedisCommandSpy
{
    /** @var array<int, string> */
    public array $gets = [];

    /** @var array<int, array<int, mixed>> */
    public array $sets = [];

    /** @var array<int, string> */
    public array $deletes = [];

    public int $connects = 0;

    public int $flushes = 0;

    public ?string $host = null;

    public ?int $port = null;

    public bool $connectReturns = true;

    public mixed $getResult = false;

    public bool $getThrows = false;

    public mixed $setResult = true;

    public bool $setThrows = false;

    public mixed $delResult = 1;

    public mixed $flushResult = true;

    public function get(string $key): mixed
    {
        if ($this->getThrows) {
            throw new \RuntimeException('redis get failed');
        }
        $this->gets[] = $key;
        return $this->getResult;
    }

    public function set(string $key, mixed $value, mixed $options = null): mixed
    {
        if ($this->setThrows) {
            throw new \RuntimeException('redis set failed');
        }
        $this->sets[] = func_get_args();
        return $this->setResult;
    }

    public function del(string $key): mixed
    {
        $this->deletes[] = $key;
        return $this->delResult;
    }

    public function flushDB(): mixed
    {
        $this->flushes++;
        return $this->flushResult;
    }

    public function connect(string $host, int $port = 6379): bool
    {
        $this->connects++;
        $this->host = $host;
        $this->port = $port;
        return $this->connectReturns;
    }
}
