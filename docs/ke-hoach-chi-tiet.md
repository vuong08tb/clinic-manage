# Kế hoạch chi tiết — Clinic Management REST API

Tài liệu này break-down toàn bộ đầu task từ `task.xlsx` theo 4 tuần. Mỗi task gồm 5 phần:

1. **Nội dung task gốc** — nguyên văn từ file task.
2. **Diễn giải chi tiết** — nghiệp vụ, vấn đề, cách tiếp cận, từng bước. Có thể copy làm prompt cho vibe code.
3. **Mở rộng** — tính năng thêm nếu còn thời gian.
4. **Checklist hoàn thành** — điều kiện coi là "xong".
5. **Checklist self-test** — cách tự kiểm chứng trước khi tạo PR.

**Quy ước xuyên suốt:** kiến trúc **B (Controller + Service)**; RBAC `CONTROLLER.ACTION`; envelope `success/message/data/errors`; branch `task/vuongth/<ID>-<slug>`. Tham chiếu playbook: [database.md](../skills/database.md), [backend.md](../skills/backend.md), [frontend.md](../skills/frontend.md), [docker.md](../skills/docker.md).

> **Lưu ý về đánh số:** file task gốc nhảy từ **T1.5 sang T1.7** (không có T1.6 tách riêng — phần schema RBAC được gộp trong T1.5). Tài liệu này giữ nguyên ID gốc để khớp khi đặt tên branch.

---

## Mục lục

- [Tuần 1 — Nền tảng: Docker, Auth, RBAC, danh mục](#tuần-1--nền-tảng-docker-auth-rbac-danh-mục)
  - T1.1 → T1.15
- [Tuần 2 — Bệnh nhân, Lịch khám, Phiếu khám](#tuần-2--bệnh-nhân-lịch-khám-phiếu-khám)
  - T2.1 → T2.11
- [Tuần 3 — Thuốc, Đơn thuốc, Hóa đơn, Thanh toán](#tuần-3--thuốc-đơn-thuốc-hóa-đơn-thanh-toán)
  - T3.1 → T3.17
- [Tuần 4 — Audit log, Stats, Test, Đóng gói](#tuần-4--audit-log-stats-test-đóng-gói)
  - T4.1 → T4.3 + task đóng gói bổ sung

---

# Tuần 1 — Nền tảng: Docker, Auth, RBAC, danh mục

**Mục tiêu tuần:** Docker + Laravel + Postgres chạy được; Sanctum login/logout/me; schema RBAC (roles, permissions, role_permissions, users.role_id) + catalog ~52 permission; Middleware `EnsurePermission`; CRUD specialties/doctors/users; chốt kiến trúc B.

---

## T1.1 — Setup Docker môi trường Ubuntu 24 + Laravel

**1. Nội dung task gốc**
> Cài Docker Engine và Docker Compose trên Ubuntu 24. Tạo project Laravel mới trong workspace. Đảm bảo chạy được container PHP/Laravel cơ bản trước khi nối database. Ghi lại phiên bản Docker/Compose vào README.

**2. Diễn giải chi tiết**
- **Vấn đề:** dựng môi trường lập trình đồng nhất qua Docker để mentor chấm giống hệt máy dev.
- **Các bước:**
  1. Cài Docker Engine + Docker Compose plugin (`docker --version`, `docker compose version`).
  2. Project Laravel 13 đã có sẵn trong repo (skeleton). Xác nhận `composer install` chạy được trong container.
  3. Dựng một container PHP CLI chạy `php artisan serve --host=0.0.0.0 --port=8000`, mở `http://localhost:8000` thấy trang Laravel.
  4. Ghi phiên bản Docker/Compose vào README (mục Stack).
- **Cách tiếp cận:** ở bước này chưa cần Postgres — chỉ chứng minh app container sống. Việc nối DB để T1.4.

> Prompt vibe code: *"Xác nhận Laravel 13 skeleton chạy trong container PHP 8.3-cli với `php artisan serve` cổng 8000; ghi version Docker/Compose vào README."*

**3. Mở rộng**
- Thêm alias/script `make up`, `make sh` để rút gọn lệnh.
- Bật opcache cho PHP trong image dev.

**4. Checklist hoàn thành**
- [ ] `docker --version` và `docker compose version` hoạt động, đã ghi vào README.
- [ ] Truy cập `http://localhost:8000` thấy Laravel welcome (hoặc trả JSON health).
- [ ] `composer install` chạy trong container không lỗi.

**5. Checklist self-test**
- [ ] `docker compose up -d --build` không lỗi.
- [ ] `docker compose exec app php -v` → PHP 8.3.
- [ ] `docker compose exec app php artisan --version` → Laravel 13.

---

## T1.2 — Viết Dockerfile và docker-compose.yml

**1. Nội dung task gốc**
> Tạo Dockerfile cho app Laravel và docker-compose.yml gồm service app + postgres:16. Persist DB bằng Docker volume. Map port API (gợi ý 8000). Mentor phải chạy được: `docker compose up -d --build`.

**2. Diễn giải chi tiết**
- **Vấn đề:** đóng gói app + DB thành 2 service liên kết, dữ liệu DB không mất khi container tắt.
- **Các bước:**
  1. **Dockerfile** (`php:8.3-cli`): cài `libpq-dev`, `libzip-dev`, extension `pdo_pgsql`, `zip`; copy composer từ image `composer:2`; `composer install --no-scripts`; `EXPOSE 8000`; `CMD php artisan serve`.
  2. **docker-compose.yml**: service `app` (build từ Dockerfile, map `8000:8000`, mount `.:/var/www`), service `db` (`postgres:16`, volume `clinic_postgres_data`, healthcheck `pg_isready`). `app.depends_on: db (service_healthy)`.
  3. Map port host Postgres `5433:5432` để không đụng Postgres local.
- **Cách tiếp cận:** healthcheck + `depends_on: condition: service_healthy` để app không migrate khi DB chưa sẵn sàng.

> Prompt vibe code: *"Viết Dockerfile PHP 8.3-cli cài pdo_pgsql/zip + composer, và docker-compose.yml gồm app (port 8000) + postgres:16 (volume, healthcheck pg_isready, app depends_on db service_healthy)."*

**3. Mở rộng**
- Thêm service `queue` chạy `php artisan queue:listen` (cho T4 queue job).
- Thêm `adminer`/`pgadmin` để xem DB trực quan.

**4. Checklist hoàn thành**
- [ ] `docker compose up -d --build` dựng cả 2 service.
- [ ] Volume Postgres persist (tắt/bật container, dữ liệu còn).
- [ ] Port API map ra `8000`.

**5. Checklist self-test**
- [ ] `docker compose ps` → `app` up, `db` healthy.
- [ ] `docker compose down && docker compose up -d` → dữ liệu DB không mất.
- [ ] `docker compose exec app php artisan tinker` → `DB::connection()->getPdo()` OK (sau T1.4).

Chi tiết: [skills/docker.md](../skills/docker.md).

---

## T1.3 — Chuẩn hóa .env.example cho clinic + PayPal Sandbox

**1. Nội dung task gốc**
> Commit `.env.example` (không commit `.env`) với `DB_CONNECTION=pgsql`, `DB_HOST=db`, `EXAMINATION_FEE`, `PAYPAL_MODE=sandbox`, `PAYPAL_CLIENT_ID/SECRET` (placeholder), `PAYPAL_CURRENCY`. README giải thích từng biến.

**2. Diễn giải chi tiết**
- **Vấn đề:** cấu hình phải tự-tài-liệu và an toàn (không lộ secret).
- **Các bước:**
  1. Thêm vào `.env.example` các biến DB (`pgsql`, `db`, `5432`, `clinic`, ...), `EXAMINATION_FEE=100000`, block PayPal (`PAYPAL_MODE=sandbox`, `PAYPAL_CLIENT_ID=your-sandbox-client-id`, `PAYPAL_CLIENT_SECRET=your-sandbox-client-secret`, `PAYPAL_CURRENCY=USD`).
  2. `.gitignore` chắc chắn chứa `.env`.
  3. README mục 5 giải thích từng biến (đã có).
- **Cách tiếp cận:** `.env.example` chỉ placeholder; secret thật chỉ nằm ở `.env` local.

> Prompt vibe code: *"Bổ sung .env.example các biến DB pgsql (host db), EXAMINATION_FEE, và block PayPal sandbox (client id/secret placeholder, currency USD). Đảm bảo .gitignore bỏ qua .env."*

**3. Mở rộng**
- Thêm `config/paypal.php` đọc từ env để không rải `env()` khắp code (Laravel best practice: chỉ đọc env trong config).

**4. Checklist hoàn thành**
- [ ] `.env.example` đủ biến DB + EXAMINATION_FEE + PayPal.
- [ ] `.env` KHÔNG bị commit (`git status` sạch).
- [ ] README giải thích từng biến.

**5. Checklist self-test**
- [ ] `git ls-files | grep -x .env` rỗng.
- [ ] `cp .env.example .env && php artisan key:generate` chạy được.

---

## T1.4 — Kết nối Laravel với PostgreSQL trong Docker

**1. Nội dung task gốc**
> Cấu hình database pgsql trỏ vào service db. Chạy migrate thành công trong container. Xử lý chờ Postgres ready (healthcheck/depends_on) nếu cần.

**2. Diễn giải chi tiết**
- **Vấn đề:** app phải kết nối được Postgres bằng tên service `db`, migrate sạch.
- **Các bước:**
  1. `.env`: `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, đúng user/pass/db.
  2. `docker compose exec app php artisan migrate` → các bảng mặc định (users, cache, jobs) tạo được.
  3. Nếu migrate chạy trước khi DB ready → dựa vào healthcheck + `depends_on` (đã làm ở T1.2); có thể thêm retry trong entrypoint.
- **Cách tiếp cận:** kiểm chứng bằng `tinker`: `DB::connection()->getPdo()`.

> Prompt vibe code: *"Cấu hình kết nối pgsql tới service db, chạy `artisan migrate` trong container thành công; xử lý chờ Postgres healthy trước khi migrate."*

**3. Mở rộng**
- Thêm `php artisan migrate --graceful` để không fail hard khi chưa có DB.

**4. Checklist hoàn thành**
- [ ] `artisan migrate` chạy trong container không lỗi.
- [ ] Kết nối dùng `DB_HOST=db` (không phải localhost).

**5. Checklist self-test**
- [ ] `docker compose exec app php artisan migrate:status` liệt kê migration đã chạy.
- [ ] `docker compose exec db psql -U clinic -d clinic -c '\dt'` thấy các bảng.

---

## T1.5 — Auth Sanctum (login, logout, me) + schema RBAC catalog

**1. Nội dung task gốc**
> Tạo migration cho roles, permissions, role_permissions. `permissions.name` unique dạng `CONTROLLER.ACTION`. `role_permissions UNIQUE(role_id, permission_id)`. Không dùng Spatie.
> *(Tiêu đề task: Auth Sanctum — login, logout, me. Phần schema RBAC được gộp vào đây do file task không tách T1.6.)*

**2. Diễn giải chi tiết**
- **Vấn đề kép:** (a) cài Sanctum và 3 endpoint auth; (b) dựng schema RBAC nền tảng.
- **Các bước — Auth Sanctum:**
  1. Cài `laravel/sanctum`, publish config, migrate `personal_access_tokens`.
  2. `User` dùng trait `HasApiTokens`.
  3. `AuthController@login`: validate email/password; sai hoặc `is_active=false` → 401; đúng → tạo token, trả `{ token, user }`.
  4. `AuthController@logout`: xóa token hiện tại (`currentAccessToken()->delete()`).
  5. `AuthController@me`: trả user + role + danh sách permission.
  6. Route: `login` public; `logout`, `me` trong group `auth:sanctum`.
- **Các bước — Schema RBAC:**
  1. Migration `roles` (name UNIQUE, display_name).
  2. Migration `permissions` (name UNIQUE dạng `CONTROLLER.ACTION`, display_name).
  3. Migration `role_permissions` (role_id, permission_id, `UNIQUE(role_id, permission_id)`).
  4. **Không** dùng Spatie — tự thiết kế.
- **Cách tiếp cận:** làm schema RBAC trước vì T1.7–T1.10 phụ thuộc; auth có thể test ngay sau khi có `users.role_id` (T1.7).

> Prompt vibe code: *"Cài Sanctum; viết AuthController login/logout/me (sai pass hoặc is_active=false → 401, envelope chuẩn). Tạo migration roles/permissions/role_permissions với UNIQUE theo đề, không dùng Spatie."*

**3. Mở rộng**
- `/api/me` trả kèm mảng permission để frontend ẩn/hiện menu.
- Token abilities theo permission (nâng cao).

**4. Checklist hoàn thành**
- [ ] Sanctum cài đặt, `personal_access_tokens` migrate.
- [ ] `login`/`logout`/`me` hoạt động, envelope chuẩn.
- [ ] Migration `roles`/`permissions`/`role_permissions` đủ UNIQUE, không Spatie.

**5. Checklist self-test**
- [ ] Login đúng → 200 + token; sai pass → 401; account khóa → 401.
- [ ] Gọi `me` không token → 401; có token → 200 + role.
- [ ] `logout` xong token cũ dùng lại → 401.

Chi tiết: [skills/backend.md](../skills/backend.md), [skills/database.md](../skills/database.md).

---

## T1.7 — Gắn role cho user + seed 5 role

**1. Nội dung task gốc**
> Thêm `users.role_id` FK → roles.id. Seed roles: ADMIN, RECEPTIONIST, DOCTOR, PHARMACIST, CASHIER (kèm display_name). Mỗi user chỉ có đúng 1 role.

**2. Diễn giải chi tiết**
- **Vấn đề:** gắn mỗi user vào đúng 1 role qua khóa ngoại.
- **Các bước:**
  1. Migration thêm cột `role_id` (FK → roles.id, NOT NULL) vào bảng `users`.
  2. Seeder `RoleSeeder` tạo 5 role kèm `display_name` (vd `ADMIN` → "Quản trị viên").
  3. Model `User belongsTo Role`; `Role hasMany User`.
- **Cách tiếp cận:** `role_id` NOT NULL nên seed role phải chạy trước seed user.

> Prompt vibe code: *"Thêm users.role_id (FK roles, NOT NULL). Seed 5 role ADMIN/RECEPTIONIST/DOCTOR/PHARMACIST/CASHIER kèm display_name. Quan hệ User belongsTo Role."*

**3. Mở rộng**
- Cache map role→permission theo request để giảm query (điểm cộng).

**4. Checklist hoàn thành**
- [ ] `users.role_id` FK NOT NULL.
- [ ] 5 role được seed với display_name.
- [ ] `User->role` trả về role.

**5. Checklist self-test**
- [ ] `SELECT name FROM roles` → 5 dòng.
- [ ] Tạo user thiếu role_id → lỗi (NOT NULL) đúng như mong đợi.

---

## T1.8 — Seed permissions và role_permissions theo map đề bài

**1. Nội dung task gốc**
> Seed đầy đủ permission `CONTROLLER.ACTION` (`PATIENTS.CREATE`, `PAYMENTS.CAPTURE`…). Map đúng quyền cho từng role theo bảng mục 3.4 trong đề.

**2. Diễn giải chi tiết**
- **Vấn đề:** khởi tạo ~52 permission và gán chính xác cho từng role.
- **Các bước:**
  1. Định nghĩa danh sách permission (xem ma trận trong [README mục 7](../README.md#7-rbac-global)).
  2. Seeder/migration idempotent tạo `permissions` (dùng `updateOrInsert` theo `name`).
  3. Gán `role_permissions` theo ma trận; ADMIN nhận **tất cả**.
  4. `permissions` nên đặt trong **data migration idempotent** để môi trường đã seed vẫn nhận khi migrate lại (yêu cầu đề: "thêm permission = migration").
- **Cách tiếp cận:** giữ 1 nguồn sự thật cho map (mảng PHP), tránh lệch giữa README và code.

> Prompt vibe code: *"Seed ~52 permission CONTROLLER.ACTION và role_permissions theo ma trận README mục 7; ADMIN full; dùng updateOrInsert idempotent."*

**3. Mở rộng**
- Thêm 1 permission mới bằng data migration thật (vd `MEDICINES.LOWSTOCK`) để chứng minh cơ chế (điểm cộng).

**4. Checklist hoàn thành**
- [ ] Đủ ~52 permission `CONTROLLER.ACTION`.
- [ ] `role_permissions` khớp ma trận; ADMIN full.
- [ ] Seeder/migration idempotent (chạy lại không lỗi trùng).

**5. Checklist self-test**
- [ ] `SELECT count(*) FROM permissions` ≈ 52.
- [ ] ADMIN có mọi permission; CASHIER có `INVOICES.*`/`PAYMENTS.*` nhưng không có `EXAMINATIONS.CREATE`.
- [ ] Chạy seeder 2 lần không tạo bản trùng.

---

## T1.9 — Seed tài khoản ADMIN đầu tiên

**1. Nội dung task gốc**
> Seeder tạo user `admin@clinic.test` (hoặc email ghi trong README) với role ADMIN và password hash. Sau `migrate --seed` có thể login ngay.

**2. Diễn giải chi tiết**
- **Vấn đề:** phải có 1 ADMIN để đăng nhập ngay (không có register công khai).
- **Các bước:**
  1. `AdminSeeder` tạo user role ADMIN, password `Hash::make(...)`.
  2. Ghi email/mật khẩu mặc định vào README (đã có mục 6).
  3. Thứ tự seeder: Role → Permission/role_permissions → Admin → demo.
- **Cách tiếp cận:** dùng `firstOrCreate` theo email để idempotent.

> Prompt vibe code: *"Seeder tạo ADMIN admin@clinic.test role ADMIN, password hash, idempotent; login được ngay sau migrate --seed."*

**3. Mở rộng**
- Seed thêm 1 user mẫu mỗi role (RECEPTIONIST/DOCTOR/PHARMACIST/CASHIER) để test RBAC nhanh.

**4. Checklist hoàn thành**
- [ ] ADMIN được seed, login ngay sau `migrate --seed`.
- [ ] Email/mật khẩu ghi trong README.

**5. Checklist self-test**
- [ ] `POST /api/login` với ADMIN → 200 + token.
- [ ] `GET /api/me` → role ADMIN.

---

## T1.10 — Middleware EnsurePermission (Controller → CONTROLLER.ACTION)

**1. Nội dung task gốc**
> Middleware lấy Controller class + action method hiện tại, map sang tên permission (`index→FINDALL`, `store→CREATE`…). Check role của user có permission hay không; thiếu → 403. Gắn vào group route API nghiệp vụ.

**2. Diễn giải chi tiết**
- **Vấn đề:** cơ chế phân quyền trung tâm, không rải `if role ==` trong controller.
- **Các bước:**
  1. Middleware `EnsurePermission` đọc route action (`$request->route()->getActionName()` → `App\Http\Controllers\PatientController@index`).
  2. Rút `PATIENT` (bỏ hậu tố `Controller`, số nhiều hóa → `PATIENTS`) + map action → suffix theo bảng quy ước (`index→FINDALL`,…) → `PATIENTS.FINDALL`.
  3. Kiểm tra role của user có permission đó (`$user->role->permissions`), thiếu → 403.
  4. Gắn middleware vào group route nghiệp vụ (sau `auth:sanctum`).
  5. Thêm helper `$user->can('PATIENTS.CREATE')` (tên tự đặt) dùng cho check thủ công.
- **Cách tiếp cận:** bảng map action→suffix là hằng số; controller name → resource name cần quy tắc số nhiều nhất quán (khuyến nghị map tường minh để tránh sai số nhiều bất quy tắc).

> Prompt vibe code: *"Viết middleware EnsurePermission: từ route action lấy Controller@method, map thành PERMISSION.NAME theo bảng quy ước, check role user, thiếu → 403. Thêm helper user->can(permission). Gắn vào group route /api."*

**3. Mở rộng**
- Cache map permission của role theo request (điểm cộng).
- Bảng map controller→resource tường minh trong config để tránh lỗi số nhiều.

**4. Checklist hoàn thành**
- [ ] Middleware resolve đúng `Controller@action → PERMISSION`.
- [ ] Thiếu quyền → 403 envelope chuẩn.
- [ ] Không hard-code role trong controller.

**5. Checklist self-test**
- [ ] RECEPTIONIST gọi `POST /api/invoices` → 403.
- [ ] ADMIN gọi endpoint bất kỳ → 200/201.
- [ ] DOCTOR gọi `POST /api/payments/{id}/capture` → 403.

Chi tiết: [skills/backend.md](../skills/backend.md).

---

## T1.11 — Chọn kiến trúc B hoặc C và ghi README

**1. Nội dung task gốc**
> Chọn Controller+Service (B) hoặc +Repository (C). Code nhất quán theo lựa chọn. README có mục "Kiến trúc đã chọn" + lý do + sơ đồ luồng request.

**2. Diễn giải chi tiết**
- **Quyết định dự án: chọn B (Controller + Service).** Lý do và sơ đồ đã ghi ở [README mục 3](../README.md#3-kiến-trúc-đã-chọn-b--controller--service).
- **Các bước:**
  1. Chuẩn hóa cấu trúc: `Controllers/`, `Services/`, `Requests/`, `Resources/`.
  2. Mọi controller mỏng: `FormRequest → Service → Resource`.
  3. README ghi mục kiến trúc + sơ đồ (đã xong).
- **Cách tiếp cận:** giữ nhất quán từ resource đầu tiên (Specialties/Doctors ở T1.14–T1.15) để không phải refactor.

**3. Mở rộng**
- Base `ApiController` + trait trả envelope thống nhất.

**4. Checklist hoàn thành**
- [ ] README có mục kiến trúc + lý do + sơ đồ.
- [ ] Code resource đầu tiên tuân theo B.

**5. Checklist self-test**
- [ ] Không có business logic trong controller (chỉ điều phối).
- [ ] Service chứa transaction/business rule.

---

## T1.12 — Form Request, API Resource, envelope JSON chuẩn

**1. Nội dung task gốc**
> Mọi API ghi dùng Form Request. Response dùng API Resource. Envelope: `success/message/data` (và `errors` với 422; `meta` khi paginate). Exception Handler trả JSON cho API, không trả HTML.

**2. Diễn giải chi tiết**
- **Vấn đề:** chuẩn hóa input validation và output format toàn hệ thống.
- **Các bước:**
  1. Base response helper/trait: `successResponse($data, $message, $status)` và `errorResponse(...)`.
  2. Form Request cho mọi endpoint ghi (rules + messages).
  3. API Resource cho mọi output; ResourceCollection cho list + `meta` phân trang.
  4. Cấu hình Exception Handler: `ValidationException` → 422 với `errors`; `AuthenticationException` → 401; `AccessDenied`/permission → 403; `ModelNotFound` → 404; luôn JSON cho request `/api/*`.
- **Cách tiếp cận:** làm helper + handler một lần, các resource sau tái dùng.

> Prompt vibe code: *"Tạo trait ApiResponse (success/error envelope), cấu hình Exception Handler trả JSON cho /api (422 kèm errors theo field, 401/403/404). Mẫu Form Request + API Resource để tái dùng."*

**3. Mở rộng**
- Chuẩn hóa message đa ngôn ngữ (lang files).

**4. Checklist hoàn thành**
- [ ] Envelope chuẩn cho success/fail.
- [ ] 422 có `errors` theo field; list có `meta`.
- [ ] Exception Handler trả JSON, không HTML cho `/api`.

**5. Checklist self-test**
- [ ] Gửi body thiếu field bắt buộc → 422 + `errors`.
- [ ] Truy cập resource không tồn tại → 404 JSON.
- [ ] List có phân trang → có `meta.current_page/total`.

Chi tiết: [skills/backend.md](../skills/backend.md).

---

## T1.13 — CRUD Users (chỉ ADMIN) + bảo vệ ADMIN cuối cùng

**1. Nội dung task gốc**
> API tạo/sửa/list/khóa user, gán `role_id`. Chỉ ADMIN (`USERS.*`). Không cho đổi role hoặc deactivate ADMIN cuối cùng còn lại trong hệ thống → 422.

**2. Diễn giải chi tiết**
- **Vấn đề:** quản trị nhân sự, kèm quy tắc an toàn "không tự khóa hết ADMIN".
- **Các bước:**
  1. `UserController` index/store/show/update/destroy/updateStatus với permission `USERS.*`.
  2. `store`: validate email unique, password, role_id hợp lệ.
  3. `destroy` = deactivate (`is_active=false`), `updateStatus` bật/khóa.
  4. **Guard ADMIN cuối:** trước khi đổi role khỏi ADMIN hoặc deactivate, đếm số ADMIN đang `is_active`; nếu đây là ADMIN cuối → 422.
- **Cách tiếp cận:** guard đặt trong Service (`UserService::assertNotLastActiveAdmin`).

> Prompt vibe code: *"CRUD Users chỉ ADMIN (USERS.*): tạo/sửa/list/khóa, gán role_id. Chặn đổi role/deactivate ADMIN cuối cùng còn active → 422 với errors rõ ràng."*

**3. Mở rộng**
- Filter list user theo role; không trả password/hash.

**4. Checklist hoàn thành**
- [ ] CRUD Users hoạt động, chỉ ADMIN.
- [ ] Guard ADMIN cuối → 422.
- [ ] `destroy` là soft-deactivate (`is_active=false`), không xóa cứng.

**5. Checklist self-test**
- [ ] Chỉ còn 1 ADMIN → thử deactivate/đổi role → 422.
- [ ] RECEPTIONIST gọi `USERS.*` → 403.
- [ ] Tạo user trùng email → 422.

---

## T1.14 — CRUD Specialties (chuyên khoa)

**1. Nội dung task gốc**
> Migration `specialties` (name unique, description). CRUD API với permission `SPECIALTIES.*`. Dùng cho gắn bác sĩ theo chuyên khoa.

**2. Diễn giải chi tiết**
- **Vấn đề:** danh mục chuyên khoa nền cho hồ sơ bác sĩ.
- **Các bước:**
  1. Migration `specialties` (name UNIQUE, description nullable, timestamps).
  2. `SpecialtyController` CRUD + `SPECIALTIES.*`.
  3. Form Request (name required unique), Resource.
- **Cách tiếp cận:** resource CRUD "chuẩn" đầu tiên — dùng làm khuôn mẫu cho các resource sau.

> Prompt vibe code: *"Migration specialties (name unique, description). CRUD SpecialtyController theo permission SPECIALTIES.*, Form Request + Resource theo kiến trúc B."*

**3. Mở rộng**
- Chặn xóa chuyên khoa đang có bác sĩ (FK restrict) → 422 message rõ.

**4. Checklist hoàn thành**
- [ ] Migration `specialties` name unique.
- [ ] CRUD + `SPECIALTIES.*`.

**5. Checklist self-test**
- [ ] Tạo trùng name → 422.
- [ ] RECEPTIONIST chỉ `FINDALL/FINDONE` được, `CREATE` → 403.

---

## T1.15 — CRUD Doctors (hồ sơ bác sĩ 1-1 với user)

**1. Nội dung task gốc**
> Bảng `doctors`: `user_id` unique (user phải role DOCTOR), `specialty_id`, `license_number`, `bio`. CRUD theo `DOCTORS.*`. Không tạo doctor cho user sai role.

**2. Diễn giải chi tiết**
- **Vấn đề:** hồ sơ bác sĩ 1-1 với user role DOCTOR.
- **Các bước:**
  1. Migration `doctors` (`user_id` UNIQUE FK, `specialty_id` FK, `license_number` khuyến nghị UNIQUE, `bio` nullable).
  2. `DoctorController` CRUD + `DOCTORS.*`.
  3. **Validate business:** `user_id` phải trỏ user role DOCTOR và chưa có hồ sơ doctor → nếu sai role/đã có → 422.
  4. Filter list theo `specialty_id`.
- **Cách tiếp cận:** guard role trong Service; eager load `doctor.user`, `doctor.specialty`.

> Prompt vibe code: *"Migration doctors (user_id unique, specialty_id, license_number, bio). CRUD DoctorController DOCTORS.*; chặn tạo doctor nếu user không phải role DOCTOR hoặc đã có hồ sơ → 422. Filter theo specialty_id."*

**3. Mở rộng**
- Trả kèm thông tin user + specialty trong Resource (tránh N+1 bằng eager load).

**4. Checklist hoàn thành**
- [ ] `doctors.user_id` UNIQUE FK.
- [ ] Chặn user sai role/đã có hồ sơ → 422.
- [ ] Filter theo `specialty_id`.

**5. Checklist self-test**
- [ ] Tạo doctor cho user role RECEPTIONIST → 422.
- [ ] Tạo doctor thứ 2 cho cùng user → 422 (UNIQUE).
- [ ] List filter `?specialty_id=` đúng.

---

# Tuần 2 — Bệnh nhân, Lịch khám, Phiếu khám

**Mục tiêu tuần:** patients CRUD + soft delete + search; appointments CRUD + máy trạng thái + chống trùng lịch + index; examinations tạo từ appointment (transaction cập nhật status); feature test tuần 2.

---

## T2.1 — Migration và model Patients

**1. Nội dung task gốc**
> Tạo bảng `patients`: `code` unique, `full_name`, `gender`, `date_of_birth`, `phone`, `email/address` nullable, timestamps. Khuyến khích soft delete. Index phục vụ tìm kiếm theo tên/SĐT/mã.

**2. Diễn giải chi tiết**
- **Vấn đề:** hồ sơ bệnh nhân + hỗ trợ tra cứu nhanh.
- **Các bước:**
  1. Migration `patients`: `code` UNIQUE (tự sinh, vd `BN-000123`), `full_name`, `gender` CHECK(male/female/other), `date_of_birth` date, `phone` (index), `email`/`address` nullable, `deleted_at` (soft delete), timestamps.
  2. Model `Patient` dùng `SoftDeletes`.
  3. Index cho `phone`; cân nhắc index/tìm kiếm theo `full_name`, `code`.
- **Cách tiếp cận:** sinh `code` trong Service khi tạo (sequence hoặc `BN-` + id padding).

> Prompt vibe code: *"Migration patients (code unique, full_name, gender CHECK male/female/other, date_of_birth, phone index, email/address nullable, soft delete). Model SoftDeletes. Sinh code BN-xxxxxx khi tạo."*

**3. Mở rộng**
- Full-text/GIN index cho tìm kiếm tên (Postgres) — điểm cộng.

**4. Checklist hoàn thành**
- [ ] Bảng `patients` đủ cột + `code` UNIQUE + soft delete.
- [ ] CHECK `gender`.
- [ ] Index phục vụ search.

**5. Checklist self-test**
- [ ] Insert `gender` sai giá trị → lỗi CHECK.
- [ ] Soft delete rồi query mặc định không thấy.

---

## T2.2 — CRUD Patients + search/filter

**1. Nội dung task gốc**
> API list/create/update/show/delete bệnh nhân. Filter/search theo `q` (tên, SĐT, code). Phân quyền: RECEPTIONIST/DOCTOR/CASHIER theo map (CASHIER chủ yếu đọc).

**2. Diễn giải chi tiết**
- **Vấn đề:** CRUD + tìm kiếm bệnh nhân theo nhiều tiêu chí.
- **Các bước:**
  1. `PatientController` CRUD + `PATIENTS.*`; `destroy` soft delete.
  2. `index` nhận `q` → search `full_name ILIKE`, `phone`, `code`; phân trang + `meta`.
  3. Phân quyền theo ma trận: RECEPTIONIST create/update; CASHIER read-only; DOCTOR read.
- **Cách tiếp cận:** Query scope `scopeSearch($q)` gọn trong Model/Service.

> Prompt vibe code: *"CRUD Patients + PATIENTS.*; index search theo q (full_name ILIKE, phone, code) + phân trang meta; destroy soft delete. Phân quyền RECEPTIONIST ghi, CASHIER/DOCTOR đọc."*

**3. Mở rộng**
- Filter theo `gender`, khoảng `date_of_birth`.

**4. Checklist hoàn thành**
- [ ] CRUD + search `q` hoạt động.
- [ ] Phân quyền đúng ma trận.
- [ ] `destroy` soft delete.

**5. Checklist self-test**
- [ ] `?q=` khớp theo tên/SĐT/mã.
- [ ] CASHIER `POST /api/patients` → 403.
- [ ] List có `meta` phân trang.

---

## T2.3 — Migration Appointments + index

**1. Nội dung task gốc**
> Bảng `appointments`: `patient_id`, `doctor_id`, `scheduled_at`, `status`, `reason`. Index: `(doctor_id, scheduled_at)`, `patient_id`, `status`. CHECK/ENUM status.

**2. Diễn giải chi tiết**
- **Vấn đề:** lịch khám + index phục vụ query theo bác sĩ/ngày/trạng thái.
- **Các bước:**
  1. Migration `appointments`: FK `patient_id`, `doctor_id`; `scheduled_at` timestamp; `status` CHECK(scheduled/confirmed/cancelled/completed) default `scheduled`; `reason` nullable.
  2. Index: `(doctor_id, scheduled_at)`, `(patient_id)`, `(status)`.
- **Cách tiếp cận:** index composite `(doctor_id, scheduled_at)` phục vụ cả filter bác sĩ + chống trùng lịch (T2.6).

> Prompt vibe code: *"Migration appointments (patient_id, doctor_id FK, scheduled_at, status CHECK default scheduled, reason nullable). Index (doctor_id, scheduled_at), patient_id, status."*

**3. Mở rộng**
- Partial index `status != cancelled` (điểm cộng).

**4. Checklist hoàn thành**
- [ ] Bảng + CHECK status + 3 index.

**5. Checklist self-test**
- [ ] `\d appointments` thấy index đúng.
- [ ] Insert status sai → lỗi CHECK.

---

## T2.4 — CRUD Appointments — tạo lịch scheduled

**1. Nội dung task gốc**
> Lễ tân tạo lịch khám gắn patient + doctor + thời điểm. Status mặc định `scheduled`. Validate doctor/patient tồn tại. Permission `APPOINTMENTS.CREATE`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `AppointmentController` index/store/show/update + `APPOINTMENTS.*`.
  2. `store`: validate `patient_id`, `doctor_id` tồn tại, `scheduled_at`; status = `scheduled`.
  3. `update`: chỉ sửa giờ/lý do khi còn `scheduled`.
  4. `index` filter `doctor_id`, `patient_id`, `status`, `date`.
- **Cách tiếp cận:** để chống trùng lịch riêng ở T2.6, đổi status riêng ở T2.5.

> Prompt vibe code: *"CRUD Appointments + APPOINTMENTS.*; store gắn patient+doctor+scheduled_at, status mặc định scheduled, validate tồn tại; index filter doctor_id/patient_id/status/date."*

**3. Mở rộng**
- Trả kèm `patient`, `doctor.user` (eager load).

**4. Checklist hoàn thành**
- [ ] CRUD cơ bản + filter.
- [ ] Status mặc định scheduled.

**5. Checklist self-test**
- [ ] Tạo với doctor không tồn tại → 422.
- [ ] Filter `?doctor_id=&status=` đúng.

---

## T2.5 — Máy trạng thái lịch khám

**1. Nội dung task gốc**
> Cho phép chuyển: `scheduled→confirmed→completed`; `scheduled/confirmed→cancelled`. Chặn transition trái quy tắc → 422. API PATCH status riêng hoặc update có kiểm soát.

**2. Diễn giải chi tiết**
- **Vấn đề:** đảm bảo vòng đời lịch khám hợp lệ.
- **Các bước:**
  1. Endpoint `PATCH /api/appointments/{id}/status` → `updateStatus` (`APPOINTMENTS.UPDATESTATUS`).
  2. Bảng transition hợp lệ; transition sai → 422 message rõ.
  3. `completed` thường do tạo phiếu khám (T2.9) đặt — nhưng máy trạng thái phải chặn nhảy cóc (vd `cancelled→completed`).
- **Cách tiếp cận:** map transition trong Service (`ALLOWED = [scheduled=>[confirmed,cancelled], confirmed=>[completed,cancelled]]`).

> Prompt vibe code: *"updateStatus cho appointment theo bảng transition hợp lệ (scheduled→confirmed→completed; scheduled/confirmed→cancelled); transition sai → 422."*

**3. Mở rộng**
- Ghi `activity_logs` mỗi lần đổi status (chuẩn bị T4.1).

**4. Checklist hoàn thành**
- [ ] `updateStatus` enforce transition.
- [ ] Transition sai → 422.

**5. Checklist self-test**
- [ ] `cancelled → completed` → 422.
- [ ] `scheduled → confirmed → cancelled` OK.

---

## T2.6 — Chống trùng lịch bác sĩ

**1. Nội dung task gốc**
> Khi tạo/sửa lịch, kiểm tra bác sĩ đã có appointment chồng khung giờ (trừ cancelled). Conflict → 422 với errors rõ ràng. Ghi rõ rule trong README.

**2. Diễn giải chi tiết**
- **Vấn đề:** một bác sĩ không thể có 2 lịch trùng giờ.
- **Các bước:**
  1. Định nghĩa "khung giờ" (vd mỗi lịch chiếm 30 phút — ghi rõ rule trong README).
  2. Khi `store`/`update`: query appointment cùng `doctor_id`, khác `cancelled`, có khoảng thời gian giao nhau → nếu có → 422.
  3. Dùng index `(doctor_id, scheduled_at)`.
- **Cách tiếp cận:** kiểm tra overlap `[start, end)`; loại chính bản ghi đang sửa.

> Prompt vibe code: *"Chống trùng lịch: khi tạo/sửa appointment, chặn bác sĩ có lịch chồng khung giờ (trừ cancelled) → 422. Định nghĩa slot 30 phút, ghi rule README."*

**3. Mở rộng**
- Exclusion constraint Postgres (`tstzrange` + `EXCLUDE`) chống trùng ở tầng DB (nâng cao).

**4. Checklist hoàn thành**
- [ ] Conflict check khi tạo/sửa → 422.
- [ ] Rule ghi trong README.

**5. Checklist self-test**
- [ ] Tạo 2 lịch cùng bác sĩ trùng giờ → lịch 2 nhận 422.
- [ ] Lịch đã `cancelled` không tính là trùng.

---

## T2.7 — Migration Examinations (phiếu khám)

**1. Nội dung task gốc**
> Bảng `examinations`: `appointment_id` UNIQUE, `doctor_id`, `patient_id`, `diagnosis`, `notes`, `examined_at`. Đảm bảo 1 lịch chỉ có tối đa 1 phiếu khám.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Migration `examinations`: `appointment_id` UNIQUE FK, `doctor_id` FK, `patient_id` FK, `diagnosis` text, `notes` nullable, `examined_at` timestamp.
  2. UNIQUE `appointment_id` đảm bảo 1-1 với appointment.
- **Cách tiếp cận:** FK `restrict` (bảng lịch sử y tế).

> Prompt vibe code: *"Migration examinations (appointment_id unique FK, doctor_id, patient_id, diagnosis, notes nullable, examined_at). 1 appointment ↔ tối đa 1 examination."*

**3. Checklist hoàn thành**
- [ ] `appointment_id` UNIQUE.
- [ ] Đủ cột + FK.

**4. Checklist self-test**
- [ ] Tạo 2 examination cho cùng appointment → lỗi UNIQUE.

---

## T2.8 — Tạo phiếu khám từ lịch đã confirmed

**1. Nội dung task gốc**
> DOCTOR tạo examination từ appointment confirmed (mặc định đề). Lấy `patient_id`/`doctor_id` từ lịch, không cho lệch. Ghi `diagnosis`, `notes`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `ExaminationController@store` (`EXAMINATIONS.CREATE`) nhận `{appointment_id, diagnosis, notes}`.
  2. Lấy `patient_id`, `doctor_id` **từ appointment** (không nhận từ client) để không lệch.
  3. Chỉ cho tạo khi appointment `confirmed` (ghi rõ trong README nếu nới lỏng).
- **Cách tiếp cận:** ràng buộc status kiểm ở Service; transaction cập nhật status ở T2.9.

> Prompt vibe code: *"ExaminationController store: tạo phiếu khám từ appointment confirmed, lấy patient_id/doctor_id từ lịch (không nhận client), ghi diagnosis/notes; EXAMINATIONS.CREATE."*

**3. Checklist hoàn thành**
- [ ] Tạo phiếu từ appointment confirmed.
- [ ] `patient_id`/`doctor_id` lấy từ lịch.

**4. Checklist self-test**
- [ ] Truyền `patient_id` lệch → hệ thống bỏ qua, dùng của lịch.
- [ ] RECEPTIONIST tạo examination → 403.

---

## T2.9 — Transaction tạo phiếu khám + hoàn tất lịch

**1. Nội dung task gốc**
> Trong `DB::transaction`: insert examination và cập nhật `appointment.status = completed`. Rollback nếu một bước lỗi.

**2. Diễn giải chi tiết**
- **Vấn đề:** hai thao tác phải nguyên tử.
- **Các bước:**
  1. `DB::transaction`: tạo examination → set `appointment.status = completed`.
  2. Nếu bước nào lỗi → rollback toàn bộ.
- **Cách tiếp cận:** đặt trong `ExaminationService::createFromAppointment`.

> Prompt vibe code: *"Bọc tạo examination + cập nhật appointment.status=completed trong DB::transaction, rollback nếu lỗi."*

**3. Checklist hoàn thành**
- [ ] Transaction bao 2 bước.
- [ ] Appointment → completed sau khi tạo phiếu.

**4. Checklist self-test**
- [ ] Ép lỗi giữa chừng (vd throw) → không có examination lẫn thay đổi status.

---

## T2.10 — Chặn tạo phiếu khám từ lịch không hợp lệ

**1. Nội dung task gốc**
> Không tạo examination từ appointment cancelled hoặc đã completed / đã có phiếu. Trả 422 với message business rõ ràng.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Trước khi tạo: nếu appointment `cancelled`/`completed` hoặc đã có examination → 422.
- **Cách tiếp cận:** kết hợp check status + UNIQUE `appointment_id` (T2.7) làm hàng rào kép.

> Prompt vibe code: *"Chặn tạo examination nếu appointment cancelled/completed/đã có phiếu → 422 message rõ."*

**3. Checklist hoàn thành**
- [ ] 3 case bị chặn → 422.

**4. Checklist self-test**
- [ ] Tạo phiếu từ lịch cancelled → 422.
- [ ] Tạo phiếu lần 2 cho cùng lịch → 422.

---

## T2.11 — Feature test tuần 2 (auth, RBAC, patient, appointment)

**1. Nội dung task gốc**
> Viết feature test: login OK; role thiếu permission → 403; tạo patient; tạo appointment. Chạy được trong `docker compose exec app php artisan test`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Test login (200 + token), sai pass (401).
  2. Test RBAC: role thiếu permission → 403.
  3. Test tạo patient, tạo appointment (201).
  4. Dùng factory + `RefreshDatabase`.
- **Cách tiếp cận:** seed role/permission trong test setup để RBAC hoạt động.

> Prompt vibe code: *"Feature tests tuần 2: login OK/sai; RBAC 403; tạo patient; tạo appointment. Dùng RefreshDatabase + factory, seed RBAC trong setUp."*

**3. Checklist hoàn thành**
- [ ] Test login/RBAC/patient/appointment pass.
- [ ] Chạy trong container.

**4. Checklist self-test**
- [ ] `php artisan test --filter=Week2` xanh.
- [ ] Test RBAC thực sự nhận 403.

Chi tiết viết test: [skills/backend.md](../skills/backend.md).

---

# Tuần 3 — Thuốc, Đơn thuốc, Hóa đơn, Thanh toán

**Mục tiêu tuần:** medicines + adjustStock; prescriptions + items (trừ/hoàn kho trong transaction); invoices (tính tiền tự động); payments PayPal Sandbox (order + capture); method paypal & visa; bảo mật credential; seeder demo.

---

## T3.1 — CRUD Medicines (danh mục thuốc + tồn kho)

**1. Nội dung task gốc**
> Bảng `medicines`: `code` unique, `name`, `unit`, `price`, `stock` (>=0), `is_active`. CRUD theo `MEDICINES.*`. Thuốc `is_active=false` không cho kê vào đơn mới.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Migration `medicines`: `code` UNIQUE, `name`, `unit`, `price` decimal(12,2), `stock` int default 0 CHECK ≥ 0, `is_active` bool default true, `deleted_at` soft delete.
  2. `MedicineController` CRUD + `MEDICINES.*`; `destroy` soft delete.
  3. Filter `index` theo còn/hết hàng.
- **Cách tiếp cận:** guard `is_active` áp dụng ở T3.6 (khi kê đơn).

> Prompt vibe code: *"Migration medicines (code unique, name, unit, price decimal, stock CHECK>=0, is_active, soft delete). CRUD MEDICINES.*; filter còn/hết hàng."*

**3. Mở rộng**
- Endpoint `lowStock` + permission mới `MEDICINES.LOWSTOCK` bằng data migration (điểm cộng).

**4. Checklist hoàn thành**
- [ ] Bảng + CHECK stock ≥ 0 + soft delete.
- [ ] CRUD + filter.

**5. Checklist self-test**
- [ ] Set stock âm → lỗi CHECK.
- [ ] PHARMACIST CRUD được; DOCTOR chỉ đọc.

---

## T3.2 — API điều chỉnh kho thuốc (adjustStock)

**1. Nội dung task gốc**
> Endpoint PATCH stock với `quantity/note` (`MEDICINES.ADJUSTSTOCK`). Cập nhật tồn kho có kiểm tra không âm. Ghi activity log nếu đã có sẵn.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `PATCH /api/medicines/{id}/stock` → `adjustStock` (`MEDICINES.ADJUSTSTOCK`).
  2. `quantity` có thể âm/dương (nhập/xuất điều chỉnh); kết quả `stock` không được < 0 → 422.
  3. Ghi `activity_logs` (`stock_adjusted`) nếu đã có T4.1.
- **Cách tiếp cận:** cập nhật trong transaction + `lockForUpdate`.

> Prompt vibe code: *"adjustStock: PATCH stock {quantity, note}, cập nhật tồn có kiểm tra không âm (→422), lockForUpdate, ghi activity log stock_adjusted."*

**3. Checklist hoàn thành**
- [ ] adjustStock cập nhật đúng, chặn âm.
- [ ] Permission `MEDICINES.ADJUSTSTOCK`.

**4. Checklist self-test**
- [ ] Điều chỉnh khiến stock < 0 → 422.
- [ ] CASHIER gọi → 403.

---

## T3.3 — Migration Prescriptions và Prescription_items

**1. Nội dung task gốc**
> `prescriptions`: `examination_id` UNIQUE, `doctor_id`, `notes`. `prescription_items`: `medicine_id`, `quantity>0`, `dosage`, `usage_instruction`; `UNIQUE(prescription_id, medicine_id)`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Migration `prescriptions` (`examination_id` UNIQUE FK, `doctor_id` FK, `notes` nullable).
  2. Migration `prescription_items` (`prescription_id` FK, `medicine_id` FK, `quantity` CHECK>0, `dosage`, `usage_instruction` nullable, `UNIQUE(prescription_id, medicine_id)`).
- **Cách tiếp cận:** UNIQUE ngăn kê trùng thuốc trên cùng đơn.

> Prompt vibe code: *"Migration prescriptions (examination_id unique, doctor_id, notes) + prescription_items (medicine_id, quantity CHECK>0, dosage, usage_instruction, UNIQUE(prescription_id, medicine_id))."*

**3. Checklist hoàn thành**
- [ ] UNIQUE `examination_id`, `UNIQUE(prescription_id, medicine_id)`, CHECK quantity>0.

**4. Checklist self-test**
- [ ] Thêm trùng medicine trong 1 đơn → lỗi UNIQUE.
- [ ] quantity 0/âm → lỗi CHECK.

---

## T3.4 — Tạo đơn thuốc từ phiếu khám

**1. Nội dung task gốc**
> DOCTOR tạo prescription gắn examination (1 phiếu ↔ 1 đơn). Có thể nhận kèm mảng `items` lúc tạo. Permission `PRESCRIPTIONS.CREATE`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `PrescriptionController@store` `{examination_id, notes, items:[{medicine_id, quantity, dosage, usage_instruction}]}`.
  2. 1 examination ↔ 1 prescription (UNIQUE).
  3. Nếu có `items` → trừ kho (T3.6) trong cùng transaction.
- **Cách tiếp cận:** logic trừ kho tách hàm dùng chung cho store + addItem.

> Prompt vibe code: *"PrescriptionController store: tạo đơn gắn examination (1-1), nhận items kèm, PRESCRIPTIONS.CREATE; trừ kho trong transaction."*

**3. Checklist hoàn thành**
- [ ] Tạo đơn + items.
- [ ] 1 phiếu ↔ 1 đơn.

**4. Checklist self-test**
- [ ] Tạo đơn thứ 2 cho cùng examination → 422/UNIQUE.
- [ ] CASHIER tạo đơn → 403.

---

## T3.5 — Thêm / sửa / xóa dòng thuốc trong đơn

**1. Nội dung task gốc**
> API `addItem`, `updateItem`, `removeItem` với permission tương ứng. Không cho trùng medicine trên cùng đơn (dùng update để đổi số lượng).

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `POST /items` (`ADDITEM`), `PUT /items/{itemId}` (`UPDATEITEM`), `DELETE /items/{itemId}` (`REMOVEITEM`).
  2. addItem trùng medicine → 422 (yêu cầu dùng updateItem).
  3. Kho: addItem trừ; updateItem điều chỉnh delta; removeItem hoàn (T3.6/T3.7).
- **Cách tiếp cận:** validate item thuộc đúng prescription trong path.

> Prompt vibe code: *"addItem/updateItem/removeItem cho prescription items với permission tương ứng; addItem trùng medicine → 422; kho cập nhật trong transaction."*

**3. Checklist hoàn thành**
- [ ] 3 endpoint + permission.
- [ ] Chặn trùng medicine khi addItem.

**4. Checklist self-test**
- [ ] addItem medicine đã có → 422.
- [ ] updateItem/removeItem item không thuộc đơn → 404.

---

## T3.6 — Transaction trừ kho khi kê thuốc

**1. Nội dung task gốc**
> Khi thêm item / tạo đơn kèm items: `lockForUpdate` medicine, kiểm tra stock, trừ kho trong `DB::transaction`. Thiếu hàng → 422 + rollback toàn bộ.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Trong transaction: `Medicine::lockForUpdate()` từng medicine.
  2. Kiểm tra `is_active` + `stock >= quantity`; thiếu → 422 + rollback.
  3. Đủ → `stock -= quantity`, tạo item.
- **Cách tiếp cận:** `lockForUpdate` chống race-condition khi nhiều đơn trừ cùng thuốc.

> Prompt vibe code: *"Trừ kho khi kê thuốc: DB::transaction + lockForUpdate medicine, check is_active + stock>=quantity (thiếu→422 rollback), trừ stock."*

**3. Mở rộng**
- Test đồng thời 2 request trừ cùng thuốc → không âm (điểm cộng).

**4. Checklist hoàn thành**
- [ ] Trừ kho trong transaction + lockForUpdate.
- [ ] Thiếu hàng → 422 + rollback.

**5. Checklist self-test**
- [ ] Kê quantity > stock → 422, stock không đổi, đơn không tạo.
- [ ] Thuốc `is_active=false` → không cho kê.

---

## T3.7 — Hoàn kho khi xóa hoặc sửa số lượng item

**1. Nội dung task gốc**
> `removeItem`: hoàn `stock = stock + quantity`. `updateItem`: tính delta quantity và cộng/trừ kho tương ứng; nếu tăng mà thiếu hàng → 422.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `removeItem`: transaction → `stock += quantity` → xóa item.
  2. `updateItem`: `delta = new_qty - old_qty`; delta>0 cần kiểm stock đủ (thiếu → 422); áp `stock -= delta` (âm delta = hoàn kho).
- **Cách tiếp cận:** luôn `lockForUpdate` medicine.

> Prompt vibe code: *"removeItem hoàn kho (stock+=qty); updateItem tính delta, tăng thiếu hàng→422, cập nhật stock; đều trong transaction + lockForUpdate."*

**3. Checklist hoàn thành**
- [ ] removeItem hoàn kho đúng.
- [ ] updateItem xử lý delta + chặn thiếu hàng.

**4. Checklist self-test**
- [ ] Xóa item → stock tăng lại đúng.
- [ ] Tăng quantity vượt stock → 422, không đổi kho.

---

## T3.8 — Migration Invoices

**1. Nội dung task gốc**
> `invoices`: `examination_id` UNIQUE, `invoice_code` unique, `subtotal`, `discount`, `total`, `status` unpaid|paid|cancelled, `issued_at`. Index theo status.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Migration `invoices`: `examination_id` UNIQUE FK, `invoice_code` UNIQUE, `subtotal`/`discount`(default 0)/`total` decimal(12,2), `status` CHECK default `unpaid`, `issued_at`. Index `status`.
- **Cách tiếp cận:** FK `restrict` (bảng tài chính).

> Prompt vibe code: *"Migration invoices (examination_id unique, invoice_code unique, subtotal/discount/total decimal, status CHECK unpaid|paid|cancelled default unpaid, issued_at, index status)."*

**3. Checklist hoàn thành**
- [ ] UNIQUE examination_id + invoice_code + CHECK status + index status.

**4. Checklist self-test**
- [ ] Tạo 2 invoice cho cùng examination → lỗi UNIQUE.

---

## T3.9 — Tạo hóa đơn từ phiếu khám (tính tiền tự động)

**1. Nội dung task gốc**
> CASHIER tạo invoice: `medicine_total = SUM(qty*price) + consultation_fee (EXAMINATION_FEE)`. `total = subtotal - discount`. `status=unpaid`. Trùng examination → 422.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `InvoiceController@store` `{examination_id, consultation_fee?, discount?}`.
  2. `medicine_total = Σ(prescription_items.quantity × medicines.price)`; `subtotal = medicine_total + EXAMINATION_FEE`; `total = subtotal - discount`.
  3. Sinh `invoice_code` unique; `status=unpaid`.
  4. Trùng examination → 422.
- **Cách tiếp cận:** README chọn 1 cách giá (hiện tại vs snapshot) và nhất quán — khuyến nghị lấy `price` hiện tại lúc lập HĐ.

> Prompt vibe code: *"InvoiceController store từ examination: subtotal = SUM(qty*price) + EXAMINATION_FEE, total = subtotal - discount, status unpaid, invoice_code unique; trùng examination → 422; INVOICES.CREATE."*

**3. Mở rộng**
- Cho phép tạo hóa đơn khi chỉ có phí khám (chưa có đơn thuốc) — ghi rõ công thức.

**4. Checklist hoàn thành**
- [ ] Tính subtotal/total đúng công thức.
- [ ] Trùng examination → 422.

**5. Checklist self-test**
- [ ] So khớp total = Σ(qty*price) + fee - discount.
- [ ] RECEPTIONIST/DOCTOR tạo invoice → 403.

---

## T3.10 — Sửa discount / hủy hóa đơn an toàn

**1. Nội dung task gốc**
> Chỉ cho UPDATE discount hoặc cancelled khi `status=unpaid` và chưa có payment completed. Đã thanh toán một phần/đủ → 422.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `update` (discount) & `updateStatus` (cancelled) chỉ khi `unpaid` và không có payment `completed`.
  2. Ngược lại → 422.
  3. Sửa discount → tính lại `total`.
- **Cách tiếp cận:** guard trong InvoiceService.

> Prompt vibe code: *"Chỉ cho sửa discount/hủy invoice khi unpaid & chưa có payment completed, ngược lại → 422; sửa discount tính lại total."*

**3. Checklist hoàn thành**
- [ ] Guard trạng thái + payment completed.
- [ ] Sửa discount cập nhật total.

**4. Checklist self-test**
- [ ] Có payment completed → sửa/hủy → 422.

---

## T3.11 — Migration Payments cho PayPal/Visa

**1. Nội dung task gốc**
> `payments`: `invoice_id`, `amount>0`, `method` paypal|visa, `status` pending|completed|failed|cancelled, `provider`, `provider_order_id`, `provider_capture_id`, `paid_at`, `note`. Index `invoice_id`, `provider_order_id`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Migration `payments` đủ cột như trên; CHECK `method`, `status`, `amount>0`; `provider` default `paypal`.
  2. Index `invoice_id`, `provider_order_id`.
- **Cách tiếp cận:** `provider_order_id`/`provider_capture_id` lưu ID từ PayPal.

> Prompt vibe code: *"Migration payments (invoice_id, amount CHECK>0, method CHECK paypal|visa, status CHECK default pending, provider default paypal, provider_order_id, provider_capture_id, paid_at, note; index invoice_id, provider_order_id)."*

**3. Checklist hoàn thành**
- [ ] Đủ cột + CHECK + index.

**4. Checklist self-test**
- [ ] amount ≤ 0 → lỗi CHECK.

---

## T3.12 — Đảm bảo đủ cột PayPal trên payments

**1. Nội dung task gốc**
> (Gộp với T3.11 nếu đã làm) Kiểm tra migration có đủ `provider_order_id` và `provider_capture_id` để lưu Order ID / Capture ID từ PayPal Sandbox. Không lưu số thẻ Visa trong DB.

**2. Diễn giải chi tiết**
- **Các bước:** rà lại migration T3.11 đủ 2 cột; xác nhận **không** có cột lưu số thẻ.
- **Cách tiếp cận:** nếu đã đủ ở T3.11 thì task này chỉ là bước xác nhận.

**3. Checklist hoàn thành**
- [ ] Có `provider_order_id`, `provider_capture_id`.
- [ ] Không có cột lưu số thẻ Visa.

**4. Checklist self-test**
- [ ] `\d payments` xác nhận cột; không có `card_number`.

---

## T3.13 — Tạo lệnh thanh toán PayPal Order (pending)

**1. Nội dung task gốc**
> `POST /api/invoices/{id}/payments` với `amount` + `method` paypal|visa. Gọi PayPal Sandbox tạo Order; lưu payment `status=pending`; trả `approval_url`/`order_id`. `amount` không vượt số còn lại → 422. Permission `PAYMENTS.CREATE`.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `PaymentService`: lấy OAuth2 token PayPal (client credentials), gọi **Create Order** với `amount` quy đổi `PAYPAL_CURRENCY`.
  2. Lưu `payments` `status=pending`, `provider_order_id`, `method`.
  3. Trả `approval_url`/`order_id`.
  4. `amount` > số còn lại (`total - Σ completed`) → 422.
- **Cách tiếp cận:** đọc credential từ `config/paypal.php`; log ẩn secret.

> Prompt vibe code: *"PaymentController store: gọi PayPal Sandbox Create Order (OAuth2 client credentials), lưu payment pending + provider_order_id, trả approval_url/order_id; amount>số còn lại→422; PAYMENTS.CREATE."*

**3. Mở rộng**
- Webhook PayPal cập nhật status (khuyến khích).

**4. Checklist hoàn thành**
- [ ] Tạo order Sandbox → payment pending.
- [ ] amount vượt số còn lại → 422.

**5. Checklist self-test**
- [ ] Tạo order → nhận `order_id`/`approval_url`.
- [ ] amount > còn lại → 422.

Chi tiết PayPal: [skills/backend.md](../skills/backend.md).

---

## T3.14 — Capture thanh toán PayPal và cập nhật hóa đơn

**1. Nội dung task gốc**
> `POST /api/payments/{id}/capture` (`PAYMENTS.CAPTURE`). Capture trên Sandbox; success → `status=completed` + lưu capture id. Transaction: nếu tổng completed = `invoice.total` → `invoices.status=paid`. Fail → failed, giữ unpaid.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `capture`: gọi PayPal **Capture Order** theo `provider_order_id`.
  2. Success → `status=completed`, `provider_capture_id`, `paid_at`. Trong transaction: cộng dồn completed; đạt `total` → invoice `paid`.
  3. Fail → `failed`, invoice giữ `unpaid`.
- **Cách tiếp cận:** transaction bao cập nhật payment + invoice.

> Prompt vibe code: *"PaymentController capture: gọi PayPal Capture, success→completed+capture_id+paid_at, trong transaction cộng dồn completed đạt total→invoice paid; fail→failed giữ unpaid; PAYMENTS.CAPTURE."*

**3. Checklist hoàn thành**
- [ ] Capture success → completed + invoice paid khi đủ tiền.
- [ ] Fail → failed, invoice unpaid.

**4. Checklist self-test**
- [ ] Thanh toán đủ → invoice `paid`.
- [ ] Thanh toán một phần → invoice vẫn `unpaid`.

---

## T3.15 — Hỗ trợ thanh toán thẻ Visa qua PayPal

**1. Nội dung task gốc**
> `method=visa` dùng luồng PayPal hỗ trợ thẻ (card fields/checkout). README hướng dẫn tạo app PayPal Developer và số thẻ Visa test sandbox. Không dùng tiền thật.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `method=visa` dùng cùng Order nhưng nguồn tiền là thẻ (card fields/hosted).
  2. README ghi hướng dẫn tạo app + thẻ Visa test (đã có [README mục 13](../README.md#13-tích-hợp-paypal-sandbox--visa)).
- **Cách tiếp cận:** backend xử lý order/capture như paypal; khác biệt ở bước duyệt phía client.

> Prompt vibe code: *"Hỗ trợ method=visa qua PayPal card fields; README hướng dẫn app PayPal Developer + thẻ Visa test sandbox."*

**3. Checklist hoàn thành**
- [ ] method=visa tạo order + capture được (Sandbox).
- [ ] README có hướng dẫn thẻ test.

**4. Checklist self-test**
- [ ] Thanh toán bằng thẻ Visa test → completed.

---

## T3.16 — Bảo mật credential PayPal

**1. Nội dung task gốc**
> Chỉ lưu `CLIENT_ID/SECRET` trong `.env`. `.env.example` dùng placeholder. Không commit secret. README cảnh báo chỉ sandbox.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Secret chỉ ở `.env`; `.env.example` placeholder.
  2. `config/paypal.php` đọc env; không hard-code secret.
  3. README cảnh báo sandbox-only; không log secret.
- **Cách tiếp cận:** rà soát `git grep` không lộ secret.

> Prompt vibe code: *"Đảm bảo PayPal secret chỉ trong .env, config/paypal.php đọc env, không commit/log secret; README cảnh báo sandbox."*

**3. Checklist hoàn thành**
- [ ] Secret không nằm trong repo.
- [ ] README cảnh báo sandbox.

**4. Checklist self-test**
- [ ] `git grep -i client_secret` không ra giá trị thật.

---

## T3.17 — Seeder dữ liệu demo đầy đủ luồng khám

**1. Nội dung task gốc**
> Seed specialties, doctors (kèm user), patients, medicines, và (tuỳ chọn) một chuỗi appointment→examination mẫu để demo nhanh.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Seed 2–3 specialty, vài doctor (kèm user role DOCTOR), vài patient, vài medicine.
  2. (Tuỳ chọn) chuỗi mẫu appointment→examination→prescription→invoice→payment để demo.
- **Cách tiếp cận:** idempotent; đặt sau seed RBAC/ADMIN.

> Prompt vibe code: *"Seeder demo: specialties, doctors+user, patients, medicines, và chuỗi mẫu appointment→examination→prescription→invoice→payment."*

**3. Checklist hoàn thành**
- [ ] Seed đủ danh mục + demo luồng.

**4. Checklist self-test**
- [ ] Sau `migrate --seed`, gọi `/api/stats` có số liệu.

---

# Tuần 4 — Audit log, Stats, Test, Đóng gói

**Mục tiêu tuần:** activity log (Event/Observer); Stats aggregate; feature tests (mock PayPal); chuẩn response/HTTP; Postman; README hướng dẫn PayPal; demo.

---

## T4.1 — Activity logs bằng Event/Observer

**1. Nội dung task gốc**
> Bảng `activity_logs` (`user_id`, `action`, `subject_type/id`, `meta` JSONB). Ghi log tối thiểu: user, appointment status, examination, prescription/kho, invoice, payment. Implement bằng Event+Listener hoặc Observer.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Migration `activity_logs` (`user_id` nullable, `subject_type`, `subject_id`, `action`, `meta` JSONB, `created_at`). Index `(subject_type, subject_id)`.
  2. Observer/Event cho các model: log created/status_changed/stock_adjusted...
  3. `meta` lưu before/after hoặc số lượng thay đổi.
- **Cách tiếp cận:** Observer cho CRUD chuẩn; Event thủ công cho action đặc thù (capture, adjustStock).

> Prompt vibe code: *"activity_logs (user_id nullable, subject_type/id, action, meta JSONB, index). Observer/Event ghi log cho user, appointment status, examination, prescription/kho, invoice, payment."*

**3. Mở rộng**
- Queue job xử lý ghi log bất đồng bộ (điểm cộng).

**4. Checklist hoàn thành**
- [ ] Bảng + JSONB + index.
- [ ] Log các action chính.

**5. Checklist self-test**
- [ ] Đổi status appointment → có dòng log `status_changed` với meta before/after.
- [ ] Capture payment → có log.

---

## T4.2 — API Stats tổng quan phòng khám

**1. Nội dung task gốc**
> `GET /api/stats` dùng SQL aggregate: số bệnh nhân, lịch hôm nay, doanh thu tháng, thuốc sắp hết. Permission `STATS.SHOW`. Không đếm bằng PHP collection.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. `StatsController@show` (`STATS.SHOW`).
  2. Dùng query aggregate: `COUNT` patients, `COUNT` appointments hôm nay, `SUM` doanh thu tháng (invoices paid), `COUNT` medicines `stock <= threshold`.
  3. **Không** load hết rồi đếm bằng PHP.
- **Cách tiếp cận:** mỗi chỉ số là 1 query aggregate; gộp hợp lý.

> Prompt vibe code: *"StatsController show: aggregate SQL số bệnh nhân, lịch hôm nay, doanh thu tháng (invoices paid), thuốc sắp hết; STATS.SHOW; không đếm bằng PHP collection."*

**3. Mở rộng**
- Dashboard nhiều chỉ số + group by (điểm cộng).

**4. Checklist hoàn thành**
- [ ] 4 chỉ số bằng aggregate.
- [ ] Chỉ ADMIN (`STATS.SHOW`).

**5. Checklist self-test**
- [ ] Số liệu khớp dữ liệu seed.
- [ ] Không có vòng lặp PHP để đếm.

---

## T4.3 — Feature tests RBAC và luồng nghiệp vụ chính

**1. Nội dung task gốc**
> Cover: role thiếu quyền → 403; RECEPTIONIST không tạo invoice; DOCTOR không capture payment; luồng tạo patient→appointment→examination cơ bản.

**2. Diễn giải chi tiết**
- **Các bước:**
  1. Test RBAC: thiếu quyền → 403; RECEPTIONIST `POST /invoices` → 403; DOCTOR `capture` → 403.
  2. Test luồng chính patient→appointment→examination→prescription (trừ kho)→invoice→payment.
  3. **Mock PayPal** (không gọi Sandbox thật trong test).
- **Cách tiếp cận:** fake HTTP client PayPal; assert trạng thái DB.

> Prompt vibe code: *"Feature tests: RBAC 403 (RECEPTIONIST invoice, DOCTOR capture); luồng patient→appointment→examination→prescription (kiểm trừ kho)→invoice→payment với PayPal mock."*

**3. Checklist hoàn thành**
- [ ] Test RBAC + luồng chính pass.
- [ ] PayPal được mock.

**4. Checklist self-test**
- [ ] `php artisan test` toàn bộ xanh.
- [ ] Test không gọi network thật.

---

## Task đóng gói bổ sung (theo lịch 4 tuần mục 9 của đề)

File `task.xlsx` chỉ liệt kê T4.1–T4.3, nhưng lịch 4 tuần và ví dụ branch (`T4.7-readme`) của đề yêu cầu thêm các đầu việc đóng gói dưới đây. Đặt ID nối tiếp (T4.4+) khi tạo branch.

### T4.4 — Chuẩn hóa response & HTTP status toàn hệ thống
- **Việc:** rà toàn bộ endpoint đúng envelope mục 10 + ma trận HTTP status (201 tạo mới, 401/403/404/422 đúng case).
- **Checklist:** [ ] mọi endpoint đúng status; [ ] 422 có errors theo field; [ ] Exception Handler JSON.
- **Self-test:** quét từng nhóm endpoint bằng Postman, đối chiếu ma trận.

### T4.5 — Postman collection
- **Việc:** tạo `postman_collection.json` gồm luồng chính đầy đủ + case 401/403/404/422 + capture PayPal.
- **Checklist:** [ ] có collection; [ ] biến môi trường base_url/token; [ ] case lỗi.
- **Self-test:** import Postman chạy từ login → payment không sửa tay.

### T4.6 — Hướng dẫn PayPal Developer trong README
- **Việc:** viết mục tạo app PayPal Sandbox, biến `.env`, thẻ Visa test (đã có [README mục 13](../README.md#13-tích-hợp-paypal-sandbox--visa) — bổ sung screenshot/chi tiết khi làm thật).
- **Checklist:** [ ] đủ bước tạo app + credential + thẻ test.
- **Self-test:** người mới theo README tạo được order sandbox.

### T4.7 — Hoàn thiện README + demo
- **Việc:** rà README đủ mục kiến trúc/RBAC/index/transaction/N+1; chuẩn bị kịch bản demo đầu–cuối.
- **Checklist:** [ ] README đủ mục; [ ] `docker compose up` + `migrate --seed` + `test` chạy sạch từ máy trắng.
- **Self-test:** clone repo mới, chạy đúng 5 lệnh mentor chấm ([README mục 4](../README.md#4-cài-đặt--chạy-bằng-docker)) không lỗi.

### T4.8 (tuỳ chọn) — Frontend Blade demo
- **Việc:** trang Blade tối giản đăng nhập + xem danh sách (bệnh nhân/lịch/hóa đơn) tiêu thụ API bằng Bearer token.
- **Checklist:** [ ] login lưu token; [ ] gọi `/api/*` hiển thị dữ liệu.
- **Self-test:** đăng nhập trên UI, thao tác 1 luồng đọc. Chi tiết: [skills/frontend.md](../skills/frontend.md).

---

## Bảng tổng hợp task ↔ tiêu chí chấm

| Nhóm task | % chấm (README mục 17) |
|---|---|
| T1.1–T1.4, docker (T4.7) | 10 (Docker Compose) |
| T1.5, T1.9 (auth) | 10 (Auth) |
| T1.5, T1.7, T1.8, T1.10, T1.13 | 20 (RBAC) |
| T2.1–T2.6 | 15 (Patients + Appointments) |
| T2.7–T2.10, T3.3–T3.7 | 20 (Examinations + Prescriptions) |
| T3.8–T3.15 | 10 (Invoices + Payments) |
| T1.14–T1.15, T3.1–T3.2 | 5 (Medicines + Specialties + Doctors) |
| T2.x/T3.x migration + README | 5 (Postgres) |
| T4.1 | 3 (Activity log) |
| T2.11, T4.3 | 2 (Feature tests) |
