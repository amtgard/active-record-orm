<?php

namespace Amtgard\ActiveRecordOrm\Configuration\Repository;

use Amtgard\ActiveRecordOrm\Schema\Schema;
use PDO;

interface PdoProviderInterface
{
    public function getPdo(): PDO;

    public function getDatabaseName(): string;

    public function convertToProviderParams(Schema $schema, array $ormValues): array;
}