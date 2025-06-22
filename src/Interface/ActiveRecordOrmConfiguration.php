<?php

namespace Amtgard\ActiveRecordOrm\Interface;

interface TableConfiguration
{
    public static function fromEnvironment(): TableConfiguration;
    public function getConfig(): array;
}