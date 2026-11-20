<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monitoring Kualitas Udara IoT — ESP32</title>
<style>
  :root {
    --bg: #081216;
    --bg-grad: radial-gradient(circle at 15% 15%, rgba(45, 212, 191, 0.08), transparent 40%),
               radial-gradient(circle at 85% 20%, rgba(56, 189, 248, 0.07), transparent 45%);
    --panel: rgba(17, 34, 41, 0.78);
    --panel-strong: rgba(21, 42, 50, 0.92);
    --panel-hover: rgba(26, 52, 62, 0.92);
    --line: rgba(148, 196, 190, 0.14);
    --line-strong: rgba(148, 196, 190, 0.25);
    --text: #eaf4f2;
    --muted: #84a4a0;
    --brand: #2dd4bf;
    color-scheme: dark;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    min-height: 100vh;
    padding: 24px 20px 40px;
    color: var(--text);
    background-color: var(--bg);
    background-image: var(--bg-grad);
    background-attachment: fixed;
    font-family: "Inter", "Avenir Next", "Segoe UI", Roboto, system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
  }

  .wrap { max-width: 1240px; margin: 0 auto; }

  .ic {
    width: 18px; height: 18px;
    fill: none; stroke: currentColor;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex: none;
  }

  /* Header */
  header {
    display: flex; flex-wrap: wrap; gap: 16px;
    align-items: center; justify-content: space-between;
    margin-bottom: 20px;
    padding: 16px 20px;
    border-radius: 18px;
    background: var(--panel);
    backdrop-filter: blur(12px);
    border: 1px solid var(--line);
    box-shadow: 0 12px 32px -16px rgba(0, 0, 0, 0.5);
  }
  .brand { display: flex; gap: 14px; align-items: center; }
  .logo {
    width: 46px; height: 46px; border-radius: 14px;
    display: grid; place-items: center;
    background: linear-gradient(135deg, #2dd4bf, #0ea5e9);
    color: #04211c;
    box-shadow: 0 8px 20px -6px rgba(45, 212, 191, 0.55);
  }
  .logo .ic { width: 25px; height: 25px; stroke-width: 2.2; }
  h1 { font-size: 1.25rem; margin: 0; font-weight: 700; letter-spacing: -0.01em; }
  .sub { color: var(--muted); font-size: 0.82rem; margin-top: 3px; display: flex; align-items: center; gap: 8px; }
  .header-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

  .stat-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 14px; border-radius: 999px;
    background: rgba(12, 25, 31, 0.65);
    border: 1px solid var(--line);
    font-size: 0.82rem; color: var(--muted);
  }
  .stat-badge b { color: var(--text); }

  .pill {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 8px 16px; border-radius: 999px;
    background: rgba(12, 25, 31, 0.8);
    border: 1px solid var(--line-strong);
    font-size: 0.84rem; font-weight: 500;
  }
  .dot { width: 10px; height: 10px; border-radius: 50%; background: #64748b; flex: none; }
  .pill.on { border-color: rgba(34, 197, 94, 0.4); background: rgba(34, 197, 94, 0.08); }
  .pill.on .dot { background: #22c55e; animation: pulse 1.8s infinite; }
  .pill.off { border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.08); }
  .pill.off .dot { background: #ef4444; }
  @keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6); }
    70% { box-shadow: 0 0 0 9px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
  }

  /* Banner peringatan */
  .banner {
    --c: #eab308;
    display: flex; gap: 12px; align-items: center;
    padding: 14px 18px; border-radius: 16px; margin-bottom: 18px;
    color: var(--c);
    border: 1px solid color-mix(in srgb, var(--c) 50%, transparent);
    background: linear-gradient(90deg, color-mix(in srgb, var(--c) 16%, transparent), color-mix(in srgb, var(--c) 6%, transparent));
    box-shadow: 0 10px 30px -15px color-mix(in srgb, var(--c) 35%, transparent);
  }
  .banner[hidden] { display: none; }
  .banner span { color: var(--text); font-size: 0.92rem; line-height: 1.45; font-weight: 500; }

  /* Top Grid: Hero ISPU + Sub-Indeks Breakdown */
  .top-row {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 16px;
    margin-bottom: 18px;
  }

  .hero {
    --accent: #64748b;
    display: grid;
    grid-template-columns: minmax(220px, 300px) 1fr;
    gap: 24px;
    align-items: center;
    padding: 24px;
    border-radius: 22px;
    background: var(--panel-strong);
    border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--line));
    box-shadow: 0 0 80px -30px var(--accent);
    transition: box-shadow 0.5s, border-color 0.5s;
    position: relative;
    overflow: hidden;
  }
  .gauge { width: 100%; display: block; overflow: visible; }
  .gauge .seg { fill: none; stroke-width: 14; opacity: 0.25; transition: opacity 0.4s; }
  .gauge .seg.act { opacity: 1; filter: drop-shadow(0 0 8px var(--accent)); }
  .gauge text { fill: var(--muted); font-size: 8px; text-anchor: middle; font-weight: 600; }
  #needle { color: var(--text); transform-origin: 100px 100px; transition: transform 0.9s cubic-bezier(0.2, 0.8, 0.2, 1); }
  .gauge .hub { fill: var(--text); }
  .ispu-num { text-align: center; margin-top: -6px; }
  .ispu-num b { display: block; font-size: 3.5rem; line-height: 1; font-weight: 800; color: var(--accent); transition: color 0.5s; }
  .ispu-num span { color: var(--muted); font-size: 0.82rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; }

  .badge {
    display: inline-flex; gap: 8px; align-items: center;
    padding: 7px 14px; border-radius: 999px; font-weight: 700; font-size: 0.92rem;
    color: var(--accent);
    background: color-mix(in srgb, var(--accent) 16%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 50%, transparent);
  }
  .saran { margin: 14px 0 12px; line-height: 1.55; color: #d5e6e3; font-size: 0.94rem; }
  .hero-meta {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
    margin-top: 14px; padding-top: 14px;
    border-top: 1px solid var(--line);
  }
  .meta-box {
    background: rgba(10, 22, 27, 0.55);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 10px 12px;
  }
  .meta-box small { display: block; color: var(--muted); font-size: 0.74rem; margin-bottom: 3px; }
  .meta-box b { font-size: 1rem; color: var(--text); }

  /* Panel Breakdown ISPU & Skala */
  .side-panel {
    background: var(--panel-strong);
    border: 1px solid var(--line);
    border-radius: 22px;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 14px;
  }
  .panel-title {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 0.95rem; font-weight: 650; margin: 0 0 4px;
  }
  .panel-sub { font-size: 0.78rem; color: var(--muted); }

  .sub-bars { display: flex; flex-direction: column; gap: 12px; }
  .sub-item {
    background: rgba(10, 22, 27, 0.5);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 10px 14px;
  }
  .sub-head { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 7px; }
  .sub-head span { color: var(--muted); font-weight: 500; }
  .sub-head b { color: var(--text); }
  .sub-track { height: 7px; border-radius: 99px; background: rgba(160, 200, 195, 0.12); overflow: hidden; }
  .sub-fill { height: 100%; width: 0%; border-radius: 99px; background: #3b82f6; transition: width 0.6s, background 0.4s; }

  .scale-legend {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px;
    padding-top: 8px; border-top: 1px solid var(--line);
  }
  .scale-pill {
    text-align: center; padding: 6px 4px; border-radius: 8px;
    font-size: 0.68rem; font-weight: 600;
    background: rgba(10, 22, 27, 0.55);
    border: 1px solid var(--line);
  }
  .scale-pill i { display: block; height: 4px; border-radius: 99px; margin-bottom: 4px; }
  .scale-pill small { display: block; color: var(--muted); font-weight: 400; font-size: 0.63rem; margin-top: 1px; }

  /* Kartu sensor (7 parameter) */
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
  }
  .card {
    --c: #38bdf8;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 16px;
    transition: transform 0.2s, border-color 0.3s, background 0.3s;
    position: relative;
    overflow: hidden;
  }
  .card:hover {
    transform: translateY(-2px);
    background: var(--panel-hover);
    border-color: color-mix(in srgb, var(--c) 45%, var(--line));
  }
  .card-top { display: flex; align-items: center; gap: 10px; color: var(--muted); font-size: 0.86rem; font-weight: 500; }
  .ico {
    width: 34px; height: 34px; border-radius: 10px;
    display: grid; place-items: center; color: var(--c);
    background: color-mix(in srgb, var(--c) 16%, transparent);
    border: 1px solid color-mix(in srgb, var(--c) 30%, transparent);
    transition: color 0.4s, background 0.4s;
  }
  .lbl { flex: 1; }
  .chip {
    font-size: 0.72rem; font-weight: 600; padding: 3px 9px; border-radius: 999px; color: var(--c);
    background: color-mix(in srgb, var(--c) 15%, transparent);
    border: 1px solid color-mix(in srgb, var(--c) 40%, transparent);
  }
  .chip[hidden] { display: none; }
  .val { margin: 14px 0 8px; display: flex; align-items: baseline; justify-content: space-between; }
  .val-main { display: flex; align-items: baseline; gap: 6px; }
  .val b { font-size: 2.1rem; font-weight: 750; letter-spacing: -0.02em; }
  .val small { color: var(--muted); font-size: 0.85rem; }
  .minmax { font-size: 0.72rem; color: var(--muted); text-align: right; line-height: 1.35; }
  .minmax span { color: var(--text); font-weight: 600; }
  .bar { height: 6px; border-radius: 99px; background: rgba(160, 200, 195, 0.14); overflow: hidden; margin-bottom: 10px; }
  .bar i { display: block; height: 100%; width: 0; background: var(--c); border-radius: 99px; transition: width 0.6s, background 0.4s; }
  .spark { width: 100%; height: 34px; display: block; overflow: visible; }
  .spark path.area { fill: color-mix(in srgb, var(--c) 18%, transparent); stroke: none; }
  .spark polyline { fill: none; stroke: var(--c); stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; stroke-linecap: round; }

  /* Bagian Grafik Analitik Utama */
  .charts-section {
    display: grid;
    grid-template-columns: 1.55fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
  }
  .chart-card {
    background: var(--panel-strong);
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 20px;
    display: flex;
    flex-direction: column;
  }
  .chart-header {
    display: flex; flex-wrap: wrap; gap: 12px;
    align-items: center; justify-content: space-between;
    margin-bottom: 14px;
  }
  .chart-tabs {
    display: inline-flex; flex-wrap: wrap; gap: 6px;
    background: rgba(10, 22, 27, 0.65);
    padding: 4px; border-radius: 12px;
    border: 1px solid var(--line);
  }
  .tab-btn {
    appearance: none; border: none; background: transparent;
    color: var(--muted); font-size: 0.78rem; font-weight: 600;
    padding: 6px 12px; border-radius: 8px; cursor: pointer;
    transition: all 0.2s;
  }
  .tab-btn:hover { color: var(--text); }
  .tab-btn.active {
    background: var(--brand);
    color: #04211c;
    box-shadow: 0 4px 12px -2px rgba(45, 212, 191, 0.45);
  }

  .chart-legend {
    display: flex; flex-wrap: wrap; gap: 14px;
    font-size: 0.78rem; color: var(--muted);
    margin-bottom: 10px;
  }
  .leg-item { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; color: var(--text); }
  .leg-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

  .svg-chart-wrap {
    position: relative;
    width: 100%;
    height: 250px;
    background: rgba(9, 20, 25, 0.5);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 10px 10px 6px 6px;
  }
  .main-svg { width: 100%; height: 100%; overflow: visible; display: block; }
  .grid-line { stroke: rgba(148, 196, 190, 0.1); stroke-dasharray: 3 3; stroke-width: 1; }
  .axis-lbl { fill: var(--muted); font-size: 10px; font-family: inherit; }
  .chart-line { fill: none; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
  .chart-area { stroke: none; opacity: 0.22; }
  .chart-dot { transition: r 0.15s; cursor: pointer; }
  .chart-dot:hover { r: 5.5; }

  .chart-tooltip {
    position: absolute;
    pointer-events: none;
    background: rgba(8, 18, 22, 0.94);
    border: 1px solid var(--line-strong);
    border-radius: 10px;
    padding: 8px 11px;
    font-size: 0.76rem;
    color: var(--text);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    z-index: 10;
    white-space: nowrap;
    transform: translate(-50%, -110%);
  }
  .chart-tooltip[hidden] { display: none; }

  /* Tabel Riwayat Pembacaan Terakhir */
  .table-wrap {
    overflow-x: auto;
    max-height: 250px;
    border-radius: 12px;
    border: 1px solid var(--line);
    background: rgba(9, 20, 25, 0.45);
  }
  table {
    width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;
  }
  th {
    position: sticky; top: 0;
    background: #10232a; color: var(--muted);
    font-weight: 600; padding: 10px 12px;
    border-bottom: 1px solid var(--line);
    white-space: nowrap;
  }
  td {
    padding: 8px 12px;
    border-bottom: 1px solid rgba(148, 196, 190, 0.07);
    white-space: nowrap;
  }
  tr:hover td { background: rgba(45, 212, 191, 0.05); }
  .tbl-badge {
    display: inline-block; padding: 2px 8px; border-radius: 99px;
    font-size: 0.7rem; font-weight: 600;
  }

  /* Status sensor & Footer */
  .footer-bar {
    display: flex; flex-wrap: wrap; gap: 12px;
    align-items: center; justify-content: space-between;
    padding: 14px 18px;
    border-radius: 16px;
    background: var(--panel);
    border: 1px solid var(--line);
  }
  .sens { display: flex; flex-wrap: wrap; gap: 8px; }
  .s {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: 0.78rem; padding: 6px 12px; border-radius: 999px;
    background: rgba(10, 22, 27, 0.65); border: 1px solid var(--line); color: var(--muted);
  }
  .s::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #64748b; }
  .s.ok { color: var(--text); border-color: rgba(34, 197, 94, 0.3); }
  .s.ok::before { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
  .s.err { border-color: rgba(239, 68, 68, 0.35); }
  .s.err::before { background: #ef4444; }
  .foot { color: var(--muted); font-size: 0.78rem; }

  /* Saat alat terputus */
  main { transition: opacity 0.4s; }
  main.stale .hero, main.stale .card { opacity: 0.65; }

  @media (max-width: 980px) {
    .top-row, .charts-section { grid-template-columns: 1fr; }
  }
  @media (max-width: 680px) {
    body { padding: 14px; }
    .hero { grid-template-columns: 1fr; gap: 16px; padding: 18px; }
    .scale-legend { grid-template-columns: repeat(2, 1fr); }
  }
</style>
</head>
<body>

<!-- Kumpulan ikon SVG -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-wind" viewBox="0 0 24 24"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></symbol>
  <symbol id="i-temp" viewBox="0 0 24 24"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></symbol>
  <symbol id="i-drop" viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></symbol>
  <symbol id="i-gauge" viewBox="0 0 24 24"><path d="M12 14l4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></symbol>
  <symbol id="i-dots" viewBox="0 0 24 24"><circle cx="7" cy="7" r="2" fill="currentColor" stroke="none"/><circle cx="17" cy="8" r="3" fill="currentColor" stroke="none"/><circle cx="10" cy="17" r="3.5" fill="currentColor" stroke="none"/><circle cx="19" cy="18" r="1.5" fill="currentColor" stroke="none"/></symbol>
  <symbol id="i-cloud" viewBox="0 0 24 24"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9z"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></symbol>
  <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 9l-5 5-4-4-3 3"/></symbol>
</svg>

<div class="wrap">
  <header>
    <div class="brand">
      <div class="logo"><svg class="ic"><use href="#i-wind"/></svg></div>
      <div>
        <h1>Monitoring Kualitas Udara IoT</h1>
        <div class="sub">Stasiun Sensor ESP32 &bull; PMS5003 &bull; BME280 &bull; MQ135</div>
      </div>
    </div>
    <div class="header-right">
      <div class="stat-badge">Sampel Riwayat: <b id="sampleCount">0</b></div>
      <div class="pill wait" id="pill"><span class="dot"></span><span id="statusText">Menunggu data dari alat...</span></div>
    </div>
  </header>

  <div class="banner" id="banner" hidden>
    <svg class="ic"><use href="#i-alert"/></svg>
    <span id="bannerText"></span>
  </div>

  <main id="main">
    <!-- Baris Atas: Gauge ISPU & Sub-Indeks -->
    <div class="top-row">
      <section class="hero" id="hero">
        <div>
          <svg class="gauge" viewBox="0 0 200 112" aria-hidden="true">
            <g id="segs"></g>
            <text x="20" y="110">0</text>
            <text x="100" y="14">250</text>
            <text x="180" y="110">500</text>
            <g id="needle">
              <line x1="100" y1="100" x2="36" y2="100" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>
            </g>
            <circle class="hub" cx="100" cy="100" r="6"/>
          </svg>
          <div class="ispu-num"><b id="ispu">--</b><span>Indeks ISPU</span></div>
        </div>
        <div>
          <div class="badge"><svg class="ic"><use href="#i-shield"/></svg><span id="kategori">Menunggu data</span></div>
          <p class="saran" id="saran">Hubungkan alat ESP32 untuk memantau indeks kualitas udara secara real-time.</p>
          <div class="hero-meta">
            <div class="meta-box">
              <small>Polutan Dominan</small>
              <b id="dominan">-</b>
            </div>
            <div class="meta-box">
              <small>Rentang ISPU Sesi Ini</small>
              <b id="ispuRange">-- / --</b>
            </div>
          </div>
        </div>
      </section>

      <section class="side-panel">
        <div>
          <div class="panel-title">
            <span>Perbandingan Sub-Indeks ISPU</span>
            <span class="panel-sub">Skala 0 – 500</span>
          </div>
          <div class="sub-bars">
            <div class="sub-item">
              <div class="sub-head"><span>Sub-Indeks PM2.5</span><b id="subPm25Val">--</b></div>
              <div class="sub-track"><div class="sub-fill" id="subPm25Bar"></div></div>
            </div>
            <div class="sub-item">
              <div class="sub-head"><span>Sub-Indeks PM10</span><b id="subPm10Val">--</b></div>
              <div class="sub-track"><div class="sub-fill" id="subPm10Bar"></div></div>
            </div>
            <div class="sub-item">
              <div class="sub-head"><span>Sub-Indeks Gas (CO₂ eq)</span><b id="subGasVal">--</b></div>
              <div class="sub-track"><div class="sub-fill" id="subGasBar"></div></div>
            </div>
          </div>
        </div>

        <div class="scale-legend">
          <div class="scale-pill"><i style="background:#22c55e"></i>Baik<small>0 - 50</small></div>
          <div class="scale-pill"><i style="background:#3b82f6"></i>Sedang<small>51 - 100</small></div>
          <div class="scale-pill"><i style="background:#eab308"></i>Tdk Sehat<small>101 - 200</small></div>
          <div class="scale-pill"><i style="background:#ef4444"></i>Sgt Tdk Sehat<small>201 - 300</small></div>
          <div class="scale-pill"><i style="background:#a855f7"></i>Berbahaya<small>301 - 500</small></div>
        </div>
      </section>
    </div>

    <!-- Kartu 7 Parameter Sensor -->
    <section class="grid" id="grid"></section>

    <!-- Bagian Grafik Analitik & Tabel Riwayat -->
    <div class="charts-section">
      <section class="chart-card">
        <div class="chart-header">
          <div>
            <div class="panel-title">Grafik Tren Real-Time</div>
            <div class="panel-sub">Menampilkan hingga 60 pembacaan terakhir dari database</div>
          </div>
          <div class="chart-tabs" id="chartTabs">
            <button class="tab-btn active" data-tab="ispu">ISPU</button>
            <button class="tab-btn" data-tab="pm">Partikulat (PM)</button>
            <button class="tab-btn" data-tab="env">Suhu &amp; Lembap</button>
            <button class="tab-btn" data-tab="gas">Gas &amp; Tekanan</button>
          </div>
        </div>

        <div class="chart-legend" id="chartLegend"></div>

        <div class="svg-chart-wrap" id="chartWrap">
          <svg class="main-svg" id="mainChart" viewBox="0 0 680 230" preserveAspectRatio="none"></svg>
          <div class="chart-tooltip" id="chartTooltip" hidden></div>
        </div>
      </section>

      <section class="chart-card">
        <div class="chart-header">
          <div>
            <div class="panel-title">Log Pembacaan Terakhir</div>
            <div class="panel-sub">Riwayat data masuk terbaru dari ESP32</div>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Waktu</th>
                <th>ISPU</th>
                <th>PM2.5</th>
                <th>PM10</th>
                <th>Gas</th>
                <th>Suhu</th>
              </tr>
            </thead>
            <tbody id="logBody">
              <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">Belum ada data</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Footer Status Sensor -->
    <div class="footer-bar">
      <div class="sens">
        <span class="s" id="sBme">BME280 (Suhu, Lembap, Tekanan)</span>
        <span class="s" id="sPms">PMS5003 (Partikel Debu PM1/2.5/10)</span>
        <span class="s" id="sMq">MQ135 (Kualitas Gas)</span>
      </div>
      <div class="foot">Pembaruan otomatis setiap 2 detik &bull; Standar ISPU Indonesia</div>
    </div>
  </main>
</div>

<script>
  const API_LATEST  = '/api/sensor/latest';
  const API_HISTORY = '/api/sensor/history?limit=60';
  const INTERVAL_MS = 2000;
  const BATAS_TERPUTUS = 45;
  const MAX_RIWAYAT = 60;
  const $ = id => document.getElementById(id);

  // Kategori ISPU
  const LEVELS = [
    { max: 50,  nama: 'Baik',               warna: '#22c55e', saran: 'Udara bersih dan aman untuk beraktivitas di dalam maupun di luar ruangan.' },
    { max: 100, nama: 'Sedang',             warna: '#3b82f6', saran: 'Kualitas udara dapat diterima. Kelompok sensitif sebaiknya mengurangi aktivitas berat di luar ruangan.' },
    { max: 200, nama: 'Tidak Sehat',        warna: '#eab308', saran: 'Dapat mengganggu kesehatan. Kurangi aktivitas di luar ruangan dan pertimbangkan memakai masker.' },
    { max: 300, nama: 'Sangat Tidak Sehat', warna: '#ef4444', saran: 'Berisiko bagi semua orang. Hindari aktivitas di luar ruangan dan gunakan masker.' },
    { max: 500, nama: 'Berbahaya',          warna: '#a855f7', saran: 'Kondisi berbahaya. Tetap di dalam ruangan dan batasi udara luar yang masuk.' }
  ];
  const NAMA = LEVELS.map(l => l.nama), WARNA = LEVELS.map(l => l.warna);

  function getIspuLevel(v) {
    let i = LEVELS.findIndex(l => v <= l.max);
    if (i < 0) i = LEVELS.length - 1;
    return { index: i, ...LEVELS[i] };
  }

  // Perhitungan sub-indeks (sama dengan rumus di ESP32)
  function calcSubIndex(c, bp) {
    if (c == null) return 0;
    const ix = [0, 50, 100, 200, 300, 500];
    if (c <= bp[0]) return ix[0];
    for (let i = 1; i < bp.length; i++) {
      if (c <= bp[i]) {
        return Math.round(ix[i - 1] + (c - bp[i - 1]) * (ix[i] - ix[i - 1]) / (bp[i] - bp[i - 1]));
      }
    }
    return ix[ix.length - 1];
  }

  function band(v, cuts, names, colors) {
    let i = cuts.findIndex(c => v <= c);
    if (i < 0) i = cuts.length;
    return { nama: names[i], warna: colors[i] };
  }

  // Daftar kartu sensor
  const KARTU = [
    { k: 'temperature', label: 'Suhu Udara',   unit: '°C',    ikon: 'i-temp',  dec: 1, min: 10,  max: 45,
      lv: v => band(v, [20, 30, 35], ['Dingin', 'Nyaman', 'Hangat', 'Panas'], ['#38bdf8', '#22c55e', '#eab308', '#ef4444']) },
    { k: 'humidity',    label: 'Kelembapan',   unit: '%',     ikon: 'i-drop',  dec: 0, min: 0,   max: 100,
      lv: v => band(v, [30, 70], ['Kering', 'Nyaman', 'Lembap'], ['#eab308', '#22c55e', '#38bdf8']) },
    { k: 'pressure',    label: 'Tekanan Udara',unit: 'hPa',   ikon: 'i-gauge', dec: 0, min: 980, max: 1040, lv: null },
    { k: 'pm1',         label: 'PM1.0',        unit: 'µg/m³', ikon: 'i-dots',  dec: 0, min: 0,   max: 100,  lv: null },
    { k: 'pm25',        label: 'PM2.5',        unit: 'µg/m³', ikon: 'i-dots',  dec: 0, min: 0,   max: 150,
      lv: v => band(v, [15.5, 55.4, 150.4, 250.4], NAMA, WARNA) },
    { k: 'pm10',        label: 'PM10',         unit: 'µg/m³', ikon: 'i-dots',  dec: 0, min: 0,   max: 350,
      lv: v => band(v, [50, 150, 350, 420], NAMA, WARNA) },
    { k: 'gas_ppm',     label: 'Gas (CO₂ eq)', unit: 'ppm',   ikon: 'i-cloud', dec: 0, min: 400, max: 2000,
      lv: v => band(v, [700, 1000, 2000, 5000], NAMA, WARNA) }
  ];

  // Bangun kartu sensor
  KARTU.forEach(c => {
    const el = document.createElement('div');
    el.className = 'card';
    el.id = 'c-' + c.k;
    el.innerHTML =
      '<div class="card-top"><span class="ico"><svg class="ic"><use href="#' + c.ikon + '"/></svg></span>' +
      '<span class="lbl">' + c.label + '</span><span class="chip" hidden></span></div>' +
      '<div class="val">' +
        '<div class="val-main"><b>--</b><small>' + c.unit + '</small></div>' +
        '<div class="minmax">Min <span class="mn">--</span><br>Max <span class="mx">--</span></div>' +
      '</div>' +
      '<div class="bar"><i></i></div>' +
      '<svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none">' +
        '<path class="area" d=""/>' +
        '<polyline points=""/>' +
      '</svg>';
    $('grid').appendChild(el);
  });

  // Bangun busur gauge ISPU
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

  // Penyimpanan riwayat data
  const records = [];
  const riwayat = {};
  let lastId = null;
  let activeChartTab = 'ispu';

  function pushRecord(d) {
    if (!d || d.id == null) return;
    if (records.some(r => r.id === d.id)) return;
    records.push(d);
    if (records.length > MAX_RIWAYAT) records.shift();

    ['temperature', 'humidity', 'pressure', 'pm1', 'pm25', 'pm10', 'gas_ppm', 'ispu'].forEach(k => {
      if (d[k] == null) return;
      (riwayat[k] = riwayat[k] || []).push(Number(d[k]));
      if (riwayat[k].length > MAX_RIWAYAT) riwayat[k].shift();
    });
    $('sampleCount').textContent = records.length;
  }

  function gambarSpark(svgEl, arr) {
    const poly = svgEl.querySelector('polyline');
    const area = svgEl.querySelector('path.area');
    if (!arr || arr.length < 2) {
      poly.setAttribute('points', '');
      if (area) area.setAttribute('d', '');
      return;
    }
    const lo = Math.min(...arr), hi = Math.max(...arr), rng = (hi - lo) || 1;
    const pts = arr.map((v, i) => {
      const x = (i / (arr.length - 1) * 100).toFixed(1);
      const y = (27 - ((v - lo) / rng) * 23).toFixed(1);
      return [x, y];
    });
    poly.setAttribute('points', pts.map(p => p.join(',')).join(' '));
    if (area) {
      const dPath = 'M0,30 L' + pts.map(p => p.join(',')).join(' L') + ' L100,30 Z';
      area.setAttribute('d', dPath);
    }
  }

  // Konfigurasi Tab Grafik Utama
  const CHART_CONFIGS = {
    ispu: {
      series: [
        { key: 'ispu', label: 'Indeks ISPU', color: '#2dd4bf', unit: '' }
      ]
    },
    pm: {
      series: [
        { key: 'pm25', label: 'PM2.5', color: '#f43f5e', unit: 'µg/m³' },
        { key: 'pm10', label: 'PM10',  color: '#f59e0b', unit: 'µg/m³' },
        { key: 'pm1',  label: 'PM1.0', color: '#38bdf8', unit: 'µg/m³' }
      ]
    },
    env: {
      series: [
        { key: 'temperature', label: 'Suhu (°C)',       color: '#fb923c', unit: '°C' },
        { key: 'humidity',    label: 'Kelembapan (%)',  color: '#38bdf8', unit: '%' }
      ]
    },
    gas: {
      series: [
        { key: 'gas_ppm',  label: 'Gas CO₂ eq (ppm)', color: '#a855f7', unit: 'ppm' },
        { key: 'pressure', label: 'Tekanan (hPa)',    color: '#22c55e', unit: 'hPa' }
      ]
    }
  };

  // Event ganti tab grafik
  document.querySelectorAll('#chartTabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#chartTabs .tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeChartTab = btn.dataset.tab;
      renderMainChart();
    });
  });

  // Render Grafik Analitik Utama (SVG Interaktif)
  function renderMainChart() {
    const cfg = CHART_CONFIGS[activeChartTab];
    const svg = $('mainChart');
    const legend = $('chartLegend');

    legend.innerHTML = cfg.series.map(s =>
      '<span class="leg-item"><i class="leg-dot" style="background:' + s.color + '"></i>' + s.label + '</span>'
    ).join('');

    if (records.length === 0) {
      svg.innerHTML = '<text x="340" y="120" text-anchor="middle" fill="#84a4a0" font-size="13">Menunggu data untuk menampilkan grafik...</text>';
      return;
    }

    const W = 680, H = 230;
    const padL = 44, padR = 16, padT = 16, padB = 28;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;

    // Kumpulkan semua nilai dari series aktif untuk menentukan skala Y
    let allVals = [];
    cfg.series.forEach(s => {
      records.forEach(r => {
        if (r[s.key] != null) allVals.push(Number(r[s.key]));
      });
    });
    if (allVals.length === 0) allVals = [0, 100];

    let minV = Math.min(...allVals);
    let maxV = Math.max(...allVals);
    if (minV === maxV) { minV = Math.max(0, minV - 10); maxV = maxV + 10; }
    else {
      const pad = (maxV - minV) * 0.12;
      minV = Math.max(0, Math.floor(minV - pad));
      maxV = Math.ceil(maxV + pad);
    }
    const rngV = (maxV - minV) || 1;

    const xOf = idx => records.length === 1 ? padL + plotW / 2 : padL + (idx / (records.length - 1)) * plotW;
    const yOf = val => padT + plotH - ((val - minV) / rngV) * plotH;

    let html = '<defs>';
    cfg.series.forEach((s, idx) => {
      html += '<linearGradient id="grad-' + idx + '" x1="0" y1="0" x2="0" y2="1">' +
                '<stop offset="0%" stop-color="' + s.color + '" stop-opacity="0.45"/>' +
                '<stop offset="100%" stop-color="' + s.color + '" stop-opacity="0.0"/>' +
              '</linearGradient>';
    });
    html += '</defs>';

    // Garis grid horizontal & label sumbu Y
    const steps = 4;
    for (let i = 0; i <= steps; i++) {
      const val = minV + (rngV * (steps - i) / steps);
      const y = (padT + (plotH * i / steps)).toFixed(1);
      html += '<line class="grid-line" x1="' + padL + '" y1="' + y + '" x2="' + (W - padR) + '" y2="' + y + '"/>';
      html += '<text class="axis-lbl" x="' + (padL - 8) + '" y="' + (Number(y) + 3) + '" text-anchor="end">' + Math.round(val) + '</text>';
    }

    // Label waktu sumbu X
    const xTicks = Math.min(6, records.length);
    for (let i = 0; i < xTicks; i++) {
      const idx = xTicks === 1 ? 0 : Math.round(i * (records.length - 1) / (xTicks - 1));
      const r = records[idx];
      if (!r || !r.created_at) continue;
      const t = new Date(r.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      const x = xOf(idx).toFixed(1);
      html += '<text class="axis-lbl" x="' + x + '" y="' + (H - 6) + '" text-anchor="middle">' + t + '</text>';
    }

    // Gambar kurva tiap series
    cfg.series.forEach((s, sIdx) => {
      const pts = [];
      records.forEach((r, idx) => {
        if (r[s.key] == null) return;
        pts.push({ x: xOf(idx), y: yOf(Number(r[s.key])), val: Number(r[s.key]), time: r.created_at });
      });
      if (pts.length === 0) return;

      if (pts.length >= 2) {
        const polyStr = pts.map(p => p.x.toFixed(1) + ',' + p.y.toFixed(1)).join(' ');
        if (cfg.series.length === 1) {
          const areaStr = 'M' + pts[0].x.toFixed(1) + ',' + (padT + plotH) + ' L' +
                          pts.map(p => p.x.toFixed(1) + ',' + p.y.toFixed(1)).join(' L') +
                          ' L' + pts[pts.length - 1].x.toFixed(1) + ',' + (padT + plotH) + ' Z';
          html += '<path d="' + areaStr + '" fill="url(#grad-' + sIdx + ')"/>';
        }
        html += '<polyline class="chart-line" stroke="' + s.color + '" points="' + polyStr + '"/>';
      }

      // Titik data interaktif
      pts.forEach(p => {
        const jam = p.time ? new Date(p.time).toLocaleTimeString('id-ID') : '';
        html += '<circle class="chart-dot" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) + '" r="3.2" fill="' + s.color + '" ' +
                'data-label="' + s.label + '" data-val="' + p.val + ' ' + s.unit + '" data-time="' + jam + '"/>';
      });
    });

    svg.innerHTML = html;

    // Tooltip interaktif saat hover titik
    const tooltip = $('chartTooltip');
    svg.querySelectorAll('.chart-dot').forEach(dot => {
      dot.addEventListener('mouseenter', e => {
        const rect = $('chartWrap').getBoundingClientRect();
        const dotRect = e.target.getBoundingClientRect();
        tooltip.innerHTML = '<b>' + dot.dataset.label + ': ' + dot.dataset.val + '</b><br><span style="color:var(--muted)">Pukul ' + dot.dataset.time + '</span>';
        tooltip.style.left = (dotRect.left - rect.left + dotRect.width / 2) + 'px';
        tooltip.style.top = (dotRect.top - rect.top) + 'px';
        tooltip.hidden = false;
      });
      dot.addEventListener('mouseleave', () => { tooltip.hidden = true; });
    });
  }

  // Render Tabel Log Pembacaan Terakhir
  function renderLogTable() {
    const tbody = $('logBody');
    if (records.length === 0) return;
    const recent = [...records].reverse().slice(0, 15);
    tbody.innerHTML = recent.map(r => {
      const jam = r.created_at ? new Date(r.created_at).toLocaleTimeString('id-ID') : '-';
      const lv = getIspuLevel(r.ispu ?? 0);
      return '<tr>' +
        '<td style="color:var(--muted)">' + jam + '</td>' +
        '<td><span class="tbl-badge" style="color:' + lv.warna + ';background:color-mix(in srgb,' + lv.warna + ' 16%,transparent);border:1px solid color-mix(in srgb,' + lv.warna + ' 40%,transparent)">' + (r.ispu ?? '-') + ' (' + lv.nama + ')</span></td>' +
        '<td>' + (r.pm25 ?? '-') + ' <small style="color:var(--muted)">µg</small></td>' +
        '<td>' + (r.pm10 ?? '-') + ' <small style="color:var(--muted)">µg</small></td>' +
        '<td>' + (r.gas_ppm != null ? Number(r.gas_ppm).toFixed(0) : '-') + ' <small style="color:var(--muted)">ppm</small></td>' +
        '<td>' + (r.temperature != null ? Number(r.temperature).toFixed(1) + '°C' : '-') + '</td>' +
      '</tr>';
    }).join('');
  }

  // Update Hero & Sub-Indeks
  function updateHero(d) {
    const v = d.ispu;
    if (v == null) return;
    const lv = getIspuLevel(v);
    const i = lv.index;
    const lo = i ? LEVELS[i - 1].max : 0;
    const frac = Math.min(1, Math.max(0, (v - lo) / (LEVELS[i].max - lo)));
    $('needle').style.transform = 'rotate(' + ((i + frac) * 36).toFixed(1) + 'deg)';
    segEls.forEach((s, k) => s.classList.toggle('act', k === i));
    $('hero').style.setProperty('--accent', lv.warna);
    $('ispu').textContent = v;
    $('kategori').textContent = lv.nama;
    $('saran').textContent = lv.saran;
    $('dominan').textContent = d.dominan || '-';

    if (riwayat.ispu && riwayat.ispu.length > 0) {
      $('ispuRange').textContent = Math.min(...riwayat.ispu) + ' – ' + Math.max(...riwayat.ispu);
    }

    // Hitung & tampilkan sub-indeks PM2.5, PM10, Gas
    const idx25 = calcSubIndex(d.pm25, [0, 15.5, 55.4, 150.4, 250.4, 500]);
    const idx10 = calcSubIndex(d.pm10, [0, 50, 150, 350, 420, 600]);
    const idxGas = calcSubIndex(d.gas_ppm, [400, 700, 1000, 2000, 5000, 10000]);

    const setSub = (valId, barId, val) => {
      const subLv = getIspuLevel(val);
      $(valId).textContent = val + ' (' + subLv.nama + ')';
      $(barId).style.width = Math.min(100, Math.round((val / 500) * 100)) + '%';
      $(barId).style.background = subLv.warna;
    };
    setSub('subPm25Val', 'subPm25Bar', idx25);
    setSub('subPm10Val', 'subPm10Bar', idx10);
    setSub('subGasVal',  'subGasBar',  idxGas);

    return i;
  }

  function updateBanner(i) {
    const b = $('banner');
    if (i >= 2) {
      b.style.setProperty('--c', LEVELS[i].warna);
      $('bannerText').textContent = 'Peringatan Kualitas Udara (' + LEVELS[i].nama + '): ' + LEVELS[i].saran;
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
      const mnEl = el.querySelector('.mn'), mxEl = el.querySelector('.mx');
      if (v == null) {
        nilai.textContent = '--'; chip.hidden = true; bar.style.width = '0';
        return;
      }
      const lv = c.lv ? c.lv(Number(v)) : null;
      el.style.setProperty('--c', lv ? lv.warna : '#38bdf8');
      nilai.textContent = Number(v).toFixed(c.dec);
      chip.hidden = !lv;
      if (lv) chip.textContent = lv.nama;
      bar.style.width = (Math.min(1, Math.max(0, (v - c.min) / (c.max - c.min))) * 100).toFixed(0) + '%';

      const arr = riwayat[c.k];
      if (arr && arr.length > 0) {
        mnEl.textContent = Math.min(...arr).toFixed(c.dec);
        mxEl.textContent = Math.max(...arr).toFixed(c.dec);
      }
      gambarSpark(el.querySelector('.spark'), arr);
    });

    renderMainChart();
    renderLogTable();

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
        cls = 'off'; teks = 'Alat terputus • Terakhir ' + jam + ' (' + fmtUmur(umur) + ' lalu)'; stale = true;
      } else {
        cls = 'on'; teks = 'Live • Diperbarui ' + jam;
      }
    }
    $('pill').className = 'pill ' + cls;
    $('statusText').textContent = teks;
    $('main').classList.toggle('stale', stale);
  }

  async function initHistory() {
    try {
      const res = await fetch(API_HISTORY, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) return;
      const list = await res.json();
      if (Array.isArray(list) && list.length > 0) {
        list.forEach(item => pushRecord(item));
        const latest = list[list.length - 1];
        lastId = latest.id;
        state.lastTime = new Date(latest.created_at);
        render(latest);
        renderStatus();
      }
    } catch (e) {}
  }

  async function ambil() {
    try {
      const res = await fetch(API_LATEST, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const d = await res.json();
      state.err = false;
      if (!d) { state.lastTime = null; renderStatus(); return; }
      if (d.id !== lastId) {
        lastId = d.id;
        pushRecord(d);
        render(d);
      }
      state.lastTime = new Date(d.created_at);
    } catch (e) {
      state.err = true;
    }
    renderStatus();
  }

  initHistory().then(() => {
    ambil();
    setInterval(ambil, INTERVAL_MS);
    setInterval(renderStatus, 1000);
  });
</script>
</body>
</html>
