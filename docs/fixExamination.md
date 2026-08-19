# Fix lỗ hổng phân quyền phiếu khám (Examinations)

Tài liệu ghi lại lỗ hổng phát hiện khi làm FE-05 (Phiếu khám) và các bước sửa cụ thể. Chưa
áp dụng vào code — dùng để bạn tự sửa và kiểm tra lại.


## 1. Bối cảnh phát hiện

Khi test trang "Tạo phiếu khám", phát hiện dropdown chọn lịch hẹn hiển thị cả lịch hẹn của
bác sĩ khác (không phải người đang đăng nhập). Đặt câu hỏi: nếu chọn lịch hẹn không phải của
mình thì hệ thống có chặn không?

Đã viết 1 test thật để kiểm chứng (chạy xong xóa ngay, không đụng dữ liệu thật):

- Tạo 2 bác sĩ: Bác sĩ A và Bác sĩ B.
- Tạo 1 lịch hẹn `confirmed` gán cho Bác sĩ A.
- Đăng nhập bằng tài khoản Bác sĩ B.
- Gọi `POST /api/examinations` với `appointment_id` của lịch hẹn thuộc Bác sĩ A.

**Kết quả: `201 Created` — không bị chặn.** Bác sĩ B tạo được phiếu khám cho bệnh nhân của
Bác sĩ A.

## 2. Nguyên nhân gốc

`app/Http/Middleware/EnsurePermission.php` chỉ kiểm tra **role của người đăng nhập có
permission tên gì** (ví dụ `EXAMINATIONS.CREATE`) — hoàn toàn không kiểm tra dữ liệu cụ thể
(lịch hẹn, phiếu khám) có thuộc về người đang thao tác hay không. Đây là kiểu phân quyền
role-based thuần túy, không có resource-ownership check ở bất kỳ đâu trong dự án.

`ExaminationService::createFromAppointment()` và `ExaminationService::update()` chỉ kiểm tra
trạng thái lịch hẹn / đã có phiếu khám chưa, không so sánh với người gọi API.

Test có sẵn (`ExaminationTest.php`) không phát hiện ra vì
`test_doctor_can_create_examination_from_confirmed_appointment` luôn đăng nhập đúng bằng bác sĩ
sở hữu lịch hẹn (`Sanctum::actingAs($doctor->user)` — cùng 1 doctor), chưa từng test trường hợp
khác bác sĩ.

## 3. Hai lỗ hổng cần sửa

| # | Endpoint | Hành vi sai | Hệ quả |
|---|---|---|---|
| 1 | `POST /api/examinations` | Bác sĩ A tạo được phiếu khám từ lịch hẹn của bác sĩ B | Chẩn đoán bị ghi nhận sai người thực hiện thật sự |
| 2 | `PATCH /api/examinations/{id}` | Bác sĩ A sửa được chẩn đoán/ghi chú của phiếu khám do bác sĩ B tạo | Dữ liệu lâm sàng của bác sĩ khác bị người không liên quan chỉnh sửa |

Cách sửa dùng chung 1 nguyên tắc: nếu người đang đăng nhập **có hồ sơ bác sĩ**
(`User::doctor()` — `app/Models/User.php:31`) thì bắt buộc phải là đúng bác sĩ gắn với lịch
hẹn/phiếu khám đó. Nếu người đăng nhập **không có hồ sơ bác sĩ** (tức ADMIN) thì không bị chặn.
Cách này không cần hard-code tên role `'ADMIN'`.

## 4. Chi tiết sửa — Lỗ hổng 1 (tạo phiếu khám)

### 4.1. `app/Constants/ExaminationMessage.php`

Thêm constant mới, đặt sau `ONLY_CONFIRMED_APPOINTMENTS`:

```php
public const ONLY_CONFIRMED_APPOINTMENTS = 'Only confirmed appointments may be examined.';

public const APPOINTMENT_NOT_ASSIGNED_TO_DOCTOR = 'You may only create an examination for your own appointment.';
```

### 4.2. `app/Services/ExaminationService.php`

**Thêm import** ở đầu file (cạnh các `use` khác):

```php
use App\Models\User;
```

**Đổi chữ ký hàm** `createFromAppointment` và thêm điều kiện chặn:

```php
// Trước
public function createFromAppointment(array $data): Examination
{
    return DB::transaction(function () use ($data): Examination {
        $appointment = Appointment::query()
            ->lockForUpdate()
            ->findOrFail($data['appointment_id']);

        if (Examination::query()
            ->where('appointment_id', $appointment->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'appointment' => [ExaminationMessage::APPOINTMENT_ALREADY_EXAMINED],
            ]);
        }

        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'appointment' => [ExaminationMessage::ONLY_CONFIRMED_APPOINTMENTS],
            ]);
        }

        $examination = Examination::query()->create([
```

```php
// Sau
public function createFromAppointment(array $data, User $actingUser): Examination
{
    return DB::transaction(function () use ($data, $actingUser): Examination {
        $appointment = Appointment::query()
            ->lockForUpdate()
            ->findOrFail($data['appointment_id']);

        if (Examination::query()
            ->where('appointment_id', $appointment->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'appointment' => [ExaminationMessage::APPOINTMENT_ALREADY_EXAMINED],
            ]);
        }

        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'appointment' => [ExaminationMessage::ONLY_CONFIRMED_APPOINTMENTS],
            ]);
        }

        if (
            $actingUser->doctor !== null
            && $appointment->doctor_id !== $actingUser->doctor->id
        ) {
            throw ValidationException::withMessages([
                'appointment' => [ExaminationMessage::APPOINTMENT_NOT_ASSIGNED_TO_DOCTOR],
            ]);
        }

        $examination = Examination::query()->create([
```

Chỉ thêm đúng 1 khối `if` mới (7 dòng), phần còn lại của hàm giữ nguyên.

### 4.3. `app/Http/Controllers/ExaminationController.php`

Sửa lời gọi trong `store()` (dòng 44-46):

```php
// Trước
public function store(StoreExaminationRequest $request): JsonResponse
{
    $examination = $this->service->createFromAppointment($request->validated());

// Sau
public function store(StoreExaminationRequest $request): JsonResponse
{
    $examination = $this->service->createFromAppointment(
        $request->validated(),
        $request->user(),
    );
```

## 5. Chi tiết sửa — Lỗ hổng 2 (sửa phiếu khám)

### 5.1. `app/Constants/ExaminationMessage.php`

Thêm constant mới, đặt cạnh constant vừa thêm ở mục 4.1:

```php
public const EXAMINATION_NOT_ASSIGNED_TO_DOCTOR = 'You may only update an examination that is yours.';
```

### 5.2. `app/Services/ExaminationService.php`

```php
// Trước
public function update(Examination $examination, array $data): Examination
{
    $examination->update($data);

    return $examination->refresh()->load(['patient', 'doctor.user']);
}

// Sau
public function update(Examination $examination, array $data, User $actingUser): Examination
{
    if (
        $actingUser->doctor !== null
        && $examination->doctor_id !== $actingUser->doctor->id
    ) {
        throw ValidationException::withMessages([
            'examination' => [ExaminationMessage::EXAMINATION_NOT_ASSIGNED_TO_DOCTOR],
        ]);
    }

    $examination->update($data);

    return $examination->refresh()->load(['patient', 'doctor.user']);
}
```

### 5.3. `app/Http/Controllers/ExaminationController.php`

Sửa lời gọi trong `update()` (dòng 70-77):

```php
// Trước
public function update(
    UpdateExaminationRequest $request,
    Examination $examination,
): JsonResponse {
    $updatedExamination = $this->service->update(
        $examination,
        $request->validated(),
    );

// Sau
public function update(
    UpdateExaminationRequest $request,
    Examination $examination,
): JsonResponse {
    $updatedExamination = $this->service->update(
        $examination,
        $request->validated(),
        $request->user(),
    );
```

## 6. Cách tự kiểm tra sau khi sửa

1. Chạy lại toàn bộ test có sẵn, phải vẫn pass 100% (mọi test hiện có đều thao tác đúng trên
   phiếu khám/lịch hẹn của chính bác sĩ đang đăng nhập nên không bị ảnh hưởng):

   ```bash
   php artisan test --filter=ExaminationTest
   ```

2. Test tay lại đúng kịch bản đã phát hiện lỗi: đăng nhập bác sĩ A, tạo phiếu khám cho lịch hẹn
   của bác sĩ B → phải trả về `422` với message ở field `appointment`.

3. Test tương tự cho update: đăng nhập bác sĩ A, sửa `diagnosis`/`notes` của phiếu khám do bác
   sĩ B tạo → phải trả về `422` với message ở field `examination`.

4. Đăng nhập bằng tài khoản ADMIN, thử cả 2 luồng trên với lịch hẹn/phiếu khám của bất kỳ bác sĩ
   nào → vẫn phải thành công bình thường (ADMIN không bị chặn).

## 7. Phạm vi chưa sửa (cần bạn quyết định thêm)

`EXAMINATIONS.FINDALL`/`FINDONE` (xem danh sách/chi tiết phiếu khám) hiện **không** giới hạn
theo bác sĩ — bất kỳ ai có quyền xem đều thấy được phiếu khám của mọi bác sĩ khác. Ở nhiều hệ
thống phòng khám, việc bác sĩ xem được hồ sơ bệnh nhân do đồng nghiệp khám là **có chủ đích**
(liên tục chăm sóc bệnh nhân), nên đây **không được coi là lỗ hổng** trong tài liệu này và
chưa có đề xuất fix. Nếu muốn giới hạn cả phần xem theo bác sĩ, cần bàn thêm trước khi làm vì
đây là quyết định nghiệp vụ, không phải bug rõ ràng như 2 lỗ hổng trên.
