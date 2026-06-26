<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ClassBooking;
use App\Models\ClassPassPlan;
use App\Models\ClassPassSegment;
use App\Models\Customer;
use App\Models\ScheduledClass;
use App\Models\ScheduleSeries;
use App\Models\Trainer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_database_seeder_builds_sanitized_demo_catalog_without_operational_data(): void
    {
        $this->configureDemoCredentials();

        $this->seed(DatabaseSeeder::class);

        $account = Account::where('slug', 'charmpole')->firstOrFail();

        $this->assertSame(2, User::count());
        $this->assertSame(1, Account::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, ScheduledClass::count());
        $this->assertSame(0, ClassBooking::count());
        $this->assertSame(23, ClassPassPlan::whereBelongsTo($account)->count());
        $this->assertSame(5, ClassPassSegment::whereBelongsTo($account)->count());
        $this->assertSame(40, ScheduleSeries::whereBelongsTo($account)->count());
        $this->assertSame(7, Trainer::whereBelongsTo($account)->count());
    }

    public function test_database_seeder_requires_local_demo_credentials(): void
    {
        config([
            'demo.users.platform.email' => null,
            'demo.users.platform.password' => null,
            'demo.users.owner.email' => null,
            'demo.users.owner.password' => null,
        ]);

        $this->expectException(RuntimeException::class);

        $this->seed(DatabaseSeeder::class);
    }

    private function configureDemoCredentials(): void
    {
        config([
            'demo.users.platform.name' => 'Test Platform Admin',
            'demo.users.platform.email' => 'platform.test@example.test',
            'demo.users.platform.password' => 'local-platform-password',
            'demo.users.owner.name' => 'Test Studio Owner',
            'demo.users.owner.email' => 'owner.test@example.test',
            'demo.users.owner.password' => 'local-owner-password',
        ]);
    }
}
