<?php

namespace Amtgard\ActiveRecordOrm\Interface;

interface TablePolicyConfiguration
{
    public static function fromEnvironment(): TablePolicyConfiguration;
    public function getConfig(): array;
}