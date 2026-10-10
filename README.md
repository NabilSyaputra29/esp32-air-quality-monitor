# ESP32 Air Quality Monitor

Sistem **monitoring dan prediksi kualitas udara berbasis IoT** memakai **ESP32 DevKit V1**. Alat membaca partikel debu (PM1.0, PM2.5, PM10), gas, serta suhu, kelembapan, dan tekanan udara, lalu menampilkannya secara real time di **LCD 16x2 I2C** dan mengirimkannya ke web lewat Wi-Fi.

## Fitur

- Pembacaan debu **PM1.0 / PM2.5 / PM10** (PMS5003)
- Pembacaan kadar gas udara (MQ135)
- Pembacaan **suhu, kelembapan, dan tekanan udara** (BME280)
- Seluruh data dan status kualitas udara tampil **sekaligus dalam satu layar** LCD 16x2, dengan ikon custom untuk suhu, kelembapan, dan tekanan
- Perhitungan kualitas udara secara real time
- Pengiriman data ke web lewat **Wi-Fi** (REST API: HTTP POST berformat JSON)
- Dashboard web untuk melihat data terbaru dan prediksi kualitas udara

## Komponen

| Komponen | Fungsi |
|----------|--------|
| ESP32 DevKit V1 | Mikrokontroler utama dan Wi-Fi |
| PMS5003 | Sensor partikel debu PM1.0 / PM2.5 / PM10 |
| MQ135 | Sensor gas |
| BME280 | Sensor suhu, kelembapan, dan tekanan |
| LCD 16x2 + modul I2C | Tampilan data |
| Power bank 5V (atau modul step down) | Catu daya |
| Box plastik hitam | Casing, dengan lubang ventilasi masuk dan keluar di sisi samping |

## Pin Mapping

| Perangkat | Pin Perangkat | Pin ESP32 |
|-----------|---------------|-----------|
| MQ135 | AO (analog) | GPIO 36 |
| MQ135 | DO (digital) | GPIO 34 |
| PMS5003 | TX | GPIO 16 (RX2) |
| PMS5003 | RX | GPIO 17 (TX2) |
| BME280 | SDA | GPIO 21 |
| BME280 | SCL | GPIO 22 |
| LCD I2C | SDA | GPIO 21 |
| LCD I2C | SCL | GPIO 22 |

BME280 dan LCD memakai **satu bus I2C yang sama** (GPIO 21 dan GPIO 22), jadi keduanya cukup disambung paralel pada jalur SDA dan SCL. Semua modul berbagi **GND** yang sama.

> Catatan daya: PMS5003 dan MQ135 umumnya membutuhkan catu 5V. MQ135 butuh waktu pemanasan (preheat) beberapa menit agar pembacaannya stabil.

## Library yang Dibutuhkan

Instal lewat Library Manager Arduino IDE sesuai yang di-`#include` pada sketch, umumnya:

- Library BME280 (mis. `Adafruit BME280 Library` dan `Adafruit Unified Sensor`)
- Library LCD I2C (mis. `LiquidCrystal_I2C`)
- Library pembaca PMS5003 yang dipakai pada sketch

Board: **ESP32** (Boards Manager: *esp32 by Espressif Systems*), pilih board **ESP32 Dev Module**.

## Konfigurasi

Kredensial Wi-Fi dan alamat server disimpan terpisah dari kode utama:

1. Salin `secrets.h.example` menjadi `secrets.h`.
2. Isi nama Wi-Fi, password Wi-Fi, dan alamat server (URL API) sesuai petunjuk di dalam file.
3. Upload sketch ke ESP32, lalu buka Serial Monitor untuk melihat log.

> 🔒 Jangan commit `secrets.h` yang berisi data asli ke GitHub. Hanya `secrets.h.example` yang boleh dipublikasikan.

## Pengiriman Data ke Web

ESP32 mengirim data sensor memakai **HTTP POST** dengan isi **JSON** ke API web. Dashboard menampilkan data terbaru dan diperbarui otomatis (auto-refresh). Pastikan ESP32 dan server berada pada jaringan yang dapat saling terhubung, lalu isi alamat server di `secrets.h`.

## Struktur Repositori

```
esp32-air-quality-monitor/
├── firmware/
│   └── monitoring_udara_wifi/
│       ├── monitoring_udara_wifi.ino
│       └── secrets.h.example
├── web/                  # aplikasi web (dashboard dan API)
├── .gitignore
├── LICENSE
└── README.md
```

## Pemecahan Masalah

| Gejala | Kemungkinan penyebab |
|--------|----------------------|
| LCD tidak menampilkan apa-apa | Alamat I2C salah, kabel SDA/SCL terbalik, atau kontras LCD belum diatur |
| BME280 tidak terdeteksi | Alamat I2C (0x76 / 0x77) tidak sesuai atau kabel SDA/SCL longgar |
| PM selalu 0 atau tidak berubah | TX/RX PMS5003 tidak disilang, atau catu daya 5V kurang |
| Nilai MQ135 naik turun | Sensor belum selesai preheat |
| Data tidak sampai ke web | Wi-Fi gagal tersambung atau alamat server di `secrets.h` salah |

## Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).
