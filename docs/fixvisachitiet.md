# Fix Visa — kế hoạch thực thi chi tiết (chưa code, đang bàn)

## 1. Yêu cầu đã chốt (sau nhiều lần trao đổi ở docs/visafix.md)

Khi người dùng chọn phương thức **Visa** trong modal chi tiết hóa đơn:

1. Bật ra một modal riêng của app (không redirect sang trang PayPal).
2. Modal có 3 field: **Card number**, **Expiration date**, **CVV**.
3. Người dùng nhập xong, bấm thanh toán → xử lý → thanh toán thành công, cập nhật
   trạng thái payment/invoice như luồng hiện tại.

Đây là **Phương án C** đã nêu ở `docs/visafix.md` (Hosted Card Fields), chính thức
được chọn lại sau khi làm rõ rằng bảng "Add credit or debit card" trên
`sandbox.paypal.com/checkoutnow` (ảnh chụp trước đó) là do PayPal tự hiển thị trên
trang của họ — không phải trên site mình, nên không đáp ứng đúng yêu cầu "hiện ngay
trong app".

## 2. ĐÍNH CHÍNH (2026-08-25) — đã đọc tài liệu chính thức PayPal

Sau khi đọc trực tiếp `developer.paypal.com/expanded/card-fields` (link bạn gửi) và
tra thêm API reference, phát hiện **đây là sản phẩm khác** với "Advanced Checkout"
SDK cũ (`www.paypal.com/sdk/js?components=card-fields`) mà tôi giả định ở bản đầu của
doc này. Đây là **"Expanded Checkout" / PayPal Web SDK v6** — API và yêu cầu backend
khác hẳn. Mục 3-6 dưới đây đã được viết lại theo đúng SDK này, thay cho bản cũ.

**Điểm sai cần sửa quan trọng nhất:** kết luận "backend không cần đổi gì" ở bản đầu
**sai** — SDK v6 bắt buộc phải có `clientToken` sinh từ server (dùng `client_secret`),
nên **cần thêm 1 endpoint backend mới**. Xem mục 4.

## 3. Lựa chọn kỹ thuật: PayPal Web SDK v6 (Card Fields)

- SDK load bằng thẻ `<script async>`, sandbox: `https://sandbox.paypal.com/web-sdk/v6/core`
  (production: `https://www.paypal.com/web-sdk/v6/core`).
- Khởi tạo: `const sdk = await window.paypal.createInstance({ clientToken, components: ["card-fields"] })`.
- 3 field vẫn là iframe do PayPal render (không phải `<input>` tự viết) — giữ đúng
  yêu cầu bảo mật: PAN/CVV/expiry không bao giờ đi qua request/log/DB của Laravel.
- Backend tạo Order (`v2/checkout/orders`) và Capture Order **y nguyên payload hiện
  tại** — không cần `payment_source.card` đặc biệt gì, xác nhận từ tài liệu.
- 3D Secure được SDK tự xử lý bên trong `submit()`, không cần code thêm bước riêng.

## 4. Backend CẦN thêm: endpoint sinh `client_token` (đính chính so với bản cũ)

Theo tài liệu, để khởi tạo SDK ở bước 3, frontend cần một `clientToken` sinh phía
server bằng `client_secret` — không thể chỉ dùng `client_id` public như bản kế hoạch
đầu tiên giả định. Endpoint PayPal thật (tra theo API reference, không phải route của
app mẫu minh họa):

```
POST /v1/identity/generate-token
Base URL: https://api-m.sandbox.paypal.com (hoặc https://api-m.paypal.com production)
Headers:
  Authorization: Bearer {access_token}   ← lấy y hệt PayPalService::token() hiện có
  Content-Type: application/json
Response: { "client_token": "..." }
```

Việc cần thêm (nhỏ, không đổi model/migration/DB):

- `PayPalService.php`: thêm method `generateClientToken(): string` — tái dùng
  `token()` private hiện có để lấy access token, gọi tiếp `POST /v1/identity/generate-token`,
  trả về `client_token`.
- 1 route + controller method mới, ví dụ `GET /api/payments/paypal/client-token` —
  yêu cầu đăng nhập (`auth:sanctum`) như các route payment khác, **không phải
  endpoint public** (khác hẳn webhook đã bàn ở `visafix.md` mục 11 — cái đó mới cần
  public).
- Không đổi `PaymentService.php`, không đổi migration, không đổi
  `PaymentResource` — các phần này vẫn đúng như bản kế hoạch đầu.

**Độ tin cậy:** endpoint `/v1/identity/generate-token` khớp với tài liệu PayPal đã
tồn tại lâu năm cho Hosted Fields/Advanced Checkout, tra 2 lần từ 2 nguồn khác nhau
đều ra cùng kết quả — độ tin cậy khá cao, nhưng vẫn nên đối chiếu lại 1 lần bằng
Postman/curl thật trước khi code chính thức method này, vì nội dung được 1 model nhỏ
tóm tắt tự động, không phải tôi tự đọc nguyên văn HTML.

**Ghi chú:** 2 thẻ `<meta name="paypal-client-id">`/`<meta name="paypal-currency">`
đã thêm vào `layouts/app.blade.php` ở bước trước (rồi bị mất trên đĩa, xem lượt trao
đổi trước) **không còn cần thiết cho hướng SDK v6 này** — `createInstance()` dùng
`clientToken` lấy từ endpoint mới ở trên, không dùng `client_id` qua URL/meta như SDK
cũ. Không cần thêm lại 2 thẻ meta đó khi code phần này, trừ khi có lý do khác cần.

## 5. Thay đổi dự kiến ở frontend (đính chính API cho đúng SDK v6)

### `resources/views/pages/invoices/index.blade.php`

- Giữ nguyên khối "Tạo thanh toán" hiện tại cho `method === 'paypal'`.
- Khi `method === 'visa'`: nút submit đổi thành "Nhập thẻ Visa", bấm vào gọi
  `openVisaModal()` thay vì `submitPayment()`.
- Thêm modal mới (`x-ui.modal`, size `sm`/`md`), nội dung:
  - Số tiền + ghi chú (đọc lại từ `paymentForm`, không cho sửa trong modal này để
    tránh lệch với amount đã validate trước khi mở).
  - 3 container rỗng để SDK mount: `#visa-card-number-field`,
    `#visa-expiration-date-field`, `#visa-cvv-field`.
  - Vùng thông báo lỗi/trạng thái (`visaMessage`).
  - Trạng thái loading khi SDK đang tải (`visaSdkState === 'loading'`).
  - Fallback: nếu `visaSdkState === 'error'` (SDK lỗi hoặc tài khoản sandbox không
    eligible cho Card Fields) → hiện thông báo + nút "Chuyển sang PayPal" gọi lại
    `submitPayment()` (giữ nguyên luồng redirect cũ làm phương án dự phòng, không có
    gì bị mất nếu Card Fields không khả dụng).
  - Nút "Thanh toán" gọi `submitVisaCard()`, disable khi `visaSubmitting`.

### `resources/js/features/payments/payment-api.js`

Thêm 1 hàm mới, gọi endpoint backend ở mục 4:

```js
export function getPayPalClientToken() {
    return apiRequest("/payments/paypal/client-token");
}
```

### `resources/js/features/invoices/index.js`

State thêm vào `paymentForm`/`detail` hiện có:

```
visaModalOpen: false,
visaSdkState: "idle",   // idle | loading | ready | error
visaSubmitting: false,
visaMessage: "",
visaSession: null,      // cardSession trả về từ SDK, huỷ khi đóng modal
```

Method dự kiến (đã sửa theo đúng API SDK v6, khác bản đầu):

- `openVisaModal()` — validate amount giống hệt logic đầu `submitPayment()` (không
  tạo payment ngay), mở modal, gọi `loadPayPalSdk()` rồi `mountVisaCardFields()`.
- `loadPayPalSdk()` — chèn `<script async src="https://sandbox.paypal.com/web-sdk/v6/core">`
  một lần (cache promise, không load lại nếu đã có `window.paypal`).
- `mountVisaCardFields()`:
  1. Gọi `getPayPalClientToken()` (API mới ở mục 4) lấy `clientToken`.
  2. `const sdk = await window.paypal.createInstance({ clientToken, components: ["card-fields"] })`.
  3. Check eligibility: `(await sdk.findEligibleMethods()).isEligible("advanced_cards")`
     — nếu `false`, chuyển `visaSdkState = 'error'`, hiện fallback (mục dưới).
  4. `const cardSession = sdk.createCardFieldsOneTimePaymentSession()`.
  5. Với mỗi field: `cardSession.createCardFieldsComponent({ type: "number" | "expiry" | "cvv" })`
     rồi `appendChild()` vào đúng `<div>` container tương ứng.
  6. Lưu `cardSession` vào `visaSession`, `visaSdkState = 'ready'`.
- `submitVisaCard()`:
  1. Gọi `createPayment(invoiceId, { amount, method: "visa", note })` (API cũ,
     không đổi) → lấy `provider_order_id`.
  2. `const { state, data } = await this.visaSession.submit(orderId, {})`.
  3. Nếu `state === "succeeded"` → gọi `capturePayment(paymentId)` (API cũ, không
     đổi) → đóng modal, reload chi tiết hóa đơn, báo thành công.
  4. Nếu `state === "canceled"` → hiện thông báo "Bạn đã huỷ xác thực 3D Secure",
     cho thử lại (payment vẫn ở `pending`, không cần tạo mới — có thể gọi lại
     `submit()` trên cùng `orderId`, hoặc đơn giản là tạo payment mới nếu muốn an
     toàn hơn, tránh giữ trạng thái SDK cũ).
  5. Nếu `state === "failed"` → hiện `data.message` (nếu có) vào `visaMessage`.
- `closeVisaModal()` — đóng modal, dọn `visaSession`/state.

## 6. Rủi ro / điều chưa thể xác nhận từ môi trường này

- **Chưa test được thật trong browser.** Toàn bộ thông tin API ở mục 3-5 lấy từ 2 lần
  tra cứu tài liệu PayPal (WebFetch, model nhỏ tóm tắt HTML) — độ tin cậy khá, nhưng
  **chưa tự chạy thử trong trình duyệt thật** nên không loại trừ khả năng còn sai
  lệch nhỏ về tên field/thứ tự tham số so với bản SDK PayPal trả về tại thời điểm
  code thật chạy. **Sau khi code xong, cần bạn tự mở trang hóa đơn, chọn Visa, thử
  nhập thẻ test sandbox để xác nhận chạy đúng**, đặc biệt là bước gọi
  `client-token` endpoint mới.
- Không xác nhận được VND có bị loại trừ khỏi "Expanded Checkout"/Card Fields hay
  không — tài liệu không nói rõ. Cứ theo `PAYPAL_CURRENCY=USD` hiện tại (đã fix ở
  `visafix.md` mục 6), không đổi currency trong lúc làm phần này.
- Nếu SDK báo không eligible (`isEligible("advanced_cards") === false`) → fallback
  tự động về redirect PayPal (không có gì hỏng thêm so với hiện tại), nhưng cần bạn
  xác nhận đã thấy fallback đó hoạt động nếu gặp phải.
- `PaymentService.php` không đổi trong kế hoạch này; `PayPalService.php` chỉ thêm 1
  method mới (`generateClientToken()`) — không sửa `createOrder()`/`captureOrder()`
  hiện có. Nếu test thật phát sinh cần chỉnh thêm, sẽ quay lại bàn tiếp trước khi sửa.

## 7. Việc cần bạn xác nhận trước khi tôi code tiếp

1. Đồng ý thêm endpoint backend mới `GET /api/payments/paypal/client-token`
   (auth:sanctum, không public) như mục 4 chưa?
2. Đồng ý cấu trúc modal + state ở mục 5 chưa, hay muốn đổi UI (ví dụ thêm trường tên
   chủ thẻ — cần kiểm tra SDK v6 có field `type: "name"` hay tương đương không, chưa
   xác nhận)?
3. Có cần giữ nút "PayPal" (redirect) song song, hay giờ luôn ưu tiên Card Fields cho
   cả 2 lựa chọn?
4. Đồng ý test thật trên sandbox sau khi code xong (vì tôi không tự kiểm tra được)?

## 8. Đã code xong (2026-08-25) — cần bạn tự test sandbox

Bạn đã đồng ý cả 4 điểm ở mục 7, đã code theo đúng kế hoạch mục 4-5. Danh sách file
đã đổi:

**Backend**
- `app/Services/PayPalService.php` — thêm `generateClientToken()`.
- `app/Http/Controllers/PaymentController.php` — thêm action `clientToken()`.
- `routes/api.php` — thêm `GET /api/payments/paypal/client-token` (trong group
  `auth:sanctum` + `permission`).
- `config/rbac.php` — map action `clientToken` → `CREATE` (dùng chung quyền
  `PAYMENTS.CREATE`, không thêm permission mới).
- `app/Constants/PaymentMessage.php` — thêm `CLIENT_TOKEN_RETRIEVED`.
- `tests/Feature/PaymentTest.php` — thêm 3 test cho endpoint mới (thành công, thiếu
  quyền, chưa đăng nhập).

**Frontend**
- `resources/js/features/payments/payment-api.js` — thêm `getPayPalClientToken()`.
- `resources/js/features/invoices/index.js` — thêm state `visaModalOpen`,
  `visaSdkState`, `visaSubmitting`, `visaMessage` + method `openVisaModal()`,
  `closeVisaModal()`, `loadPayPalWebSdk()`, `mountVisaCardFields()`,
  `submitVisaCard()`; tách `_validatePaymentAmount()` dùng chung với
  `submitPayment()` (tránh lặp code).
- `resources/views/pages/invoices/index.blade.php` — tách nút theo `method` (PayPal
  giữ nguyên submit cũ; Visa mở modal mới), thêm modal `visa-payment-modal` với 3
  container field + trạng thái loading/error/fallback.

**Đã tự kiểm tra được (không cần browser):**
- `php artisan test` — 260/260 pass.
- `npm run build` — build sạch, không lỗi cú pháp JS.
- Render thử `/invoices` qua `php artisan serve` tạm — Blade compile đúng, modal Visa
  xuất hiện đủ trong HTML output (đã curl kiểm tra trực tiếp).

**Chưa/không thể tự kiểm tra (cần bạn test tay trên sandbox thật):**
- Toàn bộ hành vi JS SDK v6 thật sự (`createInstance`, `findEligibleMethods`,
  `createCardFieldsComponent`, `submit()`) — chỉ dựa trên tài liệu tra được, chưa
  chạy qua browser.
- Tài khoản sandbox hiện tại (`PAYPAL_CLIENT_ID` trong `.env`) có eligible cho
  `advanced_cards` hay không.
- Đường dẫn thật của `POST /v1/identity/generate-token` khi gọi bằng access token
  sandbox thật (test hiện tại chỉ fake HTTP, chưa gọi PayPal thật).
- Luồng thẻ test của PayPal sandbox (số thẻ test 4-xxx thường dùng) có chạy hết qua
  `submit()` tới `onApprove`/capture thành công hay không.

**Cách test:** mở `/invoices`, vào chi tiết 1 hóa đơn `unpaid`, chọn phương thức
"Thẻ Visa", bấm "Nhập thẻ Visa" → xem modal có tải được 3 field không. Nếu báo lỗi
"Không thể tải form nhập thẻ..." → bấm F12 xem Console/Network để biết lỗi cụ thể
(sai endpoint, không eligible, SDK response khác cấu trúc...), báo lại để tôi sửa
tiếp theo đúng lỗi thực tế thay vì đoán tiếp.

## 9. Test thật xong — CONFIRMED WORKING (2026-08-25)

Đã qua 4 vòng lỗi thật trên sandbox, mỗi vòng sửa đúng 1 nguyên nhân cụ thể (không
đoán mò) — người dùng xác nhận **thanh toán Visa qua modal Card Fields chạy được**.
Log lại đầy đủ để tra cứu sau này nếu cần đụng lại phần này:

| # | Lỗi gặp phải | Nguyên nhân thật | Chỗ sửa |
|---|---|---|---|
| 1 | `client-token` trả 500 | `Http::post($url)` không truyền `$data` → PHP gửi body `[]` (JSON array), PayPal yêu cầu `{}` (JSON object) | `PayPalService.php` — dùng `(object) []`, sau đó đổi hẳn cách lấy token (xem #3) |
| 2 | `SdkInitError: SDK must be loaded from www.paypal.com or www.sandbox.paypal.com` | URL SDK thiếu `www.` (`sandbox.paypal.com` thay vì `www.sandbox.paypal.com`) | `invoices/index.js::loadPayPalWebSdk()` |
| 3 | `clientToken must be a valid JSON Web Token` | `/v1/identity/generate-token` trả token kiểu Braintree cũ, không phải JWT — sai hẳn API cho SDK v6 | `PayPalService.php` — đổi sang `POST /v1/oauth2/token` với `response_type=client_token` (verify trực tiếp bằng curl thật, xác nhận JWT 3 segment trước khi sửa code) |
| 4 | `Error: Card Fields not eligible` | Tài khoản PayPal sandbox ban đầu không được cấp `advanced_cards` — do quốc gia merchant không nằm trong 37 nước PayPal hỗ trợ Advanced/Expanded Checkout (Việt Nam không có trong danh sách) | Không sửa code — người dùng tạo/dùng app sandbox khác đủ điều kiện, đổi `PAYPAL_CLIENT_ID`/`PAYPAL_CLIENT_SECRET` trong `.env` |
| 5 | Field hiện ra nhưng không gõ được | `field.render(container)` — method này không tồn tại/không đúng cách mount theo tài liệu gốc; `createCardFieldsComponent()` trả thẳng DOM node, phải `container.appendChild(field)` | `invoices/index.js::mountVisaCardFields()` |

**Bài học chính:** tài liệu PayPal tra qua WebFetch (model nhỏ tóm tắt HTML) có vài
chỗ sai/thiếu chính xác so với hành vi API thật (endpoint client-token, cách mount
field) — phần nào tự verify được bằng curl trực tiếp (ví dụ #1, #3) thì độ tin cậy
cao hẳn so với chỉ đọc tóm tắt. Còn eligibility theo quốc gia (#4) là giới hạn
nghiệp vụ thật của PayPal, không phải lỗi kỹ thuật.

**Trạng thái cuối:** tính năng hoàn chỉnh, đã test thật thành công. `.env` hiện dùng
app sandbox mới (`PAYPAL_CLIENT_ID`/`SECRET` đã đổi, PayPal đủ điều kiện
`advanced_cards`) — cần nhớ khi merchant thật lên production, phải xác nhận lại
quốc gia đăng ký merchant có nằm trong danh sách 37 nước hỗ trợ hay không, nếu không
thì tự động fallback về PayPal redirect (đã code sẵn, không cần sửa gì thêm).
