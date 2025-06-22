<?php

namespace Amtgard\ActiveRecordOrm\Interface;

interface ActiveRecordOrmConfiguration
{
    public static function fromEnvironment(): ActiveRecordOrmConfiguration;
    public function getConfig(): array;
}