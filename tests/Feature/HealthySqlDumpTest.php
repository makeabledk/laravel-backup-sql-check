<?php

namespace Makeable\SqlCheck\Tests\Feature;

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
                'name' => 'mysite',
                'disks' => ['backup'],
                'health_checks' => [
                    HealthySqlDump::class
                ]
            ]
        ]);
    }

    /** @test */
    public function it_succeeds_when_file_is_healthy()
    {
        Event::fake(HealthyBackupWasFound::class);
        Event::listen(UnhealthyBackupWasFound::class, function ($event) {
            throw $event->backupDestinationStatus->getHealthCheckFailure()->exception();
        });

        $this->artisan('backup:monitor')->assertExitCode(0);

        Event::assertDispatched(HealthyBackupWasFound::class);
    }
}