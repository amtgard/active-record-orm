<?php

namespace Amtgard\ActiveRecordOrm\Query;

enum Operation: string
{
    // Case for when a value happens to be equivalent
    case Equals = "equals";

    // Context for when a value has been set for updating or inserting
    case Set = "set";
    case Like = "like";
    case NotLike = "notLike";
    case Greater = "gt";
    case Less = "lt";
    case GreaterOrEqual = "gte";
    case LessOrEqual = "lte";

    case Contains = "contains";
    case StartsWith = "startsWith";
    case EndsWith = "endsWith";

    case In = "in";
    case NotIn = "notIn";

    case Between = "between";
    case NotBetween = "notBetween";

    case IsNull = "isNull";
    case IsNotNull = "isNotNull";

    case And = "and";
    case Or = "or";

    public static function fromString(string $string): self {
        $operation = self::tryFrom($string);
        if (is_null($operation)) {
            switch ($string) {
                case 'greater':
                case 'greaterThan':
                    $operation = Operation::Greater;
                    break;
                case 'greaterThanOrEqualTo':
                    $operation = Operation::GreaterOrEqual;
                    break;
                case 'less':
                case 'lessThan':
                    $operation = Operation::Less;
                    break;
                case 'lessThanOrEqualTo':
                    $operation = Operation::LessOrEqual;
                    break;
            }
        }
        return $operation;
    }
}
