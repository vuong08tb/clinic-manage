# Clinic Management REST API

---

## Contents

1. [Environment](#1-environment)
2. [Architecture](#2-architecture)
3. [Running the Application with Docker](#3-running-the-application-with-docker)
4. [Appointment Scheduling Rules](#4-appointment-scheduling-rules)
5. [PayPal Sandbox & Visa Integration](#5-paypal-sandbox--visa-integration)

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
| `EXAMINATION_FEE=200000`                          | Default examination fee (VND).                                       |
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

```
Build images and start services (app & db PostgreSQL 16):
docker compose up -d --build
Unload Index/php
docker compose restart app
check list migration
docker compose exec app php artisan migrate:status
Check docker
docker ps
Build migrate created
docker exec clinic_app php artisan migrate
docker compose exec app php artisan make:model Examination -mf
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:rollback --step=1
Build migration user current
docker compose exec \
  --user "$(id -u):$(id -g)" \
  app php artisan make:model Patient --migration --factory
sudo chown ubuntu:ubuntu \
  app/Models/Patient.php \
  database/factories/PatientFactory.php \
  database/migrations/2026_08_10_020152_create_patients_table.php
check ubuntu or root
ls -l \
  app/Models/Appointment.php \
  database/factories/PatientFactory.php \
  database/migrations/2026_08_10_020152_create_patients_table.php
update root
sudo chown -R $USER:$USER .
docker compose exec app php artisan route:list --path=appointments
docker compose exec app composer dump-autoload --optimize
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
seed permission missing
docker compose exec app php artisan db:seed --class=RbacSeeder
```

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
