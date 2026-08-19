# Prescription `addItem` / `updateItem` / `removeItem` — Kế hoạch chờ review

## 1. Trạng thái tài liệu

- Trạng thái: **ĐÃ REVIEW — ĐÃ TRIỂN KHAI**.
- Kết quả: `PrescriptionTest` pass 29 test/115 assertions (12 test cũ của `store` + 17 test mới
  cho `addItem`/`updateItem`/`removeItem`); toàn bộ suite pass 139 test/976 assertions; Laravel
  Pint pass trên toàn bộ file được tạo/chỉnh trong task.
- Nguồn hướng dẫn bắt buộc: `skills/backend.md`.
- Kiến trúc áp dụng: **Controller + Service**.
- Phạm vi được yêu cầu (theo `docs/ke-hoach-chi-tiet.md` mục T3.5/T3.6/T3.7 và prompt gốc):

  > addItem/updateItem/removeItem cho prescription items với permission tương ứng; addItem
  > trùng medicine → 422; validate item thuộc đúng đơn (404 nếu sai); kho cập nhật trong
  > transaction + lockForUpdate.

- Chỉ triển khai 3 action: `addItem`, `updateItem`, `removeItem` trên `PrescriptionController`.
  **Không** đụng `index`, `show`, `update`, `destroy` của Prescription (vẫn ngoài phạm vi,
  giống ghi chú ở `docs/prescriptioncreate.md` mục 9).
- Tái sử dụng hàm trừ kho dùng chung `deductStockAndCreateItem()` đã tách sẵn trong
  `PrescriptionService` từ task `store` (T3.4) cho `addItem`; bổ sung logic hoàn kho/điều
  chỉnh delta riêng cho `updateItem`/`removeItem`.

Sau khi được duyệt, việc thực thi sẽ **chỉ** thay đổi các file liệt kê ở mục 7.

---

## 2. Mục tiêu nghiệp vụ

- **`addItem`**: thêm một dòng thuốc mới vào đơn đã tồn tại, trừ kho giống hệt logic đã dùng ở
  `store`. Nếu thuốc đã có trong đơn → 422 (yêu cầu dùng `updateItem` để đổi số lượng thay vì
  tạo dòng trùng).
- **`updateItem`**: sửa `quantity`/`dosage`/`usage_instruction` của một dòng đã có. Nếu
  `quantity` thay đổi, tính `delta = new_quantity - old_quantity` và điều chỉnh kho tương ứng:
  tăng thì trừ thêm kho (thiếu hàng → 422), giảm thì hoàn lại phần dư.
- **`removeItem`**: xóa một dòng thuốc khỏi đơn, hoàn lại đúng số lượng đã trừ vào kho
  (`stock += item.quantity`).
- Cả ba action đều phải xác nhận `{item}` trên URL **thuộc đúng** `{prescription}` trên URL —
  sai thì trả 404, không rò rỉ hay sửa nhầm item của đơn khác.
- Toàn bộ thao tác kho nằm trong `DB::transaction` + `lockForUpdate` trên dòng `Medicine` liên
  quan (nhất quán với T3.6 đã áp dụng ở `store`).

---

## 3. Audit repository hiện tại

### 3.1. Đã có sẵn (không cần sửa)

- `config/rbac.php`: bảng `actions` đã map `addItem → ADDITEM`, `updateItem → UPDATEITEM`,
  `removeItem → REMOVEITEM` (dùng chung, không cần thêm dòng riêng cho
  `PrescriptionController`).
- Permission catalog (`2026_08_05_015300_seed_permissions.php`) đã có
  `PRESCRIPTIONS.ADDITEM`, `PRESCRIPTIONS.UPDATEITEM`, `PRESCRIPTIONS.REMOVEITEM`.
- `RbacSeeder`: `DOCTOR` có cả ba permission trên; `ADMIN` có toàn bộ; `PHARMACIST` chỉ có
  `FINDALL`/`FINDONE` (không có quyền sửa item); `CASHIER`, `RECEPTIONIST` không có quyền nào
  trên prescription.
- `app/Models/PrescriptionItem.php`: đã có `Fillable`, `prescription()`, `medicine()`,
  cast `quantity`.
- `app/Models/Prescription.php`: đã có `items()` (`hasMany`).
- `app/Services/PrescriptionService.php`: đã có `deductStockAndCreateItem()` (private) — tách
  sẵn từ T3.4 để dùng chung, đúng như plan `docs/prescriptioncreate.md` đã nêu.
- `app/Http/Resources/PrescriptionItemResource.php`: đã có, dùng lại được cho response của cả
  ba action.
- `app/Constants/PrescriptionMessage.php`: đã có `MEDICINE_NOT_ACTIVE`,
  `MEDICINE_INSUFFICIENT_STOCK` — tái dùng được cho `updateItem`.

### 3.2. Chưa có — cần tạo trong task này

- Route cho `POST /prescriptions/{prescription}/items`,
  `PUT|PATCH /prescriptions/{prescription}/items/{item}`,
  `DELETE /prescriptions/{prescription}/items/{item}` — `routes/api.php` hiện chỉ có
  `POST /prescriptions`.
- `PrescriptionController@addItem`, `@updateItem`, `@removeItem`.
- `PrescriptionService::addItem()`, `::updateItem()`, `::removeItem()` — hiện chưa có.
- `app/Http/Requests/Prescription/AddPrescriptionItemRequest.php`.
- `app/Http/Requests/Prescription/UpdatePrescriptionItemRequest.php`.
- Message constants mới trong `PrescriptionMessage` (mục 6).
- Test cho ba action trong `tests/Feature/PrescriptionTest.php`.

---

## 4. API contract đề xuất

| Method | Endpoint | Controller action | Permission | Thành công |
|---|---|---|---|---:|
| POST | `/api/prescriptions/{prescription}/items` | `addItem` | `PRESCRIPTIONS.ADDITEM` | 201 |
| PUT/PATCH | `/api/prescriptions/{prescription}/items/{item}` | `updateItem` | `PRESCRIPTIONS.UPDATEITEM` | 200 |
| DELETE | `/api/prescriptions/{prescription}/items/{item}` | `removeItem` | `PRESCRIPTIONS.REMOVEITEM` | 200 |

```php
Route::post('/prescriptions/{prescription}/items', [PrescriptionController::class, 'addItem']);
Route::match(['put', 'patch'], '/prescriptions/{prescription}/items/{item}', [PrescriptionController::class, 'updateItem']);
Route::delete('/prescriptions/{prescription}/items/{item}', [PrescriptionController::class, 'removeItem']);
```

`{prescription}` dùng route model binding chuẩn (404 tự động nếu không tồn tại, đúng hành vi
sẵn có của các controller khác). `{item}` cũng bind theo `PrescriptionItem` qua type-hint,
nhưng việc "item có thuộc đúng prescription hay không" được **Service kiểm tra tường minh**
(không dựa vào `scopeBindings()` ngầm của Laravel) để đảm bảo message 404 nhất quán với
envelope chuẩn của repo — xem mục 5.

### 4.1. Body `addItem`

```json
{
  "medicine_id": 3,
  "quantity": 2,
  "dosage": "2 viên/lần, ngày 2 lần",
  "usage_instruction": "Uống sau ăn"
}
```

`AddPrescriptionItemRequest::rules()` dự kiến:

```php
return [
    'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
    'quantity' => ['required', 'integer', 'min:1'],
    'dosage' => ['required', 'string'],
    'usage_instruction' => ['nullable', 'string'],
];
```

Giống hệt rule của `items.*` trong `StorePrescriptionRequest`, chỉ khác không còn tiền tố
`items.*.` vì đây là body phẳng cho một item duy nhất.

### 4.2. Body `updateItem`

```json
{
  "quantity": 5,
  "dosage": "3 viên/lần, ngày 2 lần",
  "usage_instruction": null
}
```

`UpdatePrescriptionItemRequest::rules()` dự kiến:

```php
return [
    'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
    'dosage' => ['sometimes', 'required', 'string'],
    'usage_instruction' => ['sometimes', 'nullable', 'string'],
    'medicine_id' => ['prohibited'],
];
```

- `medicine_id` bị `prohibited`: đổi thuốc trong một dòng không nằm trong phạm vi task — muốn
  đổi thuốc thì `removeItem` rồi `addItem` lại, giữ đúng tinh thần "trùng thuốc phải dùng
  `updateItem` để đổi **số lượng**", không phải để đổi **loại thuốc**.
- Payload rỗng hoặc chỉ chứa `medicine_id` → 422 (giống `UpdateExaminationRequest`, yêu cầu ít
  nhất một field hợp lệ qua `after()` hoặc validate ở Service).

### 4.3. `removeItem`

Không có body — chỉ cần `{prescription}` và `{item}` trên URL.

### 4.4. Response

`addItem` (201) và `updateItem` (200) trả `PrescriptionItemResource` đã load `medicine`:

```json
{
  "success": true,
  "message": "Prescription item added",
  "data": {
    "id": 5,
    "medicine_id": 3,
    "quantity": 2,
    "dosage": "2 viên/lần, ngày 2 lần",
    "usage_instruction": "Uống sau ăn",
    "medicine": { "id": 3, "code": "MED-001A", "name": "Paracetamol 500mg", "unit": "Vỉ", "price": "1500.00", "is_active": true }
  }
}
```

`removeItem` (200) trả `data: null`, giống pattern `MedicineController::destroy`:

```json
{ "success": true, "message": "Prescription item removed", "data": null }
```

### 4.5. Lỗi dự kiến

| Trường hợp | HTTP | Ghi chú |
|---|---:|---|
| Chưa đăng nhập | 401 | Envelope unauthenticated hiện có |
| Thiếu permission theo action | 403 | `Missing permission: PRESCRIPTIONS.ADDITEM/UPDATEITEM/REMOVEITEM` |
| `{prescription}` không tồn tại | 404 | Route model binding chuẩn |
| `{item}` không tồn tại, hoặc tồn tại nhưng thuộc prescription khác | 404 | Service kiểm tra `item.prescription_id === prescription.id` |
| `addItem` với `medicine_id` đã có trong đơn | 422 | `medicine_id`: `Medicine {code} is already in this prescription. Use updateItem to change the quantity.` |
| `addItem`/`updateItem` thuốc `is_active=false` khi **tăng** lượng tiêu thụ | 422 | tái dùng `MEDICINE_NOT_ACTIVE` |
| `addItem`/`updateItem` (tăng) vượt tồn kho | 422 | tái dùng `MEDICINE_INSUFFICIENT_STOCK` |
| `updateItem` payload rỗng hoặc chỉ có `medicine_id` | 422 | `item`: `At least one prescription item field must be provided.` |
| `updateItem` gửi `medicine_id` | 422 | `medicine_id`: prohibited |

---

## 5. Thiết kế transaction, khóa, và validate "item thuộc đúng đơn"

Nguyên tắc chung cho cả ba action: mọi thao tác ảnh hưởng tới kho đều `lockForUpdate()` trên
dòng `Medicine` liên quan (đã áp dụng ở T3.6/`store`) — vì mọi request cùng sửa cùng một
`medicine_id` trong cùng một đơn đều phải đi qua khóa dòng thuốc đó, việc khóa `Medicine` tự
nhiên tuần tự hóa luôn các thao tác trùng lặp trên **cùng một cặp (prescription, medicine)**
(bao gồm cả race giữa hai `addItem` cùng thuốc, hay `addItem` chạy đồng thời `updateItem`/
`removeItem` trên chính dòng đó) — không cần khóa riêng ở cấp `Prescription`.

### `addItem`

```php
public function addItem(Prescription $prescription, array $data): PrescriptionItem
{
    return DB::transaction(function () use ($prescription, $data): PrescriptionItem {
        return $this->deductStockAndCreateItem($prescription, $data)->load('medicine');
    });
}
```

`deductStockAndCreateItem()` được sửa để **khóa `Medicine` trước, rồi mới kiểm tra trùng
thuốc** (không đảo ngược thứ tự) — vì khóa xảy ra trước đảm bảo hai `addItem` đồng thời cùng
thuốc/cùng đơn được tuần tự hóa: request thứ hai chỉ đọc được trạng thái "đã có item" sau khi
request thứ nhất commit.

```php
private function deductStockAndCreateItem(Prescription $prescription, array $item): PrescriptionItem
{
    $medicine = Medicine::query()->lockForUpdate()->findOrFail($item['medicine_id']);

    if (PrescriptionItem::query()
        ->where('prescription_id', $prescription->getKey())
        ->where('medicine_id', $medicine->getKey())
        ->exists()) {
        throw ValidationException::withMessages([
            'medicine_id' => [strtr(PrescriptionMessage::MEDICINE_ALREADY_IN_PRESCRIPTION, [':code' => $medicine->code])],
        ]);
    }

    if (! $medicine->is_active) { /* như cũ */ }
    if ($medicine->stock < $item['quantity']) { /* như cũ */ }

    $medicine->decrement('stock', $item['quantity']);

    return $prescription->items()->create([...]);
}
```

Vì `store` (T3.4) luôn tạo `Prescription` mới trong cùng transaction trước khi gọi hàm này,
việc thêm bước kiểm tra trùng không đổi hành vi hiện có của `store` (không thể có item trùng
trên một đơn vừa mới tạo) — chỉ bổ sung an toàn cho `addItem` gọi trên đơn đã tồn tại.

### `updateItem`

```php
public function updateItem(Prescription $prescription, PrescriptionItem $item, array $data): PrescriptionItem
{
    return DB::transaction(function () use ($prescription, $item, $data): PrescriptionItem {
        $this->assertItemBelongsToPrescription($item, $prescription);

        $lockedItem = PrescriptionItem::query()->lockForUpdate()->findOrFail($item->getKey());
        $medicine = Medicine::query()->lockForUpdate()->findOrFail($lockedItem->medicine_id);

        if (array_key_exists('quantity', $data)) {
            $delta = $data['quantity'] - $lockedItem->quantity;

            if ($delta > 0) {
                if (! $medicine->is_active) { /* MEDICINE_NOT_ACTIVE */ }
                if ($medicine->stock < $delta) { /* MEDICINE_INSUFFICIENT_STOCK */ }
                $medicine->decrement('stock', $delta);
            } elseif ($delta < 0) {
                $medicine->increment('stock', abs($delta));
            }
        }

        $lockedItem->update(Arr::only($data, ['quantity', 'dosage', 'usage_instruction']));

        return $lockedItem->refresh()->load('medicine');
    });
}
```

### `removeItem`

```php
public function removeItem(Prescription $prescription, PrescriptionItem $item): void
{
    DB::transaction(function () use ($prescription, $item): void {
        $this->assertItemBelongsToPrescription($item, $prescription);

        $lockedItem = PrescriptionItem::query()->lockForUpdate()->findOrFail($item->getKey());
        $medicine = Medicine::query()->lockForUpdate()->findOrFail($lockedItem->medicine_id);

        $medicine->increment('stock', $lockedItem->quantity);
        $lockedItem->delete();
    });
}
```

### Validate "item thuộc đúng đơn"

```php
private function assertItemBelongsToPrescription(PrescriptionItem $item, Prescription $prescription): void
{
    if ($item->prescription_id !== $prescription->getKey()) {
        throw new ModelNotFoundException();
    }
}
```

`ModelNotFoundException` đi qua exception handler sẵn có của repo (đã cấu hình cho
`/api/*` trả 404 theo envelope chuẩn — giống cách `findOrFail()` hoạt động ở mọi nơi khác),
nên không cần thêm xử lý riêng.

### Điểm cần xác nhận: `is_active` khi giảm số lượng / xóa item

`removeItem` và nhánh `delta < 0` của `updateItem` **không** kiểm tra `medicine.is_active` —
hoàn kho một thuốc đã ngừng kinh doanh vẫn hợp lệ vì đó là trả lại tồn kho, không phải tiêu thụ
thêm. Chỉ nhánh **tăng tiêu thụ** (`addItem`, hoặc `updateItem` với `delta > 0`) mới chặn thuốc
`is_active=false`. `docs/ke-hoach-chi-tiet.md` mục T3.6/T3.7 không nói rõ điểm này — đây là suy
luận hợp lý theo nghiệp vụ, cần bạn xác nhận trước khi code.

---

## 6. Message constants mới (`app/Constants/PrescriptionMessage.php`)

```php
public const ITEM_ADDED = 'Prescription item added';
public const ITEM_UPDATED = 'Prescription item updated';
public const ITEM_REMOVED = 'Prescription item removed';

public const MEDICINE_ALREADY_IN_PRESCRIPTION = 'Medicine :code is already in this prescription. Use updateItem to change the quantity.';

public const ITEM_UPDATE_FIELD_REQUIRED = 'At least one prescription item field must be provided.';

public const ITEM_MEDICINE_CANNOT_BE_CHANGED = 'The prescription item medicine cannot be changed.';
```

---

## 7. Danh sách file dự kiến thay đổi sau khi được duyệt

Tạo mới:

```text
app/Http/Requests/Prescription/AddPrescriptionItemRequest.php
app/Http/Requests/Prescription/UpdatePrescriptionItemRequest.php
```

Chỉnh sửa:

```text
app/Http/Controllers/PrescriptionController.php   (+addItem, +updateItem, +removeItem)
app/Services/PrescriptionService.php              (+addItem, +updateItem, +removeItem,
                                                     +assertItemBelongsToPrescription,
                                                     sửa deductStockAndCreateItem thêm check trùng)
app/Constants/PrescriptionMessage.php             (+6 hằng số mục 6)
routes/api.php                                    (+3 route)
tests/Feature/PrescriptionTest.php                (+test cho 3 action)
docs/auItem.md                                    (cập nhật trạng thái sau khi implement)
```

Không dự kiến sửa:

```text
app/Models/Prescription.php
app/Models/PrescriptionItem.php
app/Http/Resources/PrescriptionItemResource.php
app/Http/Requests/Prescription/StorePrescriptionRequest.php
database/migrations/*
config/rbac.php
database/seeders/RbacSeeder.php
database/migrations/2026_08_05_015300_seed_permissions.php
```

---

## 8. Feature test dự kiến (bổ sung vào `tests/Feature/PrescriptionTest.php`)

1. DOCTOR `addItem` hợp lệ vào đơn đã tồn tại → 201, stock giảm đúng, item được tạo.
2. `addItem` với `medicine_id` đã có trong đơn → 422, stock không đổi, không tạo item mới.
3. `addItem` thuốc `is_active=false` → 422.
4. `addItem` vượt tồn kho → 422, stock không đổi.
5. `addItem` vào `{prescription}` không tồn tại → 404.
6. DOCTOR `updateItem` tăng `quantity` đủ tồn kho → 200, stock giảm đúng phần chênh lệch.
7. `updateItem` tăng `quantity` vượt tồn kho → 422, stock và `quantity` không đổi.
8. `updateItem` giảm `quantity` → 200, stock tăng đúng phần dư.
9. `updateItem` chỉ sửa `dosage`/`usage_instruction`, không gửi `quantity` → 200, stock không
   đổi.
10. `updateItem` gửi `medicine_id` → 422, không có gì thay đổi.
11. `updateItem` payload rỗng → 422.
12. `updateItem`/`removeItem` với `{item}` thuộc một `prescription` khác (`{prescription}` và
    `{item}` không khớp) → 404, dữ liệu không đổi.
13. DOCTOR `removeItem` → 200, stock hoàn lại đúng `quantity`, item bị xóa khỏi DB.
14. `removeItem` một `{item}` không tồn tại → 404.
15. CASHIER/PHARMACIST/RECEPTIONIST gọi `addItem`/`updateItem`/`removeItem` → 403 với đúng
    permission tương ứng từng action.
16. Request chưa đăng nhập → 401.
17. (Điểm cộng) Test đồng thời hai `addItem` cùng `medicine_id` trên cùng đơn → chỉ một request
    thành công, request còn lại nhận 422 nêu ở mục 5, stock không bị trừ đôi.

Kết quả xác minh dự kiến sau implement:

```bash
php artisan test --filter=PrescriptionTest
php artisan test
vendor/bin/pint --test <task-files>
```

---

## 9. Nội dung ngoài phạm vi hiện tại

- `index`, `show`, `update`, `destroy` của `Prescription` (chưa được duyệt, xem
  `docs/prescriptioncreate.md` mục 9).
- Ràng buộc "khóa sửa item sau khi phiếu khám đã có hóa đơn" — vẫn là câu hỏi mở từ T3.5 mục 3
  của `ke-hoach-chi-tiet.md`. `Invoice`/`InvoiceController` (T3.8/T3.9) **chưa tồn tại** trong
  repo nên không có gì để khóa ở thời điểm này; nhắc lại để không quên khi implement Invoice.
- Ownership "DOCTOR chỉ sửa đơn/phiếu khám của chính mình" — giữ nguyên quyết định đã áp dụng ở
  Examination và Prescription `store`: chỉ dùng RBAC theo permission, chưa so khớp user đăng
  nhập với `doctor_id`.
- Activity log cho thao tác thêm/sửa/xóa item — hạ tầng activity log chưa tồn tại trong repo.

---

## 10. Việc cần xác nhận trước khi thực thi

- [x] Thứ tự trong `deductStockAndCreateItem()`: khóa `Medicine` trước, kiểm tra trùng thuốc
      sau (mục 5) — để tận dụng khóa dòng `Medicine` làm điểm tuần tự hóa, không khóa riêng
      `Prescription`.
- [x] `is_active` chỉ chặn khi **tăng** tiêu thụ (`addItem`, `updateItem` delta dương);
      `removeItem` và `updateItem` delta âm luôn được phép hoàn kho bất kể `is_active`.
- [x] `updateItem` cấm đổi `medicine_id` (`prohibited`); muốn đổi thuốc phải `removeItem` rồi
      `addItem` lại.
- [x] `removeItem` trả `data: null` (200), không trả lại `Prescription` đầy đủ.
- [x] Validate "item thuộc đúng đơn" bằng cách Service tự so khớp `item.prescription_id` rồi
      ném `ModelNotFoundException`, không dùng `Route::apiResource(...)->scopeBindings()` của
      Laravel.
