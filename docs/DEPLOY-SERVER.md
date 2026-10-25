# Panduan Deploy Wokabulari — Server Poltekkes Kemenkes Manado

Alamat: **https://wokabulari.poltekkes-manado.ac.id** · Server: **103.181.182.168**

Panduan ini untuk tim IT yang menjalankan perintah langsung di server. Semua perintah dijalankan dari folder repo (`/opt/wokabulari`) kecuali disebutkan lain.

---

## Gambaran singkat

```
Browser (HP/laptop)
   │ HTTPS 443
   ▼
nginx HOST (sudah ada di server, memegang sertifikat)
   │ http://127.0.0.1:8090   ← hanya bisa diakses dari dalam server
   ▼
Docker ── app       (nginx + PHP-FPM 8.4, aplikasi Laravel)
       ├─ queue     (pengiriman notifikasi, ekspor)
       ├─ scheduler (pengingat, tutup absen malam, dsb.)
       ├─ db        (MySQL 8.4, tanpa port publik)
       └─ redis     (cache, sesi, antrean, tanpa port publik)
```

Tidak ada port publik baru: aplikasi lain di server tidak terganggu, MySQL & Redis Wokabulari tidak bisa diakses dari luar.

**Kondisi saat dicek (25 Okt 2026):**
- DNS `wokabulari.poltekkes-manado.ac.id` → `103.181.182.168` (DNS only) ✔
- nginx 1.18 (Ubuntu) sudah berjalan; subdomain ini saat ini masih tertangkap vhost bawaan (menampilkan aplikasi lain) — akan beres setelah langkah 6.
- Sertifikat wildcard `*.poltekkes-manado.ac.id` (Sectigo) **berlaku sampai 26 Okt 2026 23.59 GMT**. Pastikan sudah diperbarui, atau pakai Let's Encrypt khusus subdomain ini (langkah 6, pilihan B). Tanpa HTTPS yang sah, kamera, mikrofon, dan GPS **tidak akan jalan** di HP.

---

## 0. Prasyarat

```bash
lsb_release -a                 # versi Ubuntu
docker --version               # Docker Engine 24+
docker compose version         # plugin compose v2
free -h && df -h               # sisa RAM ≥ 2 GB, disk ≥ 20 GB
sudo ss -ltnp | grep 8090      # harus KOSONG (port 8090 belum dipakai)
```

Bila Docker belum terpasang (Ubuntu), ikuti petunjuk resmi: <https://docs.docker.com/engine/install/ubuntu/>, lalu tambahkan user deploy ke grup docker:

```bash
sudo usermod -aG docker $USER   # keluar lalu login ulang SSH
```

Bila port 8090 sudah dipakai, pilih port lain (mis. 8091) dan samakan di **dua** tempat: `APP_PORT` di `.env` dan `server 127.0.0.1:8091;` di vhost nginx.

---

## 1. Ambil kode

```bash
sudo mkdir -p /opt/wokabulari && sudo chown $USER:$USER /opt/wokabulari
git clone https://github.com/Waters7/wokabulari.git /opt/wokabulari
cd /opt/wokabulari
```

---

## 2. Isi konfigurasi

```bash
cp .env.production.example .env
cp .env.db.example .env.db
chmod 600 .env .env.db

# Buat password acak untuk DB, root DB, dan Redis (jalankan 3×, catat hasilnya)
openssl rand -base64 24 | tr -d '/+='
```

Isi nilainya:

| Berkas | Variabel | Isi |
|---|---|---|
| `.env.db` | `MYSQL_PASSWORD` | password DB aplikasi |
| `.env.db` | `MYSQL_ROOT_PASSWORD` | password root DB (berbeda) |
| `.env` | `DB_PASSWORD` | **sama** dengan `MYSQL_PASSWORD` |
| `.env` | `REDIS_PASSWORD` | password Redis |
| `.env` | `PARTICIPANT_DEFAULT_PASSWORD` | kata sandi awal peserta dari panitia (wajib diganti saat login pertama) |
| `.env` | `MAIL_*` | opsional; biarkan `MAIL_MAILER=log` bila belum ada SMTP |

Jangan ubah `FACE_VERIFICATION_ENABLED=true` dan `GEOFENCE_ENABLED=true`.

---

## 3. Build image & buat APP_KEY

```bash
docker compose -f compose.prod.yaml build            # pertama kali ±5–10 menit
docker compose -f compose.prod.yaml run --rm --no-deps app php artisan key:generate --show
```

Salin keluaran (`base64:...`) ke baris `APP_KEY=` di `.env`.

---

## 4. Jalankan container

```bash
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml ps          # app, queue, scheduler, db (healthy), redis: Up
curl -I http://127.0.0.1:8090/up                # harus HTTP/1.1 200
```

---

## 5. Inisialisasi (sekali saja)

```bash
./scripts/init-production.sh
```

Skrip membuat tabel, role & hak akses, bahasa, aturan gamifikasi, level & lencana, lalu meminta data **akun super admin** (nama, username, email, kata sandi). Simpan kata sandi itu di tempat aman.

---

## 6. Pasang vhost nginx + sertifikat

**Pilihan A — pakai sertifikat wildcard yang sudah ada**

```bash
grep -R "ssl_certificate" /etc/nginx/sites-enabled/     # lihat path sertifikat wildcard
```

Ubah dua baris `ssl_certificate` & `ssl_certificate_key` di `deploy/nginx/wokabulari.poltekkes-manado.ac.id.conf` sesuai path tersebut. Ingat: sertifikat ini habis 26 Okt 2026 — perbarui di semua vhost yang memakainya.

**Pilihan B — Let's Encrypt khusus subdomain (diperpanjang otomatis)**

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d wokabulari.poltekkes-manado.ac.id --deploy-hook "systemctl reload nginx"
```

Lalu ubah dua baris sertifikat di berkas vhost menjadi:

```nginx
ssl_certificate     /etc/letsencrypt/live/wokabulari.poltekkes-manado.ac.id/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/wokabulari.poltekkes-manado.ac.id/privkey.pem;
```

**Pasang & muat ulang nginx (kedua pilihan):**

```bash
sudo cp deploy/nginx/wokabulari.poltekkes-manado.ac.id.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/wokabulari.poltekkes-manado.ac.id.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

`nginx -t` wajib `syntax is ok` sebelum reload agar situs lain di server tidak ikut terganggu.

---

## 7. Verifikasi

1. Buka <https://wokabulari.poltekkes-manado.ac.id> → halaman login dengan logo Poltekkes & BP3MI, ikon gembok di browser.
2. Login dengan akun super admin.
3. Dari HP: buka menu Absen → browser meminta izin **kamera** dan **lokasi** (bila tidak muncul, periksa HTTPS/sertifikat).
4. Cek proses latar:
   ```bash
   docker compose -f compose.prod.yaml exec app php artisan schedule:list
   docker compose -f compose.prod.yaml logs queue --tail=50
   ```
5. Pasang pemantau uptime (mis. UptimeRobot) ke `https://wokabulari.poltekkes-manado.ac.id/up`.

---

## 8. Data awal program (oleh admin, lewat aplikasi)

1. **Lokasi**: titik kampus & asrama sebenarnya (koordinat + radius) — dipakai validasi absen.
2. **Angkatan**, **Kelas** (bahasa, pengajar), **Asrama & Kamar**, **Vendor** konsumsi.
3. **Pengguna & Tim**: akun pengajar, tim kerja (konsumsi, asrama, kesehatan), pimpinan.
4. **Peserta**: import dari Excel/CSV. Berkas data peserta **tidak ada di Git**; salin ke server lewat `scp`, lalu:
   ```bash
   docker compose -f compose.prod.yaml cp peserta.xlsx app:/tmp/peserta.xlsx
   docker compose -f compose.prod.yaml exec app php artisan app:import-participants /tmp/peserta.xlsx --kelas=1
   docker compose -f compose.prod.yaml exec app rm /tmp/peserta.xlsx
   rm peserta.xlsx
   ```
   `--kelas` = ID kelas tujuan (lihat di menu Kelas). Template kolom: menu Peserta → unduh template.
5. **Wali**: menu Wali → Undang wali (kode/tautan dikirim lewat WhatsApp bila nomor diisi).

> `TrialSeeder` (data contoh + akun demo berkata sandi `password`) **jangan** dijalankan di server ini karena dapat diakses publik.

---

## 9. Update aplikasi

```bash
./scripts/deploy.sh
```

Mengambil kode terbaru, build ulang, menjalankan migrasi, membersihkan cache, dan me-restart antrean. Data di volume (database, berkas unggahan) tidak hilang.

Kembali ke versi sebelumnya: `git checkout <commit-atau-tag> && ./scripts/deploy.sh` (migrasi database tidak dibalik otomatis — buat cadangan sebelum update besar).

Setelah mengubah `.env`: `docker compose -f compose.prod.yaml up -d && docker compose -f compose.prod.yaml exec app php artisan optimize`.

---

## 10. Cadangan & pemulihan

```bash
./scripts/backup.sh       # → /opt/backup/wokabulari/db-*.sql.gz & files-*.tar.gz (disimpan 14 hari)
```

Jadwalkan harian lewat `crontab -e`:

```cron
0 1 * * * cd /opt/wokabulari && ./scripts/backup.sh >> /var/log/wokabulari-backup.log 2>&1
```

Salin juga hasil cadangan ke luar server (NAS kampus / penyimpanan awan). Cadangan berisi data pribadi & selfie — simpan di tempat berizin terbatas.

Pemulihan:

```bash
gunzip -c /opt/backup/wokabulari/db-YYYYMMDD-HHMM.sql.gz \
  | docker compose -f compose.prod.yaml exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
cat /opt/backup/wokabulari/files-YYYYMMDD-HHMM.tar.gz \
  | docker compose -f compose.prod.yaml exec -T app tar -C /var/www/html/storage/app -xzf -
```

Uji pemulihan minimal sekali sebelum program berjalan.

---

## 11. Masalah umum

| Gejala | Periksa |
|---|---|
| **502 Bad Gateway** | `docker compose -f compose.prod.yaml ps`; `... logs app --tail=100`; `APP_PORT` di `.env` = port di vhost |
| Subdomain menampilkan aplikasi lain | vhost belum aktif: `ls /etc/nginx/sites-enabled/`, `sudo nginx -t`, reload |
| Kamera/lokasi tidak muncul di HP | harus HTTPS dengan sertifikat sah (cek tanggal kedaluwarsa) |
| Unggahan gagal (413) | `client_max_body_size 64m` di vhost |
| Notifikasi tidak masuk | `... logs queue` |
| Pengingat/penutupan absen tidak jalan | `... logs scheduler`, `schedule:list` |
| Halaman error 500 | `docker compose -f compose.prod.yaml exec app sh -c 'tail -n 100 storage/logs/laravel-*.log'` |

---

## 12. Keamanan

- `.env` dan `.env.db` `chmod 600`, tidak pernah di-commit.
- Hanya `127.0.0.1:8090` yang dibuka container. Pastikan: `sudo ss -ltnp | grep -E ':3306|:6379'` tidak menampilkan proses milik Docker Wokabulari.
- `APP_DEBUG=false`, `FACE_VERIFICATION_ENABLED=true`, `GEOFENCE_ENABLED=true`.
- Tidak perlu membuka port firewall baru (80/443 sudah dipakai nginx host).

---

## Berkas terkait

| Berkas | Fungsi |
|---|---|
| `compose.prod.yaml` | definisi container produksi |
| `docker/app/Dockerfile` (target `prod`) | image aplikasi (PHP 8.4 + nginx internal) |
| `.env.production.example`, `.env.db.example` | contoh konfigurasi |
| `deploy/nginx/wokabulari.poltekkes-manado.ac.id.conf` | vhost nginx host |
| `scripts/init-production.sh` | inisialisasi pertama |
| `scripts/deploy.sh` | update rutin |
| `scripts/backup.sh` | cadangan database & berkas |
