<?php

namespace Amtgard\ActiveRecordOrm;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\Query\QueryBuilder;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Optional\Optional;

class Table
{
    use Builder;

    private Database $database;
    private TableSchema $tableSchema;
    private QueryBuilder $queryBuilder;
    private DataAccessPolicy $dataAccessPolicy;
    private ?RecordSet $recordSet;
    private FieldSet $fieldSet;
    private string $tableName;

    protected bool $withLimit = false;
    protected int $offset = 0;
    protected ?int $rowCount = null;

    private function __constructor() {}

    public function __set(string $name, $value) {
        $this->setFieldValue($name, $value);
    }

    public function __get(string $name) {
        if (isset($this->recordSet) && $this->recordSet->hasField($name)) {
            return $this->recordSet->$name;
        }

        $this->tableSchema->hasField($name);

        return Optional::ofNullable($this->fieldSet->getField($name))
            ->map(fn ($field) => $field->getValue())
            ->orElse(null);
    }

    public function clear() {
        $this->recordSet = null;
        $this->withLimit = false;
        $this->offset = 0;
        $this->rowCount = null;
        $this->queryBuilder = TableFactory::buildQueryBuilder($this->dataAccessPolicy, $this->tableName);
        $this->fieldSet->clear();
    }

    public function orderBy(string $fieldName, OrderBy $orderBy) {
        $this->queryBuilder->orderBy($fieldName, $orderBy);
    }

    public function find(): int {
        if ($this->withLimit) {
            $this->queryBuilder->limit($this->offset, $this->rowCount);
        }
        $this->queryBuilder->find();
        $this->recordSet = $this->dataAccessPolicy->applyQueryPolicy($this->queryBuilder->compile());
        $this->fieldSet->clear();
        return $this->recordSet->size();
    }

    public function count(string $countAlias = 'row_count'): int {
        $this->queryBuilder->count($countAlias);
        $this->recordSet = $this->dataAccessPolicy->applyQueryPolicy($this->queryBuilder->compile());
        $this->fieldSet->clear();
        $this->next();
        return $this->recordSet->size();
    }

    public function page(int $size = 10, int $page = 0) {
        $this->withLimit = true;
        $this->offset = $size * $page;
        $this->rowCount = $page;
        return $this;
    }

    public function limit(int $offset, ?int $rowCount = null) {
        $this->withLimit = true;
        $this->offset = $offset;
        if (isset($rowCount)) {
            $this->rowCount = $rowCount;
        }
    }

    public function getResultSet(): ResultSet {
        return ResultSet::builder()
            ->recordSet($this->recordSet)
            ->schema($this->tableSchema)
            ->fieldSet($this->fieldSet)
            ->build();
    }

    public function save() {
        $this->queryBuilder->upsert(function() {
            $this->setFieldValue($this->tableSchema->getPrimaryKey()->getName(), $this->database->getLastInsertId());
        });
        $this->dataAccessPolicy->applyQueryPolicy($this->queryBuilder->compile());
    }

    public function delete() {
        $this->queryBuilder->delete();
        $this->dataAccessPolicy->applyQueryPolicy($this->queryBuilder->compile());
    }

    public function size():  int {
        if ($this->hasResults()) {
            return $this->recordSet->size();
        }
        return 0;
    }

    private function hasResults(): bool {
        return Optional::ofNullable($this->recordSet)->isPresent();
    }

    public function next(): bool {
        $hasNext = $this->recordSet->next();
        $this->fieldSet->clear();
        $this->fieldSet->mapRecord($this->tableSchema, $this->recordSet, fn($fieldName, $fieldOp) => $this->queryBuilder->$fieldName = $fieldOp);
        return $hasNext;
    }

    private function setFieldValue(string $name, mixed $value, ?Operation $operation = Operation::Set) {
        $this->queryBuilder->$name = $this->fieldSet->setFieldValue($this->tableSchema, $name, $value, $operation);
        return $value;
    }

    public function hasActiveRecord(): bool {
        return $this->recordSet->hasActiveRecord();
    }

    public function __call(string $name, array $arguments) {
        Optional::ofNullable(Operation::fromString($name))
            ->map(function($operation) use ($arguments) {
                $this->operation($arguments[0], $operation, $arguments[1]);
                return true;
            })
            ->orElseThrow(new \InvalidArgumentException("Invalid Operation name: $name"));
    }

    public function operation(string $name, Operation $operation, $value) {
        $fieldOp = FieldOperation::builder()
            ->field($this->tableSchema->getField($name))
            ->value($value)
            ->operation($operation)
            ->build();
        $this->queryBuilder->$name = $fieldOp;
    }
}