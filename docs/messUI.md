# messUI — Đề xuất Việt hoá message hiển thị trên giao diện

> **Trạng thái: BẢN ĐỀ XUẤT — CHƯA THỰC THI.**
> Tài liệu này mô tả vấn đề, các phương án và luồng dự kiến sau khi sửa, để anh review.
> Không có dòng code nào được thay đổi cho tới khi anh duyệt phương án.

Ngày lập: 2026-08-20
Người lập: Claude (theo yêu cầu review trước khi code)

---

## 1. Vấn đề

UI toàn bộ là tiếng Việt, nhưng text lỗi/thành công do backend trả về lại là tiếng Anh và được
frontend hiển thị **nguyên xi**.

Ca cụ thể vừa gặp khi test luồng thanh toán PayPal:

```
┌────────────────────────────────────────┐
│              🚫 (icon vàng)            │
│    Không thể xác nhận thanh toán       │  ← tiếng Việt, hardcode trong Blade
│  Only pending payments can be captured.│  ← tiếng Anh, lấy từ backend
│        [ Về danh sách hóa đơn ]        │
└────────────────────────────────────────┘
```

Một khung thông báo mà hai ngôn ngữ. Người dùng cuối là nhân viên phòng khám, không đọc được
dòng tiếng Anh — mà đó lại chính là dòng giải thích *tại sao* thao tác hỏng.

> **Lưu ý phạm vi:** bug khiến màn hình này hiện ra sai (init chạy 2 lần → capture 2 lần) đã được
> sửa riêng và không liên quan tới tài liệu này. Ở đây chỉ bàn về **ngôn ngữ của message**.
> Sau khi sửa bug đó, các message tiếng Anh vẫn còn nguyên và vẫn sẽ lộ ra ở những tình huống
> lỗi khác (nhập trùng email, hết tồn kho, trùng lịch bác sĩ, hết phiên đăng nhập...).

---

## 2. Hiện trạng

### 2.1 Có **hai** nguồn sinh ra text tiếng Anh, không phải một

Đây là điểm quan trọng nhất khi chọn phương án. Nếu chỉ sửa nguồn (1) thì nguồn (2) vẫn tiếng Anh.

| # | Nguồn | Số lượng | Ví dụ |
|---|---|---|---|
| 1 | `app/Constants/*Message.php` — message nghiệp vụ do team tự viết | **154 hằng số** / 13 file | `Only pending payments can be captured.` |
| 2 | Validator mặc định của Laravel — cho rule không khai báo trong `messages()` | ~100 chuỗi dựng sẵn | `The full name field is required.` |

Nguồn (2) hiện đang chạy bằng bản tiếng Anh built-in của framework: dự án **chưa có thư mục
`lang/`**, và `config/app.php` đang để `'locale' => env('APP_LOCALE', 'en')` với `.env` đặt
`APP_LOCALE=en`.

Ví dụ nguồn (2) lộ ra: `StorePatientRequest::messages()` chỉ khai báo 5 message tuỳ biến
(`code.prohibited`, `gender.in`, `date_of_birth.before_or_equal`, `email.unique`, `address.max`).
Các rule còn lại — `full_name.required`, `phone.required`, `full_name.max`, `email.email`... —
đều rơi về chuỗi mặc định tiếng Anh của Laravel.

### 2.2 Phân bố 154 message theo file

| File | Số message | | File | Số message |
|---|---:|---|---|---:|
| `AppointmentMessage` | 20 | | `SpecialtyMessage` | 10 |
| `ExaminationMessage` | 18 | | `PaymentMessage` | 10 |
| `UserMessage` | 17 | | `InvoiceMessage` | 10 |
| `PrescriptionMessage` | 17 | | `ExceptionMessage` | 8 |
| `DoctorMessage` | 15 | | `AuthMessage` | 4 |
| `PatientMessage` | 13 | | `RoleMessage` | 1 |
| `MedicineMessage` | 11 | | **Tổng** | **154** |

### 2.3 Ba nhóm message, mức độ nghiêm trọng khác nhau

Không phải 154 message đều quan trọng như nhau:

| Nhóm | Ước lượng | Có lộ ra UI không? | Ví dụ |
|---|---:|---|---|
| **A. Envelope thành công** | ~55 | Hầu như không — frontend tự viết toast tiếng Việt, không dùng `response.message` | `Patient created`, `Invoices retrieved` |
| **B. Lỗi nghiệp vụ 422/409** | ~85 | **Có, thường xuyên** — frontend gán thẳng `this.formMessage = error.message` | `The examination already has an invoice.` |
| **C. Lỗi hệ thống / auth** | ~14 | **Có** | `Unauthenticated.`, `Invalid credentials` |

Nhóm B và C là phần thực sự gây khó chịu. Nhóm A gần như vô hình với người dùng nhưng vẫn nên
làm cho nhất quán.

### 2.4 Frontend đang xử lý message thế nào

Frontend **đã** hardcode tiếng Việt cho một số trường hợp, nhưng chỉ 2 trường hợp:

```js
// resources/js/features/patients/index.js — mẫu lặp lại ở mọi feature
if (error instanceof ApiError && error.status === 403) {
    this.formMessage = 'Bạn không có quyền thực hiện thao tác này.';   // ✅ tiếng Việt
} else {
    this.formMessage = error.message;                                   // ⚠️ tiếng Anh chảy thẳng ra
}
```

Nghĩa là: **403 và lỗi mạng đã có tiếng Việt; toàn bộ 422/409/404/500 thì không.**

Ngoài ra `error.errors[field]` (lỗi theo từng ô nhập) cũng được render thẳng dưới mỗi field mà
không qua bất kỳ lớp dịch nào.

### 2.5 Ràng buộc lớn nhất: **113 assertion trong test đang bám vào chuỗi tiếng Anh**

```
90  × assertJsonPath('message', '...')
23  × assertJsonPath('errors.<field>.0', '...')
────
113 assertion
```

Và **0** lần test dùng hằng số (`grep -rn "Message::" tests/` → 0 kết quả). Test viết literal:

```php
->assertJsonPath('message', 'Appointment retrieved')
->assertJsonPath('errors.payment.0', 'Only pending payments can be captured.')
```

→ **Đổi giá trị hằng số = vỡ 113 assertion.** Đây là yếu tố quyết định khi so sánh các phương án
bên dưới.

---

## 3. Luồng message hiện tại

```
┌──────────────┐
│  Controller  │  ApiResponse::resource($r, PatientMessage::CREATED, 201)
└──────┬───────┘
       │
┌──────▼────────────┐
│  Service (422)    │  ValidationException::withMessages([
│                   │      'payment' => [PaymentMessage::PAYMENT_CANNOT_BE_CAPTURED]
│                   │  ])
└──────┬────────────┘
       │
┌──────▼────────────┐
│  FormRequest      │  rules() không khai báo trong messages()
│                   │  → Laravel tự sinh 'The full name field is required.'
└──────┬────────────┘
       │
       ▼
   { "success": false,
     "message": "Only pending payments can be captured.",   ← 🇬🇧
     "errors": { "payment": ["Only pending payments..."] } } ← 🇬🇧
       │
┌──────▼────────────┐
│  api-client.js    │  throw new ApiError(payload.message, { status, errors })
└──────┬────────────┘
       │
┌──────▼────────────┐
│  Feature ctrl     │  status === 403 → text tiếng Việt hardcode  ✅
│                   │  còn lại       → error.message nguyên xi     ⚠️
└──────┬────────────┘
       │
       ▼
   Người dùng đọc tiếng Anh trong UI tiếng Việt   ❌
```

---

## 4. Ba phương án

### Phương án A — Sửa thẳng giá trị hằng số sang tiếng Việt

```php
// app/Constants/PaymentMessage.php
- public const PAYMENT_CANNOT_BE_CAPTURED = 'Only pending payments can be captured.';
+ public const PAYMENT_CANNOT_BE_CAPTURED = 'Chỉ thanh toán đang chờ mới có thể xác nhận.';
```

| | |
|---|---|
| ✅ | Đơn giản nhất, sửa đúng 13 file, không đổi kiến trúc |
| ✅ | Frontend không phải đụng vào |
| ❌ | **Vỡ 113 assertion** → phải sửa tay 113 chỗ trong test |
| ❌ | **Không giải quyết được nguồn (2)** — validator mặc định vẫn tiếng Anh |
| ❌ | API cứng tiếng Việt; nếu sau này có app mobile/đối tác dùng chung API thì không đổi ngôn ngữ được |

### Phương án B — Dùng i18n chuẩn Laravel (`lang/vi` + `lang/en`) ⭐

Hằng số trở thành **khoá dịch**, không còn là text:

```php
// app/Constants/PaymentMessage.php
- public const PAYMENT_CANNOT_BE_CAPTURED = 'Only pending payments can be captured.';
+ public const PAYMENT_CANNOT_BE_CAPTURED = 'payment.cannot_be_captured';
```

```php
// lang/vi/payment.php
'cannot_be_captured' => 'Chỉ thanh toán đang chờ mới có thể xác nhận.',

// lang/en/payment.php
'cannot_be_captured' => 'Only pending payments can be captured.',
```

Chỗ dùng bọc thêm `__()`, và `.env` đặt `APP_LOCALE=vi`.

Quan trọng: publish thêm `lang/vi/validation.php` để dịch luôn **nguồn (2)**.

| | |
|---|---|
| ✅ | **Giải quyết cả hai nguồn** — kể cả message mặc định của Laravel |
| ✅ | **Giữ nguyên 113 assertion**: `phpunit.xml` đặt `APP_LOCALE=en`, test vẫn so với bản tiếng Anh trong `lang/en` |
| ✅ | Đúng chuẩn framework, muốn thêm ngôn ngữ sau này chỉ cần thêm thư mục |
| ✅ | API giữ được khả năng đa ngôn ngữ qua header `Accept-Language` |
| ⚠️ | Công sức lớn nhất: 154 khoá × 2 ngôn ngữ + `validation.php` (~100 dòng) |
| ⚠️ | Phải sửa mọi chỗ dùng hằng số để bọc `__()` |

### Phương án C — Backend trả thêm mã lỗi, frontend tự map

```json
{ "success": false, "code": "PAYMENT_CANNOT_BE_CAPTURED",
  "message": "Only pending payments can be captured." }
```

Frontend giữ một bảng `code → tiếng Việt`.

| | |
|---|---|
| ✅ | Test gần như không đổi (chỉ thêm field `code`) |
| ✅ | API giữ tiếng Anh — sạch cho consumer khác |
| ✅ | Frontend toàn quyền quyết định câu chữ, sửa không cần đụng backend |
| ❌ | Phải sửa **cả hai phía**: envelope backend + bảng map 154 dòng ở frontend |
| ❌ | Vẫn **không xử lý được nguồn (2)** — validator mặc định không có `code` |
| ❌ | Hai nơi cùng giữ danh sách message → dễ lệch nhau khi thêm message mới |

### So sánh nhanh

| Tiêu chí | A | B ⭐ | C |
|---|:-:|:-:|:-:|
| Dịch được message nghiệp vụ (nguồn 1) | ✅ | ✅ | ✅ |
| Dịch được validator Laravel (nguồn 2) | ❌ | ✅ | ❌ |
| Giữ được 113 assertion hiện có | ❌ | ✅ | ✅ |
| Số file phải sửa | ~13 + 113 chỗ test | ~40 + tạo `lang/` | ~15 + frontend |
| Mở rộng đa ngôn ngữ về sau | ❌ | ✅ | ⚠️ |
| Công sức | Thấp | **Cao** | Trung bình |

**Đề xuất: phương án B.** Lý do quyết định không phải là "chuẩn hơn", mà là hai điều rất cụ thể:
nó là phương án **duy nhất** dịch được message mặc định của Laravel — vốn là loại lỗi người dùng
gặp nhiều nhất (bỏ trống ô bắt buộc, sai định dạng email); và nó **không đụng tới 113 assertion**
vì test chạy ở locale `en`.

---

## 5. Luồng dự kiến sau khi sửa (phương án B)

```
┌──────────────┐
│  Controller  │  ApiResponse::resource($r, __(PatientMessage::CREATED), 201)
└──────┬───────┘                          └────┬────┘
       │                                       │
       │                          ┌────────────▼─────────────┐
       │                          │  lang/{locale}/patient.php│
       │                          │  vi → 'Đã tạo hồ sơ...'   │
       │                          │  en → 'Patient created'   │
       │                          └───────────────────────────┘
┌──────▼────────────┐
│  FormRequest      │  rule không khai báo → lang/vi/validation.php
│                   │  → 'Trường Họ và tên là bắt buộc.'          ✅ ĐÃ DỊCH
└──────┬────────────┘
       │
       ▼
   { "success": false,
     "message": "Chỉ thanh toán đang chờ mới có thể xác nhận.",   ← 🇻🇳
     "errors": { "payment": ["Chỉ thanh toán đang chờ..."] } }     ← 🇻🇳
       │
┌──────▼────────────┐
│  api-client.js    │  KHÔNG ĐỔI
└──────┬────────────┘
       │
┌──────▼────────────┐
│  Feature ctrl     │  KHÔNG ĐỔI — error.message vốn đã hiển thị thẳng,
│                   │  giờ nội dung nó nhận được đã là tiếng Việt
└──────┬────────────┘
       │
       ▼
   Toàn bộ UI tiếng Việt nhất quán   ✅
```

**Điểm đáng chú ý: frontend gần như không phải sửa gì.** Vì frontend vốn đã hiển thị
`error.message` nguyên xi, chỉ cần nội dung backend trả về đổi sang tiếng Việt là UI tự đúng.
Đây là lợi thế của việc dịch ở backend thay vì map ở frontend.

Luồng chạy test thì ngược lại:

```
phpunit.xml  →  APP_LOCALE=en  →  lang/en/*  →  'Payment captured'  →  113 assertion vẫn xanh ✅
```

---

## 6. Ví dụ trước / sau

### 6.1 Nhóm C — Lỗi hệ thống & đăng nhập (ưu tiên cao nhất, người dùng gặp hằng ngày)

| Hằng số | Hiện tại | Đề xuất |
|---|---|---|
| `AuthMessage::INVALID_CREDENTIALS` | Invalid credentials | Email hoặc mật khẩu không đúng |
| `ExceptionMessage::UNAUTHENTICATED` | Unauthenticated. | Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại. |
| `ExceptionMessage::RESOURCE_NOT_FOUND` | Resource not found. | Không tìm thấy dữ liệu. |
| `ExceptionMessage::MISSING_PERMISSION` | Missing permission: %s | Thiếu quyền: %s |
| `ExceptionMessage::SERVER_ERROR` | Server Error | Lỗi hệ thống, vui lòng thử lại. |

### 6.2 Nhóm B — Lỗi nghiệp vụ (phần gây khó hiểu nhất)

| Hằng số | Hiện tại | Đề xuất |
|---|---|---|
| `PaymentMessage::PAYMENT_CANNOT_BE_CAPTURED` | Only pending payments can be captured. | Chỉ thanh toán đang chờ mới có thể xác nhận. |
| `PaymentMessage::INVOICE_NOT_PAYABLE` | Payments can only be created while the invoice is unpaid. | Chỉ có thể thanh toán khi hóa đơn chưa thanh toán. |
| `PaymentMessage::AMOUNT_EXCEEDS_REMAINING` | Amount exceeds the invoice remaining balance of :remaining. | Số tiền vượt quá số còn phải trả của hóa đơn (:remaining). |
| `AppointmentMessage` (trùng lịch) | The doctor already has an appointment overlapping this 30-minute time slot. | Bác sĩ đã có lịch hẹn trùng với khung 30 phút này. |
| `ExaminationMessage::…ALREADY_HAS_INVOICE` | The examination already has an invoice. | Phiếu khám này đã có hóa đơn. |
| `PrescriptionMessage::MEDICINE_INSUFFICIENT_STOCK` | Medicine :code has insufficient stock. | Thuốc :code không đủ tồn kho. |
| `PrescriptionMessage::MEDICINE_NOT_ACTIVE` | Medicine :code is not active. | Thuốc :code đã ngừng sử dụng. |
| `PatientMessage::EMAIL_ALREADY_TAKEN` | The email has already been taken. | Email này đã được sử dụng. |
| `PatientMessage::INVALID_GENDER` | The gender must be male, female, or other. | Giới tính phải là nam, nữ hoặc khác. |

### 6.3 Nhóm A — Envelope thành công

| Hằng số | Hiện tại | Đề xuất |
|---|---|---|
| `PatientMessage::CREATED` | Patient created | Đã tạo hồ sơ bệnh nhân |
| `PatientMessage::UPDATED` | Patient updated | Đã cập nhật hồ sơ bệnh nhân |
| `MedicineMessage::STOCK_ADJUSTED` | Medicine stock adjusted | Đã điều chỉnh tồn kho thuốc |
| `InvoiceMessage::STATUS_UPDATED` | Invoice status updated | Đã cập nhật trạng thái hóa đơn |

### 6.4 Nguồn (2) — Validator mặc định của Laravel

Chỉ phương án B xử lý được nhóm này:

| Rule | Hiện tại | Sau khi có `lang/vi/validation.php` |
|---|---|---|
| `required` | The full name field is required. | Trường Họ và tên là bắt buộc. |
| `email` | The email field must be a valid email address. | Email không đúng định dạng. |
| `max:255` | The address field must not be greater than 255 characters. | Địa chỉ không được vượt quá 255 ký tự. |
| `date` | The date of birth field must be a valid date. | Ngày sinh không hợp lệ. |
| `integer` | The quantity field must be an integer. | Số lượng phải là số nguyên. |

> Phần `attributes` trong `validation.php` cho phép đặt tên tiếng Việt cho từng field
> (`full_name` → "Họ và tên"), nhờ đó câu thông báo đọc tự nhiên chứ không lộ tên cột DB.

---

## 7. Ảnh hưởng & rủi ro

| Hạng mục | Đánh giá |
|---|---|
| **Test** | Không vỡ nếu `phpunit.xml` giữ `APP_LOCALE=en` và `lang/en/*` chép đúng chuỗi hiện tại. Cần chạy full suite (225 test) để xác nhận. |
| **API consumer khác** | Không có consumer nào ngoài frontend này. Nếu sau này có, header `Accept-Language: en` vẫn lấy được bản tiếng Anh. |
| **Dữ liệu** | Không đụng DB, không cần migration. |
| **Rollback** | Đổi `APP_LOCALE` về `en` là toàn bộ hệ thống quay lại tiếng Anh ngay, không cần revert code. Đây là lợi thế an toàn rõ rệt so với phương án A. |
| **Rủi ro chính** | Sót chỗ dùng hằng số chưa bọc `__()` → chỗ đó sẽ hiện ra khoá thô kiểu `payment.cannot_be_captured` thay vì câu tiếng Việt. Xử lý bằng test quét ở Bước 5. |

---

## 8. Kế hoạch thực thi (sau khi anh duyệt)

Chia 5 bước để anh review được từng phần, không dồn một cục:

| Bước | Nội dung | File ảnh hưởng | Rủi ro |
|---|---|---|---|
| **1** | Dựng khung: tạo `lang/en/` + `lang/vi/`, thêm `lang/vi/validation.php` + `attributes`, đặt `APP_LOCALE=vi` trong `.env` và `.env.example`, chốt `APP_LOCALE=en` trong `phpunit.xml` | `lang/**`, `.env*`, `phpunit.xml` | Thấp |
| **2** | Nhóm C — 12 message auth + exception | `AuthMessage`, `ExceptionMessage` | Thấp |
| **3** | Nhóm B — ~85 message lỗi nghiệp vụ, làm theo từng module (payment → invoice → prescription → …) | 11 file `*Message.php` + chỗ dùng | Trung bình |
| **4** | Nhóm A — ~55 message envelope thành công | 13 file `*Message.php` + controller | Thấp |
| **5** | Test chốt: full suite 225 test phải xanh; thêm test quét đảm bảo không còn khoá thô lọt ra response | `tests/Feature/**` | — |

Sau mỗi bước đều chạy được và test xanh — anh có thể dừng ở bất kỳ bước nào.

Nếu anh muốn nhanh, **chỉ làm Bước 1 + 2 + 3** đã xử lý xong toàn bộ phần người dùng thực sự
nhìn thấy khi thao tác sai. Bước 4 thuần nhất quán, có thể để sau.

---

## 9. Anh cần quyết những gì

- [ ] **Chọn phương án**: A, B (đề xuất), hay C?
- [ ] **Phạm vi**: làm cả 5 bước, hay dừng ở Bước 3 (bỏ nhóm message thành công)?
- [ ] **Câu chữ**: các bản dịch ở mục 6 có ổn không? Có thuật ngữ nào phòng khám dùng khác không
      (ví dụ "phiếu khám" hay "hồ sơ khám", "toa thuốc" hay "đơn thuốc")?
- [ ] **`APP_LOCALE`**: đổi mặc định sang `vi` trong `.env.example` luôn, hay để `en` và chỉ đổi
      ở máy local?

Anh phản hồi 4 mục trên là em bắt đầu code theo đúng phạm vi đã chốt.
