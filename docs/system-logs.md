# System Log — Ghi log hệ thống ra file theo ngày

Mọi request thất bại được ghi ra `storage/logs/laravel-YYYY-MM-DD.log`, mỗi ngày một file,
chỉ từ mức `warning` trở lên.

> Trạng thái: **đã triển khai**. 257/257 test xanh.
> Không có middleware, không có class mới — toàn bộ nằm ở `.env` và `bootstrap/app.php`.

---

## 1. Dùng thế nào

```
storage/logs/
  laravel-2026-08-24.log     <- hôm nay
  laravel-2026-08-23.log     <- hôm qua
  laravel.log                <- file cũ trước khi đổi, giữ lại làm lịch sử
```

Xem log hôm nay:

```bash
tail -f storage/logs/laravel-$(date +%F).log
docker compose exec app tail -f storage/logs/laravel-$(date +%F).log
```

Lọc theo loại lỗi:

```bash
grep '"status":500' storage/logs/laravel-2026-08-24.log
grep '"type":"ValidationException"' storage/logs/laravel-2026-08-24.log
```

---

## 2. Cấu hình

`.env` và `.env.example` (đã đồng bộ cả hai):

| Biến | Giá trị | Tác dụng |
|---|---|---|
| `LOG_CHANNEL` | `daily` | Cắt file theo ngày. Trước đây là `stack` → `single` → một file duy nhất |
| `LOG_LEVEL` | `warning` | Bỏ hẳn `debug`/`info`/`notice`. Hạ xuống `debug` khi cần điều tra sâu |
| `LOG_DAILY_DAYS` | `30` | Monolog tự xoá file cũ hơn 30 ngày |

`config/logging.php` **không sửa gì** — channel `daily` đã có sẵn từ đầu.

Sau khi đổi `.env` nhớ `php artisan config:clear`.

---

## 3. Log này ghi cái gì

| Tình huống | Mức | Nội dung |
|---|---|---|
| Request thành công (2xx/3xx) | — | Không ghi |
| 401 / 403 / 404 / 405 / 422 | `WARNING` | Một dòng gọn: message + context request |
| 500, exception chưa bắt | `ERROR` | Message + context request + **stack trace đầy đủ** |
| `abort(503)` và 5xx khác | `ERROR` | Như trên |
| `Log::warning()` gọi thủ công | `WARNING` | Tuỳ nơi gọi |
| `Log::info()` / `Log::debug()` | — | Bị chặn bởi `LOG_LEVEL=warning` |

Ví dụ thật, một request thiếu cả `email` lẫn `password`:

```
[2026-08-24 21:23:35] local.WARNING: The email field is required. (and 1 more error) {"method":"POST","url":"http://localhost:8000/api/login","user_id":null,"ip":"127.0.0.1","status":422,"type":"ValidationException","errors":{"email":["The email field is required."],"password":["The password field is required."]}}
```

Các khoá context:

| Khoá | Ghi chú |
|---|---|
| `method`, `url`, `ip` | Định danh request |
| `user_id` | `null` khi chưa đăng nhập |
| `duration_ms` | Có khi chạy qua web/console, không có khi chạy trong PHPUnit |
| `status` | Mã HTTP client thực sự nhận được |
| `type` | Tên ngắn của exception |
| `errors` | Chỉ có ở `ValidationException` |
| `exception` | **Chỉ có ở 5xx** — đây là thứ kéo theo stack trace |

---

## 4. Cơ chế — `bootstrap/app.php`

### 4.1 Vì sao phải sửa gì đó

`Illuminate\Foundation\Exceptions\Handler::$internalDontReport` bỏ qua sẵn
`ValidationException`, `AuthenticationException`, `AuthorizationException`, `HttpException`,
`ModelNotFoundException`. Nghĩa là **trước thay đổi này, mọi lỗi 4xx biến mất khỏi log hoàn toàn**;
chỉ `Throwable` chưa bắt mới được ghi, và ghi không kèm thông tin request nào.

### 4.2 Bốn mảnh ghép

```php
// 1. Danh sách lỗi phía client, gỡ khỏi internalDontReport
$clientErrors = [
    ValidationException::class,
    AuthenticationException::class,
    AuthorizationException::class,
    ModelNotFoundException::class,
    HttpException::class,          // phủ NotFoundHttpException, MethodNotAllowed, abort()
];
$exceptions->stopIgnoring($clientErrors);

// 2. Suy ra status từ exception — report() chạy TRƯỚC render() nên chưa có response để đọc
$statusFor = static fn (Throwable $e): int => match (true) {
    $e instanceof HttpExceptionInterface => $e->getStatusCode(),
    $e instanceof ValidationException    => $e->status,
    ...
};

// 3. Context request, dùng chung cho cả dòng warning lẫn dòng error
$exceptions->context(fn (Throwable $e): array => $requestContext());

// 4. 4xx: một dòng gọn rồi return false để chặn ghi mặc định (vốn kèm trace)
//    5xx: return true để rơi xuống reporter mặc định, giữ nguyên trace
$exceptions->report(function (Throwable $e) use (...): bool {
    if ($statusFor($e) >= 500) {
        return true;
    }
    Log::warning($e->getMessage() ?: class_basename($e), $context);

    return false;
});
```

Cơ chế `return false` nằm ở `Handler::reportThrowable()`:
`if ($reportCallback->handles($e) && $reportCallback($e) === false) { return; }`

Nhờ đó **422 ra đúng một dòng, còn 500 vẫn giữ trọn stack trace** — không phải đánh đổi
bằng cách tắt `includeStacktraces` cho cả channel.

### 4.3 Hai chỗ dễ vấp

- **`$exceptions->context()` không áp cho `Log::warning()` gọi tay.** Nó chỉ nuôi
  `Handler::buildExceptionContext()`, tức đường ghi mặc định. Vì vậy `$requestContext` được gọi
  tường minh ở cả hai nơi.
- **`LARAVEL_START` không tồn tại khi chạy PHPUnit.** Hằng này chỉ được `define()` trong
  `public/index.php` và `artisan`. Không guard bằng `defined()` thì mọi feature test sẽ nổ.

---

## 5. Vì sao không phải `24-08-2026.log`

Yêu cầu ban đầu là `d-m-Y`. Đã khảo sát `vendor/monolog/monolog/.../RotatingFileHandler.php`
và **chốt đổi sang chuẩn Monolog `Y-m-d`**, vì ba lý do:

1. `setDateFormat()` chặn bằng regex `{^[Yy](([/_.-]?m)([/_.-]?d)?)?$}` — bắt buộc bắt đầu bằng `Y`.
   Muốn `d-m-Y` phải kế thừa handler, đụng vào `protected` của thư viện.
2. **Quan trọng nhất**: `rotate()` xoá file cũ bằng `usort(..., fn ($a, $b) => strcmp($b, $a))`,
   tức sort theo **chuỗi tên file**. Chỉ với `Y-m-d` thì thứ tự chuỗi mới trùng thứ tự thời gian.
   Với `d-m-Y` thì `31-01-2026` > `01-02-2026` → **xoá nhầm file mới, giữ file cũ**. Đây chính là
   lý do Monolog đặt ra ràng buộc ở điểm 1, không phải quy định tuỳ tiện.
3. Đổi sang chuẩn Monolog thì `LOG_DAILY_DAYS` chạy đúng và miễn phí, xoá được 3 thành phần
   khỏi kế hoạch ban đầu: handler kế thừa, factory channel, và một artisan command prune riêng.

Nếu sau này bắt buộc phải có `d-m-Y`, cái giá là: viết `DailyDateFileHandler extends
RotatingFileHandler` (override `setDateFormat`), đặt `maxFiles = 0` để tắt GC hỏng, và tự viết
command prune + lịch chạy.

---

## 6. Vì sao không cần middleware

Ý tưởng ban đầu là một middleware global đọc `$response->getStatusCode()` để phân loại. Bỏ, vì
**mọi đường trả lỗi trong app này đều đi qua exception**. Đã grep kiểm chứng trong `app/`:

| Mẫu tìm | Kết quả |
|---|---|
| `ApiResponse::error(` | 0 — chỉ xuất hiện trong `bootstrap/app.php` |
| `response()->json(` ở Controller/Service | 0 |
| `abort(` ở Controller/Service | 0 |

Đúng như `skills/backend.md` quy định: Service ném `ValidationException` cho lỗi nghiệp vụ.
Không có lỗi nào lọt ngoài exception handler, nên middleware sẽ không bắt thêm được gì mà lại
thêm 3 class và ~250 dòng.

**Ràng buộc phải giữ**: nếu sau này có Controller trả thẳng `ApiResponse::error(...)` mà không ném
exception, lỗi đó sẽ **không** vào log. Cần thì quay lại phương án middleware.

---

## 7. Phân biệt với Activity Log

| | `ActivityLogger` (`app/Services`) | System log (tài liệu này) |
|---|---|---|
| Đích | Bảng DB `activity_logs` | File `storage/logs/laravel-YYYY-MM-DD.log` |
| Người đọc | Nghiệp vụ / admin | Dev, vận hành |
| Nội dung | Ai sửa bản ghi nào, before/after | Request nào hỏng, vì sao |
| Khi thành công | **Có ghi** | Không ghi |
| Khi thất bại | Không ghi | **Có ghi** |

Hai hệ thống bổ sung nhau. Hiện chưa dùng chung danh sách field nhạy cảm — xem mục 9.

---

## 8. Test — `tests/Feature/SystemLogTest.php`

9 test, chia hai nhóm:

**Qua `Log::spy()`** — kiểm mức log và nội dung context:

1. 422 → một `warning`, context có `status`/`type`/`method`/`url`/`errors`, `user_id` null
2. 422 → context **không** có khoá `exception` (đây là bằng chứng đã chặn được stack trace)
3. 401 thiếu token → `warning`, `status` 401
4. 404 route lạ → `warning`, `status` lấy đúng từ Symfony
5. 500 → một `error`, context có `exception instanceof RuntimeException`, không có `warning`
6. Request 200 → không `warning`, không `error`

**Qua file thật** (trỏ channel vào thư mục tạm, dọn ở `tearDown`):

7. 422 → sinh đúng `laravel-YYYY-MM-DD.log`, có `WARNING`, có `"status":422`, **không** có `[stacktrace]`
8. 500 → cùng file, có `ERROR`, **có** `[stacktrace]`
9. `Log::info()` → không sinh file nào

`phpunit.xml` thêm `LOG_CHANNEL=null`: giờ mọi 4xx đều được report, không chặn thì chạy suite sẽ
đổ hàng trăm dòng rác vào `storage/logs` thật.

---

## 9. Vận hành & lưu ý

| Điểm | Chi tiết |
|---|---|
| **Giờ trong log là UTC** | `.env` đặt `APP_TIMEZONE=UTC` theo quyết định chung ([stats.md §4.3](stats.md)). Dòng ghi lúc 21:25 giờ VN hiện `14:25`, và request lúc 06:00 giờ VN ngày 24/08 nằm trong file `laravel-2026-08-23.log` |
| **404 từ bot/scanner cũng vào log** | `NotFoundHttpException` nay được ghi. Ở production nếu nhiễu quá thì thêm `NotFoundHttpException` vào `dontReport` |
| **Log có chứa dữ liệu request** | Hiện chỉ ghi `method`/`url`/`ip`, **không** ghi body. Nếu sau này thêm `input` vào context thì phải che field nhạy cảm — `ActivityLogger::REDACTED_KEYS` đang là `private const`, cần tách ra hằng dùng chung trước |
| **`laravel.log` cũ (587 KB)** | Giữ nguyên làm lịch sử. Channel `emergency` vẫn trỏ vào file này |
| **Tiến trình chạy dài** | `RotatingFileHandler` tự chuyển file khi qua nửa đêm, nên `queue:work` không bị kẹt ở file cũ |

---

## 10. File đã đụng tới

| File | Thay đổi |
|---|---|
| `.env`, `.env.example` | `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`, thêm `LOG_DAILY_DAYS=30` |
| `bootstrap/app.php` | +3 import, +3 closure ở đầu file, +1 khối report trong `withExceptions` |
| `phpunit.xml` | `LOG_CHANNEL=null` |
| `tests/Feature/SystemLogTest.php` | Mới |
| `skills/backend.md` | Thêm mục 9, dồn số các mục sau |
| `config/logging.php` | **Không sửa** |
