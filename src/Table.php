<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Schema\TableSchema;

class Table
{
    private TableSchema $schema;
    public function __construct(TableSchema $schema) {
        $this->schema = $schema;
    }

}