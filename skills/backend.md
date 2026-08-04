# Skill: Backend (Laravel — Kiến trúc B: Controller + Service)

Playbook triển khai tầng ứng dụng Clinic API: Sanctum, RBAC middleware, Controller/Service/FormRequest/Resource, PayPal, activity log, stats, test. Kiến trúc chốt: **B (Controller + Service)** — xem [README mục 3](../README.md#3-kiến-trúc-đã-chọn-b--controller--service).

---

## 1. Phân lớp & trách nhiệm

```
Route → Middleware(auth:sanctum, EnsurePermission) → Controller → Service → Model → Resource
```

- **Controller**: mỏng. Nhận `FormRequest` (đã validate), gọi `Service`, trả `Resource`. Không business logic.
- **Service** (`app/Services`): business rule, `DB::transaction`, ném `ValidationException` cho lỗi business (→422).
- **FormRequest** (`app/Http/Requests`): validation input cho mọi API ghi.
- **Resource** (`app/Http/Resources`): định hình output.
- **Middleware** `EnsurePermission`: enforce RBAC.

### Mẫu Controller
```php
class PatientController extends Controller
{
    public function __construct(private PatientService $service) {}

    public function store(StorePatientRequest $request)
    {
        $patient = $this->service->create($request->validated());
        return (new PatientResource($patient))
            ->additional(['success' => true, 'message' => 'Patient created'])
            ->response()->setStatusCode(201);
    }
}
```

---

## 2. Envelope response thống nhất

Trait tái dùng:
```php
trait ApiResponse
{
    protected function ok($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }
    protected function fail(string $message, $errors = [], int $code = 422)
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }
}
```

Cấu hình Exception Handler (Laravel 13 `bootstrap/app.php` `withExceptions`) để `/api/*` luôn trả JSON:
- `ValidationException` → 422 `{ success:false, message, errors }`.
- `AuthenticationException` → 401.
- `AccessDeniedHttpException`/permission → 403.
- `ModelNotFoundException`/`NotFoundHttpException` → 404.

List phân trang thêm `meta`:
```php
return PatientResource::collection($patients); // paginator tự tạo meta/links
```

---

## 3. Auth Sanctum

```php
// AuthController@login
$user = User::where('email', $request->email)->first();
if (! $user || ! Hash::check($request->password, $user->password) || ! $user->is_active) {
    return $this->fail('Invalid credentials', [], 401);
}
$token = $user->createToken('api')->plainTextToken;
return $this->ok(['token' => $token, 'user' => new UserResource($user)], 'Logged in');

// logout: $request->user()->currentAccessToken()->delete();
// me: trả user + role + permissions
```

Route:
```php
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // ... nhóm route nghiệp vụ + EnsurePermission
});
```

Không có `/api/register`.

---

## 4. RBAC — EnsurePermission middleware

```php
class EnsurePermission
{
    public function handle(Request $request, Closure $next)
    {
        $action = $request->route()->getActionName(); // App\Http\Controllers\PatientController@index
        [$class, $method] = explode('@', $action);

        $resource = $this->resourceName($class);   // PatientController → PATIENTS
        $suffix   = $this->actionMap()[$method] ?? strtoupper($method);
        $permission = "{$resource}.{$suffix}";      // PATIENTS.FINDALL

        if (! $request->user()->can($permission)) {
            abort(403, "Missing permission: {$permission}");
        }
        return $next($request);
    }

    private function actionMap(): array
    {
        return [
            'index' => 'FINDALL', 'store' => 'CREATE', 'show' => 'FINDONE',
            'update' => 'UPDATE', 'destroy' => 'DELETE',
            'updateStatus' => 'UPDATESTATUS', 'addItem' => 'ADDITEM',
            'updateItem' => 'UPDATEITEM', 'removeItem' => 'REMOVEITEM',
            'adjustStock' => 'ADJUSTSTOCK', 'capture' => 'CAPTURE',
        ];
    }
}
```

- Map `Controller → resource name` nên **tường minh** (config array) để tránh sai số nhiều bất quy tắc.
- Helper `$user->can('PATIENTS.CREATE')`: định nghĩa qua Gate hoặc method trên `User` kiểm `role->permissions`.
- **Không** hard-code role trong controller.
- Thêm action mới trên controller → thêm permission bằng **data migration**.

### Quy tắc đặc biệt trong Service (không phải RBAC thuần)
- ADMIN cuối cùng: `assertNotLastActiveAdmin()` trước khi đổi role/deactivate → 422.
- DOCTOR chỉ thao tác phiếu khám của mình (nếu siết) → 403.

---

## 5. Business rule then chốt (đặt trong Service)

| Nghiệp vụ | Rule |
|---|---|
| Appointment status | transition hợp lệ theo bảng; sai → 422 |
| Trùng lịch bác sĩ | overlap khung giờ (trừ cancelled) → 422 |
| Examination | chỉ từ appointment confirmed; lấy patient/doctor từ lịch; transaction set completed |
| Prescription/kho | `is_active` + `stock>=qty` + `lockForUpdate`; thiếu → 422 rollback; hoàn/điều chỉnh khi remove/update |
| Invoice | `subtotal = Σ(qty×price) + EXAMINATION_FEE`; sửa/hủy chỉ khi unpaid & chưa completed |
| Payment | `amount ≤ còn lại`; capture đủ → invoice paid |

`EXAMINATION_FEE` đọc từ config: `config('clinic.examination_fee')` (map từ env).

---

## 6. Tránh N+1 (eager loading)

```php
Appointment::with(['patient', 'doctor.user'])->paginate();
Prescription::with('items.medicine')->find($id);
Examination::with(['patient', 'prescription.items.medicine', 'invoice'])->find($id);
```

Dùng query scope cho filter:
```php
public function scopeSearch($q, ?string $term)
{
    return $term ? $q->where(fn($w) => $w
        ->where('full_name', 'ILIKE', "%{$term}%")
        ->orWhere('phone', 'ILIKE', "%{$term}%")
        ->orWhere('code', 'ILIKE', "%{$term}%")) : $q;
}
```

---

## 7. PayPal Sandbox

`config/paypal.php` đọc env (`mode`, `client_id`, `client_secret`, `currency`). Service:

```php
class PayPalService
{
    private function token(): string
    {
        $res = Http::withBasicAuth(config('paypal.client_id'), config('paypal.client_secret'))
            ->asForm()->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        return $res->json('access_token');
    }

    public function createOrder(float $amount): array
    {
        return Http::withToken($this->token())->post($this->base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [['amount' => [
                'currency_code' => config('paypal.currency'),
                'value' => number_format($amount, 2, '.', ''),
            ]]],
        ])->json(); // → id (order), links (approval_url)
    }

    public function captureOrder(string $orderId): array
    {
        return Http::withToken($this->token())
            ->post($this->base()."/v2/checkout/orders/{$orderId}/capture")->json();
    }

    private function base(): string
    {
        return config('paypal.mode') === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }
}
```

- `store` payment: `createOrder` → lưu `pending` + `provider_order_id` → trả `approval_url`/`order_id`. `amount > còn lại` → 422.
- `capture`: `captureOrder` → success `completed` + `provider_capture_id` + `paid_at`; transaction cộng dồn → invoice `paid`. Fail → `failed`.
- **Không** commit/log secret. **Không** lưu số thẻ Visa.
- `method=visa` xử lý backend giống paypal; khác ở bước duyệt phía client.

---

## 8. Activity log (Event/Observer)

```php
class AppointmentObserver
{
    public function updated(Appointment $a): void
    {
        if ($a->wasChanged('status')) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => 'appointment', 'subject_id' => $a->id,
                'action' => 'status_changed',
                'meta' => ['from' => $a->getOriginal('status'), 'to' => $a->status],
            ]);
        }
    }
}
```

Action tối thiểu: user (created/status), appointment status, examination, prescription/kho, invoice, payment. Action đặc thù (capture, adjustStock) ghi thủ công qua Event.

---

## 9. Stats

Aggregate SQL (xem [database.md mục 8](database.md#8-stats-bằng-aggregate-không-đếm-bằng-php)) — không load rồi đếm bằng PHP.

---

## 10. Feature test

```php
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class); // roles/permissions
    }

    public function test_doctor_cannot_capture_payment(): void
    {
        $doctor = User::factory()->doctor()->create();
        $this->actingAs($doctor, 'sanctum')
            ->postJson("/api/payments/1/capture")
            ->assertStatus(403);
    }
}
```

- `RefreshDatabase` + factory; seed RBAC trong `setUp`.
- **Mock PayPal**: `Http::fake([...])` — không gọi Sandbox thật.
- Cover: auth, RBAC 403, luồng patient→…→payment, trừ kho.

---

## 11. Checklist review backend trước PR

- [ ] Controller mỏng, business trong Service.
- [ ] Form Request cho mọi API ghi; API Resource cho output.
- [ ] Envelope + HTTP status đúng ma trận.
- [ ] `EnsurePermission` enforce đúng, không hard-code role.
- [ ] Transaction + `lockForUpdate` cho examination/prescription/payment.
- [ ] Eager loading tránh N+1.
- [ ] PayPal secret an toàn; không lưu số thẻ.
- [ ] Activity log ghi các action chính.
- [ ] Feature test (mock PayPal) xanh.
