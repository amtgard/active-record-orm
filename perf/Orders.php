<?php

namespace Perf;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Attribute\RepositoryOf;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Amtgard\Traits\Builder\ToBuilder;
use DateTimeInterface;

#[RepositoryOf('perf_order', PerfOrderEntity::class)]
class PerfOrderRepository extends Repository
{
    public static function getTableName()
    {
        return 'perf_order';
    }

    public static function getEntityClass()
    {
        return PerfOrderEntity::class;
    }
}

#[EntityOf(PerfOrderRepository::class)]
class PerfOrderEntity extends RepositoryEntity
{
    use Builder;
    use ToBuilder;
    use Data;

    #[PrimaryKey('id')]
    private ?int $id;

    #[Field('customer_id')]
    private ?int $customerId;

    #[Field('status')]
    private ?string $status;

    #[Field('currency')]
    private ?string $currency;

    #[Field('subtotal')]
    private ?string $subtotal;

    #[Field('tax')]
    private ?string $tax;

    #[Field('shipping')]
    private ?string $shipping;

    #[Field('total')]
    private ?string $total;

    #[Field('notes')]
    private ?string $notes;

    #[Field('ship_city')]
    private ?string $shipCity;

    #[Field('ship_region')]
    private ?string $shipRegion;

    #[Field('ship_postal')]
    private ?string $shipPostal;

    #[Field('placed_at')]
    private ?DateTimeInterface $placedAt;

    #[Field('updated_at')]
    private ?DateTimeInterface $updatedAt;

    #[Field('source')]
    private ?string $source;

    #[Field('external_ref')]
    private ?string $externalRef;
}
