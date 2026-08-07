# CRUD Doctors — Demo lệnh, hàm và kết quả dự kiến

## 1. Mục tiêu

Triển khai CRUD Doctor theo `skills/backend.md` với kiến trúc:

```text
Route
  -> auth:sanctum
  -> EnsurePermission (DOCTORS.*)
  -> Form Request
  -> DoctorController
  -> DoctorService
  -> Doctor Model
  -> DoctorResource
  -> ApiResponse
```

Bảng `doctors` gồm:

| Cột | Ràng buộc |
|---|---|
| `id` | Primary key |
| `user_id` | Foreign key tới `users.id`, unique |
| `specialty_id` | Foreign key tới `specialties.id` |
| `license_number` | String, unique |
| `bio` | Text, nullable |
| `created_at`, `updated_at` | Timestamps |

Business rule bắt buộc:

- Chỉ tạo Doctor từ User có role `DOCTOR`.
- Khi đổi `user_id` lúc update, User mới cũng phải có role `DOCTOR`.
- User đã có Doctor profile không được đổi sang role khác `DOCTOR`.
- Một User chỉ có tối đa một Doctor profile.
- `license_number` không được trùng.
- Business rule kiểm tra role nằm trong `DoctorService`, không hard-code role trong Controller.
- Mọi endpoint dùng permission `DOCTORS.*` thông qua middleware RBAC.

## 2. Permission và API contract

Permission và mapping đã có sẵn trong dự án:

```text
DoctorController@index   -> DOCTORS.FINDALL
DoctorController@store   -> DOCTORS.CREATE
DoctorController@show    -> DOCTORS.FINDONE
DoctorController@update  -> DOCTORS.UPDATE
DoctorController@destroy -> DOCTORS.DELETE
```

| Method | Endpoint | Permission | Thành công |
|---|---|---|---:|
| GET | `/api/doctors` | `DOCTORS.FINDALL` | 200 |
| POST | `/api/doctors` | `DOCTORS.CREATE` | 201 |
| GET | `/api/doctors/{doctor}` | `DOCTORS.FINDONE` | 200 |
| PUT/PATCH | `/api/doctors/{doctor}` | `DOCTORS.UPDATE` | 200 |
| DELETE | `/api/doctors/{doctor}` | `DOCTORS.DELETE` | 200 |

Theo ma trận RBAC hiện tại:

- `ADMIN`: được dùng toàn bộ CRUD Doctor.
- `RECEPTIONIST`, `DOCTOR`: chỉ được xem danh sách và chi tiết.
- `PHARMACIST`, `CASHIER`: không có quyền Doctor.

## 3. Các hàm dự kiến

### DoctorController

```php
index(ListDoctorsRequest $request): JsonResponse
store(StoreDoctorRequest $request): JsonResponse
show(Doctor $doctor): JsonResponse
update(UpdateDoctorRequest $request, Doctor $doctor): JsonResponse
destroy(Doctor $doctor): JsonResponse
```

Controller chỉ nhận request, gọi service và định dạng response.

### DoctorService

```php
paginate(array $filters): LengthAwarePaginator
create(array $data): Doctor
load(Doctor $doctor): Doctor
update(Doctor $doctor, array $data): Doctor
delete(Doctor $doctor): void
```

`create()` và `update()` chạy trong transaction. Khi cần gán `user_id`, service khóa bản ghi
User để kiểm tra role:

```php
if ($user->role?->name !== 'DOCTOR') {
    throw ValidationException::withMessages([
        'user_id' => ['The selected user must have the DOCTOR role.'],
    ]);
}
```

### Quan hệ model

```php
Doctor::user(): BelongsTo
Doctor::specialty(): BelongsTo
User::doctor(): HasOne
Specialty::doctors(): HasMany
```

List và detail eager-load `user` và `specialty` để tránh N+1.

## 4. Demo chuẩn bị dữ liệu

Khởi động ứng dụng và migrate:

```bash
docker compose up -d
docker compose exec app php artisan migrate
```

Đăng nhập ADMIN để lấy token:

```bash
curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@clinic.local","password":"password"}'
```

Kết quả dự kiến:

```json
{
  "success": true,
  "message": "Logged in",
  "data": {
    "token": "<ADMIN_TOKEN>",
    "user": {
      "id": 1,
      "email": "admin@clinic.local"
    }
  }
}
```

Các demo bên dưới giả định:

```text
ADMIN_TOKEN=<token vừa nhận>
DOCTOR_USER_ID=2          (User có role DOCTOR)
RECEPTIONIST_USER_ID=3    (User có role RECEPTIONIST)
SPECIALTY_ID=1
```

## 5. Demo CREATE

### 5.1 Tạo Doctor hợp lệ

Lệnh:

```bash
curl -s -X POST http://localhost:8000/api/doctors \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d "{
    \"user_id\": $DOCTOR_USER_ID,
    \"specialty_id\": $SPECIALTY_ID,
    \"license_number\": \"VN-DOC-0001\",
    \"bio\": \"Cardiologist with ten years of clinical experience.\"
  }"
```

Kết quả dự kiến — HTTP `201`:

```json
{
  "success": true,
  "message": "Doctor created",
  "data": {
    "id": 1,
    "user_id": 2,
    "specialty_id": 1,
    "license_number": "VN-DOC-0001",
    "bio": "Cardiologist with ten years of clinical experience.",
    "user": {
      "id": 2,
      "name": "Doctor One",
      "email": "doctor1@clinic.local"
    },
    "specialty": {
      "id": 1,
      "name": "Cardiology"
    },
    "created_at": "2026-08-07T00:00:00.000000Z",
    "updated_at": "2026-08-07T00:00:00.000000Z"
  }
}
```

### 5.2 Từ chối User sai role

Lệnh:

```bash
curl -s -X POST http://localhost:8000/api/doctors \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d "{
    \"user_id\": $RECEPTIONIST_USER_ID,
    \"specialty_id\": $SPECIALTY_ID,
    \"license_number\": \"VN-DOC-0002\"
  }"
```

Kết quả dự kiến — HTTP `422`, không có bản ghi Doctor mới:

```json
{
  "success": false,
  "message": "The selected user must have the DOCTOR role.",
  "errors": {
    "user_id": [
      "The selected user must have the DOCTOR role."
    ]
  }
}
```

### 5.3 Từ chối `user_id` hoặc `license_number` trùng

Kết quả dự kiến — HTTP `422`:

```json
{
  "success": false,
  "message": "The selected user already has a doctor profile. (and 1 more error)",
  "errors": {
    "user_id": ["The selected user already has a doctor profile."],
    "license_number": ["The license number has already been taken."]
  }
}
```

## 6. Demo FINDALL

Lệnh:

```bash
curl -s \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  'http://localhost:8000/api/doctors?q=cardio&specialty_id=1&per_page=15&page=1'
```

Filter dự kiến:

- `q`: tìm không phân biệt hoa/thường theo tên, email, license number hoặc bio.
- `specialty_id`: lọc theo chuyên khoa.
- `page`: số trang, nhỏ nhất 1.
- `per_page`: số dòng mỗi trang, từ 1 đến 100, mặc định 15.

Kết quả dự kiến — HTTP `200`:

```json
{
  "success": true,
  "message": "Doctors retrieved",
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "specialty_id": 1,
      "license_number": "VN-DOC-0001",
      "bio": "Cardiologist with ten years of clinical experience.",
      "user": {
        "id": 2,
        "name": "Doctor One",
        "email": "doctor1@clinic.local"
      },
      "specialty": {
        "id": 1,
        "name": "Cardiology"
      },
      "created_at": "2026-08-07T00:00:00.000000Z",
      "updated_at": "2026-08-07T00:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

## 7. Demo FINDONE

Lệnh:

```bash
curl -s \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  http://localhost:8000/api/doctors/1
```

Kết quả dự kiến — HTTP `200`:

```json
{
  "success": true,
  "message": "Doctor retrieved",
  "data": {
    "id": 1,
    "user_id": 2,
    "specialty_id": 1,
    "license_number": "VN-DOC-0001",
    "bio": "Cardiologist with ten years of clinical experience.",
    "user": {
      "id": 2,
      "name": "Doctor One",
      "email": "doctor1@clinic.local"
    },
    "specialty": {
      "id": 1,
      "name": "Cardiology"
    },
    "created_at": "2026-08-07T00:00:00.000000Z",
    "updated_at": "2026-08-07T00:00:00.000000Z"
  }
}
```

ID không tồn tại trả HTTP `404`:

```json
{
  "success": false,
  "message": "Resource not found.",
  "errors": []
}
```

## 8. Demo UPDATE

Lệnh:

```bash
curl -s -X PATCH http://localhost:8000/api/doctors/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "license_number": "VN-DOC-0001-UPDATED",
    "bio": "Updated professional biography."
  }'
```

Kết quả dự kiến — HTTP `200`:

```json
{
  "success": true,
  "message": "Doctor updated",
  "data": {
    "id": 1,
    "user_id": 2,
    "specialty_id": 1,
    "license_number": "VN-DOC-0001-UPDATED",
    "bio": "Updated professional biography."
  }
}
```

Body rỗng trả HTTP `422`:

```json
{
  "success": false,
  "message": "At least one doctor field must be provided.",
  "errors": {
    "doctor": ["At least one doctor field must be provided."]
  }
}
```

Nếu update sang `user_id` có role khác `DOCTOR`, kết quả giống mục 5.2 và dữ liệu cũ không
bị thay đổi.

Nếu gọi CRUD User để đổi role của User đang sở hữu Doctor profile sang role khác, kết quả
HTTP `422`:

```json
{
  "success": false,
  "message": "A user with a doctor profile must keep the DOCTOR role.",
  "errors": {
    "role_id": ["A user with a doctor profile must keep the DOCTOR role."]
  }
}
```

## 9. Demo DELETE

Lệnh:

```bash
curl -s -X DELETE \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  http://localhost:8000/api/doctors/1
```

Kết quả dự kiến — HTTP `200`:

```json
{
  "success": true,
  "message": "Doctor deleted",
  "data": null
}
```

DELETE chỉ xóa Doctor profile, không xóa User và Specialty liên quan.

## 10. Demo xác thực và RBAC

Không gửi token:

```bash
curl -s http://localhost:8000/api/doctors
```

Kết quả — HTTP `401`:

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": []
}
```

RECEPTIONIST gọi CREATE:

```bash
curl -s -X POST http://localhost:8000/api/doctors \
  -H "Authorization: Bearer $RECEPTIONIST_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"user_id":2,"specialty_id":1,"license_number":"VN-DOC-0003"}'
```

Kết quả — HTTP `403`:

```json
{
  "success": false,
  "message": "Missing permission: DOCTORS.CREATE",
  "errors": []
}
```

## 11. Lệnh tạo code dự kiến

Các lệnh tương đương dùng để scaffold thành phần:

```bash
docker compose exec app php artisan make:model Doctor --factory
docker compose exec app php artisan make:migration create_doctors_table
docker compose exec app php artisan make:controller DoctorController --api
docker compose exec app php artisan make:request Doctor/ListDoctorsRequest
docker compose exec app php artisan make:request Doctor/StoreDoctorRequest
docker compose exec app php artisan make:request Doctor/UpdateDoctorRequest
docker compose exec app php artisan make:resource DoctorResource
docker compose exec app php artisan make:test DoctorTest
```

Code hoàn chỉnh sẽ được tổ chức theo Controller + Service và không để business logic trong
Controller.

## 12. Lệnh kiểm thử dự kiến

Chạy riêng feature test Doctor:

```bash
docker compose exec app php artisan test --filter=DoctorTest
```

Kết quả mong đợi:

```text
PASS  Tests\Feature\DoctorTest
Tests: all passed
```

Chạy toàn bộ test để kiểm tra hồi quy:

```bash
docker compose exec app php artisan test
```

Kiểm tra format:

```bash
docker compose exec app ./vendor/bin/pint --test
```

Kết quả mong đợi: toàn bộ test pass và Pint không báo lỗi format.
