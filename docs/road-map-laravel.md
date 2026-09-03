# Roadmap tự học Laravel — từ "hiểu luồng" đến "tự viết được API"

> **Bối cảnh**: bạn đã build xong `clinic-manage` (Laravel 13 + PHP 8.3 + PostgreSQL + Sanctum + RBAC tự viết + 21 feature test) với sự trợ giúp lớn từ AI. Bạn **hiểu luồng** nhưng **chưa gõ được từng dòng**. Roadmap này không dạy lại Laravel từ đầu như một khoá học — nó biến chính project này thành giáo trình của bạn.
>
> **Thời lượng đề xuất**: 12 tuần × ~8–10h/tuần. Có thể kéo dài, nhưng **không nên rút ngắn** — thứ bạn thiếu là *cơ bắp gõ code*, mà cơ bắp cần thời gian.

---

## Mục lục

- [Phần A — Nguyên tắc trước khi bắt đầu](#phần-a--nguyên-tắc-trước-khi-bắt-đầu)
- [Phần B — Tự đánh giá hiện trạng (làm ngay hôm nay)](#phần-b--tự-đánh-giá-hiện-trạng-làm-ngay-hôm-nay)
- [Phần C — 12 giai đoạn kiến thức](#phần-c--12-giai-đoạn-kiến-thức)
  - [GĐ 0 — PHP hiện đại (nền móng bắt buộc)](#gđ-0--php-hiện-đại-nền-móng-bắt-buộc)
  - [GĐ 1 — Vòng đời một request](#gđ-1--vòng-đời-một-request)
  - [GĐ 2 — Routing & Controller](#gđ-2--routing--controller)
  - [GĐ 3 — Validation & Form Request](#gđ-3--validation--form-request)
  - [GĐ 4 — Database, Migration & Eloquent](#gđ-4--database-migration--eloquent)
  - [GĐ 5 — API Resource & chuẩn hoá response](#gđ-5--api-resource--chuẩn-hoá-response)
  - [GĐ 6 — Service layer, Container & Dependency Injection](#gđ-6--service-layer-container--dependency-injection)
  - [GĐ 7 — Authentication (Sanctum) & Authorization (RBAC)](#gđ-7--authentication-sanctum--authorization-rbac)
  - [GĐ 8 — Xử lý lỗi tập trung](#gđ-8--xử-lý-lỗi-tập-trung)
  - [GĐ 9 — Testing](#gđ-9--testing)
  - [GĐ 10 — Chủ đề nâng cao](#gđ-10--chủ-đề-nâng-cao)
  - [GĐ 11 — Vận hành: Docker, env, deploy, CI](#gđ-11--vận-hành-docker-env-deploy-ci)
- [Phần D — Dự án tốt nghiệp: viết một API từ số 0, KHÔNG dùng AI](#phần-d--dự-án-tốt-nghiệp-viết-một-api-từ-số-0-không-dùng-ai)
- [Phần E — Lịch 12 tuần chi tiết](#phần-e--lịch-12-tuần-chi-tiết)
- [Phần F — Cách dùng AI để học nhanh hơn (thay vì để nó thay bạn)](#phần-f--cách-dùng-ai-để-học-nhanh-hơn-thay-vì-để-nó-thay-bạn)
- [Phần G — Tài nguyên](#phần-g--tài-nguyên)
- [Phần H — Bảng theo dõi tiến độ](#phần-h--bảng-theo-dõi-tiến-độ)

---

## Phần A — Nguyên tắc trước khi bắt đầu

### A1. Bốn nguyên tắc vàng

1. **Tra cứu KHÔNG phải là yếu kém.** Dev Laravel 5 năm kinh nghiệm vẫn mở tab `laravel.com/docs` mỗi ngày. Mục tiêu của bạn không phải là "thuộc lòng", mà là:
   > *Biết mình đang cần gì, biết nó nằm ở đâu trong docs, và đọc 30 giây là dùng được.*

   Đây gọi là **known unknowns**. Cái nguy hiểm là **unknown unknowns** — không biết là Laravel có sẵn thứ đó nên tự chế lại (hoặc để AI chế lại) một cách sai.

2. **Gõ tay, không copy.** Đọc hiểu code ≠ viết được code. Khoảng cách đó chỉ đóng được bằng cách **gõ lại bằng tay**, để trống, gõ lại lần nữa. Mỗi giai đoạn bên dưới đều có mục "Bài tập gõ tay" — đó là phần quan trọng nhất của cả roadmap, đừng bỏ qua nó để đọc tiếp cho nhanh.

3. **Học từ code của chính bạn.** Bạn có sẵn một codebase thật, đầy đủ: RBAC, transaction, soft delete, observer, PayPal, activity log, 21 test file. Đây là tài liệu học tốt hơn mọi tutorial trên mạng, vì bạn đã hiểu *nghiệp vụ* rồi — giờ chỉ cần hiểu *cú pháp và cơ chế*.

4. **Quy tắc "docs trước, AI sau".** Khi bí:
   - Bước 1: tự nghĩ 5 phút, viết ra bạn *nghĩ* nó chạy thế nào.
   - Bước 2: tra Laravel docs / API reference.
   - Bước 3: mới hỏi AI — và hỏi **"tại sao"**, không hỏi **"viết hộ"**.

### A2. Kỹ thuật học: Feynman + Xoá-và-viết-lại

**Feynman**: sau mỗi giai đoạn, viết 1 đoạn ~10 dòng giải thích chủ đề đó *như đang giảng cho một dev PHP thuần chưa biết Laravel*. Nếu viết không nổi → chưa hiểu, quay lại đọc.

**Xoá-và-viết-lại** (kỹ thuật mạnh nhất trong file này):
```bash
# Ví dụ với module Specialty (module đơn giản nhất trong project)
git switch -c practice/specialty-rewrite
rm app/Http/Controllers/SpecialtyController.php
rm app/Services/SpecialtyService.php
rm app/Http/Resources/SpecialtyResource.php
rm -r app/Http/Requests/Specialty

php artisan test --filter=SpecialtyTest   # đỏ hết -> đó là "đề bài" của bạn
# Viết lại từ đầu, KHÔNG mở git history, cho đến khi test xanh lại
git switch - && git branch -D practice/specialty-rewrite   # xong thì bỏ nhánh
```
Test suite có sẵn chính là **đề bài + đáp án tự động**. Đây là thứ mà người học Laravel bình thường không có, còn bạn thì có sẵn.

### A3. Dấu hiệu bạn đã "qua bài"

Với mỗi giai đoạn, bạn **chưa** qua nếu vẫn cần AI để viết. Bạn **đã** qua khi:
- Gõ được cấu trúc chính từ trí nhớ (chỉ cần tra tên method/rule cụ thể).
- Giải thích được *tại sao* làm cách đó, và *nếu không làm thế thì hỏng chỗ nào*.
- Debug được khi nó chạy sai.

---

## Phần B — Tự đánh giá hiện trạng (làm ngay hôm nay)

Trả lời **không mở code, không hỏi AI**. Đánh dấu ✅ nếu trả lời trôi chảy, ❌ nếu ú ớ. Kết quả sẽ chỉ ra bạn nên bắt đầu từ giai đoạn nào.

| # | Câu hỏi | Liên quan GĐ |
|---|---------|-----------|
| 1 | `private readonly PatientService $service` trong constructor — ai truyền `PatientService` vào? Bằng cơ chế nào? | GĐ 0, 6 |
| 2 | Khác nhau giữa `interface`, `abstract class`, `trait` trong PHP? | GĐ 0 |
| 3 | `?string` và `string\|null` khác gì nhau? `static fn () =>` là gì? | GĐ 0 |
| 4 | Request `GET /api/patients` đi qua những bước nào từ lúc vào `public/index.php` đến khi trả JSON? | GĐ 1 |
| 5 | Middleware `auth:sanctum` và `permission` chạy trước hay sau khi `ListPatientsRequest` validate? | GĐ 1, 3 |
| 6 | `Route::apiResource('patients', ...)` sinh ra chính xác bao nhiêu route, method + URI là gì? | GĐ 2 |
| 7 | Vì sao `show(Patient $patient)` tự có object thay vì phải tự query theo `$id`? | GĐ 2 |
| 8 | Form Request validate **fail** thì chuyện gì xảy ra? Ai bắt exception đó? Client nhận status nào? | GĐ 3, 8 |
| 9 | `$request->validated()` khác `$request->all()` chỗ nào? Vì sao khác biệt đó là bảo mật? | GĐ 3 |
| 10 | Viết migration tạo bảng có FK tới `patients` + index — gõ được không? | GĐ 4 |
| 11 | N+1 query là gì? Trong project này chỗ nào đã xử lý? Xử lý bằng gì? | GĐ 4, 10 |
| 12 | `hasMany` / `belongsTo` / `belongsToMany` — cái nào ứng với `role_permissions`? | GĐ 4 |
| 13 | Vì sao `PatientService::create()` bọc trong `DB::transaction()`? Bỏ đi thì hỏng gì? | GĐ 4 |
| 14 | `scopeSearch` được gọi ở đâu, viết thế nào để dùng nó? | GĐ 4 |
| 15 | `PatientResource` để làm gì? Bỏ nó, trả thẳng model ra thì rủi ro là gì? | GĐ 5 |
| 16 | Vì sao có `ApiResponse::paginated()` riêng thay vì dùng luôn `->response()` của Laravel? | GĐ 5 |
| 17 | Vì sao logic nằm ở `Service` chứ không nằm trong `Controller`? Lợi ích cụ thể? | GĐ 6 |
| 18 | Sanctum token được lưu ở đâu, kiểm tra thế nào mỗi request? | GĐ 7 |
| 19 | `EnsurePermission` suy ra tên permission từ đâu? Giải thích `PatientController@index` → permission nào. | GĐ 7 |
| 20 | Trong `bootstrap/app.php`, `$exceptions->render(...)` khác `$exceptions->report(...)` chỗ nào? | GĐ 8 |
| 21 | `RefreshDatabase` làm gì giữa các test? Vì sao test không làm bẩn DB thật? | GĐ 9 |
| 22 | `Sanctum::actingAs($user)` thay thế bước nào của flow thật? | GĐ 9 |
| 23 | Observer trong `app/Observers/` được đăng ký ở đâu, chạy vào lúc nào? | GĐ 10 |
| 24 | Cache/queue của project đang dùng driver gì? Đổi sang Redis cần sửa gì? | GĐ 10, 11 |

**Cách đọc kết quả:**
- ❌ ở câu 1–3 → **bắt buộc** làm GĐ 0 thật kỹ. Đây là gốc rễ; thiếu PHP OOP thì Laravel mãi mãi là ma thuật.
- ❌ ở 4–9 → bắt đầu từ GĐ 1.
- ✅ hết 1–9, ❌ từ 10 → nhảy vào GĐ 4.
- ✅ gần hết nhưng vẫn "không tự gõ được" → vấn đề của bạn là **cơ bắp gõ code**, không phải kiến thức: bỏ qua phần đọc, làm thẳng mục "Bài tập gõ tay" của mọi giai đoạn + [Phần D](#phần-d--dự-án-tốt-nghiệp-viết-một-api-từ-số-0-không-dùng-ai).

---

## Phần C — 12 giai đoạn kiến thức

> Mỗi giai đoạn có 5 mục cố định:
> **🎯 Mục tiêu** · **📚 Cần nắm** · **🔍 Đọc trong project này** · **✍️ Bài tập gõ tay** · **✅ Tự kiểm tra**

---

### GĐ 0 — PHP hiện đại (nền móng bắt buộc)

> ⏱️ ~1 tuần. **Đừng bỏ qua giai đoạn này.** 80% cảm giác "Laravel là ma thuật" thực chất là do chưa vững PHP OOP. Khi bạn hiểu `interface`, constructor injection và namespace, một nửa Laravel tự nhiên trở nên hiển nhiên.

#### 🎯 Mục tiêu
Đọc bất kỳ file `.php` nào trong `app/` mà không có dòng nào khiến bạn phải đoán *cú pháp* (nghiệp vụ thì có thể chưa biết — chấp nhận được).

#### 📚 Cần nắm
- [ ] **Namespace & autoload PSR-4**: vì sao `App\Services\PatientService` ⇄ `app/Services/PatientService.php`. Vai trò của `composer.json` → `autoload.psr-4`, và `composer dump-autoload`.
- [ ] **`use` statement**: import class, alias (`use X as Y`), khác gì `require`.
- [ ] **OOP cơ bản**: `class`, `extends`, `implements`, `abstract`, `final`, `static`, `const`.
- [ ] **Visibility**: `public` / `protected` / `private`, và `readonly` (PHP 8.1+).
- [ ] **Interface vs Abstract class vs Trait** — khi nào dùng cái nào. Trait chính là cơ chế đứng sau `use HasFactory, SoftDeletes;`.
- [ ] **Constructor property promotion** (PHP 8.0): `public function __construct(private readonly PatientService $service) {}` — hiểu nó tương đương gì ở dạng đầy đủ.
- [ ] **Type declarations**: kiểu tham số/trả về, `?Type` (nullable), union `A|B`, `void`, `mixed`, `never`, `static`.
- [ ] **Named arguments**: `ApiResponse::error($msg, status: 404)` — bỏ qua tham số giữa.
- [ ] **Closure & arrow function**: `function () use ($x) {}` vs `fn ($x) => ...`; vì sao arrow fn tự capture biến ngoài còn closure phải `use`.
- [ ] **`match` expression** (PHP 8.0) vs `switch` — strict comparison, trả về giá trị. Xem `$statusFor` trong `bootstrap/app.php`.
- [ ] **Null-safe operator** `?->` — xem `$request->route()?->getActionName()`.
- [ ] **Spread trong array** `[...$data, 'code' => ...]` — xem `PatientService::create()`.
- [ ] **Exception**: `throw`, `try/catch/finally`, class hierarchy `Throwable` → `Exception`, tự viết exception class.
- [ ] **Array functions hay dùng**: `array_map`, `array_filter`, `array_merge`, `in_array`, `array_keys`, `usort`, `compact`.
- [ ] **String functions**: `sprintf`, `str_contains`, `explode`, `trim`, `preg_match`.
- [ ] **Enum** (PHP 8.1): backed enum, `->value`, `::cases()`, `::from()` / `::tryFrom()`.
- [ ] **Attributes** (PHP 8.0): `#[Fillable([...])]` trong `app/Models/Patient.php` là attribute, không phải comment.
- [ ] **PHPDoc generic**: `@param array<string, mixed> $data`, `@return array<int, string>` — ý nghĩa với static analysis.

#### 🔍 Đọc trong project này
| File | Xem cái gì |
|---|---|
| `app/Http/Controllers/PatientController.php` | Constructor promotion, `readonly`, type hint, named arg |
| `app/Models/Patient.php` | Trait `use`, attribute `#[Fillable]`, `const GENDERS`, closure trong `scopeSearch` |
| `bootstrap/app.php` | `match(true)`, arrow fn, `use ($var)` trong closure, static closure |
| `app/Http/Responses/ApiResponse.php` | `final class`, `static` method, default param, named argument |
| `composer.json` | Khối `autoload.psr-4` — nối namespace ↔ thư mục |

#### ✍️ Bài tập gõ tay
1. Tạo `/tmp/php-practice/` (ngoài project). Viết một script thuần PHP, **không Laravel**:
   - `interface PaymentGateway { public function charge(int $amount): bool; }`
   - Hai class implement: `PayPalGateway`, `CashGateway`.
   - Class `OrderService` nhận `PaymentGateway` qua constructor và gọi `charge()`.
   - Trong `index.php` lần lượt inject 2 gateway → **đây chính xác là cơ chế mà Laravel Container làm tự động**.
2. Thêm `enum OrderStatus: string { case Pending = 'pending'; case Paid = 'paid'; }` và một method `label(): string` dùng `match($this)`.
3. Viết `class InsufficientFundsException extends RuntimeException`, `throw` nó trong `charge()`, `catch` ở `index.php`.
4. Bật autoload PSR-4 cho `/tmp/php-practice/` bằng `composer init` + `composer dump-autoload`, chia mỗi class 1 file với namespace đúng. **Bài này rất quan trọng**: nó xoá bí ẩn "sao Laravel biết file nào ở đâu".

#### ✅ Tự kiểm tra
- [ ] Giải thích được: nếu đổi tên thư mục `app/Services` → `app/Business` mà không sửa gì khác thì hỏng gì, sửa ở đâu?
- [ ] `private readonly` — `readonly` chặn cái gì mà `private` không chặn?
- [ ] Vì sao `PatientService` không cần `new` ở đâu cả mà vẫn có instance?
- [ ] Khi nào chọn interface, khi nào chọn abstract class?

---

### GĐ 1 — Vòng đời một request

> ⏱️ ~3 ngày. Bạn nói mình "hiểu luồng" — giai đoạn này biến hiểu-đại-khái thành hiểu-chính-xác-thứ-tự.

#### 🎯 Mục tiêu
Vẽ được (bằng tay, trên giấy) toàn bộ đường đi của `GET /api/patients?q=an&per_page=20`, kèm tên file thật trong project.

#### 📚 Cần nắm
- [ ] `public/index.php` → tạo Application → `bootstrap/app.php` → Kernel HTTP → Router.
- [ ] **Service Provider**: `register()` vs `boot()`. Xem `app/Providers/AppServiceProvider.php`.
- [ ] **Middleware pipeline**: chạy theo thứ tự nào, "before" vs "after" middleware, `$next($request)` nghĩa là gì.
- [ ] Thứ tự chính xác: **middleware → route model binding → Form Request (authorize → rules → validate) → controller method → service → response**.
- [ ] Exception ném ra ở bất kỳ tầng nào sẽ nhảy thẳng tới handler ở `bootstrap/app.php`, bỏ qua phần còn lại.
- [ ] `config/*.php` được đọc thế nào, `config('rbac.actions.index')` tra ra giá trị nào.
- [ ] `.env` → `config()` — vì sao **không bao giờ** gọi `env()` ngoài file config (do `config:cache`).
- [ ] Cấu trúc Laravel 11+/13: **không còn** `app/Http/Kernel.php`, tất cả gom vào `bootstrap/app.php` (nhiều tutorial cũ trên mạng sẽ làm bạn lạc — nhớ điều này).

#### 🔍 Đọc trong project này
- `bootstrap/app.php` — đọc **cả file, từ trên xuống dưới**, đây là trung tâm điều khiển. Chú ý `->withRouting()`, `->withMiddleware()` (alias `permission`), `->withExceptions()`.
- `routes/api.php` — 3 nhóm route: public (`/login`), chỉ auth (`/logout`, `/me`), auth + permission (phần còn lại).
- `config/rbac.php` — bảng ánh xạ controller → tài nguyên, method → hành động.

#### ✍️ Bài tập gõ tay
1. Vẽ sơ đồ luồng ra giấy/Excalidraw cho `POST /api/patients`. Ghi rõ: request bị **từ chối** ở mỗi chặng thì client nhận status nào (401 / 403 / 422 / 404 / 500) và ai sinh ra status đó.
2. Tự viết middleware `LogSlowRequests`: đo thời gian, nếu > 500ms thì `Log::warning`. Đăng ký alias trong `bootstrap/app.php`, gắn vào 1 route, thử nghiệm.
3. Dùng `php artisan route:list` — đối chiếu output với `routes/api.php`. Đếm xem `apiResource` sinh ra bao nhiêu dòng.
4. Thêm tạm `dd('here')` lần lượt ở: middleware, Form Request `rules()`, controller, service. Chạy request thật → **xác nhận bằng mắt** thứ tự thực thi.

#### ✅ Tự kiểm tra
- [ ] `permission` middleware chạy trước hay sau `ListPatientsRequest::rules()`? Vì sao thứ tự đó là **đúng** về mặt bảo mật?
- [ ] Nếu route model binding không tìm thấy `Patient` → exception nào → chỗ nào biến nó thành JSON 404?
- [ ] `config('rbac.controllers.PatientController')` trả ra gì? Giá trị đó lấy từ file nào?

---

### GĐ 2 — Routing & Controller

> ⏱️ ~3 ngày.

#### 🎯 Mục tiêu
Tự tay khai báo được mọi kiểu route và viết controller "mỏng" đúng chuẩn.

#### 📚 Cần nắm
- [ ] `Route::get/post/put/patch/delete/match`.
- [ ] Route parameter `{patient}`, optional `{id?}`, ràng buộc `->where('id', '[0-9]+')`.
- [ ] `Route::apiResource()` — 5 action (`index`, `store`, `show`, `update`, `destroy`); `->only()` / `->except()`. So sánh với `Route::resource()` (7 action, có `create`/`edit` cho web).
- [ ] **Route model binding**: implicit (`Patient $patient` theo `id`), custom key (`{patient:code}`), `getRouteKeyName()`.
- [ ] **⚠️ Thứ tự route quan trọng**: route tĩnh phải đặt **trước** route có tham số. Xem `routes/api.php` — `/medicines/low-stock` khai báo **trước** `apiResource('medicines')`; nếu đảo lại, `low-stock` sẽ bị nuốt thành `{medicine}` → lỗi 404 rất khó hiểu. Đây là bug kinh điển, hãy nhớ.
- [ ] `Route::group()`: prefix, middleware, name, namespace.
- [ ] Route name + `route('name')`.
- [ ] **Controller mỏng**: chỉ nhận request → gọi service → trả response. Không có `if` nghiệp vụ, không query DB, không tính toán.
- [ ] `Request` object: `$request->input()`, `query()`, `user()`, `route()`, `is()`, `header()`.

#### 🔍 Đọc trong project này
- `routes/api.php` — chú ý các route đặc biệt đặt trước `apiResource`: `/medicines/low-stock`, `/invoices/{invoice}/status`, `/users/{user}/status`, `/payments/paypal/client-token`.
- `app/Http/Controllers/PatientController.php` — controller CRUD chuẩn nhất project, mỗi method 3–8 dòng. Dùng nó làm khuôn mẫu.
- `app/Http/Controllers/PrescriptionController.php` — controller có nested resource (`items`), khai báo route thủ công vì không hợp `apiResource`.

#### ✍️ Bài tập gõ tay
1. **Không nhìn code**, gõ lại toàn bộ `PatientController` từ trí nhớ. So sánh. Lặp đến khi khớp về cấu trúc (tên biến khác không sao).
2. Trong project: thêm endpoint `GET /api/patients/{patient}/appointments` trả lịch hẹn của một bệnh nhân — tự đặt route đúng chỗ, viết controller method, service method, resource. Xong thì `git checkout .` bỏ đi (đây là bài tập, không phải feature).
3. Cố tình đặt `apiResource('medicines')` **lên trên** `/medicines/low-stock`, gọi thử endpoint, quan sát lỗi. Rồi sửa lại. **Gây lỗi có chủ đích là cách học nhanh nhất.**
4. Đổi `PatientController::show` sang bind theo `code` thay vì `id` (`{patient:code}`), test bằng `curl`.

#### ✅ Tự kiểm tra
- [ ] Liệt kê 5 route mà `Route::apiResource('doctors', DoctorController::class)` sinh ra (method + URI + tên controller method).
- [ ] Vì sao route `updateStatus` được viết tay chứ không nằm trong `apiResource`?
- [ ] Controller được phép chứa những gì, và tuyệt đối không được chứa gì?

---

### GĐ 3 — Validation & Form Request

> ⏱️ ~4 ngày. Đây là tầng bảo vệ đầu tiên của API, và là nơi bạn sẽ viết nhiều code nhất trong thực tế.

#### 🎯 Mục tiêu
Viết được Form Request cho một nghiệp vụ phức tạp mà không cần tra quá vài rule.

#### 📚 Cần nắm
- [ ] `php artisan make:request` — cấu trúc `authorize()` + `rules()` + `messages()` + `attributes()`.
- [ ] **Rule phổ biến** (thuộc nhóm này, còn lại thì tra): `required`, `nullable`, `sometimes`, `string`, `integer`, `numeric`, `boolean`, `array`, `date`, `email`, `min`, `max`, `between`, `in`, `exists`, `unique`, `confirmed`, `regex`, `after`, `before`, `size`, `digits`.
- [ ] `exists:patients,id` và `unique:users,email` — hiểu chúng **chạy query thật**; cách bỏ qua chính bản ghi hiện tại khi update: `Rule::unique('users')->ignore($this->user)`.
- [ ] `sometimes` vs `nullable` vs `required` — khác biệt then chốt khi làm `PATCH`.
- [ ] Validate mảng lồng: `items.*.medicine_id`, `items.*.quantity`.
- [ ] `prepareForValidation()` — chuẩn hoá dữ liệu trước khi validate (trim, ép kiểu).
- [ ] `withValidator()` / `after()` — validate liên trường (vd: `end_time` phải sau `start_time`).
- [ ] `Rule::in()`, `Rule::exists()`, custom Rule object (`php artisan make:rule`).
- [ ] **`$request->validated()` vs `$request->all()`** — `validated()` chỉ trả về field **đã khai báo rule**, nên dữ liệu lạ bị loại bỏ. Dùng `all()` rồi `create()` = lỗ hổng mass assignment.
- [ ] `authorize()` trả `false` → 403. Trong project này luôn `return true` vì phân quyền đã do middleware `permission` lo — hiểu **vì sao tách như vậy**.
- [ ] Validation fail → `ValidationException` → 422 kèm `errors` theo field.

#### 🔍 Đọc trong project này
| File | Học được gì |
|---|---|
| `app/Http/Requests/Patient/ListPatientsRequest.php` | Mẫu đơn giản nhất: filter + phân trang + custom message |
| `app/Http/Requests/Patient/StorePatientRequest.php` | Rule create, `in:` cho gender |
| `app/Http/Requests/Patient/UpdatePatientRequest.php` | So sánh với Store — cái nào thành `sometimes`, vì sao |
| `app/Http/Requests/Prescription/StorePrescriptionRequest.php` | Validate mảng lồng `items.*` |
| `app/Http/Requests/Appointment/StoreAppointmentRequest.php` | Validate ngày giờ, ràng buộc nghiệp vụ |
| `app/Constants/PatientMessage.php` | Pattern gom message vào constant thay vì chuỗi rải rác |

#### ✍️ Bài tập gõ tay
1. Gõ lại `StorePatientRequest` từ trí nhớ, rồi tự thêm: `phone` phải regex số điện thoại VN, `date_of_birth` không được ở tương lai (`before:today`).
2. Viết `StoreAppointmentRequest` từ đầu (xoá file gốc trên nhánh nháp) sao cho `php artisan test --filter=AppointmentTest` xanh trở lại.
3. Viết custom Rule `VietnamesePhone` bằng `make:rule`, áp vào một request.
4. **Thí nghiệm mass assignment**: sửa tạm `PatientService::create()` dùng `$request->all()`, gửi thêm field `"id": 9999` và một field không tồn tại. Quan sát điều gì xảy ra. Hoàn tác. Ghi lại kết luận cho chính mình.

#### ✅ Tự kiểm tra
- [ ] `PATCH /api/patients/1` chỉ gửi `{"phone": "..."}` — rule nào cho phép các field còn lại vắng mặt mà không báo lỗi?
- [ ] Client gửi `per_page=1000` thì nhận status nào, message nào, message đó định nghĩa ở file nào?
- [ ] Vì sao `authorize()` trong project luôn `true` mà API vẫn an toàn?

---

### GĐ 4 — Database, Migration & Eloquent

> ⏱️ ~2 tuần. **Giai đoạn nặng và quan trọng nhất.** Phần lớn bug thật ngoài đời nằm ở tầng này.

#### 🎯 Mục tiêu
Tự thiết kế schema, viết migration, khai báo quan hệ và viết query Eloquent hiệu quả mà không tra quá nhiều.

#### 📚 Cần nắm — Migration
- [ ] `php artisan make:migration`, quy ước đặt tên, `up()` / `down()`.
- [ ] Kiểu cột: `id()`, `string()`, `text()`, `integer()`, `decimal(10,2)`, `boolean()`, `date()`, `timestamp()`, `timestampTz()`, `json()`, `enum()`.
- [ ] `nullable()`, `default()`, `unique()`, `index()`, `unsigned()`, `comment()`.
- [ ] `foreignId('patient_id')->constrained()->cascadeOnDelete()` / `nullOnDelete()` / `restrictOnDelete()`.
- [ ] `timestamps()`, `softDeletes()`.
- [ ] Migration **sửa** bảng có sẵn: `Schema::table()`, `->change()`, `dropColumn()`, `renameColumn()`.
- [ ] `migrate`, `migrate:rollback`, `migrate:fresh --seed`, `migrate:status`. **Không bao giờ sửa migration đã chạy trên production** — luôn tạo migration mới.
- [ ] **Index**: khi nào cần, composite index, thứ tự cột trong composite, partial index (Postgres). Xem `2026_08_28_072434_add_appointments_active_schedule_partial_index.php`.
- [ ] Migration chứa dữ liệu (seed permission): xem `2026_08_05_015300_seed_permissions.php`, `2026_08_28_064255_add_medicines_low_stock_permission.php` — biết khi nào nên làm vậy thay vì dùng seeder.

#### 📚 Cần nắm — Eloquent
- [ ] Model = 1 bảng; quy ước số nhiều, `$table`, `$primaryKey`, `$timestamps`.
- [ ] `$fillable` / `$guarded` — mass assignment. Project dùng attribute `#[Fillable([...])]` (Laravel 12+); biết cả cú pháp property truyền thống vì tutorial cũ dùng nó.
- [ ] `casts()`: `date`, `datetime`, `decimal:2`, `boolean`, `array`, `encrypted`, cast sang Enum.
- [ ] `$hidden` — che `password`, `remember_token`.
- [ ] **Quan hệ**: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany` (bảng pivot), `hasManyThrough`, `morphTo` (polymorphic — xem `ActivityLog`).
- [ ] **Eager loading**: `with()`, `load()`, `withCount()`, `loadMissing()`. **Hiểu N+1 thật sâu** — đây là câu hỏi phỏng vấn số 1 về Laravel.
- [ ] Query builder: `where`, `orWhere`, `whereIn`, `whereBetween`, `whereNull`, `whereHas`, `whereDate`, `orderBy`, `groupBy`, `having`, `select`, `join`.
- [ ] Closure grouping: `where(function ($q) { ... })` — vì sao bắt buộc khi trộn `AND`/`OR`. Xem `Patient::scopeSearch`.
- [ ] `first()`, `firstOrFail()`, `find()`, `findOrFail()`, `get()`, `paginate()`, `exists()`, `count()`, `sum()`, `pluck()`.
- [ ] **Local scope**: `scopeSearch(Builder $query, ...)` → gọi là `Patient::query()->search($term)`. Hiểu cơ chế bỏ tiền tố `scope`.
- [ ] **Soft delete**: `SoftDeletes`, `deleted_at`, `withTrashed()`, `onlyTrashed()`, `restore()`, `forceDelete()`. Query mặc định tự loại bản ghi đã xoá — hiểu global scope đứng sau nó.
- [ ] **Transaction**: `DB::transaction(fn () => ...)`, `beginTransaction/commit/rollBack`. **Khi nào bắt buộc**: nhiều lệnh ghi phải cùng thành công hoặc cùng thất bại.
- [ ] **Locking**: `lockForUpdate()`, `sharedLock()` — chống race condition (vd: trừ tồn kho thuốc, đặt lịch trùng giờ).
- [ ] Accessor / Mutator (`Attribute::make(get:, set:)`).
- [ ] Factory & Seeder: `HasFactory`, `definition()`, `state()`, `Model::factory()->count(10)->create()`.
- [ ] Collection API: `map`, `filter`, `pluck`, `groupBy`, `sum`, `sortBy`, `each`, `when`.
- [ ] `DB::listen()` hoặc Laravel Pail để xem SQL thật được sinh ra.

#### 🔍 Đọc trong project này
| File | Học được gì |
|---|---|
| `database/migrations/2026_08_11_015448_create_appointments_table.php` | FK, index, enum status |
| `database/migrations/2026_08_28_072434_...partial_index.php` | Partial index Postgres — kỹ thuật nâng cao, hiếm tutorial nào dạy |
| `database/migrations/2026_08_19_000000_convert_timestamps_to_timestamptz.php` | Migration đổi kiểu cột trên bảng đã có dữ liệu |
| `app/Models/Patient.php` | Scope, cast, soft delete, xử lý `ILIKE` cho Postgres |
| `app/Models/User.php` | Quan hệ role, `hasPermission()`, `$hidden` |
| `app/Models/Role.php`, `app/Models/Permission.php` | `belongsToMany` qua bảng pivot `role_permissions` |
| `app/Models/Invoice.php`, `Prescription.php` | `hasMany` items, tính tổng tiền |
| `app/Services/PatientService.php` | `DB::transaction` + `forceFill` để sinh code sau khi có `id` |
| `app/Services/StatsService.php` | Aggregate query, `groupBy`, `count`, `sum` |
| `app/Services/MedicineService.php` | Điều chỉnh tồn kho — nơi transaction/lock có ý nghĩa sống còn |

#### ✍️ Bài tập gõ tay
1. **Thiết kế mới trên giấy**: bảng `medical_records` (hồ sơ bệnh án) — cột, kiểu, FK, index. Rồi viết migration thật, chạy `migrate`, rồi `migrate:rollback`. Kiểm tra `down()` có sạch không.
2. Viết model + factory + seeder cho bảng đó, seed 50 bản ghi, query thử trong `php artisan tinker`.
3. **Bài N+1**: trong tinker, chạy
   ```php
   DB::listen(fn ($q) => dump($q->sql));
   Appointment::all()->each(fn ($a) => print_r($a->patient->full_name));   // đếm số query
   Appointment::with('patient')->get()->each(fn ($a) => print_r($a->patient->full_name)); // đếm lại
   ```
   Ghi lại con số. **Nhìn tận mắt là cách duy nhất để nhớ N+1 vĩnh viễn.**
4. Viết scope `scopeUpcoming()` cho `Appointment` (lịch hẹn từ hôm nay trở đi, chưa huỷ), dùng trong `AppointmentService`.
5. **Bài transaction**: trong tinker, viết một `DB::transaction` tạo Invoice + 3 InvoiceItem, cố tình `throw` ở item thứ 3 → xác nhận invoice **không** được lưu. Bỏ transaction đi, chạy lại → xác nhận dữ liệu rác còn lại trong DB.
6. Xoá `app/Services/MedicineService.php`, viết lại đến khi `php artisan test --filter=MedicineTest` xanh.

#### ✅ Tự kiểm tra
- [ ] Vẽ ERD của toàn bộ project từ trí nhớ (10+ bảng, có FK). Đối chiếu với migration.
- [ ] `role_permissions` là quan hệ gì, khai báo trong model như thế nào?
- [ ] Tại sao `PatientService::create()` phải tạo code tạm rồi mới `forceFill` code thật? Đưa việc đó ra ngoài transaction thì hỏng gì?
- [ ] Hai user cùng lúc đặt lịch một bác sĩ vào cùng khung giờ — project chặn bằng cơ chế nào? (gợi ý: unique/partial index + kiểm tra trong service)
- [ ] `withTrashed()` dùng khi nào, và vì sao nếu quên nó thì `unique` validation có thể báo sai?

---

### GĐ 5 — API Resource & chuẩn hoá response

> ⏱️ ~3 ngày.

#### 🎯 Mục tiêu
Kiểm soát tuyệt đối hình dạng JSON trả ra, không để model rò rỉ field.

#### 📚 Cần nắm
- [ ] `php artisan make:resource` — `toArray(Request $request)`.
- [ ] `Resource::collection($models)` vs `new Resource($model)`.
- [ ] Resource lồng nhau: `'patient' => new PatientResource($this->whenLoaded('patient'))`.
- [ ] `whenLoaded()`, `when()`, `mergeWhen()` — trả field có điều kiện (tránh vô tình kích hoạt N+1).
- [ ] Format ngày: `$this->created_at?->toIso8601String()`.
- [ ] Cấu trúc phân trang mặc định của Laravel (`data` + `links` + `meta`) và lý do project chỉ giữ lại 6 key trong `meta`.
- [ ] HTTP status đúng chuẩn: `200` OK, `201` Created, `204` No Content, `400`, `401` (chưa đăng nhập), `403` (đã đăng nhập, không đủ quyền), `404`, `409` (xung đột), `422` (validate fail), `429` (rate limit), `500`.
- [ ] Envelope nhất quán `{success, message, data, errors, meta}` — vì sao frontend cần điều này.

#### 🔍 Đọc trong project này
- `app/Http/Responses/ApiResponse.php` — đọc kỹ 5 method: `success`, `resource`, `collection`, `paginated`, `error`. Chú ý `$resource->resolve(request())` và `Arr::only()` trong `paginated()`.
- `app/Http/Resources/PatientResource.php` — mẫu đơn giản.
- `app/Http/Resources/InvoiceResource.php` + `InvoiceItemResource.php` — resource lồng nhau.
- `tests/Feature/ApiResponseTest.php` — test chốt hình dạng envelope. Đây là **spec sống** của API.
- `docs/form-request-api-resource-envelope-json-chuan.md` — tài liệu bạn đã có sẵn về chủ đề này.

#### ✍️ Bài tập gõ tay
1. Gõ lại `ApiResponse` từ đầu, không nhìn, cho tới khi `php artisan test --filter=ApiResponseTest` xanh.
2. Viết `MedicalRecordResource` cho bảng bạn tạo ở GĐ 4, có nested patient + doctor, dùng `whenLoaded`.
3. Thêm tạm một field nhạy cảm (ví dụ `internal_note`) vào bảng `patients`; chứng minh nó **không** rò ra ngoài nhờ Resource, còn nếu `return response()->json($patient)` thì nó rò ngay.
4. So sánh JSON của `->response()` mặc định và của `ApiResponse::paginated()` bằng 2 lệnh `curl`. Viết 3 dòng giải thích vì sao project chọn cách sau.

#### ✅ Tự kiểm tra
- [ ] Khác nhau giữa `ApiResponse::resource()` và `ApiResponse::collection()`?
- [ ] `whenLoaded('patient')` khác `$this->patient` chỗ nào, và vì sao khác biệt đó liên quan tới hiệu năng?
- [ ] Tạo mới thành công trả `200` hay `201`? Xoá thành công trả gì? Trong project đang trả gì và vì sao?

---

### GĐ 6 — Service layer, Container & Dependency Injection

> ⏱️ ~4 ngày. Đây là phần "kiến trúc" — thứ phân biệt code chạy được với code bảo trì được.

#### 🎯 Mục tiêu
Hiểu **vì sao** project chia Controller / Service / Model, và tự quyết định được logic nào đặt ở đâu.

#### 📚 Cần nắm
- [ ] **Service Container** là gì: bảng đăng ký + tự động dựng object (autowiring) qua Reflection.
- [ ] **Dependency Injection**: constructor injection, method injection. Vì sao Laravel tự bơm được `PatientService` mà không cần `new`.
- [ ] `app()->bind()` vs `singleton()` vs `instance()` — vòng đời object.
- [ ] Bind interface → implementation trong `AppServiceProvider` (cách chuẩn để mock trong test, và để thay `PayPalService` bằng gateway khác).
- [ ] **Facade** (`DB::`, `Log::`, `Auth::`, `Cache::`) thực chất chỉ là proxy tĩnh tới object trong container.
- [ ] Nguyên tắc phân tầng của project này:
  - **Controller** — HTTP: nhận request, gọi service, trả response. Không nghiệp vụ.
  - **Form Request** — hợp lệ về *hình dạng dữ liệu*.
  - **Service** — nghiệp vụ: transaction, quy tắc, phối hợp nhiều model.
  - **Model** — dữ liệu + quan hệ + scope truy vấn.
  - **Resource** — hình dạng JSON đầu ra.
  - **Middleware** — mối quan tâm cắt ngang (auth, quyền, log, rate limit).
- [ ] Khi nào tách thêm: Action class, Repository, DTO — và **khi nào KHÔNG nên** (over-engineering là bệnh phổ biến của người mới học kiến trúc).
- [ ] `app/Constants/*` — vì sao gom message vào constant thay vì hardcode chuỗi.

#### 🔍 Đọc trong project này
- `app/Services/PatientService.php` — service đơn giản nhất, đọc trước.
- `app/Services/InvoiceService.php`, `PrescriptionService.php` — service phức tạp: nhiều model, transaction, tính tiền.
- `app/Services/PayPalService.php` — service gọi API ngoài; để ý cách nó **không** phụ thuộc HTTP request.
- `app/Services/ActivityLogger.php` — service dùng chung, được gọi từ nhiều Observer.
- `app/Providers/AppServiceProvider.php` — nơi đăng ký observer/binding.

#### ✍️ Bài tập gõ tay
1. Viết `interface NotificationChannel` + 2 implementation (`LogChannel`, `FakeSmsChannel`), bind trong `AppServiceProvider`, inject vào một service, gọi từ controller. **Đây là bài tập quan trọng nhất của GĐ 6** — nó nối GĐ 0 (interface) với Laravel container.
2. Refactor có chủ đích: bê toàn bộ logic của `SpecialtyService` vào thẳng `SpecialtyController`, chạy test (vẫn xanh). Rồi tự trả lời: *code nào dễ test hơn? dễ tái dùng ở artisan command hơn? dễ đọc hơn?* Hoàn tác.
3. Trong tinker: `app(PatientService::class)` và `app()->bound(PatientService::class)` — quan sát container hoạt động.
4. Đổi `bind` thành `singleton` cho một service có thuộc tính đếm, chứng minh sự khác biệt về vòng đời trong 1 request.

#### ✅ Tự kiểm tra
- [ ] Giải thích cho một dev PHP thuần: "Laravel tự biết truyền gì vào constructor" hoạt động ra sao?
- [ ] Logic "hoá đơn đã thanh toán thì không cho sửa" nên nằm ở tầng nào? Vì sao không phải Form Request?
- [ ] Nếu mai đổi PayPal sang VNPay, cần sửa những file nào? Kiến trúc hiện tại giúp hay cản?

---

### GĐ 7 — Authentication (Sanctum) & Authorization (RBAC)

> ⏱️ ~4 ngày.

#### 🎯 Mục tiêu
Tự dựng được authentication bằng token và một hệ phân quyền từ số 0.

#### 📚 Cần nắm — Authentication
- [ ] Guard & Provider trong `config/auth.php`.
- [ ] Hai chế độ của Sanctum: **API token** (mobile/third-party, dùng header `Bearer`) và **SPA cookie** (same-domain). Project này dùng chế độ nào?
- [ ] `HasApiTokens`, `createToken()`, `personal_access_tokens` — token được **hash** trong DB, plain text chỉ hiện đúng một lần.
- [ ] Middleware `auth:sanctum` kiểm tra gì trên mỗi request.
- [ ] `$user->currentAccessToken()->delete()` (logout 1 thiết bị) vs `$user->tokens()->delete()` (logout tất cả).
- [ ] `Hash::make()` / `Hash::check()` — vì sao **không bao giờ** lưu password thô, vì sao bcrypt chậm là *tính năng* chứ không phải khuyết điểm.
- [ ] Token abilities/scopes (`createToken('name', ['patients:read'])`).
- [ ] So sánh với JWT: khác gì, khi nào chọn cái nào.

#### 📚 Cần nắm — Authorization
- [ ] 3 cách trong Laravel: **Gate**, **Policy**, và **middleware tự viết** (project chọn cách 3).
- [ ] Mô hình RBAC: `users` → `roles` → `role_permissions` → `permissions`.
- [ ] Cơ chế của `EnsurePermission`: lấy `Controller@method` từ route → tra `config/rbac.php` → ghép `"{resource}.{action}"` → gọi `$user->hasPermission()`. **Hiểu được vì sao cách này giúp không phải gắn tên permission thủ công lên từng route.**
- [ ] `config('rbac.overrides')` — xử lý ngoại lệ, ví dụ `MedicineController@lowStock`.
- [ ] `401` vs `403` — hai thứ hoàn toàn khác nhau, đừng lẫn.
- [ ] Policy (project chưa dùng nhưng phải biết): `php artisan make:policy`, `$this->authorize()`, `Gate::allows()` — dùng khi quyền phụ thuộc **từng bản ghi** ("bác sĩ chỉ sửa được lịch hẹn của chính mình"), thứ mà RBAC theo endpoint không diễn đạt được.

#### 🔍 Đọc trong project này
- `app/Http/Middleware/EnsurePermission.php` — **đọc từng dòng**, đây là trái tim phân quyền.
- `config/rbac.php` — bảng ánh xạ.
- `app/Models/User.php` → `hasPermission()`.
- `app/Services/AuthService.php` + `app/Http/Controllers/AuthController.php` — login/logout/me.
- `database/migrations/2026_08_05_0153*_seed_permissions.php` — cách gieo danh mục quyền.
- `tests/Feature/EnsurePermissionTest.php`, `tests/Feature/AuthTest.php`.

#### ✍️ Bài tập gõ tay
1. **Bài lớn**: trong một project Laravel **mới tinh** (`laravel new auth-practice`), dựng lại từ số 0: bảng users → login trả token → middleware `auth:sanctum` → endpoint `/me`. Không copy từ clinic-manage. Đây là bài kiểm tra thật xem bạn có tự làm auth được không.
2. Trong project này: thêm role `RECEPTIONIST` chỉ có quyền `patients.*` và `appointments.*`; viết test chứng minh role đó bị `403` khi gọi `/api/medicines`.
3. Viết một Policy `AppointmentPolicy` cho quy tắc "bác sĩ chỉ được cập nhật lịch hẹn của chính mình", áp vào `AppointmentController::update`. So sánh trải nghiệm với middleware RBAC — ghi lại khi nào nên dùng cái nào.
4. Trong tinker: `User::first()->createToken('test')->plainTextToken`, rồi `curl -H "Authorization: Bearer <token>" .../api/me`. Sau đó xem bảng `personal_access_tokens` — **xác nhận bằng mắt** rằng token trong DB không giống chuỗi bạn cầm.

#### ✅ Tự kiểm tra
- [ ] Vẽ luồng: từ lúc client `POST /api/login` đến lúc gọi được `/api/patients` — mọi bước, mọi bảng liên quan.
- [ ] `PatientController@index` → permission gì? Truy vết từng dòng trong `EnsurePermission`.
- [ ] Gửi request không có header `Authorization` → 401 hay 403? Có token nhưng thiếu quyền → mã nào?
- [ ] Khi nào RBAC theo endpoint là **không đủ**, bắt buộc phải dùng Policy?

---

### GĐ 8 — Xử lý lỗi tập trung

> ⏱️ ~2 ngày. Ngắn nhưng là dấu hiệu rõ nhất của một API "chuyên nghiệp" so với API "sinh viên".

#### 🎯 Mục tiêu
Đọc hiểu và tự viết được toàn bộ khối `->withExceptions()` trong `bootstrap/app.php`.

#### 📚 Cần nắm
- [ ] `report()` (ghi log) vs `render()` (trả response cho client) — hai trách nhiệm tách biệt.
- [ ] `$exceptions->render(fn (XException $e, Request $r) => ...)` — đăng ký theo **kiểu exception**; thứ tự khai báo quyết định cái nào bắt trước (cụ thể trước, tổng quát sau).
- [ ] `shouldRenderJsonWhen()` — vì sao API phải trả JSON chứ không phải trang HTML lỗi.
- [ ] `stopIgnoring()` — Laravel mặc định **không log** lỗi client (422/404/403); project cố tình bật lại để mọi request bị từ chối đều để lại dấu vết.
- [ ] `$exceptions->context()` — đính kèm method/url/user_id/ip/duration vào mọi log entry.
- [ ] `abort(403)`, `abort_if()`, `abort_unless()`, exception tự định nghĩa.
- [ ] **Rò rỉ thông tin**: `ModelNotFoundException` mặc định lộ tên model + id; project ẩn đi qua `ExceptionMessage::RESOURCE_NOT_FOUND`. Hiểu vì sao đó là vấn đề bảo mật.
- [ ] `config('app.debug')` — production **phải** `false`, nếu không stack trace lộ ra ngoài.
- [ ] Logging: `config/logging.php`, các channel, level (`debug`/`info`/`warning`/`error`), `Log::warning($msg, $context)`, log có cấu trúc.

#### 🔍 Đọc trong project này
- `bootstrap/app.php`, khối `->withExceptions()` — đọc **theo thứ tự từ trên xuống**, hiểu vì sao `HttpExceptionInterface` đặt gần cuối và `Throwable` đặt cuối cùng.
- `app/Constants/ExceptionMessage.php`.
- `docs/system-logs.md`, `docs/activity-logs.md` — tài liệu bạn đã có.
- `tests/Feature/SystemLogTest.php`.

#### ✍️ Bài tập gõ tay
1. Trên nhánh nháp, xoá toàn bộ khối `->withExceptions()`. Chạy `php artisan test` → xem bao nhiêu test đỏ. **Số test đỏ chính là giá trị của khối code đó.** Rồi viết lại từng handler một, mỗi lần viết xong chạy test lại.
2. Tạo exception nghiệp vụ riêng: `App\Exceptions\InsufficientStockException` (409), ném từ `MedicineService` khi trừ kho quá số lượng, đăng ký render → JSON 409. Viết test.
3. Gọi một endpoint không tồn tại, sai method, thiếu token, sai quyền, sai validate → thu thập đủ 5 response, dán vào một file nháp, **xác nhận cả 5 đều cùng envelope**.
4. Bật `APP_DEBUG=false`, cố tình gây lỗi 500, xác nhận client **không** thấy stack trace mà log **có**.

#### ✅ Tự kiểm tra
- [ ] Vì sao `report()` phải tự suy ra status từ exception thay vì đọc từ response?
- [ ] Handler `Throwable` cuối cùng để làm gì, xoá đi thì client nhận được gì?
- [ ] Lỗi 422 và lỗi 500 khác nhau thế nào **trong log**? (level nào, có stack trace không, vì sao)

---

### GĐ 9 — Testing

> ⏱️ ~1 tuần. **Kỹ năng đòn bẩy lớn nhất của bạn.** Có test = có thể xoá code và viết lại mà vẫn biết mình đúng. Toàn bộ chiến lược học trong file này dựa vào test.

#### 🎯 Mục tiêu
Tự viết feature test cho một module mới, và dùng test làm lưới an toàn khi luyện "xoá-và-viết-lại".

#### 📚 Cần nắm
- [ ] Project dùng **PHPUnit** (không phải Pest) — `tests/Feature`, `tests/Unit`, `tests/TestCase.php`, `phpunit.xml`.
- [ ] Feature test vs Unit test — thực tế API thì ~90% là feature test.
- [ ] `RefreshDatabase` — migrate + transaction rollback sau mỗi test; vì sao test không làm bẩn dữ liệu.
- [ ] HTTP helper: `getJson`, `postJson`, `putJson`, `patchJson`, `deleteJson`.
- [ ] Assertion: `assertOk`, `assertCreated`, `assertStatus(422)`, `assertUnauthorized`, `assertForbidden`, `assertJson`, `assertJsonStructure`, `assertJsonCount`, `assertJsonPath`, `assertJsonValidationErrors`.
- [ ] DB assertion: `assertDatabaseHas`, `assertDatabaseMissing`, `assertSoftDeleted`, `assertDatabaseCount`.
- [ ] `Sanctum::actingAs($user)` — bỏ qua bước login khi test nghiệp vụ.
- [ ] Factory: `Model::factory()->create()`, `make()`, `count()`, `state()`, override thuộc tính.
- [ ] Seeder trong test: `$this->seed([RoleSeeder::class, RbacSeeder::class])` (xem `setUp()` của `PatientTest`).
- [ ] Fake: `Http::fake()` (test PayPal mà không gọi mạng thật), `Queue::fake()`, `Mail::fake()`, `Event::fake()`, `Log::spy()`.
- [ ] `travelTo()` / `Carbon::setTestNow()` — test logic phụ thuộc thời gian.
- [ ] Chạy chọn lọc: `php artisan test --filter=PatientTest`, `--filter=test_admin_can_...`.
- [ ] Test đặt tên như một câu khẳng định: `test_admin_can_complete_patient_crud_with_generated_codes_and_soft_delete`.
- [ ] Vòng lặp **đỏ → xanh → refactor** (TDD). Bạn không cần TDD nghiêm ngặt, nhưng phải trải nghiệm nó ít nhất một module.

#### 🔍 Đọc trong project này
| File | Học được gì |
|---|---|
| `tests/Feature/PatientTest.php` | Mẫu CRUD test chuẩn — đọc đầu tiên |
| `tests/Feature/EnsurePermissionTest.php` | Test middleware phân quyền |
| `tests/Feature/ClinicalFlowTest.php` | Test **luồng end-to-end** nhiều bước: khám → kê đơn → hoá đơn → thanh toán |
| `tests/Feature/PaymentTest.php` | `Http::fake()` cho API ngoài |
| `tests/Feature/ApiResponseTest.php` | Chốt hình dạng envelope |
| `tests/TestCase.php` | Helper dùng chung (`createUser`, ...) |
| `database/factories/` | Cách sinh dữ liệu giả |

#### ✍️ Bài tập gõ tay
1. **TDD thật sự một lần**: viết `tests/Feature/MedicalRecordTest.php` **trước**, chạy → đỏ, rồi mới viết migration/model/service/controller cho tới khi xanh. Trải nghiệm này thay đổi cách bạn viết code mãi mãi.
2. Với mỗi module trong project, thêm 1 test case còn thiếu (ví dụ: bệnh nhân đã bị soft delete thì không hiện trong list; `per_page=101` bị 422).
3. Chạy `php artisan test` toàn bộ, ghi lại thời gian và số test. Đây là "nhịp tim" của project bạn.
4. **Bài tập trung tâm của cả roadmap**: chọn module `Specialty` → xoá sạch Controller + Service + Resource + Requests → viết lại từ trí nhớ đến khi `--filter=SpecialtyTest` xanh. Xong thì làm tiếp `Doctor`, rồi `Patient`, rồi `Medicine` (tăng dần độ khó). Đây là bài luyện quan trọng nhất trong 12 tuần.

#### ✅ Tự kiểm tra
- [ ] `RefreshDatabase` thiếu thì chuyện gì xảy ra ở test thứ hai?
- [ ] Vì sao phải `Http::fake()` khi test PayPal, không fake thì rủi ro gì?
- [ ] Viết một test hoàn chỉnh kiểm tra "user không đủ quyền nhận 403" — gõ từ trí nhớ, đủ 5 dòng.

---

### GĐ 10 — Chủ đề nâng cao

> ⏱️ ~2 tuần. Đây là phần tạo khác biệt khi đi phỏng vấn hoặc lên production.

#### 10.1 Event, Listener & Observer
- [ ] Event/Listener: `make:event`, `make:listener`, `event()`, đăng ký tự động.
- [ ] Model Observer: `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`, `restored`.
- [ ] **`creating` vs `created`** — cái nào chạy trước khi có `id`? Đây là nguồn bug kinh điển.
- [ ] Observer trong transaction: nếu transaction rollback thì observer đã ghi log rồi — giải quyết bằng `DB::afterCommit()` hoặc `ShouldHandleEventsAfterCommit`.
- 🔍 **Đọc**: `app/Observers/*.php` (7 file), `app/Services/ActivityLogger.php`, nơi đăng ký trong `app/Providers/AppServiceProvider.php`.
- ✍️ **Bài tập**: viết `MedicineObserver` ghi log mỗi lần tồn kho về dưới ngưỡng; test bằng `Log::spy()`.

#### 10.2 Queue & Job
- [ ] Vì sao cần queue: tách việc chậm (gửi mail, gọi API ngoài, xuất báo cáo) ra khỏi request.
- [ ] `make:job`, `dispatch()`, `ShouldQueue`, `php artisan queue:work` vs `queue:listen`.
- [ ] `tries`, `backoff`, `timeout`, `failed()`, bảng `failed_jobs`, `queue:retry`.
- [ ] Driver: `sync` (dev), `database` (project đang dùng — xem `.env.example`), `redis` (production).
- ✍️ **Bài tập**: chuyển việc ghi `ActivityLog` sang Job, chạy `queue:work`, quan sát bảng `jobs` khi worker tắt.

#### 10.3 Cache
- [ ] `Cache::remember($key, $ttl, fn () => ...)`, `forget`, `flush`, tag.
- [ ] Chọn cache key, và **chiến lược invalidate** (phần khó nhất).
- [ ] Driver: `database` (hiện tại), `redis`, `file`, `array` (test).
- [ ] `config:cache`, `route:cache`, `view:cache` cho production — và cái bẫy `env()` đi kèm.
- ✍️ **Bài tập**: cache kết quả `StatsService`, xoá cache khi có hoá đơn mới (qua Observer). Đo thời gian trước/sau.

#### 10.4 Rate limiting *(bạn đang làm task T4.7 — xem `docs/rate-limiting.md`)*
- [ ] `RateLimiter::for()` trong Service Provider, middleware `throttle:name`.
- [ ] Chọn key: theo IP, theo user, theo email đăng nhập.
- [ ] Header `X-RateLimit-Limit` / `X-RateLimit-Remaining` / `Retry-After`, status `429`.
- [ ] Vì sao endpoint `/login` cần giới hạn chặt hơn hẳn phần còn lại (chống brute-force).

#### 10.5 Hiệu năng
- [ ] Săn N+1 bằng `DB::listen()` / Laravel Pail / `Model::preventLazyLoading()` trong môi trường local.
- [ ] `select()` chỉ lấy cột cần, `chunk()` / `lazy()` / `cursor()` cho dữ liệu lớn.
- [ ] `EXPLAIN ANALYZE` trong Postgres — đọc query plan, biết index có được dùng không.
- [ ] Index: composite, partial, và **cái giá phải trả khi ghi**.
- 🔍 **Đọc**: `database/migrations/2026_08_28_072434_add_appointments_active_schedule_partial_index.php` — bạn đã làm nó ở task T4.6; giờ hãy giải thích lại được **vì sao** partial index nhanh hơn index thường ở đây.

#### 10.6 Concurrency
- [ ] Race condition: hai request cùng lúc trừ tồn kho / đặt cùng khung giờ.
- [ ] `lockForUpdate()`, mức isolation của transaction.
- [ ] Unique constraint ở DB làm **chốt chặn cuối** — vì sao kiểm tra ở tầng PHP là **không đủ**.
- ✍️ **Bài tập**: viết script bắn 20 request đồng thời (`xargs -P20 curl`) vào endpoint trừ kho, xem tồn kho có âm không. Nếu có → sửa bằng lock. Đây là bài học không tutorial nào dạy được bằng.

#### 10.7 Artisan Command
- [ ] `make:command`, `signature`, `handle()`, argument/option, output (`info`, `error`, `table`, progress bar).
- [ ] Scheduler: `routes/console.php`, `Schedule::command()->daily()`, một dòng cron duy nhất trên server.
- ✍️ **Bài tập**: command `clinic:low-stock-report` in bảng thuốc sắp hết, hẹn chạy hằng ngày.

#### 10.8 Tài liệu API *(đã ghi nhận là task riêng)*
- [ ] Scribe hoặc L5-Swagger sinh OpenAPI từ annotation/PHPDoc.
- [ ] Giữ Postman collection đồng bộ với `routes/api.php`.

---

### GĐ 11 — Vận hành: Docker, env, deploy, CI

> ⏱️ ~4 ngày.

- [ ] `Dockerfile` + `docker-compose.yml` của project: service nào, port nào, volume nào. Vì sao `DB_HOST=db` chứ không phải `localhost`.
- [ ] `.env` vs `.env.example` — cái nào vào git, vì sao. `APP_KEY` dùng làm gì (mã hoá cookie/session) và mất nó thì sao.
- [ ] Khác biệt local ↔ production: `APP_ENV`, `APP_DEBUG=false`, `config:cache`, `route:cache`, `optimize`, `migrate --force`.
- [ ] Quyền thư mục `storage/` và `bootstrap/cache/`.
- [ ] `php artisan pail` — xem log realtime.
- [ ] `laravel/pint` — format code, `./vendor/bin/pint --test` trong CI.
- [ ] CI cơ bản với GitHub Actions: checkout → composer install → copy env → key:generate → migrate → `php artisan test` → pint.
- [ ] Git flow project đang dùng: nhánh `task/<user>/<mã task>-<mô tả>` → PR → merge vào `main`.
- ✍️ **Bài tập**: viết `.github/workflows/ci.yml` chạy test + pint trên mỗi PR, có service Postgres.

---

## Phần D — Dự án tốt nghiệp: viết một API từ số 0, KHÔNG dùng AI

> **Đây là bài kiểm tra cuối cùng.** Nếu làm được bài này, câu hỏi "tôi có tự viết được project như thế này không" đã có câu trả lời là **có**.

### Luật chơi
- ✅ Được mở: Laravel docs, `laravel-manage` của chính bạn (**để đối chiếu sau khi đã tự viết xong một phần**), Stack Overflow cho lỗi cụ thể.
- ❌ Không được: hỏi AI viết code, copy-paste nguyên khối từ clinic-manage.
- ⏱️ Mục tiêu: 2 tuần. Chậm hơn cũng được, **nhưng không được bỏ dở**.

### Đề bài: **API Quản lý thư viện** (`library-api`)

Chọn đề khác domain với phòng khám có chủ đích — để bạn không nhớ máy móc, mà phải *thiết kế*.

#### Nghiệp vụ
Thư viện có sách (nhiều bản sao mỗi đầu sách), thành viên mượn/trả sách, quá hạn thì tính phí phạt.

#### Yêu cầu bắt buộc

**1. Schema (tự thiết kế, vẽ ERD trước khi code)**
- [ ] `users` (thủ thư + admin), `roles`, `permissions`, `role_permissions`
- [ ] `authors`, `categories`
- [ ] `books` (title, isbn unique, author_id, category_id, published_year)
- [ ] `book_copies` (book_id, barcode unique, status: available/borrowed/lost)
- [ ] `members` (mã thành viên tự sinh, họ tên, sđt, email)
- [ ] `loans` (member_id, book_copy_id, borrowed_at, due_at, returned_at nullable)
- [ ] `fines` (loan_id, amount, paid_at nullable)

**2. Tính năng**
- [ ] `POST /api/login`, `POST /api/logout`, `GET /api/me` (Sanctum)
- [ ] CRUD đầy đủ: authors, categories, books, members
- [ ] `GET /api/books` có tìm kiếm (title/isbn/tên tác giả), lọc theo category, phân trang
- [ ] `POST /api/loans` — mượn sách: kiểm tra còn bản sao rảnh, thành viên chưa quá hạn mức 5 cuốn, chưa có sách quá hạn. Trong transaction, đổi `book_copies.status`.
- [ ] `POST /api/loans/{loan}/return` — trả sách: cập nhật `returned_at`, đổi status bản sao, nếu trễ thì tự tạo `fine` (1.000đ/ngày).
- [ ] `GET /api/loans?status=overdue` — danh sách quá hạn
- [ ] `POST /api/fines/{fine}/pay`
- [ ] `GET /api/stats` — tổng sách, đang mượn, quá hạn, tiền phạt chưa thu

**3. Chất lượng (phần được chấm điểm thật sự)**
- [ ] Envelope JSON nhất quán qua một class `ApiResponse` tự viết
- [ ] Form Request cho **mọi** endpoint có input
- [ ] API Resource cho **mọi** output
- [ ] Service layer — controller không quá 10 dòng/method
- [ ] RBAC: role `ADMIN` (toàn quyền) / `LIBRARIAN` (không quản lý user)
- [ ] Xử lý lỗi tập trung trong `bootstrap/app.php`
- [ ] Soft delete cho books, members
- [ ] Index hợp lý (ít nhất: `isbn`, `barcode`, `loans.due_at`, composite cho tra cứu loan đang mở)
- [ ] Feature test cho: auth, RBAC, CRUD books, luồng mượn→trả→phạt, các case biên (mượn khi hết bản sao, mượn quá hạn mức)
- [ ] Seeder tạo dữ liệu demo chạy được ngay
- [ ] README: cách chạy, danh sách endpoint, tài khoản mẫu
- [ ] Postman collection

#### Checkpoint theo ngày
| Ngày | Việc | Xong khi |
|---|---|---|
| 1 | ERD + `laravel new` + docker/DB + migrations | `migrate` chạy sạch |
| 2 | Models + quan hệ + factories + seeders | tinker query được mọi quan hệ |
| 3 | Auth Sanctum + bảng RBAC + middleware permission | login trả token, sai quyền → 403 |
| 4 | `ApiResponse` + handler lỗi trong `bootstrap/app.php` | 5 loại lỗi đều cùng envelope |
| 5–6 | CRUD authors, categories, books (+ tìm kiếm, phân trang) | Postman chạy đủ |
| 7 | CRUD members + book_copies | |
| 8–9 | Nghiệp vụ mượn/trả (transaction + quy tắc + fine) | Luồng chạy thông |
| 10 | Stats + báo cáo quá hạn | |
| 11–12 | Feature test toàn bộ | `php artisan test` xanh |
| 13 | Index + săn N+1 + tối ưu | không còn N+1 ở list endpoint |
| 14 | README + Postman + dọn code + pint | Người khác clone về chạy được |

#### Sau khi xong
1. **Chỉ khi đó** mới mở `clinic-manage` ra so sánh từng tầng. Ghi lại chỗ nào project cũ làm tốt hơn và **vì sao**.
2. Bây giờ mới đưa cho AI review, với prompt: *"Review kiến trúc, bảo mật và hiệu năng của code này. Chỉ ra vấn đề, giải thích tại sao là vấn đề, đừng viết code sửa hộ tôi."*
3. Tự sửa từng điểm được chỉ ra.

---

## Phần E — Lịch 12 tuần chi tiết

| Tuần | Giai đoạn | Sản phẩm phải có cuối tuần |
|---|---|---|
| **1** | GĐ 0 — PHP hiện đại | `/tmp/php-practice/` có interface + DI thủ công + enum + exception, autoload PSR-4 chạy được |
| **2** | GĐ 1 + GĐ 2 | Sơ đồ luồng request vẽ tay; middleware `LogSlowRequests` tự viết; gõ lại `PatientController` từ trí nhớ |
| **3** | GĐ 3 | Gõ lại 3 Form Request từ trí nhớ; 1 custom Rule; hiểu rõ mass assignment |
| **4** | GĐ 4 (phần 1) — Migration + Model | Migration `medical_records` tự thiết kế; model + factory + seeder; ERD toàn project vẽ từ trí nhớ |
| **5** | GĐ 4 (phần 2) — Query + N+1 + transaction | Bài N+1 có số liệu; viết lại `MedicineService` đến khi test xanh |
| **6** | GĐ 5 + GĐ 6 | Gõ lại `ApiResponse` từ đầu; bài `NotificationChannel` interface + binding |
| **7** | GĐ 7 | Dựng auth Sanctum trong project **mới tinh**, không copy; thêm role mới + test 403 |
| **8** | GĐ 8 + GĐ 9 | Viết lại khối `withExceptions` từng handler; **xoá-và-viết-lại** module `Specialty` rồi `Doctor` |
| **9** | GĐ 10 | Observer mới + Job + cache stats + đo hiệu năng + bài race condition |
| **10** | GĐ 11 + khởi động Phần D | CI chạy được; ERD `library-api` + migrations xong |
| **11** | Phần D | Checkpoint ngày 1–9 |
| **12** | Phần D | Checkpoint ngày 10–14; project chạy được, test xanh, README đầy đủ |

**Mỗi ngày (~1.5h):** 40% đọc/xem · **60% gõ code**. Nếu hôm nào chỉ có 30 phút → **gõ code, bỏ phần đọc**. Đọc không thiếu thì bù được, gõ thiếu thì không.

**Cuối mỗi tuần (30 phút):** viết vào một file nhật ký — *tuần này học gì · chỗ nào còn mù mờ · tuần sau phải làm rõ gì*. Nhật ký này quan trọng hơn bạn nghĩ: nó biến "học lan man" thành "học có nợ và có trả".

---

## Phần F — Cách dùng AI để học nhanh hơn (thay vì để nó thay bạn)

AI không phải kẻ thù của việc học — **cách dùng sai** mới là. Bạn đã có một codebase chất lượng nhờ AI; giờ hãy đổi vai trò của nó từ *thợ code* sang *gia sư*.

### ❌ Prompt làm bạn không tiến bộ
> "Viết cho tôi CRUD cho bảng books trong Laravel"

### ✅ Prompt làm bạn tiến bộ

| Mục đích | Prompt mẫu |
|---|---|
| **Giải thích** | "Giải thích từng dòng `app/Http/Middleware/EnsurePermission.php`. Với mỗi dòng nói rõ nếu xoá nó đi thì hỏng gì." |
| **Ra đề** | "Cho tôi 5 bài tập tăng dần về Eloquent relationship dựa trên schema này. Chỉ đề bài, **không** đưa lời giải." |
| **Chấm bài** | "Đây là code tôi tự viết. Chỉ ra lỗi và giải thích tại sao sai. **Đừng viết lại code cho tôi** — tôi muốn tự sửa." |
| **Đối chiếu** | "Tôi viết theo cách A, project mẫu viết theo cách B. So sánh ưu nhược, khi nào nên chọn cái nào?" |
| **Truy vấn ngược** | "Nếu bỏ `DB::transaction` trong `PatientService::create()`, hãy dựng một kịch bản cụ thể khiến dữ liệu hỏng." |
| **Vá lỗ hổng** | "Dựa vào những gì tôi vừa hỏi, bạn đoán tôi đang **hiểu sai** hoặc **chưa biết** khái niệm nào?" — prompt này cực kỳ hữu ích, hãy hỏi mỗi cuối tuần. |
| **Kiểm tra** | "Hỏi tôi 10 câu về Eloquent, mỗi lần 1 câu, chấm câu trả lời của tôi rồi mới hỏi tiếp." |

### Quy tắc 3 lần
Trước khi hỏi AI về **cú pháp**, tự làm đủ 3 bước: (1) đoán, (2) tra docs, (3) thử chạy. Nếu vẫn sai → mới hỏi, và hỏi *"tại sao cách của tôi sai"*, không hỏi *"cách đúng là gì"*.

### Cảnh báo
Khi bạn thấy mình **copy code AI mà không đọc hết** — dừng lại ngay. Đó là dấu hiệu bạn đang tích nợ kiến thức. Nợ này đến hạn vào ngày production sập lúc 2h sáng và không có ai để hỏi.

---

## Phần G — Tài nguyên

### Chính thống (ưu tiên tuyệt đối)
| Nguồn | Dùng khi |
|---|---|
| https://laravel.com/docs/13.x | Nguồn chân lý. **Nhớ chọn đúng version 13.x** — Laravel 11+ đổi cấu trúc rất nhiều, tutorial cũ sẽ làm bạn lạc |
| https://laravel.com/api/13.x | Tra chữ ký method chính xác |
| https://laravel.com/docs/13.x/validation#available-validation-rules | Bookmark riêng — bạn sẽ mở nó mỗi ngày |
| https://laravel.com/docs/13.x/eloquent-relationships | Đọc **toàn bộ** ít nhất một lần |
| https://laravel.com/docs/13.x/queries | Query builder |
| https://php.net/manual/en | Tra hàm PHP |

### Học có hệ thống
- **Laracasts** — "Laravel From Scratch" (miễn phí) và "PHP for Beginners". Chất lượng cao nhất trong hệ sinh thái Laravel.
- **PHP The Right Way** (phptherightway.com) — nền tảng PHP hiện đại, đọc trong GĐ 0.
- **Laravel Bootcamp** (bootcamp.laravel.com) — build một app nhỏ có hướng dẫn, làm trong tuần 2.
- **Laravel Daily** (YouTube) — video ngắn, rất thực dụng, hợp xem lúc nghỉ.

### Đọc thêm khi đã vững
- *Laravel: Up & Running* — Matt Stauffer (sách tham chiếu tốt nhất)
- *Refactoring to Collections* — Adam Wathan
- Laravel News (laravel-news.com) — cập nhật hệ sinh thái
- Nguồn code chất lượng để đọc: mã nguồn `laravel/framework` trong `vendor/` — khi tò mò `paginate()` làm gì, hãy **mở ra đọc**, đó là đặc quyền của PHP mã nguồn mở.

### Công cụ nên cài
- [ ] **Laravel Pail** (`php artisan pail`) — đã có trong project
- [ ] **Laravel Tinker** — REPL, dùng mỗi ngày
- [ ] **Laravel Pint** — format, đã có
- [ ] **Larastan/PHPStan** — static analysis, bắt lỗi type trước khi chạy (nên thêm)
- [ ] **Laravel Debugbar** (dev) — đếm query, phát hiện N+1 tức thì (nên thêm)
- [ ] **Postman/Insomnia** — đã có collection sẵn

---

## Phần H — Bảng theo dõi tiến độ

Tự đánh dấu. Chỉ tick khi **đã làm xong bài tập gõ tay**, không tick khi chỉ mới đọc hiểu.

| GĐ | Chủ đề | Đọc hiểu | Bài tập gõ tay | Tự viết được |
|---|---|:---:|:---:|:---:|
| 0 | PHP hiện đại | ☐ | ☐ | ☐ |
| 1 | Vòng đời request | ☐ | ☐ | ☐ |
| 2 | Routing & Controller | ☐ | ☐ | ☐ |
| 3 | Validation & Form Request | ☐ | ☐ | ☐ |
| 4 | Migration & Eloquent | ☐ | ☐ | ☐ |
| 5 | API Resource & Response | ☐ | ☐ | ☐ |
| 6 | Service & DI Container | ☐ | ☐ | ☐ |
| 7 | Auth & RBAC | ☐ | ☐ | ☐ |
| 8 | Xử lý lỗi tập trung | ☐ | ☐ | ☐ |
| 9 | Testing | ☐ | ☐ | ☐ |
| 10 | Nâng cao | ☐ | ☐ | ☐ |
| 11 | Vận hành & CI | ☐ | ☐ | ☐ |
| D | Dự án `library-api` | — | ☐ | ☐ |

### Module đã "xoá-và-viết-lại" thành công
| Module | Ngày làm | Test xanh sau bao lâu | Chỗ vướng nhất |
|---|---|---|---|
| Specialty | | | |
| Doctor | | | |
| Patient | | | |
| Medicine | | | |
| Appointment | | | |
| Invoice + Payment | | | |

---

## Lời cuối

Bạn không bắt đầu từ số 0 — bạn đang ở một vị trí **tốt hơn** đa số người mới học Laravel: đã có một codebase thật, đúng chuẩn, có test, và bạn hiểu nghiệp vụ của nó. Thứ duy nhất còn thiếu là **thời gian ngón tay đặt trên bàn phím**.

Ba việc quan trọng nhất trong toàn bộ file này, nếu phải chọn:

1. **GĐ 0** — PHP OOP. Không có nó, Laravel mãi là ma thuật.
2. **Xoá-và-viết-lại từng module** với test làm lưới an toàn (GĐ 9, bài tập 4).
3. **Phần D** — viết `library-api` từ số 0, không AI.

Làm xong ba việc đó, câu hỏi "tôi có tự viết được không" sẽ tự biến mất.
