# Monitoring Kualitas Udara IoT (ESP32)

Sistem monitoring kualitas udara berbasis IoT dengan ESP32. Alat membaca PM1.0/PM2.5/PM10 (PMS5003), gas (MQ135), serta suhu, kelembapan, dan tekanan (BME280). Data ditampilkan di LCD 16x2 I2C dan dikirim ke web Laravel lewat WiFi (HTTP POST JSON).

## Struktur Repo

```
esp32-air-quality-monitor/
├── firmware/
│   └── monitoring_udara_wifi/   # sketch Arduino IDE (ESP32)
│       ├── monitoring_udara_wifi.ino
│       └── secrets.h.example
├── web/                         # aplikasi web Laravel (dashboard + API)
├── .gitignore
└── README.md
```

## Perangkat Keras

| Komponen | Pin ESP32 |
|---|---|
| MQ135 AO | GPIO36 |
| MQ135 DO | GPIO34 |
| PMS5003 TX | GPIO16 (RX2) |
| PMS5003 RX | GPIO17 (TX2) |
| BME280 SDA / SCL | GPIO21 / GPIO22 |
| LCD 16x2 I2C SDA / SCL | GPIO21 / GPIO22 (satu jalur I2C dengan BME280) |

Catu daya: power bank 5V atau modul step down.

## Firmware (Arduino IDE)

Library yang dipasang lewat Library Manager:

- Adafruit BME280 Library
- Adafruit Unified Sensor
- LiquidCrystal I2C (Frank de Brabander)

`WiFi` dan `HTTPClient` sudah bawaan board ESP32.

Langkah:

1. Buka `firmware/monitoring_udara_wifi/monitoring_udara_wifi.ino` di Arduino IDE.
2. Salin `secrets.h.example` menjadi `secrets.h` (di folder yang sama), lalu isi `WIFI_SSID`, `WIFI_PASS`, dan `SERVER_URL`.
3. Pilih board **ESP32 Dev Module**, pilih port, lalu upload.

`secrets.h` sudah ada di `.gitignore`, jadi tidak ikut ke GitHub.

## Web (Laravel)

Persyaratan: PHP, Composer, MySQL, dan Node.js.

```bash
cd web
composer install
cp .env.example .env
php artisan key:generate
# atur koneksi database di .env, lalu:
php artisan migrate
php artisan serve --host=0.0.0.0
```

Isi `SERVER_URL` di `secrets.h` dengan alamat endpoint penerima data, misalnya `http://<IP-laptop>:8000/api/sensor` (sesuaikan dengan `web/routes/api.php`). ESP32 dan laptop harus berada di jaringan WiFi yang sama.

## Alur Data

ESP32 membaca sensor, menampilkan hasilnya di LCD, lalu mengirim JSON lewat HTTP POST ke API Laravel. Dashboard web menampilkan data terbaru.
