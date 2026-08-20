<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Examination;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify the demo seeder runs cleanly and produces a coherent, browsable dataset.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_runs_and_produces_a_coherent_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(5, Specialty::query()->count());
        $this->assertSame(6, Doctor::query()->count());
        $this->assertSame(20, Patient::query()->count());
        $this->assertSame(18, Medicine::query()->count());

        // One admin (from AdminSeeder) + 6 doctors + 3 support staff.
        $this->assertSame(10, User::query()->count());

        $this->assertTrue(User::query()->where('email', 'admin@clinic.test')->exists());
        $this->assertTrue(User::query()->where('email', 'doctor@clinic.test')->exists());
        $this->assertTrue(User::query()->where('email', 'receptionist@clinic.test')->exists());
        $this->assertTrue(User::query()->where('email', 'pharmacist@clinic.test')->exists());
        $this->assertTrue(User::query()->where('email', 'cashier@clinic.test')->exists());

        // 5 appointments per doctor (3 upcoming + 2 completed) x 6 doctors.
        $this->assertSame(30, Appointment::query()->count());
        $this->assertSame(6, Appointment::query()->where('status', Appointment::STATUS_SCHEDULED)->count());
        $this->assertSame(6, Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->count());
        $this->assertSame(6, Appointment::query()->where('status', Appointment::STATUS_CANCELLED)->count());
        $this->assertSame(12, Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->count());

        // One examination per completed appointment.
        $this->assertSame(12, Examination::query()->count());

        // One prescription per doctor's first completed visit.
        $this->assertSame(6, Prescription::query()->count());
        $this->assertGreaterThan(0, PrescriptionItem::query()->count());

        // One invoice per examination; half paid, half left unpaid.
        $this->assertSame(12, Invoice::query()->count());
        $this->assertSame(6, Invoice::query()->where('status', Invoice::STATUS_PAID)->count());
        $this->assertSame(6, Invoice::query()->where('status', Invoice::STATUS_UNPAID)->count());
        $this->assertSame(6, Payment::query()->where('status', Payment::STATUS_COMPLETED)->count());

        // Every paid invoice's completed payment total must match the invoice total —
        // this is what proves createFromExamination()'s real money math ran correctly.
        Invoice::query()->where('status', Invoice::STATUS_PAID)->get()->each(function (Invoice $invoice): void {
            $paid = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount');

            $this->assertSame((float) $invoice->total, (float) $paid);
        });

        // The seeded doctor account can actually log in and see their own data.
        $doctorUser = User::query()->where('email', 'doctor@clinic.test')->firstOrFail();
        Sanctum::actingAs($doctorUser);

        $this->getJson('/api/appointments')->assertOk();
        $this->getJson('/api/examinations')->assertOk();
    }

    public function test_demo_seeder_is_safe_to_run_twice_for_fixed_entities(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        // Specialties, doctors, and staff accounts upsert by natural key, so a
        // second run must not duplicate them even though patients/appointments do.
        $this->assertSame(5, Specialty::query()->count());
        $this->assertSame(6, Doctor::query()->count());
        $this->assertSame(10, User::query()->count());
    }
}
