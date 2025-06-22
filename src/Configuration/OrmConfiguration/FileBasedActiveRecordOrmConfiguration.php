<?php

namespace Amtgard\ActiveRecordOrm\Configuration\TablePolicy;

use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;

class FileBasedActiveRecordOrmConfiguration implements ActiveRecordOrmConfiguration
{

    public function getConfig(): array
    {
        return [
            'path' => $_ENV['TABLE_POLICY_PATH']
        ];
    }

    public static function fromEnvironment(): \Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration
    {
        return new self();
    }
}