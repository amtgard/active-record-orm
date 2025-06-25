<?php

namespace Amtgard\ActiveRecordOrm\Query;

use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Query\Builder\DeleteStatementBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\FindStatementBuilder;
use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Statement;
use Amtgard\ActiveRecordOrm\Query\Builder\UpsertStatementBuilder;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\Traits\Builder\Builder;
use Optional\Optional;

/*
 * General guidelines
 *  QueryBuilder calls specific sub-builders with their field parameters
 *  Sub-builders build statements composed of expressions
 *  Statements can generate SQL from their expressions, table, and fieldsets
 */
class QueryBuilder
{
    use Builder;

    protected Statement $statement;
    protected TableSchema $tableSchema;
    protected DataAccessPolicy $tablePolicy;
    protected FieldSet $fieldSet;
    protected array $fieldSelectors = [];

    protected bool $withLimit = false;
    protected int $offset = 0;
    protected ?int $rowCount = null;

    protected array $orderByOperations = [];

    private function __construct() {

    }

    public function __set($fieldName, FieldOperation $fieldOperation) {
        if ($fieldOperation instanceof FieldOperation) {
            $this->fieldSet->setField($fieldOperation);
        } else {
            throw new \InvalidArgumentException("Fields set on QueryBuilder must be an instance of FieldOperation");
        }
    }

    public function select(mixed $fieldNameOrSet) {
        if (is_array($fieldNameOrSet)) {
            foreach ($fieldNameOrSet as $fieldName) {
                $this->fieldSelectors[$fieldName] = $fieldName;
            }
        } else {
            $this->fieldSelectors[$fieldNameOrSet] = $fieldNameOrSet;
        }
    }

    public function hash() {
        return md5("");
    }

    public function compile(): Query {
        return Query::builder()
            ->sql($this->statement->buildSql())
            ->params($this->statement->getStatementParams())
            ->postQueryCallback($this->statement->getPostQueryCallback())
            ->build();
    }

    public function upsert(?callable $setLastInsertId = null) {
        $upsertBuilder = UpsertStatementBuilder::builder()
            ->tableSchema($this->tableSchema);

        if ($this->primaryKeyIsSet()) {
            // Update path
            $upsertBuilder->primaryKey($this->fieldSet->getField($this->tableSchema->primaryKey->getName())->value);
            $this->fieldSet->setFieldOperation($this->tableSchema, $this->tableSchema->primaryKey->getName(), Operation::Equals);
        } else {
            // Insert path
            $upsertBuilder->postQueryCallback($setLastInsertId);
        }

        /* @var \Amtgard\ActiveRecordOrm\Query\Builder\UpsertStatementBuilder */
        $upsert = $upsertBuilder
            ->fieldSet($this->fieldSet)
            ->build();
        $this->statement = $upsert->getStatement();
        return $this;
    }

    public function delete() {
        $deleteBuilder = DeleteStatementBuilder::builder()
            ->tableSchema($this->tableSchema)
            ->fieldSet($this->fieldSet);

        if ($this->primaryKeyIsSet()) {
            $deleteBuilder->primaryKey($this->fieldSet->getField($this->tableSchema->primaryKey->getName())->value);
        }

        /* @var \Amtgard\ActiveRecordOrm\Query\Builder\DeleteStatementBuilder */
        $delete = $deleteBuilder->build();
        $this->statement = $delete->getStatement();
        return $this;
    }

    private function findBuilder(): mixed {
        $this->fieldSet->updateSetOperationToEquals();

        $findBuilder = FindStatementBuilder::builder()
            ->tableSchema($this->tableSchema)
            ->fieldSet($this->fieldSet);

        if ($this->primaryKeyIsSet()) {
            $findBuilder->primaryKey($this->fieldSet->getField($this->tableSchema->primaryKey->getName())->value);
        }

        return $findBuilder;
    }

    public function find() {
        /* @var \Amtgard\ActiveRecordOrm\Query\Builder\FindStatementBuilder */
        $findBuilder = $this->findBuilder();
        $findBuilder->withLimit($this->withLimit);
        $findBuilder->offset($this->offset);
        $findBuilder->rowCount($this->rowCount);
        $findBuilder->orderByOperations($this->orderByOperations);
        $findBuilder->fieldSelectors($this->fieldSelectors);

        /* @var \Amtgard\ActiveRecordOrm\Query\Builder\FindStatementBuilder */
        $find = $findBuilder->build();
        $this->statement = $find->getStatement();
        return $this;
    }

    private function primaryKeyIsSet(): bool {
        return Optional::ofNullable($this->fieldSet->getField($this->tableSchema->getPrimaryKey()->getName()))
            ->map(function ($field) {
                return $field->operation == Operation::Equals || $field->operation == Operation::Set;
            })->orElse(false);
    }

    public function orderBy(string $fieldName, OrderBy $orderBy) {
        $this->orderByOperations[$fieldName] = $orderBy;
    }

    public function limit(int $offset = 10, ?int $rowCount = null) {
        $this->withLimit = true;
        $this->offset = $offset;
        $this->rowCount = $rowCount;
        return $this;
    }

    public function count(string $countAlias = 'row_count') {
        $findBuilder = $this->findBuilder();
        $findBuilder->isCount(true);
        $findBuilder->countAlias($countAlias);

        /* @var \Amtgard\ActiveRecordOrm\Query\Builder\FindStatementBuilder */
        $find = $findBuilder->build();
        $this->statement = $find->getStatement();
        return $this;
    }
}