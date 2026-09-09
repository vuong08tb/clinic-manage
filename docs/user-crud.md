# CRUD Users — Kế hoạch refactor UserService

## 1. Trạng thái tài liệu

- Trạng thái: **ĐÃ TRIỂN KHAI**. `vendor/bin/pint --test` sạch,
  `php artisan test` 293/293 (1990 assertions), `UserTest` 20/20.
- Nguồn hướng dẫn bắt buộc: `skills/backend.md` (mục 4 "Quy tắc đặc biệt trong Service",
  mục 12 "Checklist review backend trước PR", mục 13 "Comment code" — comment tiếng Anh).
- Kiến trúc giữ nguyên: **Controller + Service**.
- Phạm vi: refactor nội bộ `UserService`, thêm một method vào `ListUsersRequest`, đổi
  một dòng trong `UserController::index()`.
- **Không** đổi route, **không** đổi hình dạng response, **không** thêm migration,
  **không** đổi chữ ký `updateStatus(User $user, bool $isActive)`.
- Baseline đã giữ: 18 test cũ trong `tests/Feature/UserTest.php` xanh nguyên vẹn,
  không sửa một dòng test nào; thêm 2 test mới ở mục 10.2.

Luồng xử lý không đổi:

```text
Route
  -> auth:sanctum
  -> EnsurePermission (USERS.*)
  -> Form Request
  -> UserController
  -> UserService
  -> User model
  -> UserResource
  -> ApiResponse
```

---

## 2. Vì sao refactor

Code hiện tại **đúng nghiệp vụ và không có bug đã biết**. Đây không phải bản vá lỗi.
Bốn thứ cần dọn:

| # | Vấn đề | Loại |
|---|---|---|
| 1 | `mutateWithAdminGuard()` gánh 4 tham số và điều khiển logic bằng một chuỗi `$field` | Khó đọc |
| 2 | `create()` ghi đè `is_active` một cách ngầm định, không nêu lý do | Ngầm định |
| 3 | Service tự ép kiểu `is_active` bằng `filter_var` — ép kiểu nằm sai lớp | Sai trách nhiệm |
| 4 | `q` không escape ký tự đại diện của `LIKE` | Sai nghiệp vụ |

Chỉ mục 4 làm sai kết quả trả về cho người dùng. Ba mục còn lại là chi phí bảo trì.

---

## 3. Các quyết định đã chốt

| Nội dung | Quyết định | Lý do |
|---|---|---|
| Thứ tự khoá row | Gom target user + admin active vào **một** câu `SELECT ... FOR UPDATE` sắp theo `id` | Xem mục 4.2 — tránh deadlock |
| Phạm vi khoá | Vẫn khoá toàn bộ admin active, **không** thu hẹp | Thu hẹp sai cách sẽ tạo deadlock; số admin thực tế 1–3 row |
| Cache `admin role id` | Cache theo instance service (`private ?int $adminRoleId`) | Service resolve mới mỗi request; id của role không đổi |
| Ép kiểu filter | Đặt ở `ListUsersRequest::filters()`, không đặt trong Service | Một nguồn sự thật cho biên vào |
| `withQueryString()` | **Không dùng** | Xem mục 8 |
| Mệnh đề `ESCAPE` của `LIKE` | Khai báo tường minh `ESCAPE '\'` | Xem mục 7.3 — SQLite không có escape mặc định |
| Ngôn ngữ comment | Tiếng Anh | `skills/backend.md` mục 13 |

---

## 4. Thay đổi 1 — tách `mutateWithAdminGuard()`

### 4.1 Vấn đề

`mutateWithAdminGuard(User $user, string $field, Closure $mutation, ?int $newRoleId)`
đang dùng **một** tham số chuỗi `$field` để điều khiển **ba** thứ khác nhau:

1. Nhánh logic: `$reducesActiveAdmins = $field === 'is_active' || ...`
2. Key của `ValidationException` ném ra
3. Message hiển thị

Hệ quả: muốn hiểu luồng "đổi role" phải mô phỏng luôn cả luồng "deactivate" trong đầu,
vì hai luồng chia nhau một thân hàm. Thêm `Closure $mutation` nữa thì người đọc còn phải
nhảy ngược lên chỗ gọi mới biết cái gì thực sự được ghi xuống DB.

### 4.2 Về thứ tự khoá — phần cần đọc kỹ nhất

Thứ tự khoá **hiện tại** (`lockForUpdate()` trên tập admin trước, `findOrFail()` khoá
target user sau) là thứ tự **an toàn**. Đừng đảo nó.

Nếu refactor theo hướng trực giác "khoá đúng cái mình cần: target trước, admin sau" thì
sinh ra deadlock thật:

```text
T1: deactivate admin A          T2: deactivate admin B
    khoá A  ................        khoá B
    xin khoá {A, B} -> kẹt ở B     xin khoá {A, B} -> kẹt ở A
    <-------------- hai bên giữ khoá của nhau -------------->
    PostgreSQL phát hiện, giết 1 transaction (SQLSTATE 40P01) -> HTTP 500
```

Cách tránh: **mọi transaction phải xin khoá cùng một dãy row theo cùng một thứ tự**.
Đạt được bằng cách gom tất cả row cần khoá vào một câu lệnh có `ORDER BY id`. Trong
PostgreSQL, node `LockRows` nằm *trên* node `Sort` trong execution plan, nên row được
khoá theo đúng thứ tự đã sắp, không theo thứ tự quét bảng.

Một lưu ý về giới hạn: `FOR UPDATE` khoá row **đang tồn tại**, không chặn được INSERT
(phantom row). Nếu T2 chèn thêm một admin active trong lúc T1 đang chạy, T1 có thể không
nhìn thấy. Điều này vô hại: T1 hoặc thấy admin mới rồi cho qua, hoặc không thấy rồi ném
422 — cả hai nhánh đều **không** phá vỡ ràng buộc "luôn còn ít nhất 1 admin active".

### 4.3 Code sau khi sửa

Thêm property vào ngay đầu class, trước method đầu tiên:

```php
    /**
     * Cached ADMIN role id, resolved once per service instance.
     */
    private ?int $adminRoleId = null;
```

Thay toàn bộ method `update()`:

```php
    /**
     * Update profile fields and protect the final active administrator.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (! array_key_exists('role_id', $data)) {
            $user->update($data);

            return $user->refresh()->load('role');
        }

        $newRoleId = (int) $data['role_id'];

        return DB::transaction(function () use ($user, $data, $newRoleId): User {
            $lockedUser = $this->lockUserAndActiveAdmins($user);

            if ((int) $lockedUser->role_id !== $newRoleId) {
                $this->assertNotLastActiveAdmin($lockedUser, 'role_id');
            }

            $this->assertDoctorRoleChangeAllowed($lockedUser, $newRoleId);
            $lockedUser->update($data);

            return $lockedUser->refresh()->load('role');
        });
    }
```

Thay toàn bộ method `setInactiveWithGuard()`:

```php
    /**
     * Deactivate a locked account and revoke all issued API tokens.
     */
    private function setInactiveWithGuard(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $lockedUser = $this->lockUserAndActiveAdmins($user);

            $this->assertNotLastActiveAdmin($lockedUser, 'is_active');

            $lockedUser->update(['is_active' => false]);
            $lockedUser->tokens()->delete();

            return $lockedUser->refresh()->load('role');
        });
    }
```

**Xoá hẳn** method `mutateWithAdminGuard()`, thay bằng hai method mới:

```php
    /**
     * Lock the target account together with every active administrator.
     *
     * Both row sets are taken in one statement ordered by id, so concurrent
     * transactions always request the same rows in the same order and cannot end
     * up each holding a row the other one still needs.
     */
    private function lockUserAndActiveAdmins(User $user): User
    {
        $adminRoleId = $this->adminRoleId();

        $lockedRows = User::query()
            ->where(function ($query) use ($user, $adminRoleId): void {
                $query
                    ->where('id', $user->getKey())
                    ->orWhere(function ($query) use ($adminRoleId): void {
                        $query
                            ->where('role_id', $adminRoleId)
                            ->where('is_active', true);
                    });
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $lockedUser = $lockedRows->firstWhere('id', $user->getKey());

        if ($lockedUser === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$user->getKey()]);
        }

        return $lockedUser;
    }

    /**
     * Resolve the ADMIN role id, failing loudly when the role is missing.
     */
    private function adminRoleId(): int
    {
        if ($this->adminRoleId !== null) {
            return $this->adminRoleId;
        }

        $adminRoleId = Role::query()
            ->where('name', Role::ADMIN)
            ->value('id');

        if ($adminRoleId === null) {
            throw new LogicException(UserMessage::ADMIN_ROLE_NOT_CONFIGURED);
        }

        return $this->adminRoleId = (int) $adminRoleId;
    }
```

Thay toàn bộ method `assertNotLastActiveAdmin()` — từ 4 tham số xuống 2:

```php
    /**
     * Reject mutations that would remove the final active administrator.
     *
     * Reading without a fresh lock is safe here: lockUserAndActiveAdmins already
     * holds every row this query can match.
     *
     * @throws ValidationException
     */
    private function assertNotLastActiveAdmin(User $user, string $field): void
    {
        $isActiveAdmin = (int) $user->role_id === $this->adminRoleId() && $user->is_active;

        if (! $isActiveAdmin) {
            return;
        }

        $anotherActiveAdminExists = User::query()
            ->where('role_id', $this->adminRoleId())
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($anotherActiveAdminExists) {
            return;
        }

        $message = $field === 'role_id'
            ? UserMessage::LAST_ACTIVE_ADMIN_ROLE_CHANGE
            : UserMessage::LAST_ACTIVE_ADMIN_DEACTIVATION;

        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
```

### 4.4 Thay đổi phần `use`

| Thao tác | Dòng |
|---|---|
| Thêm | `use Illuminate\Database\Eloquent\ModelNotFoundException;` |
| Bỏ | `use Closure;` |
| Bỏ | `use Illuminate\Support\Collection;` |

`Closure` và `Collection` chỉ phục vụ `mutateWithAdminGuard()` và chữ ký cũ của
`assertNotLastActiveAdmin()`, cả hai đều biến mất.

### 4.5 Đối chiếu hành vi trước/sau

| Tình huống | Trước | Sau |
|---|---|---|
| Update không có `role_id` | Không transaction, không khoá | Giữ nguyên |
| Update có `role_id` nhưng role không đổi | Vào transaction, khoá, bỏ qua guard | Giữ nguyên |
| Update đổi role của admin cuối | 422, key `role_id` | Giữ nguyên |
| `deactivate()` / `updateStatus(false)` admin cuối | 422, key `is_active` | Giữ nguyên |
| `updateStatus(true)` | Chỉ khoá target, không khoá tập admin | Giữ nguyên |
| Deactivate một admin **đã** inactive | Không throw | Giữ nguyên |
| Xoá token khi deactivate | Có | Giữ nguyên |

Refactor này **không đổi hành vi nào**. Đó là tiêu chí nghiệm thu: 18 test cũ phải xanh
mà không sửa một dòng test nào.

---

## 5. Thay đổi 2 — `create()` không ghi đè ngầm

Hiện tại `create()` có dòng `$data['is_active'] = true;` trần, ghi đè cả khi caller
truyền giá trị khác. Đúng ý đồ (đã chặn ở `StoreUserRequest` bằng rule `prohibited`)
nhưng người đọc phải mở FormRequest mới hiểu vì sao input bị vứt đi.

Thêm hằng số vào class:

```php
    /**
     * Accounts are always born active; the status endpoint owns every later change,
     * which is why StoreUserRequest prohibits is_active on the create payload.
     */
    private const CREATED_AS_ACTIVE = true;
```

Sửa dòng trong `create()`:

```php
        $data['is_active'] = self::CREATED_AS_ACTIVE;
```

Không đổi hành vi, chỉ đặt tên cho một quyết định.

---

## 6. Thay đổi 3 — ép kiểu filter về đúng lớp

### 6.1 Vấn đề

Rule `'is_active' => ['nullable', 'boolean']` chỉ **kiểm tra**, **không ép kiểu**.
`$request->validated()` vẫn trả về chuỗi `"1"` lấy từ query string. Vì thế Service buộc
phải tự đoán kiểu lần thứ hai bằng `filter_var(..., FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`.

Kết quả là quy tắc "giá trị nào được coi là true" nằm rải ở hai file. Ai sửa rule ở
FormRequest sẽ không ngờ trong Service còn một tầng nữa.

### 6.2 Sửa `ListUsersRequest`

Thêm method vào `app/Http/Requests/User/ListUsersRequest.php`:

```php
    /**
     * Return the validated filters with query-string scalars cast to real types.
     *
     * Validation rules check shape but never cast, so "1" would otherwise reach the
     * service as a string and force it to guess types a second time.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->validated();

        if (isset($filters['is_active'])) {
            $filters['is_active'] = $this->boolean('is_active');
        }

        if (isset($filters['per_page'])) {
            $filters['per_page'] = (int) $filters['per_page'];
        }

        return $filters;
    }
```

`isset()` chỉ trả về false khi giá trị là `null`, nên `is_active=0` (ép thành `false`)
vẫn lọt vào mảng đúng như mong muốn.

### 6.3 Sửa `UserController::index()`

```php
        $users = $this->service->paginate($request->filters());
```

### 6.4 Sửa `UserService::paginate()`

Thay cả khối `array_key_exists('is_active', ...)` + `filter_var` bằng:

```php
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
```

---

## 7. Thay đổi 4 — escape ký tự đại diện của `LIKE`

### 7.1 Vấn đề

`%` và `_` là ký tự đại diện của `LIKE`. Không escape thì:

| Người dùng gõ | Kết quả hiện tại | Kết quả đúng |
|---|---|---|
| `50%` | Khớp **mọi** dòng có "50" ở đầu | Chỉ khớp tên/email chứa đúng chuỗi "50%" |
| `a_b` | Khớp cả `axb`, `a1b` | Chỉ khớp đúng "a_b" |

Đây **không** phải lỗ hổng SQL injection — giá trị vẫn được bind làm tham số qua
`whereRaw('... LIKE ?', [$pattern])`. Đây là sai kết quả nghiệp vụ.

### 7.2 Sửa

Trong `paginate()`, thay hai dòng dựng `$pattern`:

```php
            $term = mb_strtolower(trim((string) $filters['q']));

            // % and _ are LIKE wildcards: searching for "50%" would otherwise match
            // every row. The term is still bound as a parameter, so escaping here is
            // a correctness fix, not an injection fix.
            $pattern = '%'.addcslashes($term, '%_\\').'%';

            // ESCAPE is declared explicitly: PostgreSQL defaults to a backslash but
            // SQLite, which backs the test suite, has no default escape character.
            $query->where(function ($query) use ($pattern): void {
                $query
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$pattern]);
            });
```

Chú ý phần trích dẫn: chuỗi SQL đổi từ nháy đơn sang **nháy kép** để viết được `'\'`
bên trong mà không phải escape chồng nhiều lớp.

### 7.3 Vì sao phải khai báo `ESCAPE` tường minh

Bản đầu tiên của mục này viết "PostgreSQL mặc định dùng `\`, không cần `ESCAPE`". Đúng
với production nhưng **sai với test**, và test đã bắt được:

| Driver | Escape mặc định của `LIKE` |
|---|---|
| PostgreSQL (production, `DB_CONNECTION=pgsql`) | `\` |
| SQLite (test suite, `phpunit.xml` dùng `sqlite` + `:memory:`) | **Không có** |

Trên SQLite, không khai báo `ESCAPE` thì `\` trong pattern là ký tự thường. Pattern
`%50\%%` biến thành "có 50, rồi một dấu gạch chéo ngược, rồi ký tự bất kỳ" → không khớp
gì cả, `test_search_treats_like_wildcards_as_literal_characters` trả về 0 dòng.

Khai báo `ESCAPE '\'` tường minh cho kết quả giống nhau trên cả hai driver và bỏ được
phụ thuộc vào mặc định riêng của từng hệ quản trị. Đây là lý do nên viết tường minh ngay
cả khi production "vốn đã đúng".

Đáng chú ý: khoảng cách driver này cũng là lý do `ILIKE` là lựa chọn tệ. Nó chạy trên
PostgreSQL nhưng chết thẳng trên SQLite, tức là code dùng `ILIKE` **không test được** với
cấu hình test hiện tại.

---

## 8. Đã cân nhắc và loại bỏ

### 8.1 `withQueryString()`

**Không đưa vào.** `ApiResponse::paginated()` chỉ lấy `$payload['data']` và
`Arr::only($payload['meta'], ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'])`,
rồi tự dựng envelope. Trường `links` — nơi duy nhất `withQueryString()` có tác dụng —
**không bao giờ** được serialize ra response. Test `test_admin_can_list_filter_search_and_paginate_users`
chốt điều này bằng `assertJsonMissingPath('links')`.

Thêm `withQueryString()` sẽ là code chết. Nếu sau này muốn client phân trang bằng URL
dựng sẵn thì phải sửa envelope trước, và đó là một quyết định API riêng.

### 8.2 Thu hẹp phạm vi khoá

**Không làm.** Xem mục 4.2: hướng "chỉ khoá khi target thực sự là admin, khoá target
trước rồi mới khoá tập admin" sinh deadlock giữa hai request deactivate hai admin khác
nhau. Chi phí giữ nguyên phạm vi khoá là nhỏ: chỉ nhánh đổi role và nhánh deactivate mới
vào transaction, còn update hồ sơ thường đi nhánh nhanh; số admin active thực tế là 1–3 row.

### 8.3 Gộp `deactivate()` vào `updateStatus()`

**Không làm.** `deactivate()` phục vụ `DELETE /users/{id}`, `updateStatus()` phục vụ
`PATCH /users/{id}/status`. Hai route khác nhau, hai message khác nhau
(`UserMessage::DEACTIVATED` và `UserMessage::STATUS_UPDATED`). Chúng đã dùng chung
`setInactiveWithGuard()` — phần logic trùng nhau đã được gộp đúng chỗ rồi.

---

## 9. File ảnh hưởng

| File | Thay đổi |
|---|---|
| `app/Services/UserService.php` | Mục 4 (xoá `mutateWithAdminGuard`, thêm `lockUserAndActiveAdmins` + `adminRoleId` + property cache, viết lại `update` / `setInactiveWithGuard` / `assertNotLastActiveAdmin`, sửa `use`), mục 5, mục 6.4, mục 7.2 |
| `app/Http/Requests/User/ListUsersRequest.php` | Mục 6.2 — thêm `filters()` |
| `app/Http/Controllers/UserController.php` | Mục 6.3 — `index()` gọi `$request->filters()` |
| `tests/Feature/UserTest.php` | Mục 10 — **chỉ thêm** test mới, không sửa test cũ |

Không đụng: route, migration, model, resource, middleware, seeder.

---

## 10. Test

### 10.1 Test cũ — phải xanh mà không sửa

18 test trong `tests/Feature/UserTest.php`, đặc biệt 6 test canh guard admin:

- `test_last_active_admin_cannot_be_assigned_another_role`
- `test_last_active_admin_cannot_be_deactivated_through_status`
- `test_last_active_admin_cannot_be_deactivated_through_destroy`
- `test_inactive_admin_does_not_count_as_an_active_admin_replacement`
- `test_one_of_two_active_admins_can_change_role_or_be_deactivated`
- `test_admin_can_deactivate_self_when_another_active_admin_remains`

Nếu một trong sáu test này đỏ, tức là refactor đã đổi hành vi — phải sửa code, không sửa test.

### 10.2 Test mới cần thêm

Cho mục 7 (escape `LIKE`):

```php
    public function test_search_treats_like_wildcards_as_literal_characters(): void
    {
        $admin = $this->createUser('ADMIN', ['name' => 'Primary Admin']);
        $this->createUser('RECEPTIONIST', [
            'name' => 'Discount 50% Desk',
            'email' => 'discount@clinic.test',
        ]);
        $this->createUser('RECEPTIONIST', [
            'name' => 'Regular Desk',
            'email' => 'regular@clinic.test',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users?q=50%25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    ['email' => 'discount@clinic.test'],
                ],
            ]);
    }
```

`%25` là dạng URL-encode của ký tự `%` trong query string.

Cho mục 6 (ép kiểu filter) — kiểm tra `is_active=0` lọc đúng thay vì bị bỏ qua:

```php
    public function test_inactive_filter_accepts_a_falsy_query_value(): void
    {
        $admin = $this->createUser('ADMIN', ['name' => 'Primary Admin']);
        $this->createUser('RECEPTIONIST', [
            'email' => 'active@clinic.test',
            'is_active' => true,
        ]);
        $this->createUser('RECEPTIONIST', [
            'email' => 'inactive@clinic.test',
            'is_active' => false,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    ['email' => 'inactive@clinic.test'],
                ],
            ]);
    }
```

### 10.3 Phần không test được bằng feature test

Kịch bản deadlock và race condition ở mục 4.2 **không** kiểm chứng được bằng
`php artisan test`: PHPUnit chạy tuần tự trong một connection, `RefreshDatabase` bọc mỗi
test trong một transaction nên hai transaction song song không tồn tại. Tính đúng đắn ở
đây dựa vào thứ tự khoá, không dựa vào test. Đó là lý do mục 4.2 phải được review kỹ bằng
mắt.

### 10.4 Lệnh chạy và kết quả

```bash
php artisan test --filter=UserTest   # 20/20
php artisan test                     # 293/293, 1990 assertions
vendor/bin/pint --test               # passed
```

---

## 11. Checklist review trước PR

- [ ] `mutateWithAdminGuard()` đã bị xoá hoàn toàn, không còn chỗ nào gọi.
- [ ] `use Closure;` và `use Illuminate\Support\Collection;` đã bỏ; `ModelNotFoundException` đã thêm.
- [ ] Mọi khoá row đi qua `lockUserAndActiveAdmins()` — không còn `lockForUpdate()` rời rạc
      ở nhánh đổi role / deactivate.
- [ ] `updateStatus()` nhánh bật active vẫn chỉ khoá target, không khoá tập admin.
- [ ] Không còn `filter_var` trong `UserService`.
- [ ] Không hard-code `'ADMIN'`; mọi chỗ dùng `Role::ADMIN`.
- [ ] Mọi message lấy từ `App\Constants\UserMessage`, không có chuỗi trần trong Service.
- [ ] `ValidationException` ném đúng key: `role_id` cho đổi role, `is_active` cho deactivate.
- [ ] Comment mới viết bằng tiếng Anh (`skills/backend.md` mục 13).
- [ ] `UserTest` 18 test cũ xanh, **không sửa dòng test nào**.
- [ ] 2 test mới ở mục 10.2 xanh.
- [ ] `vendor/bin/pint --test` sạch.

---

## 12. Thứ tự commit đề xuất

Tách hai commit, vì mức độ cần soi kỹ khác nhau:

| Commit | Nội dung | Ghi chú review |
|---|---|---|
| 1 | Mục 4 — refactor guard admin | Cần đọc kỹ mục 4.2 trước khi approve. Không đổi hành vi, không thêm test |
| 2 | Mục 5 + 6 + 7 — dọn `create()`, ép kiểu filter, escape `LIKE` | Mục 7 đổi hành vi (đúng hơn), kèm 2 test mới |

Tách như vậy để khi commit 1 có vấn đề thì revert được riêng phần khoá DB mà không mất
ba bản vá nhỏ ở commit 2.
