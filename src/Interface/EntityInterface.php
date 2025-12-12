<?php

namespace Amtgard\ActiveRecordOrm\Interface;

use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;

interface EntityInterface
{
    function isDirty(): bool;
    function getChanges(): array;

    function getPrimaryKey(): FieldDefinition;

    function persist(EntityMapper $mapper);

    function getSchema(): TableSchema;
}