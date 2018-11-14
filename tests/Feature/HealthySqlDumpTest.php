<?php

namespace Makeable\SqlCheck\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Makeable\SqlCheck\HealthySqlDump;
use Makeable\SqlCheck\Tests\TestCase;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class HealthySqlDumpTest extends TestCase
{
    use RefreshDatabase;

    public function setUp()
    {
        parent::setUp();

        config()->set('backup.monitor_backups.0.health_checks', [HealthySqlDump::class]);
    }

    /** @test */
    public function it_succeeds_when_file_is_healthy()
    {
        Artisan::call('backup:run');

        dd('test');

        $this->expectsEvents(HealthyBackupWasFound::class);

        Artisan::call('backup:monitor');
    }

    /** @test */
    public function it_fails_when_no_tables_founds()
    {
        Schema::drop('users');
        Schema::drop('password_resets');
        Schema::drop('migrations');

        Artisan::call('backup:run');

        $this->expectsEvents(UnhealthyBackupWasFound::class);

        Artisan::call('backup:monitor');
    }
}
