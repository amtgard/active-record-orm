<?php

namespace Tests\Unit\Configuration\DataAccessPolicy;

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\CachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\RecordSet;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\PHPUnit\AmtgardTestCase;
use Phake;
use Psr\SimpleCache\CacheInterface;
use Tests\Unit\Cache\RecordingLogger;
use Tests\Util\Constants;

class CachedDataAccessPolicyLoggingTest extends AmtgardTestCase
{
    public function testLogsCodecFailureTreatedAsMiss(): void
    {
        $logger = new RecordingLogger();
        $database = Phake::mock(Database::class);
        $cache = Phake::mock(CacheInterface::class);
        $stored = null;
        Phake::when($database)->execute(Phake::anyParameters())->thenReturnCallback(function () {
            return $this->describeRecordSet();
        });
        Phake::when($cache)->set(Phake::anyParameters())->thenReturnCallback(function ($key, $value) use (&$stored) {
            $stored = $value;
            return true;
        });
        $policy = CachedDataAccessPolicy::builder()
            ->database($database)
            ->cache($cache)
            ->logger($logger)
            ->build();

        Phake::when($cache)->get(Phake::anyParameters())->thenReturn(null);
        $policy->applyTableSchemaPolicy('test_table');
        self::assertSame([], $logger->records);

        Phake::when($cache)->get(Phake::anyParameters())->thenReturn('{');
        $policy->applyTableSchemaPolicy('test_table');
        self::assertSame([
            [
                'level' => 'info',
                'message' => 'codec failure treated as a miss',
                'context' => [
                    'table' => 'test_table',
                    'schemaKey' => 'amtgard_orm_table_schema_test_table',
                    'reason' => 'schema document is not valid JSON',
                ],
            ],
        ], $logger->records);

        self::assertIsString($stored);
        Phake::when($cache)->get(Phake::anyParameters())->thenReturn($stored);
        $policy->applyTableSchemaPolicy('test_table');
        self::assertCount(1, $logger->records);
    }

    private function describeRecordSet(): RecordSet
    {
        $statement = Phake::mock(\PDOStatement::class);
        $recordSet = new RecordSet\PdoRecordSet($statement);
        $columns = json_decode(Constants::$DESCRIBE_TABLE_INTEG, true);
        $fetch = Phake::when($statement)->fetch();
        foreach ($columns as $column) {
            $fetch = $fetch->thenReturn($column);
        }
        $fetch->thenReturn(false);
        return $recordSet;
    }
}
