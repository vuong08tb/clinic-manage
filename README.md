# Clinic Management REST API

---

## Contents

1. [Environment](#1-environment)
2. [Architecture](#2-architecture)
3. [Running the Application with Docker](#3-running-the-application-with-docker)

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
docker compose exec app php artisan make:model Patient -mf
docker compose exec app php artisan migrate
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
  app/Models/Patient.php \
  database/factories/PatientFactory.php \
  database/migrations/2026_08_10_020152_create_patients_table.php

docker compose exec app php artisan route:list --path=patients
docker compose exec app composer dump-autoload --optimize
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
seed permission missing
docker compose exec app php artisan db:seed --class=RbacSeeder
```
