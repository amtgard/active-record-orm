<?php

namespace Amtgard\ActiveRecordOrm\Feature;

use Amtgard\ActiveRecordOrm\Interface\TableInterface;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Query\OrderBy;
use Amtgard\ActiveRecordOrm\ResultSet;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\Traits\Builder\Builder;


class AuditTable extends Table implements TableInterface
{
    use Builder;

    protected Table $srcTable;
    protected Table $auditTable;
    protected $byWhomSupplier;

    public function __set(string $name, $value): void
    {
        $this->srcTable->$name = $value;
        $this->auditTable->$name = $value;
    }

    public function __get(string $name)
    {
        return $this->srcTable->$name;
    }

    public function clear(): void
    {
        $this->srcTable->clear();
        $this->auditTable->clear();
    }

    public function orderBy(string $fieldName, OrderBy $orderBy): void
    {
        $this->srcTable->orderBy($fieldName, $orderBy);
    }

    public function select(mixed $fieldNameOrSet): void
    {
        $this->srcTable->select($fieldNameOrSet);
    }

    public function find(): int
    {
        return $this->srcTable->find();
    }

    public function count(string $countAlias = 'row_count'): int
    {
        return $this->srcTable->count($countAlias);
    }

    public function page(int $size = 10, int $page = 0): \Amtgard\ActiveRecordOrm\Interface\ActiveRecordTableInterface
    {
        return $this->srcTable->page($size, $page);
    }

    public function limit(int $offset, ?int $rowCount = null): void
    {
        $this->srcTable->limit($offset, $rowCount);
    }

    public function size(): int
    {
        return $this->srcTable->size();
    }

    public function next(): bool
    {
        return $this->srcTable->next();
    }

    public function hasActiveRecord(): bool
    {
        return $this->srcTable->hasActiveRecord();
    }

    public function getResultSet(): ResultSet
    {
        return $this->srcTable->getResultSet();
    }

    protected function _insert_audit_log(string $action, ?string $fields = null) {
        $this->auditTable->clear();
        $this->auditTable->record_id = $this->srcTable->getPrimaryKeyValue();
        $this->auditTable->log_datetime = date("Y-m-d H:i:s");
        $this->auditTable->fields = json_encode([]);
        $this->auditTable->action = $action;
//        $this->auditTable->by_whom_id = $this->byWhomSupplier();
        if (isset($fields)) $this->auditTable->fields = $fields;
        foreach ($this->srcTable->getFieldSet()->getFieldMap() as $field => $value) {
            if ($this->srcTable->getTableSchema()->getPrimaryKey()->getName() != $field)
                if (isset($value)) $this->auditTable->$field = $value;
        }
        $this->auditTable->save();
    }

    protected function _get_field_map(): string {
        $fields_updated = [];
        foreach ($this->srcTable->getFieldSet()->getFieldMap() as $field => $value) {
            $fields_updated[] = $field;
        }
        return json_encode($fields_updated);
    }

    /*
     * Automatic fields:
     * record_id: int; primary key from source table
     * log_datetime: datetime; date time log of entry
     * fields: mediumtext; json list of affected fields
     * action: enum(insert, update, delete); access pattern in source field
     * by_whom_id: int; integer reference to the actor; returned by byWhomSupplier()
     */
    public function save(): void
    {
        $fields = $this->_get_field_map();
        $action = $this->srcTable->hasActiveRecord() ? "update" : "insert";
        $this->srcTable->save();
        $this->_insert_audit_log($action, $fields);
    }

    public function delete(): void
    {
        $this->_insert_audit_log("delete");
        $this->srcTable->delete();
    }

    public function __call(string $name, array $arguments): void
    {
        call_user_func([$this->srcTable, $name], $arguments);
    }

    public function operation(string $name, Operation $operation, $value): void
    {
        $this->srcTable->operation($name, $operation, $value);
    }

    public function getPrimaryKeyValue()
    {
        return $this->srcTable->getPrimaryKeyValue();
    }

    public function getSetFields(): FieldSet
    {
        return $this->srcTable->getSetFields();
    }
}