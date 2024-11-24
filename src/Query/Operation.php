<?php

namespace Amtgard\ActiveRecordOrm\Query;

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
