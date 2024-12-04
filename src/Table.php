<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Configuration\Database\Database;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;

class Table
{
    use Builder;

    private Database $database;
    private TableSchema $schema;
    private QueryBuilder $query;
    private RecordSet $recordSet;

    public function clear() {
        $this->query = new QueryBuilder();
    }

    public function find(): QueryBuilder {
        $this->query = new QueryBuilder();
        return $this->query;
    }

    private function hasResults(): bool {
        return false;
    }

    private function readyToExecute(): bool {
        return false;
    }

    public function next(): \Generator {
        if (!$this->hasResults() && !$this->readyToExecute()) {
            $this->query->compile();
            $this->recordSet = $this->query->execute();
        }
        if ($this->hasResults()) {
            do {
                yield $this->recordSet;
            } while ($this->recordSet->next());
        }
    }

    public function save() {

    }

    public function delete() {

    }

    public function paginate(int $size = 10, int $offset = 10) {

    }

    public function hasCurrentRecord(): bool {
        return false;
    }

    public function or() {

    }

}