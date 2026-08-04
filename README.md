# Clinic Management REST API

Hệ thống quản lý phòng khám (single-clinic) xây dựng bằng **Laravel 13 + PostgreSQL 16 + Docker Compose**. Dự án cho nghiệp vụ phòng khám thực tế (bệnh nhân, bác sĩ, lịch khám, phiếu khám, đơn thuốc, thuốc, hóa đơn, thanh toán), có RBAC toàn hệ thống và tích hợp thanh toán PayPal Sandbox (kèm thẻ Visa qua PayPal).

> Tài liệu này là điểm vào (overview → reference). Chi tiết theo từng task xem [docs/ke-hoach-chi-tiet.md](docs/ke-hoach-chi-tiet.md). Playbook kỹ thuật theo mảng xem thư mục [skills/](skills/).

---

## Mục lục

1. [Mục tiêu & phạm vi](#1-mục-tiêu--phạm-vi)
2. [Stack công nghệ](#2-stack-công-nghệ)
3. [Kiến trúc đã chọn (B — Controller + Service)](#3-kiến-trúc-đã-chọn-b--controller--service)
4. [Cài đặt & chạy bằng Docker](#4-cài-đặt--chạy-bằng-docker)
5. [Biến môi trường (.env)](#5-biến-môi-trường-env)
6. [Tài khoản được seed sẵn](#6-tài-khoản-được-seed-sẵn)
7. [RBAC Global](#7-rbac-global)
8. [Mô hình dữ liệu (Database)](#8-mô-hình-dữ-liệu-database)
9. [Danh sách API đầy đủ](#9-danh-sách-api-đầy-đủ)
10. [Chuẩn Response & HTTP status](#10-chuẩn-response--http-status)
11. [Luồng nghiệp vụ chính](#11-luồng-nghiệp-vụ-chính)
12. [Transaction bắt buộc](#12-transaction-bắt-buộc)
13. [Tích hợp PayPal Sandbox + Visa](#13-tích-hợp-paypal-sandbox--visa)
14. [Frontend (Blade + Vite)](#14-frontend-blade--vite)
15. [Kiểm thử (Feature tests)](#15-kiểm-thử-feature-tests)
16. [Quy ước branch & Pull Request](#16-quy-ước-branch--pull-request)
17. [Tiêu chí chấm điểm](#17-tiêu-chí-chấm-điểm)
18. [Cấu trúc thư mục](#18-cấu-trúc-thư-mục)
19. [Tài liệu liên quan](#19-tài-liệu-liên-quan)

---

## 1. Mục tiêu & phạm vi

### Mục tiêu
- Thiết kế REST API nhiều resource cho một phòng khám thực tế.
- Auth bằng **Laravel Sanctum** (API token dạng Bearer).
- **RBAC Global** toàn hệ thống: mỗi user có đúng **1 role** (`users.role_id`); permission đặt theo quy ước `CONTROLLER.ACTION` (ví dụ `PATIENTS.FINDALL`, `PAYMENTS.CAPTURE`); middleware tự map Controller@action → permission và kiểm tra.
- Postgres nâng cao: schema nhiều bảng, FK, CHECK/ENUM, UNIQUE, index có chủ đích, transaction, aggregate.
- Transaction cho nghiệp vụ đa bước: phiếu khám, kê đơn + trừ kho, hóa đơn, PayPal capture.
- Event/Listener hoặc Observer ghi `activity_logs`.
- Feature test cơ bản + README giải thích quyết định kỹ thuật.

### Trong phạm vi
Nghiệp vụ đầu–cuối: Auth → quản trị user/role → danh mục (chuyên khoa, bác sĩ, thuốc) → bệnh nhân → lịch khám → phiếu khám → đơn thuốc (+ trừ kho) → hóa đơn → thanh toán PayPal/Visa → activity log → thống kê.

### Giới hạn phạm vi (các ràng buộc độc lập nêu trong phần *Mô tả hệ thống* của đề)
Phần *2. Mô tả hệ thống* của đề liệt kê một loạt **dòng ràng buộc/giới hạn độc lập** — mỗi dòng là một biên phạm vi, không phải hạng mục sẽ code:
- Multi-tenant dưới mọi hình thức (không có bảng `tenants`, không có cột `tenant_id`) — đây là **một** phòng khám duy nhất.
- Public register (đăng ký tự do) — chỉ ADMIN tạo user hoặc seeder tạo ADMIN đầu tiên.
- Domain quản lý dự án (project/task/tag/comment) của đề cũ — không copy lại bất kỳ bảng/API nào.
- Frontend (Blade/Vue/React) — đề đặt trong nhóm giới hạn này.
- Email SMTP thật, SMS, realtime/WebSocket, push notification.
- CI/CD, Kubernetes, multi-stage Docker phức tạp.
- Upload file phức tạp (ảnh X-quang…), OAuth social login.
- Package **Spatie Laravel Permission** — phải tự thiết kế bảng RBAC.
- Bảng `invoice_items` chi tiết dòng hóa đơn — hóa đơn tính `subtotal` tổng hợp từ đơn thuốc + phí khám.
- Bước "phát thuốc" (dispense) riêng cho dược sĩ — dược sĩ chỉ quản lý danh mục thuốc + xem đơn thuốc.
- Thanh toán production / tiền thật; tiền mặt / chuyển khoản thủ công — **chỉ** PayPal Sandbox + Visa qua PayPal.

> **Ghi chú về Frontend:** đề đặt "Frontend (Blade/Vue/React)" trong nhóm giới hạn phạm vi ở phần *Mô tả hệ thống* (không phải một mục "cấm" riêng). Trong phạm vi thực tập này ta **chủ động làm thêm** một frontend Blade tối giản (cùng repo) tiêu thụ API — coi là phần mở rộng tự chọn, không thay thế yêu cầu API-first. Xem [mục 14](#14-frontend-blade--vite).

---

## 2. Stack công nghệ

| Thành phần | Lựa chọn |
|---|---|
| Ngôn ngữ | PHP 8.3 |
| Framework | Laravel 13 |
| Auth | Laravel Sanctum (API token) |
| Database | PostgreSQL 16 |
| Container | Docker Engine + Docker Compose plugin |
| OS mục tiêu | Ubuntu 24 (chạy được cả trên Windows/macOS qua Docker) |
| Thanh toán | PayPal REST API (Sandbox) + Visa qua PayPal |
| Frontend | Blade + Vite + Alpine.js (cùng repo, gọi API bằng Bearer token) |
| Test | PHPUnit (feature tests) |

Phiên bản Docker/Compose thực tế dùng để phát triển: `docker --version` / `docker compose version` — điền giá trị máy bạn vào đây khi hoàn thành T1.1.

---

## 3. Kiến trúc đã chọn (B — Controller + Service)

Dự án chọn **phương án B: Controller + Service** (không dùng Repository). Lý do:

- Đề chỉ yêu cầu **cấm Fat Controller**; B đã tách toàn bộ business logic ra khỏi Controller.
- Phạm vi 14 resource / 4 tuần: B gọn hơn ~50% số file so với C (+Repository) mà vẫn rõ ràng.
- Feature test của đề chạy **DB thật trong Docker**, nên lợi ích "mock repository" của phương án C gần như không dùng tới.

### Trách nhiệm từng lớp

```
HTTP Request
   │
   ▼
Route (routes/api.php)
   │  middleware: auth:sanctum + EnsurePermission (map Controller@action → PERMISSION)
   ▼
Controller  ──► validate qua Form Request (App\Http\Requests\*)
   │            (Controller mỏng: nhận request đã validate, gọi Service, trả Resource)
   ▼
Service (App\Services\*)  ──► business rule + DB::transaction + Eloquent
   │
   ▼
Eloquent Model (App\Models\*)  ──► PostgreSQL
   │
   ▼
API Resource (App\Http\Resources\*)  ──► envelope JSON chuẩn
```

### Nguyên tắc
- **Controller** không chứa business logic; chỉ điều phối: `FormRequest → Service → Resource`.
- **Service** giữ toàn bộ quy tắc nghiệp vụ, transaction, và là nơi ném `ValidationException` cho lỗi business (→ 422).
- **Form Request** cho mọi API ghi; **API Resource** cho mọi response.
- **EnsurePermission middleware** kiểm tra quyền dựa trên Controller@action, không hard-code tên role trong method.

Chi tiết cách viết từng lớp: [skills/backend.md](skills/backend.md).

---

## 4. Cài đặt & chạy bằng Docker

Yêu cầu: đã cài **Docker Engine + Docker Compose plugin**.

```bash
git clone <repo> && cd clinic
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

- API base URL: `http://localhost:8000/api/...`
- DB Postgres expose ra host ở cổng `5433` (map `5433:5432`) để tránh đụng Postgres local; bên trong network Docker service tên là `db:5432`.
- Persist dữ liệu Postgres qua Docker volume `clinic_postgres_data`.

Các lệnh thường dùng:

```bash
docker compose exec app php artisan migrate:fresh --seed   # reset schema + seed lại
docker compose exec app php artisan test                   # chạy feature tests
docker compose exec app php artisan tinker                 # REPL
docker compose logs -f app                                 # xem log
docker compose down                                        # dừng (giữ volume)
docker compose down -v                                     # dừng + xóa volume DB
```

Chi tiết Dockerfile/compose/healthcheck: [skills/docker.md](skills/docker.md).

---

## 5. Biến môi trường (.env)

Commit **`.env.example`** (placeholder), **không commit `.env`**. Các biến chính:

| Biến | Giá trị mẫu | Ý nghĩa |
|---|---|---|
| `APP_NAME` | Clinic | Tên ứng dụng |
| `APP_URL` | http://localhost:8000 | URL gốc |
| `DB_CONNECTION` | pgsql | Dùng PostgreSQL |
| `DB_HOST` | db | Tên service Postgres trong Docker network |
| `DB_PORT` | 5432 | Cổng Postgres **bên trong** network |
| `DB_DATABASE` | clinic | Tên database |
| `DB_USERNAME` | clinic | User Postgres |
| `DB_PASSWORD` | clinic_password | Mật khẩu Postgres |
| `EXAMINATION_FEE` | 100000 | Phí khám cộng vào hóa đơn (đơn vị theo README; xem lưu ý tiền tệ mục 13) |
| `PAYPAL_MODE` | sandbox | **Bắt buộc** sandbox — không dùng live |
| `PAYPAL_CLIENT_ID` | your-sandbox-client-id | Placeholder trong `.env.example` |
| `PAYPAL_CLIENT_SECRET` | your-sandbox-client-secret | **Không commit secret thật** |
| `PAYPAL_CURRENCY` | USD | Tiền tệ order PayPal |

> **Lưu ý tiền tệ:** `EXAMINATION_FEE` và giá thuốc lưu theo đơn vị nội bộ (ví dụ VND), nhưng PayPal order tính bằng `PAYPAL_CURRENCY` (USD). README phải nêu rõ quy ước quy đổi hoặc dùng cùng đơn vị nhất quán khi demo. Quyết định 1 cách và giữ nguyên.

---

## 6. Tài khoản được seed sẵn

Sau `migrate --seed`:

| Role | Email (mặc định) | Mật khẩu | Ghi chú |
|---|---|---|---|
| ADMIN | `admin@clinic.test` | (ghi trong seeder/README) | Seed đầu tiên, full quyền |
| RECEPTIONIST | `receptionist@clinic.test` | … | User mẫu |
| DOCTOR | `doctor@clinic.test` | … | Có hồ sơ `doctors` gắn kèm |
| PHARMACIST | `pharmacist@clinic.test` | … | User mẫu |
| CASHIER | `cashier@clinic.test` | … | User mẫu |

Kèm dữ liệu demo: 2–3 chuyên khoa, vài bác sĩ, vài bệnh nhân, vài thuốc, và (tuỳ chọn) một chuỗi `appointment → examination → prescription → invoice → payment` mẫu.

> Không có `/api/register` công khai. Đăng nhập ngay bằng ADMIN sau khi seed.

---

## 7. RBAC Global

### 3 bảng catalog + `users.role_id`

| Bảng | Cột chính | Ghi chú |
|---|---|---|
| `roles` | id, name, display_name | ADMIN, RECEPTIONIST, DOCTOR, PHARMACIST, CASHIER |
| `permissions` | id, name, display_name | `name = CONTROLLER.ACTION` (UNIQUE) |
| `role_permissions` | role_id, permission_id | `UNIQUE(role_id, permission_id)` |
| `users.role_id` | FK → roles.id | Mỗi user đúng 1 role (không phải bảng catalog thứ 4) |

### Quy ước map action → permission

| Controller action | Permission suffix |
|---|---|
| `index` | `FINDALL` |
| `store` | `CREATE` |
| `show` | `FINDONE` |
| `update` | `UPDATE` |
| `destroy` | `DELETE` |
| `updateStatus` | `UPDATESTATUS` |
| `addItem` | `ADDITEM` |
| `updateItem` | `UPDATEITEM` |
| `removeItem` | `REMOVEITEM` |
| `adjustStock` | `ADJUSTSTOCK` |
| `capture` | `CAPTURE` |

Middleware `EnsurePermission` lấy Controller class + method hiện tại, ghép thành `PERMISSION.NAME`, kiểm tra role của user có permission đó không; thiếu → **403**.

### Quy tắc quan trọng
- **Thêm permission mới = data migration idempotent**, không chỉ sửa Seeder (để môi trường đã seed vẫn nhận permission mới khi `migrate`).
- **Không hard-code tên role** trong từng method Controller — mọi thứ đi qua map permission.
- **Bảo vệ ADMIN cuối cùng:** không hạ role / không deactivate ADMIN cuối cùng còn hoạt động trong hệ thống → **422**.
- `AuthController@login` public; `logout`, `me` chỉ cần Sanctum (không cần permission).

### Ma trận Role → Permission (tổng ~52 permission)

`x` = role có quyền. ADMIN có **tất cả** (cột ADMIN mặc định `x` toàn bộ).

| Permission | ADMIN | RECEPTIONIST | DOCTOR | PHARMACIST | CASHIER |
|---|:-:|:-:|:-:|:-:|:-:|
| USERS.FINDALL | x | | | | |
| USERS.CREATE | x | | | | |
| USERS.FINDONE | x | | | | |
| USERS.UPDATE | x | | | | |
| USERS.DELETE | x | | | | |
| USERS.UPDATESTATUS | x | | | | |
| ROLES.FINDALL | x | | | | |
| SPECIALTIES.FINDALL | x | x | x | | |
| SPECIALTIES.CREATE | x | | | | |
| SPECIALTIES.FINDONE | x | x | x | | |
| SPECIALTIES.UPDATE | x | | | | |
| SPECIALTIES.DELETE | x | | | | |
| DOCTORS.FINDALL | x | x | x | | |
| DOCTORS.CREATE | x | | | | |
| DOCTORS.FINDONE | x | x | x | | |
| DOCTORS.UPDATE | x | | | | |
| DOCTORS.DELETE | x | | | | |
| PATIENTS.FINDALL | x | x | x | | x |
| PATIENTS.CREATE | x | x | | | |
| PATIENTS.FINDONE | x | x | x | | x |
| PATIENTS.UPDATE | x | x | | | |
| PATIENTS.DELETE | x | | | | |
| APPOINTMENTS.FINDALL | x | x | x | | x |
| APPOINTMENTS.CREATE | x | x | | | |
| APPOINTMENTS.FINDONE | x | x | x | | x |
| APPOINTMENTS.UPDATE | x | x | | | |
| APPOINTMENTS.UPDATESTATUS | x | x | | | |
| EXAMINATIONS.FINDALL | x | | x | | x |
| EXAMINATIONS.CREATE | x | | x | | |
| EXAMINATIONS.FINDONE | x | | x | | x |
| EXAMINATIONS.UPDATE | x | | x | | |
| MEDICINES.FINDALL | x | | x | x | |
| MEDICINES.CREATE | x | | | x | |
| MEDICINES.FINDONE | x | | x | x | |
| MEDICINES.UPDATE | x | | | x | |
| MEDICINES.DELETE | x | | | x | |
| MEDICINES.ADJUSTSTOCK | x | | | x | |
| PRESCRIPTIONS.FINDALL | x | | x | x | |
| PRESCRIPTIONS.CREATE | x | | x | | |
| PRESCRIPTIONS.FINDONE | x | | x | x | |
| PRESCRIPTIONS.UPDATE | x | | x | | |
| PRESCRIPTIONS.ADDITEM | x | | x | | |
| PRESCRIPTIONS.UPDATEITEM | x | | x | | |
| PRESCRIPTIONS.REMOVEITEM | x | | x | | |
| INVOICES.FINDALL | x | | | | x |
| INVOICES.CREATE | x | | | | x |
| INVOICES.FINDONE | x | | | | x |
| INVOICES.UPDATE | x | | | | x |
| INVOICES.UPDATESTATUS | x | | | | x |
| PAYMENTS.FINDALL | x | | | | x |
| PAYMENTS.CREATE | x | | | | x |
| PAYMENTS.CAPTURE | x | | | | x |
| STATS.SHOW | x | | | | |

> Ma trận trên là nguồn để viết seeder `role_permissions`. ADMIN được gán toàn bộ permission.

### Mô tả role
- **ADMIN** — full mọi permission: quản lý user/role, danh mục, toàn bộ nghiệp vụ.
- **RECEPTIONIST** — quản lý bệnh nhân (không xóa), đặt/sửa/xác nhận/hủy lịch; chỉ xem bác sĩ/chuyên khoa; không đụng phiếu khám, đơn thuốc, thuốc (ghi), hóa đơn, thanh toán.
- **DOCTOR** — xem lịch khám, tạo/sửa phiếu khám, tạo đơn thuốc + thêm/sửa/xóa thuốc trong đơn; xem bệnh nhân/thuốc; không tạo/sửa hóa đơn hay thanh toán.
- **PHARMACIST** — CRUD danh mục thuốc + điều chỉnh tồn kho; chỉ xem đơn thuốc (không sửa); không đụng bệnh nhân/lịch khám/hóa đơn.
- **CASHIER** — tạo/sửa/hủy hóa đơn; khởi tạo & capture thanh toán PayPal/Visa; xem phiếu khám để lập hóa đơn; read-only bệnh nhân và lịch khám.

Chi tiết seeder/migration RBAC: [skills/database.md](skills/database.md) và [skills/backend.md](skills/backend.md).

---

## 8. Mô hình dữ liệu (Database)

14 bảng nghiệp vụ + `activity_logs` (hỗ trợ, không tính vào 14).

| # | Bảng (English) | Tên tiếng Việt | Vai trò |
|---|---|---|---|
| 1 | `users` | Người dùng | Tài khoản + role_id |
| 2 | `roles` | Vai trò | Catalog role |
| 3 | `permissions` | Quyền | Catalog `CONTROLLER.ACTION` |
| 4 | `role_permissions` | Vai trò–Quyền | Map role ↔ permission |
| 5 | `specialties` | Chuyên khoa | Danh mục chuyên khoa |
| 6 | `doctors` | Bác sĩ | Hồ sơ bác sĩ (user_id 1-1, specialty_id) |
| 7 | `patients` | Bệnh nhân | Hồ sơ bệnh nhân (soft delete) |
| 8 | `appointments` | Lịch khám | Lịch hẹn khám |
| 9 | `examinations` | Phiếu khám | Phiếu khám bệnh |
| 10 | `medicines` | Thuốc | Danh mục thuốc + stock (soft delete) |
| 11 | `prescriptions` | Đơn thuốc | Đơn thuốc theo phiếu khám |
| 12 | `prescription_items` | Chi tiết đơn thuốc | Dòng thuốc trong đơn |
| 13 | `invoices` | Hóa đơn | Hóa đơn theo phiếu khám |
| 14 | `payments` | Thanh toán | Thanh toán PayPal/Visa |
| + | `activity_logs` | Nhật ký hoạt động | Audit log (Event/Observer) |

### Sơ đồ quan hệ (ERD rút gọn)

Mỗi entity ghi kèm **tên tiếng Anh (Tên tiếng Việt)** trong nhãn.

```mermaid
erDiagram
    roles["roles (Vai trò)"] ||--o{ users["users (Người dùng)"] : "phân vai"
    roles ||--o{ role_permissions["role_permissions (Vai trò–Quyền)"] : "gán"
    permissions["permissions (Quyền)"] ||--o{ role_permissions : "gán"
    users ||--o| doctors["doctors (Bác sĩ)"] : "1-1"
    specialties["specialties (Chuyên khoa)"] ||--o{ doctors : "thuộc"
    doctors ||--o{ appointments["appointments (Lịch khám)"] : "khám"
    patients["patients (Bệnh nhân)"] ||--o{ appointments : "đặt"
    appointments ||--o| examinations["examinations (Phiếu khám)"] : "1-1"
    doctors ||--o{ examinations : "lập"
    patients ||--o{ examinations : "được khám"
    examinations ||--o| prescriptions["prescriptions (Đơn thuốc)"] : "1-1"
    doctors ||--o{ prescriptions : "kê"
    prescriptions ||--o{ prescription_items["prescription_items (Chi tiết đơn)"] : "gồm"
    medicines["medicines (Thuốc)"] ||--o{ prescription_items : "được kê"
    examinations ||--o| invoices["invoices (Hóa đơn)"] : "1-1"
    invoices ||--o{ payments["payments (Thanh toán)"] : "thu"
    users ||--o{ activity_logs["activity_logs (Nhật ký)"] : "ghi"
```

### Ràng buộc bắt buộc (Postgres)

- **UNIQUE:** `roles.name`, `permissions.name`, `role_permissions(role_id, permission_id)`, `users.email`, `doctors.user_id`, `patients.code`, `medicines.code`, `examinations.appointment_id`, `prescriptions.examination_id`, `prescription_items(prescription_id, medicine_id)`, `invoices.examination_id`, `invoices.invoice_code`.
- **CHECK/ENUM:** `patients.gender` (male/female/other), `appointments.status` (scheduled/confirmed/cancelled/completed), `invoices.status` (unpaid/paid/cancelled), `payments.method` (paypal/visa), `payments.status` (pending/completed/failed/cancelled).
- **CHECK số:** `medicines.stock >= 0`, `prescription_items.quantity > 0`, `payments.amount > 0`.
- **Soft delete:** `patients`, `medicines` (`deleted_at`).
- **FK onDelete:** khuyến nghị `restrict` cho bảng lịch sử tài chính/y tế (`examinations`, `invoices`, `payments`) để không mất dữ liệu khi xóa nhầm bản ghi cha.
- **Index (≥6 có chủ đích):** `appointments(doctor_id, scheduled_at)`, `appointments(patient_id)`, `appointments(status)`, `invoices(status)`, `payments(invoice_id)`, `activity_logs(subject_type, subject_id)`.
- `activity_logs.meta` kiểu **JSONB**.

Chi tiết cột từng bảng + ví dụ migration: [skills/database.md](skills/database.md).

---

## 9. Danh sách API đầy đủ

Tất cả prefix `/api`. Cột Permission ghi `Controller@action — PERMISSION`.

### Auth
| Method | Path | Permission | Memo |
|---|---|---|---|
| POST | `/api/login` | Không | Đăng nhập bằng email/mật khẩu, trả về Bearer token; sai thông tin hoặc tài khoản bị khóa → 401 |
| POST | `/api/logout` | Auth (Sanctum) | Đăng xuất, thu hồi token hiện tại |
| GET | `/api/me` | Auth (Sanctum) | Lấy thông tin user đang đăng nhập kèm role và danh sách quyền |

### Users & Roles
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/users` | UserController@index — USERS.FINDALL | Danh sách người dùng (lọc theo role) |
| POST | `/api/users` | UserController@store — USERS.CREATE | Tạo người dùng mới kèm gán role_id |
| GET | `/api/users/{id}` | UserController@show — USERS.FINDONE | Chi tiết một người dùng |
| PUT/PATCH | `/api/users/{id}` | UserController@update — USERS.UPDATE | Sửa thông tin / đổi role (chặn ADMIN cuối cùng) |
| DELETE | `/api/users/{id}` | UserController@destroy — USERS.DELETE | Vô hiệu hóa người dùng (is_active=false, chặn ADMIN cuối) |
| PATCH | `/api/users/{id}/status` | UserController@updateStatus — USERS.UPDATESTATUS | Kích hoạt lại / khóa người dùng |
| GET | `/api/roles` | RoleController@index — ROLES.FINDALL | Danh sách catalog role |

### Specialties
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/specialties` | SpecialtyController@index — SPECIALTIES.FINDALL | Danh sách chuyên khoa |
| POST | `/api/specialties` | SpecialtyController@store — SPECIALTIES.CREATE | Tạo chuyên khoa mới |
| GET | `/api/specialties/{id}` | SpecialtyController@show — SPECIALTIES.FINDONE | Chi tiết một chuyên khoa |
| PUT/PATCH | `/api/specialties/{id}` | SpecialtyController@update — SPECIALTIES.UPDATE | Sửa thông tin chuyên khoa |
| DELETE | `/api/specialties/{id}` | SpecialtyController@destroy — SPECIALTIES.DELETE | Xóa chuyên khoa |

### Doctors
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/doctors` | DoctorController@index — DOCTORS.FINDALL | Danh sách bác sĩ (lọc theo specialty_id) |
| POST | `/api/doctors` | DoctorController@store — DOCTORS.CREATE | Tạo hồ sơ bác sĩ (user phải role DOCTOR, 1-1) |
| GET | `/api/doctors/{id}` | DoctorController@show — DOCTORS.FINDONE | Chi tiết một bác sĩ |
| PUT/PATCH | `/api/doctors/{id}` | DoctorController@update — DOCTORS.UPDATE | Sửa hồ sơ bác sĩ |
| DELETE | `/api/doctors/{id}` | DoctorController@destroy — DOCTORS.DELETE | Xóa hồ sơ bác sĩ |

### Patients
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/patients` | PatientController@index — PATIENTS.FINDALL | Danh sách bệnh nhân, tìm kiếm theo `q` (tên/SĐT/mã) + phân trang |
| POST | `/api/patients` | PatientController@store — PATIENTS.CREATE | Tạo hồ sơ bệnh nhân (tự sinh mã) |
| GET | `/api/patients/{id}` | PatientController@show — PATIENTS.FINDONE | Chi tiết một bệnh nhân |
| PUT/PATCH | `/api/patients/{id}` | PatientController@update — PATIENTS.UPDATE | Sửa hồ sơ bệnh nhân |
| DELETE | `/api/patients/{id}` | PatientController@destroy — PATIENTS.DELETE | Xóa mềm (soft delete) bệnh nhân |

### Appointments
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/appointments` | AppointmentController@index — APPOINTMENTS.FINDALL | Danh sách lịch khám (lọc doctor_id, patient_id, status, ngày) |
| POST | `/api/appointments` | AppointmentController@store — APPOINTMENTS.CREATE | Đặt lịch khám (status mặc định scheduled, chống trùng giờ bác sĩ) |
| GET | `/api/appointments/{id}` | AppointmentController@show — APPOINTMENTS.FINDONE | Chi tiết một lịch khám |
| PUT/PATCH | `/api/appointments/{id}` | AppointmentController@update — APPOINTMENTS.UPDATE | Sửa giờ hẹn / lý do (chỉ khi còn scheduled) |
| PATCH | `/api/appointments/{id}/status` | AppointmentController@updateStatus — APPOINTMENTS.UPDATESTATUS | Xác nhận / hủy / hoàn tất lịch theo máy trạng thái |

### Examinations
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/examinations` | ExaminationController@index — EXAMINATIONS.FINDALL | Danh sách phiếu khám (lọc doctor_id, patient_id) |
| POST | `/api/examinations` | ExaminationController@store — EXAMINATIONS.CREATE | Tạo phiếu khám từ lịch confirmed (transaction: lịch → completed) |
| GET | `/api/examinations/{id}` | ExaminationController@show — EXAMINATIONS.FINDONE | Chi tiết phiếu khám (kèm đơn thuốc/hóa đơn nếu có) |
| PUT/PATCH | `/api/examinations/{id}` | ExaminationController@update — EXAMINATIONS.UPDATE | Sửa chẩn đoán / ghi chú |

### Medicines
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/medicines` | MedicineController@index — MEDICINES.FINDALL | Danh sách thuốc (lọc còn/hết hàng) |
| POST | `/api/medicines` | MedicineController@store — MEDICINES.CREATE | Tạo thuốc mới |
| GET | `/api/medicines/{id}` | MedicineController@show — MEDICINES.FINDONE | Chi tiết một thuốc |
| PUT/PATCH | `/api/medicines/{id}` | MedicineController@update — MEDICINES.UPDATE | Sửa thông tin / giá thuốc |
| DELETE | `/api/medicines/{id}` | MedicineController@destroy — MEDICINES.DELETE | Xóa mềm (soft delete) thuốc |
| PATCH | `/api/medicines/{id}/stock` | MedicineController@adjustStock — MEDICINES.ADJUSTSTOCK | Nhập / điều chỉnh tồn kho `{quantity, note}` (chặn âm) |

### Prescriptions
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/prescriptions` | PrescriptionController@index — PRESCRIPTIONS.FINDALL | Danh sách đơn thuốc (lọc doctor_id) |
| POST | `/api/prescriptions` | PrescriptionController@store — PRESCRIPTIONS.CREATE | Tạo đơn từ phiếu khám kèm `items` (transaction trừ kho) |
| GET | `/api/prescriptions/{id}` | PrescriptionController@show — PRESCRIPTIONS.FINDONE | Chi tiết đơn thuốc (kèm các dòng thuốc) |
| PUT/PATCH | `/api/prescriptions/{id}` | PrescriptionController@update — PRESCRIPTIONS.UPDATE | Sửa ghi chú đơn thuốc |
| POST | `/api/prescriptions/{id}/items` | PrescriptionController@addItem — PRESCRIPTIONS.ADDITEM | Thêm thuốc vào đơn (trừ kho, chặn trùng thuốc) |
| PUT/PATCH | `/api/prescriptions/{id}/items/{itemId}` | PrescriptionController@updateItem — PRESCRIPTIONS.UPDATEITEM | Sửa số lượng / liều dùng (điều chỉnh kho theo delta) |
| DELETE | `/api/prescriptions/{id}/items/{itemId}` | PrescriptionController@removeItem — PRESCRIPTIONS.REMOVEITEM | Xóa thuốc khỏi đơn (hoàn kho) |

### Invoices
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/invoices` | InvoiceController@index — INVOICES.FINDALL | Danh sách hóa đơn (lọc theo status) |
| POST | `/api/invoices` | InvoiceController@store — INVOICES.CREATE | Tạo hóa đơn từ phiếu khám, tự tính tiền thuốc + phí khám `{examination_id, consultation_fee, discount}` |
| GET | `/api/invoices/{id}` | InvoiceController@show — INVOICES.FINDONE | Chi tiết hóa đơn (kèm các thanh toán) |
| PUT/PATCH | `/api/invoices/{id}` | InvoiceController@update — INVOICES.UPDATE | Sửa discount (chỉ khi unpaid & chưa có payment completed) |
| PATCH | `/api/invoices/{id}/status` | InvoiceController@updateStatus — INVOICES.UPDATESTATUS | Hủy hóa đơn (cancelled, khi còn unpaid) |

### Payments (PayPal/Visa)
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/invoices/{invoiceId}/payments` | PaymentController@index — PAYMENTS.FINDALL | Danh sách thanh toán theo hóa đơn |
| POST | `/api/invoices/{invoiceId}/payments` | PaymentController@store — PAYMENTS.CREATE | Tạo lệnh PayPal Order `{amount, method: paypal\|visa, note?}` → trả order_id, approval_url, payment pending (amount ≤ số còn lại) |
| POST | `/api/payments/{id}/capture` | PaymentController@capture — PAYMENTS.CAPTURE | Capture order sau khi khách duyệt → completed + cập nhật hóa đơn (đủ tiền → paid) |
| POST | `/api/payments/paypal/webhook` | Public + verify chữ ký PayPal | (Khuyến khích) Webhook PayPal cập nhật trạng thái — không dùng user token |

### Stats
| Method | Path | Permission | Memo |
|---|---|---|---|
| GET | `/api/stats` | StatsController@show — STATS.SHOW | Số liệu tổng quan bằng aggregate: số bệnh nhân, lịch hôm nay, doanh thu tháng, thuốc sắp hết |

Export Postman: `postman_collection.json` (bắt buộc nộp; gồm cả case 401/403/404/422 và luồng chính đầy đủ, kèm capture PayPal).

---

## 10. Chuẩn Response & HTTP status

### Envelope JSON

Thành công:
```json
{ "success": true, "message": "...", "data": {} }
```
List có phân trang — thêm `meta`:
```json
{ "success": true, "message": "...", "data": [], "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
```
Thất bại:
```json
{ "success": false, "message": "...", "errors": {} }
```
Với **422**, `errors` bắt buộc theo field: `{ "email": ["The email has already been taken."] }`.

### Ma trận HTTP status

| HTTP | Khi nào |
|---|---|
| 200 | OK — đọc / cập nhật / xóa / capture / logout / đổi status / adjust stock |
| 201 | Created — tạo mới (user, patient, doctor, appointment, examination, prescription, invoice, payment) |
| 401 | Chưa auth / token sai / login sai / account `is_active=false` |
| 403 | Đã login nhưng thiếu permission RBAC (hoặc vi phạm quy tắc đặc biệt) |
| 404 | Resource không tồn tại |
| 422 | Validation (Form Request) hoặc business rule (trùng lịch, thiếu kho, vượt tiền, hóa đơn đã có payment…) — **bắt buộc** có `errors` theo field |
| 500 | Lỗi server không mong muốn (không cố ý trả trong case nghiệp vụ) |

Exception Handler phải trả **JSON** cho API (không trả HTML). Cách cấu hình: [skills/backend.md](skills/backend.md).

---

## 11. Luồng nghiệp vụ chính

Chuỗi nghiệp vụ theo thời gian thực tế:

```
Đăng nhập (Sanctum)
   │
ADMIN tạo nhân sự (users + doctors) ── PHARMACIST tạo danh mục medicines ── danh mục specialties
   │
RECEPTIONIST: tạo/tra cứu patients → đặt appointments (scheduled) → confirmed
   │
DOCTOR: từ appointment confirmed → tạo examinations (transaction: appointment → completed)
   │         └─► kê prescriptions + prescription_items (transaction: trừ kho medicines)
   │
CASHIER: tạo invoices từ examination (subtotal = tiền thuốc + phí khám) → status unpaid
   │         └─► tạo payment PayPal Order (pending) → capture (completed)
   │                └─► transaction: tổng completed == invoice.total ⇒ invoice paid
   │
Event/Observer ghi activity_logs ở mỗi mốc quan trọng
   │
STATS.SHOW: aggregate SQL cho dashboard
```

### Quy tắc nghiệp vụ then chốt
- **Máy trạng thái lịch khám:** `scheduled → confirmed → completed`; `scheduled/confirmed → cancelled`. Transition trái quy tắc (vd `cancelled → completed`) → 422.
- **Chống trùng lịch bác sĩ:** cùng bác sĩ không có 2 appointment chồng khung giờ (trừ `cancelled`) → 422.
- **Phiếu khám:** chỉ tạo từ appointment `confirmed`; `patient_id`/`doctor_id` lấy từ lịch (không cho lệch); 1 appointment ↔ tối đa 1 examination; tạo phiếu xong cập nhật appointment → `completed` **trong cùng transaction**. Không tạo từ lịch `cancelled`/`completed`/đã có phiếu → 422.
- **Đơn thuốc & kho:** thuốc `is_active=false` không cho kê; kiểm tra `stock >= quantity` (dùng `lockForUpdate`), thiếu → 422 + rollback; xóa/sửa item hoàn/điều chỉnh kho tương ứng; không trùng thuốc trên cùng đơn.
- **Hóa đơn:** 1 examination ↔ 1 invoice; `subtotal = Σ(quantity × price) + EXAMINATION_FEE`; `total = subtotal − discount`; chỉ sửa discount / hủy khi `unpaid` và chưa có payment `completed`, ngược lại → 422.
- **Thanh toán:** `amount ≤ số còn lại`; capture đủ tiền ⇒ invoice `paid`; fail ⇒ payment `failed`, invoice giữ `unpaid`.
- **ADMIN cuối cùng:** không hạ role / deactivate ADMIN cuối → 422.

---

## 12. Transaction bắt buộc

Dùng `DB::transaction` cho các nghiệp vụ đa bước sau (rollback nếu bất kỳ bước nào lỗi):

1. **Tạo examination** + cập nhật `appointment.status = completed`.
2. **Tạo/sửa/xóa `prescription_items`** + trừ/hoàn `medicines.stock` (kèm `lockForUpdate` chống race-condition khi trừ kho đồng thời).
3. **Tạo/capture payment** + cập nhật `invoice.status` (`paid` khi tổng completed đạt `total`).

---

## 13. Tích hợp PayPal Sandbox + Visa

**Bắt buộc `PAYPAL_MODE=sandbox`. Không dùng live client secret. Không lưu số thẻ Visa trong DB.**

### Luồng
1. CASHIER gọi `POST /api/invoices/{id}/payments` với `{ amount (≤ số còn lại), method: paypal|visa, note? }`.
2. Backend gọi PayPal REST (Sandbox) tạo **Order**; lưu `payments` `status=pending`, `provider=paypal`, `provider_order_id`; trả `approval_url` / `order_id`.
3. Khách duyệt trên PayPal (ví PayPal cho `method=paypal`, hoặc nhập thẻ Visa test cho `method=visa` qua card fields/checkout của PayPal).
4. CASHIER gọi `POST /api/payments/{id}/capture` → backend **capture** Order trên Sandbox.
   - Thành công: `payments.status=completed`, lưu `provider_capture_id`, `paid_at`. Trong transaction: cộng dồn payment `completed`; nếu đạt `invoice.total` → `invoice.status=paid`.
   - Thất bại/hủy: `payments.status=failed|cancelled`, invoice giữ `unpaid`.

### Hướng dẫn tạo app PayPal Developer (điền khi làm T3.13–T3.15)
1. Đăng nhập <https://developer.paypal.com> → **Apps & Credentials** → **Sandbox** → **Create App**.
2. Lấy **Client ID** + **Secret**, đặt vào `.env` (`PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`). `.env.example` chỉ để placeholder.
3. Tạo **Sandbox accounts** (business + personal) trong mục **Sandbox → Accounts** để test ví PayPal.
4. **Thẻ Visa test:** dùng số thẻ test do PayPal cung cấp trong tài liệu Sandbox (không dùng thẻ thật).

Chi tiết code service PayPal (tạo order/capture, token OAuth2): [skills/backend.md](skills/backend.md).

---

## 14. Frontend (Blade + Vite)

Frontend nằm **cùng repo Laravel**, là phần mở rộng tiêu thụ API:

- Blade render "vỏ" trang; **Vite + Alpine.js** gọi `/api/*` bằng `fetch` với **Bearer token** lưu ở `localStorage`.
- Đăng nhập qua `POST /api/login`, lưu token, gắn `Authorization: Bearer {token}` cho mọi request tiếp theo.
- UI hiển thị/ẩn theo permission trả về từ `/api/me` (khớp với RBAC backend; backend vẫn là nơi enforce thật).
- Không tách source, không CORS, không thêm container.

Chi tiết cấu trúc trang, gọi API, xử lý envelope/lỗi: [skills/frontend.md](skills/frontend.md).

---

## 15. Kiểm thử (Feature tests)

Chạy: `docker compose exec app php artisan test`.

Tối thiểu phải cover:
- Login / Logout / Me (sai password → 401; account khóa → 401).
- Tạo user (ADMIN) + tạo doctor profile cho user role DOCTOR.
- CRUD patient.
- Luồng `appointment → examination → prescription (kiểm tra trừ kho) → invoice → payment`.
- Các case RBAC: role thiếu permission → 403; RECEPTIONIST không tạo invoice; DOCTOR không capture payment.
- Filter appointments theo status/doctor.
- Thanh toán: **mock PayPal** (không gọi Sandbox thật trong test).

---

## 16. Quy ước branch & Pull Request

- **Không push thẳng `main`.** `main` là code ổn định để chấm.
- Công thức branch: `task/<user>/<ID>-<slug>` — user handle cố định: **`vuongth`**.
  - Ví dụ: `task/vuongth/T1.5-sanctum-auth`, `task/vuongth/T3.14-paypal-capture`.
  - `slug` kebab-case tiếng Anh, 2–5 từ; cấm dấu/tiếng Việt/khoảng trắng; cấm tên `final/update/new`.
- Luôn tạo branch từ `main` mới nhất (`git checkout main && git pull` trước khi `checkout -b`).

Quy trình PR:
```bash
git checkout main && git pull
git checkout -b task/vuongth/T1.5-sanctum-auth
# code + commit (message rõ, kèm [T1.5])
git push -u origin HEAD
gh pr create   # hoặc GitHub UI
```
- **Title PR:** `[T1.5][vuongth] Sanctum login/logout/me`
- **Body PR:** Summary (làm gì) + Test plan (checklist) + task ID.
- Một PR ≈ một task. Tự review diff + checklist đề bài trước khi request mentor. Squash merge vào `main`, xóa branch remote, rồi `git checkout main && git pull` làm task tiếp theo.

Checklist trước khi xin merge:
- [ ] Branch đúng format `task/vuongth/<ID>-<slug>`
- [ ] Title PR có `[TaskID][vuongth]`
- [ ] Docker/migrate vẫn chạy được nếu đụng infra
- [ ] Không commit `.env` / `PAYPAL_CLIENT_SECRET`
- [ ] API đúng permission `CONTROLLER.ACTION` + HTTP status
- [ ] Có Test plan trong body PR

---

## 17. Tiêu chí chấm điểm

| % | Tiêu chí |
|---:|---|
| 10 | Docker Compose chạy được (migrate + seed) |
| 10 | Auth đầy đủ (login/logout/me), seed ADMIN đầu tiên đúng |
| 20 | RBAC Global (3 bảng catalog, middleware, map role, migration thêm permission, quy tắc ADMIN cuối) |
| 15 | Patients + Appointments (CRUD, đổi status, index) |
| 20 | Examinations + Prescriptions (transaction, trừ/hoàn kho, UNIQUE) |
| 10 | Invoices + PayPal/Visa payments (Sandbox order + capture, pending→completed, invoice paid) |
| 5 | Medicines + Specialties + Doctors (CRUD, constraint) |
| 5 | Postgres: index/constraint/transaction/JSONB + giải thích README |
| 3 | Activity log (Event/Observer) |
| 2 | Feature tests (case RBAC + luồng chính) |
| **100** | **Tổng** |

**Điểm cộng (nâng cao):** STATS dashboard nhiều chỉ số, partial index (vd appointments `status != cancelled`), cache permission theo request, migration thêm permission mới thật (vd `MEDICINES.LOWSTOCK`), queue job giả lập nhắc lịch, OpenAPI/Swagger, rate limiting, `lockForUpdate()` chống race-condition.

---

## 18. Cấu trúc thư mục

```
clinic/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # Controller mỏng theo resource
│   │   ├── Requests/           # Form Request (validation)
│   │   ├── Resources/          # API Resource (envelope data)
│   │   └── Middleware/         # EnsurePermission
│   ├── Services/               # Business logic + transaction (kiến trúc B)
│   ├── Models/                 # Eloquent models
│   ├── Observers/ | Listeners/ # activity_logs
│   └── Providers/
├── database/
│   ├── migrations/             # schema + data migration permission
│   ├── seeders/                # roles/permissions/role_permissions/ADMIN/demo
│   └── factories/
├── routes/
│   ├── api.php                 # toàn bộ endpoint /api
│   └── web.php                 # trang Blade frontend
├── resources/
│   ├── views/                  # Blade
│   └── js/                     # Alpine/Vite gọi API
├── tests/Feature/              # feature tests
├── docs/
│   ├── ke-hoach-chi-tiet.md    # breakdown từng task
│   ├── de-bai-thuc-tap-clinic-api.xlsx
│   └── task.xlsx
├── skills/                     # playbook kỹ thuật theo mảng
│   ├── database.md
│   ├── backend.md
│   ├── frontend.md
│   └── docker.md
├── Dockerfile
├── docker-compose.yml
├── .env.example
├── postman_collection.json     # (tạo ở tuần 4)
└── README.md
```

---

## 19. Tài liệu liên quan

- **[docs/ke-hoach-chi-tiet.md](docs/ke-hoach-chi-tiet.md)** — kế hoạch chi tiết theo tuần/task: nội dung gốc, diễn giải từng bước, mở rộng, checklist hoàn thành + self-test.
- **[skills/database.md](skills/database.md)** — thiết kế schema, migration, constraint, index, transaction, seeder.
- **[skills/backend.md](skills/backend.md)** — Sanctum, RBAC middleware, Controller/Service/FormRequest/Resource, PayPal, activity log, stats.
- **[skills/frontend.md](skills/frontend.md)** — Blade + Vite/Alpine tiêu thụ API bằng Bearer token.
- **[skills/docker.md](skills/docker.md)** — Dockerfile, docker-compose, healthcheck, lệnh vận hành.
