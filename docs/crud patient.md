# Kế hoạch CRUD Patients — Đã review, được phép thực thi

## 1. Trạng thái tài liệu

- Trạng thái: **ĐÃ REVIEW / ĐƯỢC PHÉP TRIỂN KHAI**.
- Nguồn hướng dẫn bắt buộc: `skills/backend.md`.
- Kiến trúc áp dụng: **Controller + Service**.
- Các quyết định schema, code, soft delete và RBAC đã được người review xác nhận.

Luồng xử lý dự kiến:

```text
Route
  -> auth:sanctum
  -> EnsurePermission (PATIENTS.*)
  -> Form Request
  -> PatientController
  -> PatientService
  -> Patient model/query scope
  -> PatientResource
  -> ApiResponse
```

## 2. Mục tiêu nghiệp vụ

Triển khai CRUD Patients với các yêu cầu:

- `RECEPTIONIST`: xem danh sách, xem chi tiết, tạo và cập nhật Patient.
- `DOCTOR`: chỉ xem danh sách và chi tiết Patient.
- `CASHIER`: chỉ xem danh sách và chi tiết Patient.
- `ADMIN`: có toàn bộ quyền, bao gồm xóa Patient.
- `PHARMACIST`: không có quyền Patient.
- Search query `q` theo `full_name`, `phone`, `code`.
- PostgreSQL sử dụng `ILIKE` để tìm không phân biệt hoa/thường.
- Danh sách có pagination và `meta` chuẩn của `ApiResponse::paginated()`.
- `store` tự sinh mã Patient; client không được tự gửi `code`.
- `destroy` chỉ soft delete, không xóa vật lý.
- Search được đóng gói trong query scope của `Patient` model.

## 3. Audit trạng thái hiện tại

### 3.1 Working tree

Các file Patient hiện là file mới, chưa tracked:

```text
app/Models/Patient.php
database/factories/PatientFactory.php
database/migrations/2026_08_10_020152_create_patients_table.php
```

Ngoài ra đang có thay đổi của người dùng trong:

```text
README.md
docs/ke-hoach-chi-tiet.md
```

Khi triển khai phải giữ nguyên các thay đổi này, không ghi đè hoặc format ngoài phạm vi.

### 3.2 Quyền file

Ba file Patient hiện thuộc:

```text
ubuntu:ubuntu
```

VS Code có thể lưu bình thường. Các lệnh Artisan sau này phải chạy với UID/GID của host để
không tái tạo file `root:root`.

### 3.3 Migration và database hiện tại

Migration:

```text
2026_08_10_020152_create_patients_table [6] Ran
```

Database PostgreSQL hiện có:

```text
patient_count = 0
```

Schema hiện tại đã có:

- `code` unique.
- `full_name` và index `patients_full_name_index`.
- `gender` CHECK `male|female|other` do Laravel `enum()` sinh ra.
- `date_of_birth` kiểu date.
- `phone` và index `patients_phone_index`.
- `email` nullable nhưng đang bị đặt unique.
- `address` nullable, kiểu `varchar(255)`.
- timestamps và `deleted_at`.

Điểm cần review trước khi triển khai:

1. Giữ `email` nullable và unique theo quyết định đã duyệt.
2. Giữ `address` nullable kiểu string theo quyết định đã duyệt.
3. Giữ CHECK cho `gender` với đúng ba giá trị `male`, `female`, `other`.
4. `code` unique đã tự tạo unique index; không tạo thêm index trùng cho `code`.
5. Giữ index riêng cho `full_name` và `phone` theo yêu cầu T2.1.

### 3.4 Model và Factory

`Patient` model hiện mới chỉ có `HasFactory`, còn thiếu:

- `Fillable`.
- `SoftDeletes`.
- Cast `date_of_birth` và `deleted_at`.
- Danh sách gender hợp lệ.
- Query scope `search()`.

`PatientFactory` hiện chưa có dữ liệu mẫu.

### 3.5 RBAC đã có sẵn

Permission catalog đã có đủ:

```text
PATIENTS.FINDALL
PATIENTS.CREATE
PATIENTS.FINDONE
PATIENTS.UPDATE
PATIENTS.DELETE
```

`config/rbac.php` đã map:

```text
PatientController -> PATIENTS
index              -> FINDALL
store              -> CREATE
show               -> FINDONE
update             -> UPDATE
destroy            -> DELETE
```

`RbacSeeder` hiện đã đúng ma trận yêu cầu:

| Role | FINDALL | FINDONE | CREATE | UPDATE | DELETE |
|---|:---:|:---:|:---:|:---:|:---:|
| ADMIN | ✓ | ✓ | ✓ | ✓ | ✓ |
| RECEPTIONIST | ✓ | ✓ | ✓ | ✓ |  |
| DOCTOR | ✓ | ✓ |  |  |  |
| CASHIER | ✓ | ✓ |  |  |  |
| PHARMACIST |  |  |  |  |  |

Kết luận RBAC:

- Không cần sửa permission data migration.
- Không cần sửa `config/rbac.php`.
- `RbacSeeder` đã được đồng bộ để RECEPTIONIST có `PATIENTS.FINDALL`,
  `PATIENTS.FINDONE`, `PATIENTS.CREATE` và `PATIENTS.UPDATE`.
- Không hard-code role trong `PatientController` hoặc `PatientService`.

### 3.6 Thành phần dùng lại

Các thành phần hiện có và không dự kiến sửa:

- `EnsurePermission`: tự suy ra `PATIENTS.*` từ Controller/action.
- `ApiResponse::resource()`: response một Patient.
- `ApiResponse::paginated()`: response danh sách kèm `meta`.
- Exception Handler: chuẩn hóa lỗi 401, 403, 404 và 422.

### 3.7 Thành phần chưa có

- `PatientController`.
- `PatientService`.
- `ListPatientsRequest`.
- `StorePatientRequest`.
- `UpdatePatientRequest`.
- `PatientResource`.
- Route `/api/patients`.
- `PatientTest`.

## 4. API contract dự kiến

| Method | Endpoint | Action | Permission | Thành công |
|---|---|---|---|---:|
| GET | `/api/patients` | `index` | `PATIENTS.FINDALL` | 200 |
| POST | `/api/patients` | `store` | `PATIENTS.CREATE` | 201 |
| GET | `/api/patients/{patient}` | `show` | `PATIENTS.FINDONE` | 200 |
| PUT/PATCH | `/api/patients/{patient}` | `update` | `PATIENTS.UPDATE` | 200 |
| DELETE | `/api/patients/{patient}` | `destroy` | `PATIENTS.DELETE` | 200 |

Route model binding chỉ tìm bản ghi chưa bị soft delete. Patient không tồn tại hoặc đã bị
soft delete sẽ trả envelope 404 chuẩn.

### 4.1 Query list

```text
q         nullable string, max 255
page      nullable integer, min 1
per_page  nullable integer, min 1, max 100
```

Ví dụ:

```http
GET /api/patients?q=nguyen&per_page=15&page=1
GET /api/patients?q=0901234567
GET /api/patients?q=BN-000001
```

Response dự kiến:

```json
{
  "success": true,
  "message": "Patients retrieved",
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

### 4.2 Body create

Client không gửi `code`:

```json
{
  "full_name": "Nguyen Van An",
  "gender": "male",
  "date_of_birth": "1995-05-20",
  "phone": "0901234567",
  "email": "an@example.com",
  "address": "Ho Chi Minh City"
}
```

Response dự kiến tự có mã:

```json
{
  "success": true,
  "message": "Patient created",
  "data": {
    "id": 1,
    "code": "BN-000001",
    "full_name": "Nguyen Van An",
    "gender": "male",
    "date_of_birth": "1995-05-20",
    "phone": "0901234567",
    "email": "an@example.com",
    "address": "Ho Chi Minh City",
    "created_at": "2026-08-10T00:00:00.000000Z",
    "updated_at": "2026-08-10T00:00:00.000000Z"
  }
}
```

### 4.3 Body update

Cho phép cập nhật từng phần nhưng không cho cập nhật `code`:

```json
{
  "phone": "0911111111",
  "address": null
}
```

Body rỗng trả 422:

```json
{
  "success": false,
  "message": "At least one patient field must be provided.",
  "errors": {
    "patient": ["At least one patient field must be provided."]
  }
}
```

### 4.4 Delete

Response:

```json
{
  "success": true,
  "message": "Patient deleted",
  "data": null
}
```

Database chỉ cập nhật `deleted_at`; dòng Patient vẫn tồn tại khi query bằng
`Patient::withTrashed()`.

## 5. Thiết kế Model và query scope

### 5.1 Patient model

Model dự kiến:

- Dùng `HasFactory` và `SoftDeletes`.
- Fillable: `code`, `full_name`, `gender`, `date_of_birth`, `phone`, `email`, `address`.
- Cast `date_of_birth` thành `date`.
- Cast `deleted_at` thành `datetime` hoặc để trait SoftDeletes quản lý.
- Khai báo hằng gender: `male`, `female`, `other` để Factory và validation dùng chung.

### 5.2 Query scope search

Chữ ký dự kiến:

```php
scopeSearch(Builder $query, ?string $term): Builder
```

Logic:

```text
q rỗng
  -> giữ nguyên query

q có giá trị
  -> trim term
  -> group WHERE để không phá các filter tương lai
  -> full_name ILIKE %term%
     OR phone ILIKE %term%
     OR code ILIKE %term%
```

Production PostgreSQL dùng `ILIKE` đúng yêu cầu. Test suite hiện dùng SQLite nên scope sẽ có
fallback `LIKE` cho SQLite; mục đích chỉ để test portable, không thay đổi truy vấn production.

Ví dụ Service sử dụng scope:

```php
Patient::query()
    ->search($filters['q'] ?? null)
    ->orderByDesc('id')
    ->paginate($filters['per_page'] ?? 15);
```

Search không được viết lặp lại trong Controller hoặc Service.

## 6. Thiết kế tự sinh code

Định dạng đề xuất:

```text
BN-000001
BN-000002
BN-000003
```

Mã được sinh trong `PatientService::create()`, không sinh trong Controller/Form Request.

Phương án đề xuất để an toàn race-condition và chạy được cả PostgreSQL lẫn SQLite test:

1. Mở `DB::transaction()`.
2. Insert Patient với một mã tạm duy nhất nội bộ; mã tạm không nhận từ client.
3. Lấy primary key `id` do database sinh.
4. Đổi code thành `BN-` + `id` được pad tối thiểu 6 chữ số.
5. Save và trả model đã refresh.
6. Unique constraint `patients.code` là hàng rào cuối.

Ví dụ kết quả:

```text
id=1      -> BN-000001
id=125    -> BN-000125
id=1000000 -> BN-1000000
```

Ưu điểm:

- Không dùng `max(code) + 1`, nên không bị hai request cùng lấy một số.
- Không tái sử dụng mã của Patient đã soft delete.
- Portable giữa PostgreSQL production và SQLite feature test.
- Client không thể chọn hoặc sửa mã Patient.

## 7. Form Request dự kiến

### 7.1 ListPatientsRequest

Rules:

```text
q         nullable|string|max:255
page      nullable|integer|min:1
per_page  nullable|integer|min:1|max:100
```

### 7.2 StorePatientRequest

Rules dự kiến:

```text
code           prohibited
full_name      required|string|max:255
gender         required|in:male,female,other
date_of_birth  required|date|before_or_equal:today
phone          required|string|max:20
email          nullable|email|max:255|unique:patients,email
address        nullable|string|max:255
```

Không đặt `phone` unique. `email` được giữ unique theo quyết định đã duyệt.

### 7.3 UpdatePatientRequest

Rules dự kiến:

```text
code           prohibited
full_name      sometimes|required|string|max:255
gender         sometimes|required|in:male,female,other
date_of_birth  sometimes|required|date|before_or_equal:today
phone          sometimes|required|string|max:20
email          sometimes|nullable|email|max:255|unique:patients,email (ignore Patient hiện tại)
address        sometimes|nullable|string|max:255
```

After-validator chặn body không có field Patient và trả lỗi `errors.patient`.

`authorize()` của các request trả true vì RBAC do `EnsurePermission` chịu trách nhiệm.

## 8. PatientResource dự kiến

Resource chỉ định hình output, không query database:

```text
id
code
full_name
gender
date_of_birth (Y-m-d)
phone
email
address
created_at (ISO-8601)
updated_at (ISO-8601)
```

Không trả `deleted_at` trong CRUD thông thường.

## 9. PatientService dự kiến

Chữ ký:

```php
paginate(array $filters): LengthAwarePaginator
create(array $data): Patient
load(Patient $patient): Patient
update(Patient $patient, array $data): Patient
delete(Patient $patient): void
```

Trách nhiệm:

- `paginate`: gọi `Patient::search()`, sort `id DESC`, paginate mặc định 15.
- `create`: transaction và tự sinh code.
- `load`: trả Patient phục vụ detail, không chứa logic response.
- `update`: không cho thay đổi code; chỉ dùng dữ liệu đã validate.
- `delete`: gọi `$patient->delete()` để soft delete.

Không đặt validation format trong Service; format validation thuộc Form Request.

## 10. PatientController dự kiến

Controller mỏng, không query database và không hard-code role:

```php
index(ListPatientsRequest $request): JsonResponse
store(StorePatientRequest $request): JsonResponse
show(Patient $patient): JsonResponse
update(UpdatePatientRequest $request, Patient $patient): JsonResponse
destroy(Patient $patient): JsonResponse
```

Message:

```text
Patients retrieved
Patient created
Patient retrieved
Patient updated
Patient deleted
```

HTTP status:

```text
index   200
store   201
show    200
update  200
destroy 200
```

## 11. Route dự kiến

Thêm import `PatientController`, sau đó thêm trong group hiện có:

```php
Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
    Route::apiResource('patients', PatientController::class);
});
```

Không tạo route public và không tạo route restore vì task hiện tại chưa yêu cầu.

## 12. Xử lý migration đã chạy

Migration Patient hiện đã chạy nhưng bảng có 0 dữ liệu. Sau khi được duyệt, trước khi sửa
migration phải audit lại:

```bash
docker compose exec app php artisan migrate:status
docker compose exec db psql -U clinic -d clinic_app \
  -c 'select count(*) from patients;'
```

### Phương án ưu tiên nếu vẫn là migration mới nhất và bảng vẫn rỗng

```bash
docker compose exec app php artisan migrate:rollback --step=1
```

Sau đó mới chỉnh migration hiện tại và chạy lại:

```bash
docker compose exec app php artisan migrate
```

### Phương án an toàn nếu đã có dữ liệu hoặc có migration mới phụ thuộc Patients

- Không rollback.
- Không sửa lịch sử migration đã chạy.
- Tạo corrective migration nếu cần thay đổi schema sau khi đã có dữ liệu.

Không dùng `migrate:fresh` vì sẽ xóa toàn bộ database.

## 13. Lệnh scaffold dự kiến sau khi được duyệt

Không tạo lại Model/Migration/Factory vì ba file đã tồn tại.

Các lệnh dự kiến dùng UID/GID host để file không thuộc `root`:

```bash
docker compose exec --user "$(id -u):$(id -g)" app \
  php artisan make:controller PatientController --api

docker compose exec --user "$(id -u):$(id -g)" app \
  php artisan make:request Patient/ListPatientsRequest

docker compose exec --user "$(id -u):$(id -g)" app \
  php artisan make:request Patient/StorePatientRequest

docker compose exec --user "$(id -u):$(id -g)" app \
  php artisan make:request Patient/UpdatePatientRequest

docker compose exec --user "$(id -u):$(id -g)" app \
  php artisan make:resource PatientResource

docker compose exec --user "$(id -u):$(id -g)" app \
  php artisan make:test PatientTest
```

`PatientService.php` sẽ được tạo thủ công trong `app/Services` theo kiến trúc B.

## 14. Kế hoạch Feature Test

Test dùng `RefreshDatabase`, `RoleSeeder`, `RbacSeeder` và Sanctum.

### 14.1 CRUD và code tự sinh

- RECEPTIONIST tạo Patient thành công, HTTP 201.
- Request không gửi code nhưng response có `BN-000001`.
- Tạo Patient thứ hai có code khác và tăng theo ID.
- Xem danh sách, xem chi tiết, cập nhật thành công.
- ADMIN soft delete Patient thành công.
- `assertSoftDeleted('patients', ...)` pass.
- Dòng vẫn tồn tại qua `Patient::withTrashed()`.
- GET Patient sau khi delete trả 404.

### 14.2 Search và pagination

- Search một phần `full_name`, không phân biệt hoa/thường.
- Search theo một phần hoặc toàn bộ `phone`.
- Search theo `code` đầy đủ.
- `q` không khớp trả data rỗng.
- `per_page` hoạt động.
- Response có đúng `meta`: current_page, from, last_page, per_page, to, total.
- Search không trả Patient đã soft delete.

### 14.3 Validation

- Thiếu field bắt buộc trả 422 theo từng field.
- Gender ngoài `male|female|other` trả 422.
- Ngày sinh trong tương lai trả 422.
- Email sai format trả 422.
- Address vượt 255 ký tự trả 422.
- Email trùng Patient khác trả 422.
- Client gửi `code` khi create/update trả 422.
- Update body rỗng trả `errors.patient`.
- `email` và `address` null được chấp nhận.

### 14.4 RBAC

- ADMIN: toàn bộ CRUD, đặc biệt DELETE thành công.
- RECEPTIONIST: FINDALL/FINDONE/CREATE/UPDATE thành công; DELETE trả 403.
- DOCTOR: FINDALL/FINDONE thành công; CREATE/UPDATE/DELETE trả 403.
- CASHIER: FINDALL/FINDONE thành công; CREATE/UPDATE/DELETE trả 403.
- PHARMACIST: FINDALL trả 403.
- Không token: 401.
- ID không tồn tại: 404 envelope chuẩn.

## 15. Lệnh kiểm tra dự kiến sau triển khai

```bash
docker compose exec app php artisan route:list --path=api/patients
docker compose exec app php artisan test --filter=PatientTest
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

Kiểm tra schema PostgreSQL:

```bash
docker compose exec db psql -U clinic -d clinic_app -c '\d patients'
```

Tiêu chí hoàn tất:

- Đủ 5 routes Patient.
- PatientTest xanh.
- Toàn bộ test hồi quy xanh.
- Các file thay đổi pass Pint và PHP syntax.
- `git diff --check` sạch.
- Không có file mới thuộc `root:root`.

## 16. Thứ tự thực thi sau khi được duyệt

1. Áp dụng quyết định schema: giữ unique email và string nullable cho address.
2. Audit lại trạng thái/data bảng Patients.
3. Rollback riêng migration Patient nếu vẫn an toàn; nếu không, dùng corrective migration.
4. Hoàn thiện migration, Model và Factory.
5. Chạy lại migration và kiểm tra constraint/index.
6. Tạo Form Requests và PatientResource.
7. Tạo PatientService với query scope và code tự sinh.
8. Tạo PatientController mỏng.
9. Đăng ký route trong middleware auth + permission.
10. Viết PatientTest.
11. Chạy test riêng, test toàn bộ, Pint, schema audit và route audit.
12. Báo kết quả; không tự commit.

## 17. Các điểm người review đã xác nhận

- [x] Giữ unique constraint của `email`.
- [x] Giữ `string nullable` cho `address`.
- [x] Code có định dạng `BN-` + ID pad tối thiểu 6 chữ số.
- [x] Không hỗ trợ restore/force-delete trong task này.
- [x] Rollback riêng migration Patient nếu lúc thực thi bảng vẫn rỗng và migration vẫn là
      migration mới nhất; nếu không thì dùng corrective migration.
- [x] RBAC: ADMIN toàn quyền; RECEPTIONIST đọc/CREATE/UPDATE; DOCTOR/CASHIER read-only;
      DELETE chỉ ADMIN.
- [x] DELETE là soft delete.
