<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Configuration\Repository\Database;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\Schema;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Optional\Optional;

class ResultSet
{
    use Builder;

    private Schema $schema;
    private ?RecordSet $recordSet;
    private FieldSet $fieldSet;

    private function __constructor() {}

    public function __get(string $name) {
        $this->schema->hasField($name);

        return Optional::ofNullable($this->fieldSet->getField($name))
            ->map(fn ($field) => $field->getValue())
            ->orElse(null);
    }

    public function next(): bool {
        $hasNext = $this->recordSet->next();
        $this->fieldSet->clear();
        $this->fieldSet->mapRecord($this->schema, $this->recordSet);
        return $hasNext;
    }


}