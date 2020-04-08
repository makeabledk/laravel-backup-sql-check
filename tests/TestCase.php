<?php

namespace Makeable\SqlCheck\Tests;

use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Backup\BackupServiceProvider;

class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            BackupServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // make sure, our .env file is loaded
        $app->useEnvironmentPath(__DIR__.'/..');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        config()->set('database.connections.mysql.host', env('DB_HOST'));
        config()->set('database.connections.mysql.username', env('DB_USERNAME'));
        config()->set('database.connections.mysql.password', env('DB_PASSWORD'));
        config()->set('database.connections.mysql.database', env('DB_DATABASE'));
        config()->set('database.connections.mysql.unix_socket', env('DB_SOCKET'));
        config()->set('filesystems.disks.backup', [
            'driver' => 'local',
            'root' => __DIR__.'/stubs',
        ]);

        parent::getEnvironmentSetUp($app);
    }
}
