<p align="center"><img src="public/images/brand/logo-horizontal.svg" alt="Wokabulari" width="360"></p>

<p align="center"><strong>Kaya Kosakata, Torang Go Global!</strong><br>
Kelas Bahasa SMK Go Global · Poltekkes Kemenkes Manado</p>

---

Wokabulari adalah aplikasi web kelas bahasa (Inggris, Mandarin, Jepang, Jerman) untuk program **SMK Go Global**. Mobile-first dan dapat di-install sebagai PWA.

- Spesifikasi: [`docs/SPEC.md`](docs/SPEC.md)
- Rencana & progres: [`docs/PLAN.md`](docs/PLAN.md)
- Docker (localhost → VPS): [`docs/DOCKER.md`](docs/DOCKER.md)
- Keputusan teknis: [`docs/DECISIONS.md`](docs/DECISIONS.md)

## Stack

Laravel 13 · Livewire 4 + Flux · Tailwind CSS v4 · MySQL 8.4 · Redis 7 · Pest · Docker

## Menjalankan dengan Docker (disarankan)

```bash
cp .env.example .env
make build
make up
make install
```

Aplikasi: http://localhost:8080 · Email uji (Mailpit): http://localhost:8025

## Menjalankan tanpa Docker (Laragon/PHP lokal)

```bash
cp .env.example .env            # ganti DB_CONNECTION=sqlite, CACHE/QUEUE/SESSION=database
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Import peserta

Isi `PARTICIPANT_DEFAULT_PASSWORD` di `.env`, lalu:

```bash
php artisan app:import-participants database/data/peserta.csv
```

Kolom minimal: `nama`, `no_hp`, `asal_sekolah`. Peserta login dengan **No. HP** dan kata sandi awal, lalu wajib mengganti kata sandi saat login pertama. Berkas data asli di `database/data/` tidak ikut ter-commit.

## Test

```bash
make test            # di Docker
php artisan test     # tanpa Docker
```
