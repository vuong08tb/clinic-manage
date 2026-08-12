# Kế hoạch chuẩn hóa Form Request, API Resource và JSON Envelope

> Trạng thái: đã được duyệt và triển khai ngày 2026-08-06. Toàn bộ test suite đạt
> 22 tests / 72 assertions và Laravel Pint đạt chuẩn trên 50 files.

## 1. Mục tiêu

Chuẩn hóa toàn bộ request/response của API theo kiến trúc Controller + Service trong
`skills/backend.md`:

```text
Route -> Middleware -> Form Request -> Controller -> Service -> Model -> API Resource -> JSON response
```

Kết quả cần đạt:

- Mọi API ghi dữ liệu sử dụng Form Request để authorize và validate input.
- Mọi dữ liệu trả cho client được định hình bằng API Resource hoặc Resource Collection.
- Response thành công có envelope thống nhất `success`, `message`, `data`.
- Response lỗi có envelope thống nhất `success`, `message`, `errors`.
- Response phân trang có thêm `meta` ở top-level.
- Mọi request `/api/*`, kể cả exception, luôn nhận JSON; không redirect và không trả HTML.
- Di chuyển trách nhiệm tạo response khỏi `app/Http/Concerns` sang `app/Http/Responses`.

Kế hoạch đã được review và phê duyệt trước khi implementation bắt đầu.

## 2. Kết quả kiểm tra hiện trạng

### 2.1. Thành phần đã có

- `LoginRequest` đã validate input của API login.
- `UserResource` đã giới hạn các trường public và định hình role/permissions.
- `AuthController` đang mỏng: nhận request, gọi `AuthService`, trả response.
- `bootstrap/app.php` đã dùng `shouldRenderJsonWhen()` cho `/api/*`.
- `ValidationException` đã trả 422 JSON với errors theo field.
- `AuthenticationException` đã trả 401 JSON.
- Auth feature tests đang kiểm tra envelope của login, validation và unauthenticated.

### 2.2. Điểm chưa thống nhất

- `app/Http/Concerns/ApiResponse.php` đang chứa cả `ok()` và `fail()`; `fail()` mặc
  định mọi lỗi là 422 dù lỗi API có thể là 400, 401, 403, 404, 409 hoặc 500.
- Controller đang đặt một `JsonResource` bên trong mảng rồi bọc lại bằng
  `response()->json()`. Cách này có thể serialize được nhưng chưa dùng API Resource làm
  tầng response chính theo convention trong `skills/backend.md`.
- Middleware `EnsurePermission` tự dựng JSON 403, tạo thêm một nơi định nghĩa envelope
  ngoài response layer và exception handler.
- Exception handler mới định nghĩa rõ 401 và 422; chưa có contract tường minh cho:
  - authorization/permission 403;
  - model hoặc route không tồn tại 404;
  - HTTP method không được hỗ trợ 405;
  - lỗi hệ thống 500 trong môi trường production.
- Chưa có Resource Collection/pagination response để bảo đảm `meta` nằm đúng top-level.
- Chưa có test contract dùng chung cho success, validation, auth, forbidden, not found và
  pagination.

## 3. JSON contract đề xuất

### 3.1. Thành công với một resource

```json
{
  "success": true,
  "message": "Patient retrieved",
  "data": {
    "id": 1,
    "code": "BN000001"
  }
}
```

### 3.2. Thành công không có payload

Áp dụng cho logout hoặc thao tác không cần trả resource:

```json
{
  "success": true,
  "message": "Logged out",
  "data": null
}
```

### 3.3. Danh sách không phân trang

```json
{
  "success": true,
  "message": "Patients retrieved",
  "data": []
}
```

### 3.4. Danh sách phân trang

```json
{
  "success": true,
  "message": "Patients retrieved",
  "data": [],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 68
  }
}
```

Quyết định đề xuất: chỉ expose `meta` theo contract của dự án, không expose `links` mặc
định của Laravel trừ khi frontend xác nhận cần URL điều hướng trang. Việc này giúp response
không có thêm field ngoài đặc tả `success/message/data/meta`.

### 3.5. Validation hoặc business rule — 422

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

- Form Request validation trả errors theo field.
- Service có thể ném `ValidationException::withMessages()` cho business rule và vẫn dùng
  cùng contract.
- Response lỗi không có `data`; response thành công không có `errors`.

### 3.6. Các lỗi API khác

| HTTP | Trường hợp | Envelope |
|---|---|---|
| 401 | Chưa đăng nhập, token sai, credential sai, user inactive | `success/message/errors` |
| 403 | Thiếu permission hoặc không được phép thao tác resource | `success/message/errors` |
| 404 | Route/model không tồn tại | `success/message/errors` |
| 405 | Sai HTTP method | `success/message/errors` |
| 422 | Validation hoặc business rule không hợp lệ | `success/message/errors` |
| 500 | Lỗi hệ thống ngoài dự kiến | `success/message/errors` |

Với lỗi không có chi tiết theo field, `errors` là object/array rỗng. Ở production, lỗi 500
chỉ trả thông báo chung và không lộ stack trace, SQL, path hoặc secret.

## 4. Thiết kế response layer

### 4.1. Folder và trách nhiệm

Tạo namespace:

```text
app/Http/Responses
```

Thành phần đề xuất:

```text
app/Http/Responses/ApiResponse.php
```

`ApiResponse` là response factory/service chuyên tạo envelope và không chứa validation hay
business logic. Các method dự kiến:

- `success(mixed $data, string $message, int $status = 200)`;
- `resource(JsonResource $resource, string $message, int $status = 200)`;
- `collection(AnonymousResourceCollection $collection, string $message, int $status = 200)`;
- `paginated(AnonymousResourceCollection $collection, string $message, int $status = 200)`;
- `error(string $message, array $errors = [], int $status = 400)`.

Không giữ default lỗi 422 trong helper tổng quát. Status 422 chỉ được chọn bởi validation
handler hoặc nơi ném `ValidationException`.

### 4.2. Quan hệ với API Resource

- Resource chịu trách nhiệm biến Model thành data public.
- Response layer chịu trách nhiệm thêm `success`, `message`, status code và pagination
  `meta`.
- Controller không tự tạo mảng field từ Model và không tự dựng envelope.
- Resource không chứa business rule, query hoặc kiểm tra permission.

Ví dụ mục tiêu về mặt thiết kế:

```php
$patient = $this->service->find($id);

return $this->responses->resource(
    new PatientResource($patient),
    'Patient retrieved',
);
```

Hoặc nếu thống nhất dùng static factory:

```php
return ApiResponse::resource(
    new PatientResource($patient),
    'Patient retrieved',
);
```

Trong bước implementation sẽ chọn đúng một cách và dùng nhất quán. Đề xuất ưu tiên
response factory có static named constructors để Controller ngắn và không cần lặp dependency
injection ở mọi Controller.

### 4.3. Xử lý file Concerns hiện tại

Sau khi toàn bộ call site chuyển sang response layer mới:

- xóa `app/Http/Concerns/ApiResponse.php`;
- bỏ `use ApiResponse` khỏi Controller;
- không duy trì song song hai helper để tránh hai JSON contract khác nhau.

Đây là thao tác xóa file có chủ đích nhưng chỉ thực hiện sau khi review kế hoạch.

## 5. Chuẩn Form Request

Mọi endpoint ghi (`POST`, `PUT`, `PATCH`) phải có Form Request riêng theo use case:

```text
app/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/Patient/StorePatientRequest.php
app/Http/Requests/Patient/UpdatePatientRequest.php
```

Quy ước:

- `authorize()` chỉ xử lý authorization gắn với resource khi cần; RBAC tổng quát vẫn do
  `EnsurePermission` chịu trách nhiệm.
- `rules()` chỉ khai báo validation input.
- Controller chỉ dùng `$request->validated()`.
- Không dùng `$request->all()` để truyền dữ liệu vào Service.
- Chuẩn hóa message theo ngôn ngữ đã thống nhất của API; không hard-code message validation
  rải rác nếu có thể đặt trong language file.
- Business rule cần database/transaction thuộc Service và ném `ValidationException`, không
  đặt trong Form Request.

Trong phạm vi source hiện tại chỉ có API ghi login, nên `LoginRequest` sẽ được giữ và rà lại
contract; các Form Request nghiệp vụ được tạo cùng từng resource ở task tương ứng.

## 6. Chuẩn API Resource

- Mỗi resource nghiệp vụ có một `JsonResource` riêng.
- Chỉ expose field được phép công khai; không trả password, token hash hoặc secret.
- Relationship dùng `whenLoaded()` để tránh query ngầm và N+1.
- Controller/Service phải eager load relationship cần cho response.
- Date/time và decimal có format nhất quán.
- Collection phân trang dùng Resource Collection kết hợp response layer để đưa pagination
  vào `meta` top-level.
- Không để Laravel tự thêm một lớp `data` thứ hai bên trong envelope.

`UserResource` hiện tại sẽ được rà lại để thay cách kiểm tra `relationLoaded()` thủ công bằng
pattern phù hợp với `whenLoaded()` nếu kết quả JSON không thay đổi.

## 7. Chuẩn hóa exception cho `/api/*`

Mở rộng `withExceptions()` trong `bootstrap/app.php` theo nguyên tắc:

1. Chỉ áp dụng custom JSON envelope cho request `api/*`.
2. Web route vẫn giữ hành vi HTML/redirect mặc định.
3. Dùng response layer chung để dựng lỗi, không lặp mảng JSON trong từng handler.
4. Map tối thiểu:
   - `ValidationException` -> 422;
   - `AuthenticationException` -> 401;
   - authorization/permission exception -> 403;
   - `ModelNotFoundException` và `NotFoundHttpException` -> 404;
   - `MethodNotAllowedHttpException` -> 405;
   - exception ngoài dự kiến -> 500 với message an toàn trong production.
5. `EnsurePermission` nên ném exception HTTP 403 thay vì tự dựng JSON; exception handler sẽ
   đảm bảo cùng envelope với mọi lỗi authorization khác.
6. Không biến lỗi web thành JSON và không để API guest redirect sang trang login.

## 8. Phạm vi file dự kiến thay đổi sau khi được duyệt

### Tạo mới

- `app/Http/Responses/ApiResponse.php`
- Các Resource Collection cần thiết cho pagination khi resource danh sách đầu tiên được
  triển khai.
- Feature test contract cho response/envelope.

### Chỉnh sửa

- `app/Http/Controllers/AuthController.php`
- `app/Http/Resources/UserResource.php` nếu cần chuẩn hóa relation handling.
- `app/Http/Middleware/EnsurePermission.php`
- `bootstrap/app.php`
- `tests/Feature/AuthTest.php`
- `tests/Feature/EnsurePermissionTest.php`
- README hoặc API documentation nếu contract cuối cùng thay đổi.

### Xóa sau khi migration call site hoàn tất

- `app/Http/Concerns/ApiResponse.php`

Không sửa business logic trong `AuthService`, schema database hoặc permission matrix trong
task chuẩn hóa response này.

## 9. Trình tự triển khai đề xuất

1. Chốt JSON contract và quyết định có/không giữ pagination `links`.
2. Viết contract tests cho envelope trước khi thay implementation.
3. Tạo `app/Http/Responses/ApiResponse.php`.
4. Chuyển AuthController sang response layer mới, vẫn dùng `UserResource`.
5. Chuyển exception handlers sang response layer và bổ sung 403/404/405/500.
6. Chuyển `EnsurePermission` sang ném exception để handler định dạng JSON thống nhất.
7. Thêm test pagination bằng probe resource/paginator, không phụ thuộc resource nghiệp vụ
   chưa được triển khai.
8. Xóa Concerns cũ khi không còn reference.
9. Chạy formatter, test mục tiêu và toàn bộ test suite.
10. Kiểm tra thủ công content type và body của các lỗi API; đồng thời xác nhận web route vẫn
    trả HTML bình thường.

## 10. Kế hoạch kiểm thử

### Success/resource

- Login 200 có đúng `success/message/data` và không có lớp `data.data`.
- Me 200 được định hình bởi `UserResource`.
- Logout 200 có `data: null`.

### Validation và exception

- Login thiếu field trả 422 JSON và `errors` theo field.
- Credential sai hoặc token sai trả 401 JSON.
- Thiếu permission trả 403 JSON.
- Model không tồn tại trả 404 JSON.
- API route không tồn tại trả 404 JSON, không trả HTML.
- Sai method trên API route trả 405 JSON.
- Exception giả lập trả 500 JSON an toàn khi `APP_DEBUG=false`.

### Pagination

- Response có đúng `success/message/data/meta`.
- `meta` có đủ `current_page`, `from`, `last_page`, `per_page`, `to`, `total`.
- Không xuất hiện `links` nếu quyết định contract là không giữ links.
- Không có double wrapping `data.data`.

### Web/API boundary

- `/api/*` luôn có `Content-Type: application/json` khi lỗi.
- Web route vẫn có thể render HTML và redirect theo hành vi Laravel bình thường.

Các lệnh nghiệm thu dự kiến:

```bash
docker compose exec app php artisan test --filter=AuthTest
docker compose exec app php artisan test --filter=EnsurePermissionTest
docker compose exec app php artisan test --filter=ApiResponse
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

## 11. Tiêu chí hoàn thành

- Không còn reference tới `App\Http\Concerns\ApiResponse`.
- Controller không tự dựng JSON envelope.
- Form Request được dùng cho mọi API ghi đang tồn tại.
- API Resource/Resource Collection định hình mọi payload model.
- Success response đúng `success/message/data`.
- Validation/error response đúng `success/message/errors` và status code tương ứng.
- Pagination response có `meta` top-level theo contract đã duyệt.
- Mọi exception của `/api/*` trả JSON, không HTML hoặc redirect.
- Không lộ exception detail nhạy cảm khi production.
- Formatter và toàn bộ test suite chạy thành công.

## 12. Các điểm cần leader xác nhận

1. Đồng ý tạo folder `app/Http/Responses` và xóa trait trong `app/Http/Concerns` sau khi
   chuyển hết call site.
2. Đồng ý pagination chỉ trả `meta`, không trả `links` mặc định của Laravel.
3. Đồng ý error envelope không có `data`, success envelope không có `errors`.
4. Đồng ý lỗi business tiếp tục dùng HTTP 422 thông qua `ValidationException`, còn helper
   lỗi tổng quát không mặc định status 422.
5. Đồng ý exception 500 production chỉ trả message chung, không trả chi tiết nội bộ.
