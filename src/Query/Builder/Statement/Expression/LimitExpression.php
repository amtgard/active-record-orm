<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\Traits\Builder\Builder;

class LimitExpression extends Expression
{

    protected bool $withLimit = false;
    protected int $offset = 0;
    protected ?int $rowCount = null;

    use Builder;

    public function willEmit(): bool
    {
        return $this->withLimit;
    }

    public function emit(): string
    {
        return "LIMIT " . ($this->offset) . (isset($this->rowCount) ? (", " . $this->rowCount) : "");
    }

    public function preparedParameters(): array
    {
        return [];
    }
}