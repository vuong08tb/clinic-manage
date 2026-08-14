# Examination `index`, `store`, `show`, `update` — Đã review và triển khai

## 1. Trạng thái tài liệu

- Trạng thái: **ĐÃ REVIEW — ĐÃ TRIỂN KHAI**.
- Nguồn hướng dẫn bắt buộc: `skills/backend.md`.
- Kiến trúc áp dụng: **Controller + Service**.
- Phạm vi được yêu cầu: triển khai `ExaminationController@index`, `store`, `show`, `update`.
- Permission: `EXAMINATIONS.FINDALL`, `EXAMINATIONS.CREATE`, `EXAMINATIONS.FINDONE`,
  `EXAMINATIONS.UPDATE`.
- Kết quả: `ExaminationTest` pass 13 test/93 assertions; toàn bộ suite pass 100 test/748
  assertions; Laravel Pint pass trên toàn bộ file được tạo/chỉnh trong task.

Implementation chỉ thay đổi các file đã nêu trong phạm vi; không sửa migration, permission
catalog, RBAC config hoặc role matrix hiện có.

Luồng tạo phiếu dự kiến:

```text
POST /api/examinations
  -> auth:sanctum
  -> EnsurePermission (EXAMINATIONS.CREATE)
  -> StoreExaminationRequest
  -> ExaminationController@store
  -> ExaminationService::createFromAppointment()
  -> DB transaction + row lock Appointment
  -> ExaminationResource
  -> ApiResponse
```

## 2. Mục tiêu nghiệp vụ

Phạm vi Examination gồm:

- Xem danh sách phiếu khám có phân trang, lọc theo `doctor_id` và `patient_id`.
- Tạo một phiếu khám từ một lịch khám hợp lệ.
- Xem chi tiết một phiếu khám theo ID.
- Cập nhật `diagnosis` và/hoặc `notes` của phiếu khám.

Các nguyên tắc khi tạo phiếu:

- Chỉ tạo từ Appointment có trạng thái `confirmed`.
- Client chỉ gửi `appointment_id`, `diagnosis`, `notes`.
- `patient_id` và `doctor_id` luôn lấy từ Appointment đã khóa trong database, tuyệt đối không
  lấy từ payload.
- `examined_at` do server gán bằng thời điểm hiện tại (`now()`).
- Một Appointment chỉ có tối đa một Examination.
- Tạo Examination và chuyển Appointment sang `completed` trong cùng một transaction.
- Lỗi ở bất kỳ bước nào phải rollback toàn bộ.
- Controller mỏng; validation ở Form Request; business rule và transaction ở Service; output
  qua API Resource và JSON envelope chuẩn.

Các nguyên tắc khi cập nhật:

- Chỉ `diagnosis` và `notes` được phép thay đổi.
- Không cho đổi `appointment_id`, `patient_id`, `doctor_id` hoặc `examined_at`.
- Update không thay đổi trạng thái Appointment và không tạo lại `examined_at`.

## 3. Audit repository hiện tại

### 3.1. Working tree tại thời điểm lập kế hoạch

Tại thời điểm lập tài liệu, working tree sạch. Các file Examination hiện có là nội dung đã
commit trên branch, không phải thay đổi chưa commit của người dùng.

### 3.2. Database đã đáp ứng schema

Migration `2026_08_12_015708_create_examinations_table.php` đã có:

- `appointment_id`: foreign key, `UNIQUE`, `restrictOnDelete()`.
- `doctor_id`: foreign key, `restrictOnDelete()`.
- `patient_id`: foreign key, `restrictOnDelete()`.
- `diagnosis`: `text`.
- `notes`: `text`, nullable.
- `examined_at`: timestamp.
- timestamps.

Kết luận: không cần sửa migration trong phạm vi này. Unique constraint là hàng rào database
cuối cùng chống tạo hai phiếu cho cùng một Appointment.

### 3.3. Thành phần Examination hiện có

- `app/Models/Examination.php` mới là scaffold, chưa có fillable, cast hoặc relationship.
- `database/factories/ExaminationFactory.php` mới là scaffold, chưa sinh dữ liệu.
- Chưa có Examination Controller, Service, Form Request, Resource, route và feature test.
- `Appointment` chưa có relationship `examination()`.

### 3.4. RBAC đã có sẵn

Repository đã có:

- Mapping `ExaminationController -> EXAMINATIONS` trong `config/rbac.php`.
- Mapping action `index -> FINDALL`, `store -> CREATE`, `show -> FINDONE`,
  `update -> UPDATE`.
- Các permission `EXAMINATIONS.FINDALL`, `EXAMINATIONS.CREATE`, `EXAMINATIONS.FINDONE`,
  `EXAMINATIONS.UPDATE` trong permission catalog.
- `DOCTOR` có cả bốn permission; `ADMIN` có toàn bộ permission.
- `CASHIER` có `EXAMINATIONS.FINDALL` và `EXAMINATIONS.FINDONE`.
- `RECEPTIONIST` và `PHARMACIST` không có cả bốn permission.

Vì vậy không cần sửa permission migration, `config/rbac.php` hoặc `RbacSeeder`.

## 4. API contract đề xuất

| Method | Endpoint | Controller action | Permission | Thành công |
|---|---|---|---|---:|
| GET | `/api/examinations` | `ExaminationController@index` | `EXAMINATIONS.FINDALL` | 200 |
| POST | `/api/examinations` | `ExaminationController@store` | `EXAMINATIONS.CREATE` | 201 |
| GET | `/api/examinations/{examination}` | `ExaminationController@show` | `EXAMINATIONS.FINDONE` | 200 |
| PUT/PATCH | `/api/examinations/{examination}` | `ExaminationController@update` | `EXAMINATIONS.UPDATE` | 200 |

Endpoint nằm trong middleware group `auth:sanctum` và `permission` hiện có.

### 4.1. Query danh sách

| Query | Validation | Ý nghĩa |
|---|---|---|
| `doctor_id` | nullable, integer, `exists:doctors,id` | Lọc theo bác sĩ |
| `patient_id` | nullable, integer, `exists:patients,id` | Lọc theo bệnh nhân, gồm cả hồ sơ đã soft delete |
| `page` | nullable, integer, min 1 | Trang hiện tại |
| `per_page` | nullable, integer, min 1, max 100 | Số record/trang, mặc định 15 |

Các filter kết hợp bằng AND. Danh sách sắp xếp `examined_at DESC`, sau đó `id DESC` để thứ tự
ổn định. Service eager load `patient` và `doctor.user` trước khi serialize.

Response danh sách:

```json
{
  "success": true,
  "message": "Examinations retrieved",
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

### 4.2. Xem chi tiết

`GET /api/examinations/{examination}` dùng Route Model Binding. Service eager load
`patient`, `doctor.user` và trả cùng `ExaminationResource` dùng cho create/update.

- Tìm thấy: HTTP 200, message `Examination retrieved`.
- Không tìm thấy: HTTP 404 theo JSON envelope chuẩn.
- Endpoint chỉ đọc dữ liệu, không thay đổi Examination hoặc Appointment.

### 4.3. Body tạo mới

```json
{
  "appointment_id": 25,
  "diagnosis": "Acute upper respiratory infection",
  "notes": "Rest and return if symptoms worsen"
}
```

Validation dự kiến:

```php
return [
    'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
    'diagnosis' => ['required', 'string'],
    'notes' => ['nullable', 'string'],
    'patient_id' => ['prohibited'],
    'doctor_id' => ['prohibited'],
    'examined_at' => ['prohibited'],
];
```

Quyết định đề xuất cho review:

- Các field do server sở hữu (`patient_id`, `doctor_id`, `examined_at`) bị từ chối bằng 422
  nếu client gửi lên. Cách này làm contract rõ ràng và ngăn client tưởng rằng giá trị gửi lên
  đã được sử dụng.
- Các field hợp lệ duy nhất được Service nhận từ `$request->validated()` là
  `appointment_id`, `diagnosis`, `notes`.
- `notes` có thể không gửi hoặc gửi `null`.
- Không đặt giới hạn ký tự tùy ý cho `diagnosis`/`notes` vì schema dùng kiểu `text` và yêu cầu
  hiện tại không quy định giới hạn.

### 4.4. Body cập nhật

```json
{
  "diagnosis": "Updated diagnosis",
  "notes": null
}
```

Validation dự kiến:

```php
return [
    'diagnosis' => ['sometimes', 'required', 'string'],
    'notes' => ['sometimes', 'nullable', 'string'],
    'appointment_id' => ['prohibited'],
    'patient_id' => ['prohibited'],
    'doctor_id' => ['prohibited'],
    'examined_at' => ['prohibited'],
];
```

- Payload phải chứa ít nhất một trong hai field `diagnosis`, `notes`; payload rỗng hoặc chỉ
  chứa field bị cấm nhận 422 với error key `examination`.
- `diagnosis` nếu có phải là chuỗi không rỗng.
- `notes` nếu có được phép là `null`.
- Route Model Binding xử lý Examination không tồn tại bằng envelope 404 chuẩn.
- Service chỉ update validated input, sau đó load `patient` và `doctor.user` cho response.

### 4.5. Response resource thành công

```json
{
  "success": true,
  "message": "Examination created",
  "data": {
    "id": 1,
    "appointment_id": 25,
    "patient_id": 10,
    "doctor_id": 4,
    "diagnosis": "Acute upper respiratory infection",
    "notes": "Rest and return if symptoms worsen",
    "examined_at": "2026-08-12T03:15:00.000000Z",
    "patient": {},
    "doctor": {},
    "created_at": "2026-08-12T03:15:00.000000Z",
    "updated_at": "2026-08-12T03:15:00.000000Z"
  }
}
```

- `patient` được định hình bằng `PatientResource`.
- `doctor` được định hình bằng `DoctorResource`, có eager-loaded `doctor.user`.
- Timestamp được serialize theo ISO-8601 giống các Resource hiện tại.
- Với `store`, message là `Examination created`, HTTP 201; Appointment nguồn đã có status
  `completed`.
- Với `show`, message là `Examination retrieved`, HTTP 200.
- Với `update`, message là `Examination updated`, HTTP 200; các field định danh và
  `examined_at` giữ nguyên.

### 4.6. Lỗi dự kiến

| Trường hợp | HTTP | Error key/message dự kiến |
|---|---:|---|
| Chưa đăng nhập | 401 | Envelope unauthenticated hiện có |
| Thiếu permission theo action | 403 | `Missing permission: EXAMINATIONS.*` tương ứng |
| `appointment_id` không tồn tại | 422 | `appointment_id` validation error |
| Appointment không phải `confirmed` | 422 | `appointment`: `Only confirmed appointments may be examined.` |
| Appointment đã có Examination | 422 | `appointment`: `The appointment already has an examination.` |
| Thiếu/rỗng `diagnosis` | 422 | `diagnosis` validation error |
| Client gửi field do server sở hữu | 422 | Validation error theo field bị cấm |
| Filter danh sách không hợp lệ | 422 | Validation error theo query field |
| Examination route binding không tồn tại khi show/update | 404 | Envelope not found hiện có |
| Payload update không có field hợp lệ | 422 | `examination`: `At least one examination field must be provided.` |

Service kiểm tra “đã có Examination” trước kiểm tra status để lần gọi thứ hai sau khi lần đầu
thành công nhận đúng thông báo trùng phiếu, dù Appointment lúc đó đã là `completed`.

## 5. Thiết kế transaction và chống race condition

`ExaminationService::createFromAppointment()` dự kiến thực hiện:

1. Mở `DB::transaction()`.
2. Query Appointment theo `appointment_id` với `lockForUpdate()`.
3. Kiểm tra Examination theo `appointment_id` đã tồn tại hay chưa; có thì ném
   `ValidationException`.
4. Kiểm tra status chính xác là `Appointment::STATUS_CONFIRMED`; sai thì ném
   `ValidationException`.
5. Tạo Examination từ:
   - `appointment_id` của Appointment đã khóa;
   - `patient_id` của Appointment đã khóa;
   - `doctor_id` của Appointment đã khóa;
   - `diagnosis`, `notes` từ validated input;
   - `examined_at = now()` từ server.
6. Cập nhật cùng Appointment thành `Appointment::STATUS_COMPLETED`.
7. Load `patient` và `doctor.user`, rồi trả Examination.

`lockForUpdate()` khiến hai request đồng thời trên cùng Appointment được tuần tự hóa. Unique
constraint của `appointment_id` tiếp tục bảo vệ dữ liệu nếu có đường ghi khác bỏ qua Service.

## 6. Phân lớp dự kiến

### Controller

`ExaminationController` dự kiến có bốn action mỏng:

- `index(ListExaminationsRequest)`: gọi Service paginate và trả
  `ApiResponse::paginated()` với message `Examinations retrieved`;
- `store(StoreExaminationRequest)`: gọi
  `ExaminationService::createFromAppointment()` và trả `ExaminationResource` với HTTP 201;
- `show(Examination)`: gọi Service load quan hệ và trả `ExaminationResource` với HTTP 200;
- `update(UpdateExaminationRequest, Examination)`: gọi Service update và trả
  `ExaminationResource` với HTTP 200.

Controller không tự dựng query, không query Appointment, không gán `patient_id`/`doctor_id`,
không kiểm tra status và không hard-code role.

### Form Request

Ba Form Request dự kiến:

- `ListExaminationsRequest`: validate filters và pagination của `index`;
- `StoreExaminationRequest`: validate body tạo mới và cấm field do server sở hữu;
- `UpdateExaminationRequest`: validate partial update, cấm field định danh/server-owned và
  dùng `after()` để yêu cầu ít nhất một field hợp lệ.

Tất cả `authorize()` trả `true` vì RBAC do middleware xử lý và dùng English validation
messages ngắn gọn theo convention của project.

### Service

`ExaminationService` dự kiến có:

- `paginate(array $filters)`: dựng query có filter, eager loading, sort và pagination;
- `createFromAppointment(array $data)`: sở hữu business rule, transaction và row lock;
- `load(Examination $examination)`: eager load context cần cho resource chi tiết;
- `update(Examination $examination, array $data)`: chỉ update validated clinical fields rồi
  refresh/eager load resource context.

Service ném `ValidationException::withMessages()` cho lỗi nghiệp vụ để exception handler trả
envelope 422 chuẩn.

### Model và relationships

`Examination` dự kiến có:

- fillable: `appointment_id`, `doctor_id`, `patient_id`, `diagnosis`, `notes`, `examined_at`;
- cast `examined_at` thành `datetime`;
- `appointment(): BelongsTo`;
- `doctor(): BelongsTo`;
- `patient(): BelongsTo` và `withTrashed()` để lịch sử y tế vẫn đọc được Patient đã soft
  delete.

`Appointment` bổ sung `examination(): HasOne`.

### API Resource

`ExaminationResource` chỉ expose các field trong response contract và các relationship đã
load. Resource không query database và không chứa business rule.

### Factory

Hoàn thiện `ExaminationFactory` để feature test tạo dữ liệu hợp lệ, mặc định tạo Appointment
`completed` có Patient/Doctor tương ứng. Khi cần test luồng `store`, test vẫn tạo Appointment
`confirmed` trực tiếp rồi gọi API, không dùng factory để bỏ qua nghiệp vụ đang kiểm thử.

## 7. Danh sách file dự kiến thay đổi sau khi được duyệt

Tạo mới:

```text
app/Http/Controllers/ExaminationController.php
app/Http/Requests/Examination/ListExaminationsRequest.php
app/Http/Requests/Examination/StoreExaminationRequest.php
app/Http/Requests/Examination/UpdateExaminationRequest.php
app/Http/Resources/ExaminationResource.php
app/Services/ExaminationService.php
tests/Feature/ExaminationTest.php
```

Chỉnh sửa:

```text
app/Models/Appointment.php
app/Models/Examination.php
database/factories/ExaminationFactory.php
routes/api.php
docs/examinationcrud.md
```

Không dự kiến sửa:

```text
database/migrations/2026_08_12_015708_create_examinations_table.php
database/migrations/2026_08_05_015300_seed_permissions.php
database/seeders/RbacSeeder.php
config/rbac.php
```

File docs đã được cập nhật trạng thái và kết quả xác minh sau khi implementation hoàn tất.

## 8. Feature test dự kiến

`ExaminationTest` dùng `RefreshDatabase`, seed `RoleSeeder` + `RbacSeeder`, và cover tối thiểu:

1. DOCTOR có permission tạo Examination từ Appointment `confirmed` và nhận 201.
2. Phiếu dùng đúng `patient_id`/`doctor_id` từ Appointment.
3. Client gửi `patient_id`, `doctor_id` hoặc `examined_at` nhận 422 và không tạo dữ liệu.
4. `examined_at` do server gán gần với `now()`; dùng freeze time để assertion ổn định.
5. Appointment chuyển từ `confirmed` sang `completed` sau khi tạo phiếu.
6. `notes` nullable hoạt động đúng.
7. Appointment `scheduled`, `cancelled` hoặc `completed` không có phiếu đều bị chặn 422.
8. Tạo phiếu lần hai cho cùng Appointment bị chặn 422 và chỉ còn một record.
9. RECEPTIONIST (và một role thiếu quyền khác) nhận 403 với message permission chính xác.
10. Request chưa đăng nhập nhận 401.
11. `appointment_id` không tồn tại và `diagnosis` không hợp lệ nhận 422 đúng envelope.
12. Khi phát sinh exception giữa insert Examination và update Appointment, transaction rollback:
    không có Examination mới và Appointment vẫn `confirmed`.
13. Query count cho response store xác nhận relationships cần thiết đã eager load, tránh N+1.
14. `index` trả pagination meta và sắp xếp mới nhất trước.
15. `index` lọc đúng theo `doctor_id`, `patient_id` và kết hợp hai filter bằng AND.
16. `index` từ chối filter/pagination không hợp lệ bằng 422.
17. DOCTOR và CASHIER có `EXAMINATIONS.FINDALL` xem được danh sách; role thiếu quyền nhận 403.
18. DOCTOR cập nhật được `diagnosis`, đặt `notes = null`, và nhận resource đã eager load.
19. Update từ chối payload rỗng và các field `appointment_id`, `patient_id`, `doctor_id`,
    `examined_at`; dữ liệu định danh/thời điểm khám không thay đổi.
20. Role thiếu `EXAMINATIONS.UPDATE` nhận 403; Examination không tồn tại nhận 404.
21. DOCTOR và CASHIER có `EXAMINATIONS.FINDONE` xem được chi tiết với Patient và Doctor đã
    eager load.
22. Role thiếu `EXAMINATIONS.FINDONE` nhận 403; ID chi tiết không tồn tại nhận 404.

Kết quả xác minh:

```bash
php artisan test --filter=ExaminationTest  # 13 passed, 93 assertions
php artisan test                           # 100 passed, 748 assertions
vendor/bin/pint --test <task-files>        # passed
```

`vendor/bin/pint --test` trên toàn repository vẫn báo ba file có lỗi format tồn tại từ trước
task: hai migration Appointment/Examination và `UserController.php`. Các file này không được
tự ý format vì migration nằm ngoài phạm vi đã duyệt và `UserController.php` không liên quan.

## 9. Nội dung ngoài phạm vi hiện tại

- `destroy` của Examination.
- Các filter ngoài `doctor_id`, `patient_id`.
- Prescription, Invoice và Payment.
- Thay đổi schema Examination.
- Activity log cho Examination: `skills/backend.md` yêu cầu ở luồng hoàn chỉnh, nhưng
  repository chưa có hạ tầng activity log và phạm vi hiện tại chỉ yêu cầu `index`, `store`,
  `show`, `update`. Không tự mở rộng thêm migration/model/observer trong task này nếu chưa
  được duyệt riêng.
- Ownership rule “DOCTOR chỉ thao tác phiếu/lịch của chính mình”: phạm vi hiện tại chỉ enforce
  permission theo action, chưa hard-code role hoặc so khớp user đang đăng nhập với
  `doctor_id`. Nếu cần siết ownership, phải được chốt trước implementation vì sẽ thay đổi
  hành vi của cả `index`, `store`, `show`, `update` và ADMIN.

## 10. Các điểm đã triển khai theo nội dung review

- [x] Chỉ Appointment `confirmed` được tạo phiếu; không nới sang `scheduled`.
- [x] Tạo Examination và đổi Appointment sang `completed` trong cùng transaction.
- [x] `patient_id`, `doctor_id`, `examined_at` gửi từ client sẽ bị trả 422 thay vì âm thầm bỏ
  qua.
- [x] `index` phân trang và chỉ lọc theo `doctor_id`, `patient_id`.
- [x] `show` trả chi tiết theo ID bằng `EXAMINATIONS.FINDONE` và không thay đổi dữ liệu.
- [x] `update` chỉ sửa `diagnosis`, `notes`; không thay đổi các field còn lại.
- [x] Phạm vi hiện tại gồm `index`, `store`, `show`, `update`; chưa làm `destroy`.
- [x] Chưa áp dụng ownership theo bác sĩ; chỉ dùng RBAC permission theo từng action.
- [x] Activity log được tách sang task riêng do hạ tầng chưa tồn tại.
