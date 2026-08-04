# Skill: Docker (Compose + PostgreSQL + Laravel)

Playbook dựng và vận hành môi trường container cho Clinic API. Áp dụng cho task nhóm T1.1–T1.4 và bảo trì infra. Mục tiêu: mentor chạy được đúng chuỗi lệnh chấm bằng một lệnh build.

---

## 1. Kiến trúc container

```
docker compose
├── app  (build từ Dockerfile: php:8.3-cli + pdo_pgsql + composer)
│     port 8000:8000, mount .:/var/www, depends_on db(healthy)
│     command: php artisan serve --host=0.0.0.0 --port=8000
└── db   (postgres:16)
      port 5433:5432 (host:container), volume clinic_postgres_data
      healthcheck: pg_isready -U clinic -d clinic
```

- App gọi DB qua **service name** `db:5432` (không phải localhost).
- Host expose Postgres ở **5433** để tránh đụng Postgres cài sẵn trên máy.
- Dữ liệu DB persist qua named volume → `docker compose down` không mất data (chỉ `down -v` mới xóa).

---

## 2. Dockerfile (đã có trong repo)

Các điểm bắt buộc:
- Base `php:8.3-cli`.
- Cài `libpq-dev`, `libzip-dev`; `docker-php-ext-install pdo_pgsql zip`.
- Copy composer từ `composer:2`.
- `composer install --no-scripts` ở bước build (cache layer), copy source, `dump-autoload --optimize`.
- `EXPOSE 8000`; `CMD php artisan serve`.

> `php artisan serve` đủ cho môi trường thực tập. Production thực dùng php-fpm + nginx — ngoài phạm vi đề.

---

## 3. docker-compose.yml — điểm mấu chốt

```yaml
services:
  app:
    build: { context: ., dockerfile: Dockerfile }
    ports: ["8000:8000"]
    volumes: [".:/var/www"]
    depends_on:
      db: { condition: service_healthy }
    command: php artisan serve --host=0.0.0.0 --port=8000
  db:
    image: postgres:16
    environment:
      POSTGRES_DB: clinic
      POSTGRES_USER: clinic
      POSTGRES_PASSWORD: clinic_password
    ports: ["5433:5432"]
    volumes: ["clinic_postgres_data:/var/lib/postgresql/data"]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U clinic -d clinic"]
      interval: 5s
      timeout: 5s
      retries: 5
volumes:
  clinic_postgres_data:
```

- `depends_on: condition: service_healthy` chặn app migrate khi DB chưa sẵn sàng → tránh lỗi "connection refused" lúc `up`.
- Biến DB trong compose phải khớp `.env` (`DB_HOST=db`, `DB_DATABASE=clinic`, ...).

---

## 4. Chuỗi lệnh mentor chấm

```bash
git clone <repo> && cd clinic
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

Frontend (nếu làm): `docker compose exec app npm install && npm run build` (hoặc chạy `npm run dev` khi phát triển).

---

## 5. Lệnh vận hành thường dùng

```bash
docker compose ps                                  # trạng thái service
docker compose logs -f app                         # log app realtime
docker compose exec app bash                        # vào shell container
docker compose exec app php artisan migrate:fresh --seed   # reset schema + seed
docker compose exec db psql -U clinic -d clinic     # psql vào DB
docker compose down                                 # dừng, giữ volume
docker compose down -v                              # dừng + xóa volume DB
docker compose up -d --build                        # build lại sau khi đổi Dockerfile
```

---

## 6. Sự cố thường gặp

| Triệu chứng | Nguyên nhân | Cách xử lý |
|---|---|---|
| `SQLSTATE... connection refused` khi migrate | app migrate trước khi DB healthy | đảm bảo `depends_on: service_healthy`; hoặc chờ rồi migrate lại |
| `could not translate host name "db"` | chạy artisan ngoài container | luôn `docker compose exec app php artisan ...` |
| Port 5432 bận | Postgres local chiếm cổng | dùng map `5433:5432` (đã cấu hình); kết nối host qua 5433 |
| Đổi `.env` không ăn | config cache | `docker compose exec app php artisan config:clear` |
| Data mất sau `down` | dùng `down -v` | chỉ `down` để giữ volume |
| Quyền file `storage/` | mount volume Linux | `chmod -R 775 storage bootstrap/cache` trong container |

---

## 7. Mở rộng (điểm cộng)

- Service `queue` chạy `php artisan queue:listen` cho queue job (T4).
- Service `adminer`/`pgadmin` xem DB trực quan.
- `.dockerignore` loại `vendor/`, `node_modules/` khỏi build context để build nhanh.

---

## 8. Checklist Docker trước PR

- [ ] `docker compose up -d --build` dựng cả app + db.
- [ ] `db` healthy trước khi app migrate.
- [ ] `migrate --seed` chạy sạch từ máy trắng.
- [ ] Volume persist (down/up không mất data).
- [ ] Port API 8000; Postgres host 5433.
- [ ] Ghi version Docker/Compose vào README.
- [ ] Không commit `.env`.
