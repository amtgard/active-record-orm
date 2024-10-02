<?php

namespace Amtgard\ActiveRecordOrm\Interface\Database;

use Amtgard\ActiveRecordOrm\Interface\Entity\RecordSet;

interface IDatabase
{
    public static function fromConfig(IDatabaseConfiguration $configuration): IDatabase;

    public function execute(string $sql): RecordSet;

    public function clear(): void;

}