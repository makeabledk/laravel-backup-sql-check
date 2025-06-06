<?php

namespace Makeable\SqlCheck\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Makeable\SqlCheck\DiskSpace;
use Makeable\SqlCheck\HealthySqlDump;
use Makeable\SqlCheck\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class HealthySqlDumpTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config()->set('backup.monitor_backups.0', [
            'disks' => ['backup'],
            'name' => 'mysite',
            'health_checks' => [
                HealthySqlDump::class,
            ],
        ]);
    }

    #[Test]
    public function it_succeeds_when_file_is_healthy()
    {
        Event::fake(HealthyBackupWasFound::class);
        Event::listen(UnhealthyBackupWasFound::class, function ($event) {
            throw $event->backupDestinationStatus->getHealthCheckFailure()->exception();
        });

        $exitCode = Artisan::call('backup:monitor');
        $this->assertEquals(0, $exitCode);
        Event::assertDispatched(HealthyBackupWasFound::class);
    }

    #[Test]
    public function it_fails_when_backup_is_corrupt()
    {
        config()->set('backup.monitor_backups.0.name', 'unhealthy');

        Event::fake();

        Artisan::call('backup:monitor');
        $this->assertStringContainsString('are considered unhealthy!', Artisan::output());
        Event::assertDispatched(UnhealthyBackupWasFound::class);
    }

    #[Test]
    public function it_fails_when_sql_process_exceed_the_timeout_limit()
    {
        config()->set('backup.monitor_backups.0.health_checks', [HealthySqlDump::class => 0.01]);

        Event::fake(UnhealthyBackupWasFound::class);

        Artisan::call('backup:monitor');
        $this->assertStringContainsString('are considered unhealthy!', Artisan::output());
        Event::assertDispatched(UnhealthyBackupWasFound::class);

        // Manually remove database, because error exited code before deleting it
        DB::statement('DROP DATABASE `healthy-sql-dump--backup--mysite-2019-09-16-08-00-07`');
    }

    #[Test]
    public function it_fails_on_insufficient_disk_space()
    {
        app()->bind(DiskSpace::class, function () {
            return new class
            {
                public function available()
                {
                    return 0;
                }
            };
        });

        Event::fake(UnhealthyBackupWasFound::class);

        Artisan::call('backup:monitor');

        Event::assertDispatched(UnhealthyBackupWasFound::class);

        $this->assertStringContainsString('unhealthy!', Artisan::output());
    }
}
