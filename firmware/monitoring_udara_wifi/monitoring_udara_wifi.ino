/*
 * ============================================================
 *  SISTEM MONITORING KUALITAS UDARA BERBASIS IoT (ESP32)
 * ============================================================
 *  Sensor : PMS5003 (PM1.0 / PM2.5 / PM10)
 *           MQ135   (gas kualitas udara)
 *           BME280  (suhu, kelembapan, tekanan)
 *  Output : LCD 16x2 I2C (5 halaman otomatis bergantian)
 *           + kirim data ke web Laravel lewat WiFi (HTTP POST JSON)
 *
 *  PIN MAPPING
 *    MQ135   : AO = GPIO39 (lewat pembagi tegangan), DO = GPIO34
 *    PMS5003 : TX sensor -> RX2 (GPIO16), RX sensor -> TX2 (GPIO17)
 *    BME280  : SDA = GPIO21, SCL = GPIO22
 *    LCD     : SDA = GPIO21, SCL = GPIO22 (satu jalur I2C dengan BME280)
 *
 *  LIBRARY (Library Manager Arduino IDE)
 *    - Adafruit BME280 Library
 *    - Adafruit Unified Sensor
 *    - LiquidCrystal I2C (Frank de Brabander)
 *    - WiFi & HTTPClient: bawaan board ESP32
 *
 *  CATATAN
 *    Isi SSID, password WiFi, dan alamat server di bagian
 *    "KONFIGURASI WIFI & SERVER" sebelum upload.
 *    Jangan upload file ini ke GitHub setelah data asli diisi.
 * ============================================================
 */

#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Adafruit_Sensor.h>
#include <Adafruit_BME280.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ============================================================
//  KONFIGURASI
// ============================================================

// ----- WiFi & server (WAJIB DIISI) --------------------------
#define WIFI_SSID    "ROBOTECH TADULAKO 2026"
#define WIFI_PASS    "NANOPAGOTOJEMBER"
#define SERVER_HOST  "192.168.1.39"    // IP laptop/server Laravel (cek ulang dengan ipconfig)
#define SERVER_PORT  5000
#define SERVER_PATH  "/api/sensor"

// ----- Pin --------------------------------------------------
#define MQ_AO_PIN   39
#define MQ_DO_PIN   34
#define PMS_RX_PIN  16   // RX2 ESP32 <- TX PMS5003
#define PMS_TX_PIN  17   // TX2 ESP32 -> RX PMS5003
#define I2C_SDA     21
#define I2C_SCL     22

// ----- LCD --------------------------------------------------
#define LCD_ADDR    0x27   // ganti 0x3F jika LCD tidak menyala
#define LCD_COLS    16
#define LCD_ROWS    2

// ----- MQ135 ------------------------------------------------
#define MQ_VCC            5.0       // tegangan suplai modul MQ135 (V)
#define MQ_RL             10000.0   // resistor beban modul (ohm), umumnya 10k
#define VOLT_DIV_FACTOR   1.5       // pembagi AO (10k atas + 20k bawah => 1.5)
                                    // JANGAN 1.0: AO MQ135 bisa 5V, pin ESP32 maks 3.3V
#define PREHEAT_SECONDS   60        // pemanasan sebelum kalibrasi
#define USE_AUTO_CALIB    true      // true = kalibrasi R0 otomatis di udara bersih
#define R0_MANUAL         20000.0   // dipakai jika kalibrasi otomatis dimatikan/gagal
#define DO_ACTIVE_LOW     true      // kebanyakan modul MQ: DO = LOW saat gas melewati ambang
#define GAS_PPM_MIN       400.0     // batas bawah (udara bersih)
#define GAS_PPM_MAX       10000.0

// Kurva MQ135 untuk CO2 ekuivalen: ppm = PARA * (Rs/R0)^PARB
#define MQ_PARA   116.6020682
#define MQ_PARB   -2.769034857

// ----- Waktu ------------------------------------------------
#define SENSOR_INTERVAL_MS   1000    // baca BME280 & MQ135
#define LCD_REFRESH_MS       500     // refresh isi LCD
#define PAGE_INTERVAL_MS     3000    // ganti halaman LCD
#define PMS_TIMEOUT_MS       5000    // PMS dianggap error jika tak ada data selama ini
#define BME_RETRY_MS         10000   // coba inisialisasi ulang BME280 jika error
#define SMOOTHING_ALPHA      0.3     // 0..1, makin kecil makin halus

// ----- Pengiriman data --------------------------------------
#define SEND_INTERVAL_MS   3000     // kirim data tiap 3 detik
#define WIFI_RETRY_MS      10000     // coba sambung ulang WiFi jika terputus
#define HTTP_TIMEOUT_MS    10000      // batas tunggu server, supaya alat tidak macet

// ----- Debug ------------------------------------------------
#define DEBUG_MQ   false             // true = cetak nilai mentah MQ135 tiap detik

// ----- Ikon LCD ---------------------------------------------
// Slot 0 sengaja TIDAK dipakai karena kode 0 dianggap akhir string di C
#define ICON_TEMP  ((char)1)
#define ICON_HUM   ((char)2)
#define ICON_PRES  ((char)3)
#define ICON_DEG   ((char)223)   // simbol derajat bawaan LCD

const byte iconTemp[8] = {0b00100, 0b01010, 0b01010, 0b01010, 0b01110, 0b11111, 0b11111, 0b01110};
const byte iconHum[8]  = {0b00100, 0b00100, 0b01010, 0b01010, 0b10001, 0b10001, 0b10001, 0b01110};
const byte iconPres[8] = {0b00000, 0b01110, 0b10001, 0b10101, 0b10101, 0b10001, 0b01110, 0b00000};

// ============================================================
//  OBJEK & VARIABEL GLOBAL
// ============================================================
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
Adafruit_BME280   bme;

struct AirData {
  // BME280
  bool     bmeOk = false;
  float    temp = 0, hum = 0, pres = 0;
  // PMS5003
  bool     pmsOk = false;
  uint16_t pm1 = 0, pm25 = 0, pm10 = 0;
  // MQ135
  bool     gasInit = false;     // true setelah pembacaan pertama (untuk smoothing)
  float    gasPpm = GAS_PPM_MIN;
  float    gasRatio = 0;
  bool     gasAlert = false;
  // Hasil perhitungan
  int         idxPM25 = 0, idxPM10 = 0, idxGas = 0;
  int         ispu = 0;
  const char* dominan = "-";
  const char* kategori = "-";
} data;

float         mqR0 = R0_MANUAL;
unsigned long lastPmsFrame = 0;

// Status jaringan
int           lastHttpCode = 0;      // 0 = belum pernah kirim
bool          everSent = false;
bool          dataReady = false;     // true setelah siklus baca sensor pertama

// Timer
unsigned long tSensor = 0, tLcd = 0, tPage = 0;
unsigned long tSend = 0, tWifi = 0, tBme = 0;

// Halaman LCD
uint8_t page = 0;
const uint8_t TOTAL_PAGE = 5;

// Deklarasi awal fungsi (dipakai sebelum didefinisikan)
const char* kategoriISPU(int v);

// ============================================================
//  LCD
// ============================================================

// Cetak 1 baris + padding spasi supaya sisa karakter lama terhapus
// (tanpa lcd.clear(), jadi LCD tidak berkedip)
void lcdLine(uint8_t row, const char* text) {
  char buf[LCD_COLS + 1];
  uint8_t i = 0;
  while (i < LCD_COLS && text[i] != '\0') { buf[i] = text[i]; i++; }
  while (i < LCD_COLS) buf[i++] = ' ';
  buf[LCD_COLS] = '\0';
  lcd.setCursor(0, row);
  lcd.print(buf);
}

void showPage() {
  char l1[32], l2[32];

  switch (page) {
    case 0:  // Suhu, kelembapan, tekanan (pakai ikon)
      if (data.bmeOk) {
        snprintf(l1, sizeof(l1), "%c%.1f%cC %c%.0f%%",
                 ICON_TEMP, data.temp, ICON_DEG, ICON_HUM, data.hum);
        snprintf(l2, sizeof(l2), "%c%.0f hPa", ICON_PRES, data.pres);
      } else {
        snprintf(l1, sizeof(l1), "BME280 ERROR");
        snprintf(l2, sizeof(l2), "Cek kabel I2C");
      }
      break;

    case 1:  // Partikulat
      if (data.pmsOk) {
        snprintf(l1, sizeof(l1), "PM2.5:%u ug/m3", data.pm25);
        snprintf(l2, sizeof(l2), "PM1:%u PM10:%u", data.pm1, data.pm10);
      } else {
        snprintf(l1, sizeof(l1), "PMS5003 ERROR");
        snprintf(l2, sizeof(l2), "Cek TX/RX & 5V");
      }
      break;

    case 2:  // Gas
      snprintf(l1, sizeof(l1), "Gas:%.0f ppm", data.gasPpm);
      if (data.gasAlert) snprintf(l2, sizeof(l2), "!! GAS TINGGI !!");
      else               snprintf(l2, sizeof(l2), "%s", kategoriISPU(data.idxGas));
      break;

    case 3:  // Ringkasan indeks kualitas udara
      snprintf(l1, sizeof(l1), "ISPU:%d (%s)", data.ispu, data.dominan);
      snprintf(l2, sizeof(l2), "%s", data.kategori);
      break;

    default: // Status jaringan
      if (WiFi.status() == WL_CONNECTED) {
        snprintf(l1, sizeof(l1), "WiFi:%d dBm", WiFi.RSSI());
      } else {
        snprintf(l1, sizeof(l1), "WiFi: terputus");
      }
      if (!everSent)                              snprintf(l2, sizeof(l2), "Kirim: belum");
      else if (lastHttpCode == 200 || lastHttpCode == 201)
                                                  snprintf(l2, sizeof(l2), "Kirim: OK (%d)", lastHttpCode);
      else                                        snprintf(l2, sizeof(l2), "Kirim: GAGAL %d", lastHttpCode);
      break;
  }

  lcdLine(0, l1);
  lcdLine(1, l2);
}

// ============================================================
//  PMS5003  (frame 32 byte: 0x42 0x4D ... checksum)
// ============================================================
bool readPMS() {
  static uint8_t buf[32];
  static uint8_t idx = 0;
  bool newFrame = false;

  while (Serial2.available()) {
    uint8_t b = Serial2.read();

    // Cari header 0x42 0x4D
    if (idx == 0) {
      if (b == 0x42) buf[idx++] = b;
      continue;
    }
    if (idx == 1) {
      if (b == 0x4D)      buf[idx++] = b;
      else if (b == 0x42) { buf[0] = b; idx = 1; }   // header baru dimulai
      else                idx = 0;
      continue;
    }

    buf[idx++] = b;

    if (idx == 32) {
      idx = 0;
      uint16_t sum = 0;
      for (uint8_t i = 0; i < 30; i++) sum += buf[i];
      uint16_t chk = ((uint16_t)buf[30] << 8) | buf[31];

      if (sum == chk) {
        // Nilai atmosferik (CF=atm)
        data.pm1  = ((uint16_t)buf[10] << 8) | buf[11];
        data.pm25 = ((uint16_t)buf[12] << 8) | buf[13];
        data.pm10 = ((uint16_t)buf[14] << 8) | buf[15];
        lastPmsFrame = millis();
        newFrame = true;
      }
    }
  }

  data.pmsOk = (lastPmsFrame != 0) && ((millis() - lastPmsFrame) < PMS_TIMEOUT_MS);
  return newFrame;
}

// ============================================================
//  MQ135
// ============================================================
float readMQVoltage() {
  uint32_t sum = 0;
  const uint8_t N = 20;
  for (uint8_t i = 0; i < N; i++) {
    sum += analogReadMilliVolts(MQ_AO_PIN);
    delay(2);
  }
  float vPin = (sum / (float)N) / 1000.0;   // tegangan di pin ESP32
  return vPin * VOLT_DIV_FACTOR;            // tegangan asli di AO sensor
}

float calcRs(float vout) {
  if (vout < 0.01) vout = 0.01;
  if (vout > MQ_VCC - 0.01) vout = MQ_VCC - 0.01;
  return ((MQ_VCC - vout) / vout) * MQ_RL;
}

void calibrateMQ() {
  // Pemanasan dengan hitung mundur di LCD (WiFi tersambung di latar belakang)
  lcdLine(0, "Pemanasan MQ135");
  for (int s = PREHEAT_SECONDS; s > 0; s--) {
    char l2[24];
    snprintf(l2, sizeof(l2), "Tunggu %2d detik", s);
    lcdLine(1, l2);
    delay(1000);
  }

  mqR0 = R0_MANUAL;

  if (USE_AUTO_CALIB) {
    lcdLine(0, "Kalibrasi gas...");
    lcdLine(1, "Di udara bersih");

    float sumRs = 0;
    const uint8_t N = 30;
    for (uint8_t i = 0; i < N; i++) {
      sumRs += calcRs(readMQVoltage());
      delay(200);
    }
    float rs = sumRs / N;

    // Anggap udara bersih = ~400 ppm CO2
    float r0 = rs / pow(400.0 / MQ_PARA, 1.0 / MQ_PARB);

    if (isnan(r0) || r0 < 1000.0 || r0 > 500000.0) {
      Serial.printf("[MQ] R0 hasil kalibrasi tidak wajar (%.1f), pakai R0_MANUAL\n", r0);
      lcdLine(0, "Kalibrasi gagal");
      lcdLine(1, "Pakai nilai awal");
      delay(2000);
    } else {
      mqR0 = r0;
    }
  }

  Serial.printf("R0 MQ135 = %.1f ohm\n", mqR0);
}

void readMQ() {
  float vAO   = readMQVoltage();            // tegangan AO sensor (setelah dikali pembagi)
  float rs    = calcRs(vAO);
  float ratio = rs / mqR0;
  float ppm   = MQ_PARA * pow(ratio, MQ_PARB);

#if DEBUG_MQ
  Serial.printf("[MQ] vAO=%.3fV vPin=%.3fV Rs=%.0f R0=%.0f ratio=%.3f ppmMentah=%.0f\n",
                vAO, vAO / VOLT_DIV_FACTOR, rs, mqR0, ratio, ppm);
#endif

  if (isnan(ppm)) ppm = GAS_PPM_MIN;
  if (ppm < GAS_PPM_MIN) ppm = GAS_PPM_MIN;
  if (ppm > GAS_PPM_MAX) ppm = GAS_PPM_MAX;

  // Haluskan pembacaan (exponential moving average)
  if (!data.gasInit) {
    data.gasPpm  = ppm;
    data.gasInit = true;
  } else {
    data.gasPpm = SMOOTHING_ALPHA * ppm + (1.0 - SMOOTHING_ALPHA) * data.gasPpm;
  }
  data.gasRatio = ratio;

  bool doState = digitalRead(MQ_DO_PIN);
  data.gasAlert = DO_ACTIVE_LOW ? (doState == LOW) : (doState == HIGH);
}

// ============================================================
//  BME280
// ============================================================
bool initBME() {
  return bme.begin(0x76) || bme.begin(0x77);
}

void readBME() {
  // Jika sebelumnya error, coba hidupkan lagi secara berkala
  if (!data.bmeOk) {
    unsigned long now = millis();
    if (now - tBme >= BME_RETRY_MS) {
      tBme = now;
      data.bmeOk = initBME();
    }
    if (!data.bmeOk) return;
  }

  float t = bme.readTemperature();
  float h = bme.readHumidity();
  float p = bme.readPressure() / 100.0F;

  if (isnan(t) || isnan(h) || isnan(p)) { data.bmeOk = false; return; }
  data.temp = t;
  data.hum  = h;
  data.pres = p;
}

// ============================================================
//  PERHITUNGAN KUALITAS UDARA
// ============================================================

// Interpolasi linear antar breakpoint -> indeks (gaya ISPU/AQI)
float calcIndex(float c, const float* bp, const int* ix, uint8_t n) {
  if (c <= bp[0]) return ix[0];
  for (uint8_t i = 1; i < n; i++) {
    if (c <= bp[i]) {
      return ix[i - 1] + (c - bp[i - 1]) * (ix[i] - ix[i - 1]) / (bp[i] - bp[i - 1]);
    }
  }
  return ix[n - 1];
}

const char* kategoriISPU(int v) {
  if (v <= 50)  return "Baik";
  if (v <= 100) return "Sedang";
  if (v <= 200) return "Tidak Sehat";
  if (v <= 300) return "Sgt Tdk Sehat";
  return "Berbahaya";
}

void calculateAirQuality() {
  // Breakpoint (konsentrasi -> indeks 0..500)
  static const int   IX[]   = {0, 50, 100, 200, 300, 500};
  static const float BP25[] = {0, 15.5, 55.4, 150.4, 250.4, 500};   // PM2.5 ug/m3
  static const float BP10[] = {0, 50, 150, 350, 420, 600};          // PM10  ug/m3
  static const float BPG[]  = {400, 700, 1000, 2000, 5000, 10000};  // gas ppm (CO2 eq)

  data.idxPM25 = data.pmsOk ? (int)calcIndex(data.pm25, BP25, IX, 6) : 0;
  data.idxPM10 = data.pmsOk ? (int)calcIndex(data.pm10, BP10, IX, 6) : 0;
  data.idxGas  = (int)calcIndex(data.gasPpm, BPG, IX, 6);

  // Indeks keseluruhan = parameter terburuk (parameter dominan)
  data.ispu = data.idxPM25; data.dominan = "PM2.5";
  if (data.idxPM10 > data.ispu) { data.ispu = data.idxPM10; data.dominan = "PM10"; }
  if (data.idxGas  > data.ispu) { data.ispu = data.idxGas;  data.dominan = "Gas";  }

  data.kategori = kategoriISPU(data.ispu);
}

// ============================================================
//  WIFI & KIRIM DATA KE SERVER
// ============================================================
void startWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.persistent(false);       // jangan tulis kredensial ke flash tiap kali
  WiFi.setAutoReconnect(true);
  WiFi.setSleep(false);         // koneksi lebih stabil
  WiFi.begin(WIFI_SSID, WIFI_PASS);
}

// Sensor yang error dikirim sebagai null supaya tidak tercatat angka palsu
void appendF(char* out, size_t sz, bool ok, float v, const char* fmt) {
  size_t len = strlen(out);
  if (ok) snprintf(out + len, sz - len, fmt, v);
  else    snprintf(out + len, sz - len, "null");
}

void appendU(char* out, size_t sz, bool ok, uint16_t v) {
  size_t len = strlen(out);
  if (ok) snprintf(out + len, sz - len, "%u", v);
  else    snprintf(out + len, sz - len, "null");
}

void buildJson(char* out, size_t sz) {
  out[0] = '\0';
  strncat(out, "{\"temperature\":", sz - strlen(out) - 1);
  appendF(out, sz, data.bmeOk, data.temp, "%.1f");
  strncat(out, ",\"humidity\":", sz - strlen(out) - 1);
  appendF(out, sz, data.bmeOk, data.hum, "%.0f");
  strncat(out, ",\"pressure\":", sz - strlen(out) - 1);
  appendF(out, sz, data.bmeOk, data.pres, "%.0f");
  strncat(out, ",\"pm1\":", sz - strlen(out) - 1);
  appendU(out, sz, data.pmsOk, data.pm1);
  strncat(out, ",\"pm25\":", sz - strlen(out) - 1);
  appendU(out, sz, data.pmsOk, data.pm25);
  strncat(out, ",\"pm10\":", sz - strlen(out) - 1);
  appendU(out, sz, data.pmsOk, data.pm10);

  size_t len = strlen(out);
  snprintf(out + len, sz - len,
           ",\"gas_ppm\":%.0f,\"ispu\":%d,\"dominan\":\"%s\",\"kategori\":\"%s\"}",
           data.gasPpm, data.ispu, data.dominan, data.kategori);
}

void sendToServer() {
  if (WiFi.status() != WL_CONNECTED) return;

  char body[320];
  buildJson(body, sizeof(body));

  char url[96];
  snprintf(url, sizeof(url), "http://%s:%d%s", SERVER_HOST, SERVER_PORT, SERVER_PATH);

  HTTPClient http;
  http.setConnectTimeout(HTTP_TIMEOUT_MS);
  http.setTimeout(HTTP_TIMEOUT_MS);

  if (!http.begin(url)) {
    Serial.println("[KIRIM] URL tidak valid");
    return;
  }
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  int code = http.POST(body);
  everSent = true;
  lastHttpCode = code;

  if (code > 0) {
    Serial.printf("[KIRIM] HTTP %d\n", code);   // 201 = sukses
  } else {
    Serial.printf("[KIRIM] Gagal: %s (%d)\n", http.errorToString(code).c_str(), code);
  }
  http.end();
}

// ============================================================
//  SETUP
// ============================================================
void setup() {
  Serial.begin(115200);

  // Buffer RX diperbesar supaya data PMS tidak hilang saat HTTP POST memblokir loop
  Serial2.setRxBufferSize(512);
  Serial2.begin(9600, SERIAL_8N1, PMS_RX_PIN, PMS_TX_PIN);

  pinMode(MQ_DO_PIN, INPUT);
  analogReadResolution(12);
  analogSetPinAttenuation(MQ_AO_PIN, ADC_11db);

  // LCD
  Wire.begin(I2C_SDA, I2C_SCL);
  lcd.init();
  lcd.backlight();

  // Daftarkan ikon custom (harus setelah lcd.init())
  lcd.createChar(1, (uint8_t*)iconTemp);
  lcd.createChar(2, (uint8_t*)iconHum);
  lcd.createChar(3, (uint8_t*)iconPres);

  lcdLine(0, "Monitoring Udara");
  lcdLine(1, "IoT ESP32 ...");
  delay(2000);

  // BME280: coba alamat 0x76 lalu 0x77
  data.bmeOk = initBME();
  if (!data.bmeOk) {
    lcdLine(0, "BME280 tidak ada");
    lcdLine(1, "Cek wiring!");
    delay(2000);
  }

  // Mulai sambung WiFi. Prosesnya berjalan di latar belakang selama
  // pemanasan MQ135 (60 detik), jadi biasanya sudah terhubung saat selesai.
  startWiFi();

  calibrateMQ();

  // Buang data PMS lama yang menumpuk selama pemanasan
  while (Serial2.available()) Serial2.read();

  // Tampilkan status WiFi
  if (WiFi.status() == WL_CONNECTED) {
    lcdLine(0, "WiFi terhubung");
    lcdLine(1, WiFi.localIP().toString().c_str());
    Serial.print("IP ESP32: ");
    Serial.println(WiFi.localIP());
  } else {
    lcdLine(0, "WiFi gagal");
    lcdLine(1, "Cek SSID/pass");
    Serial.println("WiFi belum terhubung, akan dicoba ulang otomatis");
  }
  delay(2000);

  lcd.clear();
  lcdLine(0, "Sistem siap");
  lcdLine(1, "Membaca sensor..");
  delay(1000);
}

// ============================================================
//  LOOP
// ============================================================
void loop() {
  unsigned long now = millis();

  // PMS dibaca terus-menerus (data masuk tiap ~1 dtk dari sensor)
  readPMS();

  // Baca sensor lain + hitung kualitas udara
  if (now - tSensor >= SENSOR_INTERVAL_MS) {
    tSensor = now;
    readBME();
    readMQ();
    calculateAirQuality();
    dataReady = true;

    Serial.printf("T=%.1f H=%.0f P=%.0f | PM1=%u PM2.5=%u PM10=%u | Gas=%.0fppm | ISPU=%d %s (%s)\n",
                  data.temp, data.hum, data.pres,
                  data.pm1, data.pm25, data.pm10,
                  data.gasPpm, data.ispu, data.kategori, data.dominan);
  }

  // Sambung ulang WiFi jika terputus
  if (WiFi.status() != WL_CONNECTED && now - tWifi >= WIFI_RETRY_MS) {
    tWifi = now;
    Serial.println("[WiFi] Terputus, mencoba sambung ulang...");
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASS);
  }

  // Kirim data ke server Laravel (pengiriman pertama langsung, selanjutnya tiap interval)
  if (dataReady && (!everSent || now - tSend >= SEND_INTERVAL_MS)) {
    tSend = now;
    sendToServer();
  }

  // Ganti halaman LCD
  if (now - tPage >= PAGE_INTERVAL_MS) {
    tPage = now;
    page = (page + 1) % TOTAL_PAGE;
  }

  // Refresh LCD
  if (now - tLcd >= LCD_REFRESH_MS) {
    tLcd = now;
    showPage();
  }
}
