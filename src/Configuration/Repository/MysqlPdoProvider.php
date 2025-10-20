<?php

namespace Amtgard\ActiveRecordOrm\Configuration\Repository;

use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\Schema;
use Amtgard\Traits\Builder\Builder;
use DateTime;
use PDO;

class MysqlPdoProvider implements PdoProviderInterface
{
    use Builder;

    private DatabaseConfiguration $configuration;

    public static function fromConfiguration(DatabaseConfiguration $configuration): MysqlPdoProvider
    {
        return MysqlPdoProvider::builder()->configuration($configuration)->build();
    }

    public function getPdo(): PDO
    {
        $config = $this->configuration->getConfig();
        $host = $config[DatabaseConfiguration::$ENV_HOST_KEY];
        $port = $config[DatabaseConfiguration::$ENV_PORT_KEY];
        $dbname = $config[DatabaseConfiguration::$ENV_NAME_KEY];
        $user = $config[DatabaseConfiguration::$ENV_USER_KEY];
        $password = $config[DatabaseConfiguration::$ENV_PASSWORD_KEY];
        $errMode = $config[DatabaseConfiguration::$ENV_ERRMODE_KEY];
        $options = $config[DatabaseConfiguration::$ENV_OPTIONS_KEY];

        $dbh = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $password, $options);
        $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $dbh;
    }

    public function getDatabaseName(): string
    {
        return $this->configuration->getConfig()['dbname'];
    }

    public function convertToProviderParams(Schema $schema, array $params): array
    {
        $convertedParams = [];
        foreach ($params as $name => $value) {
            if ($schema->hasField($name)) {
                $field = $schema->getField($name);
                $type = $field->getType();
                switch (gettype($value)) {
                    case "object":
                        if ($value instanceof DateTime && $type == FieldType::DATETIME) {
                            $convertedParams[$name] = $value->format('Y-m-d H:i:s');
                        }
                        if ($value instanceof DateTime && $type == FieldType::INTEGER) {
                            $convertedParams[$name] = $value->format('U');
                        }
                        if ($value instanceof Entity && $type == FieldType::INTEGER) {
                            $convertedParams[$name] = $value->getPrimaryKey()->getValue();
                        }
                        break;
                    default:
                        $convertedParams[$name] = $value;
                }
            }
        }
        return $convertedParams;
    }
}