<?php

namespace Amtgard\ActiveRecordOrm\Cache;

use Amtgard\Traits\Builder\Builder;

/** Value Object */
class CacheSubject
{
    use Builder;

    private string $databaseName;
    private string $entryKind;
    private string $keyId;

    private function __construct()
    {
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function getEntryKind(): string
    {
        return $this->entryKind;
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }
}
