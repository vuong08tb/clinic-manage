# Kế hoạch triển khai API Login / Logout / Me

## 1. Mục tiêu và phạm vi

Triển khai xác thực API bằng Laravel Sanctum theo chuẩn Bearer token, tuân thủ kiến trúc B trong `skills/backend.md`:

```text
Route -> auth:sanctum -> Controller -> Service -> Model/Resource
```

Phạm vi gồm:

- `POST /api/login`: đăng nhập và cấp Sanctum personal access token.
- `POST /api/logout`: thu hồi token đang dùng.
- `GET /api/me`: trả thông tin user, role và danh sách permission.
- Không tạo API đăng ký công khai (`/api/register`).
- Không viết custom authentication middleware và không thay đổi logic của `EnsurePermission`.
- `logout` và `me` chỉ dùng middleware chuẩn `auth:sanctum`, không đi qua middleware `permission`.

## 2. Kết quả rà soát hiện trạng

### Thành phần đã có

- Dự án đang dùng Laravel 13 và đã khai báo `laravel/sanctum` trong `composer.json`.
- `composer.lock` đang khóa Sanctum ở phiên bản `v4.3.3`.
- Đã có `config/sanctum.php`.
- Đã có migration tạo bảng `personal_access_tokens`.
- Model `User` đã dùng trait `HasApiTokens`.
- `bootstrap/app.php` đã nạp `routes/api.php` và buộc request `/api/*` trả JSON.
- Schema role/permission, quan hệ `User -> Role` và seed ADMIN đã có.

Vì các thành phần cài đặt Sanctum đã tồn tại trong source, kế hoạch không cài lại package hoặc publish lại file. Khi môi trường Docker chạy, cần kiểm tra thêm trạng thái migration thực tế của database.

### Thành phần còn thiếu hoặc chưa đạt yêu cầu

- Chưa có `AuthController`, `AuthService`, `LoginRequest` và `UserResource`.
- Chưa khai báo route `login`, `logout`, `me`.
- Bảng `users` chưa có cột `is_active`, trong khi đặc tả yêu cầu tài khoản bị khóa phải đăng nhập thất bại với HTTP 401.
- `User`, `AdminSeeder` và `UserFactory` chưa khai báo trạng thái active.
- Chưa có response helper/envelope dùng chung cho auth.
- Exception API hiện mới được ép trả JSON, chưa bảo đảm envelope `success/message/errors` cho lỗi validation và unauthenticated.
- Chưa có feature test cho login/logout/me.
- Hiện chưa thể chạy kiểm tra runtime: host không có PHP trong `PATH` và Docker Desktop chưa chạy.

## 3. Hợp đồng API dự kiến

| Method | Endpoint | Bảo vệ | Kết quả chính |
|---|---|---|---|
| `POST` | `/api/login` | Public | Xác thực email/password, kiểm tra `is_active`, trả token và user |
| `POST` | `/api/logout` | `auth:sanctum` | Xóa `currentAccessToken()`, chỉ đăng xuất phiên/token hiện tại |
| `GET` | `/api/me` | `auth:sanctum` | Trả user kèm role và permission |

Input login:

```json
{
  "email": "admin@clinic.test",
  "password": "Admin@123"
}
```

Response login thành công dự kiến:

```json
{
  "success": true,
  "message": "Logged in",
  "data": {
    "token": "<plain-text-token-chỉ-trả-khi-vừa-tạo>",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Clinic Admin",
      "email": "admin@clinic.test",
      "is_active": true,
      "role": {
        "id": 1,
        "name": "ADMIN",
        "display_name": "Quản trị viên"
      },
      "permissions": ["USERS.FINDALL"]
    }
  }
}
```

Quy ước lỗi:

- Email/password không hợp lệ theo validation: HTTP 422, có `errors` theo field.
- User không tồn tại, sai mật khẩu hoặc `is_active=false`: cùng trả HTTP 401 với message chung `Invalid credentials` để tránh lộ trạng thái tài khoản.
- Thiếu/sai/hết hiệu lực Bearer token khi gọi `logout` hoặc `me`: HTTP 401.
- Mọi response tuân thủ envelope trong `skills/backend.md`.

Client gửi token bằng header:

```http
Authorization: Bearer <token>
```

## 4. Các bước thực hiện sau khi được duyệt

### Bước 1 - Hoàn thiện trạng thái tài khoản

- Tạo migration mới bổ sung `users.is_active` kiểu boolean, mặc định `true`.
- Cập nhật `User` để fill/cast `is_active` đúng kiểu boolean.
- Cập nhật `AdminSeeder` để ADMIN luôn được seed ở trạng thái active.
- Cập nhật `UserFactory` để dữ liệu test mặc định active.
- Không sửa migration cũ đã tồn tại nhằm giữ lịch sử migration an toàn.

### Bước 2 - Xây dựng các tầng auth

- Tạo `LoginRequest` để validate `email` và `password`.
- Tạo `AuthService` chứa business logic:
  - tìm user theo email;
  - kiểm tra password bằng `Hash::check`;
  - từ chối user inactive bằng cùng lỗi credential;
  - tạo Sanctum token tên `api`;
  - thu hồi token hiện tại khi logout.
- Tạo `UserResource`:
  - chỉ trả các trường public;
  - eager load `role.permissions`;
  - không để lộ password, remember token hoặc token đã hash;
  - trả permissions dưới dạng danh sách tên để frontend/RBAC sử dụng.
- Tạo `AuthController` mỏng, chỉ nhận request, gọi service và trả resource/envelope.

### Bước 3 - Chuẩn hóa response API

- Tạo trait/helper `ApiResponse` theo đúng `success`, `message`, `data`, `errors` trong `skills/backend.md`.
- Bổ sung render exception API trong `bootstrap/app.php` cho ít nhất:
  - `ValidationException` -> 422 với errors theo field;
  - `AuthenticationException` -> 401 với envelope chuẩn.
- Đây chỉ là chuẩn hóa response; không thêm logic xác thực custom vào middleware.

### Bước 4 - Khai báo route đúng ranh giới RBAC

- Đặt `POST /api/login` ngoài group bảo vệ.
- Đặt `POST /api/logout` và `GET /api/me` trong group `auth:sanctum` riêng.
- Giữ group nghiệp vụ `auth:sanctum + permission` độc lập.
- Không thêm `AuthController` vào mapping permission vì ba endpoint auth không phải nghiệp vụ RBAC.

### Bước 5 - Viết feature test

Tạo test dùng `RefreshDatabase`, seed role/RBAC và cover:

1. Login đúng trả 200, Bearer token và user/role/permissions.
2. Sai password trả 401 và không cấp token.
3. Email không tồn tại trả cùng response 401 như sai password.
4. User inactive trả 401.
5. Thiếu field/sai định dạng email trả 422 theo field.
6. Gọi `/api/me` không token trả 401.
7. Gọi `/api/me` bằng Bearer token hợp lệ trả 200.
8. Logout trả 200 và token vừa dùng bị xóa.
9. Dùng lại token đã logout trả 401.
10. Nếu user có nhiều token, logout chỉ vô hiệu token hiện tại; token còn lại vẫn dùng được.
11. Không tồn tại route `/api/register`.

### Bước 6 - Kiểm tra chất lượng và nghiệm thu

Khi Docker sẵn sàng, chạy:

```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan test --filter=Auth
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

Sau đó kiểm tra thủ công luồng:

```text
login -> lấy token -> me -> logout -> dùng lại token cũ phải nhận 401
```

## 5. File dự kiến tạo hoặc chỉnh sửa

### Tạo mới

- `database/migrations/<timestamp>_add_is_active_to_users_table.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Resources/UserResource.php`
- `app/Http/Controllers/AuthController.php`
- `app/Services/AuthService.php`
- `app/Http/Concerns/ApiResponse.php` (hoặc vị trí trait dùng chung tương đương theo convention hiện có)
- `tests/Feature/AuthTest.php`

### Chỉnh sửa

- `app/Models/User.php`
- `database/seeders/AdminSeeder.php`
- `database/factories/UserFactory.php`
- `routes/api.php`
- `bootstrap/app.php`

Không dự kiến sửa `app/Http/Middleware/EnsurePermission.php` hoặc `config/rbac.php`.

## 6. Điểm cần xác nhận trong bước review

Kế hoạch đang dùng các mặc định sau; chỉ bắt đầu code sau khi được xác nhận:

1. Logout chỉ thu hồi **token hiện tại**, đúng hướng dẫn `skills/backend.md`, không đăng xuất toàn bộ thiết bị.
2. Giữ `config('sanctum.expiration') = null`, nghĩa là token không tự hết hạn; token hết hiệu lực khi logout/thu hồi.
3. Cho phép bổ sung migration `users.is_active` với mặc định `true` vì đây là dữ liệu bắt buộc để đáp ứng case tài khoản bị khóa.
4. Hiểu cụm "chỉ code từ tầng controller trở đi" là không triển khai custom auth middleware; vẫn dùng route middleware chuẩn `auth:sanctum` và có thể chuẩn hóa exception envelope trong `bootstrap/app.php`.

## 7. Điều kiện hoàn thành

- Ba endpoint login/logout/me hoạt động bằng Bearer token Sanctum.
- Login sai hoặc user inactive trả 401 thống nhất.
- Validation trả 422 đúng envelope và theo field.
- `/me` trả role và permissions, không lộ dữ liệu nhạy cảm.
- Logout thu hồi đúng token hiện tại và token cũ không tái sử dụng được.
- Không có public register và không phát sinh custom auth middleware.
- Migration, route, feature test và toàn bộ test suite chạy thành công.
