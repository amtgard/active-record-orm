<?php

namespace Amtgard\ActiveRecordOrm\Query;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Select;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\RecordSet;

class QueryBuilder
{
    protected Statement $statement;

    public function __construct() {

    }

    public function __set(String $field, String|int|bool $value) {
        $this->__fields[$field] = $value;
    }

    public function hash() {
        return md5("");
    }

    public function compile() {

    }

    public function execute(): RecordSet {
        return new RecordSet();
    }

    public function upsert() {

    }

    public function delete() {

    }

    public function find() {
        $this->statement = Select::builder()->
    }

    public function paginate(int $size = 10, int $offset = 0) {

    }

    public function count() {

    }
}