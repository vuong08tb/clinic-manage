# T4.10 — Queue job nhắc lịch khám + Notification

## 1. Trạng thái tài liệu

- Trạng thái: **CHƯA TRIỂN KHAI — chờ review**.
- Nguồn hướng dẫn bắt buộc: `skills/backend.md` (mục 1 phân lớp, mục 8 activity log,
  mục 11 feature test, mục 13 comment tiếng Anh), `skills/database.md` (mục 11 `timestamptz`),
  `skills/docker.md` (mục 9 timezone UTC).
- Nguồn yêu cầu: `docs/de-bai-thuc-tap-clinic-api.xlsx`, sheet **"10. Chấm + Checklist"**,
  mục điểm cộng *"Queue job (ví dụ log giả lập gửi thông báo nhắc lịch khám)"*.
- Phạm vi: thêm 1 cột + 1 partial index, 1 command, 1 job, 1 notification, 1 channel,
  2 service docker, 1 file test. Không đụng API hiện có, không đổi response envelope.
- Mức triển khai đã chốt: **mức đầy đủ** (có chống gửi trùng, có scheduler, có activity log).

Luồng xử lý:

```text
scheduler container (php artisan schedule:work)
  └─> appointments:send-reminders            Command — chỉ TÌM, không gửi
        └─> dispatch SendAppointmentReminder Job — ShouldQueue
              |
worker container (php artisan queue:work)
              └─> handle()
                    ├─> claim: UPDATE ... WHERE reminder_sent_at IS NULL
                    ├─> nếu claim được 0 dòng -> thoát, ai đó đã gửi
                    ├─> $patient->notifyNow(new AppointmentReminder(...))
                    │        └─> via() = [ReminderLogChannel::class]
                    │              └─> Log::channel('reminders')
                    └─> ActivityLogger: appointment / reminder_sent
```

---

## 2. Các quyết định đã chốt

| # | Vấn đề | Quyết định | Lý do |
|---|---|---|---|
| 1 | Ai nhận thông báo | Chỉ `Patient`, thêm trait `Notifiable` | Patient đã có `email`/`phone`; thêm doctor sau chỉ là một dòng trong command |
| 2 | Chống gửi trùng | Cột `appointments.reminder_sent_at` + partial index | Xem mục 4.2 |
| 3 | Nhắc trước bao lâu | 24 giờ, đọc từ `config('clinic.reminder_lead_hours')` | Theo tiền lệ `clinic.examination_fee` |
| 4 | Status được nhắc | `scheduled` và `confirmed` | `cancelled` / `completed` mà nhắc là sai nghiệp vụ |
| 5 | Job nhận gì | Model + trait `Queueable` (đã gồm `SerializesModels`) | Xem mục 7.2 |
| 6 | Interface riêng | **Không** | Xem mục 3 |
| 7 | Nơi chạy | Thêm service `worker` và `scheduler` vào `docker-compose.yml` | Không có worker thì job nằm im trong bảng `jobs` |
| 8 | Notification có `ShouldQueue` không | **Không** — chỉ Job mới queue | Xem mục 7.3 |

---

## 3. Vì sao không viết interface riêng

Câu hỏi tự nhiên khi thêm notification: *"có nên tạo `NotificationSenderInterface` để sau
này đổi từ log sang mail/SMS không?"* Câu trả lời là **không**, và lý do đáng nhớ:

1. **Laravel Notification đã chính là lớp trừu tượng đó.** Điểm mở rộng của nó là method
   `via()` trả về danh sách channel. Đổi từ log sang mail = sửa `via()`, không cần một
   interface của riêng mình chồng lên trên.
2. **Lý do phổ biến nhất để tạo interface là để thay thế được trong test — Laravel đã cho
   sẵn seam đó**: `Notification::fake()`, `Queue::fake()`, `Bus::fake()`. Viết interface
   chỉ để test được là làm lại việc framework đã làm.
3. **YAGNI.** Hiện có đúng một channel và không có kênh thứ hai nào trong kế hoạch. Một
   interface với đúng một implementation là chi phí thuần: dài stack trace, thêm một file
   phải mở khi đọc code, không đổi lại được gì.

**Thứ duy nhất phải tự viết là một custom notification channel.** Đây không phải interface
tự nghĩ ra mà là contract có sẵn của Laravel: bất kỳ class nào có method
`send($notifiable, $notification)` đều dùng làm channel được. Cần viết vì Laravel có sẵn
`mail`, `database`, `broadcast`, `vonage`, `slack` — **không có** channel `log`.

> Nếu người review hỏi "sao không tách interface", câu trả lời là: *framework đã cung cấp
> seam ở tầng channel, thêm một tầng nữa chỉ làm dài stack trace*. Trả lời "để dễ mở rộng
> sau này" sẽ bị vặn tiếp là mở rộng cái gì, khi nào.

---

## 4. Thay đổi 1 — cột đánh dấu và partial index

### 4.1 Migration

File mới: `database/migrations/<timestamp>_add_reminder_sent_at_to_appointments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track when an appointment reminder was delivered.
     *
     * The partial index covers exactly the scan performed by the reminder command:
     * appointments still waiting for a reminder. Rows already reminded are the vast
     * majority over time and would otherwise be dead weight in the index.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestampTz('reminder_sent_at')->nullable();
        });

        DB::statement(
            "CREATE INDEX appointments_pending_reminder_index
             ON appointments (scheduled_at)
             WHERE reminder_sent_at IS NULL AND status IN ('scheduled', 'confirmed')"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS appointments_pending_reminder_index');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
```

`timestampTz` chứ không phải `timestamp`, theo `skills/database.md` mục 11: DB chạy UTC,
cột thời gian thật phải lưu mốc tuyệt đối.

Index viết bằng `DB::statement` vì schema builder không diễn đạt được mệnh đề `WHERE` trên
index — giống hệt cách migration T4.6 `appointments_active_schedule_index` đã làm.

### 4.2 Vì sao cần cột này

Không có nó thì mỗi lần worker chạy lại, hoặc job bị retry sau khi gửi xong nhưng chết ở
bước sau, bệnh nhân nhận thông báo lần hai. Queue là **at-least-once**: Laravel đảm bảo
job chạy *ít nhất* một lần, không đảm bảo *đúng* một lần. Muốn đúng một lần thì bản thân
job phải tự làm mình idempotent — và cột đánh dấu là cách rẻ nhất.

### 4.3 Cột này không nằm trong `#[Fillable]`

Cố ý. `reminder_sent_at` là trạng thái nội bộ của hệ thống gửi tin, không phải dữ liệu
client được đặt. Để ngoài `Fillable` thì không API nào ghi vào nó được, kể cả khi ai đó
lỡ thêm field này vào một FormRequest.

Cần thêm cast trong `app/Models/Appointment.php`:

```php
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }
```

---

## 5. Thay đổi 2 — cấu hình

### 5.1 `config/clinic.php`

Thêm vào mảng trả về:

```php
    // How far ahead of an appointment its reminder is sent, in hours.
    'reminder_lead_hours' => (int) env('REMINDER_LEAD_HOURS', 24),
```

### 5.2 `.env.example`

Thêm một dòng, đặt cạnh `EXAMINATION_FEE`:

```dotenv
REMINDER_LEAD_HOURS=24
```

### 5.3 `config/logging.php`

Thêm channel `reminders` vào mảng `channels`, đặt ngay sau `daily`:

```php
        'reminders' => [
            'driver' => 'daily',
            'path' => storage_path('logs/reminders.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],
```

File log riêng chứ không trộn vào `laravel.log`: thông báo nhắc lịch là dữ liệu nghiệp
vụ, còn `laravel.log` là nơi đọc khi có sự cố. Trộn chung thì mỗi lần điều tra lỗi phải
lội qua hàng nghìn dòng nhắc lịch.

### 5.4 `app/Constants/ActivityLogAction.php`

Thêm hằng số:

```php
    public const REMINDER_SENT = 'reminder_sent';
```

---

## 6. Thay đổi 3 — Notification và Channel

### 6.1 `app/Models/Patient.php`

Thêm trait `Notifiable`:

```php
use Illuminate\Notifications\Notifiable;
```

```php
    /** @use HasFactory<PatientFactory> */
    use HasFactory, Notifiable, SoftDeletes;
```

Trait này cấp cho model các method `notify()` và `notifyNow()`. `Patient` không phải
`User` và không đăng nhập được — điều đó hoàn toàn không sao: `Notifiable` chỉ cần model
có khả năng nhận thông báo, không cần nó là một tài khoản.

### 6.2 `app/Notifications/AppointmentReminder.php`

```php
<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\ReminderLogChannel;
use Illuminate\Notifications\Notification;

/**
 * Remind a patient about an upcoming appointment.
 *
 * The notification is intentionally not queued: it is always sent from inside
 * SendAppointmentReminder, which already runs on the queue. Marking it ShouldQueue
 * as well would push a second job for work the worker is already doing.
 */
class AppointmentReminder extends Notification
{
    /**
     * Create a reminder for the given appointment.
     */
    public function __construct(private readonly Appointment $appointment) {}

    /**
     * Deliver the reminder through the simulated log channel.
     *
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [ReminderLogChannel::class];
    }

    /**
     * Build the payload written to the reminder log.
     *
     * @return array<string, mixed>
     */
    public function toLog(mixed $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->getKey(),
            'patient_id' => $this->appointment->patient_id,
            'doctor_id' => $this->appointment->doctor_id,
            'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
            'email' => $notifiable->email,
            'phone' => $notifiable->phone,
        ];
    }
}
```

### 6.3 `app/Notifications/Channels/ReminderLogChannel.php`

```php
<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Simulate reminder delivery by writing it to a dedicated log channel.
 *
 * Laravel discovers a channel by convention, not by interface: any class exposing
 * send($notifiable, $notification) can be returned from a notification's via().
 * That convention is the extension seam, which is why this project adds no
 * interface of its own around notification delivery.
 */
class ReminderLogChannel
{
    /**
     * Write the notification payload to the reminder log.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toLog')) {
            return;
        }

        Log::channel('reminders')->info(
            'Appointment reminder sent',
            $notification->toLog($notifiable),
        );
    }
}
```

---

## 7. Thay đổi 4 — Job

### 7.1 `app/Jobs/SendAppointmentReminder.php`

```php
<?php

namespace App\Jobs;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deliver the reminder for a single appointment.
 *
 * One appointment per job: a delivery failure retries that appointment alone instead
 * of replaying an entire batch.
 */
class SendAppointmentReminder implements ShouldQueue
{
    use Queueable;

    /**
     * Number of attempts before the job is moved to failed_jobs.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    /**
     * Drop the job when the appointment no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a job for the given appointment.
     */
    public function __construct(public readonly Appointment $appointment) {}

    /**
     * Claim the appointment, then send its reminder exactly once.
     */
    public function handle(ActivityLogger $logger): void
    {
        if (! in_array($this->appointment->status, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ], true)) {
            return;
        }

        if ($this->appointment->scheduled_at->isPast()) {
            return;
        }

        // Compare-and-set claim: the WHERE clause makes the update itself the lock, so
        // two workers holding the same appointment cannot both pass this point. A
        // second attempt of this job claims nothing and returns without resending.
        $claimed = Appointment::query()
            ->whereKey($this->appointment->getKey())
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $this->appointment->patient->notifyNow(
            new AppointmentReminder($this->appointment),
        );

        $logger->log(
            ActivityLogSubject::APPOINTMENT,
            (int) $this->appointment->getKey(),
            ActivityLogAction::REMINDER_SENT,
            ['scheduled_at' => $this->appointment->scheduled_at->toIso8601String()],
        );
    }
}
```

### 7.2 Vì sao truyền model chứ không truyền id

Trait `Queueable` của Laravel 11+ đã gộp sẵn `SerializesModels`. Trait đó **không** nhét
cả model vào hàng đợi: khi serialize nó chỉ lưu class name và khoá chính, khi worker lấy
job ra thì **query lại từ DB**. Nghĩa là model trong `handle()` luôn là dữ liệu mới nhất
tại thời điểm chạy, không phải ảnh chụp lúc dispatch.

Hai hệ quả:

- Không cần gọi `fresh()` trong `handle()`.
- Nếu bản ghi đã bị xoá, việc query lại ném `ModelNotFoundException`. Thuộc tính
  `$deleteWhenMissingModels = true` biến tình huống đó thành "bỏ job", thay vì để nó
  retry ba lần rồi rơi vào `failed_jobs`.

Vẫn phải kiểm tra lại `status` trong `handle()` vì job có thể nằm trong hàng đợi vài phút,
đủ để lễ tân huỷ lịch.

### 7.3 Vì sao Notification không đánh dấu `ShouldQueue`

Nếu `AppointmentReminder` cũng `implements ShouldQueue` thì `notify()` sẽ đẩy thêm một job
`SendQueuedNotifications` vào hàng đợi — tức là một job queue để làm việc mà worker đang
làm dở. Hai lớp queue lồng nhau, khó lần khi debug, và mất luôn tính nguyên tử với bước
đóng dấu ở trên.

Trong job dùng `notifyNow()` thay vì `notify()`. Cả hai đều gửi ngay khi notification
không `ShouldQueue`, nhưng `notifyNow()` **nói rõ ý định**: sau này ai đó thêm
`ShouldQueue` vào notification thì `notifyNow()` vẫn gửi thẳng, còn `notify()` sẽ âm thầm
đổi hành vi.

### 7.4 Vì sao dùng compare-and-set thay vì `lockForUpdate`

Ở `UserService` (xem `docs/user-crud.md` mục 4.2) ta phải khoá hàng vì cần **đọc một tập
hợp rồi mới quyết định**. Ở đây điều kiện chỉ liên quan tới **một dòng duy nhất**, nên một
câu `UPDATE ... WHERE reminder_sent_at IS NULL` đã là nguyên tử sẵn: PostgreSQL khoá dòng
đó trong lúc update, và giá trị trả về (số dòng bị ảnh hưởng) cho biết ai là người thắng.

Không cần transaction, không có thứ tự khoá để lo, không có deadlock. Quy tắc rút ra:
**khoá tường minh chỉ cần khi quyết định phụ thuộc vào nhiều dòng.**

Lưu ý phụ: `Appointment::query()->update()` là query builder, **không** kích hoạt model
event nên `AppointmentObserver` không ghi thêm một bản ghi `updated` vào activity log.
Đúng ý muốn — ta tự ghi `reminder_sent` với ngữ nghĩa rõ hơn.

---

## 8. Thay đổi 5 — Command và Scheduler

### 8.1 `app/Console/Commands/SendAppointmentReminders.php`

```php
<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;

/**
 * Queue reminders for every appointment entering the lead window.
 *
 * The command only finds work and hands it to the queue; delivery lives in the job,
 * so one failing appointment never blocks the rest of the scan.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Queue reminder notifications for appointments due within the lead window';

    /**
     * Dispatch one job per appointment still waiting for its reminder.
     */
    public function handle(): int
    {
        $now = now();
        $until = $now->copy()->addHours((int) config('clinic.reminder_lead_hours'));
        $queued = 0;

        Appointment::query()
            ->whereNull('reminder_sent_at')
            ->whereIn('status', [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
            ])
            ->whereBetween('scheduled_at', [$now, $until])
            ->chunkById(100, function ($appointments) use (&$queued): void {
                foreach ($appointments as $appointment) {
                    SendAppointmentReminder::dispatch($appointment);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} appointment reminder(s).");

        return self::SUCCESS;
    }
}
```

`chunkById` chứ không phải `get()`: nếu phòng khám có 50.000 lịch chờ nhắc thì `get()` nạp
hết vào bộ nhớ. `chunkById` phân trang theo khoá chính nên an toàn cả khi hàng đang bị sửa
trong lúc quét.

### 8.2 Không cần "cửa sổ quét"

Thiết kế đơn giản hơn thường thấy: chỉ cần `scheduled_at` nằm giữa **bây giờ** và
**bây giờ + 24h**, không cần tính một cửa sổ hẹp quanh mốc 24h.

Lý do: cột `reminder_sent_at` đã bảo đảm không gửi trùng, nên quét dải rộng là vô hại. Đổi
lại còn được một hành vi đúng hơn: lịch được **tạo mới** khi chỉ còn 3 tiếng nữa vẫn được
nhắc, trong khi thiết kế "cửa sổ hẹp quanh mốc 24h" sẽ bỏ sót hẳn.

### 8.3 `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('appointments:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

`withoutOverlapping()` chặn lần chạy sau khởi động khi lần trước còn đang quét — nếu không
thì hai lần quét chồng nhau sẽ dispatch trùng job (job vẫn không gửi trùng nhờ mục 7.4,
nhưng dispatch thừa là lãng phí).

### 8.4 `docker-compose.yml`

Thêm hai service, dùng chung image với `app`:

```yaml
    worker:
        build:
            context: .
            dockerfile: Dockerfile
        container_name: clinic_worker
        restart: unless-stopped
        working_dir: /var/www
        environment:
            TZ: Asia/Ho_Chi_Minh
        volumes:
            - .:/var/www
            - /var/www/vendor
        depends_on:
            db:
                condition: service_healthy
        command: php artisan queue:work --tries=3 --max-time=3600

    scheduler:
        build:
            context: .
            dockerfile: Dockerfile
        container_name: clinic_scheduler
        restart: unless-stopped
        working_dir: /var/www
        environment:
            TZ: Asia/Ho_Chi_Minh
        volumes:
            - .:/var/www
            - /var/www/vendor
        depends_on:
            db:
                condition: service_healthy
        command: php artisan schedule:work
```

`--max-time=3600` để worker tự thoát sau một giờ và được `restart: unless-stopped` dựng
lại. Đây là cách xử lý rò rỉ bộ nhớ tiêu chuẩn của PHP chạy dài hạn: worker giữ code đã
nạp trong RAM, **code sửa trên host sẽ không có hiệu lực cho tới khi worker khởi động lại**.
Đây là bẫy phổ biến nhất khi lần đầu chạy queue trong Docker.

---

## 9. Cơ chế cần nắm để trả lời review

Bốn câu hỏi có xác suất bị hỏi cao nhất, kèm điều cần nói được:

1. **"Queue đảm bảo job chạy đúng một lần không?"** → Không. `at-least-once`. Worker chết
   sau khi làm xong nhưng trước khi báo hoàn thành thì job chạy lại. Đó là toàn bộ lý do
   tồn tại của `reminder_sent_at`.
2. **"Sao chỗ này không cần `lockForUpdate` như bên `UserService`?"** → Điều kiện chỉ phụ
   thuộc một dòng, nên `UPDATE ... WHERE` đã nguyên tử. Khoá tường minh chỉ cần khi quyết
   định phụ thuộc nhiều dòng.
3. **"Truyền cả model vào job có nặng không?"** → Không. `SerializesModels` chỉ lưu class
   và khoá chính, worker query lại lúc chạy.
4. **"Sửa code xong sao worker vẫn chạy code cũ?"** → Worker nạp code vào RAM một lần.
   Phải `docker compose restart worker`, hoặc chờ `--max-time` hết hạn.

---

## 10. File ảnh hưởng

| File | Thay đổi |
|---|---|
| `database/migrations/<ts>_add_reminder_sent_at_to_appointments_table.php` | **Mới** — mục 4.1 |
| `app/Models/Appointment.php` | Thêm cast `reminder_sent_at` — mục 4.3 |
| `app/Models/Patient.php` | Thêm trait `Notifiable` — mục 6.1 |
| `app/Notifications/AppointmentReminder.php` | **Mới** — mục 6.2 |
| `app/Notifications/Channels/ReminderLogChannel.php` | **Mới** — mục 6.3 |
| `app/Jobs/SendAppointmentReminder.php` | **Mới** — mục 7.1 |
| `app/Console/Commands/SendAppointmentReminders.php` | **Mới** — mục 8.1 |
| `app/Constants/ActivityLogAction.php` | Thêm `REMINDER_SENT` — mục 5.4 |
| `config/clinic.php` | Thêm `reminder_lead_hours` — mục 5.1 |
| `config/logging.php` | Thêm channel `reminders` — mục 5.3 |
| `.env.example` | Thêm `REMINDER_LEAD_HOURS` — mục 5.2 |
| `routes/console.php` | Đăng ký schedule — mục 8.3 |
| `docker-compose.yml` | Thêm `worker` + `scheduler` — mục 8.4 |
| `tests/Feature/AppointmentReminderTest.php` | **Mới** — mục 11 |
| `README.md` | Thêm mục mô tả queue/scheduler — mục 12 |

Không đụng: route API, controller, service hiện có, response envelope, RBAC.

---

## 11. Test

File mới `tests/Feature/AppointmentReminderTest.php`, 5 test:

| Test | Nội dung |
|---|---|
| `test_command_queues_a_job_for_each_due_appointment` | `Queue::fake()`, 2 lịch trong cửa sổ → `assertPushed` 2 lần |
| `test_command_skips_appointments_outside_the_lead_window` | Lịch sau 48h và lịch đã qua → `assertNothingPushed` |
| `test_command_skips_cancelled_completed_and_already_reminded_appointments` | 3 lịch không hợp lệ → `assertNothingPushed` |
| `test_job_sends_the_notification_and_stamps_the_appointment` | `Notification::fake()`, chạy job → `assertSentTo($patient, AppointmentReminder::class)` + `reminder_sent_at` khác null |
| `test_job_does_not_send_twice_when_the_appointment_is_already_stamped` | Chạy job hai lần → `assertSentTimes(AppointmentReminder::class, 1)` |

Test thứ 5 là test quan trọng nhất: nó chính là bằng chứng cho cơ chế ở mục 7.4.

Lệnh chạy:

```bash
php artisan test --filter=AppointmentReminderTest
php artisan test
vendor/bin/pint --test
```

Kiểm tra thủ công trong Docker:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan appointments:send-reminders
docker compose logs -f worker
docker compose exec app tail -f storage/logs/reminders-$(date +%Y-%m-%d).log
```

---

## 12. Việc cần bổ sung vào README

Đề bài chấm cả phần giải thích trong README. Thêm một mục mới sau mục 9 hiện có:

- Sơ đồ luồng ở mục 1 của tài liệu này.
- Vì sao queue `at-least-once` và cột `reminder_sent_at` giải quyết điều gì.
- Cách chạy worker/scheduler và cái bẫy "worker giữ code cũ trong RAM".
- Cách xem log nhắc lịch.

---

## 13. Thứ tự commit đề xuất

| Commit | Nội dung | Message đề xuất |
|---|---|---|
| 1 | Mục 4 + 5 — migration, cast, config, log channel, hằng số | `add reminder tracking column and config` |
| 2 | Mục 6 + 7 — notification, channel, job | `add appointment reminder job and notification` |
| 3 | Mục 8 — command, schedule, docker service | `run appointment reminders from the scheduler` |
| 4 | Mục 11 + 12 — test và README | `test appointment reminders` |

Tách bốn commit vì mỗi bước đều chạy được độc lập: sau commit 1 thì migrate được, sau
commit 2 thì dispatch tay từ tinker được, sau commit 3 mới thành tự động.
