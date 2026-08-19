# Bổ sung tổ chức frontend: permission constants & pagination helper

Tài liệu này là **đề xuất ý tưởng** để review, chưa triển khai code. Xuất phát từ việc rà soát
feature `patients` (xem `trien-khai-frontend.md`) và phát hiện 2 điểm lệch so với cấu trúc
chuẩn đã chốt ở mục 2.1 của tài liệu đó:

1. `core/permissions.js` được liệt kê trong cấu trúc thư mục chuẩn nhưng chưa tồn tại; permission
   string (`'PATIENTS.CREATE'`, `'PATIENTS.FINDALL'`, ...) đang được gõ tay, lặp lại ở nhiều nơi.
2. `features/patients/pagination.js` là logic phân trang thuần túy, không có gì đặc thù cho
   patient, nhưng lại nằm trong thư mục feature thay vì `core/`.

Nếu không xử lý trước khi làm FE-04 trở đi, 2 vấn đề này sẽ nhân bản ra mọi feature có
CRUD + danh sách (appointments, medicines, invoices, users, ...).

## 1. Vấn đề: permission string rải rác

### Hiện trạng

Chuỗi permission xuất hiện dạng literal ở 3 lớp khác nhau cho cùng 1 feature:

- Blade: `x-show="$store.auth.can('PATIENTS.CREATE')"` (index.blade.php, dashboard/index.blade.php)
- Sidebar cấu hình PHP: `'permissions' => ['PATIENTS.FINDALL']` (sidebar.blade.php:14)
- Alpine controller: `this.$store.auth.can('PATIENTS.UPDATE')` (patients/index.js)

Nguồn sự thật cho các chuỗi này hiện nằm ở backend:
`database/migrations/2026_08_05_015300_seed_permissions.php` và `database/seeders/RbacSeeder.php`.
Frontend không có gì tham chiếu tới nguồn đó — nếu backend đổi tên permission, phải tự tìm
từng file blade/js để sửa, và một chỗ gõ sai (`PATIENT.CREATE` thay vì `PATIENTS.CREATE`) sẽ âm
thầm fail-closed (ẩn nút) mà không có cảnh báo nào ở build time.

### Lựa chọn A — Chỉ tập trung constants trong JS, giữ nguyên literal trong Blade

Tạo `core/permissions.js` xuất ra object hằng số:

```js
export const PERMISSIONS = {
    PATIENTS: {
        FINDALL: 'PATIENTS.FINDALL',
        FINDONE: 'PATIENTS.FINDONE',
        CREATE: 'PATIENTS.CREATE',
        UPDATE: 'PATIENTS.UPDATE',
        DELETE: 'PATIENTS.DELETE',
    },
    // ... APPOINTMENTS, MEDICINES, INVOICES, USERS khi tới task tương ứng
};
```

Alpine controller dùng `this.$store.auth.can(PERMISSIONS.PATIENTS.CREATE)` thay vì chuỗi tay.
Blade **vẫn phải giữ literal** (`x-show="$store.auth.can('PATIENTS.CREATE')"`) vì attribute Blade
là HTML tĩnh, không import được module JS vào biểu thức Alpine.

- Ưu điểm: đơn giản, ít thay đổi, có autocomplete + lỗi biên dịch nếu gõ sai tên field trong JS.
- Nhược điểm: chỉ giải quyết được 1/3 chỗ rải rác (JS controller); Blade và sidebar config vẫn
  là chuỗi tay, vẫn có thể lệch khỏi backend mà không ai biết.

### Lựa chọn B — Controller expose getter theo ngữ nghĩa, Blade hết cần biết permission code

Alpine controller định nghĩa sẵn các getter dùng `PERMISSIONS`:

```js
// patients/index.js
get canCreate() {
    return this.$store.auth.can(PERMISSIONS.PATIENTS.CREATE);
},
get canUpdate() {
    return this.$store.auth.can(PERMISSIONS.PATIENTS.UPDATE);
},
get canDelete() {
    return this.$store.auth.can(PERMISSIONS.PATIENTS.DELETE);
},
```

Blade đổi thành `x-show="canCreate"`, `x-show="canUpdate"`, `x-show="canDelete"` — không còn
chuỗi permission nào trong Blade nữa, đọc cũng rõ nghĩa hơn là phải nhớ tên permission chính xác.

- Ưu điểm: xoá literal khỏi cả Blade lẫn JS controller, lỗi gõ sai chỉ có thể xảy ra ở đúng 1 chỗ
  (`core/permissions.js`), review code Blade dễ hơn vì đọc thấy `canCreate` thay vì chuỗi.
  Không cần lib mới.
- Nhược điểm: mỗi feature phải viết thêm 3–5 dòng getter lặp lại (create/update/delete/view...),
  hơi rườm rà cho feature nhỏ.

### Lựa chọn C — Helper sinh getter tự động (giảm boilerplate của B)

Thêm hàm dùng chung trong `core/permissions.js`:

```js
export function permissionGates(resource, actions) {
    const gates = {};

    for (const [getterName, action] of Object.entries(actions)) {
        gates[getterName] = () => Alpine.store('auth').can(`${resource}.${action}`);
    }

    return gates;
}
```

Controller chỉ cần:

```js
...permissionGates('PATIENTS', {
    canCreate: 'CREATE',
    canUpdate: 'UPDATE',
    canDelete: 'DELETE',
    canView: 'FINDONE',
}),
```

- Ưu điểm: giữ được lợi ích của B (Blade sạch), giảm lặp code giữa các feature, một chỗ định
  nghĩa mapping resource → action.
- Nhược điểm: thêm một lớp trừu tượng (indirection) mà nhóm phải học quy ước; có thể là
  over-engineering nếu số lượng feature CRUD không nhiều (hiện dự kiến ~8 feature).

### Đề xuất

Chọn **B** cho FE-03 trở đi: đủ để hết magic string trong Blade + JS mà không cần thêm khái
niệm mới, chi phí học gần như bằng 0. Chỉ nâng lên **C** nếu tới FE-06/FE-07/FE-08 thấy việc
lặp getter thực sự gây khó chịu (từ 4 feature CRUD tương tự trở lên là ngưỡng hợp lý để rút thành
helper dùng chung).

Không đề xuất A vì chỉ xử lý nửa vời — vẫn còn 2/3 nguồn magic string.

## 2. Vấn đề: `pagination.js` đặt sai vị trí

### Hiện trạng

`features/patients/pagination.js` chứa 2 hàm hoàn toàn generic:

- `emptyPaginationMeta()` — trả về shape mặc định của Laravel paginator meta.
- `calculateVisiblePages(meta)` — tính dải số trang hiển thị quanh trang hiện tại.

Không có tham chiếu nào tới "patient" trong file. Đây là logic UI-pagination sẽ cần lại nguyên
xi ở FE-04 (lịch hẹn), FE-05 (phiếu khám), FE-06 (toa thuốc), FE-07 (kho thuốc), FE-08 (hóa đơn),
FE-10 (người dùng) — tức 6/8 feature còn lại đều có bảng + phân trang server-side theo mục 3.4.

### Đề xuất

Chuyển file sang `core/pagination.js`, giữ nguyên tên hàm (đã đủ generic, không cần đổi tên).
Feature `patients` import từ `../../core/pagination` thay vì `./pagination`.

```text
core/
|-- api-client.js
|-- api-error.js
|-- auth-storage.js
|-- formatters.js
|-- permissions.js      <- mới (mục 1)
`-- pagination.js        <- chuyển từ features/patients/
```

Đây là thay đổi cơ học (di chuyển + sửa import), không có lựa chọn thay thế đáng cân nhắc — để
trong `core/` đúng với vai trò "cross-cutting utility" mà mục 2.1 của `trien-khai-frontend.md`
đã định nghĩa cho thư mục này.

## 3. Thứ tự triển khai đề xuất

1. Tạo `core/permissions.js` với `PERMISSIONS.PATIENTS.*` (đối chiếu đúng danh sách trong
   `RbacSeeder.php`), thêm getter `canCreate/canUpdate/canDelete/canView` vào `patients/index.js`
   và `patients/show.js`, sửa 2 file Blade + `sidebar.blade.php` để dùng getter thay vì chuỗi.
2. `git mv features/patients/pagination.js core/pagination.js`, sửa import trong
   `patients/index.js`.
3. Cập nhật mục 2.1 của `trien-khai-frontend.md` (thêm `permissions.js` đã tồn tại thật,
   `pagination.js` vào danh sách file `core/`) và tick lại checklist FE-03 cho khớp thực tế.
4. Khi làm FE-04 trở đi, mỗi feature CRUD mới bổ sung namespace tương ứng vào
   `PERMISSIONS` (`APPOINTMENTS`, `MEDICINES`, ...) và tái dùng `core/pagination.js` thay vì tạo
   file phân trang riêng.

## 4. Câu hỏi mở cần bạn quyết định trước khi code

- Chọn phương án B hay C cho permission gates? (đề xuất B, xem mục 1)
- `PERMISSIONS` constants có nên generate tự động từ `RbacSeeder.php`/migration (tránh lệch
  backend/frontend) hay chấp nhận maintain tay ở FE vì danh sách permission ít thay đổi?
