<?php

namespace Amtgard\ActiveRecordOrm\Impl;

use Amtgard\ActiveRecordOrm\Interface\Database\IDatabase;
use Amtgard\ActiveRecordOrm\Interface\Database\IDatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Interface\Entity\RecordSet;

class Database
{
    private IDatabase $dbDriver;

    public function __construct(IDatabase $dbDriver) {
        $this->dbDriver = $dbDriver;
    }

    public function execute(string $sql): RecordSet
    {
        return $this->dbDriver->execute($sql);
    }

    public function clear(): void
    {
        $this->dbDriver->clear();
    }
}