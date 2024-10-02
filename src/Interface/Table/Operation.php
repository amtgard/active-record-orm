<?php

namespace Amtgard\ActiveRecordOrm\Interface\Table;

enum Operation
{
    case Equals;
    case Set;
    case Like;
    case NotLike;
    case Greater;
    case Less;
    case GreaterOrEqual;
    case LessOrEqual;
    case In;
    case NotIn;
}
