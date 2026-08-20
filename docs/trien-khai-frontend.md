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
- Trang danh sách là trang chính của mỗi chức năng: thêm/sửa/xem đều mở **modal**, mọi xác nhận
  (xóa, hủy, đổi trạng thái) mở **popup confirm**. Không dùng drawer, không tạo trang riêng cho
  create/edit/detail.
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
|   |   |   |-- modal.blade.php          # shell dùng chung cho form/detail modal
|   |   |   |-- confirm-modal.blade.php  # popup xác nhận, nổi trên modal đang mở
|   |   |   |-- row-action.blade.php     # nút trong cột "Thao tác"
|   |   |   |-- table.blade.php
|   |   |   |-- pagination.blade.php
|   |   |   |-- empty-state.blade.php
|   |   |   |-- skeleton.blade.php
|   |   |   `-- toast.blade.php
|   |   `-- form/
|   |       |-- field.blade.php
|   |       `-- error.blade.php
|   `-- pages/                           # mỗi feature chỉ có index.blade.php (trang danh sách)
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
- Feature controller đặt tên state/hàm modal giống nhau ở mọi chức năng: `formOpen`, `formMode`,
  `editingId`, `form`, `formErrors`, `formMessage`, `submitting`, `detailOpen`, `detail`,
  `detailLoading`, `detailError`, cùng bộ hàm `openCreateModal()`, `openEditModal()`,
  `closeFormModal()`, `openDetailModal()`, `closeDetailModal()`, `editFromDetail()`,
  `submitForm()`, `fieldError()`, `handleEscape()`.

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
- Cột cuối luôn tên là "Thao tác" và chứa toàn bộ hành động của dòng: Xem, Sửa, Xóa và các nút
  đổi trạng thái (ví dụ "Chuyển sang: Hoàn thành"). Không giấu hành động trong màn hình chi tiết.
- Nút thao tác dùng `<x-ui.row-action>`: **icon button**, hover/focus hiện **tooltip** (nền
  `slate-900`, nổi phía trên nút). Dưới `md` tooltip trở thành text inline luôn hiện vì thiết bị
  cảm ứng không có hover. Tone: `primary` (Xem), `neutral` (Sửa), `success` (chuyển trạng thái
  tiến tới), `danger` (Xóa/hủy). Label động truyền qua `label-expr`, tone động qua `tone-expr`;
  luôn có `aria-label`.
- Tooltip neo theo **cạnh phải** của nút (`md:right-0 md:bottom-full`), không căn giữa: tooltip
  căn giữa sẽ tràn ra ngoài wrapper `overflow-x-auto` của bảng và sinh thanh cuộn ngang ngay cả
  khi đang ẩn.
- `<x-ui.icon>` đổi kích thước bằng prop `size` (`size="h-4 w-4"`), không truyền qua `class` —
  class merge sẽ thua default `h-5 w-5` do thứ tự CSS của Tailwind.
- Không thêm checkbox nếu chưa có batch action.
- Mobile hiển thị dữ liệu quan trọng dưới dạng card, kèm đúng bộ nút "Thao tác" như desktop.

### 3.5. Modal và popup xác nhận

Trang danh sách là trang chính của chức năng; mọi thao tác diễn ra ngay trên đó.

| Thành phần | Trường hợp sử dụng | Component |
|---|---|---|
| Form modal | Thêm mới và Sửa (một modal, phân biệt bằng `formMode`) | `<x-ui.modal>` |
| Detail modal | Xem chi tiết, read-only, footer có nút "Sửa" | `<x-ui.modal>` |
| Confirm popup | Xóa, hủy lịch, hủy hóa đơn, đổi trạng thái | `<x-ui.confirm-modal>` |
| Popover | Filter nhỏ, user menu | — |

- Không dùng drawer ở bất kỳ chức năng nào.
- Không tạo route/page riêng cho create/edit/detail — kể cả khám bệnh, kê toa và thanh toán;
  quy trình dài dùng modal `size="xl"` và chia section bên trong modal.
- Confirm popup nằm ở `z-[60]`, nổi trên form/detail modal (`z-50`).
- Modal size: `sm` (confirm), `md`, `lg` (mặc định), `xl` (form nhiều section).
- Deep link giữa các chức năng dùng query string trên trang danh sách để mở sẵn modal, ví dụ
  `/examinations?appointment_id=12`, sau đó `history.replaceState` về URL sạch.

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
- [x] Modal thêm/sửa bệnh nhân.
- [x] Validation `422` theo field.
- [x] Modal xem chi tiết bệnh nhân.
- [x] Confirm popup khi xóa.
- [x] Action hiển thị theo `PATIENTS.*`.
- [x] Empty/loading/error state.

### FE-04 — Lịch hẹn

- [x] Danh sách có search, ngày, bác sĩ, bệnh nhân và status filter.
- [x] Chuyển đổi list/calendar week.
- [x] Modal tạo/sửa lịch; modal xem chi tiết.
- [x] Nút chuyển trạng thái nằm ở cột "Thao tác" của danh sách, xác nhận bằng confirm popup.
- [x] Hiển thị đúng chuyển trạng thái được backend cho phép.
- [x] Cảnh báo xung đột lịch từ lỗi API.
- [x] Action hiển thị theo `APPOINTMENTS.*`.

### FE-05 — Phiếu khám

- [x] Danh sách phiếu khám có filter/pagination.
- [x] Modal tạo khám từ lịch đã confirmed (kể cả deep link `?appointment_id=`).
- [x] Modal xem chi tiết và modal sửa phiếu khám.
- [-] Tách section triệu chứng, chẩn đoán, kết luận và ghi chú — backend chỉ có 2 field
      `diagnosis`/`notes`, không có cột `symptoms`/`conclusion`; đã làm đúng theo dữ liệu thật
      (2 section: Chẩn đoán, Ghi chú). Cần thêm migration nếu muốn tách đủ 4 section như tên gọi.
- [x] Bảo vệ hành động theo `EXAMINATIONS.*`.

### FE-06 — Toa thuốc

- [x] Bổ sung/xác nhận API list và detail toa thuốc trước khi làm UI danh sách.
- [x] Modal tạo toa từ phiếu khám (`size="xl"`).
- [x] Bảng medicine item editable.
- [x] Thêm/sửa/xóa item theo permission.
- [x] Hiển thị tồn kho và lỗi vượt tồn kho.
- [x] Có confirm khi xóa item.

### FE-07 — Kho thuốc

- [x] Danh sách/search/filter tồn kho/pagination.
- [x] Badge tồn kho và trạng thái active.
- [x] Modal thêm/sửa thuốc.
- [x] Modal điều chỉnh tồn kho có lý do/số lượng rõ ràng.
- [x] Confirm trước thao tác xóa.
- [x] Action theo `MEDICINES.*`.

### FE-08 — Hóa đơn và thanh toán

- [x] Danh sách hóa đơn theo status.
- [x] Modal chi tiết hiển thị examination và items.
- [x] Tạo/cập nhật/hủy hóa đơn theo permission.
- [x] Tạo payment và chuyển tới `approval_url`.
- [x] Có trang return/cancel PayPal riêng.
- [x] Capture payment và cập nhật trạng thái hóa đơn.
- [x] Không hiển thị hoặc log dữ liệu thanh toán nhạy cảm.

### FE-09 — Chuyên khoa và bác sĩ

- [x] CRUD chuyên khoa bằng table + modal.
- [x] Danh sách bác sĩ có filter chuyên khoa.
- [x] Form bác sĩ chọn user và specialty.
- [x] Modal chi tiết bác sĩ.
- [x] Action theo `SPECIALTIES.*` và `DOCTORS.*`.

### FE-10 — Người dùng

- [x] Bổ sung/xác nhận `/api/roles` trước khi làm form role selector.
- [x] Danh sách/search/filter role/status/pagination.
- [x] Modal thêm/sửa người dùng.
- [x] Confirm khóa/mở tài khoản.
- [x] Không cho thao tác làm mất admin hoạt động cuối cùng; hiển thị đúng lỗi backend.
- [x] Action theo `USERS.*`.

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

Trong khi chưa có `/api/stats`, dashboard chỉ gọi các list endpoint mà user có permission và
lấy `meta.total`; không phát request dẫn tới `403`.

## 7. Definition of Done cho mỗi màn hình

- [ ] Route web và Blade page đã có (một route `/<feature>`, một trang danh sách).
- [ ] Thêm/sửa/xem chạy trong modal ngay trên trang danh sách; xác nhận dùng confirm popup.
- [ ] Cột "Thao tác" chứa đủ hành động của dòng (kể cả đổi trạng thái) ở cả desktop và mobile.
- [ ] Feature controller tách khỏi Blade và dùng đúng bộ tên state/hàm modal chuẩn.
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

### 2026-08-19 — Chuẩn hóa UI/UX: modal thống nhất cho thêm/sửa/xem

Lý do: khi review 3 chức năng đã làm (`/patients`, `/appointments`, `/examinations`), mỗi chức
năng lại dùng một kiểu khác nhau (drawer, modal, trang riêng) nên trải nghiệm không nhất quán.
Chốt lại: **trang danh sách là trang chính của chức năng**, mọi thao tác thêm/sửa/xem mở modal,
mọi xác nhận mở popup.

Thay đổi convention (mục 1, 2.1, 2.2, 3.4, 3.5, 7 của tài liệu này và `skills/frontend.md`):

- Bỏ hoàn toàn drawer cho CRUD và bỏ trang riêng cho create/edit/detail.
- Thêm 3 Blade component dùng chung: `ui/modal.blade.php` (shell form/detail modal),
  `ui/confirm-modal.blade.php` (popup xác nhận, `z-[60]` để nổi trên modal đang mở),
  `ui/row-action.blade.php` (nút cột "Thao tác").
- Chốt bộ tên state/hàm modal giống nhau ở mọi feature controller (`formOpen`, `formMode`,
  `detailOpen`, `openCreateModal()`, `openEditModal()`, `openDetailModal()`, `submitForm()`,
  `handleEscape()`…), để chức năng mới không phải phát minh lại cách đặt tên.

Áp dụng cho code hiện có:

- `/patients`: drawer tạo/sửa -> form modal; trang `/patients/{id}` -> detail modal (footer có
  nút "Sửa"); confirm xóa dùng `<x-ui.confirm-modal>`. Xóa `pages/patients/show.blade.php`,
  `features/patients/show.js` và route `web.patients.show`.
- `/appointments`: drawer chi tiết/sửa -> detail modal + form modal dùng chung với luồng tạo
  (`formMode`). **Nút "Chuyển sang: Hoàn thành"/"Chuyển sang: Đã hủy" chuyển ra cột "Thao tác"**
  của danh sách (cả bảng desktop và card mobile) thay vì nằm trong drawer; xác nhận đổi trạng
  thái dùng confirm popup. Confirm inline dạng banner vàng trong drawer đã bị bỏ.
- `/examinations`: trang `/examinations/create` và `/examinations/{id}` -> create/edit modal và
  detail modal trên trang danh sách. Link "Tạo phiếu khám" ở chi tiết lịch hẹn nay trỏ tới
  `/examinations?appointment_id=<id>`: trang danh sách tự mở modal tạo với lịch hẹn đã chọn sẵn
  rồi `history.replaceState` về `/examinations`. Xóa `pages/examinations/create.blade.php`,
  `pages/examinations/show.blade.php`, `features/examinations/create.js`,
  `features/examinations/show.js` và 2 route tương ứng.
- `routes/web.php` còn đúng 3 route nghiệp vụ: `/patients`, `/appointments`, `/examinations`.

Lưu ý khi review:

- Chuyển trạng thái lịch hẹn giờ chỉ nằm ở danh sách (theo yêu cầu). Ở chế độ "Lịch tuần", click
  vào ô lịch vẫn mở detail modal nhưng **không** có nút chuyển trạng thái — cần chuyển về chế độ
  "Danh sách" để thao tác.
- Detail modal hiển thị ngay dữ liệu của dòng rồi mới thay bằng bản đầy đủ từ API `show`, nên
  không có màn hình trắng khi mở.

Kiểm chứng:

- `npm run build`: thành công.
- `php artisan view:cache`: compile toàn bộ Blade sạch (xác nhận component modal không lỗi cú pháp).
- `FrontendPageTest`: 8 test pass, gồm 2 test mới — `test_feature_pages_render_modal_shells`
  (3 trang đều render shell modal form + detail) và `test_removed_detail_routes_are_gone`
  (`/patients/1`, `/examinations/create`, `/examinations/1` trả 404).
- Full backend suite: 193/202 pass; 9 fail vẫn là `AppointmentTest` do fixture ngày cố định
  `2026-08-15` đã ở quá khứ — lỗi có sẵn từ trước, không phải regression của lần đổi UI này.

### 2026-08-19 — Base nút "Thao tác": icon button, text hiện khi hover

- `ui/row-action.blade.php` đổi thành icon button: mặc định chỉ hiện icon, label trượt ra khi
  `hover`/`focus-visible` (`max-w-0` -> `max-w-[14rem]` + `opacity`), luôn có `aria-label` nên
  screen reader vẫn đọc đúng. Dưới breakpoint `md` label luôn hiện (`max-md:`) vì thiết bị cảm
  ứng không có trạng thái hover.
- Bổ sung `tone="success"` và 2 prop cho nút sinh trong `x-for`: `label-expr` (x-text +
  `x-bind:aria-label`) và `tone-expr` (map tone -> class qua `x-bind:class`).
- Icon mới trong `ui/icon.blade.php`: `eye`, `edit`, `trash`, `check`, `ban`.
- `/appointments`: nút "Chuyển sang: Đã xác nhận"/"Hoàn thành"/"Đã hủy" bỏ style pill riêng, dùng
  chung base `<x-ui.row-action>` như Xem/Sửa — icon `check` (tiến tới, tone `success`) hoặc `ban`
  (hủy, tone `danger`), text vẫn giữ nguyên chữ "Chuyển sang: …" khi hover.
- `/patients` và `/examinations` dùng đúng base đó: Xem (`eye`), Sửa (`edit`), Xóa (`trash`).
- Sửa kèm một lỗi âm thầm có sẵn: `<x-ui.icon class="h-4 w-4">` không bao giờ có tác dụng vì
  class merge của Blade nối thêm vào default `h-5 w-5`, và trong CSS Tailwind `.h-5` đứng sau
  `.h-4`. Nay kích thước là prop `size` (`<x-ui.icon size="h-4 w-4">`); đã đổi toàn bộ chỗ đang
  truyền qua `class` (topbar, toast, dashboard, login, appointments).
- `npm run build`, `php artisan view:cache` và `FrontendPageTest` (8 pass) đều sạch sau thay đổi.

### 2026-08-19 — Nút "Thao tác": đổi text trượt ngang thành tooltip

- Hiệu ứng cũ (label trượt ra bằng `max-w-0` -> `max-w-[14rem]`) làm các nút trong cột "Thao tác"
  đẩy nhau khi rê chuột. Nay label render thành tooltip: nền `slate-900`, chữ trắng `text-xs`,
  bo góc, đổ bóng, `pointer-events-none`, hiện bằng `opacity` khi `group-hover`/`group-focus-visible`
  — không còn dịch chuyển layout.
- Tooltip neo theo **cạnh phải** của nút (`md:right-0 md:bottom-full md:mb-1.5`) chứ không căn
  giữa: bảng nằm trong wrapper `overflow-x-auto`, tooltip căn giữa ở nút ngoài cùng bên phải sẽ
  làm tăng `scrollWidth` và sinh thanh cuộn ngang ngay cả khi tooltip đang `opacity-0`.
- Dưới breakpoint `md` tooltip trở lại dạng text inline luôn hiện (card mobile không có hover).
- `aria-label` trên button giữ nguyên, `<span role="tooltip" aria-hidden="true">` chỉ để hiển thị.
- `npm run build`, `php artisan view:cache`, `FrontendPageTest` (8 pass) đều sạch.

### 2026-08-20 — FE-06 (Toa thuốc)

- Đóng API gap đầu tiên trong checklist: `PrescriptionController` chỉ có `store`/`addItem`/
  `updateItem`/`removeItem`, chưa có `index`/`show`. Thêm `ListPrescriptionsRequest` (filter
  `examination_id`, `doctor_id`, `patient_id` — `patient_id` không phải cột trên bảng
  `prescriptions` nên lọc qua `whereHas('examination', ...)`), `PrescriptionService::paginate()`/
  `load()`, 2 route GET, và bổ sung field `examination` (lồng `ExaminationResource`, có sẵn
  `patient`) vào `PrescriptionResource`. Quyền `PRESCRIPTIONS.FINDALL`/`FINDONE` đã có sẵn trong
  `RbacSeeder` cho DOCTOR/PHARMACIST và `config/rbac.php` đã map `index -> FINDALL`,
  `show -> FINDONE` cho `PrescriptionController` từ trước — không phải sửa RBAC.
- Hoàn thiện FE-06: trang `/prescriptions` có filter bác sĩ/bệnh nhân, modal tạo toa (`size="xl"`)
  từ phiếu khám (deep link `?examination_id=` từ chi tiết phiếu khám, hoặc picker 2 bước tìm bệnh
  nhân rồi chọn phiếu khám vì API `examinations` không có filter `q`), modal chi tiết (`size="xl"`)
  quản lý danh sách thuốc trong toa. Action theo `PRESCRIPTIONS.*`/`MEDICINES.FINDALL`.
- **Không có action "Sửa" ở cấp toa thuốc**: `PRESCRIPTIONS.UPDATE` đã được seed trong RBAC nhưng
  `PrescriptionController` chưa có method `update()`. Cột "Thao tác" của danh sách chỉ có "Xem";
  sửa/xóa thật sự diễn ra ở cấp **item** bên trong modal chi tiết, đúng theo API hiện có.
- Bảng thuốc "editable" có 2 cơ chế khác nhau tùy ngữ cảnh, vì backend không có endpoint cập nhật
  hàng loạt `items`: trong **modal tạo** (`formOpen`), thuốc thêm vào là **nháp cục bộ** (chưa gọi
  API, chỉ gửi kèm mảng `items` khi submit `POST /prescriptions`); trong **modal chi tiết**
  (`detailOpen`), mỗi thao tác thêm/sửa/xóa gọi thẳng API item tương ứng
  (`addItem`/`updateItem`/`removeItem`) và load lại chi tiết. Form thêm thuốc dùng chung 1
  component Blade (`x-prescriptions.item-draft-form`, không nhận props, đọc thẳng state Alpine của
  trang cha) vì UI giống hệt nhau ở cả 2 nơi — hàm `addItemFromDraft()` rẽ nhánh theo
  `this.detailOpen`.
- Confirm popup **chỉ** áp dụng khi xóa item đã lưu (modal chi tiết, có hoàn tồn kho qua API —
  hành động thật). Xóa dòng thuốc nháp trong modal tạo (chưa có gì xảy ra) không cần confirm.
- Lỗi field không đồng nhất từ backend cho `addItem`: `deductStockAndCreateItem` dùng chung giữa
  `store` (bulk) và `addItem` (đơn lẻ) nên lỗi "thuốc không active"/"không đủ tồn" trả field
  `items` dù `addItem` không nhận mảng. `describeItemError()` trong `features/prescriptions/index.js`
  thử lần lượt `medicine_id` -> `quantity` -> `items` -> message chung để không mất thông báo lỗi.
- Thêm nút "Tạo toa thuốc" vào footer modal chi tiết phiếu khám (`/examinations`), trỏ tới
  `/prescriptions?examination_id=<id>` — đóng luồng chéo giống cách FE-05 từng thêm "Tạo phiếu
  khám" vào chi tiết lịch hẹn. Không ẩn nút khi phiếu khám đã có toa (API `examinations` hiện
  không trả `has_prescription`); nếu chọn phiếu khám đã có toa, backend trả `422` field
  `examination_id` và hiển thị đúng dưới ô chọn phiếu khám — giữ đúng nguyên tắc "backend là nguồn
  sự thật cuối cùng" của tài liệu, cố tình không sửa `ExaminationResource` cho việc này.
- Thêm `core/permissions.js` (`PERMISSIONS.PRESCRIPTIONS.*` đầy đủ trừ `UPDATE` — chưa dùng đến;
  `PERMISSIONS.MEDICINES.FINDALL/FINDONE` cho combobox tìm thuốc, FE-07 sẽ bổ sung
  `CREATE/UPDATE/DELETE/ADJUSTSTOCK`) và `core/formatters.js` (`formatCurrency`, định dạng VND
  dùng chung cho các feature liên quan tiền sau này).
- Self-test: thêm `test_prescription_index_page_is_available` vào `FrontendPageTest.php`
  (8 pass), và 4 test vào `PrescriptionTest.php` cho `index`/`show`
  (`test_index_filters_by_patient_and_doctor`, `test_show_returns_prescription_with_context`,
  `test_roles_without_permission_cannot_list_or_view_prescriptions`,
  `test_unauthenticated_requests_to_list_and_show_are_rejected`) — `PrescriptionTest` 33 pass.
- `npm run build`, `php artisan view:cache` sạch. Full backend suite: 198/207 pass; 9 fail vẫn là
  `AppointmentTest` do fixture ngày cố định `2026-08-15` nay đã ở quá khứ so với ngày chạy test
  (2026-08-20) — lỗi có sẵn từ trước (đã ghi nhận từ FE-03), không phải regression của FE-06.

### 2026-08-20 — FE-07 (Kho thuốc)

- Backend đã đầy đủ CRUD + `adjustStock` từ trước (không có API gap như FE-06), nên FE-07 chỉ là
  frontend thuần: trang `/medicines` có search + filter `stock_status` (`in_stock`/`out_of_stock`,
  đúng theo `ListMedicinesRequest`) + phân trang, bảng có badge tồn kho và badge trạng thái, modal
  thêm/sửa, modal điều chỉnh tồn kho riêng, confirm popup khi xóa (soft delete). Action theo
  `MEDICINES.*`.
- **Quyết định quan trọng: tách "sửa thuốc" khỏi "điều chỉnh tồn kho"**. API cho phép sửa `stock`
  trực tiếp qua `PATCH /medicines/{id}` (như mọi field khác) lẫn qua endpoint riêng
  `PATCH /medicines/{id}/stock` (nhận `quantity` dạng delta ± và `note`). Nếu form "Sửa thuốc" cho
  sửa `stock` trực tiếp thì sẽ tồn tại 2 đường thay đổi tồn kho xung đột nhau và không ai bắt buộc
  phải nhập lý do. Đã chốt: field `stock` **chỉ xuất hiện trong form khi tạo mới** (tồn kho ban
  đầu, bắt buộc theo `StoreMedicineRequest`); sau khi tạo, tồn kho là read-only ở form sửa và chỉ
  đổi được qua modal "Điều chỉnh tồn kho" (chọn Nhập thêm/Xuất bớt, nhập số lượng dương, xem trước
  tồn kho sau điều chỉnh, có ô lý do). `medicine-form.js` tách rõ `createPayload()` (có `stock`)
  và `updatePayload()` (không có `stock`) để không thể vô tình gửi nhầm.
- **Lưu ý về field `note`**: `AdjustMedicineStockRequest` validate `note` nhưng
  `MedicineService::adjustStock()` không lưu nó vào đâu cả — bảng `medicines` không có cột log,
  cũng không có bảng lịch sử điều chỉnh tồn kho. UI vẫn thu thập và gửi `note` (đúng yêu cầu
  checklist "có lý do rõ ràng" và đúng field API chấp nhận), nhưng cần biết trước: lý do nhập vào
  hiện **không được lưu lại** ở đâu để tra cứu sau này. Nếu cần audit trail thật, phải bổ sung bảng
  `medicine_stock_adjustments` (hoặc tương tự) ở backend — ngoài phạm vi FE-07.
- Bổ sung `active`/`inactive` vào `STATUS_LABELS`/`STATUS_CLASSES` trong `core/formatters.js` —
  trước đó 2 khóa này được nhắc trong tài liệu (mục 3.2) nhưng chưa feature nào dùng đến. Thêm mới
  `stockLabel()`/`stockClasses()` (ngưỡng `LOW_STOCK_THRESHOLD = 10`, chỉ ảnh hưởng badge hiển thị,
  không phải rule nghiệp vụ backend) vì tồn kho là dữ liệu số, không khớp được với cặp
  `statusLabel`/`statusClasses` vốn thiết kế cho tập nhãn cố định.
- Thêm icon `swap` (mũi tên lên/xuống) cho nút "Điều chỉnh tồn kho" trong cột "Thao tác" — phân
  biệt với "Sửa" (`edit`).
- `core/permissions.js`: bổ sung `CREATE/UPDATE/DELETE/ADJUSTSTOCK` vào `PERMISSIONS.MEDICINES`
  (trước đó FE-06 mới thêm `FINDALL`/`FINDONE` cho combobox tìm thuốc trong toa).
- Self-test: thêm `test_medicine_index_page_is_available` và `/medicines` (3 modal id: form, detail,
  stock) vào `test_feature_pages_render_modal_shells` trong `FrontendPageTest.php` — 10 pass.
  `MedicineTest.php` (có sẵn, không viết mới) đã đủ RBAC theo role được phép/bị từ chối cho
  medicines, kể cả `DOCTOR` chỉ đọc và `RECEPTIONIST`/`CASHIER` không có quyền truy cập.
- `npm run build`, `php artisan view:cache` sạch. Full backend suite: 199/208 pass; 9 fail vẫn là
  `AppointmentTest` do fixture ngày cố định, có sẵn từ trước, không phải regression của FE-07.

### 2026-08-20 — Sửa lỗi hệ thống: sai lệch 7 giờ ở `scheduled_at`/`examined_at`

- **Phát hiện qua QA thủ công FE-06/FE-07**: tạo lịch hẹn `11:00` VN nhưng danh sách và DB đều
  hiện `04:00`. Không phải lỗi frontend (`fromLocalDateTimeInput` trong `appointment-form.js` tính
  đúng UTC gửi lên) — lỗi nằm ở `config('app.timezone')`.
- **Nguyên nhân gốc**: `.env` chưa từng set `APP_TIMEZONE`, nên rơi về default
  `Asia/Ho_Chi_Minh` trong `config/app.php` (không phải `UTC` như nhật ký FE-04 từng ghi nhầm).
  Cột `scheduled_at`/`examined_at` là `timestamp` (không kèm timezone) nên Postgres lưu nguyên văn
  chuỗi số Laravel format ra, không tự quy đổi. Khi **ghi**: Carbon nhận chuỗi UTC có hậu tố `Z` từ
  frontend, format ra DB đúng số UTC (bước này không phụ thuộc `app.timezone`). Khi **đọc lại**:
  Carbon dùng `app.timezone` hiện tại để diễn giải số vừa đọc — với `Asia/Ho_Chi_Minh` thì con số
  UTC đó bị hiểu nhầm thành giờ VN, lệch thêm 7 tiếng lần thứ hai. Đã tái hiện bằng Carbon thuần
  (không cần DB) và xác nhận chính xác cơ chế.
- **Fix**: thêm `APP_TIMEZONE=UTC` vào `.env`, `php artisan config:clear`. Vì bug chỉ nằm ở bước
  *đọc*, dữ liệu cũ đã lưu (số UTC đúng, chỉ bị đọc sai) tự động hiển thị đúng trở lại sau khi sửa
  — không cần backfill DB.
- **Đánh đổi cần biết**: `created_at`/`updated_at` (sinh bằng `now()`, ghi/đọc luôn cùng
  `app.timezone` nên tự nhất quán dưới cấu hình cũ) sẽ hiển thị lệch +7 giờ cho các bản ghi **tạo
  trước** thời điểm sửa này; bản ghi tạo sau thì đúng ở mọi trường. Chấp nhận được vì đang là dữ
  liệu dev/test.
- `docker-compose.yml` có `TZ: Asia/Ho_Chi_Minh` ở container `app` — đây là timezone hệ điều hành,
  không phải cấu hình Laravel (Laravel tự gọi `date_default_timezone_set(config('app.timezone'))`
  lúc boot, ghi đè `TZ` của OS), nên không xung đột với fix này.
- Nhân tiện sửa luôn `tests/Feature/AppointmentTest.php`: 24 chỗ hardcode ngày `2026-08-15`/
  `2026-08-16` (nguồn gốc của 9 test fail lặp lại xuyên suốt nhật ký từ FE-03 đến FE-07 mỗi khi
  "hôm nay" vượt qua mốc đó) — đẩy sang `2030-06-15`/`2030-06-16`. Đây là fix tạm thời kiểu cũ (đẩy
  xa mốc cố định), chưa phải fix triệt để (đổi sang ngày tính tương đối theo `now()`); nếu muốn
  vĩnh viễn không tái diễn thì cần viết lại các fixture này dùng ngày động.
- Full backend suite sau cả 2 fix: **208/208 pass** — lần đầu tiên toàn bộ suite xanh hoàn toàn kể
  từ khi bắt đầu nhật ký này.
- Cần restart container (`docker compose restart app`) để Laravel đọc lại `.env` mới; không cần
  rebuild image vì `.env` nằm trong volume mount `.:/var/www`.

### 2026-08-20 — FE-08 (Hóa đơn và thanh toán)

- Đóng 2 API gap trước khi làm UI: (1) `PaymentController` chỉ có `store`/`capture`, chưa có
  `index` — thêm `ListPaymentsRequest` (filter `invoice_id`, `provider_order_id`),
  `PaymentService::paginate()`, route `GET /payments`; quyền `PAYMENTS.FINDALL` đã có sẵn cho
  CASHIER trong RBAC nên không cần sửa gì thêm. (2) `PayPalService::createOrder()` từng set cả
  `return_url` lẫn `cancel_url` cùng trỏ về `config('app.url')` (trang chủ) — không phân biệt được
  buyer đã duyệt hay đã hủy thanh toán. Đổi thành `/payments/return` và `/payments/cancel` (2 route
  Blade riêng), PayPal tự thêm `?token=&PayerID=` vào URL khi redirect về.
- Hoàn thiện FE-08: trang `/invoices` filter theo status, modal tạo/sửa (form modal chuẩn dùng
  chung `formMode`, khác FE-06 vì `UpdateInvoiceRequest` đúng là một endpoint sửa toàn bộ record dù
  chỉ có field `discount` sửa được — không cần lách quy ước như prescriptions), modal chi tiết
  (`size="xl"`) hiển thị breakdown phí khám/tiền thuốc/giảm giá/tổng cộng, danh sách item toa thuốc
  (nếu có), lịch sử thanh toán (dùng API `index` mới thêm), và form tạo thanh toán ngay trong modal
  khi hóa đơn còn `unpaid` và còn số dư. Action theo `INVOICES.*`/`PAYMENTS.*`.
- **Luồng thanh toán PayPal**: bấm "Thanh toán qua PayPal" trong modal chi tiết → gọi
  `POST /invoices/{id}/payments` → redirect toàn trang (`window.location.href`) sang
  `approval_url` PayPal trả về — đúng theo ghi chú README mục 5.2 (buyer flow chạy trên trang PayPal
  hosted, repo này không có UI nhập thẻ). Sau khi buyer duyệt/hủy, PayPal redirect về
  `/payments/return` hoặc `/payments/cancel` kèm `?token=<paypal_order_id>`.
- **Vấn đề cần giải và cách giải**: trang `/payments/return` cần biết payment cục bộ nào tương ứng
  để gọi `POST /payments/{id}/capture`, nhưng lúc tạo PayPal Order (trước khi có `payment.id` cục
  bộ) chưa thể nhúng id đó vào `return_url` — thứ tự trong `PaymentService::create()` là tạo Order
  trước, tạo bản ghi `Payment` sau. Cân nhắc dùng `sessionStorage` để nhớ `payment.id` trước khi
  redirect nhưng không chắc chắn (PayPal có thể mở tab mới tùy trình duyệt). Chọn giải pháp chắc
  chắn hơn: dùng chính `token` (= `provider_order_id`) PayPal trả về, gọi
  `GET /api/payments?provider_order_id=<token>` (API vừa thêm ở trên) để tìm lại payment cục bộ —
  đây là lý do chính khiến gap `index` cho payments đáng làm ngay, không chỉ để hiện lịch sử giao
  dịch trong modal chi tiết.
- **Trang `/payments/cancel`**: payment tạo trước đó vẫn ở trạng thái `pending` mãi mãi (backend
  không có endpoint hủy payment) — không phải bug, vì `PaymentService::create()` chỉ cộng dồn các
  payment `completed` khi tính số dư còn lại, nên 1 payment `pending` mồ côi không chặn việc thử
  thanh toán lại. Trang cancel chỉ hiển thị thông báo, không cố gắng "dọn" payment đó.
- **Deep link 2 chiều** trên `/invoices`: `?examination_id=` mở modal tạo (giống prescriptions),
  `?invoice_id=` mở thẳng modal chi tiết — thêm mới so với các feature trước vì `/payments/return`
  cần một cách quay lại đúng hóa đơn sau khi capture xong (lấy `invoice_id` từ chính response của
  payment vừa capture).
- Thêm nút "Tạo hóa đơn" vào footer modal chi tiết Phiếu khám (`/examinations`), song song với "Tạo
  toa thuốc" đã có từ FE-06 — trỏ tới `/invoices?examination_id=<id>`. Không bắt buộc phải có toa
  thuốc mới tạo được hóa đơn (`InvoiceService::createFromExamination` dùng `?->items` an toàn khi
  không có prescription, chỉ tính phí khám).
- "Không hiển thị hoặc log dữ liệu thanh toán nhạy cảm": `Payment` model không có số thẻ (PayPal xử
  lý trên trang hosted của họ), UI chỉ hiện số tiền/phương thức/trạng thái/mã đơn PayPal
  (`provider_order_id`, không phải secret, chỉ là id tham chiếu); không có `console.log` response
  PayPal ở đâu trong code.
- `core/permissions.js`: thêm `PERMISSIONS.INVOICES.*` và `PERMISSIONS.PAYMENTS.*`. Không cần sửa
  `core/formatters.js` — `statusLabel`/`statusClasses` đã có sẵn đủ nhãn cho cả trạng thái hóa đơn
  (`unpaid`/`paid`/`cancelled`) lẫn trạng thái thanh toán (`pending`/`completed`/`failed`).
- Sửa 1 lỗi khi tự viết 2 trang return/cancel: `<x-ui.button :href="null" x-bind:href="...">` khiến
  Blade luôn render `<button>` (vì `$href` được PHP đánh giá trước khi Alpine chạy), `x-bind:href`
  trên thẻ `<button>` không có tác dụng điều hướng. Sửa bằng cách truyền sẵn `href="/invoices"` làm
  giá trị mặc định để Blade render `<a>`, rồi `x-bind:href` cập nhật lại khi Alpine tính xong
  `invoiceUrl`.
- Self-test: thêm 4 test vào `PaymentTest.php` cho `index` (filter theo `invoice_id` và
  `provider_order_id`, permission-denied, unauthenticated) — 23 pass. Thêm
  `test_invoice_index_page_is_available`, `test_payment_return_and_cancel_pages_are_available` và
  `/invoices` vào `test_feature_pages_render_modal_shells` trong `FrontendPageTest.php` — 12 pass.
  `InvoiceTest.php`/`PaymentTest.php` có sẵn đã đủ RBAC theo role được phép/bị từ chối.
- `npm run build`, `php artisan view:cache` sạch. Full backend suite: **213/213 pass**.

### 2026-08-20 — Fix UX: lỗi 422 dạng business-rule bị nuốt mất ở `/invoices`

- **Phát hiện qua QA thủ công**: user báo "hóa đơn đã hoàn thành vẫn sửa được". Không phải lỗi bảo
  vệ dữ liệu (`InvoiceService::assertModifiable()` vẫn chặn đúng ở backend, không sửa được thật) —
  mà là **thông báo lỗi bị nuốt mất** khiến hành vi bị hiểu nhầm thành "cho phép sửa".
- Nguyên nhân: `ValidationException::withMessages(['invoice' => [...]])` (dùng ở
  `InvoiceService::assertModifiable()`, `PaymentService::create()`) khiến `$exception->getMessage()`
  — tức field `message` tổng ở JSON envelope — luôn là chuỗi chung chung mặc định của Laravel
  ("The given data was invalid."), còn lý do thật ("Invoice can only be modified while unpaid.")
  nằm trong `errors.invoice[0]`. `invoice` không phải tên field thật trên form (chỉ có
  `examination_id`/`discount`/`amount`), nên `features/invoices/index.js` chưa từng hiển thị field
  này ở đâu — người dùng chỉ thấy thông báo chung chung, không rõ vì sao.
- Sửa cả 3 chỗ trong `features/invoices/index.js` bắt lỗi `ApiError` (`submitForm()` — sửa hóa đơn,
  `confirmCancel()` — hủy hóa đơn, `submitPayment()` — tạo thanh toán): trước khi fallback về
  `error.message` chung, thử `firstFieldError(error.errors, 'invoice')` (và `'examination'`/
  `'status'`/`'amount'` tùy chỗ) để lấy đúng lý do nghiệp vụ cụ thể — cùng cách `describeItemError()`
  của FE-06 (toa thuốc) đã xử lý cho field `items` không khớp tên input thật.
  Đây là lỗi có thể tái diễn ở **bất kỳ feature nào khác dùng field lỗi không trùng tên input** —
  cần soát lại nếu về sau thêm business-rule error mới ở service layer.
- Không thêm chặn phía client dựa vào `invoice.status` cache sẵn trước khi gọi API (dù có thể ngăn
  request thừa) — giữ đúng nguyên tắc mục 2.2 của tài liệu này: "Backend là nguồn sự thật cuối cùng
  cho validation". `openEditModal()`/`askCancel()` đã chặn đúng theo state đang hiển thị; trường hợp
  state đó bị cũ (ví dụ hóa đơn vừa được thanh toán ở tab khác) nay hiển thị đúng lý do backend từ
  chối thay vì để lộ ra như một hành vi mơ hồ.
- `npm run build`, full backend suite **213/213 pass** — không đổi hành vi backend nên không có test
  mới, chỉ là fix hiển thị lỗi phía frontend.

### 2026-08-20 — Fix dashboard: thẻ "Tạo hóa đơn" không hiện + toast placeholder lỗi thời

- **Phát hiện qua QA thủ công**: thẻ "Tạo hóa đơn" ở "Thao tác nhanh" trên `/dashboard` không hiện
  ra dù đã đăng nhập bằng tài khoản có quyền `INVOICES.CREATE`. Nguyên nhân: blade dùng
  `x-show="canCreateInvoice"` nhưng `features/dashboard/index.js` **chưa từng định nghĩa getter
  này** — Alpine đọc `undefined` (falsy) nên thẻ luôn ẩn, không liên quan gì tới quyền thật. Thêm
  `get canCreateInvoice()` (đối chiếu `PERMISSIONS.INVOICES.CREATE`, cùng cách các getter
  `canCreatePatient`/`canCreateAppointment` đã có).
- Nhân tiện phát hiện thêm: cả 2 thẻ "Tạo lịch hẹn" và "Tạo hóa đơn" đều còn sót
  `x-on:click="$store.ui.notify('... sẽ được triển khai trong FE-04/FE-08.')"` từ thời các feature
  này chưa làm xong — nay đã hoàn thiện từ lâu nhưng toast "chưa có" vẫn hiện song song lúc bấm
  (link vẫn điều hướng đúng vì trình duyệt tự theo `href`, chỉ là hiện thêm 1 toast sai gây rối
  mắt). Đã xóa cả 2 handler thừa. Đối chiếu lại: `topbar.blade.php` (thông báo) và
  `sidebar.blade.php` (tooltip mục `ready:false`) vẫn đúng, không sửa, vì đó là tính năng thật sự
  chưa làm (FE-09/FE-10 và trung tâm thông báo).
- `npm run build`, `php artisan view:cache`, full backend suite **213/213 pass**.

### 2026-08-20 — FE-09 (Chuyên khoa và bác sĩ)

- Backend đầy đủ CRUD cho cả 2 (`SpecialtyController`/`DoctorController` là `apiResource` trọn vẹn,
  không thiếu route nào như FE-06) — nhưng phát hiện 1 lỗ hổng UX giống hệt lớp lỗi vừa vá ở
  invoices: `doctors.specialty_id`, `appointments.doctor_id`, `examinations.doctor_id`,
  `prescriptions.doctor_id` đều `restrictOnDelete()`. Xóa 1 chuyên khoa đang có bác sĩ, hoặc xóa 1
  bác sĩ đang có lịch hẹn/phiếu khám/toa thuốc, sẽ khiến Postgres chặn ở tầng DB và ném
  `QueryException` thô ra ngoài — chưa được `SpecialtyService`/`DoctorService` bắt lại, nên
  frontend sẽ nhận nguyên lỗi 500 (có thể lộ cả câu SQL vì `APP_DEBUG=true`). Vá bằng cách bắt
  `QueryException`, nhận diện đúng cả 2 driver (`23503` của Postgres — driver thật của production;
  `23000` của SQLite — driver test suite dùng, xem `phpunit.xml`), chuyển thành `ValidationException`
  422 với message rõ ràng (`SPECIALTY_HAS_DOCTORS`/`DOCTOR_HAS_DEPENDENT_RECORDS`). Thêm 2 test xác
  nhận (`test_delete_rejects_specialty_with_assigned_doctors`,
  `test_delete_rejects_doctor_with_dependent_records`).
- Hoàn thiện FE-09: `/specialties` là CRUD đơn giản nhất trong app (chỉ tên + mô tả, mirror gần như
  y hệt patients) — table + form modal (thêm/sửa dùng chung `formMode`) + detail modal (dù checklist
  không bắt buộc, thêm vào cho nhất quán với quy ước Xem/Sửa/Xóa toàn app, giống cách FE-07 đã làm)
  + confirm popup xóa. `/doctors` filter theo `specialty_id` (dropdown, load từ `GET /specialties`),
  form chọn user qua combobox tìm kiếm + chọn specialty qua `<select>` (danh sách chuyên khoa nhỏ,
  không cần search), modal chi tiết, confirm popup xóa. Action theo `SPECIALTIES.*`/`DOCTORS.*`.
- **Vấn đề chọn user cho form bác sĩ**: `StoreDoctorRequest`/`UpdateDoctorRequest` yêu cầu
  `user_id` phải trỏ tới user có role `DOCTOR` (`USER_MUST_HAVE_DOCTOR_ROLE`), nhưng
  `GET /api/users` chỉ filter được theo `role_id` (số), không theo tên role, và `GET /api/roles`
  vẫn là gap đã ghi nhận từ trước (chưa làm, dành cho FE-10) — không thể tra `role_id` của DOCTOR
  từ frontend một cách "sạch" (không hardcode magic number). Giải bằng cách tận dụng
  `UserService::paginate()` vốn đã `with('role')` sẵn: gọi `GET /users?q=...` bình thường rồi lọc
  client-side `user.role?.name === 'DOCTOR'` trước khi hiện trong combobox
  (`searchDoctorEligibleUsers()` trong `doctor-api.js`) — người dùng chỉ thấy đúng các tài khoản
  hợp lệ, không cần đợi `GET /api/roles` mới làm được. Combobox này chỉ ADMIN nhìn thấy (chỉ ADMIN
  có `DOCTORS.CREATE`/`UPDATE`), nên chắc chắn có sẵn `USERS.FINDALL` để gọi API này.
- Không thêm mục "Thanh toán" riêng vào sidebar (dù sơ đồ điều hướng ở mục 4 của tài liệu này có
  liệt kê) — thanh toán quản lý ngay trong modal chi tiết hóa đơn từ FE-08, không có màn hình CRUD
  độc lập, giữ đúng tinh thần "trang danh sách là trang chính của chức năng".
- Self-test: thêm `test_specialty_index_page_is_available`, `test_doctor_index_page_is_available`
  và `/specialties`, `/doctors` vào `test_feature_pages_render_modal_shells` trong
  `FrontendPageTest.php` — 14 pass. `SpecialtyTest.php`/`DoctorTest.php` có sẵn đã đủ RBAC theo
  role được phép/bị từ chối (chỉ ADMIN ghi được, RECEPTIONIST/DOCTOR chỉ đọc, PHARMACIST/CASHIER
  không có quyền truy cập).
- `npm run build`, `php artisan view:cache`, `vendor/bin/pint --test` sạch. Full backend suite:
  **217/217 pass**.

### 2026-08-20 — FE-10 (Người dùng) — đóng gap `GET /api/roles`

- Đóng đúng gap checklist yêu cầu xác nhận trước: `config/rbac.php` đã map sẵn
  `RoleController => ROLES` và permission `ROLES.FINDALL` đã được seed (cấp cho ADMIN qua "all
  permissions"), nhưng chưa hề có `RoleController`/route nào — y hệt kiểu gap đã gặp ở FE-06
  (prescriptions). Thêm `RoleService::all()` (trả 5 role cố định, không cần pagination vì danh mục
  nhỏ, dùng `ApiResponse::collection()` thay vì `paginated()`), `RoleResource`
  (`id`/`name`/`display_name`), `RoleController::index()`, route `GET /roles`. Thêm
  `tests/Feature/RoleTest.php` (3 test: ADMIN xem được, role khác bị chặn đúng permission,
  chưa đăng nhập bị chặn).
- Hoàn thiện FE-10: `/users` filter theo `q` (tên/email), `role_id` (dropdown từ `GET /roles`),
  `is_active` (dropdown). Modal thêm/sửa dùng chung `formMode`: form sửa có thêm checkbox "Đổi mật
  khẩu" để ẩn/hiện field mật khẩu (mặc định không đổi, chỉ gửi `password` lên khi người dùng chủ
  động tick). **`is_active` không bao giờ được gửi qua form thêm/sửa** — backend chủ động
  `prohibited` field này ở `StoreUserRequest`/`UpdateUserRequest`
  (`USE_STATUS_ENDPOINT`), bắt buộc đổi trạng thái qua `PATCH /users/{id}/status` riêng — khớp
  đúng thiết kế "Confirm khóa/mở tài khoản" của checklist. Action theo `USERS.*`.
- **"Không cho thao tác làm mất admin hoạt động cuối cùng"**: backend
  (`UserService::assertNotLastActiveAdmin`) đã tự chặn và trả lỗi field `role_id` (khi đổi role)
  hoặc `is_active` (khi khóa) — cả hai đều là tên field thật khớp với input/action tương ứng nên
  không dính lỗi "field lạ bị nuốt message" như đã gặp ở invoices/specialties; chỉ cần
  `fieldError('role_id')` trong form và bắt `firstFieldError(error.errors, 'is_active')` khi khóa
  tài khoản là hiển thị đúng nguyên văn lý do backend.
- Sửa 2 lỗi tự phát hiện khi viết `pages/users/index.blade.php`: dùng nhầm `icon-expr` trên
  `<x-ui.row-action>` (component chỉ có `icon` tĩnh, không có prop động cho icon — không giống
  `label-expr`/`tone-expr`) và `confirm-label-expr`/`variant-expr` trên `<x-ui.confirm-modal>`
  (component chỉ có `title-expr` động, còn `confirmLabel`/`variant` là prop tĩnh). Sửa bằng cách
  tách 2 nút "Khóa"/"Mở khóa" riêng (mỗi nút icon tĩnh, `x-show` theo `user.is_active`, giống cách
  các nút chuyển trạng thái lịch hẹn ở FE-04), và dùng `confirm-label`/`variant` tĩnh cho popup xác
  nhận (tiêu đề `title-expr` động đã đủ rõ ngữ cảnh khóa hay mở khóa).
- Nút khóa/mở khóa tài khoản của chính mình vẫn hiện bình thường (không tự ẩn) — chỉ thêm dòng cảnh
  báo nhỏ trong popup xác nhận ("Đây là tài khoản bạn đang đăng nhập…") vì backend đã tự cho phép
  trường hợp còn ≥ 2 admin hoạt động (`test_admin_can_deactivate_self_when_another_active_admin_remains`),
  không cần chặn phía client.
- Self-test: thêm `test_user_index_page_is_available` và `/users` vào
  `test_feature_pages_render_modal_shells` trong `FrontendPageTest.php` — 15 pass. `UserTest.php`
  có sẵn (`test_non_admin_roles_cannot_manage_users`,
  `test_last_active_admin_cannot_be_assigned_another_role`,
  `test_last_active_admin_cannot_be_deactivated_through_status`, …) đã đủ RBAC và business rule,
  không cần viết thêm ngoài `RoleTest.php`.
- `npm run build`, `php artisan view:cache`, `vendor/bin/pint --test` sạch. Full backend suite:
  **221/221 pass**.
- Checklist mục 6 (API gap) giờ chỉ còn `GET /api/stats` — mọi gap khác đã đóng qua FE-06/FE-09/FE-10.

### 2026-08-20 — T3.17 (Seeder dữ liệu demo đầy đủ luồng khám)

- Task backend thuần (không thuộc checklist FE-XX ở trên), ghi lại đây cho liền mạch nhật ký.
  `database/seeders/DemoSeeder.php`: seed 5 chuyên khoa, 6 bác sĩ (kèm tài khoản đăng nhập), 1 tài
  khoản demo cho mỗi vai trò còn lại (RECEPTIONIST/PHARMACIST/CASHIER — để click thử được toàn bộ
  luồng theo từng role mà không phải tự tạo tài khoản), 20 bệnh nhân (tên tiếng Việt sinh ngẫu
  nhiên qua hàm `vietnameseName()` riêng vì `APP_FAKER_LOCALE=en_US` mặc định ra tên kiểu Mỹ, không
  hợp demo phòng khám Việt Nam), 18 thuốc thông dụng, và với mỗi bác sĩ: 3 lịch hẹn sắp tới
  (scheduled/confirmed/cancelled) + 2 lịch đã khám xong kèm đủ chuỗi phiếu khám → toa thuốc (lượt
  khám đầu) → hóa đơn → thanh toán (lượt đầu thanh toán đủ, lượt hai để `unpaid` demo luồng tạo
  thanh toán).
- **Quyết định quan trọng**: gọi thẳng `PrescriptionService::createFromExamination()` và
  `InvoiceService::createFromExamination()` thật thay vì tự tính lại `subtotal`/`total`/trừ tồn
  kho bằng tay trong seeder — đảm bảo dữ liệu demo luôn khớp 100% với business rule thật (nếu sau
  này đổi công thức tính hóa đơn, seeder tự động đúng theo, không cần sửa 2 chỗ). Riêng
  `PaymentService::capture()` **không** gọi vì nó gọi API PayPal thật qua HTTP — seeder phải chạy
  offline được, nên với các hóa đơn demo là "đã thanh toán", tạo thẳng `Payment` completed qua
  factory rồi set `Invoice::STATUS_PAID` tay, chỉ giả lập đúng trạng thái cuối cùng chứ không tái
  hiện luồng capture thật.
- `DemoSeeder::run()` tự gọi `RoleSeeder`/`RbacSeeder`/`AdminSeeder` trước (cả 3 đều upsert nên gọi
  lại an toàn) — chạy được `db:seed --class=DemoSeeder` một lệnh duy nhất trên DB trống là đủ, không
  cần nhớ chạy 3 seeder khác trước.
- Chuyên khoa/bác sĩ/3 tài khoản nghiệp vụ dùng `updateOrCreate` theo khóa tự nhiên (tên chuyên
  khoa, email) nên **chạy lại an toàn**; bệnh nhân/lịch hẹn/phiếu khám/toa/hóa đơn/thanh toán thì
  **không** — chạy 2 lần sẽ tạo thêm dữ liệu random mới chồng lên (không lỗi, nhưng không dedupe).
  Ghi rõ trong docblock của seeder và README. Seeder nhắm tới dùng trên DB trống
  (`migrate:fresh --seed` hoặc `db:seed --class=DemoSeeder` ngay sau khi migrate), không phải để
  chạy lặp lại trên DB đã có dữ liệu thật.
- Self-test: `tests/Feature/DemoSeederTest.php` — chạy seeder thật trong test (DB SQLite), assert
  đúng số lượng từng bảng theo từng trạng thái, assert **số tiền đã thanh toán của mỗi hóa đơn
  `paid` khớp chính xác `total`** (chứng minh money math của `InvoiceService` chạy đúng qua seeder
  chứ không phải số bịa), và assert tài khoản bác sĩ vừa seed đăng nhập gọi API thật được. Thêm test
  thứ 2 xác nhận chạy seeder 2 lần không nhân đôi chuyên khoa/bác sĩ/tài khoản nghiệp vụ. Cả 2 pass
  ngay lần viết đầu tiên.
- Thêm bảng tài khoản demo + hướng dẫn lệnh vào `README.md` mục 3.
- `vendor/bin/pint --test` sạch. Full backend suite: **223/223 pass**.
