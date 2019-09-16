<?php

namespace Makeable\SqlCheck\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
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

        config()->set('backup.monitor_backups', [
            [
                'disks' => ['backup'],
                'health_checks' => [
                    HealthySqlDump::class,
                ],
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

    // Needs better backup option
    /** @test */
    public function it_fails_when_backup_is_corrupt()
    {
        config()->set('backup.monitor_backups.0.name', 'unhealthy');

        Event::fake();

        $this->artisan('backup:monitor')->assertExitCode(0);

        Event::assertDispatched(UnhealthyBackupWasFound::class);
    }
}
