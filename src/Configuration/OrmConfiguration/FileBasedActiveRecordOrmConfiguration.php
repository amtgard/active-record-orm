<?php

namespace Amtgard\ActiveRecordOrm\Configuration\OrmConfiguration;

use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;

/**
 * The purpose here is to have a configuration (or set of configuration files) that controls on a per-table basis,
 * whether a table's table schema is cached and whether and for how long a table's queries are cached.
 */
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