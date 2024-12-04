<?php

namespace Amtgard\ActiveRecordOrm\Query\Builder;

use Amtgard\ActiveRecordOrm\Query\FieldOperation;
use Amtgard\ActiveRecordOrm\Query\Operation;
use Amtgard\Traits\Builder\Getter;
use Amtgard\Traits\Builder\Setter;
use InvalidArgumentException;

class WhereBuilder extends Builder
{
    use \Amtgard\Traits\Builder\Builder;
    use Setter;
    use Getter;

    public function getQueryPart(): QueryPart
    {
        $clauses = [];
        $paramNames = [];
        $paramValues = [];
        foreach ($this->fieldSet->getFieldNames() as $fieldName) {
            $this->validate($this->fieldSet->getField($fieldName));

            $alias = $this->getFqFieldAlias($fieldName);
            $operation = $this->fieldSet->getField($fieldName)->operation;
            $value = $this->fieldSet->getField($fieldName)->value;

            switch ($operation) {
                case Operation::Set: break;
                case Operation::Equals: $clauses[] = "{$alias} = :{$alias}"; break;
                case Operation::In: $clauses[] = "{$alias} in '" . implode("','", $value) . "'"; break;
                case Operation::NotIn: $clauses[] = "{$alias} not in " . implode("','", $value) . "'"; break;
                case Operation::Like: $clauses[] = "{$alias} like :{$alias}"; break;
                case Operation::Greater: $clauses[] = "{$alias} > :{$alias}"; break;
                case Operation::Less: $clauses[] = "{$alias} < :{$alias}"; break;
                case Operation::GreaterOrEqual: $clauses[] = "{$alias} >= :{$alias}"; break;
                case Operation::LessOrEqual: $clauses[] = "{$alias} <= :{$alias}"; break;
                case Operation::NotLike: $clauses[] = "{$alias} not like :{$alias}"; break;
            }

            switch ($operation) {
                case Operation::Set: break;
                case Operation::In: break;
                case Operation::NotIn: break;
                default:
                    $paramNames[] = $alias;
                    $paramValues[] = $value;
                    break;
            }
        }
        return QueryPart::builder()
            ->data($clauses)
            ->params($paramNames)
            ->paramValues($paramValues)
            ->build();
    }

    public function validate(FieldOperation $fieldOperation) {
        if ($fieldOperation->operation == Operation::In || $fieldOperation->operation == Operation::NotIn) {
            if (!is_array($fieldOperation->value)) {
                throw new InvalidArgumentException("{$fieldOperation->field->getAlias()} should be an array}");
            }
        }
    }
}