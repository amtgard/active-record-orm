<?php

namespace Amtgard\ActiveRecordOrm\Configuration\TablePolicy;

class FileTablePolicyConfiguration implements \Amtgard\ActiveRecordOrm\Interface\TablePolicyConfiguration
{

    public function getConfig(): array
    {
        return [
            'path' => $_ENV['TABLE_POLICY_PATH']
        ];
    }

    public static function fromEnvironment(): \Amtgard\ActiveRecordOrm\Interface\TablePolicyConfiguration
    {
        return new self();
    }
}