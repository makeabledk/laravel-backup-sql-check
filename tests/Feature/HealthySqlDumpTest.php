<?php

namespace Makeable\SqlCheck\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Makeable\SqlCheck\HealthySqlDump;
use Makeable\SqlCheck\Tests\TestCase;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class HealthySqlDumpTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('backup.monitor_backups.0', [
            'disks' => ['backup'],
            'health_checks' => [
                HealthySqlDump::class,
            ],
        ]);
    }

    /** @test */
    public function it_succeeds_when_file_is_healthy()
    {
        config()->set('backup.monitor_backups.0.name', 'mysite');

        Event::fake(HealthyBackupWasFound::class);
        Event::listen(UnhealthyBackupWasFound::class, function ($event) {
            throw $event->backupDestinationStatus->getHealthCheckFailure()->exception();
        });

        $this->artisan('backup:monitor')->assertExitCode(0);

        Event::assertDispatched(HealthyBackupWasFound::class);
    }

    /** @test */
    public function it_fails_when_backup_is_corrupt()
    {
        config()->set('backup.monitor_backups.0.name', 'unhealthy');

        Event::fake();

        $this->artisan('backup:monitor')->assertExitCode(0);

        Event::assertDispatched(UnhealthyBackupWasFound::class);
    }

    /** @test */
    public function it_fails_when_sql_process_exceed_the_timeout_limit()
    {
        config()->set('backup.monitor_backups.0.health_checks', [HealthySqlDump::class => 0.01]);
        config()->set('backup.monitor_backups.0.name', 'mysite');

        Event::fake(UnhealthyBackupWasFound::class);

        $this->artisan('backup:monitor')->assertExitCode(0);

        Event::assertDispatched(UnhealthyBackupWasFound::class);

        // Manually remove database, because error exited code before deleting it
        DB::select('DROP DATABASE `healthy-sql-dump--backup--mysite-2019-09-16-08-00-07`');
    }
}
