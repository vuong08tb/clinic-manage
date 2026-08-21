# [T4.2] API Stats tổng quan — Kế hoạch triển khai

> **Trạng thái:** Đã triển khai xong. `pint` sạch, `php artisan test` **248/248**
> (10 test cho stats). Số liệu đã đối chiếu khớp với SQL thô trên dữ liệu seed.
> **Tuân thủ:** `skills/backend.md` (mục 1 phân lớp, mục 9 Stats, mục 12 comment),
> `skills/database.md` (mục 8 aggregate, mục 11 timestamptz).

---

## 1. Mục tiêu

`GET /api/stats` trả 4 chỉ số tổng quan phòng khám, tính hoàn toàn bằng **SQL aggregate**.

Yêu cầu gốc (T4.2):

- Tổng bệnh nhân — `COUNT patients`
- Lịch hôm nay — `COUNT appointments` theo ngày hiện tại
- Doanh thu tháng — `SUM total` của hoá đơn `paid` trong tháng
- Thuốc sắp hết — `COUNT medicines` có `stock <= ngưỡng`
- Permission `STATS.SHOW`
- **Tuyệt đối không** load bản ghi về rồi đếm bằng PHP collection

Luồng:

```text
GET /api/stats
  → auth:sanctum → EnsurePermission (STATS.SHOW)
  → StatsController@show
  → StatsService::overview()
  → 4 query aggregate
  → StatsResource → ApiResponse
```

---

## 2. Audit code hiện tại

### Hạ tầng đã dựng sẵn, chỉ thiếu code

| Thành phần | Trạng thái |
|---|---|
| Permission `STATS.SHOW` trong DB | ✅ Đã seed |
| `config/rbac.php`: `'StatsController' => 'STATS'` | ✅ Đã có |
| `config/rbac.php`: override `'StatsController@show' => 'STATS.SHOW'` | ✅ Đã có |
| `app/Http/Controllers/StatsController.php` | ❌ Chưa có |
| `app/Services/StatsService.php` | ❌ Chưa có |
| Route `/api/stats` | ❌ Chưa có |
| `app/Constants/StatsMessage.php` | ❌ Chưa có |
| `app/Http/Resources/StatsResource.php` | ❌ Chưa có |
| `config/clinic.php`: ngưỡng tồn kho thấp | ❌ Chưa có (mới chỉ có `examination_fee`) |

> Việc `config/rbac.php` đã trỏ tới một controller **không tồn tại** là dấu hiệu task này bị bỏ
> dở giữa chừng. Reviewer mở file đó ra sẽ thấy ngay.

### Cần biết trước

- `RoleController` là mẫu controller read-only đơn giản nhất trong dự án — bám theo nó.
- `Invoice` **không có cột `paid_at`**; chỉ có `issued_at`. `Payment` mới có `paid_at`.
- `Medicine::scopeStockStatus()` hiện chỉ hỗ trợ `in_stock` (`stock > 0`) và `out_of_stock`
  (`stock = 0`) — **chưa có khái niệm "sắp hết"**.

---

## 3. Bốn chỉ số — định nghĩa chính xác

| Chỉ số | Query dự kiến | Ghi chú |
|---|---|---|
| `total_patients` | `Patient::count()` | Model có SoftDeletes → global scope tự loại bản ghi đã xoá |
| `appointments_today` | `Appointment::whereDate('scheduled_at', today())->count()` | Xem mục 4.3 về timezone |
| `revenue_this_month` | `Payment::where('status','completed')->whereBetween('paid_at', [đầu tháng, cuối tháng])->sum('amount')` | Tiền **thực thu** — xem mục 4.2 |
| `low_stock_medicines` | `Medicine::where('stock','<=',5)->count()` | Ngưỡng từ config — xem mục 4.1 |

Cả 4 đều là **một câu SQL aggregate** do Postgres tính — Eloquent `count()`/`sum()` sinh
`SELECT count(*)` / `SELECT sum(total)`, không kéo bản ghi về PHP.

### Câu hỏi phụ: có nên gộp 4 query thành 1?

Có thể gộp bằng subquery vào một câu `SELECT`. **Đề xuất: không gộp.**

- 4 câu `COUNT`/`SUM` trên bảng có index chạy ở mức mili-giây; gộp lại không cải thiện đáng kể.
- Gộp làm câu SQL khó đọc, khó test từng chỉ số, và khó thêm chỉ số mới.
- Đề chỉ yêu cầu "mỗi chỉ số là một query aggregate riêng (hoặc gộp hợp lý)".

---

## 4. Ba điểm cần bạn chốt

### 4.1 Ngưỡng "thuốc sắp hết" — ✅ đã chốt: `5`

Đề không cho con số và ghi rõ *"nên đưa vào config để dễ đổi"*. Thêm vào `config/clinic.php`
(file đã tồn tại, đang có `examination_fee`):

```php
'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),
```

**Lưu ý về ngữ nghĩa:** `stock <= 5` **bao gồm cả `stock = 0`** (đã hết hẳn). Khác với widget
"Thuốc hết hàng" trên dashboard hiện tại đang dùng `stock = 0`. Hai con số này sẽ **không bằng
nhau** — đó là chủ đích:

| Tên | Điều kiện | Dùng ở đâu |
|---|---|---|
| Thuốc **hết hàng** | `stock = 0` | Dashboard widget hiện tại |
| Thuốc **sắp hết** | `stock <= 5` | `/api/stats` (task này) |

### 4.2 "Doanh thu tháng" — ✅ đã chốt: theo `paid_at` (tiền thực thu)

**Hệ quả quan trọng:** `paid_at` **chỉ tồn tại trên bảng `payments`**, `invoices` không có cột
này (chỉ có `issued_at`). Nên chọn `paid_at` nghĩa là đổi nguồn dữ liệu:

```php
Payment::query()
    ->where('status', Payment::STATUS_COMPLETED)
    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
    ->sum('amount');
```

| | Cách bị loại | Cách đã chọn |
|---|---|---|
| Nguồn | `SUM(invoices.total)` `status='paid'` theo `issued_at` | `SUM(payments.amount)` `status='completed'` theo `paid_at` |
| Ý nghĩa | Hoá đơn phát hành tháng này và đã thu xong | **Tiền thực nhận trong tháng** |
| Hoá đơn phát hành 31/07 thu tiền 02/08 | Tính vào tháng 7 | Tính vào **tháng 8** ✓ |
| Thanh toán từng phần | Không phản ánh cho tới khi trả đủ | Phản ánh ngay phần đã thu ✓ |

Đây là định nghĩa đúng về dòng tiền, khớp cách kế toán hiểu "doanh thu tháng".

**Vì sao dùng `whereBetween` thay vì `whereMonth` + `whereYear`:** `whereMonth` sinh
`extract(month from paid_at) = ?` — hàm bọc quanh cột nên **không dùng được index** nếu sau này
thêm index cho `paid_at`. `whereBetween` với hai mốc Carbon là so sánh khoảng, index dùng được,
và ranh giới tháng tường minh hơn khi đọc code.

**Giữ tên trường là `revenue_this_month`** để khớp từ vựng của đề ("doanh thu tháng"), nhưng
docblock của `StatsService` phải ghi rõ nó là tiền **thực thu**, không phải tổng hoá đơn.

### 4.3 Timezone — ✅ đã chốt: dùng `UTC` (mốc `today()` / `startOfMonth()`)

Khi khảo sát, tôi phát hiện `skills/docker.md` mục 9 và `skills/database.md` mục 11 vẫn ghi stack
chạy `Asia/Ho_Chi_Minh`, trong khi thực tế đo được:

```text
.env                            → APP_TIMEZONE=UTC, DB_TIMEZONE=UTC
docker-compose.yml (db)         → TZ=UTC, PGTZ=UTC, -c timezone=UTC
Postgres SHOW timezone          → UTC
Laravel config('app.timezone')  → UTC
```

**Hai file skill đã được cập nhật về `UTC`** để khớp thực tế, nên `StatsService` chỉ cần dùng
`today()` / `now()->startOfMonth()` như bình thường — nhất quán với `AppointmentService` vốn
cũng dùng `whereDate`.

**Bẫy đã ghi nhận vào tài liệu:** `today()` cắt ngày theo mốc `00:00 UTC = 07:00 giờ VN`, nên
một lịch hẹn 06:00 sáng giờ VN (lưu là `23:00 UTC hôm trước`) **không được đếm vào hôm nay**.
Chấp nhận được vì toàn hệ thống nhất quán một múi giờ; khi nào cần cắt ngày theo giờ VN thì quy
đổi tường minh ở tầng query, **không** đổi `APP_TIMEZONE`.

---

## 5. Thiết kế

### Response dự kiến

```json
{
    "success": true,
    "message": "Stats retrieved",
    "data": {
        "total_patients": 20,
        "appointments_today": 3,
        "revenue_this_month": "1470000.00",
        "low_stock_medicines": 4
    }
}
```

`revenue_this_month` trả **chuỗi** để giữ đúng 2 chữ số thập phân, nhất quán với cách
`InvoiceResource`/`PaymentResource` đang trả `amount`/`total`.

### Phân lớp (kiến trúc B)

| Lớp | Trách nhiệm |
|---|---|
| `StatsController@show` | Mỏng — gọi service, trả `ApiResponse::resource()` |
| `StatsService::overview()` | 4 query aggregate, đọc ngưỡng từ config |
| `StatsResource` | Định hình output, ép kiểu số |
| `StatsMessage` | Hằng số message |

**Không cần FormRequest** — endpoint không nhận input nào.

### Route

```php
Route::get('/stats', [StatsController::class, 'show']);
```

Đặt trong nhóm `['auth:sanctum', 'permission']` đã có sẵn. `config/rbac.php` đã map
`StatsController@show → STATS.SHOW` nên **không cần sửa RBAC**.

### Ai được xem

Chỉ **ADMIN** — vì `RbacSeeder` cấp toàn bộ permission cho ADMIN, còn 4 role kia có danh sách
liệt kê tường minh không chứa `STATS.SHOW`.

---

## 6. Endpoint này KHÔNG thay thế được widget dashboard

Cần nói rõ để tránh hiểu nhầm về phạm vi.

Dashboard hiện tại gọi **6 request** riêng (`/patients?per_page=1`, `/appointments?...`, …) rồi
đọc `meta.total`. Nhìn qua thì `/api/stats` có vẻ gộp được thành 1 request.

**Nhưng không thay thế được**, vì hai lý do:

1. `/api/stats` yêu cầu `STATS.SHOW` → **chỉ ADMIN gọi được**. Lễ tân, bác sĩ, dược sĩ, thu ngân
   mở dashboard sẽ nhận 403.
2. Dashboard lọc widget **theo từng permission riêng** (`PATIENTS.FINDALL`, `MEDICINES.FINDALL`…)
   — mỗi role thấy một tập widget khác nhau. Một endpoint gộp không làm được việc đó trừ khi
   nó tự lọc theo quyền, mà như vậy thì response có cấu trúc thay đổi tuỳ người gọi.

> Kết luận: `/api/stats` là **màn hình tổng quan cho ADMIN**, không phải bản tối ưu của dashboard.
> Việc gộp request cho dashboard là bài toán khác, không thuộc T4.2.

---

## 7. Chứng minh "không đếm bằng PHP collection"

Đề chấm điểm mục này, nên cần bằng chứng chứ không chỉ nói suông.

**Cách viết đúng:**
```php
Patient::query()->count();                    // SELECT count(*) FROM patients
Invoice::query()->where(...)->sum('total');   // SELECT sum(total) FROM invoices WHERE ...
```

**Cách viết sai cần tránh:**
```php
Patient::all()->count();          // kéo toàn bộ bản ghi về PHP rồi đếm
Invoice::get()->sum('total');     // tương tự
```

**Cách kiểm chứng khi demo:** bật query log rồi gọi endpoint —

```php
DB::enableQueryLog();
// gọi StatsService::overview()
dump(DB::getQueryLog());
```

Phải thấy đúng 4 câu dạng `select count(*)` / `select sum(...)`, **không có** câu
`select * from ...`.

---

## 8. File sẽ tạo/sửa

### Tạo mới (5)

| # | File |
|---|---|
| 1 | `app/Http/Controllers/StatsController.php` |
| 2 | `app/Services/StatsService.php` |
| 3 | `app/Http/Resources/StatsResource.php` |
| 4 | `app/Constants/StatsMessage.php` |
| 5 | `tests/Feature/StatsTest.php` |

### Sửa (2)

| File | Thay đổi |
|---|---|
| `routes/api.php` | Thêm `Route::get('/stats', ...)` + import |
| `config/clinic.php` | Thêm `low_stock_threshold` |

Thêm `LOW_STOCK_THRESHOLD=10` vào `.env` và `.env.example`.

**Không cần** sửa `config/rbac.php` (đã có sẵn) và **không cần** data migration thêm permission
(`STATS.SHOW` đã seed).

---

## 9. Kế hoạch test (`tests/Feature/StatsTest.php`)

| Test | Khẳng định |
|---|---|
| `test_admin_can_view_stats` | 200, đủ 4 key trong `data` |
| `test_total_patients_counts_only_non_deleted` | Soft-deleted patient không được tính |
| `test_appointments_today_excludes_other_days` | Lịch hôm qua/ngày mai không lọt vào |
| `test_revenue_sums_only_completed_payments_of_current_month` | Bỏ qua payment `pending`/`failed` và payment tháng trước |
| `test_revenue_counts_partial_payments` | Thanh toán từng phần vẫn được cộng vào doanh thu |
| `test_low_stock_uses_configured_threshold` | `config(['clinic.low_stock_threshold' => 3])` → kết quả đổi theo |
| `test_low_stock_includes_out_of_stock_medicines` | `stock = 0` cũng được tính |
| `test_stats_use_aggregate_queries_only` | `DB::enableQueryLog()` → mọi câu đều `count(*)`/`sum(`, **không** có `select *` |
| `test_roles_without_permission_cannot_view_stats` | 4 role còn lại đều 403 `Missing permission: STATS.SHOW` |
| `test_unauthenticated_request_is_rejected` | 401 |

`test_stats_use_aggregate_queries_only` là test đặc biệt — nó biến tiêu chí chấm *"không đếm
bằng PHP collection"* thành khẳng định tự động, thay vì để người chấm phải đọc code.

---

## 10. Checklist review trước PR

Theo `skills/backend.md` mục 11 + checklist T4.2:

- [ ] 4 chỉ số đều bằng aggregate, không vòng lặp PHP.
- [ ] Chỉ ADMIN truy cập được (`STATS.SHOW`).
- [ ] Controller mỏng, business nằm trong `StatsService`.
- [ ] Output qua `StatsResource`, envelope qua `ApiResponse`.
- [ ] Ngưỡng tồn kho đọc từ config, không hard-code.
- [ ] Số liệu khớp dữ liệu seed.
- [ ] Comment code **tiếng Anh**, phủ Class/Method/Constructor.
- [ ] `pint` sạch, `php artisan test` xanh.

---

## 11. Ba điểm đã chốt

| # | Vấn đề | Quyết định |
|---|---|---|
| 1 | Ngưỡng "thuốc sắp hết" | **`stock <= 5`**, đọc từ `config('clinic.low_stock_threshold')` — mục 4.1 |
| 2 | "Doanh thu tháng" | **Theo `payments.paid_at`** — tiền thực thu, không phải tổng hoá đơn — mục 4.2 |
| 3 | Timezone | **`UTC`** — hai file skill đã cập nhật cho khớp thực tế — mục 4.3 |

Kế hoạch đã sẵn sàng để triển khai.
