# Clinic Management REST API

---

## Contents

1. [Environment](#1-environment)
2. [Architecture](#2-architecture)
3. [Running the Application with Docker](#3-running-the-application-with-docker)
4. [Appointment Scheduling Rules](#4-appointment-scheduling-rules)
5. [PayPal Sandbox & Visa Integration](#5-paypal-sandbox--visa-integration)
6. [RBAC Global](#6-rbac-global)
7. [PostgreSQL Indexes & Constraints](#7-postgresql-indexes--constraints)
8. [Transactions & Concurrency](#8-transactions--concurrency)
9. [Avoiding N+1 Queries](#9-avoiding-n1-queries)

---

## 1. Environment

### Environment

| Component         | Version                          |
| ----------------- | -------------------------------- |
| Docker Engine     | 29.7.1                           |
| Docker Compose    | 5.3.1 (Docker Compose Plugin v2) |
| PHP (Container)   | 8.3-cli                          |
| Laravel Framework | 13.x                             |
| Database          | PostgreSQL 16                    |

### Environment Variables Explanation (.env)

| Environment variable                              | Description                                                          |
| ------------------------------------------------- | -------------------------------------------------------------------- |
| `DB_CONNECTION=pgsql`                             | Uses the PostgreSQL database management system.                      |
| `DB_HOST=db`                                      | Database host name within the Docker Compose network (`db` service). |
| `DB_PORT=5432`                                    | Default PostgreSQL connection port.                                  |
| `DB_DATABASE=clinic_app`                          | Application database name.                                           |
| `DB_USERNAME=clinic`                              | Database connection username.                                        |
| `DB_PASSWORD=secret`                              | Database connection password.                                        |
| `EXAMINATION_FEE=100000`                          | Default examination fee (VND).                                       |
| `PAYPAL_MODE=sandbox`                             | PayPal API sandbox testing mode.                                     |
| `PAYPAL_CLIENT_ID=your-sandbox-client-id`         | PayPal REST API Client ID for the sandbox integration.               |
| `PAYPAL_CLIENT_SECRET=your-sandbox-client-secret` | PayPal REST API client secret for the sandbox integration.           |
| `PAYPAL_CURRENCY=USD`                             | Default currency for PayPal transactions.                            |

---

## 2. Architecture

```
CLIENT
  │
  ▼
ROUTE (routes/api.php)
  │
  ▼
MIDDLEWARE (auth:sanctum, EnsurePermission)
  │
  ▼
FORM REQUEST (Validation & Authorization)
  │
  ▼
CONTROLLER (Routing & Service Invocation)
  │
  ▼
SERVICE (Core Business Logic / DB Transaction)
  │
  ▼
MODEL / ELOQUENT (PostgreSQL Interaction)
  │
  ▼
API RESOURCE (Format JSON Data Structure)
  │
  ▼
JSON RESPONSE (Envelope: success, message, data / errors)
  │
  ▼
CLIENT
```

## 3. Running the Application with Docker

### Build images and start services

Build images and start the application and PostgreSQL 16:

```bash
docker compose up -d --build
```

### Restart the application

```bash
docker compose restart app
```

### Check migration status

```bash
docker compose exec app php artisan migrate:status
```

### Check running containers

```bash
docker ps
```

### Run migrations

```bash
docker compose exec app php artisan migrate
```

Alternatively:

```bash
docker exec clinic_app php artisan migrate
```

### Create Model + Migration + Factory

Example: create `Examination`:

```bash
docker compose exec app php artisan make:model Examination -mf
```

Then run migration:

```bash
docker compose exec app php artisan migrate
```

### Rollback migration

Rollback the latest migration:

```bash
docker compose exec app php artisan migrate:rollback --step=1
```

---

### Create files with the current Ubuntu user

To prevent files from being created as `root` inside the project:

```bash
docker compose exec \
  --user "$(id -u):$(id -g)" \
  app php artisan make:model Patient --migration --factory
```

### Change file ownership to Ubuntu user

If files were accidentally created as `root`:

```bash
sudo chown ubuntu:ubuntu \
  app/Models/Patient.php \
  database/factories/PatientFactory.php \
  database/migrations/2026_08_10_020152_create_patients_table.php
```

Or change ownership of the entire project to the current user:

```bash
sudo chown -R $USER:$USER .
```

### Check file ownership

```bash
ls -l \
  app/Models/Appointment.php \
  database/factories/PatientFactory.php \
  database/migrations/2026_08_10_020152_create_patients_table.php
```

---

### Check Laravel routes

Check only appointment routes:

```bash
docker compose exec app php artisan route:list --path=appointments
```

Check all routes:

```bash
docker compose exec app php artisan route:list
```

### Rebuild Composer autoload

```bash
docker compose exec app composer dump-autoload --optimize
```

### Clear Laravel cache

```bash
docker compose exec app php artisan optimize:clear
```

---

### Seed RBAC permissions

If permissions are missing, run:

```bash
docker compose exec app php artisan db:seed --class=RbacSeeder
```

### Seed demo data

Seed the complete demo dataset, including:

* Specialties
* Doctors
* Login accounts for every role
* Patients
* Medicines
* Sample appointment
* Examination
* Prescription
* Invoice
* Payment

Run:

```bash
docker compose exec app php artisan db:seed --class=DemoSeeder
```

Demo login accounts created by `DemoSeeder` (see the seeder for the full list):

| Role          | Email                        | Password           |
| ------------- | ----------------------------- | ------------------- |
| ADMIN         | admin@clinic.test             | Admin@123           |
| DOCTOR        | doctor.an@clinic.test         | Doctor@123           |
| RECEPTIONIST  | receptionist@clinic.test      | Receptionist@123     |
| PHARMACIST    | pharmacist@clinic.test        | Pharmacist@123       |
| CASHIER       | cashier@clinic.test           | Cashier@123          |

`DemoSeeder` is meant for a fresh database (`migrate:fresh --seed` or the command above right
after migrating) — the specialties, doctor/staff accounts are safe to re-run, but patients,
appointments, and the rest of the sample clinical data are not deduplicated across runs.

## 4. Appointment Scheduling Rules

- Every appointment occupies a fixed 30-minute slot starting at `scheduled_at`.
- The occupied interval is `[scheduled_at, scheduled_at + 30 minutes)`.
- A doctor cannot have overlapping appointments unless the existing appointment is `cancelled`.
- Adjacent slots are allowed — e.g. a 09:00 appointment does not conflict with a 09:30 appointment.

---

## 5. PayPal Sandbox & Visa Integration

### 5.1. Create a PayPal Developer Sandbox app

1. Sign in at https://developer.paypal.com and switch the toggle to **Sandbox** (top-left).
2. Go to **Apps & Credentials** → **Sandbox** tab → **Create App**.
3. Name it (e.g. `clinic-manage`), type **Merchant**, pick the auto-generated Sandbox Business
   account, then **Create App**.
4. Copy **Client ID** and **Secret** into `.env`:
   ```
   PAYPAL_MODE=sandbox
   PAYPAL_CLIENT_ID=<client_id>
   PAYPAL_CLIENT_SECRET=<client_secret>
   PAYPAL_CURRENCY=USD
   ```
5. Restart the app container so it picks up the new `.env` values:
   `docker compose restart app`.

### 5.2. Testing `method=paypal` vs `method=visa`

Both methods use the exact same backend flow — `POST /invoices/{id}/payments` creates a PayPal
Order and `POST /payments/{id}/capture` captures it, regardless of `method`. The only
difference is which funding source the buyer picks on PayPal's hosted approval page
(`approval_url` returned by the `store` endpoint):

- `method=paypal` → buyer pays from their PayPal balance.
- `method=visa` → buyer pays with a Sandbox test Visa card instead.

This project is API-only (no client-side card-fields UI); the buyer flow is exercised through
PayPal's own hosted checkout page, not a page built in this repo.

### 5.3. Getting a Sandbox test Visa card

1. Dashboard → **Testing Tools** → **Sandbox Accounts**.
2. Open the **Personal** (buyer) account → **Funding**, or add a card under
   **Add credit or debit card** during checkout — PayPal auto-generates test card numbers (no
   real charges are ever made).
3. On the `approval_url` page, under **Pay with**, select the Visa card instead of PayPal
   balance to simulate a `method=visa` payment.

---

## 6. RBAC Global

Authorization is table-driven: no role name is ever hard-coded in a controller.

### 6.1. Schema

Three catalog tables plus one foreign key on `users`:

| Table | Key columns | Notes |
| ----- | ----------- | ----- |
| `roles` | `id`, `name`, `display_name` | `ADMIN`, `RECEPTIONIST`, `DOCTOR`, `PHARMACIST`, `CASHIER` |
| `permissions` | `id`, `name`, `display_name` | `name` follows `CONTROLLER.ACTION`, e.g. `PATIENTS.CREATE` |
| `role_permissions` | `role_id`, `permission_id` | `UNIQUE(role_id, permission_id)` |
| `users.role_id` | FK → `roles.id` | A user holds exactly one role — not a fourth catalog table |

### 6.2. How a request is authorized

`EnsurePermission` (`app/Http/Middleware/EnsurePermission.php`) never reads a role name. It
resolves the permission from the route itself:

```
Route action        PatientController@store
       │
       ▼  config/rbac.php  'controllers' => ['PatientController' => 'PATIENTS']
       │                   'actions'     => ['store' => 'CREATE']
       ▼
Permission          PATIENTS.CREATE
       │
       ▼  User::hasPermission() → role_permissions
Allow (next) or 403 "Missing permission: PATIENTS.CREATE"
```

The action map covers `index→FINDALL`, `store→CREATE`, `show→FINDONE`, `update→UPDATE`,
`destroy→DELETE`, plus the custom actions `updateStatus`, `addItem`, `updateItem`,
`removeItem`, `adjustStock` and `capture`. Any action **not** in the map falls back to its own
name upper-cased — that is how `PaymentController@cancel` resolves to `PAYMENTS.CANCEL`
without a map entry. `config/rbac.php` also holds an `overrides` map for the few routes whose
permission does not follow the convention (`StatsController@show` → `STATS.SHOW`).

Because the mapping is derived from `Controller@action`, adding a route automatically
requires the matching permission — there is nothing to remember to wire up.

### 6.3. Adding a new permission

A new permission is **data, so it ships as a data migration** — never as a Seeder edit alone.
Seeders are re-run selectively and are not guaranteed to execute on an existing database,
whereas a migration runs exactly once per environment and is tracked.

Write the migration idempotently (`upsert` keyed on `name`) so re-running it is harmless:

```php
// database/migrations/2026_08_28_100000_add_payments_cancel_permission.php
public function up(): void
{
    $now = now();

    DB::table('permissions')->upsert(
        [[
            'name' => 'PAYMENTS.CANCEL',
            'display_name' => 'Hủy thanh toán',
            'created_at' => $now,
            'updated_at' => $now,
        ]],
        ['name'],
        ['display_name', 'updated_at'],
    );
}

public function down(): void
{
    DB::table('permissions')->where('name', 'PAYMENTS.CANCEL')->delete();
}
```

Then grant it to the relevant roles in `database/seeders/RbacSeeder.php`. `ADMIN` receives
every permission automatically, so only the other roles need an entry.

---

## 7. PostgreSQL Indexes & Constraints

Integrity is enforced in the database, not only in application code, so a race condition or a
direct SQL write cannot produce invalid data.

### 7.1. Uniqueness

| Constraint | Purpose |
| ---------- | ------- |
| `roles.name`, `permissions.name`, `specialties.name` | One catalog row per name |
| `patients.code`, `medicines.code`, `invoices.invoice_code` | Business identifiers stay unique |
| `patients.email`, `users.email`, `doctors.license_number` | No duplicate contacts/licences |
| `doctors.user_id` | A user account backs at most one doctor profile |
| `examinations.appointment_id` | An appointment is examined at most once |
| `prescriptions.examination_id`, `invoices.examination_id` | One prescription and one invoice per examination |
| `UNIQUE(role_id, permission_id)` | A permission is granted to a role only once |
| `UNIQUE(prescription_id, medicine_id)` | A medicine appears at most once per prescription — quantity changes go through `updateItem` |

The last two are what make the "already in this prescription" and RBAC-grant rules
race-proof: two concurrent inserts cannot both succeed.

### 7.2. Indexes

Added to match the filters the API actually exposes:

| Index | Serves |
| ----- | ------ |
| `appointments(doctor_id, scheduled_at)` | Doctor availability check and the day/week calendar query |
| `appointments(patient_id)`, `appointments(status)` | `?patient_id=`, `?status=` filters |
| `patients(phone)`, `patients(full_name)` | `?q=` search by name or phone |
| `invoices(status)` | `?status=unpaid` listing |
| `activity_logs(subject_type, subject_id)`, `(user_id)`, `(created_at)` | Audit lookups by subject, actor, or time range |

### 7.3. Delete behaviour

Foreign keys use `RESTRICT` by default: a patient, doctor, medicine, examination or invoice
that is referenced by clinical history **cannot be deleted**, so the record trail stays intact.
The single exception is `prescription_items.prescription_id`, which cascades — items have no
meaning without their prescription. `patients` and `medicines` additionally use soft deletes,
so "deleting" one hides it from listings while preserving every historical reference.

---

## 8. Transactions & Concurrency

Every multi-step write runs inside `DB::transaction()`, and every read that a later write
depends on is taken with `lockForUpdate()` — a plain read would let two concurrent requests
both see the old value and both proceed.

Nine services follow this pattern. The clearest examples:

| Operation | What must succeed or fail together |
| --------- | ---------------------------------- |
| `ExaminationService::createFromAppointment` | Lock the appointment, verify it is `confirmed`, insert the examination, and flip the appointment to `completed` |
| `PrescriptionService::createFromExamination` | Lock each medicine, verify stock, insert the items, and deduct stock — insufficient stock on the last item rolls back the whole prescription |
| `PrescriptionService::updateItem` / `removeItem` | Adjust the line and return or deduct the stock difference in the same step |
| `PaymentService::create` | Lock the invoice, re-check the remaining balance, then open the PayPal order |
| `PaymentService::capture` | Lock the payment, reject a capture that would exceed the invoice total **before** calling PayPal, then mark the invoice `paid` once fully settled |
| `AppointmentService` | Lock the doctor's schedule while checking availability, so two receptionists cannot double-book the same slot |

Two deliberate rules follow from this:

- **Validate before the external call.** `capture` rejects an over-payment before contacting
  PayPal, because taking real money and then discarding the result locally is worse than
  refusing the request.
- **Settled operations are idempotent, not errors.** PayPal can redirect a customer back more
  than once, so re-capturing a `completed` payment returns the existing result instead of
  failing.

---

## 9. Avoiding N+1 Queries

Every endpoint that renders relations eager-loads them; no relation is resolved lazily inside
a resource.

- **List endpoints** load relations on the query before pagination:
  `Prescription::query()->with(['items.medicine', 'doctor.user', 'examination.patient'])`,
  `Invoice::query()->with(['examination.patient', 'examination.doctor.user'])`,
  `Appointment::query()->with(['patient', 'doctor.user'])`.
- **Detail endpoints** call a service `load()` helper that uses `loadMissing()`, so a model
  already carrying its relations is not re-queried.
- **After a write**, services `refresh()->load([...])` before returning, so the response
  resource is built from a fully hydrated model.
- **API Resources use `whenLoaded()`**, so a relation that was not eager-loaded is omitted from
  the JSON rather than silently triggering a query.
- **Aggregates stay in SQL.** `StatsService` computes its figures with aggregate queries
  instead of loading collections and counting in PHP; `StatsTest` asserts this by counting the
  queries the endpoint issues.
