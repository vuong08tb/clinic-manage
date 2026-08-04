# Skill: Database (PostgreSQL + Migration + Seeder)

Playbook thiết kế và triển khai tầng dữ liệu cho Clinic API. Áp dụng cho task nhóm migration/seeder/constraint/transaction. Nguồn schema chuẩn: [README mục 8](../README.md#8-mô-hình-dữ-liệu-database).

---

## 1. Nguyên tắc chung

- **PostgreSQL 16**, kết nối qua service `db` trong Docker (`DB_HOST=db`).
- Ràng buộc đặt ở **tầng DB** (UNIQUE/CHECK/FK) — không chỉ validate ở PHP. DB là hàng rào cuối.
- Constraint đặt bằng migration; ENUM ưu tiên **CHECK constraint** (đơn giản, dễ đổi) thay vì native ENUM type.
- Bảng lịch sử tài chính/y tế (`examinations`, `invoices`, `payments`) dùng FK `restrict`.
- Soft delete cho `patients`, `medicines`.
- Mọi seeder/data-migration phải **idempotent** (chạy lại không lỗi trùng).

---

## 2. Thứ tự tạo bảng (theo phụ thuộc FK)

```
roles, permissions            (không phụ thuộc)
role_permissions              → roles, permissions
users                         → roles (role_id)
specialties
doctors                       → users, specialties
patients
medicines
appointments                  → patients, doctors
examinations                  → appointments, doctors, patients
prescriptions                 → examinations, doctors
prescription_items            → prescriptions, medicines
invoices                      → examinations
payments                      → invoices
activity_logs                 → users (nullable)
```

Đặt tên file migration theo thứ tự thời gian để `migrate` chạy đúng thứ tự.

---

## 3. Mẫu migration có constraint

### CHECK (ENUM giả lập)
```php
Schema::create('appointments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained()->restrictOnDelete();
    $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
    $table->timestampTz('scheduled_at');
    $table->string('status')->default('scheduled');
    $table->string('reason')->nullable();
    $table->timestamps();

    $table->index(['doctor_id', 'scheduled_at']);
    $table->index('patient_id');
    $table->index('status');
});

// CHECK constraint bằng raw SQL (Postgres)
DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check
    CHECK (status IN ('scheduled','confirmed','cancelled','completed'))");
```

### CHECK số + UNIQUE composite
```php
Schema::create('prescription_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
    $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
    $table->integer('quantity');
    $table->string('dosage');
    $table->text('usage_instruction')->nullable();
    $table->timestamps();

    $table->unique(['prescription_id', 'medicine_id']);
});
DB::statement("ALTER TABLE prescription_items ADD CONSTRAINT pi_qty_check CHECK (quantity > 0)");
```

### Soft delete
```php
$table->softDeletes();   // deleted_at
```

### JSONB
```php
$table->jsonb('meta')->nullable();   // activity_logs.meta
```

---

## 4. Ma trận constraint bắt buộc (checklist khi review DB)

| Loại | Áp dụng cho |
|---|---|
| UNIQUE | `roles.name`, `permissions.name`, `role_permissions(role_id,permission_id)`, `users.email`, `doctors.user_id`, `patients.code`, `medicines.code`, `examinations.appointment_id`, `prescriptions.examination_id`, `prescription_items(prescription_id,medicine_id)`, `invoices.examination_id`, `invoices.invoice_code` |
| CHECK enum | `patients.gender`, `appointments.status`, `invoices.status`, `payments.method`, `payments.status` |
| CHECK số | `medicines.stock >= 0`, `prescription_items.quantity > 0`, `payments.amount > 0` |
| Index (≥6) | `appointments(doctor_id,scheduled_at)`, `appointments(patient_id)`, `appointments(status)`, `invoices(status)`, `payments(invoice_id)`, `activity_logs(subject_type,subject_id)` |
| Soft delete | `patients`, `medicines` |
| FK restrict | `examinations`, `invoices`, `payments` (bảng cha của chúng) |
| JSONB | `activity_logs.meta` |

---

## 5. RBAC schema + seed

### Bảng
```php
// roles: id, name UNIQUE, display_name
// permissions: id, name UNIQUE (CONTROLLER.ACTION), display_name
// role_permissions: role_id, permission_id, UNIQUE(role_id, permission_id)
// users.role_id: FK roles, NOT NULL
```

### Data migration idempotent cho permission (yêu cầu đề)
Thêm permission mới **bằng migration**, không chỉ seeder:
```php
public function up(): void
{
    $perms = ['MEDICINES.LOWSTOCK' => 'Xem thuốc sắp hết'];
    foreach ($perms as $name => $display) {
        DB::table('permissions')->updateOrInsert(
            ['name' => $name],
            ['display_name' => $display, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
```

### Seeder map role → permission
Dùng ma trận trong [README mục 7](../README.md#7-rbac-global) làm nguồn sự thật:
```php
$matrix = [
    'RECEPTIONIST' => ['PATIENTS.FINDALL','PATIENTS.CREATE', /* ... */],
    'DOCTOR'       => [/* ... */],
    // ADMIN nhận toàn bộ permission
];
```

---

## 6. Sinh mã tự động (code, invoice_code)

- `patients.code`: `BN-` + số tăng dần (vd `BN-000123`). Sinh trong Service khi tạo, đảm bảo UNIQUE.
- `invoices.invoice_code`: `INV-` + ngày + sequence.
- Cân nhắc dùng Postgres `SEQUENCE` để tránh race khi sinh mã.

---

## 7. Transaction

Bọc `DB::transaction` cho nghiệp vụ đa bước (rollback nếu lỗi):

| Nghiệp vụ | Bước trong transaction |
|---|---|
| Tạo examination | insert examination + `appointment.status=completed` |
| Kê/sửa/xóa item | `lockForUpdate` medicine + kiểm/trừ/hoàn stock + thao tác item |
| Capture payment | update payment `completed` + cộng dồn + `invoice.status=paid` |

### Chống race-condition khi trừ kho
```php
DB::transaction(function () use ($items) {
    foreach ($items as $item) {
        $medicine = Medicine::where('id', $item['medicine_id'])
            ->lockForUpdate()->firstOrFail();
        if (! $medicine->is_active || $medicine->stock < $item['quantity']) {
            throw ValidationException::withMessages([
                'items' => ["Không đủ tồn kho cho thuốc {$medicine->code}"],
            ]); // → 422, rollback
        }
        $medicine->decrement('stock', $item['quantity']);
    }
});
```

---

## 8. Stats bằng aggregate (không đếm bằng PHP)

```php
$patients   = Patient::count();
$today      = Appointment::whereDate('scheduled_at', today())->count();
$revenue    = Invoice::where('status', 'paid')
                ->whereMonth('issued_at', now()->month)
                ->sum('total');
$lowStock   = Medicine::where('stock', '<=', 10)->count();
```

Điểm cộng: partial index (vd `CREATE INDEX ... WHERE status <> 'cancelled'`).

---

## 9. Checklist review DB trước PR

- [ ] Đủ 14 bảng + `activity_logs`.
- [ ] Tất cả UNIQUE/CHECK/index theo ma trận mục 4.
- [ ] FK restrict cho bảng tài chính/y tế.
- [ ] Soft delete `patients`, `medicines`.
- [ ] `activity_logs.meta` JSONB.
- [ ] Seeder/data-migration idempotent, `migrate:fresh --seed` sạch.
- [ ] Stats dùng aggregate.
- [ ] Transaction + `lockForUpdate` cho trừ kho.
