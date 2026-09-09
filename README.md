# Clinic Management REST API

Laravel 13 · PHP 8.3 · PostgreSQL 16 · Docker Compose

1. [Quick Start](#1-quick-start) · 2. [Environment](#2-environment) · 3. [Architecture](#3-architecture) ·
4. [Commands](#4-commands) · 5. [RBAC Global](#5-rbac-global) ·
6. [PayPal & Visa](#6-paypal--visa) · 7. [PostgreSQL](#7-postgresql)

---

## 1. Quick Start

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
npm install && npm run build          # needs Node on the host
```

API at `http://localhost:8000/api`, web pages at `http://localhost:8000`.
Sign in with `admin@clinic.test` / `Admin@123`.

- **Steps 1 and 5 are not optional.** `.env` and `public/build` are git-ignored, so a fresh
  clone carries neither. Without built assets the API still works, but every web page fails
  with *"Vite manifest not found"*.
- **`APP_KEY` is empty in `.env.example`** — `key:generate` fills it. Without it, any
  session-backed page returns 500.
- **PayPal endpoints need real sandbox credentials** — see [section 6](#6-paypal--visa).

---

## 2. Environment

Values that matter in `.env`; the rest are Laravel defaults.

| Variable | Description |
| -------- | ----------- |
| `APP_KEY` | Empty in the example file — fill with `php artisan key:generate` |
| `DB_HOST=db` | Database host inside the Compose network (the `db` service) |
| `DB_DATABASE=clinic_app` / `DB_USERNAME=clinic` / `DB_PASSWORD=secret` | Must match the `db` service in `docker-compose.yml` |
| `EXAMINATION_FEE=100000` | Examination fee in VND, added to every invoice subtotal |
| `LOW_STOCK_THRESHOLD=5` | Stock at or below this level counts as running low |
| `PAYPAL_MODE=sandbox` / `PAYPAL_CLIENT_ID` / `PAYPAL_CLIENT_SECRET` | Sandbox credentials; placeholders by default |
| `PAYPAL_CURRENCY=USD` / `PAYPAL_EXCHANGE_RATE_VND=25400` | Invoices are in VND, PayPal charges in USD |

---

## 3. Architecture

Controller + Service. Controllers stay thin; every business rule and every transaction lives
in a service.

```
Request → route → auth:sanctum → EnsurePermission → FormRequest
        → Controller → Service (DB::transaction) → Eloquent
        → API Resource → ApiResponse envelope → JSON
```

Every response uses the same envelope: `success`, `message`, and `data` or `errors`.

---

## 4. Commands

```bash
docker compose up -d --build     # build and start
docker compose restart app       # reload after editing .env
docker compose down -v           # stop and drop volumes (needed when composer.lock changes)

docker compose exec app php artisan migrate --seed        # migrate + every seeder
docker compose exec app php artisan migrate:fresh --seed  # drop everything and rebuild
docker compose exec app php artisan migrate:status

docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app php artisan route:list --path=appointments
docker compose exec app tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

Tests run on in-memory SQLite (`phpunit.xml`) and never touch the PostgreSQL container.

`--seed` runs `DatabaseSeeder`, which chains `RoleSeeder → RbacSeeder → AdminSeeder →
DemoSeeder` — one command is all that is needed. `DemoSeeder` builds a full sample flow
(appointment → examination → prescription → invoice → payment). Roles, permissions and staff
accounts are upserted and safe to re-run; the clinical sample data is not deduplicated, so
re-seeding an existing database is better done with `migrate:fresh --seed`.

To repair a drifted permission catalog without rebuilding the database:
`php artisan db:seed --class=RbacSeeder`.

| Role | Email | Password |
| ---- | ----- | -------- |
| ADMIN | admin@clinic.test | Admin@123 |
| DOCTOR | doctor@clinic.test | Doctor@123 |
| RECEPTIONIST | receptionist@clinic.test | Receptionist@123 |
| PHARMACIST | pharmacist@clinic.test | Pharmacist@123 |
| CASHIER | cashier@clinic.test | Cashier@123 |

---

## 5. RBAC Global

Authorization is table-driven: no role name is ever hard-coded in a controller.

| Table | Key columns | Notes |
| ----- | ----------- | ----- |
| `roles` | `id`, `name`, `display_name` | `ADMIN`, `RECEPTIONIST`, `DOCTOR`, `PHARMACIST`, `CASHIER` |
| `permissions` | `id`, `name`, `display_name` | `name` follows `CONTROLLER.ACTION`, e.g. `PATIENTS.CREATE` |
| `role_permissions` | `role_id`, `permission_id` | `UNIQUE(role_id, permission_id)` |
| `users.role_id` | FK → `roles.id` | A user holds exactly one role |

`EnsurePermission` never reads a role name — it derives the permission from the route:

```
PatientController@store
  → config/rbac.php: controllers['PatientController'] = 'PATIENTS'
                     actions['store'] = 'CREATE'
  → PATIENTS.CREATE
  → User::hasPermission() → role_permissions → next() or 403
```

**Adding a permission is a migration, never a manual insert** — the catalog has to be
reproducible on any machine that runs `migrate`.
`database/migrations/2026_08_28_064255_add_medicines_low_stock_permission.php` inserts
`MEDICINES.LOWSTOCK`, grants it to the roles that need it, and reverses both in `down()`. Its
route action `MedicineController@lowStock` maps to the `LOWSTOCK` action in `config/rbac.php`.

---

## 6. PayPal & Visa

**Credentials.** developer.paypal.com → toggle **Sandbox** → **Apps & Credentials** →
**Create App** (type *Merchant*). Copy Client ID and Secret into `.env`, then
`docker compose restart app`.

**`method=paypal` vs `method=visa`.** Both run the identical backend flow —
`POST /invoices/{id}/payments` creates the PayPal Order, `POST /payments/{id}/capture`
captures it. The only difference is the funding source the buyer picks on PayPal's hosted
approval page (`approval_url` from the `store` response): PayPal balance, or a Sandbox test
Visa card. This project is API-only; there is no client-side card-fields UI.

**Test Visa card.** Dashboard → **Testing Tools** → **Sandbox Accounts** → open the
**Personal** (buyer) account → **Funding**, or add a card during checkout — PayPal generates
test numbers and never charges anything real.

---

## 7. PostgreSQL

Integrity is enforced in the database, not only in application code, so a race condition or a
direct SQL write cannot produce invalid data.

### 7.1. Uniqueness

| Constraint | Purpose |
| ---------- | ------- |
| `roles.name`, `permissions.name`, `specialties.name` | One catalog row per name |
| `patients.code`, `medicines.code`, `invoices.invoice_code` | Business identifiers stay unique |
| `patients.email`, `users.email`, `doctors.license_number` | No duplicate contacts or licences |
| `doctors.user_id` | A user account backs at most one doctor profile |
| `examinations.appointment_id` | An appointment is examined at most once |
| `prescriptions.examination_id`, `invoices.examination_id` | One prescription and one invoice per examination |
| `UNIQUE(role_id, permission_id)` | A permission is granted to a role only once |
| `UNIQUE(prescription_id, medicine_id)` | A medicine appears at most once per prescription |

The last two make the "already in this prescription" and RBAC-grant rules race-proof: two
concurrent inserts cannot both succeed.

### 7.2. Indexes

| Index | Serves |
| ----- | ------ |
| `appointments(doctor_id, scheduled_at)` | Availability check and the calendar query |
| `appointments(patient_id)`, `appointments(status)` | `?patient_id=`, `?status=` filters |
| `patients(phone)`, `patients(full_name)` | `?q=` search by name or phone |
| `invoices(status)` | `?status=unpaid` listing |
| `activity_logs(subject_type, subject_id)`, `(user_id)`, `(created_at)` | Audit lookups by subject, actor, or time |

One index is **partial**, because the double-booking check never looks at cancelled rows —
indexing them would be dead weight that grows forever:

```sql
CREATE INDEX appointments_active_schedule_index
ON appointments (doctor_id, scheduled_at)
WHERE status <> 'cancelled'
```

The schema builder cannot express `WHERE` on an index, hence raw SQL in that migration.

### 7.3. Transactions and concurrency

Every multi-step write runs inside `DB::transaction()`, and every read a later write depends
on is taken with `lockForUpdate()` — a plain read lets two concurrent requests both see the
old value and both proceed.

| Operation | What must succeed or fail together |
| --------- | ---------------------------------- |
| `ExaminationService::createFromAppointment` | Lock the appointment, verify `confirmed`, insert the examination, flip the appointment to `completed` |
| `PrescriptionService::createFromExamination` | Lock each medicine, verify stock, insert items, deduct stock — a shortfall on the last item rolls back the whole prescription |
| `PrescriptionService::updateItem` / `removeItem` | Adjust the line and return or deduct the stock difference in one step |
| `PaymentService::create` | Lock the invoice, re-check the remaining balance, then open the PayPal order |
| `PaymentService::capture` | Lock the payment, reject an over-payment **before** calling PayPal, mark the invoice `paid` once fully settled |
| `AppointmentService` | Lock the doctor's schedule while checking availability, so two receptionists cannot double-book a slot |
| `UserService` | Lock the target user together with every active administrator, so the last active ADMIN cannot be demoted or deactivated concurrently |

Three rules follow:

- **Validate before the external call.** Taking real money and then discarding the result
  locally is worse than refusing the request.
- **Settled operations are idempotent, not errors.** PayPal can redirect a customer back more
  than once, so re-capturing a `completed` payment returns the existing result.
- **Lock rows in one statement, ordered by id.** Two transactions requesting the same rows in
  a different order can each hold a row the other needs, which PostgreSQL resolves by killing
  one of them.

### 7.4. JSONB in `activity_logs`

`meta` is `jsonb`, holding a `before`/`after` pair plus business context:

```json
{
  "before": { "stock": 40 },
  "after": { "stock": 25 },
  "prescription_id": 12,
  "quantity": 15
}
```

- **Why JSON and not fixed columns.** Every audited action records a different set of fields —
  a stock deduction has quantities, a capture has amounts and a PayPal order id. Real columns
  would mean a wide table full of nulls, or a migration each time an action logs one more
  detail. The trail is written far more often than read, and it is queried by subject rather
  than by payload, so a flexible payload with an indexed subject is the right trade.
- **Why `jsonb` and not `json`.** `json` stores the original text and re-parses it on every
  access. `jsonb` stores a decoded binary form: no re-parsing, deduplicated keys, and the
  containment operator `@>` plus GIN indexing if the payload ever needs searching.
- **Secrets never reach the table.** `ActivityLogger` redacts `password`, `remember_token`,
  `api_token`, `secret` and `client_secret` to `[REDACTED]` before building the row.
- **The audit write cannot break the business write.** Rows are queued with
  `DB::afterCommit()`: the payload is built eagerly at the moment of the change, but the
  insert runs only after the surrounding transaction commits.
