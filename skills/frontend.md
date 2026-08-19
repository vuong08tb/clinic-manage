# Skill: Frontend Blade + Vite + Alpine cho Clinic API

Playbook triển khai frontend cùng repository Laravel, tiêu thụ REST API `/api/*` bằng Alpine.js.
Kế hoạch task và checklist theo dõi nằm tại
[`docs/trien-khai-frontend.md`](../docs/trien-khai-frontend.md).

## 1. Kiến trúc bắt buộc

- Blade render layout, page shell và reusable UI component.
- Alpine controller quản lý trạng thái của từng feature/page.
- Mọi request đi qua `resources/js/core/api-client.js`.
- Không gọi `fetch()` và không viết business rule trực tiếp trong Blade.
- Auth/UI shell có thể dùng Alpine store; dữ liệu CRUD phải nằm trong feature controller.
- Backend quyết định validation, permission và chuyển trạng thái; frontend chỉ tối ưu UX.

```text
Blade page -> Alpine feature -> API client -> /api/* -> Laravel service/resource
```

## 2. Cấu trúc chuẩn

```text
resources/
|-- views/
|   |-- layouts/                 # guest/app shell
|   |-- components/
|   |   |-- layout/              # sidebar, topbar, page-header
|   |   |-- ui/                  # button, badge, modal, confirm-modal, row-action...
|   |   `-- form/                # field, error
|   `-- pages/<feature>/         # page Blade theo nghiệp vụ (chỉ có trang danh sách)
|-- js/
|   |-- core/                    # API/error/auth storage/formatter
|   |-- stores/                  # chỉ auth và UI shell
|   `-- features/<feature>/      # Alpine controller theo feature
`-- css/app.css
```

Tên view dùng `pages.<feature>.<page>`, ví dụ `pages.auth.login` và
`pages.dashboard.index`.

Mỗi chức năng nghiệp vụ chỉ có **một** page: trang danh sách (`pages.<feature>.index`) và
**một** route web (`/<feature>`). Không tạo route/page riêng cho `create`, `edit` hay `show`.

## 3. API client và response envelope

API trả một trong hai dạng:

```js
{ success: true, message: '...', data: {}, meta: {} }
{ success: false, message: '...', errors: {} }
```

API client phải:

- gửi `Accept: application/json`;
- gửi `Content-Type: application/json` khi có body;
- gắn `Authorization: Bearer <token>` khi có token;
- parse JSON an toàn, kể cả response không có body;
- ném `ApiError` chứa `status`, `message`, `errors` và payload;
- phát sự kiện unauthorized khi gặp `401` để auth store xóa phiên và về `/login`.

Xử lý UX chuẩn:

| HTTP | Xử lý |
|---|---|
| 401 | Xóa token/local auth và chuyển `/login` |
| 403 | Toast "Bạn không có quyền thực hiện thao tác này" |
| 404 | Empty/not-found state phù hợp context |
| 422 | Hiển thị lỗi theo field và summary message |
| 500/network | Error state có nút thử lại |

## 4. Auth và RBAC

Luồng hiện tại dùng Sanctum personal access token:

1. `POST /api/login` nhận email/password.
2. Lưu token với key có namespace, không dùng key chung như `token`.
3. `GET /api/me` khôi phục user, role và permissions.
4. Mọi trang authenticated gọi auth bootstrap trước khi hiển thị shell.
5. `POST /api/logout`, sau đó luôn xóa local auth state.

```js
Alpine.store('auth').can('INVOICES.CREATE');
Alpine.store('auth').canAny(['PATIENTS.FINDALL', 'APPOINTMENTS.FINDALL']);
```

Frontend chỉ ẩn/disable hành động cho UX; middleware backend vẫn enforce thật.

> Production hardening: token đang được lưu phía client để tương thích API hiện tại. Trước khi
> production cần ưu tiên Sanctum stateful cookie/HttpOnly hoặc chốt CSP, expiration và token
> revocation policy. Không xem `localStorage` là giải pháp bảo mật cuối cùng.

## 5. UI đã chốt

- App shell: Clinical — sidebar sáng 240px, topbar 64px, content rõ và thoáng.
- Table: Operations — filter rõ, mật độ vừa/cao, sticky header, pagination server-side.
- Appointment: bổ sung chế độ calendar week theo Modern ở task lịch hẹn.
- Primary color blue, accent teal, neutral slate.
- KPI grid 4/2/1 cột theo desktop/tablet/mobile.
- Trang danh sách là trang chính của mọi chức năng; thêm/sửa/xem đều mở **modal**, confirm mở
  **popup** (confirm modal). Không dùng drawer, không dùng trang riêng cho CRUD.
- Button/icon/modal phải có focus state, disabled/loading state và aria label phù hợp.

Sidebar chia theo workflow:

```text
Tổng quan -> Dashboard
Tiếp đón -> Bệnh nhân, Lịch hẹn
Khám bệnh -> Phiếu khám, Toa thuốc
Dược -> Kho thuốc
Tài chính -> Hóa đơn, Thanh toán
Danh mục -> Chuyên khoa, Bác sĩ
Hệ thống -> Người dùng
```

## 6. Convention cho feature controller

Một page chỉ có một controller chính:

```js
export function patientIndex() {
    return {
        // list
        rows: [],
        meta: emptyPaginationMeta(),
        loading: true,
        listError: '',
        filters: { q: '', page: 1, per_page: 15 },

        // form modal (create + edit dùng chung)
        formOpen: false,
        formMode: 'create',
        editingId: null,
        form: {},
        formErrors: {},
        formMessage: '',
        submitting: false,

        // detail modal
        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: '',

        async init() {},
        async loadList() {},
        openCreateModal() {},
        openEditModal(row) {},
        closeFormModal() {},
        async openDetailModal(row) {},
        closeDetailModal() {},
        editFromDetail() {},
        async submitForm() {},
        fieldError(field) {},
        handleEscape() {},
        resetForm() {},
    };
}
```

Quy tắc:

- Không dùng một global store cho tất cả feature.
- Tên state/hàm modal phải giống nhau giữa các feature theo đúng khung ở trên; người đọc code
  chức năng mới phải đoán được API của controller mà không cần mở file.
- Request mới phải reset lỗi liên quan; đóng modal phải reset form state.
- Chống double-submit bằng `submitting`.
- Search dùng debounce; pagination/filter do backend xử lý.
- Formatter ngày/tiền/status đặt trong `core`, không lặp giữa feature.
- Escape đóng modal theo thứ tự confirm popup -> form modal -> detail modal
  (`handleEscape()` gắn ở root page).

## 7. Dashboard trước khi có `/api/stats`

Backend hiện chưa có route `/api/stats`. Bản dashboard đầu tiên:

- chọn KPI theo permission;
- gọi list endpoint với `per_page=1` và dùng `meta.total`;
- lấy lịch hôm nay từ `/api/appointments?date=YYYY-MM-DD`;
- không gọi endpoint user không có permission;
- cho phép từng widget lỗi độc lập thay vì làm hỏng cả dashboard.

Khi `/api/stats` được triển khai, thay data source trong feature dashboard; Blade component không
cần đổi.

## 8. Quy tắc modal thống nhất

Trang danh sách là trang chính của chức năng. Mọi thao tác thêm/sửa/xem và mọi xác nhận đều xảy
ra ngay trên trang đó:

| Thao tác | Thành phần | Component |
|---|---|---|
| Thêm mới, Sửa | Form modal (dùng chung, phân biệt bằng `formMode`) | `<x-ui.modal>` |
| Xem chi tiết | Detail modal (read-only, footer có nút "Sửa") | `<x-ui.modal>` |
| Xóa, hủy, đổi trạng thái | Confirm popup nổi trên modal đang mở (`z-[60]`) | `<x-ui.confirm-modal>` |
| Nút trong cột "Thao tác" | Xem / Sửa / Xóa / chuyển trạng thái | `<x-ui.row-action>` |

`<x-ui.row-action>` là icon button: chỉ hiện icon, hover/focus hiện tooltip phía trên nút (dưới
`md` tooltip thành text inline luôn hiện vì không có hover). Tooltip neo theo cạnh phải của nút
để không làm bảng sinh thanh cuộn ngang. Dùng `label`/`icon`/`tone` cho nút tĩnh và
`label-expr`/`tone-expr` cho nút sinh trong `x-for`. Tone: `primary` (Xem), `neutral` (Sửa),
`success` (chuyển trạng thái tiến tới), `danger` (Xóa, hủy). Icon đổi cỡ bằng prop `size`, không
bằng `class`.
| Popover | menu hàng, filter nhỏ, user menu | — |

- Không dùng drawer.
- Không tạo trang riêng cho create/edit/detail, kể cả khám bệnh và kê toa; quy trình dài dùng
  modal `size="xl"` và chia section bên trong modal.
- Deep link từ chức năng khác truyền query string tới trang danh sách để mở sẵn modal, ví dụ
  `/examinations?appointment_id=12`; sau khi mở modal phải `history.replaceState` về URL sạch.
- Mọi hành động thay đổi dữ liệu ngoài "lưu form" (xóa, đổi trạng thái) đặt ở cột "Thao tác"
  của danh sách, không giấu trong detail modal.

## 9. Checklist trước khi hoàn thành task

- [ ] Route web và Blade page đúng convention (một route `/<feature>`, một page danh sách).
- [ ] Thêm/sửa/xem dùng `<x-ui.modal>`, confirm dùng `<x-ui.confirm-modal>`, action dùng
      `<x-ui.row-action>`; không có drawer và không có trang create/edit/detail riêng.
- [ ] JavaScript nằm trong feature controller, không inline trong Blade.
- [ ] Mọi API call đi qua API client.
- [ ] Permission áp dụng cho menu và action.
- [ ] Có loading, empty, error, success feedback.
- [ ] Xử lý đúng `401`, `403`, `422`.
- [ ] Có disabled/loading để chống submit lặp.
- [ ] Responsive và keyboard navigation cơ bản.
- [ ] `npm run build` thành công.
- [ ] Cập nhật checklist trong `docs/trien-khai-frontend.md`.

## 10. Comment code

- Comment bằng tiếng Anh, ngắn gọn, giải thích lý do hoặc constraint kỹ thuật.
- Không comment lại điều code đã thể hiện rõ.
- PHP tuân thủ PSR-12; JavaScript dùng module ES và tên hàm thể hiện nghiệp vụ.

---

## 11. Thời gian: neo về giờ VN, không theo máy người dùng

Backend trả mọi mốc thời gian dưới dạng **ISO UTC có hậu tố `Z`** (`->toISOString()`). Frontend chịu trách nhiệm quy đổi sang giờ phòng khám (`Asia/Ho_Chi_Minh`, cố định UTC+7, không DST).

Toàn bộ logic nằm ở [core/formatters.js](../resources/js/core/formatters.js):

| Hàm | Dùng khi |
|---|---|
| `formatDate(iso, opts)` / `formatTime(iso)` | Hiển thị — đã ghim `timeZone: 'Asia/Ho_Chi_Minh'` |
| `formatDateOnly('YYYY-MM-DD')` | Ngày lịch thuần (`date_of_birth`) — render ở UTC để không lệch ngày |
| `localDateInput(date)` | Sinh `YYYY-MM-DD` theo giờ VN cho query param `?date=` |
| `localDateTimeInput(date)` | Đổ giá trị vào `<input type="datetime-local">` |
| `fromLocalDateTimeInput(value)` | Đọc `<input type="datetime-local">` **về đúng instant** trước khi `.toISOString()` |
| `toClinicClock` / `fromClinicClock` | Tính toán ngày/tuần (lịch tuần ở appointments) |

### Ba lỗi phải tránh

1. **`new Date(x).toISOString().slice(0,10)`** để lấy "ngày" — cắt theo UTC nên lệch một ngày với mọi mốc trước 07:00 giờ VN. Dùng `localDateInput`.
2. **`new Date(inputValue).toISOString()`** cho `datetime-local` — trình duyệt hiểu chuỗi đó theo múi giờ máy. Dùng `fromLocalDateTimeInput`.
3. **`getDay()` / `getHours()` / `setHours()`** khi dựng lịch — đều là giờ máy. Dùng `toClinicClock` rồi thao tác bằng `getUTC*`/`setUTC*`, xong `fromClinicClock`.

> Hệ quả: máy nhân viên đặt sai múi giờ vẫn thấy đúng lịch khám.
