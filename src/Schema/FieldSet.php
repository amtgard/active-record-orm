<?php

namespace Amtgard\ActiveRecordOrm\Schema;

use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Utility\Constants;
use Amtgard\Traits\Builder\Builder;

class FieldSet
{
    use Builder;

    /** @var FieldOperation[] */
    private array $fields = [];

    /** @var array<string, mixed> Row values bound on read until save/delete materializes FieldOperations. */
    private array $readRowValues = [];

    private function __construct() { }

    public function clear() {
        $this->fields = [];
        $this->readRowValues = [];
    }

    public function subSet(array $fieldNames): FieldSet {
        $subset = FieldSet::builder()->build();
        foreach ($fieldNames as $fieldName) {
            if (isset($this->fields[$fieldName])) {
                $subset->setField($this->fields[$fieldName]);
            } else {
                throw new \InvalidArgumentException(sprintf(Constants::$FIELDSET_MISSING_ERROR, $fieldName));
            }
        }
        return $subset;
    }

    public function setField(FieldOperation $fieldOperation) {
        $this->fields[$fieldOperation->getField()->getName()] = $fieldOperation;
    }

    public function hasField(string|FieldDefinition $field): bool
    {
        return array_key_exists(is_string($field) ? $field : $field->getName(), $this->fields);
    }

    public function mapRecord(Schema $schema, RecordSet $record, ?Callable $callback = null) {
        foreach ($schema->getFields() as $field) {
            $fieldName = $field->getName();
            $fieldOp = $this->setFieldValue($schema, $fieldName, $record->$fieldName, Operation::Equals);
            if ($callback) {
                $callback($fieldName, $fieldOp);
            }
        }
    }

    public function mapRecordForRead(Schema $schema, RecordSet $record, ?array $fieldNames = null): void {
        $this->readRowValues = [];
        if ($fieldNames !== null) {
            foreach ($fieldNames as $fieldName) {
                if ($record->hasField($fieldName)) {
                    $this->readRowValues[$fieldName] = $record->$fieldName;
                }
            }

            return;
        }

        foreach ($schema->getFields() as $field) {
            $fieldName = $field->getName();
            if ($record->hasField($fieldName)) {
                $this->readRowValues[$fieldName] = $record->$fieldName;
            }
        }
    }

    public function hasPendingReadRow(): bool {
        return $this->readRowValues !== [];
    }

    public function getReadRowValue(string $fieldName): mixed {
        return $this->readRowValues[$fieldName] ?? null;
    }

    public function materializeReadRow(Schema $schema, ?callable $callback = null): void {
        if ($this->readRowValues === []) {
            return;
        }

        foreach ($this->readRowValues as $fieldName => $value) {
            if (isset($this->fields[$fieldName])) {
                continue;
            }
            $fieldOp = $this->setFieldValue($schema, $fieldName, $value, Operation::Equals);
            if ($callback) {
                $callback($fieldName, $fieldOp);
            }
        }
        $this->readRowValues = [];
    }

    public function getFieldMap(): array {
        $fieldMap = [];
        foreach ($this->fields as $field) {
            $fieldMap[$field->getField()->getName()] = $field->getValue();
        }
        return $fieldMap;
    }

    public function setFieldOperation(Schema $schema, string $name, Operation $operation) {
        $schema->hasField($name);

        $this->fields[$name]->setOperation($operation);
    }

    public function setFieldValue(Schema $schema, string $name, $value, $operation = Operation::Set): FieldOperation {
        $schema->hasField($name);

        $field = FieldOperation::builder()
            ->field($schema->getField($name))
            ->value($value)
            ->operation($operation)
            ->build();
        $this->setField($field);

        return $field;
    }

    public function getField(string $fieldName): ?FieldOperation {
        return $this->fields[$fieldName] ?? null;
    }

    /** @return string[] */
    public function getFieldNames(): array {
        return array_keys($this->fields);
    }

    public function getFieldsByOperation(array $operations) {
        $fields = [];

        foreach ($this->fields as $field) {
            if (in_array($field->getOperation(), $operations)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function updateSetOperationToEquals() {
        foreach ($this->fields as $k => $field) {
            if ($field->getOperation() === Operation::Set) {
                $this->fields[$k]->setOperation(Operation::Equals);
            }
        }
    }

    public static function opsToNames(array $operations): array {
        return array_map(fn($op) => $op->getField()->getName(), $operations);
    }

    public static function opsToValues(array $operations): array {
        return array_map(fn($op) => $op->getValue(), $operations);
    }

    public static function opsToKeyValueMap(array $operations): array {
        return array_combine(self::opsToNames($operations), self::opsToValues($operations));
    }

    public function toKeyValueMap(array $operations): array {
        return static::opsToKeyValueMap($operations);
    }
}