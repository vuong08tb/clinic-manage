# CRUD Appointments — Đã review và triển khai

## 1. Trạng thái tài liệu

- Trạng thái: **ĐÃ REVIEW / ĐÃ TRIỂN KHAI**.
- Nguồn hướng dẫn bắt buộc: `skills/backend.md`.
- Kiến trúc áp dụng: **Controller + Service**.
- Phạm vi: CRUD cơ bản cho Appointment theo nội dung đã được review.
- Kết quả xác minh: `AppointmentTest` pass 12 test/144 assertions; toàn bộ suite pass 75
  test/552 assertions.

Luồng xử lý dự kiến:

```text
Route
  -> auth:sanctum
  -> EnsurePermission (APPOINTMENTS.*)
  -> Form Request
  -> AppointmentController
  -> AppointmentService
  -> Appointment model
  -> AppointmentResource
  -> ApiResponse
```

## 2. Mục tiêu nghiệp vụ

Triển khai các thao tác Appointment sau:

- Xem danh sách lịch khám có phân trang.
- Tìm kiếm bằng `q` và lọc danh sách theo `doctor_id`, `patient_id`, `status` và `date`.
- Xem chi tiết một lịch khám.
- Tạo lịch gắn với Patient, Doctor và thời điểm khám.
- Lịch mới luôn có trạng thái `scheduled`; client không được tự đặt trạng thái.
- Kiểm tra `patient_id` và `doctor_id` tồn tại bằng validation `exists`.
- Chỉ cập nhật lịch khi trạng thái hiện tại là `scheduled`.
- Khi cập nhật, chỉ cho sửa `scheduled_at` và `reason`; không đổi Patient, Doctor hoặc status.
- Eager load `patient` và `doctor.user` cho danh sách, chi tiết, tạo và cập nhật.
- Dùng permission `APPOINTMENTS.*` qua middleware RBAC, không hard-code role trong Controller
  hoặc Service.

Phạm vi CRUD của task này gồm `index`, `store`, `show`, `update`. Không có `destroy` vì:

- Permission catalog hiện không có `APPOINTMENTS.DELETE`.
- Lịch khám cần giữ lịch sử; thao tác hủy sẽ dùng trạng thái `cancelled` trong task máy trạng
  thái riêng.

## 3. Audit trạng thái repository

### 3.1 Working tree cần được bảo toàn

Tại thời điểm lập tài liệu, repository đã có thay đổi ngoài file docs này:

```text
README.md
app/Models/Appointment.php
database/factories/AppointmentFactory.php
database/migrations/2026_08_11_015448_create_appointments_table.php
```

Ba file Appointment đang là file mới, chưa được Git track. Khi được phép triển khai phải giữ
nguyên thay đổi của người dùng và chỉ chỉnh các file Appointment đúng phạm vi đã duyệt.

### 3.2 Thành phần Appointment đã có

Migration `2026_08_11_015448_create_appointments_table.php` hiện đã khai báo:

- `patient_id` FK đến `patients`, dùng `restrictOnDelete()`.
- `doctor_id` FK đến `doctors`, dùng `restrictOnDelete()`.
- `scheduled_at` kiểu timestamp.
- `status` CHECK/ENUM với bốn giá trị `scheduled`, `confirmed`, `cancelled`, `completed` và
  mặc định `scheduled`.
- `reason` nullable.
- `created_at`, `updated_at`.
- Index `(doctor_id, scheduled_at)`, `patient_id`, `status`.

`Appointment` model và `AppointmentFactory` đã tồn tại nhưng mới là scaffold:

- Model chưa có fillable, status constants, casts và relationships.
- Factory chưa sinh dữ liệu mẫu.

Migration không thuộc phần CRUD cần tạo mới. Chỉ kiểm tra khả năng hoạt động của migration khi
chạy test; không tự ý thay đổi schema trong task này nếu chưa được duyệt riêng.

### 3.3 RBAC đã có sẵn

`config/rbac.php` đã map:

```text
AppointmentController -> APPOINTMENTS
index                 -> FINDALL
store                 -> CREATE
show                  -> FINDONE
update                -> UPDATE
updateStatus          -> UPDATESTATUS
```

Permission catalog đã có:

```text
APPOINTMENTS.FINDALL
APPOINTMENTS.CREATE
APPOINTMENTS.FINDONE
APPOINTMENTS.UPDATE
APPOINTMENTS.UPDATESTATUS
```

Ma trận role hiện tại:

| Role | FINDALL | FINDONE | CREATE | UPDATE | UPDATESTATUS |
|---|:---:|:---:|:---:|:---:|:---:|
| ADMIN | ✓ | ✓ | ✓ | ✓ | ✓ |
| RECEPTIONIST | ✓ | ✓ | ✓ | ✓ | ✓ |
| DOCTOR | ✓ | ✓ |  |  |  |
| CASHIER | ✓ | ✓ |  |  |  |
| PHARMACIST |  |  |  |  |  |

Kết luận RBAC:

- Không cần tạo permission data migration.
- Không cần sửa `config/rbac.php` hoặc `RbacSeeder` cho CRUD cơ bản.
- `APPOINTMENTS.UPDATESTATUS` đã tồn tại nhưng endpoint `updateStatus` nằm ngoài task này.

### 3.4 Thành phần dùng lại

Các thành phần hiện có và không dự kiến sửa:

- `EnsurePermission`: suy ra permission từ Controller/action.
- `ApiResponse::resource()`: envelope cho một Appointment.
- `ApiResponse::paginated()`: envelope danh sách có pagination `meta`.
- Exception Handler: chuẩn hóa lỗi 401, 403, 404 và 422 cho `/api/*`.
- `PatientResource`: định hình Patient lồng trong Appointment response.
- `DoctorResource`: định hình Doctor và `doctor.user` đã eager load.

### 3.5 Thành phần còn thiếu

```text
app/Http/Controllers/AppointmentController.php
app/Services/AppointmentService.php
app/Http/Requests/Appointment/ListAppointmentsRequest.php
app/Http/Requests/Appointment/StoreAppointmentRequest.php
app/Http/Requests/Appointment/UpdateAppointmentRequest.php
app/Http/Resources/AppointmentResource.php
tests/Feature/AppointmentTest.php
```

Ngoài ra còn thiếu route `/api/appointments` và phần hoàn thiện Appointment model/factory.

## 4. API contract dự kiến

| Method | Endpoint | Action | Permission | Thành công |
|---|---|---|---|---:|
| GET | `/api/appointments` | `index` | `APPOINTMENTS.FINDALL` | 200 |
| POST | `/api/appointments` | `store` | `APPOINTMENTS.CREATE` | 201 |
| GET | `/api/appointments/{appointment}` | `show` | `APPOINTMENTS.FINDONE` | 200 |
| PUT/PATCH | `/api/appointments/{appointment}` | `update` | `APPOINTMENTS.UPDATE` | 200 |

Không khai báo `DELETE /api/appointments/{appointment}` trong task này.

Route Model Binding xử lý `{appointment}`. ID không tồn tại trả envelope 404 chuẩn.

### 4.1 Query danh sách

| Query | Validation | Ý nghĩa |
|---|---|---|
| `q` | nullable, string, max 255 | Tìm theo lý do, thông tin Patient hoặc tài khoản Doctor |
| `doctor_id` | nullable, integer, `exists:doctors,id` | Lọc theo bác sĩ |
| `patient_id` | nullable, integer, `exists:patients,id` | Lọc theo bệnh nhân |
| `status` | nullable, string, thuộc bốn status hợp lệ | Lọc theo trạng thái |
| `date` | nullable, `date_format:Y-m-d` | Lọc theo ngày của `scheduled_at` |
| `page` | nullable, integer, min 1 | Trang hiện tại |
| `per_page` | nullable, integer, min 1, max 100 | Số bản ghi, mặc định 15 |

Ví dụ:

```http
GET /api/appointments?doctor_id=2&date=2026-08-15
GET /api/appointments?patient_id=10&status=scheduled
GET /api/appointments?status=confirmed&per_page=20&page=1
GET /api/appointments?q=BN-000010
```

Các filter được kết hợp theo điều kiện AND. Danh sách dự kiến sắp xếp
`scheduled_at DESC`, sau đó `id DESC` để thứ tự ổn định.

Response danh sách dự kiến:

```json
{
  "success": true,
  "message": "Appointments retrieved",
  "data": [],
  "meta": {
    "current_page": 1,
    "from": null,
    "last_page": 1,
    "per_page": 15,
    "to": null,
    "total": 0
  }
}
```

### 4.2 Body tạo mới

```json
{
  "patient_id": 10,
  "doctor_id": 2,
  "scheduled_at": "2026-08-15T09:00:00+07:00",
  "reason": "Routine follow-up"
}
```

Validation dự kiến:

```php
return [
    'patient_id' => ['required', 'integer', 'exists:patients,id'],
    'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
    'scheduled_at' => ['required', 'date'],
    'status' => ['prohibited'],
    'reason' => ['nullable', 'string', 'max:255'],
];
```

Quy tắc tạo:

- Service luôn gán `status = Appointment::STATUS_SCHEDULED`.
- Không lấy status từ payload, kể cả khi client gửi `scheduled`.
- `patient_id` và `doctor_id` phải tồn tại; sai trả 422 theo envelope validation.
- `patient_id` phải thuộc Patient chưa bị soft delete; Appointment lịch sử vẫn đọc Patient đã
  soft delete qua quan hệ `withTrashed()`.
- `reason` có thể không gửi hoặc gửi `null`.
- `scheduled_at` phải là ngày giờ hợp lệ và ở tương lai; thời điểm hiện tại hoặc quá khứ trả
  lỗi validation 422.
- Logic chống trùng lịch bác sĩ nằm ngoài task CRUD này và sẽ được thực hiện ở task riêng.

Response thành công dự kiến:

```json
{
  "success": true,
  "message": "Appointment created",
  "data": {
    "id": 1,
    "patient_id": 10,
    "doctor_id": 2,
    "scheduled_at": "2026-08-15T02:00:00.000000Z",
    "status": "scheduled",
    "reason": "Routine follow-up",
    "patient": {
      "id": 10,
      "code": "BN-000010",
      "full_name": "Nguyen Van An"
    },
    "doctor": {
      "id": 2,
      "user_id": 7,
      "license_number": "LIC-0002",
      "user": {
        "id": 7,
        "name": "Dr. Tran Minh",
        "email": "doctor@example.com"
      }
    },
    "created_at": "2026-08-11T03:00:00.000000Z",
    "updated_at": "2026-08-11T03:00:00.000000Z"
  }
}
```

Các object lồng thực tế sẽ do `PatientResource` và `DoctorResource` định hình; ví dụ trên rút
gọn một số field để tập trung vào quan hệ cần eager load.

### 4.3 Body cập nhật

Chỉ nhận `scheduled_at` và `reason`:

```json
{
  "scheduled_at": "2026-08-15T10:30:00+07:00",
  "reason": "Rescheduled follow-up"
}
```

Validation dự kiến:

```php
return [
    'patient_id' => ['prohibited'],
    'doctor_id' => ['prohibited'],
    'status' => ['prohibited'],
    'scheduled_at' => ['sometimes', 'required', 'date'],
    'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
];
```

Request rỗng trả 422 với lỗi:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "appointment": [
      "At least one appointment field must be provided."
    ]
  }
}
```

Business rule trong Service:

- Nếu Appointment đang là `scheduled`: cho phép cập nhật.
- Nếu đang là `confirmed`, `cancelled` hoặc `completed`: trả 422.
- Dự kiến khóa bản ghi bằng `lockForUpdate()` trong transaction trước khi kiểm tra status và
  cập nhật, tránh status thay đổi đồng thời giữa bước kiểm tra và ghi dữ liệu.

Lỗi business dự kiến:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "appointment": [
      "Only scheduled appointments may be updated."
    ]
  }
}
```

## 5. Thiết kế Model và relationships

Appointment model dự kiến:

```php
#[Fillable(['patient_id', 'doctor_id', 'scheduled_at', 'status', 'reason'])]
class Appointment extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }
}
```

Status constants được dùng chung cho Form Request, Service, Factory và test để tránh lặp chuỗi
rời rạc. CHECK/ENUM ở database vẫn là hàng rào cuối.

## 6. Thiết kế Form Request

Tạo ba request:

```text
ListAppointmentsRequest
StoreAppointmentRequest
UpdateAppointmentRequest
```

Quy ước chung:

- `authorize()` trả `true`; quyền do middleware `permission` xử lý.
- Validation lỗi đi qua Exception Handler và trả envelope 422.
- `ListAppointmentsRequest` xác thực toàn bộ filter trước khi truyền vào Service.
- `StoreAppointmentRequest` cấm client gửi `status`.
- `UpdateAppointmentRequest` cấm đổi Patient, Doctor và status; dùng `after()` để chặn payload
  không có field cập nhật hợp lệ.

## 7. Thiết kế Service

### 7.1 Danh sách

Pseudo-code dự kiến:

```php
Appointment::query()
    ->with(['patient', 'doctor.user'])
    ->search($filters['q'] ?? null)
    ->when($doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
    ->when($patientId, fn ($query) => $query->where('patient_id', $patientId))
    ->when($status, fn ($query) => $query->where('status', $status))
    ->when($date, fn ($query) => $query->whereDate('scheduled_at', $date))
    ->orderByDesc('scheduled_at')
    ->orderByDesc('id')
    ->paginate($perPage);
```

`with(['patient', 'doctor.user'])` phải được áp dụng trước paginate để tránh N+1.

### 7.2 Tạo

Pseudo-code dự kiến:

```php
$appointment = Appointment::query()->create([
    ...$data,
    'status' => Appointment::STATUS_SCHEDULED,
]);

return $appointment->load(['patient', 'doctor.user']);
```

Status được gán tại Service, không phụ thuộc vào default database hoặc input của client. Default
database tiếp tục bảo vệ những insert không đi qua Service.

### 7.3 Chi tiết

```php
return $appointment->loadMissing(['patient', 'doctor.user']);
```

### 7.4 Cập nhật

Pseudo-code dự kiến:

```php
return DB::transaction(function () use ($appointment, $data): Appointment {
    $lockedAppointment = Appointment::query()
        ->lockForUpdate()
        ->findOrFail($appointment->getKey());

    if ($lockedAppointment->status !== Appointment::STATUS_SCHEDULED) {
        throw ValidationException::withMessages([
            'appointment' => ['Only scheduled appointments may be updated.'],
        ]);
    }

    $lockedAppointment->update($data);

    return $lockedAppointment->refresh()->load(['patient', 'doctor.user']);
});
```

Business rule nằm trong Service theo `skills/backend.md`, không đặt trong Controller hoặc Form
Request.

## 8. Thiết kế Controller và response

AppointmentController dự kiến có bốn action:

```text
index(ListAppointmentsRequest $request)
store(StoreAppointmentRequest $request)
show(Appointment $appointment)
update(UpdateAppointmentRequest $request, Appointment $appointment)
```

Controller chỉ thực hiện ba việc:

1. Nhận input đã validate.
2. Gọi AppointmentService.
3. Trả AppointmentResource qua ApiResponse.

Message thống nhất:

| Action | Message | Status |
|---|---|---:|
| `index` | `Appointments retrieved` | 200 |
| `store` | `Appointment created` | 201 |
| `show` | `Appointment retrieved` | 200 |
| `update` | `Appointment updated` | 200 |

## 9. Thiết kế Resource và eager loading

AppointmentResource dự kiến trả:

```php
return [
    'id' => $this->id,
    'patient_id' => $this->patient_id,
    'doctor_id' => $this->doctor_id,
    'scheduled_at' => $this->scheduled_at?->toISOString(),
    'status' => $this->status,
    'reason' => $this->reason,
    'patient' => new PatientResource($this->whenLoaded('patient')),
    'doctor' => new DoctorResource($this->whenLoaded('doctor')),
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),
];
```

Service phải eager load đúng:

```text
patient
doctor.user
```

`DoctorResource` chỉ đưa `user` vào response khi quan hệ này đã được load. Không phát sinh query
ẩn từ Resource.

## 10. Route dự kiến

Trong group middleware `['auth:sanctum', 'permission']`:

```php
Route::apiResource('appointments', AppointmentController::class)
    ->only(['index', 'store', 'show', 'update']);
```

Không dùng apiResource đầy đủ vì sẽ sinh route `destroy` không có permission tương ứng.

## 11. Factory dự kiến

AppointmentFactory tạo Patient và Doctor hợp lệ, đồng thời mặc định status `scheduled`:

```php
return [
    'patient_id' => Patient::factory(),
    'doctor_id' => Doctor::factory(),
    'scheduled_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
    'status' => Appointment::STATUS_SCHEDULED,
    'reason' => fake()->optional()->sentence(),
];
```

Factory phải cho phép override `status`, `doctor_id`, `patient_id` và `scheduled_at` để test
filter và business rule.

## 12. Feature test dự kiến

Tạo `tests/Feature/AppointmentTest.php`, dùng `RefreshDatabase`, seed `RoleSeeder` và
`RbacSeeder`.

Các case chính:

1. ADMIN tạo Appointment thành công và response là 201.
2. RECEPTIONIST tạo Appointment thành công.
3. Payload có `status` bị 422; Service/database vẫn mặc định `scheduled`.
4. `patient_id` không tồn tại trả 422 tại `errors.patient_id`.
5. `doctor_id` không tồn tại trả 422 tại `errors.doctor_id`.
6. `scheduled_at` sai định dạng và `reason` quá dài trả 422.
7. `index` lọc đúng theo từng filter `doctor_id`, `patient_id`, `status`, `date`.
8. Nhiều filter kết hợp theo AND và pagination có `meta` chuẩn.
9. `index`, `show`, `store`, `update` trả kèm `patient` và `doctor.user`.
10. Cập nhật giờ/lý do khi status là `scheduled` thành công.
11. Cập nhật khi status là `confirmed`, `cancelled` hoặc `completed` trả 422.
12. Cập nhật `patient_id`, `doctor_id` hoặc `status` bị 422.
13. Payload update rỗng trả 422.
14. DOCTOR và CASHIER chỉ được `index`/`show`; `store`/`update` trả 403.
15. PHARMACIST không có quyền đọc, trả 403.
16. Request chưa đăng nhập trả 401.
17. Appointment không tồn tại trả envelope 404 chuẩn.
18. Route DELETE không tồn tại và trả 405.

Kiểm tra N+1 dự kiến tập trung vào cấu trúc response có đủ relationships và có thể dùng query
log/assert query count nếu cần; eager loading phải được xác nhận trực tiếp trong Service review.

## 13. File dự kiến thay đổi sau khi được duyệt

### Tạo mới

```text
app/Http/Controllers/AppointmentController.php
app/Services/AppointmentService.php
app/Http/Requests/Appointment/ListAppointmentsRequest.php
app/Http/Requests/Appointment/StoreAppointmentRequest.php
app/Http/Requests/Appointment/UpdateAppointmentRequest.php
app/Http/Resources/AppointmentResource.php
tests/Feature/AppointmentTest.php
```

### Hoàn thiện file scaffold hiện có

```text
app/Models/Appointment.php
database/factories/AppointmentFactory.php
```

### Chỉnh route

```text
routes/api.php
```

### Không dự kiến chỉnh

```text
config/rbac.php
database/seeders/RbacSeeder.php
database/migrations/2026_08_05_015300_seed_permissions.php
database/migrations/2026_08_11_015448_create_appointments_table.php
```

## 14. Thứ tự triển khai sau khi được duyệt

1. Hoàn thiện Appointment model và factory.
2. Tạo ba Form Request.
3. Tạo AppointmentResource.
4. Tạo AppointmentService với filter, eager loading và rule update-only-scheduled.
5. Tạo Controller mỏng.
6. Khai báo bốn route được phép.
7. Viết feature test.
8. Chạy Pint trên các file đã thay đổi.
9. Chạy `AppointmentTest`, sau đó chạy toàn bộ test suite.
10. Báo kết quả và mọi khác biệt so với preview.

## 15. Tiêu chí chấp nhận

- [x] Có `index`, `store`, `show`, `update`; không có `destroy`.
- [x] Tất cả endpoint nằm sau `auth:sanctum` và `permission`.
- [x] `store` validate Patient và Doctor tồn tại.
- [x] `store` chặn Patient đã soft delete nhưng lịch sử vẫn hiển thị Patient cũ.
- [x] `store` luôn tạo status `scheduled` và cấm client gửi status.
- [x] `store` và `update` chặn `scheduled_at` hiện tại hoặc quá khứ.
- [x] `update` chỉ chạy khi status hiện tại là `scheduled`.
- [x] `update` chỉ nhận `scheduled_at` và `reason`.
- [x] `index` lọc được theo `doctor_id`, `patient_id`, `status`, `date`.
- [x] `Appointment::scopeSearch()` tìm theo reason, Patient và Doctor qua query `q`.
- [x] Danh sách có pagination meta chuẩn.
- [x] Mọi response Appointment eager load `patient` và `doctor.user`.
- [x] Controller mỏng; business rule nằm trong Service.
- [x] Không hard-code role.
- [x] 401, 403, 404 và 422 đúng envelope dự án.
- [x] Feature test liên quan và toàn bộ test suite đều pass.
- [x] Comment code bằng tiếng Anh, ngắn gọn và đúng convention dự án.

## 16. Ngoài phạm vi

- Endpoint `PATCH /api/appointments/{appointment}/status`.
- Máy trạng thái `scheduled -> confirmed/cancelled -> completed`.
- Logic chống trùng lịch bác sĩ.
- Activity log khi đổi trạng thái.
- Examination tạo từ Appointment.
- Chỉnh sửa migration/schema Appointment hiện có.

Các nội dung này không được triển khai cùng CRUD cơ bản nếu chưa có yêu cầu và review riêng.
