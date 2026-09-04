# Rate Limiting — Kế hoạch thiết kế và triển khai

Tài liệu này mô tả cách thiết kế rate limiting cho API của Clinic Management, và quan trọng
hơn là **lý do đằng sau từng con số**. Mọi giá trị đề xuất đều kèm cơ sở tính toán và điều
kiện để xem xét lại.

**Trạng thái hiện tại của project:** đã triển khai xong. Bốn limiter (`login`, `api`,
`sensitive`, `payment`) khai báo trong `AppServiceProvider::boot()` và gắn vào route theo đúng
[bảng mục 8.3](#83-bảng-chi-tiết). Còn lại ở [mục 8.5](#85-giai-đoạn-2--tách-riêng-nhóm-ghi)
(tách limiter `write`), trusted proxies, và đổi `CACHE_STORE` sang Redis — mỗi thứ một task riêng.

---

## Mục lục

0. [Kiến thức nền](#0-kiến-thức-nền) — đọc trước nếu chưa từng làm rate limiting, kèm [mức độ cần nắm](#07-nên-nắm-tới-mức-nào)
1. [Rate limiting là gì](#1-rate-limiting-là-gì)
2. [Tại sao API này cần](#2-tại-sao-api-này-cần)
3. [Mô hình mối đe doạ](#3-mô-hình-mối-đe-doạ)
4. [Phân loại API theo rủi ro và chi phí](#4-phân-loại-api-theo-rủi-ro-và-chi-phí)
5. [Quy trình xác định limit](#5-quy-trình-xác-định-limit)
6. [Cách chọn window](#6-cách-chọn-window)
7. [Chọn key](#7-chọn-key)
8. [Bảng đề xuất cho từng API](#8-bảng-đề-xuất-cho-từng-api)
9. [Kế hoạch triển khai Laravel](#9-kế-hoạch-triển-khai-laravel)
10. [Rate limiting không thay thế được gì](#10-rate-limiting-không-thay-thế-được-gì)
11. [Testing](#11-testing)
12. [Monitoring và điều chỉnh](#12-monitoring-và-điều-chỉnh)
13. [Phụ lục: dữ liệu đo được](#13-phụ-lục-dữ-liệu-đo-được)
14. [Nâng cao](#14-nâng-cao) — idempotency key, nhiều tầng, circuit breaker
15. [Lộ trình vừa học vừa làm](#15-lộ-trình-vừa-học-vừa-làm) — 7 bài thực hành

---

## 0. Kiến thức nền

> Mục này dành cho người lần đầu đụng tới rate limiting. Nếu đã quen middleware và các thuật
> toán giới hạn tần suất, nhảy thẳng tới [mục 1](#1-rate-limiting-là-gì).

### 0.1. Một request đi qua đâu trước khi tới controller

Trong Laravel, request không đi thẳng vào controller. Nó chui qua một chuỗi **middleware** —
mỗi middleware là một lớp có quyền cho đi tiếp, sửa request, hoặc **chặn lại ngay tại đó**.

```
Request tới
   │
   ▼  Middleware 1  ──► chặn? ──► trả response luôn, controller không bao giờ chạy
   │                (cho đi tiếp)
   ▼  Middleware 2  ──► chặn? ──► trả response luôn
   │
   ▼  Middleware 3
   │
   ▼  CONTROLLER   ← chỉ tới đây nếu mọi middleware đều cho đi
   │
   ▼  Response đi ngược ra qua từng middleware
```

Trong project này, một request tới `POST /api/patients` đi qua:

```php
Route::middleware(['auth:sanctum', 'permission'])->group(...)
                    │                 │
                    │                 └─► EnsurePermission — có quyền PATIENTS.CREATE không?
                    └───────────────────► Sanctum — token có hợp lệ không?
```

**Thứ tự trong mảng chính là thứ tự chạy.** `auth:sanctum` chạy trước `permission`, nên khi
`EnsurePermission` chạy thì `$request->user()` đã có sẵn. Đảo ngược lại là hỏng: middleware
kiểm tra quyền sẽ không biết người gọi là ai.

Rate limiting là một middleware nữa, tên là `throttle`.

### 0.2. Vì sao throttle phải là middleware

Người mới thường nghĩ: "sao không viết luôn trong controller cho dễ hiểu?"

```php
// Cách SAI
public function login(LoginRequest $request)
{
    $user = User::where('email', $request->email)->first();

    if (! Hash::check($request->password, $user->password)) {   // ← 226 ms đã tiêu ở đây
        // ... rồi mới đếm số lần thử
    }
}
```

Vấn đề: `Hash::check()` với bcrypt cost 12 tốn **226 ms CPU**, và nó đã chạy **trước khi** ta
kịp đếm. Kẻ tấn công gửi 1000 request thì server vẫn phải nghiến 226 giây CPU, dù ta có từ
chối cả 1000 request đi nữa.

Đặt ở middleware thì:

```
Request thứ 6  ──►  throttle  ──►  429, dừng tại đây
                                   ↑
                    Sanctum, controller, bcrypt: KHÔNG CHẠY
```

**Chặn sớm là toàn bộ giá trị của rate limiting.** Càng gần cửa vào càng tốt — đó cũng là lý
do WAF (chặn ở tầng mạng, trước cả PHP) mạnh hơn nữa, xem [mục 10](#10-rate-limiting-không-thay-thế-được-gì).

### 0.3. Bốn thuật toán rate limiting

Bốn cách đếm, khác nhau ở độ chính xác và chi phí bộ nhớ. Biết tên và đánh đổi của từng cái là
đủ cho phần lớn tình huống.

#### a) Fixed window counter — Laravel dùng cái này

Chia thời gian thành các ô cố định. Mỗi ô một bộ đếm. Hết ô thì đếm lại từ 0.

```
│──── phút 1 ────│──── phút 2 ────│
     đếm = 3           đếm = 1
```

- **Lưu trữ:** 2 giá trị cho mỗi key (bộ đếm + thời điểm hết hạn). Rẻ nhất.
- **Ưu:** đơn giản, nhanh, dễ hiểu.
- **Nhược:** cho phép dồn gấp đôi ở ranh giới — xem [0.4](#04-bộ-đếm-fixed-window-chạy-thế-nào).

#### b) Sliding window log

Lưu **dấu thời gian của từng request**. Muốn biết còn được phép không thì đếm số dấu thời gian
nằm trong N giây gần nhất.

```
Danh sách: [10:00:03, 10:00:17, 10:00:41, 10:00:55]
Lúc 10:01:00, đếm các mốc trong 60 giây qua → 4 request
```

- **Lưu trữ:** một dấu thời gian cho **mỗi request**. Đắt nhất.
- **Ưu:** chính xác tuyệt đối, không có lỗi ranh giới.
- **Nhược:** 1 triệu request/phút là 1 triệu bản ghi phải giữ. Không dùng được ở quy mô lớn.

#### c) Sliding window counter

Dung hoà: giữ bộ đếm của ô hiện tại và ô trước, rồi lấy trung bình có trọng số theo vị trí
trong ô.

```
Ô trước = 8, ô này = 3, đang ở 25% ô hiện tại
Ước lượng = 8 × (1 − 0,25) + 3 = 9
```

- **Lưu trữ:** 2 bộ đếm. Rẻ như fixed window.
- **Ưu:** gần như hết lỗi ranh giới.
- **Nhược:** là ước lượng, không chính xác tuyệt đối. Đây là cách các hệ thống lớn hay dùng.

#### d) Token bucket

Một cái xô chứa token, được **đổ đầy đều đặn** theo thời gian. Mỗi request tiêu một token. Hết
token thì bị chặn.

```
Sức chứa 10 token, đổ thêm 1 token/6 giây
Người dùng im lặng 1 phút → xô đầy 10 token
→ có thể bắn ngay 10 request liền, rồi sau đó bị ép về nhịp 1/6 giây
```

- **Ưu:** **cho phép dồn cục một cách có kiểm soát**. Rất hợp với giao diện web, nơi mở một
  trang bắn ra 7-10 request cùng lúc rồi im lặng.
- **Nhược:** phức tạp hơn, cần lưu thêm thời điểm đổ đầy lần cuối.
- Đây là thuật toán của AWS API Gateway và phần lớn thiết bị mạng.

*(Còn **leaky bucket** — xếp request vào hàng đợi rồi xả ra với nhịp đều. Nó làm *mượt* lưu
lượng chứ không *từ chối*, nên hợp với hàng đợi xử lý hơn là API đồng bộ.)*

#### Chọn cái nào cho project này

**Fixed window của Laravel là đủ.** Lý do: nhược điểm duy nhất của nó là cho phép dồn gấp đôi
ở ranh giới, mà 10 lần đoán mật khẩu trong 2 giây rồi phải chờ vẫn chậm hơn **500 lần** so với
không giới hạn gì. Đổi sang thuật toán phức tạp hơn để bịt kẽ hở đó là tối ưu sai chỗ.

Nếu sau này chuyển `CACHE_STORE` sang Redis, Laravel có `throttleWithRedis()` dùng thuật toán
chính xác hơn — đổi một dòng config, không phải sửa code.

### 0.4. Bộ đếm fixed window chạy thế nào

Giới hạn **5 request/phút**. Theo dõi từng giây:

| Thời điểm | Sự kiện | Bộ đếm | Kết quả |
| --- | --- | --- | --- |
| `00:00` | Request 1 | 1 | 200 — cửa sổ mở, hết hạn lúc `01:00` |
| `00:05` | Request 2 | 2 | 200 |
| `00:11` | Request 3 | 3 | 200 |
| `00:20` | Request 4 | 4 | 200 |
| `00:31` | Request 5 | 5 | 200 — **đã chạm ngưỡng** |
| `00:32` | Request 6 | 5 | **429** — `Retry-After: 28` |
| `00:45` | Request 7 | 5 | **429** — `Retry-After: 15` |
| `01:00` | — | **xoá** | Cửa sổ hết hạn |
| `01:01` | Request 8 | 1 | 200 — cửa sổ mới |

Hai điều rút ra:

1. **`Retry-After` giảm dần** — nó là số giây còn lại tới khi hết cửa sổ, không phải một hằng số.
2. **Cửa sổ tính từ request đầu tiên**, không phải từ lúc bị chặn. Bị chặn ở giây 32 thì chỉ
   phải chờ 28 giây, không phải 60.

**Lỗi dồn cục ở ranh giới**, cũng với giới hạn 5/phút:

```
00:56  request 1  ┐
00:57  request 2  │
00:58  request 3  ├─ cửa sổ A: 5 request
00:59  request 4  │
00:59  request 5  ┘
       ─────────── 01:00 cửa sổ reset ───────────
01:01  request 6  ┐
01:02  request 7  │
01:03  request 8  ├─ cửa sổ B: 5 request
01:04  request 9  │
01:05  request 10 ┘

→ 10 request trong 9 giây, dù giới hạn ghi là "5/phút"
```

Đây chính là nhược điểm mà sliding window và token bucket khắc phục. Với hệ thống này thì chấp
nhận được.

### 0.5. Thuật ngữ

| Thuật ngữ | Nghĩa |
| --- | --- |
| **Rate limit** | Giới hạn số request trong một khoảng thời gian |
| **Throttle** | Động từ của việc đó. Trong Laravel là tên middleware: `throttle:login` |
| **Limit** | Con số tối đa, ví dụ `5` |
| **Window** | Khoảng thời gian đếm, ví dụ `1 phút` |
| **Key** | Chuỗi quyết định "đếm chung sổ với ai". Xem [mục 7](#7-chọn-key) |
| **Limiter** | Một cấu hình có tên gồm limit + window + key. Khai bằng `RateLimiter::for('login', ...)` |
| **Burst** | Đợt request dồn cục trong thời gian rất ngắn. Không nhất thiết là tấn công — mở một trang web là một burst |
| **Quota** | Hạn mức theo chu kỳ dài (tháng, gói dịch vụ). Khác rate limit ở mục đích: quota để tính tiền, rate limit để bảo vệ |
| **Backoff** | Chiến lược chờ trước khi thử lại. **Exponential backoff** là chờ 1s, 2s, 4s, 8s... |
| **Jitter** | Thêm ngẫu nhiên vào thời gian chờ, để 100 client không cùng thử lại đúng một lúc |
| **429** | `Too Many Requests` — mã HTTP báo vượt giới hạn |
| **`Retry-After`** | Header cho biết phải chờ bao nhiêu **giây** |
| **False positive** | Chặn nhầm người dùng hợp lệ. Chỉ số quan trọng nhất khi chỉnh limit |
| **Decay** | Cách Laravel gọi việc bộ đếm hết hạn. `decaySeconds` = độ dài window tính bằng giây |

### 0.6. Sáu sai lầm người mới hay mắc

**1. Khoá theo IP cho hệ thống nội bộ.** Cả văn phòng đi chung một IP public. Một người gõ sai
mật khẩu là khoá cả công ty. Xem [mục 7](#7-chọn-key).

**2. Đặt ngưỡng theo cảm tính.** "Để 100 cho chắc" — 100 vừa không bảo vệ được gì, vừa không
giải thích được cho ai. Phải đi từ lưu lượng hợp lệ đo được, xem [mục 5](#5-quy-trình-xác-định-limit).

**3. Chỉ giới hạn login rồi coi như xong.** Login là ưu tiên số một nhưng không phải duy nhất.
Một tài khoản hợp lệ bị chiếm quyền vẫn cào sạch dữ liệu nếu không có giới hạn chung.

**4. Quên chuẩn hoá key.** `Admin@clinic.test` và `admin@clinic.test` là hai sổ riêng nếu
không có `Str::lower()`. Kẻ tấn công đổi hoa-thường là nhân đôi hạn mức.

**5. Không xử lý 429 ở phía client.** Server chặn đúng nhưng người dùng chỉ thấy một lỗi khó
hiểu, không biết chờ bao lâu. Xem [mục 9.7](#97-phía-client).

**6. Nghĩ rate limiting là xong phần bảo mật.** Nó là **một** lớp. Không thay thế
authentication, authorization, validation hay WAF. Xem [mục 10](#10-rate-limiting-không-thay-thế-được-gì).

### 0.7. Nên nắm tới mức nào

Tài liệu này cố ý đi sâu hơn mức cần thiết để hoàn thành task. Điều đó không có nghĩa là mọi
mục đều đáng nhớ như nhau. Bảng dưới phân tầng để khỏi phân bổ sai công sức.

**Mức 1 — phải thuộc, không tra tài liệu.** Đây là thứ bị hỏi khi review code và khi phỏng vấn.

| Cần nắm | Đọc ở | Tự kiểm tra: trả lời trôi được không? |
| --- | --- | --- |
| Rate limiting là gì, vì sao phải là middleware | [0.2](#02-vì-sao-throttle-phải-là-middleware) | *Đặt trong controller thì sao?* → đã vào tới nơi rồi, không còn gì để bảo vệ |
| Ba thành phần: key / limit / window | [1](#1-rate-limiting-là-gì) | *Cái nào quan trọng nhất?* → key |
| Key cho endpoint đã xác thực | [7](#7-chọn-key) | *Vì sao `user id` chứ không phải IP?* → cả phòng khám chung một IP public |
| Vì sao login cần hai lớp | [7](#7-chọn-key) | *Lớp `email+IP` không chặn được gì?* → credential stuffing nhiều tài khoản |
| Fixed window và đỉnh gấp đôi | [0.3](#03-bốn-thuật-toán-rate-limiting), [0.4](#04-bộ-đếm-fixed-window-chạy-thế-nào) | *Giảm đỉnh bằng cách nào?* → rút ngắn window, giảm limit theo tỉ lệ |
| `429`, `Retry-After`, `X-RateLimit-*` | [9.4](#94-response-429--không-phải-viết-thêm-gì), [9.5](#95-header) | *Client nhận 429 thì làm gì?* → đọc `Retry-After` và chờ, không retry ngay |
| Khai limiter và gắn vào route | [9.2](#92-khai-báo-limiter), [9.3](#93-gắn-vào-route) | Gõ lại được ba dòng đó mà không nhìn mẫu |

**Mức 2 — biết là có, tra lại khi cần.** Không thuộc, nhưng nghe tới phải biết nó giải quyết gì.

| Thứ | Đủ khi biết rằng | Đọc ở |
| --- | --- | --- |
| `throttleApi()` trong `bootstrap/app.php` | Laravel 11+ bỏ throttle mặc định, phải tự bật | [9.3](#93-gắn-vào-route) |
| `Limit::none()` | Có cách miễn trừ cho health check, job nội bộ | [9.9](#99-miễn-trừ-những-thứ-không-được-chặn) |
| `->response(...)` | Tuỳ biến được body của 429 | [9.4](#94-response-429--không-phải-viết-thêm-gì) |
| `->after(...)` | Có cách chỉ đếm khi request thất bại | [14](#14-nâng-cao) |
| `RateLimiter::hit()` / `clear()` thủ công | Login thường làm tay để xoá bộ đếm khi đăng nhập đúng | [14](#14-nâng-cao) |
| Rate limit ở nginx / CDN | Tầng ứng dụng không phải tầng duy nhất | [14.2](#142-rate-limiting-ở-nhiều-tầng) |
| Redis so với database cache | Redis hợp hơn cho việc đếm | [9.8](#98-chi-phí-và-hành-vi-đồng-thời-của-chính-rate-limiter) |

**Mức 3 — đọc để có phản xạ, không cần nhớ.** Quên hết ngày mai cũng không sao: cơ chế khoá
trong `DatabaseStore::incrementOrDecrement()`, race condition mà `RateLimiter::increment()` vá,
sliding window bằng Redis sorted set, idempotency key, circuit breaker, cấu hình proxy theo
từng loại load balancer.

Giá trị của mức 3 không nằm ở việc nhớ, mà ở chỗ: khi gặp hành vi lạ, biết câu trả lời nằm
trong `vendor/` và dám mở ra đọc thay vì đoán. Xem [mục 15](#15-lộ-trình-vừa-học-vừa-làm) —
bài 4 và bài 7 được thiết kế đúng để tạo phản xạ đó.

---

## 1. Rate limiting là gì

Giới hạn **số request** mà một bên gọi được trong một **khoảng thời gian**. Vượt ngưỡng thì
server trả `429 Too Many Requests` thay vì xử lý.

### Ba thành phần

| Thành phần | Ý nghĩa | Ví dụ |
| --- | --- | --- |
| **Limit** | Số request tối đa | 5 |
| **Window** | Khoảng thời gian đếm | 1 phút |
| **Key** | Đếm chung sổ với ai | `email + IP` |

Ba thứ này độc lập nhau. Sai key thì limit và window đúng cỡ nào cũng vô nghĩa — đây là lỗi
thiết kế phổ biến nhất, xem [mục 7](#7-chọn-key).

### Laravel cài đặt thế nào

`Illuminate\Cache\RateLimiter` dùng **fixed window counter** lưu trong cache:

```
cache["<hash của key>"]        = 3            ← số lần đã dùng trong cửa sổ
cache["<hash của key>:timer"]  = 1756370400   ← thời điểm cửa sổ hết hạn
```

Mỗi request tăng bộ đếm. Hết hạn thì bộ đếm bị xoá, đếm lại từ 0.

**Điểm yếu cần biết:** cửa sổ cố định cho phép dồn cục ở ranh giới. Giới hạn 5/phút vẫn cho
phép 5 request lúc `00:59` và 5 request nữa lúc `01:01` — tức 10 request trong 2 giây. Thuật
toán sliding window hoặc token bucket không có nhược điểm này, nhưng tốn kém hơn. Laravel chọn
fixed window vì rẻ và đủ tốt cho phần lớn trường hợp.

Với hệ thống này, dồn cục ở ranh giới **không phải vấn đề**: 10 lần đoán mật khẩu trong 2 giây
rồi phải chờ, vẫn chậm hơn hàng nghìn lần so với không giới hạn.

---

## 2. Tại sao API này cần

### Bằng chứng đo được

20 lần đăng nhập sai liên tiếp vào `admin@clinic.test`:

```
Status trả về: 401 × 20
Tổng thời gian: 4,56 giây
→ không lần nào bị chặn
```

Khoảng **4,4 lần thử/giây** từ một tiến trình curl duy nhất, tương đương ~380.000 lần thử mỗi
ngày. Với danh sách 10.000 mật khẩu phổ biến, toàn bộ danh sách chạy hết trong **38 phút**.

### Hai rủi ro riêng biệt

**Rủi ro 1 — đoán được mật khẩu.** Rõ ràng.

**Rủi ro 2 — bào mòn tài nguyên.** `BCRYPT_ROUNDS=12` khiến mỗi lần kiểm tra mật khẩu tốn
**226 ms** (đo được), so với **25 ms** của một request đọc danh sách. Login đắt gấp **9 lần**
một request thường.

Đây là con dao hai lưỡi: bcrypt chậm làm kẻ tấn công khó dò, nhưng cũng biến `/api/login`
thành điểm khuếch đại DoS. Gửi ồ ạt vào đó là cách làm nghẽn server mà **không cần đăng nhập
thành công lần nào**.

Rate limiting xử lý cả hai, vì nó chặn *trước khi* bcrypt chạy.

---

## 3. Mô hình mối đe doạ

Từng kiểu tấn công, và rate limiting chặn được đến đâu.

### 3.1. Brute-force login

Thử nhiều mật khẩu cho **một** tài khoản.

- **Chặn bằng:** limit theo `email + IP`.
- **Hiệu quả:** cao. 5/phút biến 38 phút thành ~33 ngày cho cùng danh sách 10.000 mật khẩu.
- **Lỗ hổng còn lại:** kẻ tấn công đổi IP thì được sổ mới.

### 3.2. Credential stuffing

Lấy cặp email/mật khẩu rò rỉ từ nơi khác, thử hàng loạt **nhiều tài khoản khác nhau**, mỗi tài
khoản chỉ 1-2 lần.

- **Limit theo `email + IP` KHÔNG chặn được.** Mỗi email chỉ bị thử 1 lần nên không sổ nào
  chạm ngưỡng 5.
- **Chặn bằng:** thêm một limit **theo IP** — ví dụ 20/phút bất kể email nào.
- Đây là lý do login cần **hai lớp giới hạn**, không phải một.

### 3.3. Tấn công từ chối dịch vụ (spam request)

Gửi ồ ạt để làm nghẽn server.

- **Chặn bằng:** limit toàn API theo user, cộng limit theo IP cho endpoint public.
- **Hiệu quả:** vừa phải. Request vẫn tới PHP, vẫn tốn một vòng bootstrap Laravel và một query
  cache. Nhưng tránh được phần đắt nhất (bcrypt, aggregate query, gọi PayPal).
- **Giới hạn thật:** chặn ở tầng ứng dụng là **muộn**. Chặn sớm phải ở tầng nginx/WAF.

### 3.4. Scraping (cào dữ liệu)

Duyệt hết `/api/patients?page=1..N` để lấy toàn bộ hồ sơ bệnh nhân.

- **Chặn bằng:** limit theo user cho các endpoint đọc.
- Đây là lý do limit toàn API vẫn cần thiết dù mọi route đã có `auth:sanctum`. Một tài khoản
  nhân viên hợp lệ bị chiếm quyền vẫn có thể cào sạch dữ liệu — **rate limit biến việc cào
  5.000 hồ sơ từ 30 giây thành 42 phút**, đủ lâu để monitoring kịp báo động.

### 3.5. Một user tạo quá nhiều request

Thường là **bug ở client**, không phải tấn công: vòng lặp vô hạn, retry không có backoff,
component bị render lại liên tục.

- **Chặn bằng:** limit theo user id.
- **Giá trị thật:** bảo vệ hệ thống khỏi chính client của mình. Đây là kịch bản xảy ra thường
  xuyên nhất trong thực tế, hơn hẳn tấn công có chủ đích.

### 3.6. Một IP có nhiều user

Toàn bộ phòng khám đi chung một IP public — kịch bản mặc định của hệ thống nội bộ.

- **Đây không phải tấn công mà là ràng buộc thiết kế.**
- Limit theo IP cho API nội bộ sẽ khiến 5 nhân viên chia nhau một hạn mức; người dùng nặng làm
  người khác bị chặn oan.
- **Cách xử lý:** endpoint đã xác thực thì khoá theo `user id`. Chỉ endpoint chưa xác thực
  (login) mới dùng IP.

### 3.7. Request phân tán từ nhiều IP (botnet)

- **Rate limiting ở tầng ứng dụng KHÔNG chặn được.** Mỗi IP chỉ gửi vài request, không sổ nào
  chạm ngưỡng, nhưng tổng lưu lượng vẫn hạ được server.
- **Cần:** WAF, Cloudflare, giới hạn ở tầng hạ tầng, hoặc phát hiện bất thường.
- Ghi rõ ở đây để không ai nhầm rằng cài xong `throttle` là hết lo DDoS.

---

## 4. Phân loại API theo rủi ro và chi phí

Mỗi nhóm cần một mức khác nhau vì ba biến khác nhau: **chi phí tài nguyên**, **rủi ro nghiệp
vụ**, và **tần suất hợp lệ**.

### 4.1. Authentication — ưu tiên cao nhất

`POST /api/login`

- **Chi phí:** 226 ms/request (bcrypt cost 12) — đắt nhất hệ thống.
- **Rủi ro:** cao nhất. Đây là cửa duy nhất vào toàn bộ dữ liệu y tế.
- **Public:** không cần token, ai cũng gọi được.
- **Tần suất hợp lệ:** rất thấp. Người dùng đăng nhập 1-2 lần/ngày. Token Sanctum không hết
  hạn (`expiration => null`), nên còn ít hơn nữa.

Chênh lệch giữa "tần suất hợp lệ rất thấp" và "hậu quả rất nặng" là lý do login được siết chặt
nhất. Ngưỡng chặt gần như không phiền người dùng thật.

### 4.2. Session self-service — ưu tiên thấp

`GET /api/me`, `POST /api/logout`

- **Chi phí:** 24 ms. Đã có token hợp lệ nên không chạy bcrypt.
- **Rủi ro:** thấp. Không đọc được dữ liệu người khác.
- Chỉ cần một mức lỏng để chặn bug vòng lặp ở client.

### 4.3. Read / GET danh sách — lưu lượng cao nhất

`GET /api/patients`, `/api/appointments`, `/api/medicines`, `/api/invoices`,
`/api/prescriptions`, `/api/doctors`, `/api/specialties`, `/api/users`, `/api/roles`,
`/api/payments`, `/api/medicines/low-stock`

- **Chi phí:** 23-35 ms, và **không tăng theo kích thước bảng** vì có phân trang và index.
- **Rủi ro:** vừa — đây là bề mặt bị cào dữ liệu.
- **Tần suất hợp lệ: cao nhất trong hệ thống.** Xem [mục 8.1](#81-cơ-sở-tính-toán-cho-nhóm-đọc).

Nhóm này quyết định con số của limit toàn cục, vì nó là nhóm chạm trần trước tiên.

### 4.4. Search / filter — cùng nhóm với đọc

`GET /api/patients?q=`, `?stock_status=`, `?date=`, `?status=`

- Không tách riêng, vì cùng đường dẫn và cùng chi phí (23 ms cho `?q=Nguyen`).
- **Điểm cần lưu ý:** tìm kiếm gõ-tới-đâu-tìm-tới-đó có thể sinh nhiều request. Trong project
  này đã có `x-on:input.debounce.350ms`, giới hạn ở ~2,8 request/giây, thực tế ~3-4 request
  cho một cái tên. Nếu debounce bị gỡ, nhóm này sẽ chi phối toàn bộ lưu lượng.

### 4.5. Write CRUD — ưu tiên trung bình

`POST`/`PATCH`/`PUT` cho patients, appointments, examinations, prescriptions, medicines,
invoices, specialties, doctors, users

- **Chi phí:** cao hơn đọc — chạy trong `DB::transaction()` với `lockForUpdate()`, một số còn
  cộng thêm việc trừ kho.
- **Rủi ro:** vừa — ghi rác vào dữ liệu y tế.
- **Tần suất hợp lệ: rất thấp.** Con người điền form và bấm lưu; không ai tạo quá vài chục bản
  ghi mỗi phút.

Chênh lệch lớn giữa "tần suất hợp lệ rất thấp" và "chi phí cao" khiến nhóm này siết được khá
chặt mà không ảnh hưởng ai.

### 4.6. Delete — ưu tiên trung bình

`DELETE` cho patients, medicines, doctors, specialties, prescription items

- **Rủi ro nghiệp vụ cao** nhưng **đã được chặn bằng cơ chế khác**: RBAC giới hạn ai xoá được,
  khoá ngoại `RESTRICT` chặn xoá bản ghi có lịch sử, và soft delete giữ lại dữ liệu.
- Rate limiting **không phải** lớp bảo vệ chính ở đây. Đặt chung nhóm ghi là đủ.

### 4.7. Statistics / reports — cần siết dù hiện tại nhanh

`GET /api/stats`

- **Chi phí hiện tại: 25 ms** — nghe thì rẻ.
- **Nhưng đây là con số gây hiểu nhầm.** `StatsService` chạy 4 truy vấn tổng hợp:

  ```php
  Patient::query()->count();                      // COUNT toàn bảng
  Appointment::query()->whereDate(...)->count();  // COUNT theo ngày
  Payment::query()->where('status', ...)->sum();  // SUM theo tháng
  Medicine::query()->lowStock()->count();         // COUNT có điều kiện
  ```

  `COUNT(*)` trên PostgreSQL **không dùng được index** — nó phải quét toàn bảng. Chi phí tăng
  tuyến tính theo số dòng, trong khi một endpoint danh sách có phân trang thì gần như không đổi.

- **Kết luận:** 25 ms là con số của 20 bệnh nhân. Ở 200.000 bệnh nhân, cùng endpoint đó sẽ mất
  hàng trăm ms tới hàng giây. Siết nhóm này **ngay từ bây giờ**, đừng đợi tới lúc nó thành vấn đề.
- **Tần suất hợp lệ: rất thấp.** Dashboard nạp 1 lần khi mở trang.

### 4.8. Payment — siết chặt nhất sau login

`POST /api/invoices/{invoice}/payments`, `POST /api/payments/{payment}/capture`,
`GET /api/payments/paypal/client-token`

- **Chi phí: cao nhất hệ thống**, và không do code của mình. Mỗi request gọi HTTP sang PayPal
  Sandbox — thường 300-1000 ms, phụ thuộc mạng và tình trạng PayPal.
- **Rủi ro nghiệp vụ cao nhất:** liên quan tiền thật.
- **PayPal cũng có rate limit riêng.** Vượt hạn mức của họ có thể ảnh hưởng cả tài khoản
  merchant, không chỉ một request. **Tự giới hạn ở phía mình là cách bảo vệ hạn mức đó.**
- **Tần suất hợp lệ: cực thấp.** Một hoá đơn thanh toán một lần.

### 4.9. Điều chỉnh tồn kho

`PATCH /api/medicines/{medicine}/stock`

- Chạy trong transaction có `lockForUpdate()`, ghi thêm activity log.
- **Rủi ro nghiệp vụ:** sai lệch tồn kho thuốc.
- Đặt chung nhóm ghi.

### Bảng tóm tắt mức ưu tiên

| Ưu tiên | Nhóm | Vì sao |
| --- | --- | --- |
| 1 | Authentication | Public + đắt nhất + hậu quả nặng nhất |
| 2 | Payment | Liên quan tiền + gọi bên thứ ba có hạn mức riêng |
| 3 | Statistics | Chi phí tăng theo kích thước dữ liệu |
| 4 | Write / Delete | Chi phí cao, tần suất hợp lệ thấp |
| 5 | Read / Search | Lưu lượng cao, chi phí thấp — chỉ cần trần chống cào |
| 6 | Session self-service | Rẻ và vô hại |

---

## 5. Quy trình xác định limit

Không chọn số theo cảm tính. Sáu bước, theo thứ tự:

```
Đặc điểm API → Chi phí tài nguyên → Rủi ro nghiệp vụ → Lưu lượng hợp lệ
             → Rủi ro bị lạm dụng → limit / window / key
```

### Bước 1 — Đặc điểm API

Public hay đã xác thực? Đọc hay ghi? Có gọi ra ngoài không? Có transaction không?

*Ví dụ:* `/api/login` — public, ghi (tạo token), chạy bcrypt.

### Bước 2 — Chi phí tài nguyên

Đo thật, đừng đoán. Ba câu hỏi:

1. **Một request tốn bao nhiêu?** Đo bằng `curl -w "%{time_total}"`.
2. **Chi phí có tăng theo kích thước dữ liệu không?** Đây là câu quan trọng hơn. Endpoint có
   phân trang gần như không đổi; endpoint tổng hợp tăng tuyến tính.
3. **Nút thắt nằm ở đâu?** CPU (bcrypt), DB (aggregate), hay mạng (PayPal)? Ba loại này cạn
   kiệt theo cách khác nhau.

### Bước 3 — Rủi ro nghiệp vụ

Chuyện tệ nhất nếu bị lạm dụng: mất tiền, lộ dữ liệu, hỏng dữ liệu, hay chỉ chậm?

Ưu tiên siết theo **hậu quả**, không theo tần suất.

### Bước 4 — Lưu lượng hợp lệ

**Đây là bước quyết định con số**, và là bước hay bị bỏ qua nhất.

Cách làm: dựng kịch bản người dùng bận rộn nhất, đếm số request mà kịch bản đó sinh ra trong
một phút. Đó là **trần hợp lệ**. Xem [mục 8.1](#81-cơ-sở-tính-toán-cho-nhóm-đọc).

### Bước 5 — Rủi ro bị lạm dụng

Kẻ tấn công cần bao nhiêu request để đạt mục đích? Đặt limit **thấp hơn con số đó nhiều lần**,
nhưng vẫn **cao hơn trần hợp lệ**.

Nếu hai khoảng này chồng lấn — tức người dùng hợp lệ cần nhiều request hơn kẻ tấn công — thì
rate limiting **không phải công cụ đúng**, phải dùng cách khác.

### Bước 6 — Chốt limit

```
limit = trần hợp lệ × hệ số an toàn
```

**Hệ số an toàn 2×** là mặc định hợp lý. Lý do:

- Số đo trần hợp lệ luôn thiếu vài kịch bản chưa nghĩ tới.
- Fixed window cho phép dồn cục ở ranh giới.
- Một `429` sai vào mặt nhân viên đang tiếp bệnh nhân là hỏng việc thật.

Với endpoint mà **chi phí sai lệch không đối xứng** — chặn nhầm thì phiền, không chặn thì mất
tiền — dùng hệ số thấp hơn và chấp nhận rủi ro chặn nhầm. Đó là trường hợp của payment.

---

## 6. Cách chọn window

Cùng một tốc độ trung bình có thể diễn đạt bằng nhiều window: 60/phút và 3600/giờ bằng nhau về
trung bình nhưng **hành xử hoàn toàn khác**.

| Window | Cho phép dồn cục | Phản hồi | Dùng khi |
| --- | --- | --- | --- |
| **per second** | Gần như không | Tức thì | Bảo vệ tài nguyên cứng; hầu như không hợp cho API có người dùng thật |
| **per minute** | Vừa phải | Nhanh — chờ tối đa 60s | **Mặc định cho đa số endpoint** |
| **per 5 minutes** | Cao | Chậm — chờ tối đa 5 phút | Khi cần cho phép làm việc theo đợt |
| **per hour** | Rất cao | Rất chậm | Hạn mức theo hạn ngạch, không phải chống lạm dụng |
| **per day** | Cực cao | Không chấp nhận được | Hạn mức thương mại (gói API), không dùng cho bảo vệ |

### Nguyên tắc

**Window ngắn = phản hồi nhanh + ít dồn cục, nhưng dễ chặn nhầm khi có đợt cao điểm hợp lệ.**
**Window dài = chịu được đợt cao điểm, nhưng người bị chặn oan phải chờ rất lâu.**

Thời gian chờ tối đa chính bằng độ dài window. Đây là thứ quyết định trải nghiệm khi bị chặn
nhầm, và là lý do **per minute là mặc định tốt cho hệ thống có người dùng ngồi trước màn hình**:
chặn nhầm thì tệ nhất là chờ 60 giây.

### Áp dụng cho từng nhóm

**Login → window ngắn (1 phút).** Hai lý do:

1. Người thật gõ sai mật khẩu 2-3 lần rồi nhớ ra. Chặn 5 lần rồi cho thử lại sau 60 giây là
   đủ để người thật không bực, mà kẻ dò mật khẩu thì chậm đi 500 lần.
2. Nếu dùng window 1 giờ, người quên mật khẩu phải chờ 60 phút — không chấp nhận được với
   phòng khám đang có bệnh nhân xếp hàng.

**GET thông thường → 1 phút, limit cao.** Lưu lượng đến theo đợt tự nhiên (mở trang bắn 7-10
request cùng lúc). Window ngắn với limit cao hấp thụ được đợt mà vẫn chặn được cào dữ liệu
kéo dài.

**Payment → 1 phút, limit rất thấp.** Không cần window dài vì tần suất hợp lệ cực thấp. Limit
thấp trong window ngắn cho phản hồi nhanh nếu chặn nhầm.

**Stats/report → 1 phút, limit thấp.** Cùng lý do payment. Có thể cân nhắc 5 phút nếu sau này
có báo cáo nặng thật sự — nhưng lúc đó cache kết quả là giải pháp đúng hơn là siết limit.

**CRUD → 1 phút, limit trung bình.** Cân bằng: đủ cao để nhập liệu hàng loạt không vướng, đủ
thấp để script không ghi rác nhanh được.

---

## 7. Chọn key

Đây là quyết định thiết kế quan trọng nhất. Sai key làm hỏng cả limit lẫn window.

### Ba lựa chọn và cách chúng hỏng

**Chỉ theo IP**

```php
->by($request->ip())
```

- Kẻ tấn công đổi IP là có sổ mới. Proxy rất rẻ.
- Nghiêm trọng hơn với hệ thống này: **cả phòng khám đi chung một IP public**. Một người gõ
  sai mật khẩu 5 lần sẽ khoá toàn bộ đồng nghiệp.

**Chỉ theo email**

```php
->by($request->input('email'))
```

- Bất kỳ ai biết `admin@clinic.test` đều có thể cố tình gõ sai 5 lần để **khoá admin**.
- Rate limiting trở thành vũ khí tấn công thay vì lớp phòng thủ.

**Theo `email + IP`** — lựa chọn cho login

```php
->by(Str::lower($request->input('email')).'|'.$request->ip())
```

- Dò mật khẩu một tài khoản từ một nguồn: bị chặn.
- Người lạ không khoá được tài khoản người khác (họ ở IP khác → sổ khác).
- Đồng nghiệp không ảnh hưởng nhau (email khác → sổ khác).

`Str::lower()` là bắt buộc: không chuẩn hoá thì `Admin@clinic.test` và `admin@clinic.test` là
hai sổ riêng, kẻ tấn công đổi hoa-thường là nhân đôi hạn mức.

### Key cho endpoint đã xác thực

```php
->by($request->user()?->id ?: $request->ip())
```

**Khoá theo `user id`, không theo IP.** Vì cả phòng khám chung một IP: nếu khoá theo IP với
hạn mức 120, 5 nhân viên thực tế chỉ được 24/phút mỗi người, và người dùng nặng làm 4 người
kia bị chặn oan.

Chỉ rơi về IP khi chưa xác thực — trường hợp hiếm vì gần như mọi route đều có `auth:sanctum`.

### Vì sao login cần hai lớp

Một `Limit` không đủ:

| Lớp | Key | Chặn được | Không chặn được |
| --- | --- | --- | --- |
| 5/phút | `email + IP` | Brute-force một tài khoản | Credential stuffing nhiều tài khoản |
| 20/phút | `IP` | Credential stuffing từ một nguồn | Tấn công phân tán nhiều IP |

Laravel cho closure trả về **mảng nhiều `Limit`** — tất cả phải cùng thoả. Hai lớp bổ khuyết
cho nhau chứ không thừa.

### Điều kiện tiên quyết: `$request->ip()` có đáng tin không

Mọi key ở trên có chứa IP đều dựa trên một giả định chưa được kiểm chứng: rằng
`$request->ip()` trả về IP thật của client. Trong project này, **giả định đó hiện chưa đúng**.

`bootstrap/app.php` chưa gọi `trustProxies()`. Middleware `TrustProxies` vẫn nằm trong global
stack mặc định của Laravel, nhưng việc đầu tiên nó làm là xoá sạch danh sách proxy tin cậy:

```php
// vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php
$request::setTrustedProxies([], $this->getTrustedHeaderNames());   // không tin ai
```

Chỉ khi có cấu hình thì danh sách mới được nạp lại. Chưa cấu hình nghĩa là `$request->ip()`
trả về `REMOTE_ADDR` thuần. Từ đó sinh ra hai kịch bản hỏng:

| Cấu hình | Hậu quả |
| --- | --- |
| Không khai báo, deploy sau nginx / load balancer | `REMOTE_ADDR` là IP của nginx (`127.0.0.1`, `10.0.x.x`). **Toàn bộ người dùng chung một sổ.** Limiter 20/phút thành 20 lần đăng nhập cho cả phòng khám |
| Tin mọi proxy: `trustProxies(at: '*')` | IP đọc từ header `X-Forwarded-For` — **do client gửi**. Kẻ tấn công đổi header mỗi request là có sổ mới, không cần proxy thật. Rate limiting bị vô hiệu hoá hoàn toàn |

Đúng: khai báo dải IP thật của tầng proxy phía trước, ví dụ `trustProxies(at: ['10.0.0.0/8'])`.

**Việc này phải chốt trước khi triển khai limiter theo IP**, vì cả hai kịch bản trên đều không
gây lỗi, không ghi log, và biểu đồ vẫn xanh — một cái chặn nhầm toàn bộ, một cái không chặn ai.

Key theo `user id` không bị ảnh hưởng. Chỉ login và các endpoint chưa xác thực mới phụ thuộc
vào điều kiện này — nhưng đó lại đúng là nhóm cần bảo vệ nhất.

---

## 8. Bảng đề xuất cho từng API

> **Cảnh báo quan trọng:** mọi con số dưới đây là **điểm khởi đầu dựa trên giả định**, không
> phải giá trị tuyệt đối. Chúng được suy ra từ dữ liệu demo (20 bệnh nhân, 30 lịch hẹn, 1
> người dùng đồng thời) và phải điều chỉnh sau khi có số liệu thật. Xem
> [mục 8.4](#84-giả-định-và-điều-kiện-xem-lại).

### 8.1. Cơ sở tính toán cho nhóm đọc

Đo trên frontend hiện tại:

| Hành động | Số request |
| --- | --- |
| Không có polling nền | 0 |
| Mở trang lịch hẹn | ~3 (`/me` + doctors + list) |
| **Chuyển sang xem theo tuần** | **7 cùng lúc** — `Promise.all` gọi 1 request/ngày |
| Gõ một tên bệnh nhân để tìm | ~3-4 (đã debounce 350 ms) |
| Lưu form | 1-2 |

**Kịch bản lễ tân bận rộn nhất trong 1 phút:**

```
Mở trang lịch hẹn                    3
Chuyển tuần 5 lần × 7 request       35
Tìm 3 bệnh nhân × 4 request         12
Tạo 2 lịch hẹn (form + lưu)          6
Đổi trạng thái 3 lịch hẹn            6
                                   ────
Trần hợp lệ                        ~62 request/phút
```

**Limit đề xuất = 62 × 2 ≈ 120/phút mỗi người dùng.**

Hệ số 2× bù cho: kịch bản chưa nghĩ tới, dồn cục ở ranh giới window, và trường hợp một người
mở nhiều tab.

### 8.2. Thiết kế theo tầng

Thay vì mỗi endpoint một limiter (khó bảo trì, khó giải thích), dùng **4 limiter có tên**, áp
chồng lên nhau. Route nào có hai limiter thì cả hai cùng phải thoả — đây là phòng thủ nhiều lớp.

| Limiter | Limit | Window | Key | Áp cho |
| --- | --- | --- | --- | --- |
| `login` | 5 **và** 20 | 1 phút | `email+IP` / `IP` | `POST /api/login` |
| `api` | 120 | 1 phút | `user id` → IP | Toàn bộ route đã xác thực |
| `sensitive` | 20 | 1 phút | `user id` | `/api/stats` |
| `payment` | 10 | 1 phút | `user id` | 3 endpoint PayPal |

### 8.3. Bảng chi tiết

| API | Method | Nhóm | Risk | Resource cost | Limit | Window | Key | Lý do |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `/api/login` | POST | Auth | **Cao** | **226 ms** (bcrypt 12) | **5** | 1 phút | `email+IP` | Public, đắt nhất, hậu quả nặng nhất. Người thật đăng nhập 1-2 lần/ngày nên 5 không bao giờ vướng. Chuẩn của Laravel Fortify. |
| `/api/login` | POST | Auth | **Cao** | 226 ms | **20** | 1 phút | `IP` | Lớp 2 chống credential stuffing — kiểu tấn công mà key `email+IP` không thấy. 20 > 5 nên không ảnh hưởng người dùng thật. |
| `/api/me` | GET | Session | Thấp | 24 ms | 120 | 1 phút | `user id` | Theo mức chung. Chỉ cần chặn vòng lặp lỗi ở client. |
| `/api/logout` | POST | Session | Thấp | ~25 ms | 120 | 1 phút | `user id` | Như trên. |
| `/api/patients` | GET | Read | Vừa | 27 ms | 120 | 1 phút | `user id` | Bề mặt cào dữ liệu lớn nhất. 120 vẫn cho phép kịch bản 62 request/phút, nhưng biến việc cào 5.000 hồ sơ từ 30 giây thành 42 phút. |
| `/api/patients?q=` | GET | Search | Vừa | 23 ms | 120 | 1 phút | `user id` | Cùng đường dẫn, cùng chi phí. Đã có debounce 350 ms ở client. |
| `/api/appointments` | GET | Read | Vừa | 33 ms | 120 | 1 phút | `user id` | **Nhóm chi phối trần**: xem theo tuần bắn 7 request cùng lúc. |
| `/api/medicines`, `/low-stock` | GET | Read | Thấp | 23-25 ms | 120 | 1 phút | `user id` | Dữ liệu danh mục, ít nhạy cảm. |
| `/api/prescriptions`, `/api/invoices` | GET | Read | Vừa | 32-35 ms | 120 | 1 phút | `user id` | Có eager loading nên nặng hơn chút, vẫn không tăng theo kích thước bảng. |
| `/api/doctors`, `/specialties`, `/roles`, `/users` | GET | Read | Thấp-Vừa | ~25 ms | 120 | 1 phút | `user id` | `/api/users` nhạy cảm hơn nhưng đã có RBAC chặn (`USERS.FINDALL` chỉ ADMIN). |
| `/api/payments` | GET | Read | Vừa | ~25 ms | 120 | 1 phút | `user id` | Chỉ đọc, không gọi PayPal. Khác hẳn 3 endpoint bên dưới. |
| **`/api/invoices/{id}/payments`** | POST | **Payment** | **Rất cao** | **300-1000 ms** (HTTP → PayPal) | **10** | 1 phút | `user id` | Tiền thật + gọi bên thứ ba. PayPal có hạn mức riêng, tự siết là bảo vệ hạn mức đó. Một hoá đơn thanh toán 1 lần nên 10 đã rất rộng. |
| **`/api/payments/{id}/capture`** | POST | **Payment** | **Rất cao** | **300-1000 ms** | **10** | 1 phút | `user id` | Như trên. Đây là bước tiền thực sự chuyển. |
| `/api/payments/paypal/client-token` | GET | Payment | Cao | 300-1000 ms | 10 | 1 phút | `user id` | Cũng gọi PayPal. Frontend gọi 1 lần mỗi lần mở form thanh toán. |
| **`/api/stats`** | GET | **Report** | Thấp | **25 ms hôm nay, O(n) theo dữ liệu** | **20** | 1 phút | `user id` | 4 truy vấn `COUNT`/`SUM` toàn bảng. `COUNT(*)` không dùng được index trên Postgres → chi phí tăng tuyến tính. Dashboard nạp 1 lần/trang nên 20 rất rộng. **Siết trước khi thành vấn đề.** |
| `POST/PATCH/PUT` patients, appointments, examinations, prescriptions, invoices, medicines, doctors, specialties, users | Write | Write | Vừa | Cao hơn đọc (transaction + lock) | 120 | 1 phút | `user id` | Theo mức chung ở giai đoạn 1. Con người không lưu form nhanh hơn vài chục lần/phút, nên 120 chưa bao giờ vướng — nhưng cũng vì thế mà nó **không siết được gì**. Xem giai đoạn 2 bên dưới. |
| `PATCH /api/medicines/{id}/stock` | PATCH | Write | Vừa | transaction + lock + log | 120 | 1 phút | `user id` | Như trên. |
| `DELETE` patients, medicines, doctors, specialties, prescription items | DELETE | Delete | Cao (nghiệp vụ) | Thấp | 120 | 1 phút | `user id` | **Rate limiting không phải lớp bảo vệ chính ở đây** — RBAC, khoá ngoại `RESTRICT` và soft delete mới là. |

### 8.4. Giả định và điều kiện xem lại

**Các giả định đang dùng:**

| # | Giả định | Nếu sai thì sao |
| --- | --- | --- |
| 1 | Số người dùng đồng thời ở mức một phòng khám nhỏ (dưới ~20) | Không ảnh hưởng — key theo user id nên mỗi người có sổ riêng |
| 2 | Frontend không thêm polling | Polling 30 giây sẽ thêm 2 request/phút — không đáng kể; polling 1 giây thì phải tính lại |
| 3 | Debounce 350 ms trên ô tìm kiếm được giữ nguyên | Gỡ debounce → tìm kiếm có thể sinh 15+ request cho một cái tên, phải nâng limit hoặc bỏ chặn |
| 4 | Không có tích hợp máy-gọi-máy nào | Một hệ thống ngoài đồng bộ dữ liệu qua API sẽ vượt xa 120/phút — cần token riêng với limiter riêng |
| 5 | Chi phí `/api/stats` sẽ tăng theo kích thước dữ liệu | Nếu sau này cache kết quả thì có thể nới limit |

**Các số liệu đo được trên dữ liệu demo, chưa phải production:**

Toàn bộ số ms trong bảng đo với 20 bệnh nhân / 30 lịch hẹn / 18 thuốc. Chúng cho biết **thứ tự
độ lớn tương đối** (login đắt gấp 9 lần một request đọc) chứ **không phải giá trị tuyệt đối ở
quy mô thật**.

**Cần đo thêm gì trước khi chốt số:**

| Cần đo | Cách đo | Dùng để |
| --- | --- | --- |
| Phân bố request/phút thực tế mỗi user | Log số request theo `user_id` trong 2 tuần, lấy phân vị p95 và p99 | Thay con số 62 ước lượng bằng số thật |
| Đỉnh thật sự trong giờ cao điểm | Đếm request theo cửa sổ 1 phút, tìm giá trị lớn nhất | Kiểm tra hệ số an toàn 2× có đủ không |
| `/api/stats` ở quy mô thật | `EXPLAIN ANALYZE` với 100k+ dòng | Quyết định giữ limit 20 hay chuyển sang cache kết quả |
| Độ trễ thật của PayPal | Log `time_total` của mọi lần gọi PayPal | Xác nhận 300-1000 ms có đúng không |
| Tỉ lệ 429 sau khi bật | Đếm response 429 theo endpoint | Xem [mục 12](#12-monitoring-và-điều-chỉnh) |

**Load test cần chạy:**

1. **Baseline** — `k6` hoặc `ab` mô phỏng 10 user đồng thời chạy kịch bản lễ tân trong 10 phút.
   Ghi lại request/phút mỗi user và độ trễ p95. Đây là con số thay thế cho ước lượng 62.
2. **Kiểm tra ngưỡng** — một user chạy 150 request/phút, xác nhận bị 429 đúng ở request thứ 121.
3. **Kiểm tra chặn nhầm** — chạy kịch bản lễ tân với limit đã bật, xác nhận **không có 429 nào**.
   Đây là bài quan trọng nhất; có 429 nghĩa là limit quá thấp.
4. **Bài stress cho stats** — nhân dữ liệu lên 100k dòng rồi đo lại `/api/stats`.

**Quy trình điều chỉnh sau khi có dữ liệu:**

```
limit mới = p99 thực tế của request/phút mỗi user × 1,5
```

Chuyển từ hệ số 2× sang 1,5× khi đã có số thật, vì lúc đó phần bất định giảm đi.

Nếu p99 thật vượt 120 → **nâng limit**, đừng bắt người dùng chịu. Nếu p99 thật chỉ khoảng 30 →
hạ xuống 60 để siết chặt hơn mà vẫn còn dư địa.

### 8.5. Giai đoạn 2 — tách riêng nhóm ghi

Ở giai đoạn 1, mọi route đã xác thực dùng chung limit 120. Nhóm ghi thực chất **không được
bảo vệ gì thêm**, vì con người không bao giờ chạm tới 120 lần lưu form mỗi phút.

Tách riêng một limiter `write` khoảng **40/phút** sẽ siết đúng chỗ. Lý do chưa làm ngay:

- Phải sửa nhiều dòng trong `routes/api.php` (`apiResource` không cho gắn middleware theo từng
  method), làm diff to và khó review.
- Chưa có dữ liệu thật về đỉnh nhập liệu. Nhập bệnh nhân hàng loạt sau một đợt khám đông có thể
  vượt 40 — cần đo trước.

Ghi lại ở đây để không quên, và làm ở một task riêng sau khi có số liệu.

---

## 9. Kế hoạch triển khai Laravel

### 9.1. Hạ tầng — đã sẵn sàng

| Hạng mục | Trạng thái |
| --- | --- |
| Laravel | 13.23.0 |
| `CACHE_STORE` | `database` |
| Bảng `cache`, `cache_locks` | **đã tồn tại** (`0001_01_01_000001_create_cache_table.php`) |
| Limiter đã khai | chưa có |
| Throttle trên route | chưa có |

**Không cần migration mới.** Bộ đếm nằm trong bảng `cache` của PostgreSQL, nghĩa là dùng chung
giữa nhiều tiến trình PHP và sống sót qua restart container. Đổi lại mỗi request tốn thêm 1-2
query nhẹ — chấp nhận được.

*Ghi chú về Redis:* nếu sau này chuyển sang Redis, đổi `CACHE_STORE=redis` là xong, không phải
sửa code. Laravel còn có `throttleWithRedis()` dùng thuật toán sliding window chính xác hơn.
Chưa cần ở quy mô hiện tại.

### 9.2. Khai báo limiter

Đặt trong `app/Providers/AppServiceProvider::boot()` — nơi project đã dùng cho `enforceMorphMap`
và đăng ký observer.

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

RateLimiter::for('login', function (Request $request): array {
    // Closure này chạy trong middleware, trước khi LoginRequest kiểm tra bất cứ thứ gì,
    // nên input vẫn còn thô. Không có is_string() thì một payload dạng `email[]=x` làm
    // Str::lower ném TypeError → 500, và request đó KHÔNG bao giờ được đếm: kẻ tấn công
    // có một đường thử vô hạn đi vòng qua toàn bộ rate limiting.
    // Str::lower là bắt buộc vì users.email collate không phân biệt hoa thường: "Admin@"
    // và "admin@" vào cùng một tài khoản nhưng sinh hai key khác nhau nếu không chuẩn hoá.
    $email = $request->input('email');
    $email = is_string($email) ? Str::lower(trim($email)) : '';

    return [
        // Lớp 1: chặn dò mật khẩu một tài khoản từ một nguồn.
        Limit::perMinute(5)->by($email.'|'.$request->ip()),

        // Lớp 2: chặn credential stuffing — nhiều email, mỗi email một lần,
        // cùng một IP. Lớp 1 không thấy kiểu này vì không sổ nào chạm 5.
        Limit::perMinute(20)->by($request->ip()),
    ];
});

RateLimiter::for('api', function (Request $request): Limit {
    // Khoá theo user id chứ không theo IP: cả phòng khám đi chung một IP
    // public, khoá theo IP sẽ khiến người dùng nặng chặn oan đồng nghiệp.
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('sensitive', function (Request $request): Limit {
    return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('payment', function (Request $request): Limit {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});
```

### 9.3. Gắn vào route

```php
// routes/api.php

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'permission', 'throttle:api'])->group(function (): void {
    // Endpoint gọi PayPal: throttle:payment chồng lên throttle:api,
    // cả hai cùng phải thoả.
    Route::get('/payments/paypal/client-token', [PaymentController::class, 'clientToken'])
        ->middleware('throttle:payment');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])
        ->middleware('throttle:payment');
    Route::post('/payments/{payment}/capture', [PaymentController::class, 'capture'])
        ->middleware('throttle:payment');

    Route::get('/stats', [StatsController::class, 'show'])
        ->middleware('throttle:sensitive');

    // ... các route còn lại thừa hưởng throttle:api từ group
});
```

**Middleware chồng nhau là có chủ ý.** `/api/stats` chịu cả `throttle:api` (120) lẫn
`throttle:sensitive` (20) — cái chặt hơn thắng, và nếu sau này nới `sensitive` thì `api` vẫn
là lưới an toàn.

Điều này áp dụng cho cả header trả về. `getHeaders()` trong `ThrottleRequests` bỏ qua header
mới nếu response đã mang một `X-RateLimit-Remaining` nhỏ hơn hoặc bằng, nên client luôn đọc
được hạn mức của **limiter chặt nhất** chứ không phải của limiter chạy sau cùng.

#### Thứ tự middleware: throttle luôn chạy SAU xác thực

`Limit::...->by($request->user()?->id ...)` chỉ hoạt động nếu xác thực đã chạy xong trước đó.
Điều đó được bảo đảm, nhưng **không phải** do thứ tự viết trong mảng `middleware([...])`.
`Router::sortMiddleware()` sắp lại toàn bộ middleware của route theo bảng `$middlewarePriority`
trong `Illuminate\Foundation\Http\Kernel`, và bảng đó xếp:

```php
\Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,   // auth:sanctum
\Illuminate\Routing\Middleware\ThrottleRequests::class,               // throttle:*
```

`auth:sanctum` đứng trên, nên viết `['throttle:api', 'auth:sanctum']` hay ngược lại đều cho
cùng kết quả. Đối chiếu: `/api/login` không có middleware xác thực nào, nên `$request->user()`
luôn là `null` ở đó — đó là lý do limiter `login` buộc phải đọc email từ input thô và tự phòng
vệ kiểu dữ liệu.

**Hệ quả chưa được xử lý:** vì throttle chạy *sau* xác thực, một request mang token sai bị
`auth:sanctum` trả 401 **trước khi** chạm tới `throttle:api`, tức là **không bị đếm**. Kẻ tấn
công có thể bắn token rác vào `/api/patients` không giới hạn, mỗi request vẫn tốn một truy vấn
tra token trong bảng `personal_access_tokens`.

Bịt lỗ này cần một throttle theo IP đặt ở **tầng global middleware** (`bootstrap/app.php`,
`$middleware->api(prepend: ...)`), vì middleware toàn cục chạy trước mọi route middleware và
không bị bảng priority sắp lại. Đề xuất khoảng 300/phút theo IP làm lưới ngoài cùng — rộng hơn
`api` (120) nhiều lần để không bao giờ chạm phải người dùng thật, chỉ chặn lưu lượng rác.
Chưa làm trong task này vì nó đụng vào tầng global và cần cấu hình trusted proxies trước mới
đo đúng IP; ghi lại ở đây để không quên.

### 9.4. Response 429 — không phải viết thêm gì

`ThrottleRequestsException` kế thừa `TooManyRequestsHttpException`, implement
`HttpExceptionInterface`. Trong `bootstrap/app.php` đã có sẵn:

```php
$exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
    if (! $request->is('api/*')) {
        return null;
    }

    $status = $exception->getStatusCode();
    $message = $exception->getMessage()
        ?: (HttpResponse::$statusTexts[$status] ?? ExceptionMessage::REQUEST_FAILED);

    $response = ApiResponse::error($message, status: $status);
    $response->headers->add($exception->getHeaders());

    return $response;
});
```

Dòng `headers->add()` là bắt buộc và từng bị thiếu. `ApiResponse::error()` dựng một
`JsonResponse` mới hoàn toàn, không biết gì về exception, nên bốn header rate limit — do
`buildException()` nhét vào chính exception — sẽ biến mất nếu không copy lại. Đặt ở nhánh
`HttpExceptionInterface` chung thay vì nhánh riêng cho `ThrottleRequestsException` để mọi
exception mang header đều được cứu (`503` kèm `Retry-After`, `405` kèm `Allow`, …).

Nên 429 **tự động rơi vào envelope chuẩn**:

```json
{
    "success": false,
    "message": "Too Many Attempts.",
    "errors": []
}
```

Đây là phần thưởng cho việc xử lý exception tập trung ngay từ đầu.

**Nếu muốn thông điệp riêng**, thêm vào `ExceptionMessage` và dùng `->response()`:

```php
Limit::perMinute(5)
    ->by($email.'|'.$request->ip())
    ->response(fn (Request $request, array $headers) => ApiResponse::error(
        ExceptionMessage::TOO_MANY_LOGIN_ATTEMPTS,
        status: HttpResponse::HTTP_TOO_MANY_REQUESTS,
    )->withHeaders($headers));
```

**Chú ý bảo mật:** thông điệp login không được tiết lộ email đó có tồn tại hay không. Giữ
chung một câu cho mọi trường hợp, giống cách `/api/login` hiện đang trả cùng một 401 cho "sai
mật khẩu" và "email không tồn tại".

### 9.5. Header

Laravel tự thêm, không phải làm gì:

| Header | Khi nào | Ý nghĩa |
| --- | --- | --- |
| `X-RateLimit-Limit` | Mọi response | Hạn mức của window |
| `X-RateLimit-Remaining` | Mọi response | Còn lại bao nhiêu |
| `Retry-After` | Chỉ khi 429 | Số **giây** phải chờ |
| `X-RateLimit-Reset` | Chỉ khi 429 | Timestamp lúc reset |

`Retry-After` là header duy nhất client **bắt buộc** phải tôn trọng. Nếu sau này viết client
tự động, phải đọc header này để chờ thay vì retry ngay.

### 9.6. Limit động theo role (chưa làm ở giai đoạn 1)

Project đã có RBAC theo bảng nên mở rộng được:

```php
RateLimiter::for('api', function (Request $request): Limit {
    $user = $request->user();

    if ($user === null) {
        return Limit::perMinute(30)->by($request->ip());
    }

    return match ($user->role?->name) {
        'ADMIN' => Limit::perMinute(300)->by($user->id),
        default => Limit::perMinute(120)->by($user->id),
    };
});
```

**Chưa nên làm ngay**, hai lý do:

1. `$user->role?->name` sinh thêm một query mỗi request nếu quan hệ chưa được nạp — đúng vấn
   đề mà task cache permission đang định giải quyết. Làm sau task đó thì rẻ hơn.
2. Chưa có bằng chứng ADMIN cần nhiều hơn. Thêm phức tạp mà không có dữ liệu là đầu cơ.

Ghi lại để biết đường mở rộng khi có nhu cầu thật.

### 9.7. Phía client

**Đây là phần dễ quên nhất, và bỏ qua nó thì rate limiting phản tác dụng với người dùng thật.**

#### Vấn đề hiện tại

`resources/js/core/api-client.js` xử lý riêng 401 nhưng **không có gì cho 429**:

```js
if (!response.ok || payload?.success === false) {
    if (response.status === 401 && token) {
        window.dispatchEvent(new CustomEvent('clinic:unauthorized'));
    }

    throw new ApiError(payload?.message ?? 'Yêu cầu không thành công.', { ... });
}
```

Hệ quả khi bật rate limiting: nhân viên nhận đúng một câu tiếng Anh thô — `"Too Many
Attempts."` — **không biết mình đã làm gì sai, cũng không biết phải chờ bao lâu**, dù server
đã gửi kèm `Retry-After` trong header. Người dùng sẽ bấm lại liên tục, làm bộ đếm không bao
giờ kịp hết hạn.

#### Sửa: đọc `Retry-After` và nói bằng tiếng Việt

**Bước 1** — `ApiError` mang thêm số giây phải chờ:

```js
// resources/js/core/api-error.js
export class ApiError extends Error {
    constructor(message, { status = 0, errors = {}, payload = null, retryAfter = null } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.payload = payload;
        this.retryAfter = retryAfter;   // số giây, chỉ có khi status = 429
    }
    // ...
}
```

**Bước 2** — `api-client.js` dịch 429 thành câu người đọc hiểu:

```js
if (!response.ok || payload?.success === false) {
    if (response.status === 401 && token) {
        window.dispatchEvent(new CustomEvent('clinic:unauthorized'));
    }

    // Server đã nói phải chờ bao lâu; không chuyển tiếp thông tin đó thì
    // người dùng chỉ còn cách bấm lại liên tục, khiến bộ đếm không kịp hết hạn.
    if (response.status === 429) {
        const retryAfter = Number(response.headers.get('Retry-After')) || 60;

        throw new ApiError(
            `Bạn đã thao tác quá nhanh. Vui lòng thử lại sau ${retryAfter} giây.`,
            { status: 429, retryAfter, payload },
        );
    }

    throw new ApiError(payload?.message ?? 'Yêu cầu không thành công.', { ... });
}
```

Thay hẳn thông điệp của server thay vì hiển thị nó, vì hai lý do: `"Too Many Attempts."` là
tiếng Anh trong khi toàn bộ giao diện là tiếng Việt, và nó không chứa thời gian chờ.

#### Tự thử lại: chỉ cho request đọc, và phải có jitter

Với `GET`, có thể tự chờ rồi thử lại thay vì báo lỗi ngay:

```js
async function requestWithRetry(path, options, attempt = 1) {
    try {
        return await apiRequest(path, options);
    } catch (error) {
        const method = (options.method ?? 'GET').toUpperCase();

        // Chỉ thử lại request đọc. POST/PATCH/DELETE không idempotent —
        // thử lại có thể tạo hai lịch hẹn hoặc trừ kho hai lần.
        if (error.status !== 429 || method !== 'GET' || attempt > 3) {
            throw error;
        }

        // Jitter: nếu 20 tab cùng bị chặn mà cùng chờ đúng N giây, chúng sẽ
        // cùng thử lại một lúc và lại cùng bị chặn. Ngẫu nhiên hoá để rải ra.
        const base = (error.retryAfter ?? 60) * 1000;
        const jitter = Math.random() * 1000;

        await new Promise((resolve) => setTimeout(resolve, base + jitter));

        return requestWithRetry(path, options, attempt + 1);
    }
}
```

**Ba quy tắc bắt buộc khi tự thử lại:**

1. **Chỉ với thao tác idempotent.** `GET` gọi lại 10 lần vẫn cho cùng kết quả. `POST
   /api/appointments` gọi lại là tạo thêm một lịch hẹn. `POST /api/payments/{id}/capture` gọi
   lại là **rủi ro tiền bạc** — tuyệt đối không tự thử lại.
2. **Phải có giới hạn số lần.** Không có `attempt > 3` thì gặp server chặn kéo dài là vòng lặp
   vô tận.
3. **Phải có jitter.** Không có thì các client bị chặn cùng lúc sẽ đồng loạt thử lại cùng lúc —
   hiện tượng *thundering herd*, biến rate limiting thành nguồn tạo đợt tải mới.

#### Với login thì không tự thử lại

Người dùng phải thấy thông báo và tự quyết định. Tự thử lại một lần đăng nhập sai chỉ làm tiêu
thêm hạn mức và kéo dài thời gian bị khoá.

Giao diện login nên hiển thị đồng hồ đếm ngược lấy từ `error.retryAfter`, thay vì chỉ một câu
báo lỗi tĩnh.

#### Ghi chú phạm vi

Phần client này **không nằm trong task rate limiting của backend**. Nhưng bật giới hạn ở server
mà không sửa client thì lỗi 429 sẽ đổ lên đầu nhân viên dưới dạng một thông báo vô nghĩa. Nên
làm cùng đợt, hoặc làm ngay sau đó ở một task riêng.

### 9.8. Chi phí và hành vi đồng thời của chính rate limiter

Rate limiting không miễn phí. Nó là công việc thêm trên **mọi** request, kể cả request hợp lệ.

#### Đo được

Trên môi trường này, với `CACHE_STORE=database`:

```
RateLimiter::tooManyAttempts() + RateLimiter::hit()  →  2,87 ms/request
```

Đặt cạnh chi phí của endpoint:

| Endpoint | Chi phí gốc | Thêm | Tỉ lệ |
| --- | --- | --- | --- |
| `POST /api/login` | 226 ms | 2,87 ms | **1,3%** |
| `GET /api/appointments` | 33 ms | 2,87 ms | **8,7%** |
| `GET /api/patients?q=` | 23 ms | 2,87 ms | **12,5%** |

**Kết luận:** với login thì chi phí không đáng kể so với lợi ích. Với endpoint đọc thì thêm
~10% độ trễ — chấp nhận được, nhưng phải biết mà nói ra thay vì lờ đi.

Nếu sau này 10% trở thành vấn đề, đổi `CACHE_STORE` sang `redis` là cách rẻ nhất: Redis thao
tác trong bộ nhớ nên nhanh hơn nhiều lần so với round-trip xuống PostgreSQL, và đổi một dòng
`.env` chứ không phải sửa code.

#### Tính nguyên tử — có, đã kiểm chứng

Câu hỏi tự nhiên: hai request đến cùng lúc có thể **cùng đọc** bộ đếm bằng 4, rồi **cùng ghi**
5, khiến một lần đếm bị mất không?

`Illuminate\Cache\DatabaseStore::incrementOrDecrement`:

```php
return $this->connection->transaction(function () use ($key, $value, $callback) {
    $cache = $this->table()->where('key', $prefixed)
        ->lockForUpdate()->first();
    // ...
});
```

**Có `transaction()` + `lockForUpdate()` nên thao tác là nguyên tử.** Đây cũng đúng kỹ thuật mà
project đang dùng ở 23 chỗ trong 9 service. Không có lỗ hổng đếm sai.

#### Nhưng khoá kéo theo xếp hàng

`lockForUpdate()` nghĩa là **các request dùng chung một key sẽ nối đuôi nhau**, không chạy song song.

| Limiter | Key | Có tranh chấp không |
| --- | --- | --- |
| `throttle:api` | `user id` | **Không** — mỗi người một key riêng |
| `login` lớp 1 | `email + IP` | Không đáng kể — login thưa |
| `login` lớp 2 | `IP` | **Có** — cả phòng khám chung một key |

Với `login`, tranh chấp không thành vấn đề vì tần suất đăng nhập rất thấp.

**Điều này củng cố quyết định ở [mục 7](#7-chọn-key):** khoá `throttle:api` theo `user id`
không chỉ tránh chặn nhầm đồng nghiệp, mà còn tránh biến rate limiter thành nút thắt tuần tự
hoá. Nếu khoá theo IP, 7 request song song của màn hình xem-theo-tuần sẽ phải xếp hàng chờ nhau
lấy khoá — biến 7 × 2,87 ms song song thành ~20 ms tuần tự.

### 9.9. Miễn trừ: những thứ không được chặn

#### Health check

`bootstrap/app.php` đăng ký `health: '/up'`. Endpoint này **nằm ngoài `api/*`**, nên
`throttle:api` áp cho group API không chạm tới nó — hiện tại an toàn.

Ghi lại để cảnh giác: nếu sau này ai đó áp throttle ở mức toàn ứng dụng, `/up` sẽ bị chặn khi
hệ thống giám sát gọi quá dày, và **báo động giả rằng server đã chết**. Đây là kiểu lỗi rất
khó chẩn đoán vì triệu chứng (server "chết") không liên quan gì tới nguyên nhân (rate limit).

Quy tắc: **health check không bao giờ bị rate limit.**

#### Thao tác hàng loạt hợp lệ

Tình huống thật: admin cần nhập 500 bệnh nhân từ file Excel, hoặc một hệ thống ngoài đồng bộ
dữ liệu hằng đêm. Cả hai đều vượt xa 120 request/phút mà **hoàn toàn chính đáng**.

Ba hướng xử lý, theo thứ tự ưu tiên:

**Hướng 1 — Đẩy vào queue (tốt nhất).** Không tạo 500 request. Client tải file lên bằng **một**
request, server đẩy vào queue và xử lý nền. Rate limiting không còn liên quan.

Đây là cách đúng, và nó trùng với một mục điểm cộng khác của đề bài (queue job).

**Hướng 2 — Miễn trừ theo role.** Laravel có `Limit::none()`:

```php
RateLimiter::for('api', function (Request $request) {
    $user = $request->user();

    // Chỉ miễn trừ cho tiến trình đồng bộ tự động, không phải cho mọi ADMIN.
    if ($user?->email === config('clinic.sync_account_email')) {
        return Limit::none();
    }

    return Limit::perMinute(120)->by($user?->id ?: $request->ip());
});
```

**Cảnh báo:** miễn trừ cho cả role `ADMIN` là ý tồi — tài khoản admin bị chiếm quyền là tài
khoản nguy hiểm nhất, và đó lại chính là tài khoản không còn giới hạn nào. Nếu phải miễn trừ,
miễn trừ cho **một tài khoản dịch vụ cụ thể**, không phải cho một role.

**Hướng 3 — Token riêng có limiter riêng.** Sanctum hỗ trợ ability cho token. Cấp cho hệ thống
tích hợp một token có ability `sync`, rồi:

```php
if ($request->user()?->tokenCan('sync')) {
    return Limit::perMinute(1000)->by($request->user()->id);
}
```

Vẫn có giới hạn (1000 thay vì vô hạn), nhưng đủ rộng cho máy. Tách được lưu lượng máy khỏi
lưu lượng người, và thu hồi được độc lập.

**Hướng nào cũng phải ghi lại lý do trong code.** Một `Limit::none()` không có comment giải
thích sẽ bị người sau đọc thành lỗ hổng bảo mật.

---

## 10. Rate limiting không thay thế được gì

Đây là chỗ dễ hiểu sai nhất. Rate limiting là **một lớp**, không phải lớp bảo vệ duy nhất.

| Cơ chế | Trả lời câu hỏi | Rate limiting KHÔNG làm được |
| --- | --- | --- |
| **Authentication** (Sanctum) | *Bạn là ai?* | Không biết người gọi là ai — chỉ đếm. Không giới hạn nào ngăn được người có token hợp lệ làm điều họ được phép làm. |
| **Authorization** (RBAC) | *Bạn được làm gì?* | Không kiểm tra quyền. Lễ tân bị giới hạn 120/phút vẫn không thể tạo hoá đơn — đó là việc của `EnsurePermission`. |
| **Validation** (FormRequest) | *Dữ liệu có hợp lệ không?* | Không nhìn vào nội dung. 5 request rác vẫn lọt qua nếu chưa chạm ngưỡng. |
| **Ràng buộc DB** | *Dữ liệu có nhất quán không?* | Không ngăn được ghi trùng hay sai tham chiếu. `UNIQUE`, khoá ngoại và transaction làm việc đó. |
| **WAF / Cloudflare** | *Request này có độc hại không?* | Chặn ở tầng ứng dụng là muộn — request đã tốn một vòng bootstrap Laravel. Và **không chặn được tấn công phân tán từ nhiều IP**. |
| **Khoá tài khoản** | *Tài khoản này có bị xâm phạm không?* | Rate limit tự hết hạn sau 60 giây. Nghi ngờ bị chiếm tài khoản thì phải khoá thật (`users.is_active = false`), việc mà project đã làm được. |

### Vị trí trong chuỗi phòng thủ

```
Internet
   │
   ▼  WAF / Cloudflare        ← chặn tấn công phân tán, IP xấu đã biết
   ▼  nginx                   ← giới hạn kết nối, kích thước body
   ▼  RATE LIMITING           ← ta đang ở đây: giới hạn tần suất mỗi bên gọi
   ▼  auth:sanctum            ← bạn là ai
   ▼  EnsurePermission        ← bạn được làm gì
   ▼  FormRequest             ← dữ liệu có hợp lệ không
   ▼  Service + transaction   ← nghiệp vụ có nhất quán không
   ▼  Ràng buộc PostgreSQL    ← lưới cuối cùng
```

Rate limiting nằm **trước** authentication, nên nó bảo vệ được cả bcrypt của `/api/login` —
phần đắt nhất hệ thống. Đó là lý do nó phải là middleware, không phải kiểm tra trong controller.

---

## 11. Testing

### 11.1. Bối cảnh của project

Đã kiểm tra bộ test hiện tại:

- **10 lần gọi `/api/login`** trải trên 3 file, **nhiều nhất 2 lần trong cùng một test**.
- `phpunit.xml` đặt `CACHE_STORE=array`, và mỗi test tạo một Application mới → `ArrayStore`
  mới → bộ đếm reset. **Trạng thái không rò rỉ giữa các test.**

**Kết luận: với ngưỡng 5/phút, không test hiện có nào bị gãy.** Nhưng đây là chi tiết dễ đổi
(ai đó thêm test đăng nhập 6 lần), nên test throttle vẫn phải tự dọn trạng thái.

### 11.2. Các trường hợp cần phủ

| # | Trường hợp | Kỳ vọng |
| --- | --- | --- |
| 1 | Trong giới hạn | 200/201 bình thường |
| 2 | Vượt giới hạn | 429, envelope `success: false` |
| 3 | Có header `Retry-After` | Số giây > 0 |
| 4 | Sau khi window reset | Được phép lại |
| 5 | Hai email khác nhau, cùng IP | Sổ riêng — email B không bị ảnh hưởng bởi email A |
| 6 | Cùng email, hai IP khác nhau | Sổ riêng |
| 7 | Lớp IP của login | 4 email × 5 lần = 20, lần thứ 21 bị chặn dù mỗi email chưa chạm 5 |
| 8 | Hai user đã đăng nhập khác nhau | Sổ riêng trên `throttle:api` |
| 9 | `/api/stats` chặt hơn mức chung | Bị chặn ở request thứ 21, không phải 121 |
| 10 | Đăng nhập đúng vẫn bị đếm | Ghi rõ hành vi đã chọn (đếm mọi request) |

### 11.3. Kỹ thuật

**Dọn trạng thái** — bắt buộc, kể cả khi hiện tại không cần:

```php
protected function setUp(): void
{
    parent::setUp();

    Cache::flush();
}
```

**Đừng dùng `RateLimiter::clear('login')`.** `clear()` nhận một *cache key*, không phải tên
limiter. `ThrottleRequests` tính key thật là:

```php
md5($limiterName.$limit->key)   // ví dụ: md5('login' . 'admin@clinic.test|127.0.0.1')
```

Gọi `RateLimiter::clear('login')` sẽ xoá một key tên đúng là `"login"` — key này không tồn
tại, nên lệnh không làm gì cả và test vẫn dính trạng thái cũ. `Cache::flush()` với driver
`array` là cách chắc chắn và rẻ.

**Đổi IP giả lập** — `withServerVariables` để test key:

```php
$this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
    ->postJson('/api/login', [...]);
```

**Tua đồng hồ** thay vì `sleep(61)`:

```php
$this->travel(61)->seconds();

$this->postJson('/api/login', [...])->assertUnauthorized();  // được phép lại
```

`sleep()` làm bộ test chậm đi 61 giây mỗi lần — không chấp nhận được.

**Khung một test tiêu biểu:**

```php
public function test_login_is_throttled_after_five_failed_attempts(): void
{
    $payload = ['email' => 'admin@clinic.test', 'password' => 'wrong'];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/login', $payload)->assertUnauthorized();
    }

    $response = $this->postJson('/api/login', $payload)
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
        ->assertJsonPath('success', false);

    $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

    // Cửa sổ hết hạn thì được thử lại — 429 phải tự hết, không phải khoá vĩnh viễn.
    $this->travel(61)->seconds();

    $this->postJson('/api/login', $payload)->assertUnauthorized();
}
```

### 11.4. Negative control

Test throttle rất dễ "xanh giả": nếu limiter chưa được đăng ký, request thứ 6 vẫn trả 401 và
một assertion viết cẩu thả có thể vẫn pass.

Cách kiểm chứng: **tạm gỡ `->middleware('throttle:login')` khỏi route, chạy lại test, phải
thấy đỏ.** Rồi khôi phục. Không làm bước này thì không biết test có thật sự bắt lỗi hay không.

### 11.5. Kiểm tra thủ công bằng HTTP

```bash
# Phải thấy 5 lần 401 rồi tới 429
for i in $(seq 1 7); do
  curl -s -o /dev/null -w "lần $i → %{http_code}\n" \
    -X POST http://localhost:8000/api/login \
    -H "Content-Type: application/json" -H "Accept: application/json" \
    -d '{"email":"admin@clinic.test","password":"sai"}'
done

# Xem header của lần bị chặn
curl -si -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@clinic.test","password":"sai"}' \
  | grep -iE "HTTP/|retry-after|x-ratelimit"
```

Thêm 2 request vào `postman_collection.json`, thư mục `04 — Case lỗi`:

- `429 — Đăng nhập sai quá nhiều lần`
- `429 — Vượt giới hạn /api/stats`

### 11.6. Debug khi gặp 429 bất ngờ

Ai đó báo "tôi bị chặn mà không hiểu vì sao". Không thể mở bảng `cache` ra nhìn rồi đoán, vì
key đã bị băm. Đây là cách tra.

#### Vì sao không nhìn thấy gì trong bảng `cache`

Key thật lưu trong DB được ghép từ hai phần:

```
clinic-cache-  +  md5('login' . 'admin@clinic.test|172.18.0.1')
     ↑                 ↑            ↑
cache.prefix    tên limiter    giá trị ->by()
```

Nên `SELECT * FROM cache` chỉ ra một đống chuỗi hex vô nghĩa.

#### Cách 1 — Tự tính lại key

```bash
docker compose exec app php artisan tinker
```

```php
use Illuminate\Support\Facades\RateLimiter;

// Ghép đúng công thức trong ThrottleRequests::handleRequestUsingNamedLimiter
$key = md5('login'.'admin@clinic.test|172.18.0.1');

RateLimiter::attempts($key);      // đã dùng bao nhiêu lần
RateLimiter::remaining($key, 5);  // còn lại bao nhiêu (5 = maxAttempts)
RateLimiter::availableIn($key);   // còn bao nhiêu giây nữa thì hết chặn

RateLimiter::clear($key);         // gỡ chặn ngay cho người dùng này
```

`RateLimiter::clear($key)` là cách xử lý khi cần **gỡ chặn khẩn cấp** cho một nhân viên đang
tiếp bệnh nhân. Đừng xoá cả bảng `cache` chỉ để gỡ một người.

#### Cách 2 — Tắt băm key khi debug ở local

Laravel có sẵn công tắc:

```php
// AppServiceProvider::boot() — CHỈ dùng ở local
if (app()->environment('local')) {
    ThrottleRequests::shouldHashKeys(false);
}
```

Key trong cache trở thành chuỗi đọc được:

```
login:admin@clinic.test|172.18.0.1
```

Lúc đó tra thẳng bằng SQL:

```sql
SELECT key, value, to_timestamp(expiration) AS het_han
FROM cache
WHERE key LIKE '%login%';
```

**Không bật ở production.** Key chưa băm sẽ ghi thẳng email người dùng vào bảng `cache` —
vừa lộ dữ liệu cá nhân, vừa khiến độ dài key phụ thuộc dữ liệu đầu vào.

#### Cách 3 — Đọc log

`bootstrap/app.php` ghi log warning cho mọi client error kèm ngữ cảnh request, nên 429 tự động
có mặt trong log với đầy đủ thông tin:

```bash
docker compose exec app tail -100 storage/logs/laravel.log | grep -A5 "429"
```

Mỗi dòng có sẵn `method`, `url`, `user_id`, `ip` — đủ để trả lời "ai bị chặn, ở endpoint nào".

#### Danh sách câu hỏi khi điều tra

| Câu hỏi | Cách trả lời | Kết luận |
| --- | --- | --- |
| Bị chặn ở limiter nào? | Xem `url` trong log, đối chiếu route | `login` hay `api` hay `payment` |
| Đếm chung sổ với ai? | Xem định nghĩa `->by()` của limiter đó | Nếu là IP thì nghi ngờ chặn nhầm do dùng chung IP |
| Còn bao lâu? | `RateLimiter::availableIn($key)` | Nếu luôn gần bằng window thì họ đang bấm lại liên tục |
| Rải nhiều endpoint hay dồn một chỗ? | Gom log theo `url` | Nhiều endpoint = người thật; một endpoint = script |
| Nhịp có đều không? | Xem khoảng cách timestamp | Đều tăm tắp = máy |

Bốn câu đầu trả lời được trong vài phút, và thường lộ ra ngay đó là chặn nhầm hay chặn đúng.

### 11.7. Test đồng thời

**Nói thẳng trước: PHPUnit không test được tính đồng thời thật.** Nó chạy một tiến trình, một
luồng, tuần tự. Một "test đồng thời" viết bằng PHPUnit chỉ là gọi 10 lần liên tiếp — không
chứng minh được gì về race condition.

Có hai cách tiếp cận đúng, dùng cho hai mục đích khác nhau.

#### Cách 1 — Suy luận từ mã nguồn (đủ cho phần lớn trường hợp)

Tính đúng đắn khi đồng thời **không đến từ test mà đến từ cơ chế khoá**. Ta đã kiểm chứng ở
[mục 9.8](#98-chi-phí-và-hành-vi-đồng-thời-của-chính-rate-limiter): `DatabaseStore` dùng
`transaction()` + `lockForUpdate()`, nên hai request không thể cùng đọc bộ đếm cũ.

Đây là lập luận mạnh hơn một test đồng thời viết cẩu thả, vì nó đúng với **mọi** mức độ đồng
thời chứ không phải chỉ với con số ta tình cờ thử.

#### Cách 2 — Test thật bằng HTTP song song

Khi muốn bằng chứng thực nghiệm, chạy ngoài PHPUnit, nhắm vào container đang chạy:

```bash
# Bắn 20 request song song (10 tiến trình cùng lúc) vào login với giới hạn 5/phút.
# Kỳ vọng: đúng 5 lần 401 và 15 lần 429 — không hơn không kém.
seq 1 20 | xargs -P 10 -I {} sh -c '
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://localhost:8000/api/login \
    -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"email\":\"admin@clinic.test\",\"password\":\"sai\"}"
' | sort | uniq -c
```

Kết quả đúng:

```
      5 401
     15 429
```

**Nếu thấy 6 hoặc 7 lần 401 thì bộ đếm bị mất lần đếm** — tức thao tác không nguyên tử, và đó
là lỗi nghiêm trọng cần điều tra ngay.

`-P 10` là số tiến trình chạy song song. Tăng lên `-P 50` để ép mạnh hơn.

#### Cách 3 — Test được phần kiểm tra được bằng PHPUnit

Cái PHPUnit **làm tốt** là kiểm tra ranh giới đếm chính xác, dù tuần tự:

```php
public function test_the_sixth_attempt_is_the_first_to_be_blocked(): void
{
    $payload = ['email' => 'admin@clinic.test', 'password' => 'wrong'];

    // Đúng 5 lần đầu phải qua được — nếu chặn ở lần 5 là off-by-one.
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/login', $payload)
            ->assertUnauthorized();
    }

    $this->postJson('/api/login', $payload)
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
}
```

Lỗi off-by-one (chặn ở lần thứ 5 thay vì thứ 6) là lỗi phổ biến nhất khi tự cài rate limiting,
và test tuần tự bắt được nó.

**Tóm lại:** PHPUnit lo ranh giới và ngữ nghĩa; `xargs -P` lo đồng thời; đọc mã nguồn lo tính
nguyên tử. Ba thứ khác nhau, đừng dùng cái này để chứng minh cái kia.

---

## 12. Monitoring và điều chỉnh

Con số đặt lúc triển khai là **giả thuyết**, không phải kết luận. Phải đo rồi sửa.

### 12.1. Chỉ số cần theo dõi

| Chỉ số | Lấy từ đâu | Nói lên điều gì |
| --- | --- | --- |
| **Số request/phút mỗi user** | Log ứng dụng theo `user_id` | Trần hợp lệ thật — thay thế ước lượng 62 |
| **Tỉ lệ 429** theo endpoint | Đếm response 429 | Chỉ số quan trọng nhất, xem 12.2 |
| **Tỉ lệ chặn nhầm** | Số 429 rơi vào user hợp lệ | Nếu > 0 thì limit đang quá thấp |
| **Độ trễ p95/p99** | Đo theo endpoint | Rate limit phải làm giảm đuôi trễ, không tăng |
| **CPU** | Docker stats / host | Đỉnh CPU tương quan với đợt login là dấu hiệu bị dò mật khẩu |
| **Bộ nhớ** | Docker stats | Bảng `cache` phình bất thường = nhiều key rate limit = có tấn công |
| **Tải database** | `pg_stat_statements` | `CACHE_STORE=database` nên rate limit tự nó cũng tạo query |
| **Số key rate limit** | `SELECT count(*) FROM cache WHERE key LIKE '%login%'` | Tăng đột biến = tấn công phân tán |

Project đã có `activity_logs` và ghi log warning cho mọi client error trong `bootstrap/app.php`,
nên **429 sẽ tự động vào log** với đầy đủ ngữ cảnh request (method, url, user_id, ip). Không
cần dựng hạ tầng log mới.

### 12.2. Đọc tỉ lệ 429

Đây là chỉ số quan trọng nhất, và nó **hai chiều**:

| Tỉ lệ 429 | Nghĩa là | Hành động |
| --- | --- | --- |
| **0% suốt nhiều tuần** | Limit quá cao, không bảo vệ gì cả — hoặc chưa ai tấn công | Hạ dần cho tới khi chạm mức thật, hoặc chấp nhận nó chỉ là lưới an toàn |
| **< 0,1%, tập trung ở một vài IP lạ** | **Đang hoạt động đúng** — chặn được lạm dụng | Không làm gì |
| **> 1%, rải đều trên user hợp lệ** | **Limit quá thấp**, đang cản trở công việc | Nâng ngay |
| **Tăng đột ngột trên `/api/login`** | Đang bị dò mật khẩu | Điều tra IP, cân nhắc chặn ở tầng WAF |

**Điểm mấu chốt: 0% không phải là thành công.** Nó chỉ có nghĩa là chưa biết ngưỡng nằm ở đâu.

### 12.3. Phân biệt chặn nhầm và chặn đúng

Khi thấy 429, đọc theo thứ tự:

1. **User đó là ai?** Tài khoản nhân viên thật hay không xác thực?
2. **Endpoint nào?** Rải nhiều endpoint (giống người dùng thật) hay dồn một endpoint (giống script)?
3. **Nhịp độ thế nào?** Đều tăm tắp = máy. Lúc dồn lúc thưa = người.
4. **User-Agent?** Trình duyệt thật hay curl/python-requests?

Nhân viên thật + nhiều endpoint + nhịp không đều → **chặn nhầm, phải nâng limit**.

### 12.4. Quy trình

```
Deploy → Monitor (2 tuần) → Analyze → Adjust → Load test → Deploy lại
```

**1. Deploy** — bật limit với con số ở [mục 8](#8-bảng-đề-xuất-cho-từng-api).

*Cân nhắc:* có thể chạy "shadow mode" trước — ghi log lần vượt ngưỡng nhưng **không chặn**,
trong 1 tuần. An toàn hơn nhiều nếu số liệu ước lượng sai. Laravel không hỗ trợ sẵn, phải tự
viết middleware ghi log dùng `RateLimiter::tooManyAttempts()` mà không ném exception.

**2. Monitor — 2 tuần.** Đủ để phủ hết chu kỳ làm việc: ngày thường, cuối tuần, ngày đông bệnh.
Ngắn hơn thì dễ chốt số dựa trên một tuần bất thường.

**3. Analyze** — trả lời hai câu:
- p99 của request/phút mỗi user là bao nhiêu?
- Có 429 nào rơi vào user hợp lệ không?

**4. Adjust:**

```
limit mới = p99 thực tế × 1,5
```

Đổi một limiter mỗi lần, chờ một tuần rồi mới đổi cái tiếp theo. Đổi nhiều thứ cùng lúc thì
không biết cái nào gây ra thay đổi.

**5. Load test** trước khi deploy lại — chạy 4 bài ở [mục 8.4](#84-giả-định-và-điều-kiện-xem-lại),
đặc biệt là bài "kiểm tra chặn nhầm".

**6. Deploy lại** và lặp.

### 12.5. Khi nào cần xem lại ngoài chu kỳ

- Frontend thêm polling hoặc gỡ debounce → tính lại trần hợp lệ.
- Có tích hợp máy-gọi-máy → cần token và limiter riêng, không dùng chung với người dùng.
- Dữ liệu tăng đáng kể → đo lại `/api/stats`, cân nhắc cache thay vì siết limit.
- Chuyển sang chạy nhiều container app → xác nhận `CACHE_STORE` vẫn là store dùng chung
  (`database` hoặc `redis`, **không phải `file` hay `array`**), nếu không mỗi container sẽ đếm
  sổ riêng và limit thực tế bị nhân lên theo số container.

---

## 13. Phụ lục: dữ liệu đo được

Toàn bộ số liệu trong tài liệu này đo trên môi trường Docker local, ngày 28/08/2026, với dữ
liệu demo (20 bệnh nhân, 30 lịch hẹn, 18 thuốc, 1 người dùng đồng thời).

### Độ trễ theo endpoint

Trung bình 5 lần gọi, đã xác thực:

| Endpoint | Trung bình |
| --- | --- |
| `POST /api/login` | **226 ms** |
| `GET /api/invoices` | 35 ms |
| `GET /api/appointments` | 33 ms |
| `GET /api/prescriptions` | 32 ms |
| `GET /api/patients` | 27 ms |
| `GET /api/medicines` | 25 ms |
| `GET /api/stats` | 25 ms |
| `GET /api/me` | 24 ms |
| `GET /api/patients?q=Nguyen` | 23 ms |
| `GET /api/medicines/low-stock` | 23 ms |

Kết luận rút ra: **login đắt gấp ~9 lần một request đọc**, và mọi endpoint đọc gần như bằng
nhau. Chênh lệch giữa các endpoint đọc nhỏ hơn nhiễu đo.

### Không có giới hạn (trước khi triển khai)

```
20 lần đăng nhập sai liên tiếp:
Status: 401 × 20
Thời gian: 4,56 giây  →  4,4 lần/giây  →  ~380.000 lần/ngày
```

### Lưu lượng frontend

| Nguồn | Số request |
| --- | --- |
| Polling nền | không có |
| Mở trang lịch hẹn | ~3 |
| Xem theo tuần | **7 song song** |
| Tìm một bệnh nhân | ~3-4 (debounce 350 ms) |

### Tồn tại hệ thống

| | |
| --- | --- |
| Tổng số route API | 58 |
| Laravel | 13.23.0 |
| `CACHE_STORE` | `database` |
| `BCRYPT_ROUNDS` | 12 |
| Bảng `cache` | đã tồn tại |
| Limiter đã khai | không có |

---

## 14. Nâng cao

Phần này nằm ngoài phạm vi task hiện tại, nhưng là thứ cần biết để áp dụng rate limiting cho
những hệ thống khác.

### 14.1. Idempotency key — cách đúng để retry một POST

[Mục 9.7](#97-phía-client) nói "đừng tự thử lại POST". Đúng, nhưng cụt. Câu hỏi thật là: *làm
sao để POST retry được an toàn?*

**Idempotent** nghĩa là gọi nhiều lần cho cùng kết quả như gọi một lần. `GET` idempotent tự
nhiên. `POST /api/appointments` thì không — gọi hai lần tạo hai lịch hẹn.

Cách biến POST thành idempotent: **client sinh một khoá duy nhất và gửi kèm**.

```
POST /api/payments/{id}/capture
Idempotency-Key: 8f14e45f-ea3b-4c2a-9e77-1d2b3c4d5e6f
```

Server:

1. Nhận request, tra khoá đó trong cache/DB.
2. **Chưa từng thấy** → xử lý bình thường, lưu lại `(khoá → response, status)`.
3. **Đã thấy rồi** → trả về **đúng response đã lưu**, không xử lý lại.

Client retry bao nhiêu lần cũng chỉ tạo ra một giao dịch.

**Vì sao nó liên quan trực tiếp tới project này:** `POST /api/payments/{payment}/capture` là
endpoint chuyển tiền thật. Nếu mạng chập chờn và client gửi lại, hậu quả là capture hai lần.

Hiện tại project **đã tự bảo vệ** bằng cách khác — `PaymentService::capture()` khoá bản ghi rồi
trả về sớm nếu payment đã ở trạng thái `completed` hoặc `cancelled`:

```php
if (in_array($lockedPayment->status, [
    Payment::STATUS_COMPLETED,
    Payment::STATUS_CANCELLED,
], true)) {
    return $lockedPayment;
}
```

Đây là **idempotency dựa trên trạng thái tài nguyên** thay vì dựa trên khoá client gửi lên. Với
một endpoint có trạng thái rõ ràng như capture thì cách này đơn giản hơn và đủ tốt. Idempotency
key cần thiết khi thao tác **không có trạng thái để bám vào** — ví dụ "tạo lịch hẹn mới", nơi
không có cách nào phân biệt "gửi lại do lỗi mạng" với "cố ý đặt hai lịch giống nhau".

Stripe, PayPal và hầu hết API thanh toán đều yêu cầu idempotency key vì lý do này.

### 14.2. Rate limiting ở nhiều tầng

Rate limiting của Laravel là **một tầng**, và là tầng trong cùng. Hệ thống thật thường có nhiều
tầng, mỗi tầng chặn thứ mà tầng trong không chặn được:

| Tầng | Công cụ | Chặn được | Không chặn được |
| --- | --- | --- | --- |
| CDN / WAF | Cloudflare, AWS WAF | Botnet phân tán, IP xấu đã biết, tấn công theo mẫu | Lạm dụng từ tài khoản hợp lệ |
| Load balancer / proxy | nginx `limit_req` | Ồ ạt theo IP, trước khi tốn tiến trình PHP | Không biết user là ai |
| **Ứng dụng** | **Laravel `throttle`** | **Theo user, theo endpoint, theo nghiệp vụ** | **Tấn công phân tán** |
| Database | `max_connections`, statement timeout | Truy vấn chạy loạn | Mọi thứ phía trên |

Ví dụ tầng nginx — chặn trước khi request kịp vào PHP:

```nginx
# 10 request/giây mỗi IP, cho phép dồn 20, không làm chậm mà từ chối luôn
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

location /api/ {
    limit_req zone=api burst=20 nodelay;
    limit_req_status 429;
}
```

**Nguyên tắc chọn tầng:** chặn càng sớm càng rẻ, nhưng càng sớm càng **ít biết ngữ cảnh**.
nginx không biết user nào đang gọi; Laravel biết nhưng đã tốn một vòng bootstrap. Nên dùng cả
hai: nginx chặn ồ ạt thô bạo, Laravel chặn theo nghiệp vụ.

### 14.3. Quota khác rate limit

Hai khái niệm hay bị lẫn:

| | Rate limit | Quota |
| --- | --- | --- |
| Mục đích | Bảo vệ hệ thống | Tính tiền / phân hạng dịch vụ |
| Chu kỳ | Giây, phút | Ngày, tháng |
| Vượt thì sao | 429, chờ chút là được lại | 402/403, phải nâng gói hoặc chờ hết tháng |
| Ví dụ | 120 request/phút | 100.000 request/tháng, gói Pro |

Hệ thống nội bộ như phòng khám **chỉ cần rate limit**. Quota xuất hiện khi API được bán ra
ngoài. Đừng cài quota khi chưa có mô hình kinh doanh cần tới nó.

### 14.4. Circuit breaker và load shedding

Hai kỹ thuật họ hàng, giải quyết vấn đề khác:

**Circuit breaker** — bảo vệ *mình* khỏi *dịch vụ bên ngoài đang hỏng*. Nếu PayPal timeout 10
lần liên tiếp, "ngắt cầu dao": trong 60 giây tới, từ chối ngay mọi request tới PayPal mà không
cần thử. Tránh việc mỗi request treo 30 giây rồi mới lỗi, làm cạn tiến trình PHP.

**Đây là thứ project này đang thiếu** cho các endpoint gọi PayPal. Rate limiting bảo vệ ta khỏi
người dùng gọi quá nhiều; circuit breaker bảo vệ ta khi PayPal chết.

**Load shedding** — khi hệ thống quá tải, **chủ động vứt bỏ** một phần request thay vì để mọi
request cùng chậm dần rồi timeout hết. Ưu tiên giữ lại request quan trọng (thanh toán) và bỏ
request ít quan trọng (thống kê).

Ba kỹ thuật trả lời ba câu khác nhau:

| Kỹ thuật | Câu hỏi |
| --- | --- |
| Rate limiting | *Bên gọi này có gọi quá nhiều không?* |
| Circuit breaker | *Dịch vụ tôi phụ thuộc có còn sống không?* |
| Load shedding | *Tôi có đang quá tải không, và nên bỏ cái gì?* |

### 14.5. Chuẩn header

`X-RateLimit-*` là **quy ước, không phải chuẩn chính thức**. Tiền tố `X-` cho biết đây là header
phi tiêu chuẩn. Mỗi nhà cung cấp đặt tên hơi khác nhau — GitHub, Twitter, Stripe đều có biến thể riêng.

Có một bản dự thảo IETF (`RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset`, không có
tiền tố `X-`) nhưng chưa phổ biến. Laravel dùng dạng `X-`.

Riêng **`Retry-After` là chuẩn thật** (RFC 9110). Nó nhận hai dạng:

```
Retry-After: 120                                  ← số giây
Retry-After: Wed, 21 Oct 2026 07:28:00 GMT        ← mốc thời gian HTTP
```

Laravel luôn gửi dạng số giây. Client tự viết phải xử lý được cả hai nếu gọi API bên ngoài.

### 14.6. Khi chuyển sang nhiều container

Điều **bắt buộc kiểm tra** nếu sau này chạy nhiều bản ứng dụng sau load balancer:

`CACHE_STORE` phải là store **dùng chung** — `database` hoặc `redis`. Nếu là `file` hoặc
`array`, mỗi container đếm sổ riêng, và giới hạn thực tế bị **nhân lên theo số container**:
3 container × 5/phút = 15/phút thật, trong khi ta tưởng là 5.

Đây là lỗi im lặng — không có thông báo nào, chỉ là rate limiting không còn hiệu lực như thiết
kế. Project hiện dùng `database` nên an toàn.

---

## 15. Lộ trình vừa học vừa làm

Bảy bài, mỗi bài có việc làm và cách tự kiểm chứng. Làm tuần tự; mỗi bài dựa trên bài trước.

### Bài 1 — Nhìn thấy vấn đề trước khi giải

**Làm:** chạy 20 lần đăng nhập sai, đo thời gian.

```bash
time (for i in $(seq 1 20); do
  curl -s -o /dev/null -w "%{http_code} " -X POST http://localhost:8000/api/login \
    -H "Content-Type: application/json" -H "Accept: application/json" \
    -d '{"email":"admin@clinic.test","password":"sai"}'
done)
```

**Kiểm chứng:** 20 lần 401, không có 429. Tính ra số lần thử/ngày.

**Học được:** rate limiting giải quyết vấn đề gì — bằng con số của chính mình, không phải bằng
lý thuyết đọc được.

### Bài 2 — Limiter đầu tiên, một lớp

**Làm:** khai `RateLimiter::for('login', ...)` với `Limit::perMinute(5)->by($request->ip())`
(cố ý dùng IP để thấy nhược điểm ở bài 4), gắn `throttle:login` vào route.

**Kiểm chứng:** chạy lại lệnh bài 1 — phải thấy 5 lần 401 rồi 15 lần 429.

**Học được:** vòng đời limiter → middleware → response.

### Bài 3 — Đọc header

**Làm:** không sửa code, chỉ quan sát.

```bash
curl -si -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@clinic.test","password":"sai"}' \
  | grep -iE "HTTP/|retry-after|x-ratelimit"
```

Gọi lặp lại, quan sát `X-RateLimit-Remaining` giảm dần và `Retry-After` **giảm dần chứ không cố định**.

**Kiểm chứng:** đối chiếu với bảng timeline ở [mục 0.4](#04-bộ-đếm-fixed-window-chạy-thế-nào).

**Học được:** fixed window hành xử thế nào trong thực tế, và vì sao cửa sổ tính từ request đầu tiên.

### Bài 4 — Tự tay gây ra lỗi chọn key

**Làm:** giữ nguyên key theo IP. Đăng nhập sai 5 lần với `admin@clinic.test`, rồi thử đăng nhập
**đúng** bằng `doctor@clinic.test`.

**Kiểm chứng:** tài khoản thứ hai cũng bị 429 dù chưa hề gõ sai lần nào.

**Học được:** đây chính là kịch bản "một người khoá cả phòng khám". Sửa key thành
`Str::lower($email).'|'.$request->ip()`, lặp lại, xác nhận tài khoản thứ hai vào được.

Bài này quan trọng nhất trong bảy bài — nó biến một lời khuyên trừu tượng thành thứ tự tay gây ra và tự tay sửa.

### Bài 5 — Lớp thứ hai chống credential stuffing

**Làm:** với key `email+IP` của bài 4, thử 6 email khác nhau, mỗi email sai 1 lần.

**Kiểm chứng:** **không lần nào bị chặn** — vì mỗi email chỉ đếm 1. Đây là lỗ hổng credential
stuffing, tự tay nhìn thấy.

Thêm `Limit::perMinute(20)->by($request->ip())` vào mảng trả về, lặp lại với 21 email khác nhau.

**Kiểm chứng:** lần thứ 21 bị chặn.

**Học được:** vì sao một `Limit` không đủ, và cách nhiều `Limit` bổ khuyết nhau.

### Bài 6 — Test và negative control

**Làm:** viết test cho các case ở [mục 11.2](#112-các-trường-hợp-cần-phủ). Rồi **tạm gỡ**
`->middleware('throttle:login')` khỏi route và chạy lại.

**Kiểm chứng:** test phải **đỏ**. Nếu vẫn xanh thì test không kiểm tra thứ mình tưởng — sửa test
trước khi khôi phục route.

**Học được:** thói quen negative control. Một test chưa từng thấy đỏ là một test chưa được chứng minh.

### Bài 7 — Đồng thời

**Làm:** chạy lệnh `xargs -P 10` ở [mục 11.7](#117-test-đồng-thời).

**Kiểm chứng:** đúng 5 lần 401 và 15 lần 429. Rồi mở
`vendor/laravel/framework/src/Illuminate/Cache/DatabaseStore.php` đọc `incrementOrDecrement()`,
tìm ra `lockForUpdate()` và tự giải thích được **vì sao** kết quả đúng.

**Học được:** tính đúng đắn khi đồng thời đến từ cơ chế khoá, không đến từ test. Test chỉ xác nhận.

### Sau bảy bài

Bạn sẽ trả lời được năm câu mà mentor có thể hỏi:

1. *Vì sao chọn con số đó?* → [mục 5](#5-quy-trình-xác-định-limit) và [8.1](#81-cơ-sở-tính-toán-cho-nhóm-đọc)
2. *Vì sao khoá theo user id chứ không theo IP?* → tự tay gây ra lỗi ở bài 4
3. *Laravel dùng thuật toán gì, nhược điểm là gì?* → [mục 0.3](#03-bốn-thuật-toán-rate-limiting), [0.4](#04-bộ-đếm-fixed-window-chạy-thế-nào)
4. *Nhiều request cùng lúc có đếm sai không?* → bài 7
5. *Rate limiting có đủ để bảo mật không?* → [mục 10](#10-rate-limiting-không-thay-thế-được-gì)

### Phạm vi cho PR đầu tiên

Học sâu xong thường sinh ra ham muốn dùng hết những gì vừa học. Đừng. Reviewer đánh giá cao
một PR nhỏ, đúng, có lý do rõ ràng hơn nhiều so với một PR phô diễn kỹ thuật.

**Thuộc phạm vi:**

- Bật `throttleApi()` với limiter `api` khoá theo `user id`
- Limiter `login` hai lớp
- Limiter `sensitive` và `payment` theo [bảng mục 8](#83-bảng-chi-tiết)
- Test: vượt ngưỡng ra 429, dưới ngưỡng vẫn đi qua, và một negative control
- Ghi lại **lý do chọn từng con số** — đây mới là phần được đọc kỹ nhất

**Để lại cho sau, và nói rõ trong mô tả PR là đã cân nhắc:**

- Sliding window tự cài đặt — [0.3](#03-bốn-thuật-toán-rate-limiting) đã giải thích khi nào mới cần
- Limit động theo role — [9.6](#96-limit-động-theo-role-chưa-làm-ở-giai-đoạn-1)
- Idempotency key, circuit breaker — [14](#14-nâng-cao)
- Đổi `CACHE_STORE` sang Redis: là thay đổi hạ tầng, tách PR riêng

**Không được im lặng bỏ qua:** cấu hình trusted proxies. Nếu chưa xử lý được vì còn phụ thuộc
môi trường deploy, hãy viết một dòng trong mô tả PR: *"limiter theo IP chỉ chính xác sau khi
khai báo trusted proxies theo hạ tầng thật — cần xác nhận với team vận hành."* Nêu ra một vấn
đề chưa giải quyết vẫn tốt hơn nhiều so với không nhắc tới nó.

### Áp dụng cho task sau

Gặp một API mới cần rate limiting, chạy lại đúng quy trình [mục 5](#5-quy-trình-xác-định-limit):

```
Đặc điểm API → Chi phí tài nguyên → Rủi ro nghiệp vụ → Lưu lượng hợp lệ
             → Rủi ro bị lạm dụng → limit / window / key
```

Bước tốn công nhất luôn là **"lưu lượng hợp lệ"** — phải đo, không được đoán. Bước quan trọng
nhất luôn là **key**. Hai bước còn lại thường suy ra được nhanh.

---

## Tóm tắt

| Limiter | Limit | Window | Key | Áp cho |
| --- | --- | --- | --- | --- |
| `login` | 5 | 1 phút | `email + IP` | `POST /api/login` |
| `login` | 20 | 1 phút | `IP` | `POST /api/login` (lớp 2) |
| `api` | 120 | 1 phút | `user id` → IP | Mọi route đã xác thực |
| `sensitive` | 20 | 1 phút | `user id` | `GET /api/stats` |
| `payment` | 10 | 1 phút | `user id` | 3 endpoint gọi PayPal |

**Ba điều cần nhớ:**

1. Các con số này là **giả thuyết dựa trên dữ liệu demo**, phải đo lại ở production.
2. **Key quan trọng hơn limit.** Khoá theo IP cho hệ thống nội bộ dùng chung một IP public là
   sai lầm nghiêm trọng hơn bất kỳ con số nào.
3. **Tỉ lệ 429 bằng 0 không phải thành công** — nó chỉ có nghĩa là chưa biết ngưỡng ở đâu.
