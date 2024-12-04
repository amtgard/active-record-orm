<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\Setter;

class FindBuilder extends Builder
{
    use \Amtgard\Traits\Builder\Builder;
    use Setter;
    use Getter;

    public function getQueryPart(): QueryPart
    {
        return QueryPart::builder()->build();
    }
}