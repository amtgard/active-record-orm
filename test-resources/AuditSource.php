<?php

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;

#[EntityOf(AuditSourceRepository::class)]
class AuditSource extends RepositoryEntity
{
    use Builder, ToBuilder, Data;

    #[PrimaryKey]
    private int $id;

    #[Field('int_value')]
    private ?int $intValue;

    #[Field('string_value')]
    private ?string $stringValue;
}