<?php

namespace Makeable\SqlCheck\DbImporter\Databases;

use Illuminate\Support\Arr;
use Makeable\SqlCheck\DbImporter\DbImporter;
use Makeable\SqlCheck\DbImporter\Exceptions\DatabaseImportFailed;
use Symfony\Component\Process\Process;

class MysqlImporter extends DbImporter
{
    /**
     * @var string
     */
    protected $dbCharset;

    /**
     * @var string
     */
    protected $dbCollation;

    /**
     * @param string $databaseName
     * @param string $dumpFile
     * @throws DatabaseImportFailed
     */
    public function createDatabaseFromFile($databaseName, $dumpFile)
    {
        $credentials = $this->configureCredentials($_ = tmpfile());

        $this->createDatabase($databaseName, $dumpFile, $credentials);

        $this->checkIfImportWasSuccessful($databaseName, $credentials);
    }

    /**
     * @param string $databaseName
     * @throws DatabaseImportFailed
     */
    public function dropDatabase($databaseName)
    {
        $credentials = $this->configureCredentials($_ = tmpfile());

        $this->runMysqlCommand("DROP DATABASE `{$databaseName}`", $credentials);
    }

    /**
     * @param $dbCharset
     * @return $this
     */
    public function setDbCharset($dbCharset)
    {
        $this->dbCharset = $dbCharset;

        return $this;
    }

    /**
     * @param $dbCollation
     * @return $this
     */
    public function setDbCollation($dbCollation)
    {
        $this->dbCollation = $dbCollation;

        return $this;
    }

    /**
     * @param $file
     * @return mixed
     * @throws DatabaseImportFailed
     */
    protected function configureCredentials($file)
    {
        $this->guardAgainstIncompleteCredentials();

        fwrite($file, implode(PHP_EOL, [
            '[client]',
            "user = '{$this->userName}'",
            "password = '{$this->password}'",
            "host = '{$this->host}'",
            "port = '{$this->port}'",
        ]));

        return stream_get_meta_data($file)['uri'];
    }

    /**
     * @param $databaseName
     * @param $file
     * @param $credentials
     * @throws DatabaseImportFailed
     */
    protected function createDatabase($databaseName, $file, $credentials)
    {
        $this->runMysqlCommand([
            "CREATE DATABASE `{$databaseName}`".
                ($this->dbCharset ? " CHARACTER SET {$this->dbCharset}" : "").
                ($this->dbCollation ? " COLLATE {$this->dbCollation}" : ""),
            "USE `{$databaseName}`",
            "SET autocommit=0",
            "SOURCE {$file}",
            "COMMIT"
        ], $credentials);
    }

    /**
     * @param $databaseName
     * @param $credentials
     * @throws DatabaseImportFailed
     */
    protected function checkIfImportWasSuccessful($databaseName, $credentials)
    {
        $rawTables = $this->runMysqlCommand(["USE `{$databaseName}`", "SHOW TABLES"], $credentials)->getOutput();

        if (! starts_with($rawTables, 'Tables_in') || ! count(explode(PHP_EOL, $rawTables)) > 1) {
            throw DatabaseImportFailed::databaseWasEmpty($rawTables);
        }
    }

    /**
     * @param $mysqlCommands
     * @param string $credentialsFile
     * @return Process
     * @throws DatabaseImportFailed
     */
    protected function runMysqlCommand($mysqlCommands, $credentialsFile)
    {
        $quote = $this->determineQuote();

        $command = [
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            "--defaults-extra-file=\"{$credentialsFile}\"",
        ];

        if ($this->socket !== '') {
            $command[] = "--socket={$this->socket}";
        }

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        $command[] = "-e {$quote}".implode('; ', Arr::wrap($mysqlCommands))."{$quote}";

        $process = new Process(implode(' ', $command));
        $process->run();

        if (! $process->isSuccessful()) {
            throw DatabaseImportFailed::processDidNotEndSuccessfully($process);
        }

        return $process;
    }

    /**
     * @return string
     */
    protected function determineQuote()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '"' : "'";
    }

    /**
     * @throws DatabaseImportFailed
     */
    protected function guardAgainstIncompleteCredentials()
    {
        foreach (['userName', 'dbName', 'host'] as $requiredProperty) {
            if (strlen($this->$requiredProperty) === 0) {
                throw DatabaseImportFailed::missingCredentials($requiredProperty);
            }
        }
    }
}