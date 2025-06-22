<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression;

use Amtgard\ActiveRecordOrm\Query\Builder\Statement\Expression\Expression;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\ActiveRecordOrm\Schema\FieldSet;
use Amtgard\Traits\Builder\Builder;
use Optional\Optional;
use PDO;

class WhereExpression extends Expression
{

    use Builder;

    public function willEmit(): bool
    {
        return count($this->getBinaryFieldQualifiers()) > 0 || count($this->getUnaryFieldQualifiers()) > 0 || count($this->getSetFieldQualifiers()) > 0;
    }

    public function emit(): string
    {
        return "WHERE " .
            implode(' AND ',
                array_merge(
                    array_map(
                        fn($f) => "{$f->getField()->getName()} " . WhereExpression::emitOperator($f->getOperation()) . " :{$f->getField()->getName()}",
                        $this->getBinaryFieldQualifiers()),

                    array_map(
                        fn($f) => "{$f->getField()->getName()} " . WhereExpression::emitOperator($f->getOperation()),
                        $this->getUnaryFieldQualifiers()),

                    array_map(
                        fn($f) => "{$f->getField()->getName()} " . WhereExpression::emitOperator($f->getOperation()) . " (" . implode(', ', array_map(fn($v) => $v, $f->getValue())  ) . ")",
                        $this->getSetFieldQualifiers()),
                )
            );
    }

    private function getUnaryFieldQualifiers(): array
    {
        return $this->getFieldSet()->getFieldsByOperation([Operation::IsNull, Operation::IsNotNull]);
    }

    private function getSetFieldQualifiers(): array
    {
        return $this->getFieldSet()->getFieldsByOperation([Operation::In, Operation::NotIn]);
    }

    static function emitOperator(Operation $operation) {
        switch ($operation) {
            case Operation::Equals: return '=';
            case Operation::Greater: return '>';
            case Operation::GreaterOrEqual: return '>=';
            case Operation::Less: return '<';
            case Operation::LessOrEqual: return '<=';
            case Operation::Like: return 'LIKE';
            case Operation::NotLike: return 'NOT LIKE';

            case Operation::IsNull: return 'IS NULL';
            case Operation::IsNotNull: return 'IS NOT NULL';

            case Operation::In: return 'IN';
            case Operation::NotIn: return 'NOT IN';
        }
        throw new \InvalidArgumentException("Invalid Operation: {$operation->value}");
    }

    private function getBinaryFieldQualifiers(): array
    {
        return $this->getFieldSet()->getFieldsByOperation([Operation::Equals, Operation::Greater, Operation::GreaterOrEqual, Operation::Less, Operation::LessOrEqual, Operation::Like, Operation::NotLike]);
    }

    public function preparedParameters(): array
    {
        return FieldSet::opsToKeyValueMap($this->getBinaryFieldQualifiers());
    }
}