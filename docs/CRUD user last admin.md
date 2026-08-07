# Kế hoạch CRUD User và bảo vệ ADMIN cuối cùng

> Trạng thái: đã được duyệt và triển khai. Toàn bộ test suite đạt 40 tests / 158 assertions;
> Laravel Pint đạt chuẩn trên 57 files. Runtime Docker/PostgreSQL đã xác nhận login, list user
> có pagination meta và logout đều trả HTTP 200.

## 1. Trạng thái và nguyên tắc thực hiện

Kế hoạch này áp dụng cho task T1.13 trên branch:

```text
task/vuongth/T1.13-CRUD-Users-last-Admin
```

Kiến trúc bắt buộc theo `skills/backend.md`:

```text
Route
  -> auth:sanctum
  -> EnsurePermission
  -> Form Request
  -> UserController
  -> UserService
  -> User/Role Model
  -> UserResource
  -> ApiResponse
```

Nguyên tắc:

- Controller chỉ điều phối, không chứa business rule.
- Form Request validate input cho mọi endpoint ghi.
- UserService xử lý nghiệp vụ, transaction và guard ADMIN cuối cùng.
- Output user luôn đi qua API Resource, không lộ password/token hash.
- Permission lấy từ `EnsurePermission`, không hard-code tên ADMIN trong Controller.
- Business rule không hợp lệ ném `ValidationException` để Exception Handler trả 422 với
  `errors` theo field.
- Implementation chỉ bắt đầu sau khi kế hoạch được review và phê duyệt.

## 2. Yêu cầu nghiệp vụ

> API tạo/sửa/list/xem/khóa user, gán `role_id`. Chỉ ADMIN thông qua `USERS.*`.
> Không cho đổi role hoặc deactivate ADMIN active cuối cùng trong hệ thống; vi phạm trả 422.

### Định nghĩa dùng trong kế hoạch

- “ADMIN cuối cùng” là user có role `ADMIN`, `is_active=true`, và không còn ADMIN active
  nào khác.
- `destroy` không xóa bản ghi; chỉ chuyển `is_active=false`.
- Khóa user phải thu hồi toàn bộ Sanctum token của user đó. Nếu chỉ cập nhật
  `is_active=false`, token đã cấp trước khi khóa vẫn có thể tiếp tục gọi API.
- Cho phép ADMIN tự đổi role hoặc tự khóa khi hệ thống còn ít nhất một ADMIN active khác.
- User đã inactive được deactivate lần nữa theo hướng idempotent: giữ inactive và trả success,
  không phát sinh lỗi business.
- Kích hoạt lại user không chịu guard ADMIN cuối cùng.

Các quyết định trên cần leader xác nhận tại mục 13 trước khi implementation.

## 3. Kết quả audit code hiện tại

### Thành phần đã sẵn sàng

- Branch hiện tại đúng T1.13 và working tree sạch tại thời điểm lập kế hoạch.
- Bảng `users` đã có `role_id`, `is_active`, unique email và password.
- `users.role_id` là foreign key tới `roles` và restrict khi xóa role.
- Model User đã có fillable, password hashed cast, boolean cast cho `is_active` và quan hệ
  `role()`.
- Role đã có quan hệ `users()` và `permissions()`.
- Permission đã có đầy đủ:
  - `USERS.FINDALL`;
  - `USERS.CREATE`;
  - `USERS.FINDONE`;
  - `USERS.UPDATE`;
  - `USERS.DELETE`;
  - `USERS.UPDATESTATUS`.
- `RbacSeeder` gán toàn bộ permission cho ADMIN; các role khác không có `USERS.*`.
- `config/rbac.php` đã map `UserController -> USERS` và đủ action suffix.
- Group route nghiệp vụ đã có `auth:sanctum + permission` nhưng chưa đăng ký route user.
- UserResource đã không trả password/remember token.
- ApiResponse đã hỗ trợ resource đơn và pagination `meta`.
- Exception Handler đã format `ValidationException` thành HTTP 422 JSON.

### Thành phần còn thiếu

- Chưa có UserController.
- Chưa có UserService.
- Chưa có Form Request dành cho CRUD user.
- Chưa có route `/api/users`.
- Chưa có pagination/filter user thật.
- Chưa có guard ADMIN cuối cùng.
- Chưa thu hồi token khi khóa user.
- Chưa có feature test CRUD/RBAC/concurrency guard.

### Thành phần không làm trong task này

- Không tạo lại migration role/permission/user đã có.
- Không sửa permission matrix nếu audit runtime xác nhận ADMIN đã có đủ `USERS.*`.
- Không hard-delete user.
- Không triển khai ActivityLog vì đây là phạm vi T4.1 và bảng hiện chưa tồn tại.
- Không tạo public register.
- Không thay đổi logic login/logout/me ngoài việc xác nhận user bị khóa không thể login.

## 4. Thiết kế endpoint và permission

| Method | Endpoint | Controller action | Permission | Status thành công |
|---|---|---|---|---:|
| GET | `/api/users` | `index` | `USERS.FINDALL` | 200 |
| POST | `/api/users` | `store` | `USERS.CREATE` | 201 |
| GET | `/api/users/{user}` | `show` | `USERS.FINDONE` | 200 |
| PUT/PATCH | `/api/users/{user}` | `update` | `USERS.UPDATE` | 200 |
| DELETE | `/api/users/{user}` | `destroy` | `USERS.DELETE` | 200 |
| PATCH | `/api/users/{user}/status` | `updateStatus` | `USERS.UPDATESTATUS` | 200 |

Route status sẽ khai báo tường minh cùng `Route::apiResource('users', UserController::class)`
trong group `['auth:sanctum', 'permission']`.

Route model binding tự trả 404 JSON khi `{user}` không tồn tại.

## 5. Contract request

### 5.1. ListUsersRequest — index query

Query được phép:

- `q`: nullable string, tìm theo name hoặc email;
- `role_id`: nullable integer, phải tồn tại trong roles;
- `is_active`: nullable boolean;
- `per_page`: nullable integer, giới hạn đề xuất 1–100;
- `page`: nullable integer, tối thiểu 1.

Mặc định `per_page=15`. Dùng Form Request cho query giúp filter sai cũng trả 422 theo
envelope chung.

### 5.2. StoreUserRequest — tạo user

```json
{
  "name": "Clinic Receptionist",
  "email": "receptionist2@clinic.test",
  "password": "Password@123",
  "password_confirmation": "Password@123",
  "role_id": 2
}
```

Rules dự kiến:

- `name`: required, string, max 255;
- `email`: required, string, email, max 255, unique users;
- `password`: required, string, confirmed, min 8;
- `role_id`: required, integer, exists roles id.

`is_active` không nhận từ store; user mới mặc định active theo schema để tránh client tự tạo
tài khoản khóa. Nếu leader muốn cho phép tạo inactive cần xác nhận riêng.

### 5.3. UpdateUserRequest — sửa user/đổi role

Rules dự kiến cho PATCH:

- `name`: sometimes, required, string, max 255;
- `email`: sometimes, required, email, max 255, unique ngoại trừ user hiện tại;
- `password`: sometimes, required, string, confirmed, min 8;
- `role_id`: sometimes, required, integer, exists roles id;
- request phải có ít nhất một field được phép.

Không nhận `is_active` tại update; trạng thái chỉ thay đổi qua endpoint status hoặc destroy để
mọi đường khóa đều đi qua cùng guard.

### 5.4. UpdateUserStatusRequest — khóa/mở user

```json
{
  "is_active": false
}
```

Rule:

- `is_active`: required, boolean.

Mọi Form Request có custom messages bằng tiếng Anh, nhất quán với response hiện tại.

## 6. Response contract

### 6.1. List có pagination

```json
{
  "success": true,
  "message": "Users retrieved",
  "data": [],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 5,
    "total": 5
  }
}
```

Controller dùng:

```text
ApiResponse::paginated(UserResource::collection($users), 'Users retrieved')
```

### 6.2. Tạo user

HTTP 201:

```json
{
  "success": true,
  "message": "User created",
  "data": {
    "id": 10,
    "name": "Clinic Receptionist",
    "email": "receptionist2@clinic.test",
    "is_active": true,
    "role": {
      "id": 2,
      "name": "RECEPTIONIST",
      "display_name": "Lễ tân"
    }
  }
}
```

### 6.3. Guard ADMIN cuối cùng

HTTP 422 khi đổi role:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "role_id": [
      "The last active administrator cannot be assigned another role."
    ]
  }
}
```

HTTP 422 khi deactivate:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "is_active": [
      "The last active administrator cannot be deactivated."
    ]
  }
}
```

Không gọi `ApiResponse::error()` trực tiếp cho hai lỗi này. UserService ném
`ValidationException::withMessages()` để Exception Handler bảo đảm status 422 và errors theo
field.

## 7. Thiết kế UserController

UserController inject UserService qua constructor và chỉ điều phối:

- `index(ListUsersRequest)`:
  - lấy `$request->validated()`;
  - gọi service paginate;
  - trả UserResource collection qua `ApiResponse::paginated()`.
- `store(StoreUserRequest)`:
  - gọi service create;
  - trả UserResource với HTTP 201.
- `show(User)`:
  - gọi service load role;
  - trả UserResource.
- `update(UpdateUserRequest, User)`:
  - gọi service update;
  - trả UserResource.
- `destroy(User)`:
  - gọi service deactivate;
  - trả UserResource/message, HTTP 200.
- `updateStatus(UpdateUserStatusRequest, User)`:
  - gọi service updateStatus;
  - trả UserResource, HTTP 200.

Controller không:

- đếm ADMIN;
- query role;
- kiểm tra tên role;
- hash password thủ công;
- mở transaction;
- tự kiểm tra permission.

## 8. Thiết kế UserService và guard ADMIN cuối

### 8.1. Các method dự kiến

- `paginate(array $filters): LengthAwarePaginator`;
- `create(array $data): User`;
- `find(User $user): User` hoặc `load(User $user): User`;
- `update(User $user, array $data): User`;
- `deactivate(User $user): User`;
- `updateStatus(User $user, bool $isActive): User`;
- `assertNotLastActiveAdmin(User $user, string $field): void`.

### 8.2. Create/update

- Model password cast `hashed` chịu trách nhiệm hash; không hash lần hai.
- Sau create/update load relation role để Resource không query ngầm.
- Update chỉ ghi field từ Form Request đã validated.
- Chỉ gọi guard nếu target hiện là ADMIN active và request thực sự đổi sang role khác.
- Đổi từ role không phải ADMIN sang ADMIN không cần guard.
- Đổi role của ADMIN inactive không làm giảm số ADMIN active nên không cần guard.

### 8.3. Guard an toàn trước race condition

Mọi thao tác có thể làm giảm số ADMIN active chạy trong `DB::transaction()`.

Trong transaction:

1. Lock target user bằng `lockForUpdate()`.
2. Lấy role ADMIN theo `roles.name='ADMIN'`.
3. Lock danh sách user có `role_id=ADMIN_ID` và `is_active=true` theo thứ tự id.
4. Nếu target là ADMIN active và số lượng ADMIN active `<=1`, ném
   `ValidationException::withMessages()` với field phù hợp.
5. Nếu còn ADMIN active khác, thực hiện đổi role hoặc deactivate.

Lock các dòng ADMIN active giúp hai request đồng thời không thể cùng thấy count cũ rồi khóa
hai ADMIN cuối cùng. Sau khi request thứ nhất commit, request thứ hai đếm lại và bị chặn nếu
chỉ còn một ADMIN active.

### 8.4. Deactivate và token

Khi chuyển từ active sang inactive:

1. Chạy guard ADMIN cuối.
2. Cập nhật `is_active=false`.
3. Xóa toàn bộ `$user->tokens()` trong cùng luồng nghiệp vụ.
4. Trả user đã refresh/load role.

Destroy gọi lại cùng method deactivate để không nhân đôi guard.

Khi kích hoạt lại:

- cập nhật `is_active=true`;
- không tạo token tự động;
- user phải login lại.

## 9. Query và tránh N+1

Index dự kiến:

- `User::query()->with('role')`;
- filter role_id/is_active;
- search name/email;
- order mặc định id giảm dần hoặc name tăng dần sau khi leader chốt;
- paginate theo `per_page` đã validate.

UserResource sẽ chỉ trả permissions khi `role.permissions` thực sự được load. Với màn hình quản
trị user, list/show chỉ cần role để tránh trả hàng nghìn permission name không cần thiết. Auth
login/me tiếp tục load `role.permissions` và vẫn trả permissions.

Điểm này có thể yêu cầu chỉnh nhẹ UserResource bằng `whenLoaded`/conditional field nhưng không
được làm thay đổi contract login/me hiện tại.

## 10. Kế hoạch route và RBAC

Trong group hiện có:

```php
Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::apiResource('users', UserController::class);
});
```

EnsurePermission sẽ tự map:

```text
UserController@index        -> USERS.FINDALL
UserController@store        -> USERS.CREATE
UserController@show         -> USERS.FINDONE
UserController@update       -> USERS.UPDATE
UserController@destroy      -> USERS.DELETE
UserController@updateStatus -> USERS.UPDATESTATUS
```

Không viết `if ($user->role->name !== 'ADMIN')` trong Controller. “Chỉ ADMIN” được bảo đảm
bằng dữ liệu `role_permissions`: ADMIN có `USERS.*`, các role khác không có.

## 11. Kế hoạch feature test

Tạo `tests/Feature/UserTest.php`, dùng `RefreshDatabase`, seed RoleSeeder + RbacSeeder.

### Auth/RBAC

- [ ] Không token gọi users -> 401 JSON.
- [ ] RECEPTIONIST gọi index -> 403 `Missing permission: USERS.FINDALL`.
- [ ] RECEPTIONIST gọi store/update/destroy/status -> 403 tương ứng.
- [ ] ADMIN gọi đủ endpoint -> được phép.

### Store

- [ ] Tạo user hợp lệ -> 201, lưu đúng role_id, password được hash.
- [ ] Response không có password/remember_token/token hash.
- [ ] Thiếu field -> 422 errors theo field.
- [ ] Email sai định dạng -> 422 `errors.email`.
- [ ] Email trùng -> 422 `errors.email`.
- [ ] role_id không tồn tại -> 422 `errors.role_id`.
- [ ] Password confirmation sai -> 422 `errors.password`.

### Index/show

- [ ] Index trả pagination meta đúng.
- [ ] Filter role_id đúng.
- [ ] Filter is_active đúng.
- [ ] Search name/email đúng.
- [ ] Không N+1 role khi list (kiểm qua eager loading/code review).
- [ ] Show user tồn tại -> 200.
- [ ] Show user không tồn tại -> 404 JSON.

### Update

- [ ] Sửa name/email/password hợp lệ -> 200.
- [ ] Password mới được hash và login được bằng password mới.
- [ ] Email unique bỏ qua chính user hiện tại.
- [ ] Đổi role user thường -> thành công.
- [ ] Payload có `is_active` tại update không được dùng để né status guard.

### Guard ADMIN cuối cùng

- [ ] Chỉ có một ADMIN active, đổi role -> 422 `errors.role_id`.
- [ ] Chỉ có một ADMIN active, PATCH status false -> 422 `errors.is_active`.
- [ ] Chỉ có một ADMIN active, DELETE -> 422 `errors.is_active`.
- [ ] Khi guard trả 422, role/status database không đổi.
- [ ] Có hai ADMIN active, đổi role một ADMIN -> 200.
- [ ] Có hai ADMIN active, deactivate một ADMIN -> 200.
- [ ] ADMIN inactive không được tính là ADMIN active dự phòng.
- [ ] Kích hoạt lại ADMIN inactive -> 200.
- [ ] Tự đổi role/tự khóa được phép khi còn ADMIN active khác, nếu leader duyệt quy tắc này.

### Token khi khóa

- [ ] User bị deactivate thì toàn bộ personal access token của user bị xóa.
- [ ] Token cũ gọi `/api/me` -> 401.
- [ ] User inactive login đúng password -> 401.
- [ ] Kích hoạt lại xong có thể login và nhận token mới.

### Destroy semantics

- [ ] DELETE chỉ đặt `is_active=false`, bản ghi user vẫn tồn tại.
- [ ] Deactivate user đã inactive không xóa bản ghi và giữ trạng thái inactive.

### Regression

- [ ] AuthTest vẫn xanh.
- [ ] EnsurePermissionTest vẫn xanh.
- [ ] ApiResponseTest vẫn xanh.
- [ ] Toàn bộ test suite và Pint xanh.

## 12. File dự kiến tạo hoặc chỉnh sửa

### Tạo mới

- `app/Http/Controllers/UserController.php`
- `app/Services/UserService.php`
- `app/Http/Requests/User/ListUsersRequest.php`
- `app/Http/Requests/User/StoreUserRequest.php`
- `app/Http/Requests/User/UpdateUserRequest.php`
- `app/Http/Requests/User/UpdateUserStatusRequest.php`
- `tests/Feature/UserTest.php`

### Chỉnh sửa

- `routes/api.php`
- `app/Http/Resources/UserResource.php` nếu cần làm permissions conditional cho list.
- `database/factories/UserFactory.php` để thêm state role thuận tiện cho test, nếu cần.
- README/API docs sau khi endpoint hoạt động và contract được xác nhận.

### Không dự kiến chỉnh sửa

- Migrations đã chạy.
- Permission data migration và RbacSeeder.
- EnsurePermission/config RBAC, trừ khi test phát hiện mapping hiện tại sai.
- AuthController/AuthService.
- ApiResponse/Exception Handler.

## 13. Điểm cần leader xác nhận

1. “ADMIN cuối cùng” được tính theo `role=ADMIN` và `is_active=true`.
2. Cho phép ADMIN tự đổi role hoặc tự khóa nếu còn ít nhất một ADMIN active khác.
3. Khóa user sẽ thu hồi tất cả Sanctum token hiện có của user.
4. User mới luôn active; API store không nhận `is_active`.
5. Update không nhận `is_active`; trạng thái chỉ đổi qua status endpoint hoặc DELETE.
6. DELETE là idempotent deactivate và trả HTTP 200 với UserResource, không trả 204.
7. Message API và custom validation message tiếp tục dùng tiếng Anh.
8. Index mặc định sắp xếp theo `id` giảm dần và `per_page=15`.
9. List/show chỉ trả role; permissions chỉ xuất hiện khi relation permissions được load cho
   login/me.

## 14. Điều kiện hoàn thành

- Tất cả sáu endpoint hoạt động đúng contract và status.
- Chỉ role có permission `USERS.*` truy cập được; dữ liệu seed bảo đảm chỉ ADMIN có quyền.
- Mọi endpoint ghi dùng Form Request và `$request->validated()`.
- Controller mỏng; guard, transaction và token revocation nằm trong UserService.
- Không thể đổi role/khóa/xóa mềm ADMIN active cuối cùng.
- Mọi vi phạm guard trả 422 với errors theo field và không thay đổi database.
- DELETE chỉ deactivate, không hard-delete.
- UserResource không lộ dữ liệu nhạy cảm.
- List có pagination meta và không N+1 role.
- User bị khóa không thể tiếp tục dùng token cũ hoặc login mới.
- Feature tests, regression suite và Pint đều pass.
