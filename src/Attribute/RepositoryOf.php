<?php

namespace Amtgard\ActiveRecordOrm\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RepositoryOf
{
    public function __construct(string $tableName, string $repositoryEntityClass) {
    }

}