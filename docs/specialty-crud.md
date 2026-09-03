# CRUD Specialty — Demo hướng triển khai từng bước

## 1. Mục tiêu

Triển khai T1.14 theo `skills/backend.md` và dùng Specialty làm resource CRUD mẫu cho các
module tiếp theo.

Yêu cầu:

- Migration `specialties` có `name` unique, `description` nullable và timestamps.
- CRUD Specialty theo kiến trúc B: Controller + Service.
- Mọi input ghi đi qua Form Request.
- Mọi output model đi qua SpecialtyResource.
- Response dùng envelope chuẩn qua ApiResponse.
- RBAC dùng `SPECIALTIES.*`, không hard-code role trong Controller.
- ADMIN được đọc/ghi; RECEPTIONIST và DOCTOR chỉ được đọc theo permission matrix hiện tại.
- Viết feature test cho CRUD, validation, pagination, 401, 403 và 404.

Luồng xử lý:

```text
Route
  -> auth:sanctum
  -> EnsurePermission
  -> Form Request
  -> SpecialtyController
  -> SpecialtyService
  -> Specialty Model
  -> SpecialtyResource
  -> ApiResponse
```

Tài liệu này chỉ mô tả hướng thực hiện. Chưa tạo code Specialty cho đến khi được review và
phê duyệt.

## 2. Audit code hiện tại

### Đã có sẵn

- Branch hiện tại: `task/vuongth/T1.14-CRUD-Specialties`.
- Working tree sạch tại thời điểm lập tài liệu.
- `ApiResponse` đã hỗ trợ resource đơn, collection và pagination meta.
- Exception Handler đã trả JSON cho 401, 403, 404, 405, 422 và 500.
- `EnsurePermission` đã tự map Controller/action thành permission.
- `config/rbac.php` đã có:

```text
SpecialtyController -> SPECIALTIES
index                -> FINDALL
store                -> CREATE
show                 -> FINDONE
update               -> UPDATE
destroy              -> DELETE
```

- Permission catalog đã có đủ:

```text
SPECIALTIES.FINDALL
SPECIALTIES.CREATE
SPECIALTIES.FINDONE
SPECIALTIES.UPDATE
SPECIALTIES.DELETE
```

- RbacSeeder hiện gán:

| Role | FINDALL | FINDONE | CREATE | UPDATE | DELETE |
|---|:---:|:---:|:---:|:---:|:---:|
| ADMIN | ✓ | ✓ | ✓ | ✓ | ✓ |
| RECEPTIONIST | ✓ | ✓ |  |  |  |
| DOCTOR | ✓ | ✓ |  |  |  |
| PHARMACIST |  |  |  |  |  |
| CASHIER |  |  |  |  |  |

### Chưa có

- Migration và bảng `specialties`.
- Specialty model/factory.
- SpecialtyController và SpecialtyService.
- Form Request cho list/store/update.
- SpecialtyResource.
- Route `/api/specialties`.
- Specialty feature test.

### Không cần sửa nếu test xác nhận đúng

- Permission data migration.
- RbacSeeder.
- `config/rbac.php`.
- EnsurePermission.
- ApiResponse và Exception Handler.

## 3. API contract dự kiến

| Method | Endpoint | Action | Permission | Status thành công |
|---|---|---|---|---:|
| GET | `/api/specialties` | `index` | `SPECIALTIES.FINDALL` | 200 |
| POST | `/api/specialties` | `store` | `SPECIALTIES.CREATE` | 201 |
| GET | `/api/specialties/{specialty}` | `show` | `SPECIALTIES.FINDONE` | 200 |
| PUT/PATCH | `/api/specialties/{specialty}` | `update` | `SPECIALTIES.UPDATE` | 200 |
| DELETE | `/api/specialties/{specialty}` | `destroy` | `SPECIALTIES.DELETE` | 200 |

Route Model Binding xử lý `{specialty}`. ID không tồn tại sẽ đi qua Exception Handler và trả
404 JSON.

## 4. Bước 1 — Tạo migration specialties

Lệnh dự kiến:

```bash
docker compose exec app php artisan make:migration create_specialties_table
```

Schema mẫu:

```php
Schema::create('specialties', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

`down()`:

```php
Schema::dropIfExists('specialties');
```

Lý do:

- `name` dùng string và unique ở database làm hàng rào cuối.
- `description` dùng text vì nội dung có thể dài và không bắt buộc.
- Không dùng soft delete vì task yêu cầu CRUD catalog thông thường và chưa yêu cầu
  `deleted_at`.

Kiểm tra migration:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:status
```

## 5. Bước 2 — Tạo Specialty model và factory

Lệnh dự kiến:

```bash
docker compose exec app php artisan make:model Specialty --factory
```

Model mẫu:

```php
#[Fillable(['name', 'description'])]
class Specialty extends Model
{
    use HasFactory;
}
```

Không đặt validation hoặc permission trong Model.

Factory dùng cho test:

```php
return [
    'name' => fake()->unique()->words(2, true),
    'description' => fake()->optional()->sentence(),
];
```

## 6. Bước 3 — Tạo Form Request

Tạo ba request:

```text
app/Http/Requests/Specialty/ListSpecialtiesRequest.php
app/Http/Requests/Specialty/StoreSpecialtyRequest.php
app/Http/Requests/Specialty/UpdateSpecialtyRequest.php
```

Lệnh dự kiến:

```bash
docker compose exec app php artisan make:request Specialty/ListSpecialtiesRequest
docker compose exec app php artisan make:request Specialty/StoreSpecialtyRequest
docker compose exec app php artisan make:request Specialty/UpdateSpecialtyRequest
```

### ListSpecialtiesRequest

Query đề xuất:

```text
q         nullable string, max 255
page      nullable integer, min 1
per_page  nullable integer, min 1, max 100
```

Mục tiêu: tìm theo tên và trả pagination thống nhất. Mặc định `per_page=15`.

### StoreSpecialtyRequest

Rules mẫu:

```php
return [
    'name' => ['required', 'string', 'max:255', 'unique:specialties,name'],
    'description' => ['nullable', 'string', 'max:2000'],
];
```

Body:

```json
{
  "name": "Cardiology",
  "description": "Diagnosis and treatment of cardiovascular diseases."
}
```

### UpdateSpecialtyRequest

Rules mẫu:

```php
return [
    'name' => [
        'sometimes',
        'required',
        'string',
        'max:255',
        Rule::unique('specialties', 'name')->ignore($this->route('specialty')),
    ],
    'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
];
```

Thêm after-validator để body update rỗng trả 422:

```text
errors.specialty = At least one specialty field must be provided.
```

Tất cả request:

- `authorize()` trả true vì EnsurePermission chịu trách nhiệm RBAC.
- Có custom messages bằng tiếng Anh, nhất quán với API hiện tại.
- Controller chỉ truyền `$request->validated()` vào Service.

## 7. Bước 4 — Tạo SpecialtyResource

Lệnh dự kiến:

```bash
docker compose exec app php artisan make:resource SpecialtyResource
```

Output mẫu:

```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'description' => $this->description,
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),
];
```

Resource chỉ định hình output, không query database hoặc chứa business rule.

Response tạo thành công:

```json
{
  "success": true,
  "message": "Specialty created",
  "data": {
    "id": 1,
    "name": "Cardiology",
    "description": "Diagnosis and treatment of cardiovascular diseases.",
    "created_at": "2026-08-07T01:00:00.000000Z",
    "updated_at": "2026-08-07T01:00:00.000000Z"
  }
}
```

## 8. Bước 5 — Tạo SpecialtyService

File:

```text
app/Services/SpecialtyService.php
```

Method dự kiến:

```php
paginate(array $filters): LengthAwarePaginator
create(array $data): Specialty
update(Specialty $specialty, array $data): Specialty
delete(Specialty $specialty): void
```

### paginate

- Query Specialty.
- Nếu có `q`, tìm theo name.
- Sắp xếp mặc định `name ASC` để catalog ổn định.
- Paginate theo `per_page`, mặc định 15.

Pseudo-code:

```php
$query = Specialty::query();

if ($term !== null) {
    $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
}

return $query->orderBy('name')->paginate($perPage);
```

### create/update/delete

```php
public function create(array $data): Specialty
{
    return Specialty::query()->create($data);
}

public function update(Specialty $specialty, array $data): Specialty
{
    $specialty->update($data);

    return $specialty->refresh();
}

public function delete(Specialty $specialty): void
{
    $specialty->delete();
}
```

Không cần transaction cho thao tác một bảng đơn giản. Khi T1.15 thêm Doctor và quy tắc xóa
chuyên khoa đang được tham chiếu, sẽ xử lý business rule/foreign key theo task đó.

## 9. Bước 6 — Tạo SpecialtyController

Lệnh dự kiến:

```bash
docker compose exec app php artisan make:controller SpecialtyController --api
```

Controller inject SpecialtyService và giữ mỏng.

### index

```php
$specialties = $this->service->paginate($request->validated());

return ApiResponse::paginated(
    SpecialtyResource::collection($specialties),
    'Specialties retrieved',
);
```

### store

```php
$specialty = $this->service->create($request->validated());

return ApiResponse::resource(
    new SpecialtyResource($specialty),
    'Specialty created',
    201,
);
```

### show

```php
return ApiResponse::resource(
    new SpecialtyResource($specialty),
    'Specialty retrieved',
);
```

### update

```php
$specialty = $this->service->update($specialty, $request->validated());

return ApiResponse::resource(
    new SpecialtyResource($specialty),
    'Specialty updated',
);
```

### destroy

```php
$this->service->delete($specialty);

return ApiResponse::success(message: 'Specialty deleted');
```

Controller không:

- query Eloquent trực tiếp;
- kiểm tra role name;
- validate thủ công;
- chứa unique rule;
- tự dựng mảng JSON envelope.

## 10. Bước 7 — Đăng ký route và RBAC

Trong group nghiệp vụ hiện tại:

```php
Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
    Route::apiResource('specialties', SpecialtyController::class);
});
```

EnsurePermission tự map:

```text
SpecialtyController@index   -> SPECIALTIES.FINDALL
SpecialtyController@store   -> SPECIALTIES.CREATE
SpecialtyController@show    -> SPECIALTIES.FINDONE
SpecialtyController@update  -> SPECIALTIES.UPDATE
SpecialtyController@destroy -> SPECIALTIES.DELETE
```

Không viết logic kiểu:

```php
if ($request->user()->role->name !== 'ADMIN') {
    abort(403);
}
```

Quyền đọc/ghi đến từ role_permissions đã seed.

## 11. Bước 8 — Viết SpecialtyTest

File:

```text
tests/Feature/SpecialtyTest.php
```

Dùng `RefreshDatabase`, seed RoleSeeder và RbacSeeder.

### Migration/model

- [ ] Bảng specialties tồn tại.
- [ ] Database unique index chặn name trùng.
- [ ] Description nhận null.

### Authentication

- [ ] Không token gọi index -> 401 JSON.

### ADMIN

- [ ] ADMIN index -> 200 và có pagination meta.
- [ ] ADMIN store hợp lệ -> 201.
- [ ] ADMIN show -> 200.
- [ ] ADMIN update -> 200.
- [ ] ADMIN destroy -> 200 và bản ghi bị xóa.

### Validation

- [ ] Store thiếu name -> 422 `errors.name`.
- [ ] Store name trùng -> 422 `errors.name`.
- [ ] Update thành name của specialty khác -> 422 `errors.name`.
- [ ] Update giữ nguyên name hiện tại -> 200.
- [ ] Update body rỗng -> 422 `errors.specialty`.
- [ ] Description vượt giới hạn -> 422 `errors.description`.
- [ ] Query `per_page>100` -> 422.

### RBAC matrix

- [ ] RECEPTIONIST index/show -> 200.
- [ ] RECEPTIONIST store/update/delete -> 403.
- [ ] DOCTOR index/show -> 200.
- [ ] DOCTOR store/update/delete -> 403.
- [ ] PHARMACIST index -> 403.
- [ ] CASHIER index -> 403.

### Not found/search/pagination

- [ ] Show ID không tồn tại -> 404 JSON.
- [ ] Update ID không tồn tại -> 404 JSON.
- [ ] Delete ID không tồn tại -> 404 JSON.
- [ ] Search `q` chỉ trả specialty phù hợp.
- [ ] Pagination có `meta.current_page/per_page/total/last_page`.
- [ ] Response không có double wrapping `data.data`.

### Regression

- [ ] AuthTest vẫn xanh.
- [ ] EnsurePermissionTest vẫn xanh.
- [ ] ApiResponseTest vẫn xanh.
- [ ] UserTest vẫn xanh.
- [ ] Toàn bộ suite và Pint xanh.

## 12. Bước 9 — Chạy và kiểm tra

Thứ tự lệnh dự kiến:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan route:list --path=api/specialties
docker compose exec app php artisan test --filter=SpecialtyTest
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

Postman smoke flow:

```text
ADMIN login
  -> create specialty
  -> list specialties
  -> show specialty
  -> update specialty
  -> delete specialty
  -> show deleted ID nhận 404

RECEPTIONIST login
  -> list/show nhận 200
  -> create nhận 403

DOCTOR login
  -> list/show nhận 200
  -> update nhận 403
```

## 13. File dự kiến tạo hoặc chỉnh sửa

### Tạo mới

- `database/migrations/<timestamp>_create_specialties_table.php`
- `app/Models/Specialty.php`
- `database/factories/SpecialtyFactory.php`
- `app/Http/Requests/Specialty/ListSpecialtiesRequest.php`
- `app/Http/Requests/Specialty/StoreSpecialtyRequest.php`
- `app/Http/Requests/Specialty/UpdateSpecialtyRequest.php`
- `app/Http/Resources/SpecialtyResource.php`
- `app/Services/SpecialtyService.php`
- `app/Http/Controllers/SpecialtyController.php`
- `tests/Feature/SpecialtyTest.php`

### Chỉnh sửa

- `routes/api.php`
- README/API documentation sau khi runtime contract được xác nhận.

### Không dự kiến chỉnh sửa

- `config/rbac.php`.
- Permission data migration.
- RbacSeeder.
- EnsurePermission.
- ApiResponse/Exception Handler.
- User CRUD/Auth code.

## 14. Các điểm cần xác nhận trước khi code

1. DELETE Specialty sẽ hard-delete và trả HTTP 200 với `data:null`.
2. Name unique theo PostgreSQL mặc định, tức phân biệt hoa/thường; `Cardiology` và
   `cardiology` được xem là hai giá trị khác nhau.
3. Description nullable, giới hạn validation đề xuất 2000 ký tự.
4. Index có search `q`, pagination mặc định 15 và tối đa 100 bản ghi/trang.
5. Index sắp xếp theo name tăng dần.
6. Resource trả cả `created_at` và `updated_at` theo ISO 8601.
7. PUT và PATCH tạm dùng chung partial-update rules, đồng nhất với User CRUD hiện tại.
8. Message và custom validation message dùng tiếng Anh.
9. Không tạo SpecialtySeeder trong T1.14; dữ liệu demo được tạo qua factory/test hoặc API.

## 15. Điều kiện hoàn thành

- Migration chạy/rollback đúng và có unique index cho name.
- Năm endpoint Specialty hoạt động đúng status/envelope.
- Controller mỏng, Service giữ thao tác dữ liệu.
- Store/update dùng Form Request và unique validation đúng.
- Output model dùng SpecialtyResource.
- Index có pagination meta và search.
- ADMIN đọc/ghi; RECEPTIONIST và DOCTOR chỉ đọc; role khác bị 403 theo matrix.
- Duplicate name trả 422 errors theo field.
- ID không tồn tại trả 404 JSON.
- Delete đúng semantics đã được duyệt.
- SpecialtyTest, regression suite và Pint đều pass.

