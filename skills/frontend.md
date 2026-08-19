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
|   |   |-- ui/                  # button, badge, modal, drawer, table...
|   |   `-- form/                # field, error
|   `-- pages/<feature>/         # page Blade theo nghiệp vụ
|-- js/
|   |-- core/                    # API/error/auth storage/formatter
|   |-- stores/                  # chỉ auth và UI shell
|   `-- features/<feature>/      # Alpine controller theo feature
`-- css/app.css
```

Tên view dùng `pages.<feature>.<page>`, ví dụ `pages.auth.login` và
`pages.dashboard.index`.

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
- Drawer cho CRUD vừa; modal cho confirm/form ngắn; trang riêng cho khám và kê toa.
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
        rows: [],
        loading: true,
        errors: {},
        filters: { q: '', page: 1, per_page: 15 },

        async init() {},
        async load() {},
        async submit() {},
        resetForm() {},
    };
}
```

Quy tắc:

- Không dùng một global store cho tất cả feature.
- Request mới phải reset lỗi liên quan; đóng drawer/modal phải reset form state.
- Chống double-submit bằng `submitting`.
- Search dùng debounce; pagination/filter do backend xử lý.
- Formatter ngày/tiền/status đặt trong `core`, không lặp giữa feature.

## 7. Dashboard trước khi có `/api/stats`

Backend hiện chưa có route `/api/stats`. Bản dashboard đầu tiên:

- chọn KPI theo permission;
- gọi list endpoint với `per_page=1` và dùng `meta.total`;
- lấy lịch hôm nay từ `/api/appointments?date=YYYY-MM-DD`;
- không gọi endpoint user không có permission;
- cho phép từng widget lỗi độc lập thay vì làm hỏng cả dashboard.

Khi `/api/stats` được triển khai, thay data source trong feature dashboard; Blade component không
cần đổi.

## 8. Quy tắc chọn modal/drawer/page

- Popover: menu hàng, filter nhỏ, user menu.
- Confirm modal: xóa, hủy, đổi trạng thái nhạy cảm.
- Form modal: tối đa khoảng 5 field.
- Drawer: bệnh nhân, lịch/hóa đơn quick view, điều chỉnh tồn kho.
- Full page: khám bệnh, kê toa, chi tiết bệnh nhân và thanh toán.

## 9. Checklist trước khi hoàn thành task

- [ ] Route web và Blade page đúng convention.
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
