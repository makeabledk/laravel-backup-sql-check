<?php

namespace Makeable\SqlCheck\DbImporter;

use Exception;
use Makeable\SqlCheck\DbImporter\Databases\MysqlImporter;

class DbImporterFactory
{
    /**
     * @param $dbConnectionName
     * @return DbImporter
     * @throws Exception
     */
    public static function createFromConnection($dbConnectionName)
    {
        $dbConfig = config("database.connections.{$dbConnectionName}");

        // If read / write connections are given, use the write
        if (isset($dbConfig['write'])) {
            $dbConfig = array_except(array_merge($dbConfig, $dbConfig['write']), ['read', 'write']);
        }

        $dbImporter = static::forDriver($dbConfig['driver'])
            ->setHost(array_first(array_wrap($dbConfig['host'] ?? '')))
            ->setDbName($dbConfig['database'])
            ->setUserName($dbConfig['username'] ?? '')
            ->setPassword($dbConfig['password'] ?? '');

        if ($dbImporter instanceof MysqlImporter) {
            $dbImporter->setDbCharset(array_get($dbConfig, 'charset'));
            $dbImporter->setDbCollation(array_get($dbConfig, 'collation'));
        }

        if (isset($dbConfig['port'])) {
            $dbImporter = $dbImporter->setPort($dbConfig['port']);
        }

        return $dbImporter;
    }

    /**
     * @param $dbDriver
     * @return MysqlImporter
     * @throws Exception
     */
    protected static function forDriver($dbDriver)
    {
        $driver = strtolower($dbDriver);

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return new MysqlImporter();
        }

        throw new Exception('Cannot import '.$driver.' files. Database not supported.');
    }
}