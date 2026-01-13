<?php

namespace Amtgard\ActiveRecordOrm\Entity\Repository;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Optional\Optional;

class EntityFieldMap
{
    use Builder, Data;

    /** @var MappedInfo[]  */
    private $fieldMap = [];

    /** @var String[] */
    private $fieldSourceMap = [];

    public function getInstanceFields(): array
    {
        return array_keys($this->fieldMap);
    }

    public function setField(string $fieldName, MappedInfo $mappedInfo)
    {
        $this->fieldMap[$fieldName] = $mappedInfo;
        $this->fieldSourceMap[$mappedInfo->source] = $fieldName;
    }

    public function getField(string $fieldName): ?MappedInfo
    {
        return Optional::ofNullable($this->fieldMap[$fieldName])
            ->orElseGet(function () use ($fieldName) {
                return Optional::ofNullable($this->fieldSourceMap[$fieldName])
                    ->map(fn($sourceField) => $this->fieldMap[$sourceField])
                    ->orElse(null);
            });
    }
}