<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\Setter;
use Optional\Optional;

abstract class Builder
{
    use \Amtgard\Traits\Builder\Builder;
    use Setter;
    use Getter;

    protected TableSchema $tableSchema;
    protected FieldSet $fieldSet;
    protected $primaryKey;

    protected function getTableAlias(int $tableNum): string {
        return substr($this->tableSchema->getTableName(), 0, 1) . $tableNum;
    }

    protected function getFieldAlias(string $fieldName): string {
        return $this->getTableAlias($fieldName) . '_' . $this->fieldSet->getField($fieldName)->field->getAlias();
    }

    protected function getFqFieldAlias(string $fieldName, string $prefix = null, int $tableNum = 1): string {
        return Optional::ofNullable($prefix)
            ->map(function() use ($tableNum, $fieldName, $prefix) {
                return $prefix . '_' . $this->getTableAlias($tableNum) . '.' . $this->getFieldAlias($fieldName);
            })
            ->orElseGet(function() use ($tableNum, $fieldName) {
                return $this->getTableAlias($tableNum) . '.' . $this->getFieldAlias($fieldName);
            });
    }
}