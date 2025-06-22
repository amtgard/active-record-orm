<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;

class SelectExpression extends Expression
{
    use Builder;
    use Getter;

    private bool $isCount = false;
    private string $countAlias = 'row_count';

    public function willEmit(): bool
    {
        return true;
    }

    public function emit(): string
    {
        if ($this->isCount) {
            $fieldSelectorExpression = 'SELECT COUNT(*) as ' . $this->countAlias;
        } else {
            $fieldSelectorExpression = 'SELECT ' . implode(", ", array_map(fn($field): string => $field->getName(), $this->getTableSchema()->getFields()));
        }
        return $fieldSelectorExpression;
    }

    public function preparedParameters(): array
    {
        return [];
    }
}