<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\Traits\Builder\Getter;

class QueryPart
{
    use \Amtgard\Traits\Builder\Builder;
    use Getter;

    protected array $data;

    /** @var string[] */
    protected array $paramNames;
    protected array $paramValues;

    public function hasPart(): bool {
        return isset($this->data);
    }
}