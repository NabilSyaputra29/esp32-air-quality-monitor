<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monitoring Kualitas Udara</title>
<style>
  :root{
    --bg:#0d1b1f; --panel:#13262c; --panel2:#183139;
    --line:rgba(160,200,195,.14); --text:#e8f1ee; --muted:#8aa5a1;
    color-scheme:dark;
  }
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;padding:20px;color:var(--text);background:var(--bg);
       font-family:"Avenir Next","Segoe UI",Roboto,system-ui,sans-serif;font-variant-numeric:tabular-nums}
  .wrap{max-width:1100px;margin:0 auto}
  .ic{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}

  /* Header */
  header{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
  .brand{display:flex;gap:12px;align-items:center}
  .logo{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:#2dd4bf;color:#06251f}
  .logo .ic{width:24px;height:24px}
  h1{font-size:1.2rem;margin:0;font-weight:650}
  .sub{color:var(--muted);font-size:.8rem;margin-top:2px}
  .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;
        background:var(--panel);border:1px solid var(--line);font-size:.85rem}
  .dot{width:10px;height:10px;border-radius:50%;background:#64748b;flex:none}
  .pill.on .dot{background:#22c55e;animation:pulse 1.8s infinite}
  .pill.off .dot{background:#ef4444}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 9px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}

  /* Banner peringatan */
  .banner{--c:#eab308;display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:14px;margin-bottom:14px;
          color:var(--c);border:1px solid color-mix(in srgb,var(--c) 45%,transparent);
          background:color-mix(in srgb,var(--c) 13%,transparent)}
  .banner[hidden]{display:none}
  .banner span{color:var(--text);font-size:.92rem;line-height:1.4}

  /* Hero: gauge ISPU */
  .hero{--accent:#64748b;display:grid;grid-template-columns:minmax(240px,360px) 1fr;gap:28px;align-items:center;
        padding:24px;border-radius:20px;background:var(--panel2);margin-bottom:14px;
        border:1px solid color-mix(in srgb,var(--accent) 45%,var(--line));
        box-shadow:0 0 70px -28px var(--accent);transition:box-shadow .5s,border-color .5s}
  .gauge{width:100%;display:block;overflow:visible}
  .gauge .seg{fill:none;stroke-width:14;opacity:.3;transition:opacity .4s}
  .gauge .seg.act{opacity:1}
  .gauge text{fill:var(--muted);font-size:8px;text-anchor:middle}
  #needle{color:var(--text);transform-origin:100px 100px;transition:transform .9s cubic-bezier(.2,.8,.2,1)}
  .gauge .hub{fill:var(--text)}
  .ispu-num{text-align:center;margin-top:-4px}
  .ispu-num b{display:block;font-size:3.6rem;line-height:1;color:var(--accent);transition:color .5s}
  .ispu-num span{color:var(--muted);font-size:.85rem}
  .badge{display:inline-flex;gap:8px;align-items:center;padding:8px 14px;border-radius:999px;font-weight:650;
         color:var(--accent);background:color-mix(in srgb,var(--accent) 16%,transparent);
         border:1px solid color-mix(in srgb,var(--accent) 50%,transparent)}
  .saran{margin:14px 0 10px;line-height:1.5;max-width:52ch}
  .dom{color:var(--muted);font-size:.9rem}
  .dom b{color:var(--text)}
  .tren{margin-top:16px}
  .tren small{color:var(--muted);font-size:.8rem}

  /* Kartu sensor */
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
  .card{--c:#60a5fa;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:14px}
  .card-top{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:.88rem}
  .ico{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;color:var(--c);
       background:color-mix(in srgb,var(--c) 16%,transparent);transition:color .4s,background .4s}
  .lbl{flex:1}
  .chip{font-size:.72rem;padding:3px 9px;border-radius:999px;color:var(--c);
        background:color-mix(in srgb,var(--c) 15%,transparent);border:1px solid color-mix(in srgb,var(--c) 40%,transparent)}
  .chip[hidden]{display:none}
  .val{margin:12px 0 10px;display:flex;align-items:baseline;gap:6px}
  .val b{font-size:2rem;font-weight:650}
  .val small{color:var(--muted)}
  .bar{height:6px;border-radius:99px;background:rgba(160,200,195,.16);overflow:hidden}
  .bar i{display:block;height:100%;width:0;background:var(--c);border-radius:99px;transition:width .6s,background .4s}
  .spark{width:100%;height:30px;margin-top:10px;display:block}
  .spark polyline{fill:none;stroke:var(--c);stroke-width:2;vector-effect:non-scaling-stroke;stroke-linejoin:round;stroke-linecap:round}
  .trend{height:56px;margin-top:6px}
  .trend polyline{stroke:var(--accent)}

  /* Status sensor */
  .sens{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
  .s{display:inline-flex;align-items:center;gap:7px;font-size:.8rem;padding:6px 12px;border-radius:999px;
     background:var(--panel);border:1px solid var(--line);color:var(--muted)}
  .s::before{content:"";width:8px;height:8px;border-radius:50%;background:#64748b}
  .s.ok::before{background:#22c55e}
  .s.err::before{background:#ef4444}
  .foot{margin-top:18px;color:var(--muted);font-size:.78rem}

  /* Saat alat terputus */
  main{transition:opacity .4s}
  main.stale .hero,main.stale .card{opacity:.5;filter:grayscale(.5)}

  @media (max-width:720px){
    body{padding:14px}
    .hero{grid-template-columns:1fr;gap:16px;padding:18px}
  }
  @media (prefers-reduced-motion:reduce){
    *{animation:none!important;transition:none!important}
  }
</style>
</head>
<body>

<!-- Kumpulan ikon -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-wind" viewBox="0 0 24 24"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></symbol>
  <symbol id="i-temp" viewBox="0 0 24 24"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></symbol>
  <symbol id="i-drop" viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></symbol>
  <symbol id="i-gauge" viewBox="0 0 24 24"><path d="M12 14l4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></symbol>
  <symbol id="i-dots" viewBox="0 0 24 24"><circle cx="7" cy="7" r="2" fill="currentColor" stroke="none"/><circle cx="17" cy="8" r="3" fill="currentColor" stroke="none"/><circle cx="10" cy="17" r="3.5" fill="currentColor" stroke="none"/><circle cx="19" cy="18" r="1.5" fill="currentColor" stroke="none"/></symbol>
  <symbol id="i-cloud" viewBox="0 0 24 24"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9z"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></symbol>
</svg>

<div class="wrap">
  <header>
    <div class="brand">
      <div class="logo"><svg class="ic"><use href="#i-wind"/></svg></div>
      <div>
        <h1>Monitoring Kualitas Udara</h1>
        <div class="sub">Data langsung dari alat ESP32</div>
      </div>
    </div>
    <div class="pill wait" id="pill"><span class="dot"></span><span id="statusText">Menunggu data dari alat...</span></div>
  </header>

  <div class="banner" id="banner" hidden>
    <svg class="ic" style="margin-top:2px"><use href="#i-alert"/></svg>
    <span id="bannerText"></span>
  </div>

  <main id="main">
    <section class="hero" id="hero">
      <div>
        <svg class="gauge" viewBox="0 0 200 112" aria-hidden="true">
          <g id="segs"></g>
          <text x="20" y="110">0</text>
          <text x="180" y="110">500</text>
          <g id="needle">
            <line x1="100" y1="100" x2="38" y2="100" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
          </g>
          <circle class="hub" cx="100" cy="100" r="6"/>
        </svg>
        <div class="ispu-num"><b id="ispu">--</b><span>Indeks ISPU</span></div>
      </div>
      <div>
        <div class="badge"><svg class="ic"><use href="#i-shield"/></svg><span id="kategori">Menunggu data</span></div>
        <p class="saran" id="saran">Hubungkan alat untuk melihat kondisi udara.</p>
        <div class="dom">Parameter dominan: <b id="dominan">-</b></div>
        <div class="tren">
          <small>Tren ISPU selama halaman ini terbuka</small>
          <svg class="spark trend" viewBox="0 0 100 28" preserveAspectRatio="none"><polyline id="trendLine" points=""/></svg>
        </div>
      </div>
    </section>

    <section class="grid" id="grid"></section>

    <div class="sens">
      <span class="s" id="sBme">BME280 suhu, lembap, tekanan</span>
      <span class="s" id="sPms">PMS5003 partikel debu</span>
      <span class="s" id="sMq">MQ135 gas</span>
    </div>
    <div class="foot">Halaman memperbarui diri otomatis tiap 2 detik. Kategori mengikuti standar ISPU.</div>
  </main>
</div>

<script>
  const API = '/api/sensor/latest';
  const INTERVAL_MS = 2000;     // seberapa sering ambil data
  const BATAS_TERPUTUS = 15;    // detik tanpa data baru = alat dianggap terputus
  const MAX_RIWAYAT = 40;       // jumlah titik untuk grafik kecil
  const $ = id => document.getElementById(id);

  // Kategori ISPU (batas sama dengan yang dipakai di kode ESP32)
  const LEVELS = [
    { max: 50,  nama: 'Baik',               warna: '#22c55e', saran: 'Udara bersih dan aman untuk beraktivitas di dalam maupun di luar ruangan.' },
    { max: 100, nama: 'Sedang',             warna: '#3b82f6', saran: 'Kualitas udara dapat diterima. Kelompok sensitif sebaiknya mengurangi aktivitas berat di luar ruangan.' },
    { max: 200, nama: 'Tidak Sehat',        warna: '#eab308', saran: 'Dapat mengganggu kesehatan. Kurangi aktivitas di luar ruangan dan pertimbangkan memakai masker.' },
    { max: 300, nama: 'Sangat Tidak Sehat', warna: '#ef4444', saran: 'Berisiko bagi semua orang. Hindari aktivitas di luar ruangan dan gunakan masker.' },
    { max: 500, nama: 'Berbahaya',          warna: '#a855f7', saran: 'Kondisi berbahaya. Tetap di dalam ruangan dan batasi udara luar yang masuk.' }
  ];
  const NAMA = LEVELS.map(l => l.nama), WARNA = LEVELS.map(l => l.warna);

  // Menentukan kategori sebuah nilai dari daftar batas
  function band(v, cuts, names, colors) {
    let i = cuts.findIndex(c => v <= c);
    if (i < 0) i = cuts.length;
    return { nama: names[i], warna: colors[i] };
  }

  // Daftar kartu sensor
  const KARTU = [
    { k: 'temperature', label: 'Suhu',         unit: '°C',    ikon: 'i-temp',  dec: 1, min: 10,  max: 45,
      lv: v => band(v, [20, 30, 35], ['Dingin', 'Nyaman', 'Hangat', 'Panas'], ['#38bdf8', '#22c55e', '#eab308', '#ef4444']) },
    { k: 'humidity',    label: 'Kelembapan',   unit: '%',     ikon: 'i-drop',  dec: 0, min: 0,   max: 100,
      lv: v => band(v, [30, 70], ['Kering', 'Nyaman', 'Lembap'], ['#eab308', '#22c55e', '#38bdf8']) },
    { k: 'pressure',    label: 'Tekanan',      unit: 'hPa',   ikon: 'i-gauge', dec: 0, min: 980, max: 1040, lv: null },
    { k: 'pm1',         label: 'PM1.0',        unit: 'µg/m³', ikon: 'i-dots',  dec: 0, min: 0,   max: 100,  lv: null },
    { k: 'pm25',        label: 'PM2.5',        unit: 'µg/m³', ikon: 'i-dots',  dec: 0, min: 0,   max: 150,
      lv: v => band(v, [15.5, 55.4, 150.4, 250.4], NAMA, WARNA) },
    { k: 'pm10',        label: 'PM10',         unit: 'µg/m³', ikon: 'i-dots',  dec: 0, min: 0,   max: 350,
      lv: v => band(v, [50, 150, 350, 420], NAMA, WARNA) },
    { k: 'gas_ppm',     label: 'Gas (CO₂ eq)', unit: 'ppm',   ikon: 'i-cloud', dec: 0, min: 400, max: 2000,
      lv: v => band(v, [700, 1000, 2000, 5000], NAMA, WARNA) }
  ];

  // Bangun kartu
  KARTU.forEach(c => {
    const el = document.createElement('div');
    el.className = 'card';
    el.id = 'c-' + c.k;
    el.innerHTML =
      '<div class="card-top"><span class="ico"><svg class="ic"><use href="#' + c.ikon + '"/></svg></span>' +
      '<span class="lbl">' + c.label + '</span><span class="chip" hidden></span></div>' +
      '<div class="val"><b>--</b><small>' + c.unit + '</small></div>' +
      '<div class="bar"><i></i></div>' +
      '<svg class="spark" viewBox="0 0 100 28" preserveAspectRatio="none"><polyline points=""/></svg>';
    $('grid').appendChild(el);
  });

  // Bangun busur gauge: 5 segmen sama lebar, satu per kategori
  const polar = (deg, r) => { const a = deg * Math.PI / 180; return [100 + r * Math.cos(a), 100 + r * Math.sin(a)]; };
  LEVELS.forEach((l, i) => {
    const [x1, y1] = polar(180 + i * 36 + 1.5, 80);
    const [x2, y2] = polar(180 + (i + 1) * 36 - 1.5, 80);
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', 'M' + x1.toFixed(2) + ' ' + y1.toFixed(2) + ' A80 80 0 0 1 ' + x2.toFixed(2) + ' ' + y2.toFixed(2));
    p.setAttribute('class', 'seg');
    p.setAttribute('stroke', l.warna);
    $('segs').appendChild(p);
  });
  const segEls = [...document.querySelectorAll('#segs .seg')];

  // Riwayat untuk grafik kecil (hanya selama halaman terbuka)
  const riwayat = {};
  let lastId = null;
  function tambahRiwayat(d) {
    ['temperature', 'humidity', 'pressure', 'pm1', 'pm25', 'pm10', 'gas_ppm', 'ispu'].forEach(k => {
      if (d[k] == null) return;
      (riwayat[k] = riwayat[k] || []).push(Number(d[k]));
      if (riwayat[k].length > MAX_RIWAYAT) riwayat[k].shift();
    });
  }
  function gambarSpark(poly, arr) {
    if (!arr || arr.length < 2) { poly.setAttribute('points', ''); return; }
    const lo = Math.min(...arr), hi = Math.max(...arr), rng = (hi - lo) || 1;
    poly.setAttribute('points', arr.map((v, i) =>
      (i / (arr.length - 1) * 100).toFixed(1) + ',' + (26 - ((v - lo) / rng) * 24).toFixed(1)).join(' '));
  }

  // Bagian ISPU (gauge, kategori, saran)
  function updateHero(d) {
    const v = d.ispu;
    if (v == null) return;
    let i = LEVELS.findIndex(l => v <= l.max);
    if (i < 0) i = LEVELS.length - 1;
    const lo = i ? LEVELS[i - 1].max : 0;
    const frac = Math.min(1, Math.max(0, (v - lo) / (LEVELS[i].max - lo)));
    $('needle').style.transform = 'rotate(' + ((i + frac) * 36).toFixed(1) + 'deg)';
    segEls.forEach((s, k) => s.classList.toggle('act', k === i));
    $('hero').style.setProperty('--accent', LEVELS[i].warna);
    $('ispu').textContent = v;
    $('kategori').textContent = LEVELS[i].nama;
    $('saran').textContent = LEVELS[i].saran;
    $('dominan').textContent = d.dominan || '-';
    return i;
  }

  function updateBanner(i) {
    const b = $('banner');
    if (i >= 2) {
      b.style.setProperty('--c', LEVELS[i].warna);
      $('bannerText').textContent = 'Peringatan: kualitas udara ' + LEVELS[i].nama.toLowerCase() + '. ' + LEVELS[i].saran;
      b.hidden = false;
    } else {
      b.hidden = true;
    }
  }

  function render(d) {
    const i = updateHero(d);
    if (i !== undefined) updateBanner(i);

    KARTU.forEach(c => {
      const el = $('c-' + c.k), v = d[c.k];
      const nilai = el.querySelector('.val b'), chip = el.querySelector('.chip'), bar = el.querySelector('.bar i');
      if (v == null) {            // sensor tidak mengirim nilai
        nilai.textContent = '--'; chip.hidden = true; bar.style.width = '0';
        return;
      }
      const lv = c.lv ? c.lv(Number(v)) : null;
      el.style.setProperty('--c', lv ? lv.warna : '#60a5fa');
      nilai.textContent = Number(v).toFixed(c.dec);
      chip.hidden = !lv;
      if (lv) chip.textContent = lv.nama;
      bar.style.width = (Math.min(1, Math.max(0, (v - c.min) / (c.max - c.min))) * 100).toFixed(0) + '%';
      gambarSpark(el.querySelector('.spark polyline'), riwayat[c.k]);
    });
    gambarSpark($('trendLine'), riwayat.ispu);

    $('sBme').className = 's ' + (d.temperature != null ? 'ok' : 'err');
    $('sPms').className = 's ' + (d.pm25 != null ? 'ok' : 'err');
    $('sMq').className  = 's ' + (d.gas_ppm != null ? 'ok' : 'err');
  }

  // Status koneksi alat
  const state = { err: false, lastTime: null };
  const fmtUmur = s => s < 60 ? s + ' dtk' : Math.floor(s / 60) + ' mnt';
  function renderStatus() {
    let cls = 'wait', teks = 'Menunggu data dari alat...', stale = false;
    if (state.err) {
      cls = 'off'; teks = 'Server tidak terjangkau'; stale = true;
    } else if (state.lastTime) {
      const umur = Math.max(0, Math.round((Date.now() - state.lastTime) / 1000));
      const jam = state.lastTime.toLocaleTimeString('id-ID');
      if (umur > BATAS_TERPUTUS) {
        cls = 'off'; teks = 'Alat terputus, data terakhir ' + jam + ' (' + fmtUmur(umur) + ' lalu)'; stale = true;
      } else {
        cls = 'on'; teks = 'Live, diperbarui ' + jam;
      }
    }
    $('pill').className = 'pill ' + cls;
    $('statusText').textContent = teks;
    $('main').classList.toggle('stale', stale);
  }

  async function ambil() {
    try {
      const res = await fetch(API, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const d = await res.json();
      state.err = false;
      if (!d) { state.lastTime = null; renderStatus(); return; }
      if (d.id !== lastId) { lastId = d.id; tambahRiwayat(d); }
      state.lastTime = new Date(d.created_at);
      render(d);
    } catch (e) {
      state.err = true;
    }
    renderStatus();
  }

  ambil();
  setInterval(ambil, INTERVAL_MS);
  setInterval(renderStatus, 1000);   // menjaga hitungan "x detik lalu" tetap berjalan
</script>
</body>
</html>
