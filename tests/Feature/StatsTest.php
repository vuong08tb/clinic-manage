<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\StatsService;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify the clinic overview figures, their aggregate-only implementation, and RBAC access.
 */
class StatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by the stats endpoint.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify an administrator receives all four figures.
     */
    public function test_admin_can_view_stats(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Stats retrieved',
            ])
            ->assertJsonStructure([
                'data' => [
                    'total_patients',
                    'appointments_today',
                    'revenue_this_month',
                    'low_stock_medicines',
                ],
            ]);
    }

    /**
     * Verify the patient count ignores soft-deleted records.
     */
    public function test_total_patients_counts_only_non_deleted(): void
    {
        Patient::factory()->count(3)->create();
        Patient::factory()->create()->delete();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.total_patients', 3);
    }

    /**
     * Verify only appointments scheduled for the current day are counted.
     */
    public function test_appointments_today_excludes_other_days(): void
    {
        Appointment::factory()->create(['scheduled_at' => now()]);
        Appointment::factory()->create(['scheduled_at' => now()->addDays(2)]);
        Appointment::factory()->create(['scheduled_at' => now()->subDays(2)]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.appointments_today', 1);
    }

    /**
     * Verify revenue sums settled payments collected during the current month only.
     */
    public function test_revenue_sums_only_completed_payments_of_current_month(): void
    {
        Payment::factory()->completed()->create([
            'amount' => 120000,
            'paid_at' => now(),
        ]);

        // Settled, but collected before this month.
        Payment::factory()->completed()->create([
            'amount' => 500000,
            'paid_at' => now()->startOfMonth()->subDay(),
        ]);

        // Collected this month on paper, but never settled.
        Payment::factory()->create([
            'amount' => 900000,
            'status' => Payment::STATUS_PENDING,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.revenue_this_month', '120000.00');
    }

    /**
     * Verify partial settlements contribute to the month they were collected in.
     *
     * This is the behaviour that measuring on paid_at buys over summing paid invoices.
     */
    public function test_revenue_counts_partial_payments(): void
    {
        Payment::factory()->completed()->create(['amount' => 30000, 'paid_at' => now()]);
        Payment::factory()->completed()->create(['amount' => 45000, 'paid_at' => now()]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.revenue_this_month', '75000.00');
    }

    /**
     * Verify the low-stock figure follows the configured threshold.
     */
    public function test_low_stock_uses_configured_threshold(): void
    {
        config(['clinic.low_stock_threshold' => 3]);

        Medicine::factory()->create(['stock' => 3]);
        Medicine::factory()->create(['stock' => 4]);
        Medicine::factory()->create(['stock' => 50]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.low_stock_medicines', 1);
    }

    /**
     * Verify medicines that ran out entirely are counted as low stock.
     */
    public function test_low_stock_includes_out_of_stock_medicines(): void
    {
        config(['clinic.low_stock_threshold' => 5]);

        Medicine::factory()->create(['stock' => 0]);
        Medicine::factory()->create(['stock' => 5]);
        Medicine::factory()->create(['stock' => 6]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.low_stock_medicines', 2);
    }

    /**
     * Verify every figure is produced by an SQL aggregate rather than counted in PHP.
     *
     * The service is called directly so the log holds only its own queries, without the
     * authentication and permission lookups an HTTP request would add.
     */
    public function test_overview_uses_aggregate_queries_only(): void
    {
        Patient::factory()->count(3)->create();
        Medicine::factory()->count(2)->create();

        DB::enableQueryLog();

        app(StatsService::class)->overview();

        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertCount(4, $queries);

        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression(
                '/select\s+(count\(\*\)|sum\()/i',
                $query,
                "Query is not an aggregate: {$query}",
            );
            $this->assertStringNotContainsStringIgnoringCase('select *', $query);
        }
    }

    /**
     * Verify roles without STATS.SHOW cannot read the overview.
     */
    public function test_roles_without_permission_cannot_view_stats(): void
    {
        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST', 'CASHIER'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->getJson('/api/stats')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: STATS.SHOW');
        }
    }

    /**
     * Verify unauthenticated requests are rejected.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/stats')->assertUnauthorized();
    }

    /**
     * Create a user assigned to the requested seeded role.
     */
    private function createUser(string $role): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $role)->firstOrFail())
            ->create();
    }
}
