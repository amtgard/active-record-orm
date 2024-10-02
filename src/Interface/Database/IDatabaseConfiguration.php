<?php

namespace Amtgard\ActiveRecordOrm\Interface\Database;

interface IDatabaseConfiguration
{
    public static function fromEnvironment(): IDatabaseConfiguration;
    public function getConfig(): array;
}