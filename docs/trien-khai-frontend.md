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
|   |   |-- pagination.js
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

- [x] Danh sách, search và pagination server-side.
- [x] Drawer tạo/sửa bệnh nhân.
- [x] Validation `422` theo field.
- [x] Trang chi tiết bệnh nhân.
- [x] Action hiển thị theo `PATIENTS.*`.
- [x] Empty/loading/error state.

### FE-04 — Lịch hẹn

- [x] Danh sách có search, ngày, bác sĩ, bệnh nhân và status filter.
- [x] Chuyển đổi list/calendar week.
- [x] Modal tạo lịch; drawer xem/sửa.
- [x] Hiển thị đúng chuyển trạng thái được backend cho phép.
- [x] Cảnh báo xung đột lịch từ lỗi API.
- [x] Action hiển thị theo `APPOINTMENTS.*`.

### FE-05 — Phiếu khám

- [x] Danh sách phiếu khám có filter/pagination.
- [x] Trang tạo khám từ lịch đã confirmed.
- [x] Trang xem/sửa phiếu khám.
- [-] Tách section triệu chứng, chẩn đoán, kết luận và ghi chú — backend chỉ có 2 field
      `diagnosis`/`notes`, không có cột `symptoms`/`conclusion`; đã làm đúng theo dữ liệu thật
      (2 section: Chẩn đoán, Ghi chú). Cần thêm migration nếu muốn tách đủ 4 section như tên gọi.
- [x] Bảo vệ hành động theo `EXAMINATIONS.*`.

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

### 2026-08-19 — FE-03 và dọn cấu trúc `core/`

- Hoàn thiện FE-03 (bệnh nhân): danh sách + search + pagination server-side, drawer tạo/sửa,
  validation `422` theo field, trang chi tiết, empty/loading/error state, action theo
  `PATIENTS.*`.
- Tạo `core/permissions.js` (`PERMISSIONS.PATIENTS.*`) đối chiếu `RbacSeeder.php`; chuyển
  `patients/index.js` và `patients/show.js` sang dùng getter ngữ nghĩa (`canCreate`,
  `canUpdate`, `canDelete`, `canView`) thay vì gọi `$store.auth.can('PATIENTS.X')` bằng chuỗi
  tay rải rác trong Blade.
- Chuyển `features/patients/pagination.js` (logic phân trang thuần túy, không đặc thù patient)
  sang `core/pagination.js` để tái dùng cho các feature danh sách khác từ FE-04 trở đi.
- `sidebar.blade.php` giữ nguyên chuỗi permission dạng PHP array — đây là cấu hình PHP-side,
  không áp dụng được getter JS, và đã là nguồn tập trung duy nhất cho toàn bộ menu nên không
  cần refactor thêm ở bước này.
- `npm run build` thành công sau refactor.

### 2026-08-19 — FE-04 (Lịch hẹn)

- Hoàn thiện FE-04: danh sách có search/ngày/trạng thái/bác sĩ filter, chuyển đổi list/calendar
  tuần, modal tạo lịch, drawer xem/sửa + hành động chuyển trạng thái, cảnh báo xung đột lịch qua
  lỗi `422` field `scheduled_at`, action theo `APPOINTMENTS.*`.
- Filter "bệnh nhân" không làm dropdown/combobox riêng như filter bác sĩ — gộp vào ô `q` chung vì
  `Appointment::scopeSearch` đã tìm theo tên/SĐT/mã bệnh nhân trong `q`; combobox tìm bệnh nhân
  chỉ dùng ở modal tạo lịch (bắt buộc chọn đúng 1 `patient_id`).
- Bổ sung `core/permissions.js` (`PERMISSIONS.APPOINTMENTS.*`, không có `DELETE` vì backend không
  có endpoint xóa lịch hẹn) và `core/formatters.js` (`localDateTimeInput` cho input
  `datetime-local`).
- `features/appointments/status-transitions.js` mirror tay `AppointmentService::ALLOWED_TRANSITIONS`
  (`app/Services/AppointmentService.php`) để UI chỉ hiện đúng nút chuyển trạng thái hợp lệ — cần
  tự đồng bộ nếu backend đổi luật chuyển trạng thái.
- Sửa 1 lỗi cú pháp PHP khi tự tích hợp (thiếu 1 dấu `]` đóng mảng `items` trong
  `sidebar.blade.php` khi thêm mục "Lịch hẹn"), và 2 chỗ dashboard còn sót nút/label cũ trỏ vào
  toast "sắp có FE-03/FE-04" — đã đổi thành link thật tới `/patients` và `/appointments`.
- Self-test theo role được phép/bị từ chối: đã có sẵn và đầy đủ trong `AppointmentTest.php`
  (`test_doctor_and_cashier_are_read_only`, `test_update_status_enforces_authentication_and_permission_matrix`,
  `test_appointment_read_requires_authentication_and_permission`) — không phải viết thêm. Chỉ bổ
  sung `test_appointment_index_page_is_available` vào `FrontendPageTest.php` theo đúng pattern có
  sẵn của patients/dashboard (test này chỉ xác nhận Blade shell render, không phân biệt theo role
  vì route web không có gate phía server — quyền chỉ ẩn/hiện ở client). Dự án chưa có công cụ
  browser test (không có Dusk/Playwright/Vitest trong `composer.json`/`package.json`), nên hành vi
  ẩn/hiện theo quyền ở Alpine (`canCreate`, `canUpdate`, `canUpdateStatus`, `canView`) vẫn cần
  kiểm tra tay theo role khi QA thủ công.
- Full backend suite: 190/199 pass; 9 fail vẫn là `AppointmentTest` do fixture ngày cố định
  `2026-08-15` đã ở quá khứ — lỗi có sẵn từ trước, không phải regression của FE-04.
- `npm run build` và `php artisan view:cache` (compile toàn bộ Blade) đều sạch sau khi tích hợp.
- Sửa bug múi giờ: `createPayload`/`editPayload` từng gửi thẳng chuỗi `datetime-local` (không có
  offset) lên API; vì `config('app.timezone')` là `UTC`, Carbon hiểu nhầm giờ nhập là giờ UTC,
  lệch 7 tiếng so với giờ Việt Nam người dùng chọn. Sửa bằng
  `new Date(form.scheduled_at).toISOString()` trước khi gửi để chuyển đúng sang UTC kèm `Z`.

### 2026-08-19 — FE-05 (Phiếu khám) và fix lỗ hổng phân quyền chéo bác sĩ

- Hoàn thiện FE-05: danh sách có filter theo bác sĩ (dropdown) và bệnh nhân (combobox tìm kiếm,
  API examinations không có `q` nên không gộp được như appointments), trang tạo phiếu khám riêng
  (`/examinations/create`, chọn lịch hẹn `confirmed` qua combobox hoặc pre-fill bằng
  `?appointment_id=`), trang xem/sửa riêng (`/examinations/{id}`), action theo `EXAMINATIONS.*`.
- Backend chỉ có 2 field nội dung (`diagnosis`, `notes`) — không có `symptoms`/`conclusion` như
  tên gọi checklist yêu cầu; đã làm đúng theo dữ liệu thật, xem chi tiết ở dòng checklist FE-05.
- Thêm link "Tạo phiếu khám" trong drawer chi tiết lịch hẹn (trang Appointments), chỉ hiện khi
  lịch đang `confirmed` và có quyền `EXAMINATIONS.CREATE` — đóng luồng "quy trình khám bắt đầu từ
  lịch hẹn đã xác nhận" mà thiết kế ban đầu mô tả nhưng bị bỏ sót khi giao code lần đầu.
- **Phát hiện lỗ hổng bảo mật thật** (đã kiểm chứng bằng test, không chỉ đọc code): bất kỳ
  tài khoản có role DOCTOR nào cũng tạo/sửa được phiếu khám của bác sĩ khác — middleware
  `EnsurePermission` chỉ check permission theo role, không check dữ liệu có thuộc về người thao
  tác không. Chi tiết đầy đủ (nguyên nhân, cách phát hiện, code fix, cách tự kiểm tra) ở
  `docs/fixExamination.md`. Đã thử áp dụng fix và kiểm chứng hoạt động đúng (bác sĩ khác bị chặn
  tạo/sửa, bác sĩ sở hữu và ADMIN vẫn hoạt động bình thường), nhưng **đã revert lại theo yêu cầu
  — chưa muốn sửa vào lúc này**. Code hiện tại vẫn còn lỗ hổng này, cách sửa đã có sẵn trong
  `fixExamination.md`, áp dụng lại khi nào sẵn sàng.
- Self-test: thêm `test_examination_index_page_is_available`,
  `test_examination_create_page_is_available`, `test_examination_detail_page_is_available` vào
  `FrontendPageTest.php`. `ExaminationTest.php` (có sẵn, không phải viết mới) đã đủ RBAC theo
  role được phép/bị từ chối cho examinations.
- Full backend suite: 191/200 pass; 9 fail vẫn là `AppointmentTest` do fixture ngày cố định, có
  sẵn từ trước, không liên quan FE-05.
- `npm run build` sạch sau khi thêm link "Tạo phiếu khám".
