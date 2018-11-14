<?php

namespace Makeable\SqlCheck\Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Backup\BackupServiceProvider;

class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        putenv('APP_ENV=testing');
        putenv('APP_NAME=sql-check');
        putenv('DB_CONNECTION=mysql');

        $app = require __DIR__.'/../vendor/laravel/laravel/bootstrap/app.php';
        $app->useEnvironmentPath(__DIR__.'/..');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app->register(BackupServiceProvider::class);

        config()->set('backup.backup.source.files.include', []);
        config()->set('backup.backup.source.databases', ['mysql']);
        config()->set('backup.backup.destination.disks', ['local']);
        config()->set('backup.backup.temporary_directory', __DIR__.'/temp/backup-temp');
        config()->set('filesystems.disks.local.root', __DIR__.'/temp/backups');

//        // MySQL 5.6 compatibility
        Schema::defaultStringLength(191);

        return $app;
    }
}
