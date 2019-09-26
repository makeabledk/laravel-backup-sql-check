<?php

namespace Makeable\SqlCheck\DbImporter\Exceptions;

use Exception;
use Symfony\Component\Process\Process;

class DatabaseImportFailed extends Exception
{
    /**
     * @param $name
     * @return DatabaseImportFailed
     */
    public static function missingCredentials($name)
    {
        return new static("Parameter `{$name}` cannot be empty.");
    }

    /**
     * @param \Symfony\Component\Process\Process $process
     * @return DatabaseImportFailed
     */
    public static function processDidNotEndSuccessfully(Process $process)
    {
        return new static("The import process failed with exit code {$process->getExitCode()} : {$process->getExitCodeText()} : {$process->getErrorOutput()}");
    }

    /**
     * @return DatabaseImportFailed
     */
    public static function databaseWasNotCreated()
    {
        return new static('The database could not be created');
    }

    /**
     * @param $showTablesOutput
     * @return DatabaseImportFailed
     */
    public static function databaseWasEmpty($showTablesOutput)
    {
        return new static('The created database does not contain any tables. Output of SHOW TABLES was '.$showTablesOutput);
    }

    /**
     * @return DatabaseImportFailed
     */
    public static function previouslyFailed()
    {
        return new static('The import process has failed on a previous check. Please check the sql file manually. You may reset this warning by performing a new backup or clearing cache.');
    }

    /**
     * @param $timeout
     * @return DatabaseImportFailed
     */
    public static function timeoutExceeded($timeout)
    {
        return new static('The sql process exceeded the timeout limit of '.$timeout.' seconds. This timeout may be tweaked in your config/backup.php file.');
    }

    /**
     * @return DatabaseImportFailed
     */
    public static function notEnoughDiskSpace()
    {
        return new static("There isn't enough disk space to perform a sql check. Current disk space is " . round(disk_free_space("/") / 1024 / 1024 ) . " MB");
    }
}
