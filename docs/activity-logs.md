# Activity Logs — Kế hoạch triển khai

> **Trạng thái:** Đã triển khai xong M1 → M6. `pint` sạch, `php artisan test` 238/238.
> M7 (API đọc log) tách sang task riêng — xem mục 10.
> **Tuân thủ:** `skills/backend.md` (mục 8 Activity log, mục 12 Comment code),
> `skills/database.md` (mục 3 JSONB, mục 4 ma trận constraint, mục 11 timestamptz).

---

## 1. Mục tiêu

Ghi lại vết (audit trail) cho các hành động nghiệp vụ quan trọng, phục vụ truy vết
"ai đã làm gì, trên đối tượng nào, thay đổi từ giá trị nào sang giá trị nào".

Yêu cầu gốc:

- Migration `activity_logs`: `user_id` nullable, `subject_type`/`subject_id`, `action`,
  `meta` JSONB, index `(subject_type, subject_id)`.
- Observer/Event ghi log cho: **user**, **appointment status**, **examination**,
  **prescription/kho**, **invoice**, **payment**.
- `meta` lưu **before/after**.

Luồng ghi log:

```text
Controller -> Service (DB::transaction)
                 |
                 +-- Model save/update/delete --> Observer --> ActivityLogger --> activity_logs
                 |
                 +-- Hành động đặc thù (capture, trừ kho) --> ActivityLogger (gọi trực tiếp)
```

---

## 2. Audit code hiện tại

### Đã có sẵn

- Kiến trúc B (Controller mỏng + Service chứa business rule) áp dụng nhất quán
  cho 13 service trong `app/Services`.
- Mọi nghiệp vụ đa bước đã bọc `DB::transaction` + `lockForUpdate`
  (`PrescriptionService`, `InvoiceService`, `PaymentService`, `MedicineService::adjustStock`).
- `app/Constants/*Message.php` — mỗi module một file hằng số message.
- `config/rbac.php` map `Controller -> RESOURCE` và `method -> ACTION`.
- Migration `2026_08_19_000000_convert_timestamps_to_timestamptz` đã đổi toàn bộ cột
  thời gian sang `timestamptz`.

### Chưa có (phải tạo mới)

| Hạng mục | Đường dẫn |
|---|---|
| Bảng `activity_logs` | `database/migrations/2026_08_21_000000_create_activity_logs_table.php` |
| Model | `app/Models/ActivityLog.php` |
| Hằng số action/subject | `app/Constants/ActivityLogAction.php`, `ActivityLogSubject.php` |
| Service ghi log | `app/Services/ActivityLogger.php` |
| Observers | `app/Observers/` (chưa tồn tại thư mục) |
| Đăng ký observer | `app/Providers/AppServiceProvider::boot()` (hiện đang rỗng) |
| Factory + test | `database/factories/ActivityLogFactory.php`, `tests/Feature/ActivityLogTest.php` |

---

## 3. Thiết kế bảng `activity_logs`

### Cột

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigserial | PK |
| `user_id` | bigint **nullable** | FK → `users`, `nullOnDelete` |
| `subject_type` | string(50) | Slug ngắn: `user`, `appointment`, … (xem 3.2) |
| `subject_id` | unsignedBigInteger | Không đặt FK (đa hình) |
| `action` | string(50) | `created`, `status_changed`, `stock_deducted`, … |
| `meta` | **jsonb** nullable | `{ before: {...}, after: {...} }` |
| `created_at` / `updated_at` | **timestamptz** | `$table->timestampsTz()` |

### 3.1 Vì sao `user_id` nullable

Hành động do hệ thống sinh ra (seeder, artisan command, job nền) không có user đăng nhập —
`auth()->id()` trả `null`. Nếu để `NOT NULL`, mọi lệnh `db:seed` sẽ vỡ. Đúng theo
`skills/database.md` mục 2: `activity_logs → users (nullable)`.

Dùng `nullOnDelete` thay vì `restrictOnDelete`: xoá user không được phép xoá mất vết audit,
nhưng cũng không được chặn nghiệp vụ xoá user. Log giữ lại, `user_id` thành `null`.

### 3.2 `subject_type` — slug ngắn, không phải FQCN

Theo mẫu trong `skills/backend.md` mục 8: `'subject_type' => 'appointment'` (không phải
`App\Models\Appointment`).

Đề xuất: dùng `Relation::enforceMorphMap()` trong `AppServiceProvider::boot()` để vừa lưu
slug ngắn, vừa dùng được quan hệ `morphTo` khi cần load subject:

```php
Relation::enforceMorphMap([
    'user' => User::class,
    'appointment' => Appointment::class,
    // ...
]);
```

Lợi: DB đọc được bằng mắt, không lộ namespace, đổi namespace không hỏng dữ liệu cũ.

> **Đã chốt:** dùng slug ngắn + `enforceMorphMap`.

**Lưu ý bắt buộc nhớ:** chữ `enforce` nghĩa đen — model nào không có trong map mà bị morph sẽ
**ném `ClassMorphViolationException`**, không âm thầm fallback về FQCN. Đây là hành vi tốt
(lỗi to hơn lỗi ngầm), nhưng kéo theo một quy tắc: **thêm subject mới = thêm một dòng vào
morph map**.

### 3.3 Index

| Index | Lý do |
|---|---|
| `(subject_type, subject_id)` | **Bắt buộc theo đề** — truy vết lịch sử của 1 đối tượng |
| `user_id` | Lọc "user X đã làm gì" |
| `created_at` | Sắp xếp/phân trang theo thời gian |

Không đặt CHECK constraint cho `action`: danh sách action sẽ còn mở rộng, ràng buộc cứng ở DB
sẽ buộc phải viết migration mỗi lần thêm action mới. Kiểm soát bằng hằng số PHP là đủ.

### 3.4 Lưu ý timestamptz

Migration này chạy **sau** `2026_08_19_000000_convert_timestamps_to_timestamptz`, nên nó
**không được migration đó quét qua**. Bắt buộc tự khai báo `$table->timestampsTz()` —
nếu viết `timestamps()` thì cột sẽ lọt lưới quy ước (`skills/database.md` mục 11).

---

## 4. Danh mục action sẽ ghi

| Subject | Action | Nguồn ghi | `meta` |
|---|---|---|---|
| `user` | `created` | Observer | `after`: name, email, role_id, is_active |
| `user` | `updated` | Observer | `before`/`after` các field đổi (đã lọc `password`) |
| `user` | `status_changed` | Observer | `before`/`after`: `is_active` |
| `appointment` | `status_changed` | Observer | `before`/`after`: `status` |
| `examination` | `created` | Observer | `after`: appointment_id, patient_id, doctor_id |
| `examination` | `updated` | Observer | `before`/`after` các field đổi |
| `prescription` | `created` | Observer | `after`: examination_id, doctor_id |
| `prescription_item` | `created` | Observer | `after`: medicine_id, quantity |
| `prescription_item` | `updated` | Observer | `before`/`after`: quantity, dosage |
| `prescription_item` | `deleted` | Observer | `before`: medicine_id, quantity |
| `medicine` | `stock_deducted` | **ActivityLogger trực tiếp** | `before`/`after`: stock; kèm `quantity`, `prescription_id` |
| `medicine` | `stock_restored` | **ActivityLogger trực tiếp** | như trên |
| `medicine` | `stock_adjusted` | **ActivityLogger trực tiếp** | `before`/`after`: stock; kèm `quantity` |
| `invoice` | `created` | Observer | `after`: subtotal, discount, total |
| `invoice` | `updated` | Observer | `before`/`after`: discount, total |
| `invoice` | `status_changed` | Observer | `before`/`after`: `status` |
| `payment` | `created` | Observer | `after`: amount, method, provider_order_id |
| `payment` | `captured` | **ActivityLogger trực tiếp** | `before`/`after`: status; kèm `provider_capture_id` |
| `payment` | `capture_failed` | **ActivityLogger trực tiếp** | `before`/`after`: status |

### 4.1 Phạm vi: đúng 8 subject, cố ý không log `doctor` / `patient` / `specialty`

Đã cân nhắc và **quyết định không log** ba bảng này. Lý do — không phải để tiết kiệm công viết:

- **Không nằm trong yêu cầu.** Đề chỉ định 6 nhóm: user, appointment status, examination,
  prescription/kho, invoice, payment. Danh sách đó có tiêu chí rõ ràng: nơi thay đổi trạng thái
  **gây hậu quả** — tiền, tồn kho, hồ sơ lâm sàng, quyền truy cập.
- **`specialty` là bảng danh mục tra cứu**, giá trị audit gần bằng 0.
- **`doctor`** chỉ có `license_number` là đáng theo dõi; phần còn lại là dữ liệu hồ sơ tĩnh.
- **`patient`** là trường hợp đáng cân nhắc nhất (PII y tế), nhưng vẫn ngoài phạm vi đề.

Lý do quan trọng nhất: **log rác làm hỏng chính audit trail**. Khi điều tra một giao dịch
thanh toán sai hoặc kho bị trừ bất thường, bảng log ngập những dòng "sửa mô tả chuyên khoa"
sẽ khiến việc lần vết khó hơn. Log là công cụ điều tra — tỷ lệ tín hiệu/nhiễu quyết định nó
có dùng được không, không phải số lượng dòng.

> Nếu sau này cần mở rộng: thêm subject = thêm hằng số + **một dòng vào morph map** (bắt buộc,
> vì `enforceMorphMap` là strict) + observer + test.

---

## 5. Observer hay gọi trực tiếp — và vì sao chia đôi

`skills/backend.md` mục 8 nói rõ: *"Action đặc thù (capture, adjustStock) ghi thủ công qua
Event"*. Lý do kỹ thuật cụ thể trong dự án này:

### 5.1 Observer làm được

Vòng đời model chuẩn (`created`, `updated`, `deleted`) qua `save()`/`update()`/`delete()`.
Dùng `$model->wasChanged('status')` + `$model->getOriginal('status')` để bắt đổi trạng thái.

### 5.2 Observer **không** đáng tin cho phần kho

`PrescriptionService` trừ/hoàn kho bằng `$medicine->decrement('stock', $qty)` và
`increment(...)` ([PrescriptionService.php:138](../app/Services/PrescriptionService.php#L138),
[:140](../app/Services/PrescriptionService.php#L140),
[:166](../app/Services/PrescriptionService.php#L166),
[:205](../app/Services/PrescriptionService.php#L205)).

**Đã đo thực tế, và kết quả bác bỏ giả định ban đầu:** `decrement()` *có* cập nhật giá trị
in-memory và `getChanges()` *có* trả đúng `{"stock": 45}`. Về mặt kỹ thuật, observer đọc được
before/after của `stock`.

Lý do thật sự để không dùng observer ở đây là **ngữ cảnh nghiệp vụ**: cùng một lần `stock`
giảm, nhưng do kê đơn hay do thủ kho điều chỉnh là hai action khác nhau, và log cần biết
`prescription_id` nào gây ra. Observer chỉ thấy "stock: 48 → 45", không thể phân biệt.

**Hệ quả quan trọng:** vì `decrement()`/`increment()` *có* bắn event `updated`, tuyệt đối
**không được thêm `MedicineObserver::updated()`** — mỗi lần trừ kho sẽ sinh 2 dòng log, một
dòng đầy đủ ngữ cảnh và một dòng trống rỗng.

→ **Trừ/hoàn/điều chỉnh kho ghi log bằng cách gọi `ActivityLogger` trực tiếp trong Service**,
nơi đã có sẵn cả giá trị cũ, giá trị mới và ngữ cảnh.

### 5.3 `payment` capture cũng gọi trực tiếp

`PaymentService::capture()` cần phân biệt `captured` với `capture_failed`, và cần ghi kèm
`provider_capture_id` — thông tin chỉ có trong service. Observer `updated` chỉ thấy
"status đổi từ pending sang completed", thiếu ngữ cảnh PayPal.

---

## 6. Ghi log **sau khi commit** — đã chốt (phương án C)

### Quy tắc duy nhất của toàn hệ thống

> **Log chỉ được ghi sau khi nghiệp vụ đã commit thành công, và không bao giờ có quyền
> làm hỏng nghiệp vụ.**

Mọi chỗ ghi log đều tuân theo quy tắc này — không có ngoại lệ cho module nào.

### Vì sao không ghi log bên trong transaction

Model event chạy đồng bộ ngay trong `update()`, tức nằm gọn trong `DB::transaction` của
service. Với [`PaymentService::capture()`](../app/Services/PaymentService.php#L88) điều đó
gây ra một kịch bản hỏng nghiêm trọng:

```text
DB::transaction(function () {
    $order = $payPalService->captureOrder(...);   ← TIỀN THẬT BỊ TRỪ (ngoài tầm DB)
    $lockedPayment->update([...'completed'...]);  ← observer → INSERT activity_logs
                                                   ← nếu insert lỗi → rollback tất cả
    $lockedInvoice->update(['status' => 'paid']);
});
```

Rollback được DB nhưng **không rollback được tiền**. Tệ hơn: payment quay về `pending`, trang
`/payments/return` retry → gọi `captureOrder()` lần hai → PayPal trả `ORDER_ALREADY_CAPTURED`
→ vì [`captureOrder()` không dùng `->throw()`](../app/Services/PayPalService.php#L45), lỗi này
về dưới dạng `status ≠ 'COMPLETED'` → [dòng 133-135](../app/Services/PaymentService.php#L133)
đánh dấu payment thành **`failed`**.

Kết cục: khách mất tiền, hệ thống ghi "thanh toán thất bại". Một bug ở tầng phụ trợ biến
thành sự cố tài chính.

### Cách triển khai — quy tắc nằm gọn trong `ActivityLogger`

Toàn bộ cơ chế nằm ở **một chỗ duy nhất**: `ActivityLogger::log()` dựng payload **ngay lập tức**
rồi bọc *chỉ mỗi lệnh INSERT* trong `DB::afterCommit()`.

| Thành phần | Cơ chế |
|---|---|
| `ActivityLogger::log()` | Payload dựng eager; `DB::afterCommit(fn () => ActivityLog::create(...))` |
| Observer | **Chạy inline, KHÔNG dùng `ShouldHandleEventsAfterCommit`** — xem cảnh báo dưới |
| 3 chỗ gọi trực tiếp trong Service | Gọi thẳng `$this->logger->...`, không cần bọc gì |

`DatabaseTransactionsManager::addCallback()` có nhánh fallback chạy callback ngay khi không có
transaction nào mở, nên đặt `DB::afterCommit()` trong logger là an toàn ở mọi ngữ cảnh — kể cả
khi gọi từ console/seeder.

**Hành vi:** rollback → callback bị huỷ, không có log rác. Commit → INSERT chạy sau
`$pdo->commit()` ([ManagesTransactions.php:207 rồi :215](../vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php)),
nên lỗi ghi log không thể rollback thứ đã commit.

### ⚠ Vì sao observer KHÔNG được dùng `ShouldHandleEventsAfterCommit`

Kế hoạch ban đầu định cho 7 observer implement interface này. **Kiểm chứng thực tế cho thấy
làm vậy sẽ phá hỏng `before`:**

`Model::finishSave()` gọi `syncOriginal()` ngay sau khi save xong, ghi đè `original` bằng giá
trị mới. Observer inline fire *trong* `performUpdate()` — trước thời điểm đó — nên còn đọc được
giá trị cũ. Callback hoãn tới lúc commit thì đã muộn.

Đo trực tiếp trên dự án (đổi `name` của một user):

| Thời điểm đọc | `getRawOriginal('name')` |
|---|---|
| Observer inline | `"Test Receptionist 2"` — giá trị cũ, **đúng** |
| Hoãn tới afterCommit | `"TEN_THU_NGHIEM"` — giá trị mới, **sai** |

Vì payload đã được dựng eager trong `ActivityLogger`, observer inline vẫn đạt đủ mọi tính chất
an toàn của phương án C. Interface đó vừa thừa vừa có hại.

### Đánh đổi đã chấp nhận

Nếu insert log lỗi **sau** khi commit, sẽ có nghiệp vụ đã lưu nhưng thiếu một dòng audit.
Mất một dòng log nhẹ hơn nhiều so với mất tiền của khách — đây là lý do chọn C thay vì
atomic tuyệt đối.

`ActivityLogger` vẫn phải viết phòng thủ (không truy cập field có thể null, `meta` luôn là
array thuần) để xác suất đó gần bằng không.

---

## 7. Lọc dữ liệu nhạy cảm khỏi `meta`

`meta` chụp lại `before`/`after` từ `getChanges()`/`getOriginal()`. Model `User` có
`password` và `remember_token` — **tuyệt đối không được lọt vào JSONB**, vì log sẽ bị đọc
bởi màn hình audit và bị dump ra khi backup.

Cơ chế: `ActivityLogger` có danh sách redact cứng, áp dụng cho mọi subject:

```text
password, remember_token, api_token, client_secret, provider_capture_id (?)
```

Đồng thời không log toàn bộ attribute — mỗi observer chỉ khai báo **whitelist field cần theo
dõi** (xem cột `meta` ở mục 4). Cách này an toàn hơn blacklist: field nhạy cảm thêm sau này
sẽ không tự động lọt vào log.

> Liên quan tiêu chí chấm điểm *"không commit PayPal secret"* — không log secret cũng nằm
> trong cùng tinh thần (`skills/backend.md` mục 7: *"Không commit/log secret"*).

---

## 8. Các file sẽ tạo/sửa

### Tạo mới

| # | File | Nội dung |
|---|---|---|
| 1 | `database/migrations/2026_08_21_000000_create_activity_logs_table.php` | Bảng + index, `timestampsTz` |
| 2 | `app/Models/ActivityLog.php` | `#[Fillable]`, cast `meta` → array, quan hệ `user()`, `subject()` morphTo |
| 3 | `app/Constants/ActivityLogAction.php` | Hằng số action |
| 4 | `app/Constants/ActivityLogSubject.php` | Hằng số subject slug |
| 5 | `app/Services/ActivityLogger.php` | `log()`, `logChange()`, redact, resolve `auth()->id()` |
| 6 | `app/Observers/UserObserver.php` | created / updated / status_changed |
| 7 | `app/Observers/AppointmentObserver.php` | status_changed |
| 8 | `app/Observers/ExaminationObserver.php` | created / updated |
| 9 | `app/Observers/PrescriptionObserver.php` | created |
| 10 | `app/Observers/PrescriptionItemObserver.php` | created / updated / deleted |
| 11 | `app/Observers/InvoiceObserver.php` | created / updated / status_changed |
| 12 | `app/Observers/PaymentObserver.php` | created |
| 13 | `database/factories/ActivityLogFactory.php` | Cho test |
| 14 | `tests/Feature/ActivityLogTest.php` | Test từng luồng |

### Sửa file có sẵn

| File | Thay đổi |
|---|---|
| `app/Providers/AppServiceProvider.php` | `boot()`: `Relation::enforceMorphMap()` + đăng ký 7 observer |
| `app/Services/PrescriptionService.php` | Gọi `ActivityLogger` tại 4 điểm trừ/hoàn kho |
| `app/Services/MedicineService.php` | Gọi `ActivityLogger` trong `adjustStock()` |
| `app/Services/PaymentService.php` | Gọi `ActivityLogger` trong `capture()` (2 nhánh) |

> Các service trên nhận `ActivityLogger` qua constructor injection, giữ đúng kiến trúc B.
> `PaymentService` hiện đã có constructor (`PayPalService`) — thêm tham số thứ hai.
>
> Observer chạy inline và Service gọi thẳng logger — không chỗ nào tự bọc `DB::afterCommit()`,
> vì `ActivityLogger` đã lo việc đó. Xem mục 6.

---

## 9. Thứ tự triển khai — **đã hoàn thành M1 → M6**

| Bước | Nội dung | Trạng thái |
|---|---|---|
| **M1** | Migration + Model + 2 file Constants | ✅ Xong |
| **M2** | `ActivityLogger` + morph map + đăng ký provider | ✅ Xong |
| **M3** | Observers cho user / appointment / examination | ✅ Xong |
| **M4** | Observers cho prescription / prescription_item / invoice / payment | ✅ Xong |
| **M5** | Gọi trực tiếp trong 3 Service (kho + capture) | ✅ Xong |
| **M6** | `ActivityLogTest` — 13 test | ✅ Xong |
| **M7** *(tùy chọn)* | API đọc log — xem mục 10 | Tách task riêng |

Kết quả cuối: `./vendor/bin/pint --test` passed, `php artisan test` **238/238** (1395 assertions).

> **`ActivityLogFactory` đã bỏ, có chủ đích.** Không test nào cần tạo sẵn dòng log — cả 13 test
> đều assert trên log do chính ứng dụng ghi ra. Thêm factory (và trait `HasFactory` kèm theo)
> sẽ là code chết. Khi nào có test cần dựng sẵn lịch sử log thì thêm.

---

## 10. API đọc log — **đã chốt: tách sang task riêng**

Yêu cầu gốc chỉ nói "Observer/Event **ghi** log", không yêu cầu endpoint đọc. Phase này
**không làm** M7. Khi nào làm, sẽ cần thêm:

1. Data migration idempotent thêm permission `ACTIVITYLOGS.FINDALL`
   (theo `skills/database.md` mục 5 — thêm permission **bằng migration**, không chỉ seeder).
2. `config/rbac.php`: thêm `'ActivityLogController' => 'ACTIVITYLOGS'`.
3. `RbacSeeder`: cấp cho ADMIN (mặc định ADMIN nhận toàn bộ nên tự có).
4. Controller + `ListActivityLogsRequest` + `ActivityLogResource` + filter theo
   `subject_type`, `subject_id`, `user_id`, khoảng thời gian.

Ngoài chi phí kỹ thuật, còn một quyết định **quyền riêng tư** chưa được đặt ra: `meta` chứa
dữ liệu nhạy cảm (số tiền, thông tin bệnh nhân, thay đổi tài khoản). Mở API đọc buộc phải trả
lời role nào xem được gì — CASHIER có được xem log `user` không, DOCTOR có được xem log
`payment` không. Đó là một thiết kế RBAC riêng, xứng đáng task riêng.

Trong lúc chưa có API, verify M1→M6 bằng `php artisan tinker` hoặc query thẳng Postgres.

---

## 11. Test đã viết (`tests/Feature/ActivityLogTest.php`) — 13 test, xanh

| Test | Khẳng định |
|---|---|
| `test_creating_user_writes_activity_log` | 1 dòng `user/created`, `user_id` = admin thao tác |
| `test_user_password_is_never_written_to_activity_log_meta` | `password` = `[REDACTED]`, không có hash `$2y$` |
| `test_updating_appointment_status_logs_before_and_after` | `before.status`/`after.status` đúng |
| `test_creating_examination_writes_activity_log` | `examination/created` **và** `appointment/status_changed` |
| `test_prescription_item_deducts_stock_and_logs_before_after` | stock 10→7, `quantity`=3, đúng `prescription_id` |
| `test_removing_prescription_item_logs_stock_restored` | stock 10→14, `action=stock_restored` |
| `test_adjust_stock_logs_before_and_after` | stock 20→35, `action=stock_adjusted` |
| `test_creating_invoice_writes_activity_log` | **đúng 1 dòng** — chặn hồi quy bẫy `invoice_code` |
| `test_capturing_payment_logs_captured_action` | `captured` + `provider_capture_id` + invoice `paid` |
| `test_failed_capture_logs_capture_failed_action` | `capture_failed`, invoice **không** đổi trạng thái |
| `test_failed_business_rule_rolls_back_activity_log` | Thiếu kho (422) → **0 dòng log**, kho nguyên vẹn |
| `test_console_originated_change_writes_log_with_null_user_id` | `user_id` null khi không có ai đăng nhập |
| `test_subject_relation_resolves_through_the_morph_map` | `$log->subject` trả đúng model qua morph map |

Hai test đáng chú ý:

- **`test_failed_business_rule_rolls_back_activity_log`** — chứng minh quyết định mục 6: nghiệp
  vụ rollback thì callback `afterCommit` bị huỷ, không sinh log rác.
- **`test_creating_invoice_writes_activity_log`** — assert **đúng 1 dòng**, khoá lại bẫy
  `invoice_code` hai bước ở mục 4. Nếu ai đó thêm `invoice_code` vào watched list, test này đỏ.

> **Lưu ý khi viết thêm test:** factory **cũng** kích hoạt observer. `Invoice::factory()->create()`
> tự ghi một dòng `invoice/created`. Vì vậy các assertion nên lọc theo `action` cụ thể thay vì
> đếm tổng số dòng của một subject.

---

## 12. Checklist review trước PR

Theo `skills/backend.md` mục 11 + `skills/database.md` mục 9:

- [x] `activity_logs.meta` là **JSONB**, không phải `json`/`text`.
- [x] Có index `(subject_type, subject_id)`.
- [x] Dùng `timestampsTz()`, không dùng `timestamps()`.
- [x] `user_id` nullable + `nullOnDelete`.
- [x] Không có `password`/secret nào trong `meta` (có test riêng).
- [x] Observer đăng ký trong `AppServiceProvider::boot()`.
- [x] Service nhận `ActivityLogger` qua constructor, không dùng facade tĩnh rải rác.
- [x] Controller không đụng tới log (business nằm ở Service/Observer).
- [x] Comment code **tiếng Anh**, phủ Method/Class/Constructor (`skills/backend.md` mục 12).
- [x] `php artisan test` xanh; `migrate:fresh --seed` sạch.

---

## 13. Ba điểm đã chốt

| # | Vấn đề | Quyết định |
|---|---|---|
| 1 | `subject_type` | **Slug ngắn + `enforceMorphMap`** — xem mục 3.2 |
| 2 | Transaction | **Phương án C: ghi log sau commit** (`ShouldHandleEventsAfterCommit` + `DB::afterCommit`) — xem mục 6 |
| 3 | API đọc log | **Tách task riêng**, phase này chỉ làm M1 → M6 — xem mục 10 |

Phạm vi phase này: **M1 → M6**. Không làm M7.