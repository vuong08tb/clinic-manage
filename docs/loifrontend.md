# Checklist lỗi và phần còn thiếu của frontend

Cập nhật lần cuối: **2026-08-28**

Tài liệu này là backlog xử lý frontend theo từng vấn đề độc lập. Chỉ đánh dấu `[x]` sau khi:

1. Đã sửa code.
2. Đã có regression test phù hợp.
3. `npm run test:frontend` và `npm run build` đều pass.
4. Test PHP liên quan pass.

## Quy ước

- **P1**: ưu tiên cao, có thể làm sai thao tác, sai dữ liệu, mất phiên hoặc chặn triển khai.
- **P2**: ảnh hưởng rõ tới UX, accessibility, phân quyền hoặc độ ổn định.
- **P3**: hoàn thiện tiêu chuẩn và chất lượng sản phẩm.
- **Chưa xử lý**: chưa được phép thay đổi hoặc chưa bắt đầu.
- **Đang xử lý**: đã thống nhất phương án và đang sửa.
- **Đã xử lý**: code và test đã hoàn thành.

## Các lỗi đã xử lý

- [x] **FE-001 — Modal điều chỉnh tồn kho không đóng sau khi lưu thành công**
  - Đã sửa tại `resources/js/features/medicines/index.js`.
  - Modal hiện đóng/reset sau khi request thành công và danh sách được tải lại.
  - Có regression test trong `tests/frontend/async-state.test.js`.

- [x] **FE-002 — Sửa thuốc trong toa thành công nhưng hàng vẫn ở chế độ chỉnh sửa**
  - Đã sửa tại `resources/js/features/prescriptions/index.js`.
  - Editor và draft hiện được xóa sau khi lưu thành công.
  - Có regression test trong `tests/frontend/async-state.test.js`.

- [x] **FE-003 — API chi tiết toa thuốc không trả tồn kho hiện tại**
  - Đã bổ sung `medicine.stock` trong `app/Http/Resources/PrescriptionItemResource.php`.
  - Đã bổ sung test tại `tests/Feature/PrescriptionTest.php`.
  - Thay đổi hiện đang ở working tree và chưa được commit.

## Thứ tự xử lý các lỗi còn lại

### 1. FE-004 — Lỗi mạng tạm thời làm mất phiên đăng nhập

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Khi `/api/me` lỗi mạng hoặc server trả 5xx, frontend xóa token và chuyển người dùng về trang đăng nhập dù token vẫn hợp lệ.
- **Nguyên nhân:** `restore()` bắt mọi exception rồi gọi `clear()`.
- **File liên quan:**
  - `resources/js/stores/auth-store.js`
  - `resources/js/core/api-client.js`
- **Hướng xử lý:** Chỉ xóa phiên khi nhận `401`; lỗi mạng/5xx phải giữ token, hiện thông báo và cho phép thử lại.
- **Tiêu chí hoàn thành:**
  - `401` vẫn đăng xuất đúng.
  - Lỗi mạng và `500` không xóa token.
  - Có test cho cả ba trường hợp.

### 2. FE-005 — Lịch hẹn thiếu thời gian gây lỗi JavaScript chung

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Bỏ trống thời gian rồi submit tạo ra `Invalid Date`/`RangeError` và chỉ hiện lỗi chung.
- **Nguyên nhân:** `createPayload()` và `editPayload()` gọi `toISOString()` trước khi kiểm tra `scheduled_at`.
- **File liên quan:**
  - `resources/js/features/appointments/appointment-form.js`
  - `resources/js/features/appointments/index.js`
  - `resources/views/pages/appointments/index.blade.php`
- **Hướng xử lý:** Validate thời gian trước khi dựng payload, gắn lỗi vào field `scheduled_at` và không gọi API khi dữ liệu rỗng/không hợp lệ.
- **Tiêu chí hoàn thành:** Không còn exception; người dùng nhận đúng lỗi tại trường thời gian.

### 3. FE-006 — Response chi tiết cũ có thể ghi đè bản ghi mới

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Mở bản ghi A, đóng và mở B nhanh; response A về trễ có thể làm modal B hiển thị dữ liệu A.
- **Nguyên nhân:** Request chi tiết không có request ID hoặc `AbortController`.
- **File liên quan:**
  - `resources/js/features/patients/index.js`
  - `resources/js/features/appointments/index.js`
  - `resources/js/features/examinations/index.js`
  - `resources/js/features/prescriptions/index.js`
  - `resources/js/features/invoices/index.js`
  - `resources/js/features/medicines/index.js`
  - `resources/js/features/specialties/index.js`
  - `resources/js/features/doctors/index.js`
  - `resources/js/features/users/index.js`
- **Hướng xử lý:** Chuẩn hóa request ID/abort cho toàn bộ modal chi tiết và vô hiệu hóa response khi modal đã đóng.
- **Tiêu chí hoàn thành:** Response cũ không thể thay đổi modal đang hiển thị bản ghi khác.

### 4. FE-007 — Lịch tuần có thể hiển thị dữ liệu của tuần cũ

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Bấm “Tuần trước/Tuần sau” liên tục có thể khiến tiêu đề là tuần mới nhưng dữ liệu thuộc tuần cũ.
- **Nguyên nhân:** `loadWeek()` không chống response về sai thứ tự.
- **File liên quan:** `resources/js/features/appointments/index.js`
- **Hướng xử lý:** Thêm request ID/abort và khóa hoặc quản lý trạng thái các nút trong lúc tải.
- **Tiêu chí hoàn thành:** Chỉ response của tuần được chọn gần nhất được phép cập nhật `weekDays`.

### 5. FE-008 — Escape trong modal Visa đóng sai modal

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Khi modal Visa nằm trên modal hóa đơn, nhấn Escape có thể đóng modal hóa đơn bên dưới thay vì Visa.
- **Nguyên nhân:** `handleEscape()` không ưu tiên kiểm tra `visaModalOpen`.
- **File liên quan:** `resources/js/features/invoices/index.js`
- **Hướng xử lý:** Đóng modal theo thứ tự layer: confirm → Visa → form → detail.
- **Tiêu chí hoàn thành:** Escape chỉ đóng modal trên cùng và không làm mất context hóa đơn.

### 6. FE-009 — PayPal Web SDK lỗi một lần thì không thể thử lại

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2
- **Hiện tượng:** Nếu tải SDK lỗi mạng một lần, mở lại modal vẫn dùng promise đã reject cho tới khi reload trang.
- **Nguyên nhân:** `_visaSdkPromise` được cache nhưng không reset khi `script.onerror`.
- **File liên quan:** `resources/js/features/invoices/index.js`
- **Hướng xử lý:** Reset promise và dọn script lỗi để lần mở tiếp theo có thể tải lại.
- **Tiêu chí hoàn thành:** Sau lỗi tải SDK, người dùng có thể retry mà không cần reload trang.

### 7. FE-010 — Đăng xuất ở tab này không đồng bộ sang tab khác

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Tab A đăng xuất nhưng tab B vẫn giữ `user/permissions`; request sau đó có thể nhận `401` mà không chuyển về login.
- **Nguyên nhân:** Auth store không nghe sự kiện `storage`/`BroadcastChannel`; API client chỉ phát unauthorized khi request có token.
- **File liên quan:**
  - `resources/js/stores/auth-store.js`
  - `resources/js/core/auth-storage.js`
  - `resources/js/core/api-client.js`
- **Hướng xử lý:** Đồng bộ thay đổi phiên giữa các tab và xử lý mọi `401` từ endpoint yêu cầu đăng nhập.
- **Tiêu chí hoàn thành:** Logout ở một tab làm các tab còn lại trở về login an toàn.

### 8. FE-011 — Fresh clone không tự tạo Vite assets

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1
- **Hiện tượng:** Clone mới rồi chạy đúng `docker compose up -d --build` có thể lỗi `Vite manifest not found`.
- **Nguyên nhân:** `public/build` bị Git ignore, Dockerfile không chạy npm build và bind mount có thể che artifact trong image.
- **File liên quan:**
  - `Dockerfile`
  - `docker-compose.yml`
  - `.gitignore`
  - `README.md`
- **Hướng xử lý:** Dùng Node multi-stage build cho production và tách rõ Compose dev/production hoặc thêm Node/Vite service cho dev.
- **Tiêu chí hoàn thành:** Fresh clone khởi động theo README và `/login` trả `200` mà không cần build thủ công trên host.

### 9. FE-012 — Bearer token lưu lâu dài trong localStorage

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1 — security
- **Hiện tượng:** JavaScript cùng origin có thể đọc token; token hiện không có thời hạn Sanctum mặc định.
- **Nguyên nhân:** Frontend dùng personal access token trong `localStorage`, `config/sanctum.php` để `expiration = null`.
- **File liên quan:**
  - `resources/js/core/auth-storage.js`
  - `resources/js/stores/auth-store.js`
  - `app/Services/AuthService.php`
  - `config/sanctum.php`
- **Hướng xử lý:** Chuyển web frontend sang Sanctum stateful cookie `HttpOnly`, `Secure`, `SameSite`; xác định timeout và chính sách thu hồi token.
- **Tiêu chí hoàn thành:** Frontend không còn đọc được credential bằng JavaScript và phiên có chính sách hết hạn rõ ràng.

### 10. FE-013 — Trường tồn kho bị trả kèm qua API hóa đơn

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — RBAC
- **Hiện tượng:** `PrescriptionItemResource` được dùng chung bởi toa thuốc và hóa đơn, nên `medicine.stock` cũng có thể xuất hiện trong response hóa đơn cho thu ngân.
- **Nguyên nhân:** Một resource dùng cho nhiều context có quyền khác nhau.
- **File liên quan:**
  - `app/Http/Resources/PrescriptionItemResource.php`
  - `app/Http/Resources/InvoiceResource.php`
- **Hướng xử lý:** Quyết định stock có được phép công khai cho cashier hay không; nếu không, tách resource/context hoặc trả có điều kiện mà không gây N+1 query.
- **Tiêu chí hoàn thành:** API chỉ trả tồn kho cho vai trò/context đã được thống nhất và có test RBAC.

### 11. FE-014 — Picker tài khoản bác sĩ có thể bỏ sót kết quả hợp lệ

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2
- **Hiện tượng:** Tài khoản bác sĩ phù hợp có thể không xuất hiện nếu không nằm trong 10 user đầu tiên.
- **Nguyên nhân:** Frontend gọi `/users?per_page=10` rồi mới lọc role `DOCTOR` ở client.
- **File liên quan:** `resources/js/features/doctors/doctor-api.js`
- **Hướng xử lý:** Hỗ trợ filter role ở backend hoặc endpoint options chuyên biệt rồi lọc trước khi phân trang.
- **Tiêu chí hoàn thành:** Mọi tài khoản bác sĩ phù hợp đều có thể tìm được.

### 12. FE-015 — Picker phiếu khám có thể đưa ra lựa chọn không hợp lệ

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2
- **Hiện tượng:** Picker chỉ tải tối đa 20 phiếu và có thể hiển thị phiếu đã có toa/hóa đơn, sau đó backend mới từ chối.
- **Nguyên nhân:** Endpoint tìm kiếm chưa có filter “eligible” và frontend không phân trang tiếp.
- **File liên quan:**
  - `resources/js/features/prescriptions/prescription-api.js`
  - `resources/js/features/invoices/invoice-api.js`
- **Hướng xử lý:** Bổ sung filter nghiệp vụ ở backend và pagination/search phù hợp.
- **Tiêu chí hoàn thành:** Picker chỉ hiện các phiếu có thể dùng cho thao tác hiện tại.

### 13. FE-016 — Modal chưa quản lý focus đúng chuẩn

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** Focus có thể thoát ra nền; mở modal không focus vào nội dung; đóng modal không trả focus về nút mở; nền vẫn được screen reader đọc.
- **Nguyên nhân:** Modal chỉ có `role="dialog"`/`aria-modal`, chưa có focus trap, restore focus, inert và scroll lock.
- **File liên quan:** `resources/views/components/ui/modal.blade.php`
- **Hướng xử lý:** Dùng Alpine Focus hoặc cơ chế focus trap dùng chung trong component modal.
- **Tiêu chí hoàn thành:** Tab không thoát khỏi modal, Escape đóng đúng layer và focus quay về trigger.

### 14. FE-017 — Sidebar mobile đóng nhưng vẫn nằm trong tab order

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** Người dùng bàn phím có thể tab vào các link sidebar đang nằm ngoài màn hình.
- **Nguyên nhân:** Sidebar chỉ ẩn bằng `transform: translateX`, không dùng `inert`/`aria-hidden` động.
- **File liên quan:**
  - `resources/views/components/layout/sidebar.blade.php`
  - `resources/views/components/layout/topbar.blade.php`
- **Hướng xử lý:** Quản lý `inert`, focus, restore focus và scroll lock theo `sidebarOpen`.
- **Tiêu chí hoàn thành:** Sidebar đóng không còn phần tử focusable; mở sidebar focus vào navigation.

### 15. FE-018 — Các ô autocomplete chưa có semantics combobox

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** Không thể dùng Arrow Up/Down/Enter đầy đủ; screen reader không biết trạng thái danh sách gợi ý.
- **Nguyên nhân:** Thiếu `role="combobox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant` và option semantics.
- **File liên quan:** Các picker bệnh nhân, bác sĩ, lịch hẹn, phiếu khám và thuốc trong `resources/views/pages/**`.
- **Hướng xử lý:** Tạo component autocomplete dùng chung có keyboard navigation và ARIA chuẩn.
- **Tiêu chí hoàn thành:** Có thể tìm và chọn hoàn toàn bằng bàn phím, có test browser/axe.

### 16. FE-019 — Đóng modal làm mất form chưa lưu mà không cảnh báo

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — UX
- **Hiện tượng:** Click backdrop hoặc nhấn Escape reset ngay dữ liệu đã nhập, đặc biệt nguy hiểm với form toa thuốc dài.
- **Nguyên nhân:** Hàm close reset form trực tiếp, không theo dõi dirty state.
- **File liên quan:** Shared modal và toàn bộ feature form.
- **Hướng xử lý:** Theo dõi form dirty; xác nhận trước khi đóng hoặc không cho backdrop đóng form nhập liệu.
- **Tiêu chí hoàn thành:** Dữ liệu chưa lưu không thể bị mất do click lệch mà không có cảnh báo.

### 17. FE-020 — Validation chưa được nối đầy đủ với screen reader

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** Nhiều input không có `aria-invalid`/`aria-describedby`; sau submit lỗi không focus hoặc scroll tới lỗi đầu tiên.
- **Nguyên nhân:** Cách render lỗi chưa thống nhất giữa các form.
- **File liên quan:** Các form trong `resources/views/pages/**/index.blade.php`.
- **Hướng xử lý:** Tạo convention/component field error dùng chung và helper focus lỗi đầu tiên.
- **Tiêu chí hoàn thành:** Screen reader đọc đúng lỗi; người dùng được đưa tới field lỗi đầu tiên.

### 18. FE-021 — Kết quả thanh toán async không được screen reader thông báo

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** Screen reader có thể chỉ đọc trạng thái đang tải mà không thông báo thành công/thất bại sau khi capture xong.
- **Nguyên nhân:** Khối kết quả thiếu live region và không chuyển focus tới heading kết quả.
- **File liên quan:** `resources/views/pages/payments/return.blade.php`
- **Hướng xử lý:** Dùng `aria-live`/`role="status"` phù hợp và focus heading khi trạng thái kết thúc.
- **Tiêu chí hoàn thành:** Thành công, thất bại và lỗi đều được thông báo tự động.

### 19. FE-022 — Một số state chỉ thể hiện bằng màu

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P3 — accessibility
- **Hiện tượng:** Tab danh sách/lịch tuần, trang pagination hiện tại và sidebar active chỉ đổi màu.
- **Nguyên nhân:** Thiếu `aria-pressed` và `aria-current`.
- **File liên quan:**
  - `resources/views/pages/appointments/index.blade.php`
  - Các pagination trong `resources/views/pages/**`
  - `resources/views/components/layout/sidebar.blade.php`
- **Hướng xử lý:** Bổ sung state semantics và text trạng thái khi cần.
- **Tiêu chí hoàn thành:** Trạng thái vẫn hiểu được khi không nhìn thấy màu sắc.

### 20. FE-023 — Lịch tuần quá chật ở màn hình tablet nhỏ

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — responsive
- **Hiện tượng:** Từ 640px giao diện đã chia 7 cột, mỗi ngày chỉ còn khoảng 69px.
- **Nguyên nhân:** Dùng `sm:grid-cols-7` quá sớm.
- **File liên quan:** `resources/views/pages/appointments/index.blade.php`
- **Hướng xử lý:** Dùng breakpoint lớn hơn, agenda view cho mobile/tablet hoặc horizontal scroll với min-width.
- **Tiêu chí hoàn thành:** Lịch đọc và thao tác được ở 640–1024px, không làm tên/giờ quá chật.

### 21. FE-024 — Một số chữ có độ tương phản thấp

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** `text-slate-400` trên nền trắng ở placeholder/chữ nhỏ không đạt tỷ lệ 4.5:1.
- **Nguyên nhân:** Màu quá nhạt cho nội dung chữ thường.
- **File liên quan:**
  - `resources/css/app.css`
  - `resources/views/components/layout/sidebar.blade.php`
  - `resources/views/pages/auth/login.blade.php`
- **Hướng xử lý:** Tăng lên màu phù hợp và kiểm tra contrast bằng axe/Lighthouse.
- **Tiêu chí hoàn thành:** Text thường đạt WCAG AA.

### 22. FE-025 — Thiếu skip link tới nội dung chính

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P3 — accessibility
- **Hiện tượng:** Người dùng bàn phím phải tab qua toàn bộ sidebar ở mỗi trang.
- **Nguyên nhân:** Layout không có “Bỏ qua điều hướng” và `<main>` không có target ID.
- **File liên quan:** `resources/views/layouts/app.blade.php`
- **Hướng xử lý:** Thêm skip link chỉ hiện khi focus và `id` cho main content.
- **Tiêu chí hoàn thành:** Phím Tab đầu tiên cho phép đi thẳng tới nội dung chính.

### 23. FE-026 — User menu thiếu keyboard navigation

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — accessibility
- **Hiện tượng:** Menu khai báo `role="menu"` nhưng không hỗ trợ Escape, Arrow keys và focus management.
- **Nguyên nhân:** Chỉ xử lý click và click outside.
- **File liên quan:** `resources/views/components/layout/topbar.blade.php`
- **Hướng xử lý:** Hoàn thiện menu pattern hoặc bỏ menu semantics nếu chỉ là popup đơn giản.
- **Tiêu chí hoàn thành:** Mở/đóng/chọn hoàn toàn bằng bàn phím và focus được quản lý đúng.

### 24. FE-027 — Radio nhập/xuất kho chưa được nhóm đúng semantics

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P3 — accessibility
- **Hiện tượng:** Screen reader không nhận rõ hai lựa chọn thuộc cùng một nhóm; điều hướng phím mũi tên không chuẩn.
- **Nguyên nhân:** Thiếu `fieldset`, `legend` và `name` chung.
- **File liên quan:** `resources/views/pages/medicines/index.blade.php`
- **Hướng xử lý:** Dùng fieldset/legend và cùng `name` cho hai radio.
- **Tiêu chí hoàn thành:** Nhóm có tên accessible và điều hướng radio đúng chuẩn trình duyệt.

### 25. FE-028 — Bảng thiếu caption và scope cho header

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P3 — accessibility
- **Hiện tượng:** Screen reader thiếu ngữ cảnh tên bảng và liên kết header/cell chưa rõ.
- **Nguyên nhân:** `<table>` không có caption; `<th>` thiếu `scope="col"`.
- **File liên quan:** Các bảng trong `resources/views/pages/**`.
- **Hướng xử lý:** Thêm caption `sr-only` và scope nhất quán.
- **Tiêu chí hoàn thành:** Mỗi bảng có tên accessible và header semantics đúng.

### 26. FE-029 — UI còn lộ nội dung dành cho quá trình phát triển

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P3 — product polish
- **Hiện tượng:** Người dùng thấy các câu như “task sau”, “triển khai theo checklist”, “backend/database”.
- **Nguyên nhân:** Placeholder và câu kỹ thuật chưa được thay bằng nội dung sản phẩm.
- **File liên quan:**
  - `resources/views/components/layout/topbar.blade.php`
  - `resources/views/pages/dashboard/index.blade.php`
  - Các confirm modal bệnh nhân/lịch hẹn.
- **Hướng xử lý:** Ẩn tính năng chưa sẵn sàng hoặc dùng nội dung hướng tới người dùng cuối.
- **Tiêu chí hoàn thành:** Không còn thuật ngữ task/backend/database trong UI nghiệp vụ.

### 27. FE-030 — Thiếu test frontend cho các luồng chính

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1 — quality
- **Hiện tượng:** Test hiện mới bao phủ hai regression async; phần lớn store, formatter, form và browser flow chưa có test.
- **Nguyên nhân:** Chưa có Vitest/Playwright/Dusk và CI frontend đầy đủ.
- **File liên quan:**
  - `package.json`
  - `tests/frontend/**`
  - Chưa có workflow CI.
- **Hướng xử lý:** Bổ sung unit test cho core/form/store và browser test cho login, RBAC, CRUD modal, tồn kho, toa thuốc, thanh toán.
- **Tiêu chí hoàn thành:** Các luồng P1 có regression test và chạy tự động trên clean checkout.

### 28. FE-031 — Chưa pin phiên bản Node.js

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — build
- **Hiện tượng:** Máy dùng Node 18 sẽ không chạy được Vite 8 nhưng README/package không cảnh báo rõ.
- **Nguyên nhân:** Thiếu `engines`, `.nvmrc`/`.node-version` và version Node trong Docker/CI.
- **File liên quan:**
  - `package.json`
  - `README.md`
  - Docker/CI config tương lai.
- **Hướng xử lý:** Pin Node 22 LTS hoặc phiên bản đáp ứng yêu cầu Vite 8.
- **Tiêu chí hoàn thành:** Local, Docker và CI dùng cùng major Node; sai version báo lỗi rõ.

### 29. FE-032 — Thiếu HTTP security headers cho frontend production

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P1 — security/deployment
- **Hiện tượng:** Response hiện thiếu CSP, `X-Content-Type-Options`, `Referrer-Policy`, frame protection và `Permissions-Policy`; còn lộ `X-Powered-By`.
- **Nguyên nhân:** Chưa có reverse proxy/middleware hardening.
- **File liên quan:** Cấu hình web server hoặc middleware HTTP tương lai.
- **Hướng xử lý:** Xây CSP tương thích PayPal SDK, thêm security headers và tắt version disclosure.
- **Tiêu chí hoàn thành:** Security headers được kiểm tra tự động; PayPal vẫn hoạt động trong CSP đã giới hạn.

### 30. FE-033 — Route giao diện chỉ được bảo vệ ở phía client

- [ ] **Trạng thái:** Chưa xử lý
- **Mức độ:** P2 — security architecture
- **Hiện tượng:** Người chưa đăng nhập vẫn tải được HTML shell của các trang nghiệp vụ rồi mới bị JavaScript chuyển hướng.
- **Nguyên nhân:** `routes/web.php` dùng `Route::view` không có auth middleware; auth gate nằm trong Alpine store.
- **File liên quan:**
  - `routes/web.php`
  - `resources/views/layouts/app.blade.php`
  - `resources/js/stores/auth-store.js`
- **Hướng xử lý:** Sau khi chuyển sang cookie auth, bảo vệ web routes bằng middleware server-side; API vẫn là ranh giới dữ liệu bắt buộc.
- **Tiêu chí hoàn thành:** Guest bị redirect trước khi render shell; API authorization vẫn giữ nguyên.

## Bộ lệnh kiểm tra chuẩn sau mỗi mục

```bash
npm run test:frontend
npm run build
php artisan test
git diff --check
```

Nếu chạy trong Docker:

```bash
docker compose exec app npm run test:frontend
docker compose exec app npm run build
docker compose exec app php artisan test
```

## Nguyên tắc xử lý backlog

1. Mỗi lần chỉ chọn một mã `FE-xxx`.
2. Trước khi sửa phải thống nhất diff hoặc phương án.
3. Không gộp refactor không liên quan vào cùng một lỗi.
4. Khi hoàn thành, cập nhật checkbox, mô tả thay đổi và test đã chạy ngay trong tài liệu này.
