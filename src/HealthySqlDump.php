<?php

namespace Makeable\SqlCheck;

use Illuminate\Support\Facades\Cache;
use Makeable\SqlCheck\DbImporter\DbImporterFactory;
use Makeable\SqlCheck\DbImporter\Exceptions\DatabaseImportFailed;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthCheck;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Finder\Finder;
use ZipArchive;

class HealthySqlDump extends HealthCheck
{
    /**
     * @param BackupDestination $backupDestination
     * @throws \Spatie\Backup\Exceptions\InvalidHealthCheck
     * @throws \Throwable
     */
    public function checkHealth(BackupDestination $backupDestination)
    {
        $this->failsOnEmpty($newestBackup = $backupDestination->backups()->newest());

        if (Cache::has($key = static::class . '--' . $newestBackup->path() . '--result')) {
            throw_if(Cache::get($key) === false, DatabaseImportFailed::previouslyFailed());

            return;
        }

        Cache::put($key, false, now()->addWeek());

        $this->performCheck($backupDestination, $newestBackup);

        Cache::put($key, true, now()->addWeek());
    }

    /**
     * @param BackupDestination $backupDestination
     * @param Backup $backup
     * @throws \Spatie\Backup\Exceptions\InvalidHealthCheck
     */
    public function performCheck(BackupDestination $backupDestination, Backup $backup)
    {
        $tempDirectory = $this->setupTempDirectory($backupDestination, $backup);
        $extracted = $this->downloadAndExtract($backup, $tempDirectory);

        $this->failsOnEmpty($dumps = $this->getDumps($extracted));

        foreach ($dumps as $dump) {
            $dumper = DbImporterFactory::createFromConnection($this->getConnectionFromFile($dump));
            $dumper->createDatabaseFromFile($name = basename(dirname($extracted)), $dump);
            $dumper->dropDatabase($name);
        }

        $tempDirectory->delete();
    }

    /**
     * @param BackupDestination $destination
     * @param Backup $backup
     * @return TemporaryDirectory
     */
    protected function setupTempDirectory(BackupDestination $destination, Backup $backup)
    {
        return (new TemporaryDirectory(config('backup.backup.temporary_directory')))
            ->name('healthy-sql-dump--' . $destination->diskName() . '--' . pathinfo($backup->path(), PATHINFO_FILENAME))
            ->force()
            ->create()
            ->empty();
    }

    /**
     * @param Backup $backup
     * @param TemporaryDirectory $temporaryDirectory
     * @return string
     * @throws \Spatie\Backup\Exceptions\InvalidHealthCheck
     */
    protected function downloadAndExtract(Backup $backup, TemporaryDirectory $temporaryDirectory)
    {
        $destination = $temporaryDirectory->path(pathinfo($backup->path(), PATHINFO_BASENAME));
        $stream = fopen($destination, 'w+b');

        if (stream_copy_to_stream($backup->stream(), $stream) === false || !fclose($stream)) {
            $this->fail('Something went wrong while downloading backup to temporary storage!');
        }

        return $this->extract($destination);
    }

    /**
     * @param $source
     * @return string
     */
    protected function extract($source)
    {
        $zip = new ZipArchive();
        $zip->open($source);
        $zip->extractTo($destination = str_before($source, '.zip'));
        $zip->close();

        $this->failIf(!is_dir($destination), 'Something went wrong while extracting zip file!');

        return $destination;
    }

    /**
     * @param $backup
     * @throws \Spatie\Backup\Exceptions\InvalidHealthCheck
     */
    protected function failsOnEmpty($backup)
    {
        if (empty($backup)) {
            $this->fail('No SQL backups found');
        }
    }

    /**
     * @param $backup
     * @throws \Spatie\Backup\Exceptions\InvalidHealthCheck
     */
    protected function failsOnNoSql($backup)
    {
        if ($backup->count) {
            $this->fail('No SQL backups found');
        }
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @return string
     */
    protected function getConnectionFromFile(\SplFileInfo $fileInfo)
    {
        return str_before($fileInfo->getFilename(), '-');
    }

    /**
     * @param $backupFolder
     * @return Finder
     */
    protected function getDumps($backupFolder)
    {
        return (new Finder)
            ->files()
            ->in($backupFolder . DIRECTORY_SEPARATOR . 'db-dumps')
            ->name('mysql-*');
    }
}
