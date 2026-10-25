# DOCKER: LOCALHOST → VPS

Satu basis kode, dua mode:
1. **Pengembangan di localhost** (`compose.yaml`): kode di-mount, Vite dev server, Mailpit.
2. **Produksi di VPS** (`compose.prod.yaml`): image siap pakai, HTTPS otomatis lewat Caddy.

Sebelum ke VPS, mode produksi juga diuji di localhost (bagian C).

Potongan konfigurasi di bawah adalah acuan. Cocokkan nama variabel dan perintah dengan dokumentasi image yang dipakai saat implementasi, lalu catat perbedaannya di `docs/DECISIONS.md`.

---

## A. Struktur File

```
.
├── compose.yaml                 # dev (localhost)
├── compose.prod.yaml            # produksi (VPS & uji produksi lokal)
├── Makefile
├── .env.example                 # dev
├── .env.production.example      # produksi
├── .dockerignore
├── docker/
│   ├── app/Dockerfile           # multi-stage: base, dev, vendor, assets, prod
│   └── caddy/Caddyfile
└── scripts/
    ├── deploy.sh
    └── copy-face-models.mjs     # salin model face-api ke public/models (npm postinstall)
```

`.dockerignore`: `.git`, `node_modules`, `vendor`, `storage/app/*`, `storage/logs/*`, `public/build`, `public/hot`, `.env*` (kecuali contoh).

---

## B. Pengembangan di Localhost

### B.1 Layanan

| Service | Image | Port host | Fungsi |
|---|---|---|---|
| `app` | build `docker/app` target `dev` (basis `serversideup/php:8.4-fpm-nginx`) | 8080 | Laravel (PHP-FPM + Nginx) |
| `queue` | sama dengan app | – | `php artisan queue:work --queue=notifications,exports,default --tries=3` |
| `scheduler` | sama dengan app | – | `php artisan schedule:work` |
| `db` | `mysql:8.4` | 33060 | Database (port hanya untuk alat lokal) |
| `redis` | `redis:7-alpine` | – | cache, session, queue |
| `vite` | `node:22-alpine` | 5173 | `npm run dev -- --host 0.0.0.0` |
| `mailpit` | `axllent/mailpit` | 8025 | menangkap email |
| `phpmyadmin` | `phpmyadmin` (profil `tools`) | 8081 | opsional |
| `tunnel` | `cloudflare/cloudflared` (profil `tunnel`) | – | HTTPS sementara untuk uji di HP |

### B.2 Dockerfile (acuan)

```dockerfile
# syntax=docker/dockerfile:1
FROM serversideup/php:8.4-fpm-nginx AS base
USER root
RUN install-php-extensions intl gd exif bcmath redis
ENV TZ=Asia/Makassar \
    PHP_UPLOAD_MAX_FILE_SIZE=60M \
    PHP_POST_MAX_SIZE=64M \
    PHP_MEMORY_LIMIT=512M
USER www-data

# ---- dev: UID/GID disamakan dengan pengguna host agar tidak ada masalah izin file
FROM base AS dev
ARG USER_ID=1000
ARG GROUP_ID=1000
USER root
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
 && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx
USER www-data

# ---- vendor (produksi)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- assets (produksi)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- prod
FROM base AS prod
ENV PHP_OPCACHE_ENABLE=1
COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=vendor /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
COPY --chown=www-data:www-data --from=assets /app/public/models /var/www/html/public/models
```

Catatan:
- Model face-api disalin dari `node_modules/@vladmandic/face-api/model` ke `public/models` lewat `npm postinstall` (`scripts/copy-face-models.mjs`). `public/models` di-gitignore.
- Font Noto Sans SC/JP untuk dompdf disimpan di `resources/fonts` (masuk repo) dan didaftarkan ke dompdf.

### B.3 compose.yaml (acuan ringkas)

```yaml
x-app: &app
  build:
    context: .
    dockerfile: docker/app/Dockerfile
    target: dev
    args: { USER_ID: "${UID:-1000}", GROUP_ID: "${GID:-1000}" }
  image: wokabulari-app:dev
  env_file: .env
  volumes: [".:/var/www/html"]
  depends_on:
    db: { condition: service_healthy }
    redis: { condition: service_started }

services:
  app:
    <<: *app
    ports: ["8080:8080"]
  queue:
    <<: *app
    command: ["php", "/var/www/html/artisan", "queue:work", "--queue=notifications,exports,default", "--tries=3"]
    stop_signal: SIGTERM
  scheduler:
    <<: *app
    command: ["php", "/var/www/html/artisan", "schedule:work"]
    stop_signal: SIGTERM
  db:
    image: mysql:8.4
    command: ["--default-time-zone=+08:00"]
    environment:
      MYSQL_DATABASE: wokabulari
      MYSQL_USER: wokabulari
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    ports: ["33060:3306"]
    volumes: ["db-data:/var/lib/mysql"]
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      retries: 20
  redis:
    image: redis:7-alpine
  vite:
    image: node:22-alpine
    working_dir: /app
    volumes: [".:/app"]
    command: sh -c "npm install && npm run dev -- --host 0.0.0.0"
    ports: ["5173:5173"]
  mailpit:
    image: axllent/mailpit
    ports: ["8025:8025"]
  phpmyadmin:
    image: phpmyadmin
    profiles: ["tools"]
    environment: { PMA_HOST: db }
    ports: ["8081:80"]
  tunnel:
    image: cloudflare/cloudflared
    profiles: ["tunnel"]
    command: tunnel --no-autoupdate --url http://app:8080

volumes:
  db-data:
```

`vite.config.js`: `server: { host: '0.0.0.0', port: 5173, hmr: { host: 'localhost' } }`.

### B.4 .env.example (dev, inti)

```dotenv
APP_NAME=Wokabulari
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=wokabulari
DB_USERNAME=wokabulari
DB_PASSWORD=secret

REDIS_HOST=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=noreply@wokabulari.test
MAIL_FROM_NAME="Wokabulari"

WHATSAPP_DRIVER=log
FACE_VERIFICATION_ENABLED=true
GEOFENCE_ENABLED=true
```

### B.5 Makefile (target minimal)

| Target | Isi |
|---|---|
| `build` | `docker compose build` |
| `up` / `down` | `docker compose up -d` / `docker compose down` |
| `install` | composer install → key:generate → migrate --seed → storage:link → npm install (model face-api ikut tersalin) |
| `sh` | `docker compose exec app bash` |
| `artisan cmd=…` | `docker compose exec app php artisan $(cmd)` |
| `composer cmd=…` | `docker compose exec app composer $(cmd)` |
| `npm cmd=…` | `docker compose run --rm vite npm $(cmd)` |
| `test` | `docker compose exec app php artisan test` |
| `fresh` | `migrate:fresh --seed` |
| `assets` | `npm run build` dan hapus `public/hot` (dipakai sebelum uji lewat tunnel) |
| `tunnel` | `docker compose --profile tunnel up tunnel` lalu tampilkan URL https dari log |
| `tools` | `docker compose --profile tools up -d phpmyadmin` |
| `logs` | `docker compose logs -f` |
| `prod-local` | jalankan `compose.prod.yaml` dengan `APP_DOMAIN=localhost` (bagian C) |

### B.6 Langkah uji di localhost

1. Pasang Docker Desktop (Windows/macOS) atau Docker Engine (Linux). Di Windows, simpan proyek di dalam filesystem WSL2 agar cepat.
2. `cp .env.example .env` → `make build` → `make up` → `make install`.
3. Buka `http://localhost:8080`, login dengan akun seeder (lihat SPEC bagian 8).
4. **Kamera & GPS di laptop:** `http://localhost` sudah dianggap aman oleh browser. Untuk lokasi palsu, pakai Chrome DevTools → More tools → Sensors → Location (isi koordinat lokasi kelas/asrama dari seeder).
5. **Uji di HP:**
   - `make assets` (build aset, karena HP tidak bisa menjangkau Vite dev server).
   - `make tunnel`, salin URL `https://….trycloudflare.com` dari log, buka di HP.
   - Pastikan Laravel mempercayai proxy (`trustProxies(at: '*')` khusus lingkungan lokal) agar skema https terdeteksi.
   - Selesai uji: hentikan tunnel dan jalankan lagi Vite (`make up`).
6. Email terlihat di `http://localhost:8025`; notifikasi WhatsApp tercatat di `storage/logs`.
7. Jalankan `make test` sebelum setiap commit.

---

## C. Uji Mode Produksi di Localhost

Sebelum menyentuh VPS, jalankan konfigurasi produksi persis di laptop:

1. Siapkan `.env.prod-local` dari `.env.production.example` dengan `APP_DOMAIN=localhost`, `APP_URL=https://localhost`, `APP_DEBUG=false`.
2. `make prod-local` (alias `docker compose -f compose.prod.yaml --env-file .env.prod-local up -d --build`).
3. Caddy otomatis membuat sertifikat lokal untuk `https://localhost` (browser mungkin meminta konfirmasi sertifikat).
4. Jalankan migrate + `ProductionSeeder` + `app:create-admin`, lalu cek: login, kamera, unggah file, antrean berjalan (`queue` memproses notifikasi), `php artisan schedule:list`, `php artisan backup:run`.
5. Matikan dengan `docker compose -f compose.prod.yaml down` (volume dipertahankan).

---

## D. Produksi di VPS

> **Server Poltekkes (wokabulari.poltekkes-manado.ac.id):** server sudah menjalankan nginx untuk aplikasi lain, jadi Caddy tidak dipakai. Ikuti **docs/DEPLOY-SERVER.md** (container di 127.0.0.1:8090 di belakang nginx host). Bagian D di bawah tetap berlaku sebagai acuan umum VPS kosong.

### D.1 Kebutuhan
- VPS Ubuntu 24.04 LTS, minimal **2 vCPU, 4 GB RAM, 40 GB SSD** (cukup untuk ±200 pengguna aktif).
- Domain/subdomain dengan record **A** ke IP VPS, contoh `[domain-anda]`.
- Repo Git privat (GitHub/GitLab) dengan deploy key read-only.

### D.2 compose.prod.yaml (acuan ringkas)

```yaml
x-app: &app
  image: wokabulari-app:prod
  env_file: .env
  volumes: ["storage-app:/var/www/html/storage/app", "storage-logs:/var/www/html/storage/logs"]
  restart: unless-stopped
  depends_on:
    db: { condition: service_healthy }
    redis: { condition: service_started }
  logging: { driver: json-file, options: { max-size: "10m", max-file: "3" } }

services:
  caddy:
    image: caddy:2-alpine
    ports: ["80:80", "443:443", "443:443/udp"]
    environment: { APP_DOMAIN: "${APP_DOMAIN}" }
    volumes:
      - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy-data:/data
      - caddy-config:/config
    restart: unless-stopped
    depends_on: [app]
  app:
    <<: *app
    build: { context: ., dockerfile: docker/app/Dockerfile, target: prod }
  queue:
    <<: *app
    command: ["php", "/var/www/html/artisan", "queue:work", "--queue=notifications,exports,default", "--tries=3", "--max-time=3600"]
    stop_signal: SIGTERM
  scheduler:
    <<: *app
    command: ["php", "/var/www/html/artisan", "schedule:work"]
    stop_signal: SIGTERM
  db:
    image: mysql:8.4
    command: ["--default-time-zone=+08:00"]
    env_file: .env.db
    volumes: ["db-data:/var/lib/mysql"]
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      retries: 10
  redis:
    image: redis:7-alpine
    command: ["redis-server", "--appendonly", "yes", "--requirepass", "${REDIS_PASSWORD}"]
    volumes: ["redis-data:/data"]
    restart: unless-stopped

volumes: { db-data: {}, redis-data: {}, storage-app: {}, storage-logs: {}, caddy-data: {}, caddy-config: {} }
```

Port MySQL dan Redis **tidak** dibuka ke luar. Hanya Caddy yang membuka 80/443.

### D.3 Caddyfile

```caddyfile
{$APP_DOMAIN} {
    encode zstd gzip
    request_body {
        max_size 64MB
    }
    reverse_proxy app:8080
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
        X-Content-Type-Options "nosniff"
        Referrer-Policy "strict-origin-when-cross-origin"
        Permissions-Policy "camera=(self), microphone=(self), geolocation=(self)"
        -Server
    }
}
```

`Permissions-Policy` wajib mengizinkan kamera, mikrofon, dan lokasi untuk origin sendiri. Laravel harus mempercayai proxy dari jaringan Docker (`trustProxies`).

### D.4 .env.production.example (inti)

```dotenv
APP_NAME=Wokabulari
APP_ENV=production
APP_DEBUG=false
APP_DOMAIN=[domain-anda]
APP_URL=https://[domain-anda]
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=id

DB_HOST=db
DB_DATABASE=wokabulari
DB_USERNAME=wokabulari
DB_PASSWORD=[ganti-password-kuat]

REDIS_HOST=redis
REDIS_PASSWORD=[ganti-password-kuat]
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=[smtp-anda]
MAIL_PORT=587
MAIL_USERNAME=[user]
MAIL_PASSWORD=[password]
MAIL_FROM_ADDRESS=noreply@[domain-anda]

WHATSAPP_DRIVER=[nama-gateway]
WHATSAPP_API_KEY=[kunci]

FACE_VERIFICATION_ENABLED=true
GEOFENCE_ENABLED=true

BACKUP_DISK=backups
BACKUP_OFFSITE_DISK=[s3-kompatibel-opsional]
```

`.env.db` berisi `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` (nilai sama dengan `.env`). Kedua file `chmod 600`.

### D.5 Langkah pertama kali di VPS

1. **Pengamanan server:** buat user `deploy` dengan SSH key, matikan login root & password SSH, `ufw allow OpenSSH`, `ufw allow 80,443/tcp`, `ufw allow 443/udp`, `ufw enable`, pasang `fail2ban` dan `unattended-upgrades`, set zona waktu `Asia/Makassar`.
2. **Pasang Docker Engine + plugin compose** dari repositori resmi Docker; tambahkan `deploy` ke grup `docker`.
3. **Ambil kode:** `git clone` ke `/opt/wokabulari` memakai deploy key.
4. **Konfigurasi:** salin `.env.production.example` → `.env`, buat `.env.db`, isi semua nilai, `php artisan key:generate --show` untuk `APP_KEY`.
5. **DNS:** pastikan domain sudah mengarah ke IP VPS (dibutuhkan Caddy untuk sertifikat Let's Encrypt).
6. **Jalankan:** `docker compose -f compose.prod.yaml up -d --build`.
7. **Inisialisasi:**
   ```bash
   docker compose -f compose.prod.yaml exec app php artisan migrate --force
   docker compose -f compose.prod.yaml exec app php artisan db:seed --class=ProductionSeeder --force
   docker compose -f compose.prod.yaml exec app php artisan storage:link
   docker compose -f compose.prod.yaml exec app php artisan app:create-admin
   docker compose -f compose.prod.yaml exec app php artisan optimize
   ```
   `ProductionSeeder` hanya berisi role, permission, bahasa, aturan gamifikasi, level, lencana, dan pengaturan bawaan, **tanpa** data contoh.
8. **Verifikasi:** buka `https://[domain-anda]` di HP, uji kamera & lokasi, kirim notifikasi uji, `schedule:list`, `backup:run`.

### D.6 Update rutin (`scripts/deploy.sh`)

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /opt/wokabulari
git pull --ff-only
docker compose -f compose.prod.yaml build app
docker compose -f compose.prod.yaml up -d --remove-orphans
docker compose -f compose.prod.yaml exec -T app php artisan migrate --force
docker compose -f compose.prod.yaml exec -T app php artisan optimize
docker compose -f compose.prod.yaml exec -T app php artisan queue:restart
docker image prune -f
```

Gunakan tag Git per rilis (`v1.0.0`) agar mudah kembali ke versi sebelumnya: `git checkout v1.0.0 && ./scripts/deploy.sh`.

### D.7 Backup & pemantauan

- spatie/laravel-backup harian (database + `storage/app`) ke disk `backups`, **dan** salinan ke penyimpanan di luar VPS (S3-compatible atau server lain). Backup di VPS yang sama bukan backup sesungguhnya.
- Uji pemulihan minimal sekali sebelum program dimulai.
- Pantau ketersediaan dengan layanan uptime eksternal ke endpoint `/up`.
- Pantau sisa disk (selfie dan audio dapat cepat bertambah); aktifkan retensi selfie (SPEC bagian 6).
- Log container dirotasi (10 MB × 3 file).
