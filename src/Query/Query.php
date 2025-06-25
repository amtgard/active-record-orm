<?php

namespace Amtgard\ActiveRecordOrm\Query;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;

class Query implements \JsonSerializable
{
    use Builder, Data;

    private string $sql;
    private array $params;
    private DataAccessPolicy $policy;
    private $postQueryCallback;

    public function hash() {
        return md5(json_encode($this));
    }

    public function apply(Database $db): RecordSet {
        $db->clear();
        foreach ($this->params as $key => $value) {
            $db->$key = $value;
        }
        return $db->execute($this->sql);
    }

    public function postQuery() {
        if (is_callable($this->postQueryCallback)) {
            call_user_func($this->postQueryCallback);
        }
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}