<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Examination;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PrescriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seed a full, presentable demo dataset: specialties, doctors (with login
 * accounts), one account per remaining role, patients, medicines, and a
 * sample appointment -> examination -> prescription -> invoice -> payment
 * chain covering every status the UI can display.
 *
 * Intended for a fresh database (`migrate:fresh --seed` or
 * `db:seed --class=DemoSeeder`). The fixed entities (roles, permissions,
 * admin, specialties, staff accounts) are safe to re-run — they upsert by a
 * natural key. The randomized entities (patients, medicines, appointments,
 * ...) are only meant to be seeded once; running this a second time against
 * a database that already has demo data may hit unique constraints.
 */
class DemoSeeder extends Seeder
{
    private const STAFF_PASSWORD_BY_ROLE = [
        'DOCTOR' => 'Doctor@123',
        'RECEPTIONIST' => 'Receptionist@123',
        'PHARMACIST' => 'Pharmacist@123',
        'CASHIER' => 'Cashier@123',
    ];

    public function run(): void
    {
        // RBAC and the initial admin are prerequisites — upserted, so calling
        // them again here is safe even if they already ran.
        $this->call([RoleSeeder::class, RbacSeeder::class, AdminSeeder::class]);

        DB::transaction(function (): void {
            $specialties = $this->seedSpecialties();
            $doctors = $this->seedDoctors($specialties);
            $this->seedSupportStaff();
            $patients = $this->seedPatients();
            $medicines = $this->seedMedicines();
            $this->seedClinicalFlow($doctors, $patients, $medicines);
        });

        $this->command?->info('Demo data seeded. Doctor login: doctor.an@clinic.test / Doctor@123 (see DemoSeeder for the full account list).');
    }

    /**
     * @return Collection<int, Specialty>
     */
    private function seedSpecialties(): Collection
    {
        $definitions = [
            ['name' => 'Nội tổng quát', 'description' => 'Khám và điều trị các bệnh lý nội khoa tổng quát.'],
            ['name' => 'Nhi khoa', 'description' => 'Chăm sóc sức khỏe trẻ em từ sơ sinh đến 16 tuổi.'],
            ['name' => 'Da liễu', 'description' => 'Chẩn đoán và điều trị các bệnh về da, tóc, móng.'],
            ['name' => 'Tim mạch', 'description' => 'Khám và điều trị các bệnh lý tim mạch.'],
            ['name' => 'Tai Mũi Họng', 'description' => 'Khám và điều trị các bệnh về tai, mũi, họng.'],
        ];

        return collect($definitions)->map(
            fn (array $data): Specialty => Specialty::query()->updateOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']],
            ),
        );
    }

    /**
     * @param  Collection<int, Specialty>  $specialties
     * @return Collection<int, Doctor>
     */
    private function seedDoctors(Collection $specialties): Collection
    {
        $bySpecialty = $specialties->keyBy('name');

        $definitions = [
            ['name' => 'BS. Nguyễn Văn An', 'email' => 'doctor.an@clinic.test', 'specialty' => 'Nội tổng quát', 'license' => 'VN-DOC-0001'],
            ['name' => 'BS. Trần Thị Bình', 'email' => 'doctor.binh@clinic.test', 'specialty' => 'Nhi khoa', 'license' => 'VN-DOC-0002'],
            ['name' => 'BS. Lê Minh Cường', 'email' => 'doctor.cuong@clinic.test', 'specialty' => 'Da liễu', 'license' => 'VN-DOC-0003'],
            ['name' => 'BS. Phạm Thị Dung', 'email' => 'doctor.dung@clinic.test', 'specialty' => 'Tim mạch', 'license' => 'VN-DOC-0004'],
            ['name' => 'BS. Hoàng Văn Em', 'email' => 'doctor.em@clinic.test', 'specialty' => 'Tim mạch', 'license' => 'VN-DOC-0005'],
            ['name' => 'BS. Vũ Thị Phương', 'email' => 'doctor.phuong@clinic.test', 'specialty' => 'Tai Mũi Họng', 'license' => 'VN-DOC-0006'],
        ];

        return collect($definitions)->map(function (array $data) use ($bySpecialty): Doctor {
            $user = $this->upsertStaffUser($data['name'], $data['email'], 'DOCTOR');

            return Doctor::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty_id' => $bySpecialty[$data['specialty']]->id,
                    'license_number' => $data['license'],
                    'bio' => "Bác sĩ {$data['specialty']} với nhiều năm kinh nghiệm lâm sàng.",
                ],
            );
        });
    }

    /**
     * One demo account per remaining operational role, so every role can be
     * clicked through end to end without creating accounts by hand first.
     */
    private function seedSupportStaff(): void
    {
        $this->upsertStaffUser('Đặng Thị Lễ Tân', 'receptionist@clinic.test', 'RECEPTIONIST');
        $this->upsertStaffUser('Bùi Văn Dược', 'pharmacist@clinic.test', 'PHARMACIST');
        $this->upsertStaffUser('Ngô Thị Thu Ngân', 'cashier@clinic.test', 'CASHIER');
    }

    private function upsertStaffUser(string $name, string $email, string $roleName): User
    {
        $roleId = Role::query()->where('name', $roleName)->value('id');

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $roleId,
                'name' => $name,
                'password' => Hash::make(self::STAFF_PASSWORD_BY_ROLE[$roleName]),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * @return Collection<int, Patient>
     */
    private function seedPatients(): Collection
    {
        return Patient::factory()
            ->count(20)
            ->sequence(fn (): array => ['full_name' => $this->vietnameseName()])
            ->create();
    }

    /**
     * @return Collection<int, Medicine>
     */
    private function seedMedicines(): Collection
    {
        $definitions = [
            ['name' => 'Paracetamol 500mg', 'unit' => 'Vỉ', 'price' => 15000],
            ['name' => 'Amoxicillin 500mg', 'unit' => 'Hộp', 'price' => 45000],
            ['name' => 'Vitamin C 1000mg', 'unit' => 'Hộp', 'price' => 60000],
            ['name' => 'Omeprazole 20mg', 'unit' => 'Vỉ', 'price' => 25000],
            ['name' => 'Loratadine 10mg', 'unit' => 'Vỉ', 'price' => 18000],
            ['name' => 'Ibuprofen 400mg', 'unit' => 'Vỉ', 'price' => 22000],
            ['name' => 'Cefixime 200mg', 'unit' => 'Hộp', 'price' => 85000],
            ['name' => 'Metformin 500mg', 'unit' => 'Vỉ', 'price' => 30000],
            ['name' => 'Amlodipine 5mg', 'unit' => 'Vỉ', 'price' => 28000],
            ['name' => 'Salbutamol xịt', 'unit' => 'Chai', 'price' => 95000],
            ['name' => 'Domperidone 10mg', 'unit' => 'Vỉ', 'price' => 20000],
            ['name' => 'Cetirizine 10mg', 'unit' => 'Vỉ', 'price' => 17000],
            ['name' => 'Diclofenac gel', 'unit' => 'Tuýp', 'price' => 35000],
            ['name' => 'Oresol', 'unit' => 'Gói', 'price' => 5000],
            ['name' => 'Multivitamin', 'unit' => 'Hộp', 'price' => 120000],
            ['name' => 'Berberin', 'unit' => 'Vỉ', 'price' => 8000],
            ['name' => 'Clorpheniramin 4mg', 'unit' => 'Vỉ', 'price' => 10000],
            ['name' => 'Vitamin B1-B6-B12', 'unit' => 'Hộp', 'price' => 40000],
        ];

        return collect($definitions)->map(
            fn (array $data, int $index): Medicine => Medicine::query()->updateOrCreate(
                ['code' => sprintf('MED-%03d', $index + 1)],
                [
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'price' => $data['price'],
                    'stock' => fake()->numberBetween(50, 300),
                    'is_active' => true,
                ],
            ),
        );
    }

    /**
     * Create appointments across every status the UI shows, and turn the
     * completed ones into full examination -> prescription -> invoice ->
     * payment chains so the whole clinical flow has real data to click
     * through immediately after seeding.
     *
     * @param  Collection<int, Doctor>  $doctors
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Medicine>  $medicines
     */
    private function seedClinicalFlow(
        Collection $doctors,
        Collection $patients,
        Collection $medicines,
    ): void {
        $invoiceService = app(InvoiceService::class);
        $prescriptionService = app(PrescriptionService::class);
        $patientPool = $patients->shuffle();
        $patientCursor = 0;
        $nextPatient = function () use ($patientPool, &$patientCursor): Patient {
            $patient = $patientPool[$patientCursor % $patientPool->count()];
            $patientCursor++;

            return $patient;
        };

        foreach ($doctors as $doctorIndex => $doctor) {
            $baseFuture = Carbon::now()->addDays(1)->setTime(8, 0)->addDays($doctorIndex);
            $basePast = Carbon::now()->subDays(30)->setTime(8, 0)->addDays($doctorIndex);

            // Upcoming, not yet examined.
            Appointment::query()->create([
                'patient_id' => $nextPatient()->id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => (clone $baseFuture)->addMinutes(30),
                'status' => Appointment::STATUS_SCHEDULED,
                'reason' => 'Khám định kỳ',
            ]);
            Appointment::query()->create([
                'patient_id' => $nextPatient()->id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => (clone $baseFuture)->addMinutes(60),
                'status' => Appointment::STATUS_CONFIRMED,
                'reason' => 'Tái khám',
            ]);
            Appointment::query()->create([
                'patient_id' => $nextPatient()->id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => (clone $baseFuture)->addMinutes(90),
                'status' => Appointment::STATUS_CANCELLED,
                'reason' => 'Bệnh nhân xin hủy',
            ]);

            // Two completed visits per doctor, each with a full downstream chain.
            for ($visit = 0; $visit < 2; $visit++) {
                $scheduledAt = (clone $basePast)->addMinutes($visit * 30);

                $appointment = Appointment::query()->create([
                    'patient_id' => $nextPatient()->id,
                    'doctor_id' => $doctor->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => Appointment::STATUS_COMPLETED,
                    'reason' => 'Khám bệnh',
                ]);

                $examination = Examination::query()->create([
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $doctor->id,
                    'patient_id' => $appointment->patient_id,
                    'diagnosis' => fake()->randomElement([
                        'Viêm họng cấp',
                        'Cảm cúm thông thường',
                        'Rối loạn tiêu hóa',
                        'Tăng huyết áp độ 1',
                        'Viêm da tiếp xúc',
                    ]),
                    'notes' => 'Tái khám sau 7 ngày nếu triệu chứng không thuyên giảm.',
                    'examined_at' => $scheduledAt->clone()->addMinutes(30),
                ]);

                // Every other visit gets a prescription with 1-3 medicine lines.
                $prescription = null;

                if ($visit === 0) {
                    $items = $medicines->shuffle()->take(fake()->numberBetween(1, 3))
                        ->map(fn (Medicine $medicine): array => [
                            'medicine_id' => $medicine->id,
                            'quantity' => fake()->numberBetween(1, 3),
                            'dosage' => fake()->randomElement([
                                '1 viên/lần, ngày 2 lần',
                                '2 viên/lần, ngày 3 lần',
                                '1 viên/lần, ngày 1 lần trước khi ngủ',
                            ]),
                            'usage_instruction' => 'Uống sau ăn.',
                        ])
                        ->values()
                        ->all();

                    $prescription = $prescriptionService->createFromExamination([
                        'examination_id' => $examination->id,
                        'notes' => 'Tái khám nếu còn triệu chứng.',
                        'items' => $items,
                    ]);
                }

                $invoice = $invoiceService->createFromExamination([
                    'examination_id' => $examination->id,
                    'discount' => $visit === 0 ? 10000 : 0,
                ]);

                $this->settleInvoiceForDemo($invoice, $visit);

                unset($prescription);
            }
        }
    }

    /**
     * Fake a terminal payment outcome without calling the real PayPal API —
     * seeders must stay offline-safe. Mirrors the end state PaymentService
     * would leave behind, not the live capture flow itself.
     */
    private function settleInvoiceForDemo(Invoice $invoice, int $visit): void
    {
        // First visit per doctor: fully paid. Second: left unpaid so the
        // "Tạo thanh toán" flow has something to demo against.
        if ($visit !== 0) {
            return;
        }

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
        ]);

        $invoice->update(['status' => Invoice::STATUS_PAID]);
    }

    private function vietnameseName(): string
    {
        $family = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Ngô', 'Dương', 'Lý'];
        $middleMale = ['Văn', 'Hữu', 'Đức', 'Minh', 'Quốc', 'Thành'];
        $middleFemale = ['Thị', 'Ngọc', 'Thu', 'Kim', 'Hồng', 'Mai'];
        $givenMale = ['An', 'Bình', 'Cường', 'Dũng', 'Phong', 'Giang', 'Hải', 'Huy', 'Khang', 'Long', 'Minh', 'Nam', 'Phát', 'Quân', 'Sơn', 'Tùng', 'Việt'];
        $givenFemale = ['Anh', 'Bích', 'Chi', 'Dung', 'Hoa', 'Lan', 'Linh', 'Mai', 'Nga', 'Oanh', 'Phương', 'Quyên', 'Thảo', 'Trang', 'Vân', 'Yến'];

        $isMale = fake()->boolean();
        $middle = fake()->randomElement($isMale ? $middleMale : $middleFemale);
        $given = fake()->randomElement($isMale ? $givenMale : $givenFemale);

        return trim(fake()->randomElement($family)." {$middle} {$given}");
    }
}
