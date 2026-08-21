<?php

namespace Tests\Feature;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Examination;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify the audit trail written by observers and by the services that record stock and
 * settlement outcomes directly.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by the audited endpoints.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify creating a user records the acting administrator and the new account details.
     */
    public function test_creating_user_writes_activity_log(): void
    {
        $admin = $this->createUser('ADMIN');
        $doctorRole = Role::query()->where('name', 'DOCTOR')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyenvana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $doctorRole->id,
        ])->assertCreated();

        $created = User::query()->where('email', 'nguyenvana@example.com')->firstOrFail();
        $logs = $this->logsFor(ActivityLogSubject::USER, $created->id);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityLogAction::CREATED, $logs[0]->action);
        $this->assertSame($admin->id, $logs[0]->user_id);
        $this->assertSame('nguyenvana@example.com', $logs[0]->meta['after']['email']);
    }

    /**
     * Verify a password change is auditable without either hash reaching the meta payload.
     */
    public function test_user_password_is_never_written_to_activity_log_meta(): void
    {
        $target = $this->createUser('DOCTOR');

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->putJson("/api/users/{$target->id}", [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $updated = $this->logsFor(ActivityLogSubject::USER, $target->id)
            ->firstWhere('action', ActivityLogAction::UPDATED);

        $this->assertNotNull($updated);

        // The trail must show that the credential rotated while hiding both hashes.
        $this->assertSame('[REDACTED]', $updated->meta['before']['password']);
        $this->assertSame('[REDACTED]', $updated->meta['after']['password']);
        $this->assertStringNotContainsString('$2y$', json_encode($updated->meta));
    }

    /**
     * Verify an appointment transition records both sides of the change.
     */
    public function test_updating_appointment_status_logs_before_and_after(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertOk();

        $logs = $this->logsFor(ActivityLogSubject::APPOINTMENT, $appointment->id);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityLogAction::STATUS_CHANGED, $logs[0]->action);
        $this->assertSame(Appointment::STATUS_SCHEDULED, $logs[0]->meta['before']['status']);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $logs[0]->meta['after']['status']);
    }

    /**
     * Verify opening an examination is audited together with the appointment it completes.
     */
    public function test_creating_examination_writes_activity_log(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($appointment->doctor->user);

        $this->postJson('/api/examinations', [
            'appointment_id' => $appointment->id,
            'diagnosis' => 'Viêm họng cấp',
        ])->assertCreated();

        $examination = Examination::query()
            ->where('appointment_id', $appointment->id)
            ->firstOrFail();

        $examinationLogs = $this->logsFor(ActivityLogSubject::EXAMINATION, $examination->id);

        $this->assertCount(1, $examinationLogs);
        $this->assertSame(ActivityLogAction::CREATED, $examinationLogs[0]->action);
        $this->assertSame($appointment->id, $examinationLogs[0]->meta['after']['appointment_id']);

        // Opening an examination auto-completes its appointment, which must be traceable
        // from the appointment side rather than looking like a manual edit.
        $appointmentLogs = $this->logsFor(ActivityLogSubject::APPOINTMENT, $appointment->id);

        $this->assertCount(1, $appointmentLogs);
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointmentLogs[0]->meta['after']['status']);
    }

    /**
     * Verify prescribing a medicine records the stock movement with its business context.
     */
    public function test_prescription_item_deducts_stock_and_logs_before_after(): void
    {
        $examination = Examination::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [[
                'medicine_id' => $medicine->id,
                'quantity' => 3,
                'dosage' => '1 viên/lần, ngày 2 lần',
            ]],
        ])->assertCreated();

        $logs = $this->logsFor(ActivityLogSubject::MEDICINE, $medicine->id);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityLogAction::STOCK_DEDUCTED, $logs[0]->action);
        $this->assertSame(10, $logs[0]->meta['before']['stock']);
        $this->assertSame(7, $logs[0]->meta['after']['stock']);
        $this->assertSame(3, $logs[0]->meta['quantity']);

        // The prescription that caused the movement must be identifiable from the entry.
        $prescription = Prescription::query()
            ->where('examination_id', $examination->id)
            ->firstOrFail();

        $this->assertSame($prescription->id, $logs[0]->meta['prescription_id']);
    }

    /**
     * Verify removing a prescribed line returns the quantity to stock and audits it.
     */
    public function test_removing_prescription_item_logs_stock_restored(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 4,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->deleteJson("/api/prescriptions/{$prescription->id}/items/{$item->id}")->assertOk();

        $logs = $this->logsFor(ActivityLogSubject::MEDICINE, $medicine->id);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityLogAction::STOCK_RESTORED, $logs[0]->action);
        $this->assertSame(10, $logs[0]->meta['before']['stock']);
        $this->assertSame(14, $logs[0]->meta['after']['stock']);
    }

    /**
     * Verify a manual stock adjustment is audited separately from prescribing.
     */
    public function test_adjust_stock_logs_before_and_after(): void
    {
        $medicine = Medicine::factory()->create(['stock' => 20]);

        Sanctum::actingAs($this->createUser('PHARMACIST'));

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [
            'quantity' => 15,
        ])->assertOk();

        $logs = $this->logsFor(ActivityLogSubject::MEDICINE, $medicine->id);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityLogAction::STOCK_ADJUSTED, $logs[0]->action);
        $this->assertSame(20, $logs[0]->meta['before']['stock']);
        $this->assertSame(35, $logs[0]->meta['after']['stock']);
    }

    /**
     * Verify issuing an invoice writes exactly one entry despite the two-step code generation.
     */
    public function test_creating_invoice_writes_activity_log(): void
    {
        $examination = Examination::factory()->create();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
            'discount' => 20000,
        ])->assertCreated();

        $invoice = Invoice::query()
            ->where('examination_id', $examination->id)
            ->firstOrFail();

        $logs = $this->logsFor(ActivityLogSubject::INVOICE, $invoice->id);

        // InvoiceService saves a temporary invoice_code and rewrites it, so a watched
        // invoice_code would add a meaningless second entry here.
        $this->assertCount(1, $logs);
        $this->assertSame(ActivityLogAction::CREATED, $logs[0]->action);
        $this->assertEquals($invoice->total, $logs[0]->meta['after']['total']);
        $this->assertEquals(20000, $logs[0]->meta['after']['discount']);
    }

    /**
     * Verify a settled capture is audited with its provider reference and settles the invoice.
     */
    public function test_capturing_payment_logs_captured_action(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 100000,
            'status' => Invoice::STATUS_UNPAID,
        ]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-LOG-1',
        ]);

        $this->fakePayPalCapture('ORDER-LOG-1', 'COMPLETED', 'CAPTURE-LOG-1');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")->assertOk();

        $captured = $this->logsFor(ActivityLogSubject::PAYMENT, $payment->id)
            ->firstWhere('action', ActivityLogAction::CAPTURED);

        $this->assertNotNull($captured);
        $this->assertSame(Payment::STATUS_PENDING, $captured->meta['before']['status']);
        $this->assertSame(Payment::STATUS_COMPLETED, $captured->meta['after']['status']);

        // The provider reference stays readable: it is a reconciliation key, not a secret.
        $this->assertSame('CAPTURE-LOG-1', $captured->meta['provider_capture_id']);

        $settled = $this->logsFor(ActivityLogSubject::INVOICE, $invoice->id)
            ->firstWhere('action', ActivityLogAction::STATUS_CHANGED);

        $this->assertNotNull($settled);
        $this->assertSame(Invoice::STATUS_PAID, $settled->meta['after']['status']);
    }

    /**
     * Verify a capture PayPal did not complete is audited as a failure, leaving the invoice alone.
     */
    public function test_failed_capture_logs_capture_failed_action(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 100000,
            'status' => Invoice::STATUS_UNPAID,
        ]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-LOG-2',
        ]);

        $this->fakePayPalCapture('ORDER-LOG-2', 'VOIDED');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")->assertOk();

        $failed = $this->logsFor(ActivityLogSubject::PAYMENT, $payment->id)
            ->firstWhere('action', ActivityLogAction::CAPTURE_FAILED);

        $this->assertNotNull($failed);
        $this->assertSame(Payment::STATUS_PENDING, $failed->meta['before']['status']);
        $this->assertSame(Payment::STATUS_FAILED, $failed->meta['after']['status']);

        // A failed capture must not fabricate an invoice transition. The invoice still owns
        // the entry its own creation wrote, so only the transition may be absent.
        $this->assertNull(
            $this->logsFor(ActivityLogSubject::INVOICE, $invoice->id)
                ->firstWhere('action', ActivityLogAction::STATUS_CHANGED),
        );
    }

    /**
     * Verify a rejected business rule leaves no audit entry behind.
     *
     * This is the guarantee behind writing the entry with DB::afterCommit: the callback is
     * discarded on rollback, so the trail can never claim work that never happened.
     */
    public function test_failed_business_rule_rolls_back_activity_log(): void
    {
        $examination = Examination::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 1, 'is_active' => true]);

        Sanctum::actingAs($examination->doctor->user);

        // Discard the entries written while arranging the fixtures above.
        ActivityLog::query()->delete();

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [[
                'medicine_id' => $medicine->id,
                'quantity' => 5,
                'dosage' => '1 viên/lần',
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, ActivityLog::query()->count());
        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertDatabaseHas('medicines', ['id' => $medicine->id, 'stock' => 1]);
    }

    /**
     * Verify console-originated work is recorded without an acting user.
     */
    public function test_console_originated_change_writes_log_with_null_user_id(): void
    {
        $user = $this->createUser('DOCTOR');

        $log = ActivityLog::query()
            ->where('subject_type', ActivityLogSubject::USER)
            ->where('subject_id', $user->id)
            ->firstOrFail();

        $this->assertNull($log->user_id);
    }

    /**
     * Verify the stored alias resolves back to its model through the enforced morph map.
     */
    public function test_subject_relation_resolves_through_the_morph_map(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $appointment->update(['status' => Appointment::STATUS_CONFIRMED]);

        $log = $this->logsFor(ActivityLogSubject::APPOINTMENT, $appointment->id)->firstOrFail();

        $this->assertSame(ActivityLogSubject::APPOINTMENT, $log->subject_type);
        $this->assertTrue($log->subject->is($appointment));
    }

    /**
     * Fetch the audit entries recorded against one subject, oldest first.
     *
     * @return Collection<int, ActivityLog>
     */
    private function logsFor(string $subjectType, int $subjectId): Collection
    {
        return ActivityLog::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Fake a successful PayPal OAuth2 token exchange and Order capture.
     */
    private function fakePayPalCapture(
        string $orderId,
        string $status = 'COMPLETED',
        string $captureId = 'CAPTURE-1',
    ): void {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'FAKE_ACCESS_TOKEN',
            ], 200),
            "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => $status,
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [
                            ['id' => $captureId, 'status' => $status],
                        ],
                    ],
                ]],
            ], 201),
        ]);
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
