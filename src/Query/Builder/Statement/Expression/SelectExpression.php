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
    private array $fieldSelectors = [];

    public function willEmit(): bool
    {
        return true;
    }

    public function emit(): string
    {
        if ($this->isCount) {
            $fieldSelectorExpression = 'SELECT COUNT(*) as ' . $this->countAlias;
        } else {
            if (isset($this->fieldSelectors) && count($this->fieldSelectors) > 0) {
                $fieldSelectorExpression = 'SELECT ' . implode(", ", $this->fieldSelectors);
            } else {
                $fieldSelectorExpression = 'SELECT *';
            }
        }
        return $fieldSelectorExpression;
    }

    public function preparedParameters(): array
    {
        return [];
    }
}