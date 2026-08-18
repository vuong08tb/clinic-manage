# Kế hoạch triển khai frontend Clinic Management

## 1. Quyết định đã chốt

Frontend sử dụng **Blade + Vite + Alpine.js + Tailwind CSS** và nằm cùng repository Laravel.
Blade chịu trách nhiệm layout và markup, Alpine.js quản lý trạng thái tương tác theo từng màn
hình, còn dữ liệu nghiệp vụ luôn được lấy từ REST API `/api/*`.

Các quyết định chính:

- Cấu trúc code: **feature-first + Blade UI components**.
- Layout tổng thể: **Clinical** — sáng, rõ ràng, sidebar đầy đủ, màu xanh y tế.
- Bảng dữ liệu: **Operations** — mật độ vừa/cao, filter rõ, sticky header, phân trang server-side.
- Lịch hẹn: **Modern** — hỗ trợ chuyển đổi danh sách/lịch tuần ở task lịch hẹn.
- Form CRUD vừa: dùng drawer; form ngắn/xác nhận: dùng modal; quy trình khám và kê toa:
  dùng trang riêng.
- Sidebar và hành động hiển thị theo permission từ `/api/me`; backend vẫn là nơi enforce quyền.
- Không viết `fetch()` hoặc business logic trực tiếp trong file Blade.

## 2. Kiến trúc frontend

```text
Blade page
   |
   +-- Blade layout / UI components
   |
   +-- Alpine feature controller
              |
              v
         API client dùng chung
              |
              +-- Bearer token
              +-- response envelope
              +-- 401 / 403 / 422
              +-- loading / network error
              |
              v
       Laravel REST API
```

### 2.1. Cấu trúc thư mục chuẩn

```text
resources/
|-- views/
|   |-- layouts/
|   |   |-- app.blade.php
|   |   `-- guest.blade.php
|   |-- components/
|   |   |-- layout/
|   |   |   |-- sidebar.blade.php
|   |   |   |-- topbar.blade.php
|   |   |   `-- page-header.blade.php
|   |   |-- ui/
|   |   |   |-- button.blade.php
|   |   |   |-- badge.blade.php
|   |   |   |-- modal.blade.php
|   |   |   |-- drawer.blade.php
|   |   |   |-- table.blade.php
|   |   |   |-- pagination.blade.php
|   |   |   |-- empty-state.blade.php
|   |   |   |-- skeleton.blade.php
|   |   |   `-- toast.blade.php
|   |   `-- form/
|   |       |-- field.blade.php
|   |       `-- error.blade.php
|   `-- pages/
|       |-- auth/
|       |-- dashboard/
|       |-- patients/
|       |-- appointments/
|       |-- examinations/
|       |-- prescriptions/
|       |-- medicines/
|       |-- invoices/
|       |-- specialties/
|       |-- doctors/
|       `-- users/
|-- js/
|   |-- app.js
|   |-- core/
|   |   |-- api-client.js
|   |   |-- api-error.js
|   |   |-- auth-storage.js
|   |   |-- permissions.js
|   |   `-- formatters.js
|   |-- stores/
|   |   |-- auth-store.js
|   |   `-- ui-store.js
|   `-- features/
|       |-- auth/
|       |-- dashboard/
|       |-- patients/
|       |-- appointments/
|       |-- examinations/
|       |-- prescriptions/
|       |-- medicines/
|       |-- invoices/
|       |-- specialties/
|       |-- doctors/
|       `-- users/
`-- css/
    `-- app.css
```

### 2.2. Quy tắc clean code

- Blade component là presentational component; không gọi API và không chứa business rule.
- Mỗi page chỉ khởi tạo một Alpine feature controller chính.
- Trạng thái toàn cục chỉ gồm auth và UI shell; không đưa dữ liệu CRUD vào global store.
- Mọi request đi qua `core/api-client.js` và dùng cùng một kiểu lỗi `ApiError`.
- Permission, status, key lưu trữ và formatter không được viết thành magic string rải rác.
- Lỗi `422` được ánh xạ về đúng field; `403` hiển thị thông báo không đủ quyền; `401`
  xóa phiên cục bộ và chuyển về `/login`.
- Backend là nguồn sự thật cuối cùng cho validation, permission và chuyển trạng thái.
- Component phải có trạng thái loading, empty, error và disabled phù hợp.

## 3. Hệ thống giao diện

### 3.1. App shell

- Sidebar desktop rộng `240px`; chế độ thu gọn rộng `72px`.
- Topbar cao `64px`; content padding `24px` trên desktop và `16px` trên mobile.
- Mobile/tablet nhỏ dùng sidebar dạng overlay drawer.
- Sidebar chia nhóm: Tổng quan, Tiếp đón, Khám bệnh, Dược, Tài chính, Danh mục,
  Hệ thống.
- Chỉ render nhóm/menu nếu người dùng có ít nhất một permission tương ứng.

### 3.2. Màu và trạng thái

- Primary: blue; accent: teal; nền: slate/white.
- `scheduled`, `pending`, `unpaid`: amber.
- `confirmed`: blue.
- `completed`, `paid`: emerald.
- `cancelled`, `failed`: red hoặc rose.
- `inactive`: slate.
- Không dùng màu làm dấu hiệu duy nhất; badge luôn có label.

### 3.3. Button

- Primary: hành động chính như Tạo lịch hẹn, Lưu.
- Secondary: Làm mới, Xuất dữ liệu.
- Ghost: xem chi tiết, menu hàng.
- Danger: xóa hoặc hủy nghiệp vụ.
- Icon button: đóng modal, mở sidebar/menu; luôn có `aria-label`.
- Mỗi page header chỉ có tối đa một primary action.

### 3.4. Grid và table

- KPI grid: 4 cột desktop, 2 cột tablet, 1 cột mobile.
- Form: 2 cột desktop, 1 cột mobile; textarea chiếm toàn hàng.
- Table có search debounce, filter, skeleton, empty state, sticky header và pagination server-side.
- Row action sử dụng menu ba chấm; không thêm checkbox nếu chưa có batch action.
- Mobile hiển thị dữ liệu quan trọng dưới dạng row rút gọn hoặc card.

### 3.5. Modal, drawer và trang riêng

| Thành phần | Trường hợp sử dụng |
|---|---|
| Popover | Menu ba chấm, filter nhỏ, user menu |
| Confirm modal | Xóa, hủy lịch, hủy hóa đơn, đổi trạng thái nhạy cảm |
| Form modal | Form ngắn tối đa khoảng 5 field |
| Drawer | Tạo/sửa bệnh nhân, xem nhanh lịch hẹn/hóa đơn, điều chỉnh tồn kho |
| Trang riêng | Khám bệnh, kê toa, chi tiết bệnh nhân, thanh toán |

## 4. Điều hướng theo nghiệp vụ

```text
TỔNG QUAN
`-- Dashboard

TIẾP ĐÓN
|-- Bệnh nhân
`-- Lịch hẹn

KHÁM BỆNH
|-- Phiếu khám
`-- Toa thuốc

DƯỢC
`-- Kho thuốc

TÀI CHÍNH
|-- Hóa đơn
`-- Thanh toán

DANH MỤC
|-- Chuyên khoa
`-- Bác sĩ

HỆ THỐNG
`-- Người dùng
```

## 5. Kế hoạch triển khai theo task

Quy ước trạng thái:

- `[ ]`: chưa thực hiện.
- `[-]`: đang thực hiện hoặc còn tiêu chí chưa đạt.
- `[x]`: đã hoàn thành và kiểm tra.

### FE-00 — Nền tảng frontend

Mục tiêu: tạo app shell, API client, auth store, UI store và convention dùng chung.

- [x] Cài Alpine.js và tạo entry Vite.
- [x] Tạo `api-client`, `ApiError` và auth storage có namespace.
- [x] Chuẩn hóa xử lý `401`, `403`, `422` và lỗi mạng.
- [x] Tạo layout guest và authenticated layout.
- [x] Tạo sidebar responsive, topbar và page header.
- [x] Tạo các UI component nền: button, badge, skeleton, empty state, toast.
- [x] Sidebar ẩn/hiện theo permission.
- [x] `npm run build` thành công.
- [x] Không có JavaScript inline trong Blade page.

### FE-01 — Đăng nhập và đăng xuất

Mục tiêu: hoàn thiện luồng `/login -> /api/login -> /dashboard` và logout.

- [x] Form email/password có label và autocomplete đúng.
- [x] Có loading state và chống submit lặp.
- [x] Hiển thị lỗi credential và lỗi validation từ API.
- [x] Lưu Bearer token bằng key có namespace.
- [x] Gọi `/api/me` khi khôi phục phiên.
- [x] Người đã đăng nhập truy cập `/login` được chuyển về dashboard.
- [x] Người chưa đăng nhập truy cập trang nội bộ được chuyển về login.
- [x] Logout gọi `/api/logout`, xóa local state và token.
- [x] Có focus-visible và breakpoint responsive cho mobile.

### FE-02 — Dashboard

Mục tiêu: dashboard role-aware, không gọi endpoint người dùng không có quyền.

- [x] Header chào người dùng và hiển thị role.
- [x] KPI cards được chọn theo permission.
- [x] Bản đầu tổng hợp số liệu từ API list hiện có với `per_page=1`.
- [x] Lịch hôm nay lấy từ `/api/appointments?date=YYYY-MM-DD` khi có quyền.
- [x] Quick actions hiển thị theo permission.
- [x] Có loading, partial error và empty state.
- [x] Không phụ thuộc `/api/stats` cho tới khi backend triển khai endpoint này.
- [x] Responsive 4/2/1 cột.

### FE-03 — Bệnh nhân

- [ ] Danh sách, search và pagination server-side.
- [ ] Drawer tạo/sửa bệnh nhân.
- [ ] Validation `422` theo field.
- [ ] Trang chi tiết bệnh nhân.
- [ ] Action hiển thị theo `PATIENTS.*`.
- [ ] Empty/loading/error state.

### FE-04 — Lịch hẹn

- [ ] Danh sách có search, ngày, bác sĩ, bệnh nhân và status filter.
- [ ] Chuyển đổi list/calendar week.
- [ ] Modal tạo lịch; drawer xem/sửa.
- [ ] Hiển thị đúng chuyển trạng thái được backend cho phép.
- [ ] Cảnh báo xung đột lịch từ lỗi API.
- [ ] Action hiển thị theo `APPOINTMENTS.*`.

### FE-05 — Phiếu khám

- [ ] Danh sách phiếu khám có filter/pagination.
- [ ] Trang tạo khám từ lịch đã confirmed.
- [ ] Trang xem/sửa phiếu khám.
- [ ] Tách section triệu chứng, chẩn đoán, kết luận và ghi chú.
- [ ] Bảo vệ hành động theo `EXAMINATIONS.*`.

### FE-06 — Toa thuốc

- [ ] Bổ sung/xác nhận API list và detail toa thuốc trước khi làm UI danh sách.
- [ ] Trang tạo toa từ phiếu khám.
- [ ] Bảng medicine item editable.
- [ ] Thêm/sửa/xóa item theo permission.
- [ ] Hiển thị tồn kho và lỗi vượt tồn kho.
- [ ] Có confirm khi xóa item.

### FE-07 — Kho thuốc

- [ ] Danh sách/search/filter tồn kho/pagination.
- [ ] Badge tồn kho và trạng thái active.
- [ ] Drawer tạo/sửa thuốc.
- [ ] Drawer điều chỉnh tồn kho có lý do/số lượng rõ ràng.
- [ ] Confirm trước thao tác xóa.
- [ ] Action theo `MEDICINES.*`.

### FE-08 — Hóa đơn và thanh toán

- [ ] Danh sách hóa đơn theo status.
- [ ] Detail drawer hiển thị examination và items.
- [ ] Tạo/cập nhật/hủy hóa đơn theo permission.
- [ ] Tạo payment và chuyển tới `approval_url`.
- [ ] Có trang return/cancel PayPal riêng.
- [ ] Capture payment và cập nhật trạng thái hóa đơn.
- [ ] Không hiển thị hoặc log dữ liệu thanh toán nhạy cảm.

### FE-09 — Chuyên khoa và bác sĩ

- [ ] CRUD chuyên khoa bằng table + modal/drawer.
- [ ] Danh sách bác sĩ có filter chuyên khoa.
- [ ] Form bác sĩ chọn user và specialty.
- [ ] Trang chi tiết bác sĩ.
- [ ] Action theo `SPECIALTIES.*` và `DOCTORS.*`.

### FE-10 — Người dùng

- [ ] Bổ sung/xác nhận `/api/roles` trước khi làm form role selector.
- [ ] Danh sách/search/filter role/status/pagination.
- [ ] Drawer tạo/sửa người dùng.
- [ ] Confirm khóa/mở tài khoản.
- [ ] Không cho thao tác làm mất admin hoạt động cuối cùng; hiển thị đúng lỗi backend.
- [ ] Action theo `USERS.*`.

### FE-11 — Hoàn thiện và production hardening

- [ ] Chuyển auth sang cookie HttpOnly/stateful Sanctum hoặc chốt biện pháp giảm rủi ro token.
- [ ] Đặt expiration/token revocation policy.
- [ ] Accessibility: focus trap, escape, aria label, contrast, keyboard navigation.
- [ ] Kiểm tra responsive desktop/tablet/mobile.
- [ ] Kiểm tra luồng cho ADMIN, RECEPTIONIST, DOCTOR, PHARMACIST, CASHIER.
- [ ] Unit test cho API client/formatter và browser test cho auth-critical flow.
- [ ] Docker multi-stage build frontend; production dùng Nginx + PHP-FPM.
- [ ] Không commit secret, token hoặc dữ liệu bệnh nhân thật.

## 6. API gap cần xử lý

Các capability đã có trong tài liệu/RBAC nhưng chưa có route tương ứng trong source hiện tại:

- `GET /api/stats` cho dashboard aggregate.
- `GET /api/roles` cho role selector.
- API list/detail toa thuốc cho bác sĩ và dược sĩ.
- API list/detail thanh toán nếu cần màn hình giao dịch độc lập.
- PayPal return/cancel route riêng cho frontend.

Trong khi chưa có `/api/stats`, dashboard chỉ gọi các list endpoint mà user có permission và
lấy `meta.total`; không phát request dẫn tới `403`.

## 7. Definition of Done cho mỗi màn hình

- [ ] Route web và Blade page đã có.
- [ ] Feature controller tách khỏi Blade.
- [ ] Permission kiểm soát menu và action.
- [ ] Loading, empty, error và success feedback đầy đủ.
- [ ] Lỗi `422` hiển thị đúng field.
- [ ] Không submit trùng và không để stale state sau khi đóng form.
- [ ] Responsive tối thiểu ở 375px, 768px và desktop.
- [ ] Có self-test theo role được phép và role bị từ chối.
- [ ] Build frontend thành công.
- [ ] Checklist task và tài liệu được cập nhật.

## 8. Nhật ký triển khai

### 2026-08-18 — FE-00, FE-01, FE-02

- `npm install`: thành công, tạo `package-lock.json`, audit không phát hiện vulnerability.
- `npm run build`: thành công với Vite; manifest và bundle được sinh trong `public/build`.
- Route đã kiểm tra: `/` redirect `/dashboard`, `/login` và `/dashboard` trả Blade page.
- Test frontend: 4 test pass, 8 assertions (`FrontendPageTest` và `ExampleTest`).
- Full backend suite: 187 test pass; còn 9 test `AppointmentTest` thất bại do fixture dùng
  ngày cố định `2026-08-15`, đã nằm trong quá khứ tại thời điểm chạy `2026-08-18`. Đây là lỗi
  test theo thời gian có sẵn, không phát sinh từ frontend.
