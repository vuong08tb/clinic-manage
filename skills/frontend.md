# Skill: Frontend (Blade + Vite + Alpine — tiêu thụ Clinic API)

Playbook làm frontend tối giản **cùng repo Laravel**, tiêu thụ REST API bằng **Bearer token** (Sanctum). Đây là phần mở rộng ngoài đề gốc — API vẫn là sản phẩm chính. Quyết định: Blade khung trang + Vite + Alpine.js gọi `/api/*`, **không tách source**.

---

## 1. Vì sao Blade chung repo (không tách SPA riêng)

- API auth bằng **Sanctum API token**; frontend chỉ cần lưu token và gắn header `Authorization: Bearer`.
- Cùng repo → **không CORS**, không container thứ 3, tái dùng đúng API đang chấm.
- Vẫn dùng được Vite/Alpine (hoặc Vue/React sau này) trong `resources/js` nếu muốn nâng cấp.

Sơ đồ:
```
Blade view (vỏ trang)  ──Vite──►  resources/js (Alpine + fetch)
        │                                  │
        └── render khung, data-* attrs     └── fetch /api/* kèm Bearer token (localStorage)
                                                   │
                                                   ▼
                                            Clinic REST API (Sanctum)
```

---

## 2. Luồng auth phía client

```js
// resources/js/api.js
const BASE = '/api';

export async function login(email, password) {
  const res = await fetch(`${BASE}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const json = await res.json();
  if (!json.success) throw json;               // { success:false, message, errors }
  localStorage.setItem('token', json.data.token);
  localStorage.setItem('permissions', JSON.stringify(json.data.user.permissions ?? []));
  return json.data.user;
}

export async function api(path, options = {}) {
  const token = localStorage.getItem('token');
  const res = await fetch(`${BASE}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  });
  if (res.status === 401) { localStorage.clear(); location.href = '/login'; return; }
  return res.json();
}

export function logout() {
  return api('/logout', { method: 'POST' }).finally(() => { localStorage.clear(); location.href = '/login'; });
}
```

---

## 3. Xử lý envelope & lỗi

Mọi response theo envelope `success/message/data/errors`:

```js
const res = await api('/patients', {
  method: 'POST',
  body: JSON.stringify(form),
});
if (!res.success) {
  // 422 → res.errors = { field: [msg] }
  showFieldErrors(res.errors);
  showToast(res.message);
} else {
  // 201 → res.data
}
```

Map HTTP status → UX:
| Status | Xử lý UI |
|---|---|
| 401 | về `/login`, xóa token |
| 403 | thông báo "Không đủ quyền" |
| 404 | trang/thực thể không tồn tại |
| 422 | hiển thị lỗi theo field từ `errors` |

---

## 4. Ẩn/hiện theo permission

Backend là nơi enforce thật; frontend chỉ ẩn/hiện cho gọn UX. Dùng `permissions` lấy từ `/api/me`:

```html
<button x-data x-show="$store.auth.can('INVOICES.CREATE')">Tạo hóa đơn</button>
```
```js
Alpine.store('auth', {
  permissions: JSON.parse(localStorage.getItem('permissions') || '[]'),
  can(p) { return this.permissions.includes(p); },
});
```

---

## 5. Cấu trúc trang gợi ý (Blade + web.php)

```
routes/web.php:
  GET /login              → view('auth.login')
  GET /                   → view('dashboard')   (đọc /api/stats)
  GET /patients           → view('patients.index')
  GET /appointments       → view('appointments.index')
  GET /invoices           → view('invoices.index')

resources/views/
  layouts/app.blade.php    (khung + @vite)
  auth/login.blade.php
  dashboard.blade.php
  patients/index.blade.php
```

- Blade chỉ render "vỏ" + mount điểm Alpine; dữ liệu nạp qua `fetch` từ API.
- `@vite(['resources/js/app.js'])` để bundle Alpine + api.js.

Trang demo tối thiểu để đạt T4.8: **login → dashboard (stats) → danh sách bệnh nhân/lịch/hóa đơn** (read).

---

## 6. Vite/Alpine setup

```js
// resources/js/app.js
import Alpine from 'alpinejs';
import './api.js';
window.Alpine = Alpine;
Alpine.start();
```

`npm install alpinejs`; `npm run dev` (hoặc `npm run build`) — đã có Vite trong skeleton.

---

## 7. Checklist frontend trước PR

- [ ] Login lưu token, gắn Bearer cho mọi request.
- [ ] 401 tự đăng xuất về `/login`.
- [ ] Hiển thị lỗi 422 theo field từ `errors`.
- [ ] Ẩn/hiện nút theo permission (UX), backend vẫn enforce.
- [ ] Demo đọc được 1 luồng (stats + danh sách).
- [ ] Không hard-code token/secret trong JS.
