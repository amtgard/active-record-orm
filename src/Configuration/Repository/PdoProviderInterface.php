<?php

namespace Amtgard\ActiveRecordOrm\Configuration\Repository;

use PDO;

interface PdoProviderInterface
{
    public function getPdo(): PDO;

    public function getDatabaseName(): string;

}