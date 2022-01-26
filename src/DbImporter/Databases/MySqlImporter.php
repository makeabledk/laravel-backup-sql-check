<?php

namespace Makeable\SqlCheck\DbImporter\Databases;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Makeable\SqlCheck\DbImporter\DbImporter;
use Makeable\SqlCheck\DbImporter\Exceptions\DatabaseImportFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class MySqlImporter extends DbImporter
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
     * @param $databaseName
     * @param $dumpFile
     * @param $timeout
     * @return mixed|void
     *
     * @throws DatabaseImportFailed
     */
    public function createDatabaseFromFile($databaseName, $dumpFile, $timeout)
    {
        $credentials = $this->configureCredentials($_ = tmpfile());

        $this->createDatabase($databaseName, $dumpFile, $credentials, $timeout);

        $this->checkIfImportWasSuccessful($databaseName, $credentials);
    }

    /**
     * @param $databaseName
     * @return mixed|void
     *
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
     *
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
     * @param $timeout
     *
     * @throws DatabaseImportFailed
     */
    protected function createDatabase($databaseName, $file, $credentials, $timeout)
    {
        $this->runMysqlCommand([
            "CREATE DATABASE `{$databaseName}`".
                ($this->dbCharset ? " CHARACTER SET {$this->dbCharset}" : '').
                ($this->dbCollation ? " COLLATE {$this->dbCollation}" : ''),
            "USE `{$databaseName}`",
            'SET autocommit=0',
            "SOURCE {$file}",
            'COMMIT',
        ], $credentials, $timeout);
    }

    /**
     * @param $databaseName
     * @param $credentials
     *
     * @throws DatabaseImportFailed
     */
    protected function checkIfImportWasSuccessful($databaseName, $credentials)
    {
        $rawTables = $this->runMysqlCommand(["USE `{$databaseName}`", 'SHOW TABLES'], $credentials)->getOutput();

        if (! Str::startsWith($rawTables, 'Tables_in') || ! count(explode(PHP_EOL, $rawTables)) > 1) {
            throw DatabaseImportFailed::databaseWasEmpty($rawTables);
        }
    }

    /**
     * @param $mysqlCommands
     * @param $credentialsFile
     * @param $timeout
     * @return Process
     *
     * @throws DatabaseImportFailed
     */
    protected function runMysqlCommand($mysqlCommands, $credentialsFile, $timeout = 60)
    {
        $quote = $this->determineQuote();

        $command = [
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            "--defaults-extra-file='{$credentialsFile}'",
        ];

        if ($this->socket !== '') {
            $command[] = "--socket='{$this->socket}'";
        }

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        $command[] = "-e {$quote}".implode('; ', Arr::wrap($mysqlCommands))."{$quote}";

        $command = implode(' ', $command);

        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout($timeout);
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw DatabaseImportFailed::timeoutExceeded($timeout);
        }

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
