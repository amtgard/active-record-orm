<?php

namespace Tests\Unit\Configuration\OrmConfiguration;

use Amtgard\ActiveRecordOrm\Configuration\OrmConfiguration\FileBasedActiveRecordOrmConfiguration;
use Amtgard\ActiveRecordOrm\Interface\ActiveRecordOrmConfiguration;
use Amtgard\PHPUnit\AmtgardTestCase;

class FileBasedActiveRecordOrmConfigurationTest extends AmtgardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment variable
        $_ENV['TABLE_POLICY_PATH'] = '/path/to/table/policy/config';
    }

    public function testFromEnvironment_createsInstanceSuccessfully(): void
    {
        $config = FileBasedActiveRecordOrmConfiguration::fromEnvironment();
        
        self::assertInstanceOf(FileBasedActiveRecordOrmConfiguration::class, $config);
        self::assertInstanceOf(ActiveRecordOrmConfiguration::class, $config);
    }

    public function testGetConfig_returnsCorrectConfigurationArray(): void
    {
        $config = FileBasedActiveRecordOrmConfiguration::fromEnvironment();
        $configArray = $config->getConfig();
        
        self::assertArrayHasKey('path', $configArray);
        self::assertEquals('/path/to/table/policy/config', $configArray['path']);
    }
} 