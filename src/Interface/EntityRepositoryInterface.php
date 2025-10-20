<?php

namespace Amtgard\ActiveRecordOrm\Interface;

interface EntityRepositoryInterface
{
    static function getTableName();
    public static function getEntityClass();
}