# Sistem Monitoring Kualitas Udara Berbasis IoT (ESP32 + Laravel)

Alat pemantau kualitas udara yang membaca debu (PM1.0, PM2.5, PM10), gas, suhu, kelembapan, dan tekanan udara, lalu menampilkannya di LCD dan mengirimkannya ke web dashboard Laravel secara hampir real time lewat REST API.

## Daftar Isi

1. [Fitur](#fitur)
2. [Cara Kerja](#cara-kerja)
3. [Perangkat Keras](#perangkat-keras)
4. [Kebutuhan Perangkat Lunak](#kebutuhan-perangkat-lunak)
5. [Instalasi Web Laravel](#instalasi-web-laravel)
6. [Konfigurasi dan Upload Kode ESP32](#konfigurasi-dan-upload-kode-esp32)
7. [Menjalankan Sistem](#menjalankan-sistem)
8. [Dokumentasi API](#dokumentasi-api)
9. [Struktur Database](#struktur-database)
10. [Perhitungan ISPU](#perhitungan-ispu)
11. [Struktur File Penting](#struktur-file-penting)
12. [Pemecahan Masalah](#pemecahan-masalah)
13. [Keterbatasan dan Catatan](#keterbatasan-dan-catatan)
14. [Rencana Pengembangan](#rencana-pengembangan)

---

## Fitur

- Pembacaan 3 jenis sensor: PMS5003 (partikel debu), MQ135 (gas, ekuivalen CO2), BME280 (suhu, kelembapan, tekanan).
- Perhitungan indeks kualitas udara (ISPU) langsung di alat, mengambil parameter terburuk sebagai nilai akhir.
- LCD 16x2 I2C dengan 4 halaman otomatis bergantian, dilengkapi ikon untuk suhu, kelembapan, dan tekanan.
- Pengiriman data ke server Laravel lewat Wi-Fi (HTTP POST berformat JSON).
- Data tersimpan di database MySQL.
- Dashboard web yang memperbarui diri otomatis: gauge ISPU, kartu sensor berikon, label kategori, banner peringatan, indikator alat terhubung atau terputus, dan status tiap sensor.

## Cara Kerja

```
┌──────────┐   HTTP POST (JSON)    ┌───────────────┐   simpan   ┌─────────┐
│  ESP32   │ ────────────────────► │  Laravel API  │ ─────────► │  MySQL  │
│ + sensor │   /api/sensor         │               │            └─────────┘
└──────────┘   tiap 3 detik        └───────┬───────┘
                                           │ JSON
                                           ▼
                                   GET /api/sensor/latest
                                           ▲
                                           │ tanya tiap 2 detik
                                    ┌──────┴───────┐
                                    │  Dashboard   │
                                    │  (browser)   │
                                    └──────────────┘
```

1. ESP32 membaca sensor, menghitung ISPU, lalu mengirim hasilnya ke `POST /api/sensor` setiap 3 detik.
2. Laravel memvalidasi data dan menyimpannya sebagai satu baris baru di tabel `sensor_readings`.
3. Dashboard memanggil `GET /api/sensor/latest` setiap 2 detik (teknik polling) dan memperbarui tampilan tanpa memuat ulang halaman.

Data tidak langsung dikirim dari ESP32 ke database. Semuanya melewati API sebagai perantara, sehingga database tetap terlindungi oleh validasi.

## Perangkat Keras

| Komponen | Fungsi |
|---|---|
| ESP32 DevKit V1 | Mikrokontroler utama dengan Wi-Fi |
| PMS5003 | Sensor partikel debu PM1.0, PM2.5, PM10 |
| MQ135 | Sensor gas kualitas udara |
| BME280 | Sensor suhu, kelembapan, tekanan |
| LCD 16x2 I2C | Tampilan lokal di alat |
| Catu daya 5V | Power bank atau modul step down |
| Resistor 10k dan 20k | Pembagi tegangan untuk keluaran analog MQ135 |

### Pin Mapping

| Sensor | Pin Sensor | Pin ESP32 |
|---|---|---|
| MQ135 | AO | GPIO36 |
| MQ135 | DO | GPIO34 |
| PMS5003 | TX | GPIO16 (RX2) |
| PMS5003 | RX | GPIO17 (TX2) |
| BME280 | SDA, SCL | GPIO21, GPIO22 |
| LCD I2C | SDA, SCL | GPIO21, GPIO22 (satu jalur I2C dengan BME280) |

Catatan wiring:
- Keluaran analog MQ135 (AO) bertegangan sampai 5V, sedangkan pin ESP32 maksimal 3,3V. Pasang pembagi tegangan 10k dan 20k. Nilai ini sesuai `VOLT_DIV_FACTOR = 1.5` di kode.
- GPIO36 termasuk ADC1, jadi pembacaan gas tetap aman saat Wi-Fi menyala.
- Alamat LCD default `0x27`. Jika layar tidak menyala, ganti ke `0x3F` pada `LCD_ADDR`.

## Kebutuhan Perangkat Lunak

**Komputer server (menjalankan Laravel)**
- PHP 8.2 atau lebih baru (dikembangkan dan diuji pada PHP 8.5) dengan ekstensi `mbstring`, `xml`, `curl`, `zip`, `mysql`
- Composer
- Node.js dan npm
- MySQL atau MariaDB

**Arduino IDE (untuk ESP32)**
- Board package ESP32 (Espressif)
- Library lewat Library Manager:
  - Adafruit BME280 Library
  - Adafruit Unified Sensor
  - LiquidCrystal I2C (Frank de Brabander)
- Library `WiFi` dan `HTTPClient` sudah bawaan board ESP32, tidak perlu dipasang.

## Instalasi Web Laravel

Contoh perintah untuk Ubuntu atau Debian. Di Windows, paket seperti Laragon sudah menyediakan PHP, MySQL, dan Composer sekaligus.

### 1. Pasang kebutuhan dasar

```bash
sudo apt update
sudo apt install php php-cli php-mbstring php-xml php-curl php-zip php-mysql unzip curl composer nodejs npm
```

### 2. Siapkan proyek

Masuk ke folder proyek, lalu:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Buat database kosong di MySQL

```bash
sudo mysql -e "CREATE DATABASE monitoring_udara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Nama database bebas, asal sama dengan isi `.env` pada langkah berikutnya.

### 4. Atur koneksi database di `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monitoring_udara
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` dengan akun MySQL di komputer tersebut.

### 5. Buat tabel

```bash
php artisan migrate
```

Perintah ini membuat tabel `sensor_readings` dan tabel bawaan Laravel secara otomatis. Tidak perlu mengimpor file SQL.

### 6. Jalankan server

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Opsi `--host=0.0.0.0` wajib agar server bisa dijangkau ESP32 dan perangkat lain di jaringan yang sama. Tanpa opsi ini, server hanya melayani komputer itu sendiri.

Buka `http://127.0.0.1:8000` di browser. Dashboard akan tampil dengan keterangan "Menunggu data dari alat" sampai data pertama masuk.

Jika Ubuntu memakai firewall, buka portnya:

```bash
sudo ufw allow 8000/tcp
```

## Konfigurasi dan Upload Kode ESP32

### 1. Cari alamat IP komputer server

```bash
hostname -I
```

Ambil alamat berbentuk `192.168.x.x` atau `10.x.x.x`.

### 2. Ubah tiga baris di bagian atas kode

```cpp
#define WIFI_SSID   "NAMA_WIFI"
#define WIFI_PASS   "PASSWORD_WIFI"
#define SERVER_URL  "http://IP_KOMPUTER:8000/api/sensor"
```

Syarat penting:
- ESP32 dan komputer server harus berada di jaringan Wi-Fi yang sama.
- ESP32 hanya mendukung Wi-Fi 2,4 GHz.
- Jangan memakai `localhost` atau `127.0.0.1` di `SERVER_URL`, karena itu akan menunjuk ke ESP32 sendiri.

### 3. Pengaturan lain (opsional)

| Pengaturan | Bawaan | Fungsi |
|---|---|---|
| `SEND_INTERVAL_MS` | 3000 | Jeda kirim data ke server. Naikkan ke 5000 sampai 10000 jika alat menyala berhari-hari |
| `WIFI_RETRY_MS` | 10000 | Jeda mencoba sambung ulang Wi-Fi |
| `HTTP_TIMEOUT_MS` | 2500 | Batas tunggu server agar alat tidak macet |
| `PREHEAT_SECONDS` | 60 | Lama pemanasan MQ135 sebelum kalibrasi |
| `USE_AUTO_CALIB` | true | Kalibrasi gas otomatis saat alat dinyalakan |

### 4. Upload

Pilih board **ESP32 Dev Module**, pilih port, lalu upload. Buka Serial Monitor dengan baud rate **115200**.

Saat alat menyala, urutannya:
1. LCD menampilkan tulisan pembuka, lalu pemeriksaan BME280.
2. Wi-Fi mulai tersambung di latar belakang selama pemanasan MQ135 (60 detik).
3. Kalibrasi gas. Pastikan alat berada di udara bersih pada tahap ini.
4. LCD menampilkan status Wi-Fi beserta alamat IP ESP32.
5. Alat mulai membaca sensor dan mengirim data.

## Menjalankan Sistem

Urutan yang disarankan setiap kali memakai:

1. Nyalakan MySQL.
2. Di folder proyek, jalankan `php artisan serve --host=0.0.0.0 --port=8000`.
3. Nyalakan ESP32 dan tunggu hingga pemanasan dan kalibrasi selesai.
4. Pastikan Serial Monitor menampilkan `[KIRIM] HTTP 201` setiap 3 detik.
5. Buka `http://IP_KOMPUTER:8000` di browser komputer maupun HP yang berada di jaringan sama.

Tanda sistem berjalan benar: titik status di dashboard berwarna hijau dengan tulisan "Live", dan angka berubah mengikuti sensor.

## Dokumentasi API

Semua endpoint berawalan `/api`. Tambahkan header `Accept: application/json` pada setiap permintaan agar kesalahan validasi dibalas sebagai JSON, bukan dialihkan ke halaman web.

### `POST /api/sensor`

Menyimpan satu pembacaan sensor. Dipanggil oleh ESP32.

**Contoh permintaan**

```bash
curl -X POST http://127.0.0.1:8000/api/sensor \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"temperature":35.8,"humidity":53,"pressure":1003,"pm1":26,"pm25":38,"pm10":47,"gas_ppm":400,"ispu":78,"dominan":"PM2.5","kategori":"Sedang"}'
```

**Balasan sukses** (`201 Created`)

```json
{ "message": "Data tersimpan", "id": 1 }
```

**Aturan validasi.** Semua kolom boleh kosong (`null`). Sensor yang sedang error dikirim sebagai `null`.

| Kolom | Aturan |
|---|---|
| `temperature` | angka |
| `humidity` | angka, 0 sampai 100 |
| `pressure` | angka |
| `pm1`, `pm25`, `pm10` | bilangan bulat, minimal 0 |
| `gas_ppm` | angka, minimal 0 |
| `ispu` | bilangan bulat, minimal 0 |
| `dominan` | teks, maksimal 10 karakter |
| `kategori` | teks, maksimal 20 karakter |

Jika ada yang tidak valid, server membalas `422` beserta rincian kolom yang salah.

### `GET /api/sensor/latest`

Mengambil satu pembacaan paling baru. Dipanggil oleh dashboard.

**Contoh balasan**

```json
{
  "id": 2,
  "temperature": 35.8,
  "humidity": 53,
  "pressure": 1003,
  "pm1": 26,
  "pm25": 38,
  "pm10": 47,
  "gas_ppm": 400,
  "ispu": 78,
  "dominan": "PM2.5",
  "kategori": "Sedang",
  "created_at": "2026-10-08T06:52:33.000000Z",
  "updated_at": "2026-10-08T06:52:33.000000Z"
}
```

Jika tabel masih kosong, balasannya `null`.

Waktu `created_at` tersimpan dalam UTC. Dashboard mengubahnya ke jam lokal browser saat ditampilkan.

## Struktur Database

Tabel `sensor_readings`:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint, primary key | Nomor urut |
| `temperature` | float, null | Suhu (°C) |
| `humidity` | float, null | Kelembapan (%) |
| `pressure` | float, null | Tekanan (hPa) |
| `pm1` | smallint, null | PM1.0 (µg/m³) |
| `pm25` | smallint, null | PM2.5 (µg/m³) |
| `pm10` | smallint, null | PM10 (µg/m³) |
| `gas_ppm` | float, null | Gas MQ135 (ppm, ekuivalen CO2) |
| `ispu` | smallint, null | Indeks ISPU gabungan |
| `dominan` | varchar(10), null | Parameter terburuk: PM2.5, PM10, atau Gas |
| `kategori` | varchar(20), null | Kategori ISPU |
| `created_at`, `updated_at` | timestamp | Waktu data diterima |

Perkiraan pertumbuhan data pada interval kirim 3 detik adalah sekitar 28.800 baris per hari.

## Perhitungan ISPU

Dihitung di ESP32 dengan interpolasi linear antar batas (breakpoint). Indeks dihitung terpisah untuk PM2.5, PM10, dan gas. Nilai ISPU akhir adalah yang **paling tinggi**, dan parameter pemenangnya ditampilkan sebagai "dominan".

**PM2.5 (µg/m³)**

| Kategori | Rentang konsentrasi | Indeks |
|---|---|---|
| Baik | 0 – 15,5 | 0 – 50 |
| Sedang | 15,6 – 55,4 | 51 – 100 |
| Tidak Sehat | 55,5 – 150,4 | 101 – 200 |
| Sangat Tidak Sehat | 150,5 – 250,4 | 201 – 300 |
| Berbahaya | > 250,4 | > 300 |

**PM10 (µg/m³)**

| Kategori | Rentang konsentrasi | Indeks |
|---|---|---|
| Baik | 0 – 50 | 0 – 50 |
| Sedang | 51 – 150 | 51 – 100 |
| Tidak Sehat | 151 – 350 | 101 – 200 |
| Sangat Tidak Sehat | 351 – 420 | 201 – 300 |
| Berbahaya | > 420 | > 300 |

**Gas (ppm, ekuivalen CO2)**: batas 400, 700, 1000, 2000, 5000, 10000 dipetakan ke indeks 0, 50, 100, 200, 300, 500.

Catatan: pembacaan gas dibatasi minimal 400 ppm sebagai kondisi udara bersih, sehingga nilai 400 ppm yang konstan adalah hal normal. Nilai naik hanya ketika sensor terpapar gas atau asap.

Jika PMS5003 tidak mengirim data, indeks PM dianggap 0 dan ISPU hanya dipengaruhi gas. Dashboard menandai sensor itu sebagai error pada indikator status sensor.

## Struktur File Penting

```
monitoring-udara/
├── app/
│   ├── Http/Controllers/
│   │   ├── DashboardController.php        # menampilkan halaman dashboard
│   │   └── Api/SensorController.php       # endpoint store dan latest
│   └── Models/SensorReading.php           # model tabel sensor_readings
├── database/migrations/
│   └── ..._create_sensor_readings_table.php
├── resources/views/
│   └── dashboard.blade.php                # tampilan dashboard (HTML, CSS, JS)
├── routes/
│   ├── web.php                            # route halaman dashboard ( / )
│   └── api.php                            # route API (/api/sensor)
└── .env                                   # pengaturan lokal (JANGAN dibagikan)
```

Kode ESP32 (`monitoring_udara_wifi.ino`) berada terpisah di folder Arduino.

## Pemecahan Masalah

### Kode balasan di Serial Monitor ESP32

| Tampilan | Arti | Yang dicek |
|---|---|---|
| `[KIRIM] HTTP 201` | Sukses | Tidak ada masalah |
| `[KIRIM] HTTP -1` | Server tidak terjangkau | IP salah atau berubah, beda jaringan Wi-Fi, firewall, server belum memakai `--host=0.0.0.0`, atau Wi-Fi memblokir antar perangkat |
| `[KIRIM] HTTP 404` | Alamat tidak ditemukan | Periksa `SERVER_URL`, harus diakhiri `/api/sensor` |
| `[KIRIM] HTTP 422` | Data ditolak validasi | Periksa isi JSON, nilai di luar batas |
| `[KIRIM] HTTP 500` | Error di Laravel | Lihat `storage/logs/laravel.log` |

Langkah cepat untuk `-1`: sambungkan HP ke Wi-Fi yang sama, lalu buka `http://IP_KOMPUTER:8000` di browser HP. Jika dashboard tidak muncul di HP, masalahnya ada di jaringan atau firewall, bukan di kode ESP32. Beberapa jaringan kampus atau publik memblokir komunikasi antar perangkat. Gunakan hotspot HP sebagai jaringan bersama jika begitu.

### Masalah lain

| Masalah | Penyebab dan solusi |
|---|---|
| Dashboard tetap "Menunggu data" | Belum ada baris di tabel. Kirim data uji lewat curl, atau cek Serial Monitor |
| Titik status merah, "Alat terputus" | Tidak ada data baru lebih dari 15 detik. Cek alat, Wi-Fi, dan server |
| Tampilan dashboard tidak berubah setelah diedit | Refresh paksa dengan `Ctrl+Shift+R` |
| Error `could not find driver` | Pasang ekstensi PHP MySQL: `sudo apt install php-mysql` |
| Error `Access denied for user` | Periksa `DB_USERNAME` dan `DB_PASSWORD` di `.env` |
| Error `Unknown column` saat menyimpan | Migration dijalankan sebelum isinya lengkap. Jalankan `php artisan migrate:rollback` lalu `php artisan migrate` |
| Halaman `/api/...` menampilkan jejak error panjang | Alamat salah. Pastikan `/api/sensor/latest` |
| Karakter aneh `^[[200~` saat paste di terminal | Artefak paste. Ketik ulang perintahnya |
| LCD tidak menyala | Ganti `LCD_ADDR` dari `0x27` ke `0x3F`, periksa SDA dan SCL |
| LCD menampilkan `BME280 ERROR` | Periksa kabel I2C. Alamat BME280 yang dicoba adalah `0x76` lalu `0x77` |
| LCD menampilkan `PMS5003 ERROR` | Periksa TX dan RX (harus bersilang) dan catu 5V |
| Gas selalu 400 ppm | Normal di udara bersih. Uji dengan mendekatkan asap atau alkohol |
| Suhu terbaca lebih tinggi dari ruangan | BME280 terlalu dekat dengan ESP32, regulator, atau MQ135 yang panas. Jauhkan posisinya |

## Keterbatasan dan Catatan

- **Endpoint API belum diamankan.** Siapa pun yang mengetahui alamat `/api/sensor` dapat mengirim data palsu. Aman dipakai di jaringan rumah atau lab yang tertutup, tetapi **jangan dibuka ke internet** sebelum diberi autentikasi (API key).
- **Bukan real time murni.** Jeda yang terlihat di dashboard bisa mencapai sekitar 5 detik pada kasus terburuk (jeda kirim 3 detik ditambah jeda polling 2 detik). Sensor PMS5003 sendiri memperbarui data sekitar sekali per detik.
- **Grafik kecil di dashboard hanya berisi data selama halaman terbuka.** Setelah halaman dimuat ulang, grafik mulai dari kosong. Riwayat penuh tersimpan di database tetapi belum ditampilkan.
- **Data tidak disimpan saat koneksi putus.** Selama Wi-Fi atau server mati, pembacaan di periode itu tidak tercatat di database. LCD tetap menampilkan data langsung dari sensor.
- **Database tidak dibersihkan otomatis.** Tabel akan terus bertambah selama alat menyala.
- **Sensor MQ135 memberi perkiraan.** Nilai ppm adalah ekuivalen CO2 hasil kurva umum dan bukan hasil pengukuran terkalibrasi laboratorium. Gunakan untuk melihat tren dan perbandingan, bukan angka mutlak.
- **Alamat IP komputer server bisa berubah** ketika router diganti atau komputer tersambung ulang. Jika alat tiba-tiba gagal mengirim, periksa IP lalu sesuaikan `SERVER_URL`. Untuk pemakaian tetap, atur IP statis atau DHCP reservation di router.
- **Jangan menyertakan file `.env`** saat membagikan proyek. File itu berisi kata sandi database dan kunci aplikasi. Bagikan `.env.example` saja.

## Rencana Pengembangan

- Pengamanan endpoint dengan API key
- Halaman dan grafik riwayat (misalnya 1 jam, 24 jam, 7 hari) dengan endpoint riwayat di Laravel
- Pembersihan otomatis data lama (misalnya simpan 30 hari terakhir)
- Pembaruan benar-benar real time dengan WebSocket (Laravel Reverb) atau MQTT
- Penyimpanan sementara di alat saat koneksi putus
- Penempatan server di hosting atau VPS beralamat tetap (dengan HTTPS)
- Fitur prediksi kualitas udara dari data riwayat