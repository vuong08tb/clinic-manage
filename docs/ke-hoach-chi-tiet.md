# Kế hoạch chi tiết — Clinic Management REST API

Tài liệu này break-down toàn bộ đầu task từ `task.xlsx` theo 4 tuần, đối chiếu với đề bài `de-bai-thuc-tap-clinic-api.xlsx`. Mỗi task trình bày theo cấu trúc:

1. **Nội dung task gốc** — nguyên văn từ file task.
2. **Diễn giải chi tiết** — gồm ba phần:
   - *Vấn đề (nghiệp vụ & mục tiêu)* — mô tả đúng phạm vi theo nội dung gốc: cần giải quyết gì, vì sao.
   - *Các bước thực hiện* — danh sách công việc cụ thể, đủ chi tiết để tìm hiểu và làm theo.
   - *Cách tiếp cận (từng bước)* — trình tự thao tác/kỹ thuật khuyến nghị để thực hiện các bước trên.
   - *Prompt gợi ý (vibe code)* — câu lệnh có thể copy cho công cụ sinh code.
3. **Vấn đề cần lưu ý hoặc xác nhận** — các chi tiết phát sinh sau khi break task nhưng **chưa được đề cập/chưa rõ** trong tài liệu mô tả, cần bạn quyết hoặc hỏi mentor. **Mục này chỉ xuất hiện khi thực sự có điểm cần làm rõ** — task đã rõ ràng sẽ bỏ qua mục 3 (nhảy thẳng từ 2 sang 4).
4. **Checklist hoàn thành** — điều kiện coi là "xong".
5. **Checklist self-test** — cách tự kiểm chứng trước khi tạo PR.

**Quy ước xuyên suốt:** kiến trúc **B (Controller + Service)**; RBAC `CONTROLLER.ACTION`; envelope `success/message/data/errors`; branch `task/vuongth/<ID>-<slug>`. Tham chiếu playbook: [database.md](../skills/database.md), [backend.md](../skills/backend.md), [frontend.md](../skills/frontend.md), [docker.md](../skills/docker.md).

> **Lưu ý về đánh số:** file task gốc nhảy từ **T1.5 sang T1.7** (không có T1.6 tách riêng — phần schema RBAC được gộp trong T1.5). Tài liệu này giữ nguyên ID gốc để khớp khi đặt tên branch.

---

## Mục lục

- [Tuần 1 — Nền tảng: Docker, Auth, RBAC, danh mục](#tuần-1--nền-tảng-docker-auth-rbac-danh-mục) — T1.1 → T1.15
- [Tuần 2 — Bệnh nhân, Lịch khám, Phiếu khám](#tuần-2--bệnh-nhân-lịch-khám-phiếu-khám) — T2.1 → T2.11
- [Tuần 3 — Thuốc, Đơn thuốc, Hóa đơn, Thanh toán](#tuần-3--thuốc-đơn-thuốc-hóa-đơn-thanh-toán) — T3.1 → T3.17
- [Tuần 4 — Audit log, Stats, Test, Đóng gói](#tuần-4--audit-log-stats-test-đóng-gói) — T4.1 → T4.3 + task đóng gói

---

# Tuần 1 — Nền tảng: Docker, Auth, RBAC, danh mục

**Mục tiêu tuần:** Docker + Laravel + Postgres chạy được; Sanctum login/logout/me; schema RBAC (roles, permissions, role_permissions, users.role_id) + catalog ~52 permission; Middleware `EnsurePermission`; CRUD specialties/doctors/users; chốt kiến trúc B.

---

## T1.1 — Setup Docker môi trường Ubuntu 24 + Laravel

**1. Nội dung task gốc**
> Cài Docker Engine và Docker Compose trên Ubuntu 24. Tạo project Laravel mới trong workspace. Đảm bảo chạy được container PHP/Laravel cơ bản trước khi nối database. Ghi lại phiên bản Docker/Compose vào README.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Cả dự án phải chạy trong Docker để mentor chấm trên môi trường **giống hệt** máy dev (đề chỉ định Ubuntu 24). Task này chỉ lo bước nền: có Docker + Compose trên máy, có một container PHP chạy được Laravel ở mức "hello world", **chưa** cần Postgres (việc nối DB để T1.4). Đây là điểm khởi động, làm sai ở đây sẽ kéo lỗi sang mọi task sau.

*Các bước thực hiện:*
1. Cài **Docker Engine** + **Docker Compose plugin** trên máy (Ubuntu 24: theo hướng dẫn chính thức của Docker; Windows/macOS: Docker Desktop).
2. Xác nhận project Laravel 13 skeleton đã có trong repo (thư mục `app/`, `artisan`, `composer.json`).
3. Dựng một container PHP 8.3 CLI, cài Composer, chạy `composer install` bên trong container.
4. Chạy `php artisan serve --host=0.0.0.0 --port=8000` trong container, mở `http://localhost:8000` thấy trang Laravel.
5. Lấy phiên bản: `docker --version`, `docker compose version`, `php -v`, `php artisan --version` — ghi vào README mục Stack.

*Cách tiếp cận (từng bước):*
1. Cài Docker xong, chạy `docker run hello-world` để chắc chắn Docker hoạt động.
2. Tạm thời dùng lệnh một dòng để test PHP trước khi có Dockerfile hoàn chỉnh: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php -v`.
3. Vì Dockerfile/compose hoàn chỉnh là T1.2, ở task này chỉ cần chứng minh "app container sống" — có thể dùng image `php:8.3-cli` tạm.
4. Ghi lại chính xác chuỗi version (copy nguyên output) vào README để mentor đối chiếu.

*Prompt gợi ý (vibe code):* "Xác nhận Laravel 13 skeleton chạy được trong container PHP 8.3-cli với `php artisan serve` cổng 8000; thu thập version Docker/Compose/PHP/Laravel và điền vào mục Stack của README."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- Đề yêu cầu OS Ubuntu 24 nhưng bạn đang dev trên Windows 11. Docker giúp đồng nhất runtime, nhưng cần xác nhận với mentor: chấm trên Ubuntu thật hay chỉ cần "chạy được qua Docker trên OS bất kỳ" (điều này ảnh hưởng cách viết mục OS trong README).

**4. Checklist hoàn thành**
- [ ] `docker --version` và `docker compose version` hoạt động, đã ghi vào README.
- [ ] Truy cập `http://localhost:8000` thấy Laravel welcome (hoặc JSON health).
- [ ] `composer install` chạy trong container không lỗi.

**5. Checklist self-test**
- [ ] `docker run hello-world` OK.
- [ ] `docker compose exec app php -v` → PHP 8.3 (sau khi có compose ở T1.2).
- [ ] `php artisan --version` → Laravel 13.

---

## T1.2 — Viết Dockerfile và docker-compose.yml

**1. Nội dung task gốc**
> Tạo Dockerfile cho app Laravel và docker-compose.yml gồm service app + postgres:16. Persist DB bằng Docker volume. Map port API (gợi ý 8000). Mentor phải chạy được: `docker compose up -d --build`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Đóng gói ứng dụng thành hai service liên kết (`app` Laravel + `db` Postgres 16) chạy chỉ bằng **một lệnh** `docker compose up -d --build`. Dữ liệu Postgres phải **không mất** khi container tắt (persist qua volume). Đây là hạ tầng nền cho toàn bộ dự án và chiếm 10% điểm chấm.

*Các bước thực hiện:*
1. **Dockerfile** (`php:8.3-cli`): cài gói hệ thống `git`, `unzip`, `libpq-dev`, `libzip-dev`; cài PHP extension `pdo_pgsql`, `zip`; copy binary `composer` từ image `composer:2`; `composer install`; `EXPOSE 8000`; `CMD php artisan serve --host=0.0.0.0 --port=8000`.
2. **docker-compose.yml**:
   - Service `app`: build từ Dockerfile, map `8000:8000`, mount `.:/var/www`, `depends_on: db (condition: service_healthy)`.
   - Service `db`: image `postgres:16`, biến `POSTGRES_DB/USER/PASSWORD`, volume `clinic_postgres_data:/var/lib/postgresql/data`, `healthcheck` bằng `pg_isready`.
   - Map port host cho Postgres: `5433:5432` (tránh đụng Postgres cài sẵn trên máy).
3. Khai báo named volume `clinic_postgres_data` ở cuối file.

*Cách tiếp cận (từng bước):*
1. Viết Dockerfile trước, `docker build .` để bảo đảm image build không lỗi (đặc biệt bước cài extension `pdo_pgsql` cần `libpq-dev`).
2. Trong Dockerfile, copy `composer.json`/`composer.lock` và chạy `composer install` **trước** khi copy toàn bộ source → tận dụng cache layer, build lại nhanh khi chỉ đổi code.
3. Viết compose, `docker compose config` để validate cú pháp YAML.
4. `docker compose up -d --build`, rồi `docker compose ps` xem `db` đã `healthy` chưa; `depends_on: service_healthy` đảm bảo `app` khởi động sau khi DB sẵn sàng.
5. Kiểm chứng persist: `docker compose down` (không `-v`) rồi `up -d`, dữ liệu vẫn còn.

*Prompt gợi ý (vibe code):* "Viết Dockerfile PHP 8.3-cli (cài pdo_pgsql, zip, composer) và docker-compose.yml gồm app (port 8000, mount source, depends_on db healthy) + postgres:16 (named volume, healthcheck pg_isready, map 5433:5432)."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- `app` chạy `php artisan serve` (server dev PHP). Đề không yêu cầu php-fpm + nginx nên đây là lựa chọn hợp lệ cho thực tập; chỉ cần lưu ý nếu mentor kỳ vọng cấu hình gần production hơn.

**4. Checklist hoàn thành**
- [ ] `docker compose up -d --build` dựng cả `app` + `db`.
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

*Vấn đề (nghiệp vụ & mục tiêu):*
Cấu hình phải **tự tài liệu hoá** (người mới clone về đọc `.env.example` là hiểu cần biến gì) và **an toàn** (không lộ secret PayPal). Đây cũng là nơi khai báo hằng số nghiệp vụ `EXAMINATION_FEE` (phí khám) mà hóa đơn sẽ dùng ở tuần 3.

*Các bước thực hiện:*
1. Bổ sung vào `.env.example` khối DB: `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (khớp với biến trong compose ở T1.2).
2. Thêm biến nghiệp vụ `EXAMINATION_FEE` (vd `100000`).
3. Thêm khối PayPal: `PAYPAL_MODE=sandbox`, `PAYPAL_CLIENT_ID=your-sandbox-client-id`, `PAYPAL_CLIENT_SECRET=your-sandbox-client-secret`, `PAYPAL_CURRENCY=USD` (toàn bộ là **placeholder**).
4. Đảm bảo `.gitignore` chứa `.env`.
5. README giải thích từng biến (đã có [README mục 5](../README.md#5-biến-môi-trường-env)).

*Cách tiếp cận (từng bước):*
1. Sao chép các biến DB từ compose sang `.env.example` để hai bên khớp nhau tuyệt đối (sai lệch tên DB/user là lỗi kết nối phổ biến nhất).
2. Tạo `config/paypal.php` đọc các biến qua `env()` **chỉ trong config** (chuẩn Laravel: không rải `env()` khắp code, vì khi cache config `env()` ngoài config sẽ trả null).
3. `git status` xác nhận `.env` không nằm trong danh sách theo dõi; nếu lỡ commit, `git rm --cached .env`.

*Prompt gợi ý (vibe code):* "Bổ sung .env.example khối DB pgsql (host db), EXAMINATION_FEE, và khối PayPal sandbox (client id/secret placeholder, currency USD). Tạo config/paypal.php đọc từ env. Đảm bảo .gitignore bỏ qua .env."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Đơn vị tiền tệ:** `EXAMINATION_FEE=100000` và `medicines.price` ngầm hiểu theo VND, nhưng `PAYPAL_CURRENCY=USD`. Đề **không** nêu cách quy đổi. Cần chốt: (a) quy đổi VND→USD theo tỷ giá cố định khi tạo PayPal order, hay (b) demo dùng luôn USD cho cả phí khám/giá thuốc cho nhất quán. Quyết định này ảnh hưởng T3.9, T3.13 — nên xác nhận sớm.

**4. Checklist hoàn thành**
- [ ] `.env.example` đủ biến DB + EXAMINATION_FEE + PayPal (placeholder).
- [ ] `.env` KHÔNG bị commit.
- [ ] README giải thích từng biến.

**5. Checklist self-test**
- [ ] `git ls-files | grep -x .env` rỗng.
- [ ] `cp .env.example .env && php artisan key:generate` chạy được.
- [ ] `config('paypal.mode')` trả `sandbox`.

---

## T1.4 — Kết nối Laravel với PostgreSQL trong Docker

**1. Nội dung task gốc**
> Cấu hình database pgsql trỏ vào service db. Chạy migrate thành công trong container. Xử lý chờ Postgres ready (healthcheck/depends_on) nếu cần.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
App phải kết nối được Postgres **qua tên service `db`** (mạng nội bộ Docker), không phải `localhost`. Migrate mặc định của Laravel (users, cache, jobs) phải chạy sạch trong container. Đây là bước "bắt tay" giữa hai service dựng ở T1.2.

*Các bước thực hiện:*
1. Trong `.env`: `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, đúng `DB_DATABASE/USERNAME/PASSWORD`.
2. Chạy `docker compose exec app php artisan migrate` → tạo được các bảng mặc định.
3. Nếu app cố migrate khi Postgres chưa sẵn sàng → dựa vào `healthcheck` + `depends_on: service_healthy` (đã cấu hình ở T1.2).

*Cách tiếp cận (từng bước):*
1. Kiểm tra kết nối trước khi migrate: `docker compose exec app php artisan tinker` → `DB::connection()->getPdo()` (không lỗi = kết nối OK).
2. Nếu lỗi `could not translate host name "db"` → bạn đang chạy artisan **ngoài** container; luôn dùng `docker compose exec app ...`.
3. Nếu lỗi `connection refused` lúc `up` đầu tiên → DB chưa healthy; đợi `docker compose ps` báo `healthy` rồi migrate lại, hoặc dùng `php artisan migrate --graceful`.
4. Xác nhận bằng `docker compose exec db psql -U clinic -d clinic -c '\dt'`.

*Prompt gợi ý (vibe code):* "Cấu hình kết nối pgsql tới service db; chạy `artisan migrate` trong container thành công; xử lý chờ Postgres healthy trước khi migrate."

**4. Checklist hoàn thành**
- [ ] `artisan migrate` chạy trong container không lỗi.
- [ ] Kết nối dùng `DB_HOST=db` (không phải localhost).

**5. Checklist self-test**
- [ ] `artisan migrate:status` liệt kê migration đã chạy.
- [ ] `psql ... '\dt'` thấy các bảng mặc định.

---

## T1.5 — Auth Sanctum (login, logout, me) + schema RBAC catalog

**1. Nội dung task gốc**
> Tạo migration cho roles, permissions, role_permissions. `permissions.name` unique dạng `CONTROLLER.ACTION`. `role_permissions UNIQUE(role_id, permission_id)`. Không dùng Spatie.
> *(Tiêu đề task trong file: "Auth Sanctum — login, logout, me". Nội dung cell mô tả phần schema RBAC. Do file task không tách T1.6, task này gộp cả hai phần: xác thực Sanctum + schema RBAC nền tảng.)*

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Hai mục tiêu độc lập nhưng làm chung một tuần:
- **(a) Xác thực:** nhân sự đăng nhập bằng tài khoản (không có đăng ký công khai), nhận **Bearer token** (Sanctum) để gọi các API sau. Có 3 endpoint: `login`, `logout`, `me`. Đăng nhập sai hoặc tài khoản bị khóa (`is_active=false`) → 401.
- **(b) Nền tảng RBAC:** dựng 3 bảng catalog `roles`, `permissions`, `role_permissions`. `permissions.name` theo quy ước `CONTROLLER.ACTION` và UNIQUE. **Tuyệt đối không** dùng Spatie (đề bắt tự thiết kế).

*Các bước thực hiện:*
1. **Schema RBAC (làm trước vì T1.7–T1.10 phụ thuộc):**
   - Migration `roles`: `id`, `name` UNIQUE, `display_name`, timestamps.
   - Migration `permissions`: `id`, `name` UNIQUE (dạng `CONTROLLER.ACTION`), `display_name`, timestamps.
   - Migration `role_permissions`: `role_id` FK, `permission_id` FK, `UNIQUE(role_id, permission_id)`.
2. **Sanctum:**
   - Cài `laravel/sanctum`, publish config, chạy migration `personal_access_tokens`.
   - Model `User` thêm trait `HasApiTokens`.
   - `AuthController@login`: validate email/password (Form Request); nếu user không tồn tại, sai password, hoặc `is_active=false` → trả 401 envelope; đúng → `createToken()` trả `{ token, user }`.
   - `AuthController@logout`: `$request->user()->currentAccessToken()->delete()`.
   - `AuthController@me`: trả user + role + danh sách permission.
   - Route: `login` public; `logout`, `me` trong group `auth:sanctum`. **Không** tạo `/api/register`.

*Cách tiếp cận (từng bước):*
1. Viết 3 migration RBAC, `migrate`, kiểm tra bằng `\d roles` / `\d permissions` trong psql.
2. Cài Sanctum: `composer require laravel/sanctum`, `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`, `migrate`.
3. Viết `AuthController` + `LoginRequest`; đặt logic kiểm tra credential trong `AuthService` (giữ controller mỏng theo kiến trúc B).
4. Test bằng Postman: login lấy token → gắn `Authorization: Bearer <token>` → gọi `me`.
5. Chưa gán được role cho user tới khi có `users.role_id` (T1.7); tạm thời có thể để `me` trả role null và hoàn thiện sau T1.7.

*Prompt gợi ý (vibe code):* "Tạo 3 migration roles/permissions/role_permissions với UNIQUE theo đề (không Spatie). Cài Sanctum; viết AuthController login/logout/me theo kiến trúc Controller+Service, envelope chuẩn; sai pass hoặc is_active=false → 401; không tạo route register."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Bảng `personal_access_tokens` của Sanctum** không nằm trong danh sách 14 bảng schema ở mục 5 của đề. Đây là bảng hạ tầng do Sanctum sinh ra — cần xác nhận với mentor rằng nó nằm ngoài đếm "14 bảng" và được chấp nhận.
- **Vòng đời token:** đề không quy định thời hạn hết hạn token hay giới hạn số token/user. Mặc định Sanctum token không hết hạn — xác nhận có cần đặt `expiration` không.
- **Khuyết T1.6:** file task nhảy T1.5 → T1.7. Nếu mentor có ý định tách "Sanctum" (T1.5) và "schema RBAC" (T1.6) thành 2 PR riêng, cần hỏi để đặt tên branch cho đúng.

**4. Checklist hoàn thành**
- [ ] 3 migration RBAC đủ UNIQUE, không Spatie.
- [ ] Sanctum cài đặt, `personal_access_tokens` migrate.
- [ ] `login`/`logout`/`me` hoạt động, envelope chuẩn, 401 đúng case.

**5. Checklist self-test**
- [ ] Login đúng → 200 + token; sai pass → 401; account khóa → 401.
- [ ] Gọi `me` không token → 401; có token → 200.
- [ ] `logout` xong dùng lại token cũ → 401.

Chi tiết: [skills/backend.md](../skills/backend.md), [skills/database.md](../skills/database.md).

---

## T1.7 — Gắn role cho user + seed 5 role

**1. Nội dung task gốc**
> Thêm `users.role_id` FK → roles.id. Seed roles: ADMIN, RECEPTIONIST, DOCTOR, PHARMACIST, CASHIER (kèm display_name). Mỗi user chỉ có đúng 1 role.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Mô hình RBAC của đề là "single role per user": mỗi user gắn **đúng 1** role qua cột `users.role_id` (không phải bảng pivot user_roles). Cần tạo cột FK đó và seed đủ 5 role chuẩn kèm tên hiển thị.

*Các bước thực hiện:*
1. Migration thêm cột `role_id` vào `users` (FK → `roles.id`, NOT NULL).
2. Seeder `RoleSeeder` tạo 5 role: `ADMIN`, `RECEPTIONIST`, `DOCTOR`, `PHARMACIST`, `CASHIER`, mỗi role có `display_name` tiếng Việt (vd "Quản trị viên", "Lễ tân", "Bác sĩ", "Dược sĩ", "Thu ngân").
3. Model: `User belongsTo Role`; `Role hasMany User`.

*Cách tiếp cận (từng bước):*
1. Vì `role_id` NOT NULL, **thứ tự seeder bắt buộc**: `RoleSeeder` chạy trước mọi seeder tạo user.
2. Dùng `firstOrCreate(['name' => ...])` để seeder idempotent (chạy lại không trùng).
3. Sau khi có cột role_id, hoàn thiện `AuthController@me` để trả `role` (đã bắt đầu ở T1.5).
4. Kiểm tra quan hệ trong tinker: `User::first()->role->name`.

*Prompt gợi ý (vibe code):* "Thêm users.role_id (FK roles, NOT NULL). Seeder tạo 5 role ADMIN/RECEPTIONIST/DOCTOR/PHARMACIST/CASHIER kèm display_name tiếng Việt, idempotent. Quan hệ User belongsTo Role, Role hasMany User."

**4. Checklist hoàn thành**
- [ ] `users.role_id` FK NOT NULL.
- [ ] 5 role được seed với display_name.
- [ ] `User->role` trả về đúng role.

**5. Checklist self-test**
- [ ] `SELECT name FROM roles` → đúng 5 dòng.
- [ ] Tạo user thiếu `role_id` → lỗi NOT NULL (đúng thiết kế).
- [ ] `me` trả kèm role sau khi user có role_id.

---

## T1.8 — Seed permissions và role_permissions theo map đề bài

**1. Nội dung task gốc**
> Seed đầy đủ permission `CONTROLLER.ACTION` (`PATIENTS.CREATE`, `PAYMENTS.CAPTURE`…). Map đúng quyền cho từng role theo bảng mục 3.4 trong đề.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Khởi tạo toàn bộ danh mục ~52 permission và **gán chính xác** cho từng role theo ma trận đề bài (mục 3.4, tái hiện tại [README mục 7](../README.md#7-rbac-global)). Sai ma trận này = sai RBAC = mất phần lớn 20% điểm RBAC.

*Các bước thực hiện:*
1. Định nghĩa danh sách ~52 permission `CONTROLLER.ACTION` (mọi action của mọi controller nghiệp vụ) làm **một nguồn sự thật** (mảng PHP).
2. Tạo record `permissions` cho từng permission (idempotent).
3. Gán `role_permissions` theo ma trận; **ADMIN nhận toàn bộ** permission.
4. Đặt phần tạo `permissions` trong **data migration idempotent** (yêu cầu đề: "thêm permission = migration"); phần gán `role_permissions` có thể ở seeder.

*Cách tiếp cận (từng bước):*
1. Copy ma trận từ README thành mảng PHP: `['RECEPTIONIST' => ['PATIENTS.FINDALL', ...], ...]` — để README và code không lệch nhau.
2. Sinh danh sách permission tự động từ danh sách controller@action (giảm gõ tay, tránh sót).
3. Dùng `updateOrInsert(['name' => $name], [...])` cho `permissions` để chạy lại không trùng.
4. Với ADMIN: gán tất cả permission bằng cách lấy `Permission::pluck('id')`.
5. Kiểm chứng bằng SQL đếm và spot-check vài role.

*Prompt gợi ý (vibe code):* "Data migration idempotent tạo ~52 permission CONTROLLER.ACTION. Seeder gán role_permissions theo ma trận README mục 7 (ADMIN full), dùng updateOrInsert, có nguồn sự thật là mảng PHP."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Ranh giới migration vs seeder:** đề nói "thêm permission mới = data migration idempotent, không chỉ sửa Seeder", nhưng cũng có `RolePermissionSeeder`. Cần chốt quy ước: danh mục `permissions` khai báo bằng **migration** (để môi trường đã seed vẫn nhận permission mới khi `migrate`), còn ánh xạ `role_permissions` mặc định ban đầu đặt ở **seeder**. Xác nhận cách chia này với mentor để nhất quán về sau.
- **Con số "~52":** đề ghi xấp xỉ. Cần đếm lại chính xác từ ma trận thực tế (README) và ghi con số cuối vào README để mentor đối chiếu.

**4. Checklist hoàn thành**
- [ ] Đủ danh mục permission `CONTROLLER.ACTION` theo ma trận.
- [ ] `role_permissions` khớp ma trận; ADMIN full.
- [ ] Idempotent (chạy lại không tạo bản trùng).

**5. Checklist self-test**
- [ ] `SELECT count(*) FROM permissions` khớp con số ghi trong README.
- [ ] ADMIN có mọi permission; CASHIER có `INVOICES.*`/`PAYMENTS.*` nhưng KHÔNG có `EXAMINATIONS.CREATE`.
- [ ] Chạy seeder/migration 2 lần → không tạo trùng.

---

## T1.9 — Seed tài khoản ADMIN đầu tiên

**1. Nội dung task gốc**
> Seeder tạo user `admin@clinic.test` (hoặc email ghi trong README) với role ADMIN và password hash. Sau `migrate --seed` có thể login ngay.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Vì không có đăng ký công khai, hệ thống phải có sẵn **một ADMIN** để đăng nhập lần đầu và tạo các nhân sự khác. ADMIN này được seed tự động khi cài đặt.

*Các bước thực hiện:*
1. `AdminSeeder` tạo user role ADMIN, email cố định (vd `admin@clinic.test`), password đã `Hash::make(...)`, `is_active=true`.
2. Ghi email + mật khẩu mặc định vào README (đã có [README mục 6](../README.md#6-tài-khoản-được-seed-sẵn)).
3. (Khuyến nghị) seed thêm 1 user mẫu mỗi role để test RBAC nhanh.

*Cách tiếp cận (từng bước):*
1. Đăng ký thứ tự trong `DatabaseSeeder`: Role → Permission/role_permissions → Admin → (demo).
2. Dùng `firstOrCreate(['email' => 'admin@clinic.test'], [...])` để idempotent.
3. Sau `migrate --seed`, test login ADMIN bằng Postman ngay.

*Prompt gợi ý (vibe code):* "Seeder tạo ADMIN admin@clinic.test role ADMIN, password hash, is_active=true, idempotent; đảm bảo login được ngay sau migrate --seed. Seed thêm 1 user mẫu mỗi role."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Mật khẩu mặc định của ADMIN** chưa được đề quy định. Cần tự chọn một giá trị (vd `Password@123`) và ghi rõ trong README; xác nhận mentor chấp nhận mật khẩu demo dạng này (không phải secret thật).

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

*Vấn đề (nghiệp vụ & mục tiêu):*
RBAC phải được enforce **tập trung** tại một middleware, không rải `if ($user->role == ...)` trong từng controller. Middleware tự suy ra permission cần thiết từ **Controller@action** đang chạy, rồi kiểm tra role của user có permission đó không; thiếu → 403. Đây là trái tim của phần RBAC (20% điểm).

*Các bước thực hiện:*
1. Tạo middleware `EnsurePermission`.
2. Lấy action đang chạy: `$request->route()->getActionName()` → `App\Http\Controllers\PatientController@index`.
3. Tách `Controller` name → **resource name** (`PatientController` → `PATIENTS`) và `method` → **action suffix** (`index` → `FINDALL`) theo bảng quy ước.
4. Ghép `PATIENTS.FINDALL`, kiểm tra `role->permissions` của user có chứa không; thiếu → `abort(403)`.
5. Thêm helper `$user->can('PATIENTS.CREATE')` (qua Gate hoặc method trên `User`) cho các chỗ cần check thủ công.
6. Gắn middleware vào group route nghiệp vụ (sau `auth:sanctum`).

*Cách tiếp cận (từng bước):*
1. Định nghĩa bảng map action→suffix là hằng số: `index→FINDALL, store→CREATE, show→FINDONE, update→UPDATE, destroy→DELETE, updateStatus→UPDATESTATUS, addItem→ADDITEM, updateItem→UPDATEITEM, removeItem→REMOVEITEM, adjustStock→ADJUSTSTOCK, capture→CAPTURE`.
2. Với Controller→resource: **map tường minh** trong một mảng config (vd `PatientController → PATIENTS`) thay vì tự động số nhiều hoá — vì số nhiều tiếng Anh có ngoại lệ và dễ sai.
3. Load permission của user hiệu quả: eager load `role.permissions`, có thể cache theo request để tránh query lặp.
4. Test theo ma trận: đăng nhập từng role, gọi endpoint bị cấm → phải nhận 403.

*Prompt gợi ý (vibe code):* "Viết middleware EnsurePermission: từ route action lấy Controller@method, map thành PERMISSION.NAME (map controller→resource tường minh trong config, action→suffix theo bảng), check role user, thiếu → 403. Thêm helper user->can(permission). Gắn vào group route /api nghiệp vụ."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Thuật toán Controller→resource name** không được đề quy định (đề chỉ liệt kê tên permission thành phẩm). Ví dụ `SpecialtyController → SPECIALTIES`, `MedicineController → MEDICINES` là số nhiều bất quy tắc. Đề xuất **map tường minh** — cần xác nhận cách này thay vì cố suy luận tự động.
- **Route ngoài RBAC user:** `PaymentController@webhook` (verify chữ ký PayPal) và có thể `capture` gọi từ hệ thống — đề ghi webhook "không dùng user token". Cần xác nhận webhook được đặt **ngoài** group `auth:sanctum`+`EnsurePermission`.

**4. Checklist hoàn thành**
- [ ] Middleware resolve đúng `Controller@action → PERMISSION`.
- [ ] Thiếu quyền → 403 envelope chuẩn.
- [ ] Không hard-code role trong controller.

**5. Checklist self-test**
- [ ] RECEPTIONIST gọi `POST /api/invoices` → 403.
- [ ] DOCTOR gọi `POST /api/payments/{id}/capture` → 403.
- [ ] ADMIN gọi endpoint bất kỳ → 200/201.

Chi tiết: [skills/backend.md](../skills/backend.md).

---

## T1.11 — Chọn kiến trúc B hoặc C và ghi README

**1. Nội dung task gốc**
> Chọn Controller+Service (B) hoặc +Repository (C). Code nhất quán theo lựa chọn. README có mục "Kiến trúc đã chọn" + lý do + sơ đồ luồng request.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Đề bắt buộc chọn một kiến trúc và **code nhất quán** theo nó, cấm Fat Controller. Quyết định phải được ghi lại (lý do + sơ đồ) để mentor đánh giá tính nhất quán.

*Các bước thực hiện:*
1. **Chốt: phương án B (Controller + Service).** Lý do và sơ đồ luồng request đã ghi tại [README mục 3](../README.md#3-kiến-trúc-đã-chọn-b--controller--service).
2. Chuẩn hóa cấu trúc thư mục: `app/Http/Controllers`, `app/Services`, `app/Http/Requests`, `app/Http/Resources`.
3. Áp dụng đúng mô hình ngay từ resource đầu tiên (Specialties/Doctors ở T1.14–T1.15).

*Cách tiếp cận (từng bước):*
1. Tạo một base pattern mẫu (1 controller + 1 service + 1 request + 1 resource) để các resource sau copy theo.
2. Quy tắc tự kiểm: nếu một controller method có nhiều hơn ~5 dòng logic (không tính điều phối) → đẩy xuống Service.
3. Review README chắc chắn có đủ: mục kiến trúc + lý do chọn B + sơ đồ luồng.

*Prompt gợi ý (vibe code):* "Thiết lập khung kiến trúc B: mẫu Controller mỏng → Service (business + transaction) → Model, kèm Form Request + API Resource; tạo 1 bộ mẫu để tái dùng cho các resource."

**4. Checklist hoàn thành**
- [ ] README có mục kiến trúc + lý do + sơ đồ.
- [ ] Resource đầu tiên tuân theo B.

**5. Checklist self-test**
- [ ] Không có business logic trong controller (chỉ điều phối).
- [ ] Service chứa transaction/business rule.

---

## T1.12 — Form Request, API Resource, envelope JSON chuẩn

**1. Nội dung task gốc**
> Mọi API ghi dùng Form Request. Response dùng API Resource. Envelope: `success/message/data` (và `errors` với 422; `meta` khi paginate). Exception Handler trả JSON cho API, không trả HTML.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Chuẩn hoá **input** (validation qua Form Request) và **output** (định dạng qua API Resource) cho toàn hệ thống, thống nhất một envelope JSON. Đặc biệt: khi lỗi, API phải trả **JSON** đúng status, tuyệt đối không trả trang HTML lỗi mặc định của Laravel.

*Các bước thực hiện:*
1. Tạo trait/helper `ApiResponse` với `ok($data, $message, $code)` và `fail($message, $errors, $code)`.
2. Tạo Form Request cho mọi endpoint ghi (rules + custom messages).
3. Tạo API Resource cho mọi output; ResourceCollection cho list kèm `meta` phân trang.
4. Cấu hình Exception Handler (Laravel 13: `bootstrap/app.php` → `withExceptions`) để với request `/api/*`:
   - `ValidationException` → 422 `{success:false, message, errors}` (errors theo field).
   - `AuthenticationException` → 401.
   - lỗi phân quyền → 403.
   - `ModelNotFoundException` / `NotFoundHttpException` → 404.

*Cách tiếp cận (từng bước):*
1. Làm helper + Exception Handler **một lần** ngay đầu, các resource sau tái dùng.
2. Với 422: tận dụng `ValidationException` của Laravel (Form Request tự ném) và format lại trong handler cho khớp envelope.
3. Với list: trả `Resource::collection($paginator)` — Laravel tự sinh `meta`/`links`; điều chỉnh về đúng khoá `meta` mà đề mong đợi (`current_page, per_page, total, last_page`).
4. Test từng loại lỗi bằng Postman để chắc chắn không rơi ra HTML.

*Prompt gợi ý (vibe code):* "Tạo trait ApiResponse (envelope success/fail); cấu hình Exception Handler trả JSON cho /api (422 kèm errors theo field, 401/403/404); mẫu Form Request + API Resource + ResourceCollection có meta phân trang để tái dùng."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Ngôn ngữ message:** đề nói chọn tiếng Anh **hoặc** tiếng Việt cho `message` và dùng **nhất quán**. Cần chốt một ngôn ngữ cho toàn bộ message API và ghi vào README.

**4. Checklist hoàn thành**
- [ ] Envelope chuẩn cho success/fail.
- [ ] 422 có `errors` theo field; list có `meta`.
- [ ] Exception Handler trả JSON cho `/api`, không HTML.

**5. Checklist self-test**
- [ ] Body thiếu field bắt buộc → 422 + `errors`.
- [ ] Resource không tồn tại → 404 JSON.
- [ ] List phân trang → có `meta.current_page/total`.

Chi tiết: [skills/backend.md](../skills/backend.md).

---

## T1.13 — CRUD Users (chỉ ADMIN) + bảo vệ ADMIN cuối cùng

**1. Nội dung task gốc**
> API tạo/sửa/list/khóa user, gán `role_id`. Chỉ ADMIN (`USERS.*`). Không cho đổi role hoặc deactivate ADMIN cuối cùng còn lại trong hệ thống → 422.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
ADMIN quản trị nhân sự: tạo user và gán role, sửa thông tin/đổi role, khóa/mở user. Toàn bộ chỉ ADMIN (`USERS.*`). Kèm một quy tắc an toàn quan trọng: **không được để hệ thống mất ADMIN** — không cho đổi role khỏi ADMIN hay khóa ADMIN nếu đó là ADMIN active cuối cùng → 422.

*Các bước thực hiện:*
1. `UserController`: `index`, `store`, `show`, `update`, `destroy`, `updateStatus` với permission `USERS.*`.
2. `store`: Form Request validate `name`, `email` (unique), `password`, `role_id` (tồn tại trong roles).
3. `destroy`: theo đề = **vô hiệu hóa** (`is_active=false`), không xóa cứng.
4. `updateStatus`: bật/khóa qua `{is_active}`.
5. **Guard ADMIN cuối:** trong Service, trước khi (a) đổi `role_id` của một ADMIN sang role khác, hoặc (b) `is_active=false` một ADMIN → đếm số ADMIN đang `is_active`; nếu ≤ 1 (chính user này là cuối) → ném 422.

*Cách tiếp cận (từng bước):*
1. Viết `UserService::assertNotLastActiveAdmin(User $user)` gọi ở cả `update` (khi đổi role) và `updateStatus`/`destroy` (khi khóa).
2. Logic đếm: `User::whereHas('role', fn($q)=>$q->where('name','ADMIN'))->where('is_active',true)->count()`.
3. Resource user **không** trả `password`.
4. Test: tạo hệ thống chỉ còn 1 ADMIN active → thử khóa/đổi role → 422.

*Prompt gợi ý (vibe code):* "CRUD Users chỉ ADMIN (USERS.*): tạo/sửa/list/khóa, gán role_id; destroy = is_active false. Guard trong UserService chặn đổi role/khóa ADMIN active cuối cùng → 422. Resource không lộ password."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Định nghĩa "ADMIN cuối cùng":** hiểu là ADMIN đang `is_active=true` cuối cùng (soft-deactivate vẫn tính là đã mất). Cần xác nhận cách đếm này.
- **Tự khóa chính mình:** đề không nói rõ ADMIN có được tự khóa/đổi role của chính mình không (nếu còn ADMIN khác). Đề xuất cho phép miễn không vi phạm quy tắc "ADMIN cuối" — xác nhận.

**4. Checklist hoàn thành**
- [ ] CRUD Users hoạt động, chỉ ADMIN.
- [ ] Guard ADMIN cuối → 422 (cả đổi role lẫn khóa).
- [ ] `destroy` là deactivate, không xóa cứng.

**5. Checklist self-test**
- [ ] Chỉ còn 1 ADMIN active → khóa/đổi role → 422.
- [ ] RECEPTIONIST gọi `USERS.*` → 403.
- [ ] Tạo user trùng email → 422.

---

## T1.14 — CRUD Specialties (chuyên khoa)

**1. Nội dung task gốc**
> Migration `specialties` (name unique, description). CRUD API với permission `SPECIALTIES.*`. Dùng cho gắn bác sĩ theo chuyên khoa.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Danh mục chuyên khoa (Nội, Ngoại, Nhi…) là dữ liệu nền để gắn bác sĩ (T1.15). Cần CRUD đầy đủ với phân quyền `SPECIALTIES.*` (theo ma trận: ADMIN ghi; RECEPTIONIST/DOCTOR chỉ đọc).

*Các bước thực hiện:*
1. Migration `specialties`: `name` UNIQUE, `description` nullable, timestamps.
2. `SpecialtyController` CRUD với `SPECIALTIES.*`.
3. Form Request (name required, unique) + API Resource.

*Cách tiếp cận (từng bước):*
1. Đây là **resource CRUD chuẩn đầu tiên** — làm kỹ để dùng làm khuôn mẫu (controller mỏng, service, request, resource) cho các resource sau.
2. `store`/`update` validate name unique (bỏ qua chính bản ghi khi update).
3. Test phân quyền: RECEPTIONIST đọc được, tạo → 403.

*Prompt gợi ý (vibe code):* "Migration specialties (name unique, description). CRUD SpecialtyController theo SPECIALTIES.* (ADMIN ghi, các role khác đọc theo ma trận), Form Request + Resource theo kiến trúc B. Dùng làm mẫu resource chuẩn."

**4. Checklist hoàn thành**
- [ ] Migration `specialties` name unique.
- [ ] CRUD + `SPECIALTIES.*` đúng ma trận.

**5. Checklist self-test**
- [ ] Tạo trùng name → 422.
- [ ] RECEPTIONIST: `FINDALL/FINDONE` OK, `CREATE` → 403.

---

## T1.15 — CRUD Doctors (hồ sơ bác sĩ 1-1 với user)

**1. Nội dung task gốc**
> Bảng `doctors`: `user_id` unique (user phải role DOCTOR), `specialty_id`, `license_number`, `bio`. CRUD theo `DOCTORS.*`. Không tạo doctor cho user sai role.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Bác sĩ = một user (role DOCTOR) + một hồ sơ `doctors` quan hệ **1-1**. Hồ sơ gắn chuyên khoa và số chứng chỉ hành nghề. Ràng buộc nghiệp vụ: **không** tạo hồ sơ doctor cho user không phải role DOCTOR, và mỗi user chỉ có tối đa 1 hồ sơ (UNIQUE `user_id`).

*Các bước thực hiện:*
1. Migration `doctors`: `user_id` FK UNIQUE, `specialty_id` FK, `license_number` (khuyến nghị UNIQUE), `bio` nullable, timestamps.
2. `DoctorController` CRUD với `DOCTORS.*`.
3. **Validate business trong Service:** `user_id` phải trỏ tới user có role DOCTOR và chưa có hồ sơ doctor; sai → 422.
4. `index` cho filter theo `specialty_id`.

*Cách tiếp cận (từng bước):*
1. UNIQUE `user_id` ở DB là hàng rào cuối; kiểm tra role DOCTOR ở Service là hàng rào nghiệp vụ (trả 422 message rõ ràng thay vì để DB ném lỗi thô).
2. Eager load `doctor.user`, `doctor.specialty` để Resource trả thông tin đầy đủ, tránh N+1.
3. Test: tạo doctor cho user role RECEPTIONIST → 422; tạo hồ sơ thứ 2 cho cùng user → 422.

*Prompt gợi ý (vibe code):* "Migration doctors (user_id unique FK, specialty_id FK, license_number, bio). CRUD DoctorController DOCTORS.*; Service chặn tạo doctor nếu user không phải role DOCTOR hoặc đã có hồ sơ → 422. index filter theo specialty_id, eager load user+specialty."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **`license_number` UNIQUE:** đề ghi "khuyến nghị UNIQUE" (không bắt buộc). Cần chốt có đặt UNIQUE hay không — ảnh hưởng migration và thông báo lỗi khi trùng.

**4. Checklist hoàn thành**
- [ ] `doctors.user_id` UNIQUE FK.
- [ ] Chặn user sai role / đã có hồ sơ → 422.
- [ ] Filter theo `specialty_id`.

**5. Checklist self-test**
- [ ] Tạo doctor cho user role RECEPTIONIST → 422.
- [ ] Tạo doctor thứ 2 cho cùng user → 422.
- [ ] `?specialty_id=` lọc đúng.

---

# Tuần 2 — Bệnh nhân, Lịch khám, Phiếu khám

**Mục tiêu tuần:** patients CRUD + soft delete + search; appointments CRUD + máy trạng thái + chống trùng lịch + index; examinations tạo từ appointment (transaction cập nhật status); feature test tuần 2.

---

## T2.1 — Migration và model Patients

**1. Nội dung task gốc**
> Tạo bảng `patients`: `code` unique, `full_name`, `gender`, `date_of_birth`, `phone`, `email/address` nullable, timestamps. Khuyến khích soft delete. Index phục vụ tìm kiếm theo tên/SĐT/mã.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Hồ sơ bệnh nhân là gốc của mọi lịch khám/phiếu khám. Cần bảng đủ trường, mã bệnh nhân duy nhất, hỗ trợ **tìm kiếm nhanh** theo tên/SĐT/mã (lễ tân tra cứu liên tục), và **không xóa cứng** (bệnh nhân đã từng phát sinh dữ liệu y tế) → soft delete.

*Các bước thực hiện:*
1. Migration `patients`: `code` UNIQUE, `full_name`, `gender` (CHECK male/female/other), `date_of_birth` (date), `phone` (index), `email` nullable, `address` nullable, `deleted_at` (soft delete), timestamps.
2. Model `Patient` dùng trait `SoftDeletes`.
3. Index phục vụ search: tối thiểu `phone`; cân nhắc cho `full_name`, `code`.

*Cách tiếp cận (từng bước):*
1. Đặt CHECK cho `gender` bằng raw SQL sau `Schema::create` (xem [database.md](../skills/database.md#3-mẫu-migration-có-constraint)).
2. Sinh `code` trong Service khi tạo (T2.2), không để client truyền — bàn ở phần "cần xác nhận".
3. `migrate`, kiểm tra CHECK bằng cách insert `gender` sai giá trị (phải lỗi).

*Prompt gợi ý (vibe code):* "Migration patients (code unique, full_name, gender CHECK male/female/other, date_of_birth, phone index, email/address nullable, soft delete). Model dùng SoftDeletes. Index phục vụ search theo phone/full_name/code."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Định dạng và cách sinh `code`:** đề nêu ví dụ `BN-000123` nhưng không quy định thuật toán sinh (sequence tăng dần? theo năm? padding mấy số?). Cần chốt định dạng và cơ chế sinh đảm bảo UNIQUE (khuyến nghị Postgres `SEQUENCE` hoặc `BN-` + id zero-pad) — xác nhận với mentor.

**4. Checklist hoàn thành**
- [ ] Bảng `patients` đủ cột + `code` UNIQUE + soft delete.
- [ ] CHECK `gender`.
- [ ] Index phục vụ search.

**5. Checklist self-test**
- [ ] Insert `gender` sai → lỗi CHECK.
- [ ] Soft delete rồi query mặc định không thấy.

---

## T2.2 — CRUD Patients + search/filter

**1. Nội dung task gốc**
> API list/create/update/show/delete bệnh nhân. Filter/search theo `q` (tên, SĐT, code). Phân quyền: RECEPTIONIST/DOCTOR/CASHIER theo map (CASHIER chủ yếu đọc).

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
CRUD bệnh nhân + tìm kiếm một-tham-số `q` gộp nhiều tiêu chí (tên/SĐT/mã). Phân quyền theo ma trận: RECEPTIONIST tạo/sửa; CASHIER/DOCTOR chủ yếu đọc; chỉ ADMIN xóa.

*Các bước thực hiện:*
1. `PatientController` CRUD + `PATIENTS.*`; `destroy` = soft delete.
2. `index`: nhận `q` → tìm `full_name ILIKE %q%` OR `phone` OR `code`; phân trang + `meta`.
3. Sinh `code` tự động khi `store` (theo định dạng chốt ở T2.1).
4. Áp phân quyền đúng ma trận.

*Cách tiếp cận (từng bước):*
1. Viết query scope `scopeSearch($q, $term)` trong Model để `index` gọn (xem [backend.md](../skills/backend.md#6-tránh-n1-eager-loading)).
2. Dùng `ILIKE` (Postgres) cho tìm không phân biệt hoa/thường.
3. Sinh `code` trong `PatientService::create` (transaction nếu dùng sequence riêng).
4. Test search với từng loại giá trị (một phần tên, số điện thoại, mã đầy đủ).

*Prompt gợi ý (vibe code):* "CRUD Patients + PATIENTS.* (RECEPTIONIST ghi, CASHIER/DOCTOR đọc, ADMIN xóa). index search theo q (full_name ILIKE, phone, code) + phân trang meta. store tự sinh code. destroy soft delete. Dùng query scope."

**4. Checklist hoàn thành**
- [ ] CRUD + search `q` hoạt động.
- [ ] Phân quyền đúng ma trận.
- [ ] `store` tự sinh `code`; `destroy` soft delete.

**5. Checklist self-test**
- [ ] `?q=` khớp theo tên/SĐT/mã.
- [ ] CASHIER `POST /api/patients` → 403.
- [ ] List có `meta` phân trang.

---

## T2.3 — Migration Appointments + index

**1. Nội dung task gốc**
> Bảng `appointments`: `patient_id`, `doctor_id`, `scheduled_at`, `status`, `reason`. Index: `(doctor_id, scheduled_at)`, `patient_id`, `status`. CHECK/ENUM status.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Bảng lịch khám với 4 trạng thái vòng đời và các index phục vụ hai nhu cầu: (a) lọc lịch theo bác sĩ/ngày/trạng thái, (b) chống trùng lịch bác sĩ (T2.6). Index `(doctor_id, scheduled_at)` phục vụ cả hai.

*Các bước thực hiện:*
1. Migration `appointments`: `patient_id` FK, `doctor_id` FK, `scheduled_at` (timestamp), `status` (CHECK: scheduled/confirmed/cancelled/completed, default `scheduled`), `reason` nullable, timestamps.
2. Index: `(doctor_id, scheduled_at)`, `(patient_id)`, `(status)`.

*Cách tiếp cận (từng bước):*
1. Đặt `status` default `scheduled` ở cả migration lẫn model.
2. CHECK status bằng raw SQL.
3. FK dùng `restrictOnDelete` cho `doctor_id`/`patient_id`? — cân nhắc: appointment không phải bản ghi tài chính, nhưng vẫn nên restrict để tránh mất lịch sử; xác nhận ở phần dưới.

*Prompt gợi ý (vibe code):* "Migration appointments (patient_id, doctor_id FK, scheduled_at, status CHECK 4 giá trị default scheduled, reason nullable). Index (doctor_id, scheduled_at), patient_id, status."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Thiếu khái niệm "độ dài buổi khám":** schema chỉ có `scheduled_at` (thời điểm), **không** có `duration`/`end_at`. Nhưng T2.6 (chống trùng lịch) cần biết mỗi lịch chiếm bao lâu để tính chồng khung giờ. Cần chốt **một trong hai**: (a) quy ước cứng mỗi lịch dài cố định (vd 30 phút) ghi trong README, hay (b) thêm cột `duration_minutes`/`end_at` vào bảng. Đây là điểm quan trọng, nên xác nhận trước khi làm T2.6.

**4. Checklist hoàn thành**
- [ ] Bảng + CHECK status + 3 index.

**5. Checklist self-test**
- [ ] `\d appointments` thấy đủ index.
- [ ] Insert status ngoài 4 giá trị → lỗi CHECK.

---

## T2.4 — CRUD Appointments — tạo lịch scheduled

**1. Nội dung task gốc**
> Lễ tân tạo lịch khám gắn patient + doctor + thời điểm. Status mặc định `scheduled`. Validate doctor/patient tồn tại. Permission `APPOINTMENTS.CREATE`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Lễ tân đặt lịch: chọn bệnh nhân + bác sĩ + thời điểm (+ lý do). Lịch mới luôn ở `scheduled`. Việc đổi trạng thái tách ra T2.5, chống trùng tách ra T2.6 — task này lo CRUD cơ bản + list có filter.

*Các bước thực hiện:*
1. `AppointmentController`: `index`, `store`, `show`, `update` với `APPOINTMENTS.*`.
2. `store`: Form Request validate `patient_id`/`doctor_id` tồn tại, `scheduled_at` (định dạng thời gian, có thể yêu cầu tương lai), `reason` nullable; set `status=scheduled`.
3. `update`: chỉ cho sửa giờ/lý do khi lịch còn `scheduled`.
4. `index`: filter `doctor_id`, `patient_id`, `status`, `date`.

*Cách tiếp cận (từng bước):*
1. Validate tồn tại bằng rule `exists:doctors,id` / `exists:patients,id`.
2. Eager load `patient`, `doctor.user` cho Resource.
3. Với filter `date`: so khớp theo ngày của `scheduled_at`.
4. Test tạo với `doctor_id` không tồn tại → 422.

*Prompt gợi ý (vibe code):* "CRUD Appointments + APPOINTMENTS.*; store gắn patient+doctor+scheduled_at, status mặc định scheduled, validate tồn tại (exists), update chỉ khi scheduled; index filter doctor_id/patient_id/status/date; eager load patient+doctor.user."

**4. Checklist hoàn thành**
- [ ] CRUD cơ bản + filter.
- [ ] Status mặc định scheduled.
- [ ] `update` chỉ khi scheduled.

**5. Checklist self-test**
- [ ] Tạo với doctor không tồn tại → 422.
- [ ] `?doctor_id=&status=` lọc đúng.

---

## T2.5 — Máy trạng thái lịch khám

**1. Nội dung task gốc**
> Cho phép chuyển: `scheduled→confirmed→completed`; `scheduled/confirmed→cancelled`. Chặn transition trái quy tắc → 422. API PATCH status riêng hoặc update có kiểm soát.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Trạng thái lịch phải đi theo vòng đời hợp lệ, không cho nhảy cóc hay lùi sai (vd `cancelled → completed`). Cần một endpoint đổi trạng thái có kiểm soát và một bảng transition rõ ràng.

*Các bước thực hiện:*
1. Endpoint `PATCH /api/appointments/{id}/status` → `updateStatus` (`APPOINTMENTS.UPDATESTATUS`), body `{status}`.
2. Định nghĩa bảng transition hợp lệ: `scheduled → {confirmed, cancelled}`, `confirmed → {completed, cancelled}`, `completed`/`cancelled` là trạng thái cuối.
3. Transition không hợp lệ → 422 với message rõ.

*Cách tiếp cận (từng bước):*
1. Đặt hằng `ALLOWED_TRANSITIONS` trong Service; hàm `assertCanTransition($from, $to)`.
2. Lưu ý: `completed` thường được đặt tự động khi tạo phiếu khám (T2.9). Cần quyết định `updateStatus` có cho set `completed` thủ công không (xem phần cần xác nhận).
3. Test đủ các cặp transition hợp lệ và không hợp lệ.

*Prompt gợi ý (vibe code):* "updateStatus cho appointment theo bảng transition (scheduled→confirmed/cancelled; confirmed→completed/cancelled); transition sai → 422 message rõ. Đặt ALLOWED_TRANSITIONS trong Service."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Ai được set `completed`:** đề cho `confirmed→completed`, nhưng T2.9 lại đặt `completed` **tự động** khi tạo phiếu khám. Cần chốt: `updateStatus` có cho phép set `completed` **thủ công** hay `completed` **chỉ** phát sinh qua tạo phiếu khám? (Nếu chỉ qua phiếu khám thì `updateStatus` chặn set completed.)

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

*Vấn đề (nghiệp vụ & mục tiêu):*
Một bác sĩ không thể có hai lịch khám **chồng khung giờ** cùng lúc. Khi tạo hoặc sửa lịch, phải kiểm tra xung đột với các lịch khác của cùng bác sĩ (bỏ qua lịch đã `cancelled`); có xung đột → 422. Quy tắc phải được ghi rõ trong README (vì phụ thuộc định nghĩa độ dài buổi khám).

*Các bước thực hiện:*
1. Chốt định nghĩa "khung giờ" của một lịch (phụ thuộc quyết định ở T2.3).
2. Khi `store`/`update`: query các appointment cùng `doctor_id`, `status != cancelled`, có khoảng thời gian **giao nhau** với lịch đang xử lý; nếu có → 422.
3. Khi `update`: loại chính bản ghi đang sửa khỏi phép kiểm tra.
4. Ghi rule vào README.

*Cách tiếp cận (từng bước):*
1. Tính khoảng `[start, end)` của lịch mới từ `scheduled_at` + độ dài buổi khám.
2. Điều kiện overlap: `existing.start < new.end AND existing.end > new.start`.
3. Tận dụng index `(doctor_id, scheduled_at)` để query nhanh.
4. Test: tạo 2 lịch cùng bác sĩ trùng giờ → lịch thứ 2 nhận 422; lịch đã cancelled không tính là trùng.

*Prompt gợi ý (vibe code):* "Chống trùng lịch: khi tạo/sửa appointment, chặn bác sĩ có lịch chồng khung giờ (status != cancelled) → 422; công thức overlap [start,end); loại chính bản ghi khi update; ghi rule độ dài buổi khám vào README."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Phụ thuộc trực tiếp vào T2.3:** cần chốt độ dài buổi khám (slot cố định vs cột `duration`/`end_at`) trước khi cài đặt. Đây là cùng một điểm xác nhận với T2.3 — giải quyết một lần cho cả hai.

**4. Checklist hoàn thành**
- [ ] Conflict check khi tạo/sửa → 422.
- [ ] Rule ghi trong README.

**5. Checklist self-test**
- [ ] 2 lịch cùng bác sĩ trùng giờ → lịch 2 nhận 422.
- [ ] Lịch `cancelled` không tính là trùng.

---

## T2.7 — Migration Examinations (phiếu khám)

**1. Nội dung task gốc**
> Bảng `examinations`: `appointment_id` UNIQUE, `doctor_id`, `patient_id`, `diagnosis`, `notes`, `examined_at`. Đảm bảo 1 lịch chỉ có tối đa 1 phiếu khám.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Phiếu khám ghi kết quả khám cho một lịch. Ràng buộc lõi: **một lịch ↔ tối đa một phiếu khám** (UNIQUE `appointment_id`). Phiếu lưu lại `doctor_id`/`patient_id` (dù suy được từ lịch) để dữ liệu y tế độc lập, đầy đủ.

*Các bước thực hiện:*
1. Migration `examinations`: `appointment_id` FK UNIQUE, `doctor_id` FK, `patient_id` FK, `diagnosis` (text), `notes` nullable, `examined_at` (timestamp), timestamps.
2. FK dùng `restrictOnDelete` (bảng lịch sử y tế).

*Cách tiếp cận (từng bước):*
1. UNIQUE `appointment_id` là hàng rào chống tạo trùng phiếu (kết hợp check nghiệp vụ ở T2.10).
2. `migrate`, test insert 2 phiếu cùng appointment → lỗi UNIQUE.

*Prompt gợi ý (vibe code):* "Migration examinations (appointment_id unique FK, doctor_id, patient_id FK, diagnosis text, notes nullable, examined_at). FK restrictOnDelete. Đảm bảo 1 appointment ↔ tối đa 1 examination."

**4. Checklist hoàn thành**
- [ ] `appointment_id` UNIQUE + đủ cột + FK.

**5. Checklist self-test**
- [ ] Tạo 2 examination cho cùng appointment → lỗi UNIQUE.

---

## T2.8 — Tạo phiếu khám từ lịch đã confirmed

**1. Nội dung task gốc**
> DOCTOR tạo examination từ appointment confirmed (mặc định đề). Lấy `patient_id`/`doctor_id` từ lịch, không cho lệch. Ghi `diagnosis`, `notes`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Bác sĩ tạo phiếu khám từ một lịch đã `confirmed`. Điểm mấu chốt về toàn vẹn dữ liệu: `patient_id`/`doctor_id` của phiếu **lấy từ lịch**, không nhận từ client — tránh phiếu khám ghi sai bệnh nhân/bác sĩ. Việc cập nhật lịch → `completed` (transaction) tách ra T2.9; chặn lịch không hợp lệ tách ra T2.10.

*Các bước thực hiện:*
1. `ExaminationController@store` (`EXAMINATIONS.CREATE`), body `{appointment_id, diagnosis, notes}`.
2. Load appointment theo `appointment_id`; lấy `patient_id`, `doctor_id` từ nó.
3. Chỉ cho tạo khi appointment `confirmed` (mặc định đề).
4. Set `examined_at` (thời điểm khám, mặc định now).

*Cách tiếp cận (từng bước):*
1. **Không** đưa `patient_id`/`doctor_id` vào Form Request — chỉ nhận `appointment_id`, `diagnosis`, `notes`.
2. Trong Service: nếu client cố truyền patient/doctor → bỏ qua, dùng của lịch.
3. Kiểm tra trạng thái lịch trước khi tạo (ràng buộc đầy đủ ở T2.10).
4. Test: RECEPTIONIST tạo phiếu → 403; truyền patient lệch → phiếu vẫn dùng patient của lịch.

*Prompt gợi ý (vibe code):* "ExaminationController store: tạo phiếu khám từ appointment confirmed, lấy patient_id/doctor_id từ lịch (không nhận từ client), body {appointment_id, diagnosis, notes}, set examined_at=now; EXAMINATIONS.CREATE."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Trạng thái nguồn hợp lệ:** đề mặc định "phải `confirmed` mới khám" nhưng **cho phép nới lỏng** sang `scheduled` nếu ghi rõ trong README. Cần chốt: chỉ `confirmed`, hay chấp nhận cả `scheduled`? Quyết định này phải nhất quán với T2.10 và ghi vào README.

**4. Checklist hoàn thành**
- [ ] Tạo phiếu từ appointment confirmed.
- [ ] `patient_id`/`doctor_id` lấy từ lịch.

**5. Checklist self-test**
- [ ] Truyền `patient_id` lệch → hệ thống dùng của lịch.
- [ ] RECEPTIONIST tạo examination → 403.

---

## T2.9 — Transaction tạo phiếu khám + hoàn tất lịch

**1. Nội dung task gốc**
> Trong `DB::transaction`: insert examination và cập nhật `appointment.status = completed`. Rollback nếu một bước lỗi.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Hai thao tác — tạo phiếu khám và chuyển lịch sang `completed` — phải **nguyên tử**: hoặc cả hai thành công, hoặc không có gì thay đổi. Nếu chỉ tạo phiếu mà quên cập nhật lịch (hoặc ngược lại) → dữ liệu mâu thuẫn.

*Các bước thực hiện:*
1. Bọc logic tạo examination + `appointment.status = completed` trong `DB::transaction`.
2. Nếu bất kỳ bước nào ném exception → rollback toàn bộ.

*Cách tiếp cận (từng bước):*
1. Đặt trong `ExaminationService::createFromAppointment()`.
2. Dùng closure `DB::transaction(function () { ... })` — Laravel tự rollback khi có exception.
3. Test rollback: tạm throw sau khi insert examination → xác nhận không có examination lẫn thay đổi status.

*Prompt gợi ý (vibe code):* "Bọc tạo examination + cập nhật appointment.status=completed trong DB::transaction (ExaminationService), rollback nếu lỗi."

**4. Checklist hoàn thành**
- [ ] Transaction bao 2 bước.
- [ ] Appointment → completed sau khi tạo phiếu.

**5. Checklist self-test**
- [ ] Ép lỗi giữa chừng → không còn examination lẫn thay đổi status.

---

## T2.10 — Chặn tạo phiếu khám từ lịch không hợp lệ

**1. Nội dung task gốc**
> Không tạo examination từ appointment cancelled hoặc đã completed / đã có phiếu. Trả 422 với message business rõ ràng.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Hàng rào nghiệp vụ cho việc tạo phiếu khám: không cho tạo từ lịch đã `cancelled`, đã `completed`, hoặc lịch đã có phiếu khám. Kết hợp với UNIQUE `appointment_id` (T2.7) tạo phòng thủ hai lớp (nghiệp vụ + DB).

*Các bước thực hiện:*
1. Trước khi tạo: kiểm tra `appointment.status` không phải `cancelled`/`completed`, và chưa tồn tại examination cho appointment đó.
2. Vi phạm → 422 với message rõ (vd "Lịch đã hủy không thể tạo phiếu khám").

*Cách tiếp cận (từng bước):*
1. Đặt các kiểm tra này ngay đầu `ExaminationService::createFromAppointment()`, trước transaction ở T2.9.
2. Message phân biệt từng nguyên nhân (cancelled / completed / đã có phiếu) để dễ debug và test.
3. Test cả ba case → đều 422.

*Prompt gợi ý (vibe code):* "Chặn tạo examination nếu appointment cancelled/completed hoặc đã có phiếu → 422 message phân biệt từng nguyên nhân; đặt trước transaction tạo phiếu."

**4. Checklist hoàn thành**
- [ ] 3 case bị chặn → 422.

**5. Checklist self-test**
- [ ] Tạo phiếu từ lịch cancelled → 422.
- [ ] Tạo phiếu lần 2 cho cùng lịch → 422.

---

## T2.11 — Feature test tuần 2 (auth, RBAC, patient, appointment)

**1. Nội dung task gốc**
> Viết feature test: login OK; role thiếu permission → 403; tạo patient; tạo appointment. Chạy được trong `docker compose exec app php artisan test`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Chốt chất lượng tuần 2 bằng feature test tự động, chạy được trong Docker. Cover các mảng đã làm: xác thực, RBAC (403), tạo bệnh nhân, tạo lịch.

*Các bước thực hiện:*
1. Test login: đúng → 200 + token; sai password → 401.
2. Test RBAC: role thiếu permission gọi endpoint bị cấm → 403.
3. Test tạo patient (RECEPTIONIST) → 201.
4. Test tạo appointment → 201.
5. Dùng `RefreshDatabase` + factory; seed RBAC trong `setUp`.

*Cách tiếp cận (từng bước):*
1. Cấu hình test DB (Postgres test hoặc sqlite in-memory — xem phần cần xác nhận).
2. Tạo factory cho User (kèm role), Patient, Doctor, Appointment.
3. Seed roles/permissions/role_permissions trong `setUp()` để middleware RBAC hoạt động trong test.
4. `docker compose exec app php artisan test` phải xanh.

*Prompt gợi ý (vibe code):* "Feature tests tuần 2: login OK/sai (200/401); RBAC 403; tạo patient; tạo appointment (201). Dùng RefreshDatabase + factory, seed RBAC trong setUp; chạy được trong container."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Database dùng cho test:** đề yêu cầu Postgres cho app, nhưng test thường chạy nhanh hơn trên sqlite in-memory. Tuy nhiên sqlite **không** hỗ trợ đầy đủ CHECK/JSONB/`lockForUpdate` như Postgres, có thể làm test lệch behavior thật. Cần chốt: chạy test trên **Postgres** (đúng với runtime, khuyến nghị) hay sqlite (nhanh nhưng rủi ro lệch). Xác nhận để cấu hình `phpunit.xml`.

**4. Checklist hoàn thành**
- [ ] Test login/RBAC/patient/appointment pass.
- [ ] Chạy trong container.

**5. Checklist self-test**
- [ ] `php artisan test` xanh.
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

*Vấn đề (nghiệp vụ & mục tiêu):*
Danh mục thuốc do dược sĩ quản lý, có tồn kho (`stock`) và cờ `is_active`. Ràng buộc nghiệp vụ liên quan tuần này: thuốc `is_active=false` **không được kê** vào đơn mới (enforce khi kê ở T3.6). Tồn kho không âm (CHECK).

*Các bước thực hiện:*
1. Migration `medicines`: `code` UNIQUE, `name`, `unit`, `price` decimal(12,2), `stock` int default 0 CHECK ≥ 0, `is_active` bool default true, `deleted_at` (soft delete), timestamps.
2. `MedicineController` CRUD + `MEDICINES.*`; `destroy` = soft delete.
3. `index` filter còn/hết hàng (`stock > 0` / `stock = 0`).

*Cách tiếp cận (từng bước):*
1. CHECK `stock >= 0` bằng raw SQL.
2. Phân quyền theo ma trận: PHARMACIST + ADMIN ghi; DOCTOR đọc.
3. Guard `is_active` khi kê đơn để ở T3.6 (không ở đây).

*Prompt gợi ý (vibe code):* "Migration medicines (code unique, name, unit, price decimal, stock int default 0 CHECK>=0, is_active bool, soft delete). CRUD MEDICINES.* (PHARMACIST/ADMIN ghi, DOCTOR đọc); index filter còn/hết hàng; destroy soft delete."

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

*Vấn đề (nghiệp vụ & mục tiêu):*
Dược sĩ cần nhập kho / điều chỉnh tồn không qua CRUD thường mà qua một endpoint chuyên biệt `adjustStock`, có ghi chú lý do và không để tồn âm. (Đây là thao tác khác với việc trừ kho tự động khi kê đơn.)

*Các bước thực hiện:*
1. `PATCH /api/medicines/{id}/stock` → `adjustStock` (`MEDICINES.ADJUSTSTOCK`), body `{quantity, note}`.
2. Cập nhật `stock` theo `quantity`; kết quả không được < 0 → 422.
3. Ghi `activity_logs` (`stock_adjusted`) nếu module log đã có (T4.1).

*Cách tiếp cận (từng bước):*
1. Thực hiện trong transaction + `lockForUpdate` trên dòng medicine (tránh race với trừ kho đồng thời).
2. Validate kết quả tồn trước khi ghi.
3. Nếu chưa làm T4.1, để chỗ ghi log dạng TODO và bổ sung sau.

*Prompt gợi ý (vibe code):* "adjustStock: PATCH /medicines/{id}/stock {quantity, note}; transaction + lockForUpdate; cập nhật stock, chặn kết quả âm → 422; ghi activity log stock_adjusted (nếu có)."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Ngữ nghĩa `quantity`:** đề không nói rõ `quantity` là **lượng thay đổi** (delta: dương = nhập, âm = xuất) hay **giá trị tồn mới tuyệt đối**. Hai cách xử lý và validate khác nhau. Đề xuất hiểu là **delta** (cộng dồn vào stock) — cần xác nhận.

**4. Checklist hoàn thành**
- [ ] adjustStock cập nhật đúng, chặn âm.
- [ ] Permission `MEDICINES.ADJUSTSTOCK`.

**5. Checklist self-test**
- [ ] Điều chỉnh khiến stock < 0 → 422.
- [ ] CASHIER gọi → 403.

---

## T3.3 — Migration Prescriptions và Prescription_items

**1. Nội dung task gốc**
> `prescriptions`: `examination_id` UNIQUE, `doctor_id`, `notes`. `prescription_items`: `medicine_id`, `quantity>0`, `dosage`, `usage_instruction`; `UNIQUE(prescription_id, medicine_id)`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Đơn thuốc quan hệ 1-1 với phiếu khám (`examination_id` UNIQUE) và có nhiều dòng thuốc. Mỗi dòng gắn một thuốc với số lượng > 0 và không được trùng thuốc trong cùng đơn (`UNIQUE(prescription_id, medicine_id)` — buộc dùng update để đổi số lượng thay vì thêm dòng trùng).

*Các bước thực hiện:*
1. Migration `prescriptions`: `examination_id` FK UNIQUE, `doctor_id` FK, `notes` nullable, timestamps.
2. Migration `prescription_items`: `prescription_id` FK (cascade on delete hợp lý), `medicine_id` FK (restrict), `quantity` int CHECK > 0, `dosage` string, `usage_instruction` nullable, `UNIQUE(prescription_id, medicine_id)`, timestamps.

*Cách tiếp cận (từng bước):*
1. CHECK `quantity > 0` bằng raw SQL.
2. Cân nhắc `prescription_items` cascade khi xóa prescription; nhưng lưu ý hoàn kho phải xử lý ở tầng nghiệp vụ (T3.7), không dựa vào cascade.
3. Test UNIQUE và CHECK bằng insert vi phạm.

*Prompt gợi ý (vibe code):* "Migration prescriptions (examination_id unique FK, doctor_id, notes) + prescription_items (prescription_id FK, medicine_id FK restrict, quantity CHECK>0, dosage, usage_instruction nullable, UNIQUE(prescription_id, medicine_id))."

**4. Checklist hoàn thành**
- [ ] UNIQUE `examination_id`, `UNIQUE(prescription_id, medicine_id)`, CHECK quantity>0.

**5. Checklist self-test**
- [ ] Thêm trùng medicine trong 1 đơn → lỗi UNIQUE.
- [ ] quantity 0/âm → lỗi CHECK.

---

## T3.4 — Tạo đơn thuốc từ phiếu khám

**1. Nội dung task gốc**
> DOCTOR tạo prescription gắn examination (1 phiếu ↔ 1 đơn). Có thể nhận kèm mảng `items` lúc tạo. Permission `PRESCRIPTIONS.CREATE`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Bác sĩ kê đơn từ một phiếu khám (quan hệ 1-1). Có thể tạo đơn kèm luôn danh sách thuốc (`items`) trong một request. Nếu có items → phải trừ kho (T3.6) trong cùng transaction.

*Các bước thực hiện:*
1. `PrescriptionController@store` (`PRESCRIPTIONS.CREATE`), body `{examination_id, notes, items:[{medicine_id, quantity, dosage, usage_instruction}]}`.
2. Đảm bảo 1 examination ↔ 1 prescription (UNIQUE).
3. Nếu có `items`: gọi logic trừ kho (T3.6) trong transaction.

*Cách tiếp cận (từng bước):*
1. Tách hàm trừ-kho-cho-item dùng chung cho `store` (items kèm) và `addItem` (T3.5).
2. Validate `examination_id` tồn tại và chưa có đơn.
3. Test tạo đơn có items → tồn kho giảm đúng; tạo đơn thứ 2 cho cùng phiếu → 422.

*Prompt gợi ý (vibe code):* "PrescriptionController store: tạo đơn gắn examination (1-1, UNIQUE), body {examination_id, notes, items[]}; nếu có items → trừ kho trong transaction (dùng chung logic với addItem); PRESCRIPTIONS.CREATE."

**4. Checklist hoàn thành**
- [ ] Tạo đơn + items.
- [ ] 1 phiếu ↔ 1 đơn.

**5. Checklist self-test**
- [ ] Tạo đơn thứ 2 cho cùng examination → 422/UNIQUE.
- [ ] CASHIER tạo đơn → 403.

---

## T3.5 — Thêm / sửa / xóa dòng thuốc trong đơn

**1. Nội dung task gốc**
> API `addItem`, `updateItem`, `removeItem` với permission tương ứng. Không cho trùng medicine trên cùng đơn (dùng update để đổi số lượng).

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Quản lý các dòng thuốc trong đơn sau khi đã tạo: thêm thuốc, sửa số lượng/liều, xóa thuốc — mỗi thao tác kéo theo cập nhật kho tương ứng (T3.6/T3.7). Không cho thêm trùng thuốc (phải dùng `updateItem` để đổi số lượng).

*Các bước thực hiện:*
1. `POST /prescriptions/{id}/items` → `addItem` (`PRESCRIPTIONS.ADDITEM`).
2. `PUT/PATCH /prescriptions/{id}/items/{itemId}` → `updateItem` (`PRESCRIPTIONS.UPDATEITEM`).
3. `DELETE /prescriptions/{id}/items/{itemId}` → `removeItem` (`PRESCRIPTIONS.REMOVEITEM`).
4. `addItem` với medicine đã có trong đơn → 422 (yêu cầu dùng updateItem).
5. Kho: addItem trừ; updateItem điều chỉnh delta; removeItem hoàn (chi tiết T3.6/T3.7).

*Cách tiếp cận (từng bước):*
1. Validate item thuộc đúng prescription trong path (`{id}` và `{itemId}` khớp) → sai → 404.
2. Toàn bộ thao tác kho trong transaction + `lockForUpdate`.
3. Test: addItem trùng → 422; thao tác item không thuộc đơn → 404.

*Prompt gợi ý (vibe code):* "addItem/updateItem/removeItem cho prescription items với permission tương ứng; addItem trùng medicine → 422; validate item thuộc đúng đơn (404 nếu sai); kho cập nhật trong transaction + lockForUpdate."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Sửa đơn sau khi đã lập hóa đơn:** T3.9 tạo hóa đơn từ đơn thuốc (tính tiền theo items). Nếu cho `addItem/updateItem/removeItem` **sau khi** đã có hóa đơn cho phiếu khám đó, số tiền hóa đơn sẽ lệch với đơn thực tế. Đề không nói rõ. Cần chốt: khóa chỉnh sửa item khi phiếu khám đã có hóa đơn (khuyến nghị), hay cho sửa và chấp nhận lệch? — xác nhận với mentor.

**4. Checklist hoàn thành**
- [ ] 3 endpoint + permission.
- [ ] Chặn trùng medicine khi addItem.

**5. Checklist self-test**
- [ ] addItem medicine đã có → 422.
- [ ] updateItem/removeItem item không thuộc đơn → 404.

---

## T3.6 — Transaction trừ kho khi kê thuốc

**1. Nội dung task gốc**
> Khi thêm item / tạo đơn kèm items: `lockForUpdate` medicine, kiểm tra stock, trừ kho trong `DB::transaction`. Thiếu hàng → 422 + rollback toàn bộ.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Khi kê thuốc (tạo đơn kèm items hoặc addItem), kho phải bị trừ **an toàn**: khóa dòng thuốc để tránh hai request cùng trừ làm âm kho, kiểm tra thuốc còn active và đủ tồn, trừ trong transaction; thiếu hàng → 422 và rollback **toàn bộ** (không trừ nửa vời).

*Các bước thực hiện:*
1. Mở `DB::transaction`.
2. Với mỗi item: `Medicine::where('id', $id)->lockForUpdate()->firstOrFail()`.
3. Kiểm tra `is_active=true` và `stock >= quantity`; vi phạm → ném ValidationException (422) → rollback.
4. Đủ điều kiện → `stock -= quantity`, tạo item.

*Cách tiếp cận (từng bước):*
1. `lockForUpdate` giữ khóa dòng đến hết transaction → request khác phải chờ, không đọc tồn cũ.
2. Gộp mọi item vào **một** transaction để đảm bảo all-or-nothing.
3. Message 422 nêu rõ thuốc nào thiếu để dễ xử lý.
4. (Điểm cộng) test đồng thời hai request trừ cùng thuốc → tổng không làm âm kho.

*Prompt gợi ý (vibe code):* "Trừ kho khi kê thuốc: DB::transaction, lockForUpdate từng medicine, kiểm tra is_active + stock>=quantity (thiếu → 422 message nêu thuốc thiếu, rollback toàn bộ), trừ stock, tạo item."

**4. Checklist hoàn thành**
- [ ] Trừ kho trong transaction + lockForUpdate.
- [ ] Thiếu hàng → 422 + rollback.

**5. Checklist self-test**
- [ ] Kê quantity > stock → 422, stock không đổi, đơn/item không tạo.
- [ ] Thuốc `is_active=false` → không cho kê.

---

## T3.7 — Hoàn kho khi xóa hoặc sửa số lượng item

**1. Nội dung task gốc**
> `removeItem`: hoàn `stock = stock + quantity`. `updateItem`: tính delta quantity và cộng/trừ kho tương ứng; nếu tăng mà thiếu hàng → 422.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Kho phải luôn phản ánh đúng thực tế khi đơn thay đổi: xóa dòng thuốc → **hoàn** lại số lượng đã trừ; sửa số lượng → điều chỉnh kho theo **chênh lệch** (tăng số lượng cần thêm kho, thiếu → 422; giảm số lượng → hoàn phần dư).

*Các bước thực hiện:*
1. `removeItem`: trong transaction, `stock += item.quantity`, rồi xóa item.
2. `updateItem`: `delta = new_quantity - old_quantity`.
   - `delta > 0` (tăng): cần kiểm tra `stock >= delta`, thiếu → 422; đủ → `stock -= delta`.
   - `delta < 0` (giảm): `stock += |delta|`.
   - cập nhật `quantity` (và dosage/usage nếu có).

*Cách tiếp cận (từng bước):*
1. Luôn `lockForUpdate` medicine trong transaction (nhất quán với T3.6).
2. Tính delta cẩn thận theo giá trị cũ đọc trong transaction.
3. Test: xóa item → stock tăng lại đúng; tăng quantity vượt tồn → 422, kho không đổi.

*Prompt gợi ý (vibe code):* "removeItem hoàn kho (stock+=qty) rồi xóa item; updateItem tính delta = new-old, delta>0 kiểm stock đủ (thiếu→422) rồi trừ, delta<0 hoàn kho; đều trong transaction + lockForUpdate."

**4. Checklist hoàn thành**
- [ ] removeItem hoàn kho đúng.
- [ ] updateItem xử lý delta + chặn thiếu hàng.

**5. Checklist self-test**
- [ ] Xóa item → stock tăng lại đúng.
- [ ] Tăng quantity vượt stock → 422, kho không đổi.

---

## T3.8 — Migration Invoices

**1. Nội dung task gốc**
> `invoices`: `examination_id` UNIQUE, `invoice_code` unique, `subtotal`, `discount`, `total`, `status` unpaid|paid|cancelled, `issued_at`. Index theo status.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Hóa đơn quan hệ 1-1 với phiếu khám (`examination_id` UNIQUE), có mã hóa đơn duy nhất, các trường tiền (`subtotal`, `discount`, `total`) và trạng thái thanh toán 3 giá trị. Index `status` phục vụ lọc danh sách hóa đơn.

*Các bước thực hiện:*
1. Migration `invoices`: `examination_id` FK UNIQUE, `invoice_code` UNIQUE, `subtotal`/`discount` (default 0)/`total` decimal(12,2), `status` (CHECK unpaid/paid/cancelled, default `unpaid`), `issued_at` timestamp, timestamps. Index `status`.
2. FK `restrictOnDelete` (bảng tài chính).

*Cách tiếp cận (từng bước):*
1. CHECK status bằng raw SQL.
2. `invoice_code` sinh ở Service (T3.9) — bàn định dạng ở T3.9.
3. Test UNIQUE `examination_id`.

*Prompt gợi ý (vibe code):* "Migration invoices (examination_id unique FK, invoice_code unique, subtotal/discount default 0/total decimal(12,2), status CHECK unpaid|paid|cancelled default unpaid, issued_at, index status). FK restrictOnDelete."

**4. Checklist hoàn thành**
- [ ] UNIQUE `examination_id` + `invoice_code` + CHECK status + index status.

**5. Checklist self-test**
- [ ] Tạo 2 invoice cho cùng examination → lỗi UNIQUE.

---

## T3.9 — Tạo hóa đơn từ phiếu khám (tính tiền tự động)

**1. Nội dung task gốc**
> CASHIER tạo invoice: `medicine_total = SUM(qty*price) + consultation_fee (EXAMINATION_FEE)`. `total = subtotal - discount`. `status=unpaid`. Trùng examination → 422.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Thu ngân lập hóa đơn từ phiếu khám; hệ thống **tự tính** tiền: tiền thuốc = tổng (số lượng × giá) của các dòng đơn thuốc, cộng phí khám (`EXAMINATION_FEE`) ra `subtotal`; `total = subtotal − discount`. Trạng thái ban đầu `unpaid`. Mỗi phiếu khám chỉ một hóa đơn (trùng → 422).

> Lưu ý: cell task viết `medicine_total = SUM(qty*price) + consultation_fee`, nhưng theo đề mục 4.8 thì đúng là **`subtotal = SUM(qty*price) + phí khám`** (tiền thuốc và phí khám là hai thành phần của subtotal). Tài liệu này dùng cách hiểu theo mục 4.8.

*Các bước thực hiện:*
1. `InvoiceController@store` (`INVOICES.CREATE`), body `{examination_id, consultation_fee?, discount?}`.
2. Tính `medicine_total = Σ(prescription_items.quantity × medicines.price)`.
3. `subtotal = medicine_total + phí khám`; `total = subtotal − discount` (discount mặc định 0).
4. Sinh `invoice_code` UNIQUE; `status=unpaid`; `issued_at=now`.
5. Trùng examination → 422.

*Cách tiếp cận (từng bước):*
1. Load examination + prescription.items.medicine để tính tiền (eager load, tránh N+1).
2. Xử lý trường hợp phiếu khám **chưa có đơn thuốc**: `medicine_total = 0`, chỉ thu phí khám (xem phần cần xác nhận).
3. Sinh `invoice_code` (vd `INV-` + ngày + sequence) đảm bảo UNIQUE.
4. Test: total khớp công thức; tạo trùng examination → 422.

*Prompt gợi ý (vibe code):* "InvoiceController store từ examination: subtotal = SUM(qty*price) + phí khám, total = subtotal - discount (default 0), status unpaid, invoice_code unique, issued_at now; trùng examination → 422; INVOICES.CREATE; eager load items.medicine."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Phí khám lấy từ đâu:** API payload có `consultation_fee` nhưng đề mục 4.8 nói dùng **config `EXAMINATION_FEE`**. Mâu thuẫn: nhận từ request (thu ngân nhập tay) hay lấy cứng từ config? Cần chốt (khuyến nghị: mặc định lấy `EXAMINATION_FEE`, cho phép override qua payload nếu mentor đồng ý).
- **Giá thuốc hiện tại vs snapshot:** đề nói "lấy giá hiện tại **hoặc** snapshot; README chọn 1 cách và nhất quán". Cần chốt: tính theo `medicines.price` tại thời điểm lập hóa đơn (đơn giản, khuyến nghị) hay lưu snapshot giá vào lúc kê đơn.
- **Hóa đơn khi chưa có đơn thuốc:** đề cho phép (chỉ thu phí khám) — cần xác nhận và ghi rõ công thức trong README.

**4. Checklist hoàn thành**
- [ ] Tính subtotal/total đúng công thức.
- [ ] Trùng examination → 422.
- [ ] `invoice_code` UNIQUE, status unpaid.

**5. Checklist self-test**
- [ ] total = Σ(qty×price) + phí khám − discount.
- [ ] RECEPTIONIST/DOCTOR tạo invoice → 403.

---

## T3.10 — Sửa discount / hủy hóa đơn an toàn

**1. Nội dung task gốc**
> Chỉ cho UPDATE discount hoặc cancelled khi `status=unpaid` và chưa có payment completed. Đã thanh toán một phần/đủ → 422.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Chỉ được sửa discount hoặc hủy hóa đơn khi nó **chưa phát sinh thanh toán hoàn tất** và còn `unpaid`. Nếu đã có payment `completed` (thanh toán một phần hoặc đủ) → không cho sửa/hủy (422), tránh sai lệch tài chính.

*Các bước thực hiện:*
1. `update` (sửa discount) và `updateStatus` (hủy → cancelled): chỉ cho khi `status=unpaid` **và** không tồn tại payment `completed` của hóa đơn.
2. Vi phạm → 422.
3. Sửa discount → tính lại `total = subtotal − discount`.

*Cách tiếp cận (từng bước):*
1. Guard trong `InvoiceService`: `canModify(Invoice)` = `status==unpaid && !payments()->where('status','completed')->exists()`.
2. Sau khi đổi discount, cập nhật `total`.
3. Test: có payment completed → sửa/hủy → 422.

*Prompt gợi ý (vibe code):* "Chỉ cho sửa discount / hủy invoice khi status=unpaid và chưa có payment completed, ngược lại → 422; sửa discount tính lại total. Guard trong InvoiceService."

**4. Checklist hoàn thành**
- [ ] Guard trạng thái + payment completed.
- [ ] Sửa discount cập nhật total.

**5. Checklist self-test**
- [ ] Có payment completed → sửa/hủy → 422.
- [ ] unpaid, chưa payment → sửa discount OK, total đổi.

---

## T3.11 — Migration Payments cho PayPal/Visa

**1. Nội dung task gốc**
> `payments`: `invoice_id`, `amount>0`, `method` paypal|visa, `status` pending|completed|failed|cancelled, `provider`, `provider_order_id`, `provider_capture_id`, `paid_at`, `note`. Index `invoice_id`, `provider_order_id`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Bảng thanh toán lưu từng lệnh thanh toán của một hóa đơn qua PayPal (ví hoặc thẻ Visa). Cần đủ cột để lưu vòng đời PayPal: `provider_order_id` (Order tạo ra), `provider_capture_id` (khi capture thành công), `status` 4 giá trị, `paid_at`. Một hóa đơn có thể có nhiều payment (thanh toán từng phần).

*Các bước thực hiện:*
1. Migration `payments`: `invoice_id` FK, `amount` decimal(12,2) CHECK > 0, `method` CHECK (paypal/visa), `status` CHECK (pending/completed/failed/cancelled, default pending), `provider` string default 'paypal', `provider_order_id` nullable, `provider_capture_id` nullable, `paid_at` nullable, `note` nullable, timestamps.
2. Index `invoice_id`, `provider_order_id`.

*Cách tiếp cận (từng bước):*
1. CHECK cho `amount`, `method`, `status` bằng raw SQL.
2. FK `invoice_id` restrict (bảng tài chính).
3. Test insert amount ≤ 0 → lỗi CHECK.

*Prompt gợi ý (vibe code):* "Migration payments (invoice_id FK, amount decimal CHECK>0, method CHECK paypal|visa, status CHECK 4 giá trị default pending, provider default paypal, provider_order_id, provider_capture_id, paid_at, note nullable; index invoice_id, provider_order_id)."

**4. Checklist hoàn thành**
- [ ] Đủ cột + CHECK + index.

**5. Checklist self-test**
- [ ] amount ≤ 0 → lỗi CHECK.

---

## T3.12 — Đảm bảo đủ cột PayPal trên payments

**1. Nội dung task gốc**
> (Gộp với T3.11 nếu đã làm) Kiểm tra migration có đủ `provider_order_id` và `provider_capture_id` để lưu Order ID / Capture ID từ PayPal Sandbox. Không lưu số thẻ Visa trong DB.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Task rà soát: đảm bảo bảng `payments` (T3.11) đủ hai cột lưu ID từ PayPal (`provider_order_id`, `provider_capture_id`) và **tuyệt đối không** có cột lưu số thẻ Visa (yêu cầu bảo mật/PCI — không bao giờ lưu số thẻ).

*Các bước thực hiện:*
1. Rà migration T3.11 có đủ `provider_order_id`, `provider_capture_id`.
2. Xác nhận không có cột nào lưu số thẻ (`card_number`…).

*Cách tiếp cận (từng bước):*
1. Nếu T3.11 đã đủ, task này chỉ là bước xác nhận (không cần migration mới).
2. `\d payments` trong psql để soát cột.

*Prompt gợi ý (vibe code):* "Rà soát bảng payments đủ provider_order_id/provider_capture_id, không có cột lưu số thẻ Visa."

**4. Checklist hoàn thành**
- [ ] Có `provider_order_id`, `provider_capture_id`.
- [ ] Không có cột lưu số thẻ.

**5. Checklist self-test**
- [ ] `\d payments` xác nhận cột; không có `card_number`.

---

## T3.13 — Tạo lệnh thanh toán PayPal Order (pending)

**1. Nội dung task gốc**
> `POST /api/invoices/{id}/payments` với `amount` + `method` paypal|visa. Gọi PayPal Sandbox tạo Order; lưu payment `status=pending`; trả `approval_url`/`order_id`. `amount` không vượt số còn lại → 422. Permission `PAYMENTS.CREATE`.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Thu ngân khởi tạo một lệnh thanh toán cho hóa đơn: backend gọi PayPal Sandbox tạo **Order**, lưu payment `pending` kèm `provider_order_id`, trả về `approval_url`/`order_id` để khách duyệt. Số tiền không được vượt **số còn phải thu** của hóa đơn (`total − Σ payment completed`) → 422.

*Các bước thực hiện:*
1. `PaymentController@store` (`PAYMENTS.CREATE`), body `{amount, method: paypal|visa, note?}`.
2. Tính số còn lại của hóa đơn; nếu `amount >` số còn lại → 422.
3. `PayPalService`: lấy OAuth2 token (client credentials), gọi Create Order với số tiền (quy đổi `PAYPAL_CURRENCY`).
4. Lưu `payments` `status=pending`, `provider=paypal`, `provider_order_id`, `method`, `amount`.
5. Trả `approval_url` (từ `links`) + `order_id`.

*Cách tiếp cận (từng bước):*
1. Viết `PayPalService::token()` và `createOrder()` (xem [backend.md](../skills/backend.md#7-paypal-sandbox)).
2. Đọc credential từ `config/paypal.php` (không dùng `env()` trực tiếp).
3. Không log secret/response nhạy cảm.
4. Test: tạo order → nhận `order_id`/`approval_url`; amount vượt số còn lại → 422.

*Prompt gợi ý (vibe code):* "PaymentController store: kiểm amount ≤ số còn lại (total − Σ completed) else 422; PayPalService lấy OAuth2 token + Create Order (currency từ config), lưu payment pending + provider_order_id, trả approval_url/order_id; PAYMENTS.CREATE."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Quy đổi tiền tệ khi tạo Order:** `amount` (theo đơn vị nội bộ, có thể VND) phải gửi lên PayPal theo `PAYPAL_CURRENCY` (USD). Cần chốt cách quy đổi (liên quan điểm xác nhận ở T1.3) — nếu không, số tiền order sẽ sai.
- **Thanh toán từng phần:** thiết kế cho phép nhiều payment cho một hóa đơn (amount ≤ số còn lại). Cần xác nhận mentor có muốn hỗ trợ thanh toán từng phần hay bắt buộc thanh toán đủ một lần.

**4. Checklist hoàn thành**
- [ ] Tạo order Sandbox → payment pending.
- [ ] amount vượt số còn lại → 422.

**5. Checklist self-test**
- [ ] Tạo order → nhận `order_id`/`approval_url`.
- [ ] amount > số còn lại → 422.

Chi tiết PayPal: [skills/backend.md](../skills/backend.md).

---

## T3.14 — Capture thanh toán PayPal và cập nhật hóa đơn

**1. Nội dung task gốc**
> `POST /api/payments/{id}/capture` (`PAYMENTS.CAPTURE`). Capture trên Sandbox; success → `status=completed` + lưu capture id. Transaction: nếu tổng completed = `invoice.total` → `invoices.status=paid`. Fail → failed, giữ unpaid.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Sau khi khách duyệt Order trên PayPal, thu ngân gọi capture để **thu tiền thật** (trên Sandbox). Capture thành công → payment `completed`, lưu `provider_capture_id`, `paid_at`; và trong transaction, nếu tổng các payment `completed` đạt `invoice.total` → hóa đơn `paid`. Capture thất bại → payment `failed`, hóa đơn giữ `unpaid`.

*Các bước thực hiện:*
1. `PaymentController@capture` (`PAYMENTS.CAPTURE`).
2. Gọi PayPal Capture Order theo `provider_order_id`.
3. Success → trong transaction: set payment `completed` + `provider_capture_id` + `paid_at`; tính tổng completed; nếu ≥ `total` → invoice `paid`.
4. Fail/hủy → payment `failed`/`cancelled`, invoice giữ `unpaid`.

*Cách tiếp cận (từng bước):*
1. `PayPalService::captureOrder($orderId)`.
2. Bọc cập nhật payment + invoice trong `DB::transaction`.
3. So sánh `Σ completed` với `total` (dùng cùng đơn vị/tiền tệ đã chốt).
4. Test: thanh toán đủ → invoice paid; một phần → vẫn unpaid.

*Prompt gợi ý (vibe code):* "PaymentController capture: PayPalService captureOrder; success → transaction set payment completed + capture_id + paid_at, cộng dồn completed đạt total → invoice paid; fail → failed giữ unpaid; PAYMENTS.CAPTURE."

**4. Checklist hoàn thành**
- [ ] Capture success → completed + invoice paid khi đủ tiền.
- [ ] Fail → failed, invoice unpaid.

**5. Checklist self-test**
- [ ] Thanh toán đủ → invoice `paid`.
- [ ] Thanh toán một phần → invoice vẫn `unpaid`.

---

## T3.15 — Hỗ trợ thanh toán thẻ Visa qua PayPal

**1. Nội dung task gốc**
> `method=visa` dùng luồng PayPal hỗ trợ thẻ (card fields/checkout). README hướng dẫn tạo app PayPal Developer và số thẻ Visa test sandbox. Không dùng tiền thật.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Ngoài ví PayPal, hệ thống hỗ trợ thanh toán bằng **thẻ Visa** thông qua cổng PayPal (card fields / hosted checkout). Về backend, luồng order/capture giống `method=paypal`; khác biệt nằm ở bước khách nhập thẻ phía client. README phải hướng dẫn tạo app PayPal Developer và dùng **số thẻ Visa test** của Sandbox (không tiền thật).

*Các bước thực hiện:*
1. Backend: `method=visa` dùng cùng Create Order/Capture; ghi `method=visa` trên payment.
2. README: bổ sung hướng dẫn app PayPal Developer + thẻ Visa test (đã có khung tại [README mục 13](../README.md#13-tích-hợp-paypal-sandbox--visa)).

*Cách tiếp cận (từng bước):*
1. Giữ backend thống nhất order/capture; chỉ khác giá trị `method`.
2. Bổ sung mô tả bước nhập thẻ phía client (frontend/Postman) vào README.
3. Test bằng số thẻ Visa test của PayPal Sandbox.

*Prompt gợi ý (vibe code):* "Hỗ trợ method=visa dùng cùng luồng PayPal order/capture (ghi method=visa); bổ sung README hướng dẫn app PayPal Developer + thẻ Visa test sandbox."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Luồng thẻ Visa cần thành phần client-side:** PayPal card fields / Advanced Card Payments yêu cầu **SDK phía client** và thường cần bật tính năng "Advanced Checkout" trên tài khoản Sandbox; API-only backend khó hoàn tất luồng nhập thẻ trọn vẹn. Cần chốt phạm vi test: chỉ cần chứng minh backend tạo/capture order với `method=visa` trên Sandbox (kèm frontend Blade tối giản hoặc Postman), hay phải demo nhập thẻ đầy đủ? — xác nhận với mentor để không sa đà.

**4. Checklist hoàn thành**
- [ ] method=visa tạo order + capture được (Sandbox).
- [ ] README có hướng dẫn thẻ test.

**5. Checklist self-test**
- [ ] Thanh toán bằng thẻ Visa test → completed.

---

## T3.16 — Bảo mật credential PayPal

**1. Nội dung task gốc**
> Chỉ lưu `CLIENT_ID/SECRET` trong `.env`. `.env.example` dùng placeholder. Không commit secret. README cảnh báo chỉ sandbox.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Bảo vệ bí mật PayPal: secret chỉ nằm trong `.env` (không commit), `.env.example` chỉ placeholder, code đọc qua config, README cảnh báo chỉ dùng Sandbox. Đây là yêu cầu bảo mật bắt buộc, dễ mất điểm nếu lộ secret trong git.

*Các bước thực hiện:*
1. Đảm bảo `PAYPAL_CLIENT_ID/SECRET` chỉ ở `.env`; `.env.example` là placeholder.
2. `config/paypal.php` đọc env; code không hard-code secret; không log secret.
3. README cảnh báo sandbox-only.

*Cách tiếp cận (từng bước):*
1. `git grep -i client_secret` để chắc chắn không có secret thật trong repo/lịch sử.
2. Kiểm tra log không in credential/response chứa token.
3. Nếu lỡ commit secret → xoay (rotate) secret trên PayPal và xóa khỏi lịch sử git.

*Prompt gợi ý (vibe code):* "Rà soát bảo mật PayPal: secret chỉ trong .env, config/paypal.php đọc env, không commit/log secret; README cảnh báo sandbox."

**4. Checklist hoàn thành**
- [ ] Secret không nằm trong repo.
- [ ] README cảnh báo sandbox.

**5. Checklist self-test**
- [ ] `git grep -i client_secret` không ra giá trị thật.

---

## T3.17 — Seeder dữ liệu demo đầy đủ luồng khám

**1. Nội dung task gốc**
> Seed specialties, doctors (kèm user), patients, medicines, và (tuỳ chọn) một chuỗi appointment→examination mẫu để demo nhanh.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Có dữ liệu mẫu để demo/chấm nhanh mà không phải tạo tay từng bước: danh mục (chuyên khoa, bác sĩ kèm user, bệnh nhân, thuốc) và tuỳ chọn một chuỗi nghiệp vụ mẫu (appointment → examination → prescription → invoice → payment).

*Các bước thực hiện:*
1. Seed 2–3 specialty; vài doctor (mỗi doctor kèm một user role DOCTOR); vài patient; vài medicine.
2. (Tuỳ chọn) một chuỗi mẫu đủ luồng để `/api/stats` có số liệu và demo nhanh.

*Cách tiếp cận (từng bước):*
1. Seeder idempotent (`firstOrCreate`), đặt **sau** seed RBAC/ADMIN trong `DatabaseSeeder`.
2. Tái dùng logic Service (trừ kho, transaction) khi seed chuỗi mẫu để dữ liệu hợp lệ, nhất quán với API.
3. Chạy `migrate:fresh --seed` và gọi `/api/stats` xác nhận có số liệu.

*Prompt gợi ý (vibe code):* "Seeder demo idempotent: specialties, doctors+user (role DOCTOR), patients, medicines, và chuỗi mẫu appointment→examination→prescription→invoice→payment (dùng Service để dữ liệu hợp lệ)."

**4. Checklist hoàn thành**
- [ ] Seed đủ danh mục + (tuỳ chọn) demo luồng.

**5. Checklist self-test**
- [ ] Sau `migrate --seed`, gọi `/api/stats` có số liệu.

---

# Tuần 4 — Audit log, Stats, Test, Đóng gói

**Mục tiêu tuần:** activity log (Event/Observer); Stats aggregate; feature tests (mock PayPal); chuẩn response/HTTP; Postman; README hướng dẫn PayPal; demo.

---

## T4.1 — Activity logs bằng Event/Observer

**1. Nội dung task gốc**
> Bảng `activity_logs` (`user_id`, `action`, `subject_type/id`, `meta` JSONB). Ghi log tối thiểu: user, appointment status, examination, prescription/kho, invoice, payment. Implement bằng Event+Listener hoặc Observer.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Ghi vết (audit) các hành động quan trọng để truy vết ai làm gì, khi nào. Bảng `activity_logs` với `meta` JSONB lưu chi tiết (before/after, số lượng thay đổi). Cài đặt bằng Observer (cho CRUD chuẩn) và/hoặc Event+Listener (cho action đặc thù).

*Các bước thực hiện:*
1. Migration `activity_logs`: `user_id` nullable FK, `subject_type` string, `subject_id` bigint, `action` string, `meta` JSONB nullable, `created_at`. Index `(subject_type, subject_id)`.
2. Đăng ký Observer/Event cho các mốc: tạo/đổi role/khóa user, đổi status appointment, tạo examination, tạo prescription/điều chỉnh kho, tạo invoice, tạo/capture payment.
3. `meta` lưu ngữ cảnh (vd before/after của status).

*Cách tiếp cận (từng bước):*
1. Observer cho các model có CRUD chuẩn (vd `AppointmentObserver::updated` ghi khi đổi status).
2. Event+Listener cho action không map thẳng vào lifecycle model (capture payment, adjustStock).
3. `user_id` lấy từ `auth()->id()`; hệ thống tự sinh (seeder) → null.
4. Test: đổi status appointment → có log `status_changed` với meta before/after.

*Prompt gợi ý (vibe code):* "Migration activity_logs (user_id nullable, subject_type/id, action, meta JSONB, index (subject_type,subject_id)). Observer/Event ghi log cho user, appointment status, examination, prescription/kho, invoice, payment; meta lưu before/after."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Quy ước `subject_type`:** đề nêu ví dụ chuỗi ngắn (`appointment`, `invoice`…). Cần chốt dùng chuỗi ngắn tự đặt hay dùng morph map/class name của Laravel — ảnh hưởng cách truy vấn log về sau.

**4. Checklist hoàn thành**
- [ ] Bảng + JSONB + index.
- [ ] Log các action chính.

**5. Checklist self-test**
- [ ] Đổi status appointment → log `status_changed` có meta before/after.
- [ ] Capture payment → có log.

---

## T4.2 — API Stats tổng quan phòng khám

**1. Nội dung task gốc**
> `GET /api/stats` dùng SQL aggregate: số bệnh nhân, lịch hôm nay, doanh thu tháng, thuốc sắp hết. Permission `STATS.SHOW`. Không đếm bằng PHP collection.

**2. Diễn giải chi tiết**

*Vấn đề (nghiệp vụ & mục tiêu):*
Dashboard số liệu tổng quan cho ADMIN, tính bằng **SQL aggregate** (COUNT/SUM) — tuyệt đối không load hết bản ghi về rồi đếm bằng PHP (yêu cầu về hiệu năng và cách chấm).

*Các bước thực hiện:*
1. `StatsController@show` (`STATS.SHOW`).
2. Bốn chỉ số: tổng bệnh nhân (`COUNT patients`), lịch hôm nay (`COUNT appointments whereDate today`), doanh thu tháng (`SUM total invoices paid trong tháng`), thuốc sắp hết (`COUNT medicines stock <= ngưỡng`).

*Cách tiếp cận (từng bước):*
1. Mỗi chỉ số là một query aggregate riêng (hoặc gộp hợp lý), không dùng vòng lặp PHP.
2. Đặt trong `StatsService` để controller mỏng.
3. Test số liệu khớp dữ liệu seed.

*Prompt gợi ý (vibe code):* "StatsController show: aggregate SQL tổng bệnh nhân, lịch hôm nay, doanh thu tháng (invoices paid theo tháng), thuốc sắp hết (stock<=ngưỡng); STATS.SHOW; không đếm bằng PHP collection."

**3. Vấn đề cần lưu ý hoặc xác nhận**
- **Ngưỡng "thuốc sắp hết":** đề không cho con số cụ thể. Cần chốt ngưỡng (vd `stock <= 10`) — nên đưa vào config để dễ đổi.
- **"Doanh thu tháng" tính theo gì:** theo `issued_at` hay `paid_at`? Tính trên hóa đơn `paid` hay tổng payment `completed` trong tháng? Cần chốt định nghĩa để số liệu nhất quán.

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

*Vấn đề (nghiệp vụ & mục tiêu):*
Feature test chốt chất lượng toàn hệ thống: các case RBAC quan trọng và luồng nghiệp vụ chính đầu–cuối. Phần thanh toán phải **mock PayPal** (không gọi Sandbox thật trong test để test nhanh, ổn định, không phụ thuộc mạng).

*Các bước thực hiện:*
1. Test RBAC: role thiếu quyền → 403; RECEPTIONIST `POST /invoices` → 403; DOCTOR `capture` → 403.
2. Test luồng chính: patient → appointment → examination → prescription (kiểm tra trừ kho) → invoice → payment.
3. Mock PayPal bằng `Http::fake()`.

*Cách tiếp cận (từng bước):*
1. Tái dùng factory + seed RBAC (như T2.11).
2. `Http::fake([...])` giả lập response create order/capture của PayPal.
3. Assert trạng thái DB (stock giảm, invoice paid) thay vì gọi network.
4. `php artisan test` toàn bộ xanh.

*Prompt gợi ý (vibe code):* "Feature tests: RBAC 403 (RECEPTIONIST invoice, DOCTOR capture); luồng patient→appointment→examination→prescription (kiểm trừ kho)→invoice→payment với Http::fake mock PayPal; assert trạng thái DB."

**4. Checklist hoàn thành**
- [ ] Test RBAC + luồng chính pass.
- [ ] PayPal được mock.

**5. Checklist self-test**
- [ ] `php artisan test` toàn bộ xanh.
- [ ] Test không gọi network thật.

---

## Task đóng gói bổ sung (theo lịch 4 tuần mục 9 của đề)

File `task.xlsx` chỉ liệt kê T4.1–T4.3, nhưng lịch 4 tuần và ví dụ branch (`T4.7-readme`) của đề yêu cầu thêm các đầu việc đóng gói dưới đây. Đặt ID nối tiếp (T4.4+) khi tạo branch. Các task này chủ yếu là rà soát/đóng gói nên trình bày gọn.

### T4.4 — Chuẩn hóa response & HTTP status toàn hệ thống
- *Vấn đề:* rà toàn bộ endpoint đúng envelope + ma trận HTTP status (201 tạo mới, 401/403/404/422 đúng case), Exception Handler trả JSON.
- *Các bước:* duyệt từng nhóm endpoint bằng Postman, đối chiếu ma trận [README mục 10](../README.md#10-chuẩn-response--http-status); sửa chỗ sai status/thiếu errors.
- *Checklist hoàn thành:* [ ] mọi endpoint đúng status; [ ] 422 có errors theo field; [ ] không rơi HTML.
- *Self-test:* gọi thử case lỗi cho mỗi nhóm, xác nhận status + envelope.

### T4.5 — Postman collection
- *Vấn đề:* nộp `postman_collection.json` gồm luồng chính đầy đủ + case lỗi 401/403/404/422 + capture PayPal.
- *Các bước:* dựng collection với biến môi trường `base_url`, `token`; tổ chức theo nhóm resource; thêm request cho case lỗi.
- *Checklist hoàn thành:* [ ] có collection; [ ] biến môi trường; [ ] đủ case lỗi + luồng chính.
- *Self-test:* import Postman, chạy từ login → payment không sửa tay.

### T4.6 — Hướng dẫn PayPal Developer trong README
- *Vấn đề:* README hướng dẫn tạo app PayPal Sandbox, biến `.env`, thẻ Visa test (khung đã có tại [README mục 13](../README.md#13-tích-hợp-paypal-sandbox--visa)).
- *Các bước:* bổ sung các bước tạo app + lấy credential + tạo sandbox account + số thẻ Visa test.
- *Checklist hoàn thành:* [ ] đủ bước tạo app + credential + thẻ test.
- *Self-test:* người mới theo README tạo được order sandbox.

### T4.7 — Hoàn thiện README + demo
- *Vấn đề:* README đủ mục kiến trúc/RBAC/index/transaction/N+1; chuẩn bị kịch bản demo đầu–cuối.
- *Các bước:* rà README theo checklist đề; chạy thử toàn bộ từ máy trắng.
- *Checklist hoàn thành:* [ ] README đủ mục; [ ] `docker compose up` + `migrate --seed` + `test` chạy sạch từ máy trắng.
- *Self-test:* clone repo mới, chạy đúng chuỗi lệnh chấm ([README mục 4](../README.md#4-cài-đặt--chạy-bằng-docker)) không lỗi.

### T4.8 (tuỳ chọn) — Frontend Blade demo
- *Vấn đề:* trang Blade tối giản đăng nhập + xem danh sách (bệnh nhân/lịch/hóa đơn) tiêu thụ API bằng Bearer token (phần mở rộng ngoài đề — xem ghi chú Frontend ở [README mục 1](../README.md#1-mục-tiêu--phạm-vi)).
- *Các bước:* login lưu token; trang danh sách gọi `/api/*`; ẩn/hiện theo permission.
- *Checklist hoàn thành:* [ ] login lưu token; [ ] gọi `/api/*` hiển thị dữ liệu.
- *Self-test:* đăng nhập trên UI, thao tác 1 luồng đọc. Chi tiết: [skills/frontend.md](../skills/frontend.md).

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

---

## Tổng hợp các điểm cần xác nhận (gom từ mục 3 các task)

Danh sách nhanh để trao đổi một lần với mentor / tự quyết trước khi code:

| # | Task | Điểm cần chốt |
|---|---|---|
| 1 | T1.3, T3.13 | Quy đổi tiền tệ VND (EXAMINATION_FEE/price) ↔ USD (PAYPAL_CURRENCY) khi tạo PayPal order |
| 2 | T1.5 | Bảng `personal_access_tokens` nằm ngoài "14 bảng"; vòng đời token; khuyết T1.6 |
| 3 | T1.8 | Ranh giới migration (permissions) vs seeder (role_permissions); con số permission chính xác |
| 4 | T1.9 | Mật khẩu ADMIN mặc định (ghi README) |
| 5 | T1.10 | Thuật toán Controller→resource name (map tường minh); webhook ngoài RBAC |
| 6 | T1.12 | Ngôn ngữ message API (Anh/Việt) nhất quán |
| 7 | T1.13 | Định nghĩa "ADMIN cuối cùng"; ADMIN tự khóa mình |
| 8 | T1.15 | `license_number` có UNIQUE không |
| 9 | T2.1 | Định dạng & cơ chế sinh `patients.code` |
| 10 | T2.3, T2.6 | Độ dài buổi khám (slot cố định vs cột duration/end_at) cho chống trùng lịch |
| 11 | T2.5 | `completed` set thủ công qua updateStatus hay chỉ qua tạo phiếu khám |
| 12 | T2.8 | Nguồn hợp lệ tạo phiếu: chỉ `confirmed` hay cả `scheduled` |
| 13 | T2.11 | DB cho test: Postgres hay sqlite |
| 14 | T3.2 | `quantity` của adjustStock là delta hay giá trị tuyệt đối |
| 15 | T3.5 | Cho sửa item sau khi đã lập hóa đơn không |
| 16 | T3.9 | Phí khám từ request hay config; giá thuốc hiện tại vs snapshot; hóa đơn khi chưa có đơn thuốc |
| 17 | T3.13 | Thanh toán từng phần (nhiều payment/invoice) có được phép |
| 18 | T3.15 | Phạm vi test luồng Visa (backend-only vs nhập thẻ đầy đủ) |
| 19 | T4.1 | Quy ước `subject_type` (chuỗi ngắn vs class/morph map) |
| 20 | T4.2 | Ngưỡng "thuốc sắp hết"; định nghĩa "doanh thu tháng" |
