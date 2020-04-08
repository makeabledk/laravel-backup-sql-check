<?php

namespace Makeable\SqlCheck\DbImporter;

use Exception;
use Illuminate\Support\Arr;
use Makeable\SqlCheck\DbImporter\Databases\MySqlImporter;

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
            $dbConfig = Arr::except(array_merge($dbConfig, $dbConfig['write']), ['read', 'write']);
        }

        $dbImporter = static::forDriver($dbConfig['driver'])
            ->setHost(Arr::first(Arr::wrap($dbConfig['host'] ?? '')))
            ->setDbName($dbConfig['database'])
            ->setUserName($dbConfig['username'] ?? '')
            ->setPassword($dbConfig['password'] ?? '')
            ->setSocket($dbConfig['unix_socket'] ?? '');

        if ($dbImporter instanceof MysqlImporter) {
            $dbImporter->setDbCharset(Arr::get($dbConfig, 'charset'));
            $dbImporter->setDbCollation(Arr::get($dbConfig, 'collation'));
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
