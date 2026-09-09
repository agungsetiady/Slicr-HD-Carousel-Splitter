<?php
/**
 * index.php — Split Image untuk Carousel Instagram
 * Tools sekali pakai: tidak ada gambar yang disimpan di server maupun database.
 * Upload -> proses di memori (GD) -> hasil dikirim balik ke browser -> selesai.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Slicr — HD Carousel Splitter by Aguphia</title>
<meta name="description" content="Potong gambar jadi beberapa bagian untuk carousel Instagram tanpa mengurangi kualitas. Tanpa penyimpanan, sekali pakai.">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Tailwind (Play CDN) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
        },
        colors: {
          base: {
            950: '#04050a',
            900: '#0a0d16',
            850: '#0e1220',
          },
        },
        boxShadow: {
          glow: '0 0 0 1px rgba(139,92,246,0.15), 0 8px 40px -8px rgba(99,102,241,0.35)',
        },
        keyframes: {
          floatslow: {
            '0%,100%': { transform: 'translateY(0px)' },
            '50%': { transform: 'translateY(-14px)' },
          },
          shimmer: {
            '0%': { backgroundPosition: '-200% 0' },
            '100%': { backgroundPosition: '200% 0' },
          },
        },
        animation: {
          floatslow: 'floatslow 7s ease-in-out infinite',
          shimmer: 'shimmer 2.5s linear infinite',
        },
      },
    },
  };
</script>

<!-- Boxicons -->
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<!-- Toastify -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<!-- JSZip (untuk download semua hasil sebagai .zip, dibuat di browser) -->
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Favicon SVG (Boxicons Crop) -->
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><defs><linearGradient id='grad' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' stop-color='%238b5cf6'/><stop offset='100%' stop-color='%236366f1'/></linearGradient></defs><rect width='24' height='24' rx='6' fill='url(%23grad)'/><path fill='%23ffffff' d='M19 16h-2V8a2 2 0 0 0-2-2H7V4H5v2H4v2h11v11h2v2h2v-2h2v-2zM8 8v9a1 1 0 0 0 1 1h9v-2H10V8H8z'/></svg>">
<link rel="stylesheet" href="style.css">
</head>
<body class="bg-base-950 text-slate-200 font-sans antialiased min-h-screen selection:bg-violet-500/30">

<!-- Ambient background glow -->
<div class="pointer-events-none fixed inset-0 overflow-hidden -z-10">
  <div class="absolute -top-40 -left-32 w-[32rem] h-[32rem] bg-violet-600/20 blur-[120px] rounded-full animate-floatslow"></div>
  <div class="absolute top-1/3 -right-40 w-[28rem] h-[28rem] bg-indigo-600/20 blur-[120px] rounded-full animate-floatslow" style="animation-delay:2s"></div>
  <div class="absolute bottom-0 left-1/4 w-[26rem] h-[26rem] bg-fuchsia-600/10 blur-[120px] rounded-full animate-floatslow" style="animation-delay:4s"></div>
  <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.05)_1px,transparent_0)] [background-size:28px_28px]"></div>
</div>

<div class="max-w-7xl mx-auto px-5 sm:px-8 py-10 sm:py-14">

  <!-- Header -->
  <header class="flex flex-col md:flex-row md:items-end sm:justify-between gap-4 mb-10">
    <div class="flex items-start gap-4">
      <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-glow">
        <i class='bx bx-crop text-2xl text-white'></i>
      </div>
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">Slicr — <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 to-indigo-400">HD Carousel Splitter</span></h1>
        <p class="text-slate-400 text-sm mt-1 max-w-md">Potong satu gambar jadi beberapa slice — kualitas asli tetap terjaga, tanpa resize.</p>
      </div>
    </div>
  </header>

  <!-- Step indicator -->
  <div id="stepBar" class="flex items-center gap-2 mb-8 text-xs font-medium">
    <div class="step-pill active" data-step="1"><i class='bx bx-upload'></i><span>Upload</span></div>
    <div class="step-line"></div>
    <div class="step-pill" data-step="2"><i class='bx bx-slider-alt'></i><span>Atur Potongan</span></div>
    <div class="step-line"></div>
    <div class="step-pill" data-step="3"><i class='bx bx-collection'></i><span>Hasil</span></div>
  </div>

  <!-- Main grid -->
  <div class="grid lg:grid-cols-5 gap-6">

    <!-- LEFT: Upload + Settings -->
    <div class="lg:col-span-2 flex flex-col gap-6">

      <!-- Upload card -->
      <section class="glass-card p-5">
        <h2 class="card-title"><i class='bx bx-image-add'></i> Gambar Sumber</h2>

        <div id="dropzone" class="dropzone mt-4">
          <input type="file" id="fileInput" accept="image/png, image/jpeg, image/webp" class="hidden">
          <div id="dropzoneEmpty" class="flex flex-col items-center justify-center text-center py-8 px-4 cursor-pointer">
            <div class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mb-3">
              <i class='bx bx-image-add text-2xl text-violet-300'></i>
            </div>
            <p class="text-sm font-semibold text-slate-200">Klik untuk pilih gambar</p>
            <p class="text-xs text-slate-500 mt-1">atau seret & lepas file di sini</p>
            <p class="text-[11px] text-slate-600 mt-3 font-mono">JPG · PNG · WEBP</p>
          </div>

          <div id="dropzoneFilled" class="hidden p-3">
            <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black/30">
              <img id="previewThumb" class="w-full max-h-56 object-contain" alt="Preview gambar">
              <button id="btnRemoveImage" type="button" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-black/60 hover:bg-rose-500/80 border border-white/10 flex items-center justify-center transition-colors" title="Hapus gambar">
                <i class='bx bx-x text-lg text-white'></i>
              </button>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-3 text-center">
              <div class="meta-chip"><span id="metaDims">–</span><label>Dimensi</label></div>
              <div class="meta-chip"><span id="metaSize">–</span><label>Ukuran</label></div>
              <div class="meta-chip"><span id="metaType">–</span><label>Format</label></div>
            </div>
          </div>
        </div>
      </section>

      <!-- Settings card -->
      <section id="settingsCard" class="glass-card p-5 disabled-card">
        <h2 class="card-title"><i class='bx bx-slider-alt'></i> Pengaturan Potongan</h2>

        <!-- Direction -->
        <div class="mt-4">
          <label class="field-label">Arah potongan</label>
          <div class="grid grid-cols-3 gap-2 mt-2">
            <button type="button" class="dir-btn active" data-direction="vertical">
              <i class='bx bx-menu'></i><span>Vertikal</span><small>Baris bertumpuk</small>
            </button>
            <button type="button" class="dir-btn" data-direction="horizontal">
              <i class='bx bx-columns'></i><span>Horizontal</span><small>Kolom berdampingan</small>
            </button>
            <button type="button" class="dir-btn" data-direction="grid">
              <i class='bx bx-grid-alt'></i><span>Grid</span><small>Baris × kolom</small>
            </button>
          </div>
        </div>

        <!-- Mode: quantity / size (hidden for grid) -->
        <div id="modeWrap" class="mt-5">
          <label class="field-label">Potong berdasarkan</label>
          <div class="grid grid-cols-2 gap-2 mt-2">
            <button type="button" class="mode-btn active" data-mode="quantity">Jumlah potongan</button>
            <button type="button" class="mode-btn" data-mode="size">Ukuran per blok (px)</button>
          </div>
        </div>

        <!-- Quantity control -->
        <div id="quantityWrap" class="mt-5">
          <label class="field-label" id="quantityLabel">Jumlah potongan</label>
          <div class="stepper mt-2">
            <button type="button" id="qtyMinus" class="stepper-btn"><i class='bx bx-minus'></i></button>
            <input type="number" id="quantityInput" value="2" min="2" max="10" class="stepper-input">
            <button type="button" id="qtyPlus" class="stepper-btn"><i class='bx bx-plus'></i></button>
          </div>
          <p class="hint-text mt-2">Ideal 2–10 slice untuk carousel Instagram (maks. 10 slide per post).</p>
        </div>

        <!-- Block size control -->
        <div id="blockSizeWrap" class="mt-5 hidden">
          <label class="field-label" id="blockSizeLabel">Tinggi tiap blok (px)</label>
          <input type="number" id="blockSizeInput" value="500" min="10" class="text-input mt-2">
        </div>

        <!-- Grid rows/cols -->
        <div id="gridWrap" class="mt-5 hidden">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Baris</label>
              <div class="stepper mt-2">
                <button type="button" id="rowsMinus" class="stepper-btn"><i class='bx bx-minus'></i></button>
                <input type="number" id="rowsInput" value="3" min="1" max="8" class="stepper-input">
                <button type="button" id="rowsPlus" class="stepper-btn"><i class='bx bx-plus'></i></button>
              </div>
            </div>
            <div>
              <label class="field-label">Kolom</label>
              <div class="stepper mt-2">
                <button type="button" id="colsMinus" class="stepper-btn"><i class='bx bx-minus'></i></button>
                <input type="number" id="colsInput" value="3" min="1" max="8" class="stepper-input">
                <button type="button" id="colsPlus" class="stepper-btn"><i class='bx bx-plus'></i></button>
              </div>
            </div>
          </div>
        </div>

        <!-- Overlap -->
        <div class="mt-5 flex items-center justify-between">
          <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-slate-300" for="overlapToggle">
            <input type="checkbox" id="overlapToggle" class="checkbox-custom">
            Overlap antar blok
          </label>
          <div id="overlapInputWrap" class="hidden w-24">
            <input type="number" id="overlapInput" value="20" min="0" class="text-input text-right !py-1.5">
          </div>
        </div>

        <div class="divider"></div>

        <!-- Output format -->
        <div>
          <label class="field-label">Format output</label>
          <div class="grid grid-cols-4 gap-2 mt-2">
            <button type="button" class="fmt-btn active" data-format="same">Sama</button>
            <button type="button" class="fmt-btn" data-format="png">PNG</button>
            <button type="button" class="fmt-btn" data-format="jpg">JPG</button>
            <button type="button" class="fmt-btn" data-format="webp">WEBP</button>
          </div>
        </div>

        <!-- Quality -->
        <div id="qualityWrap" class="mt-5 hidden">
          <div class="flex items-center justify-between">
            <label class="field-label mb-0">Kualitas gambar</label>
            <span id="qualityValue" class="text-xs font-mono text-violet-300">100</span>
          </div>
          <input type="range" id="qualityRange" min="1" max="100" value="100" class="range-custom mt-2">
          <p class="hint-text mt-1">100 = kualitas maksimal (disarankan untuk carousel HD).</p>
        </div>

        <button id="btnSplit" type="button" class="btn-primary w-full mt-6" disabled>
          <span class="btn-content">
            <i class='bx bx-cut text-lg'></i>
            <span>Split Gambar Sekarang</span>
          </span>
          <span class="btn-progress"></span>
        </button>
      </section>
    </div>

    <!-- RIGHT: Live Preview -->
    <div class="lg:col-span-3 flex flex-col gap-6">
      <section class="glass-card p-5 flex-1 flex flex-col">
        <div class="flex items-center justify-between">
          <h2 class="card-title mb-0"><i class='bx bx-show'></i> Preview Langsung</h2>
          <span id="pieceCountBadge" class="badge-count hidden"><i class='bx bx-collection'></i> <span id="pieceCountText">0 potongan</span></span>
        </div>

        <div id="previewStage" class="preview-stage mt-4">
          <div id="previewEmptyState" class="flex flex-col items-center justify-center h-full text-center py-16 px-6 text-slate-500">
            <i class='bx bx-image text-4xl mb-3 opacity-40'></i>
            <p class="text-sm text-center">Preview garis potongan akan muncul di sini setelah kamu upload gambar.</p>
          </div>
          <div id="previewImageWrap" class="hidden relative w-full h-full flex items-center justify-center">
            <img id="previewMain" class="max-w-full max-h-full block select-none" draggable="false" alt="Preview split">
            <div id="overlayLines" class="absolute inset-0 pointer-events-none"></div>
          </div>
        </div>
      </section>

      <!-- Results -->
      <section id="resultsSection" class="glass-card p-5 hidden">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="card-title mb-0"><i class='bx bx-collection'></i> Hasil Split <span id="resultsCount" class="text-slate-500 font-normal text-sm"></span></h2>
          <div class="flex gap-2">
            <button id="btnDownloadZip" type="button" class="btn-secondary">
              <i class='bx bx-archive-in'></i> Download Semua (.zip)
            </button>
            <button id="btnResetAll" type="button" class="btn-ghost">
              <i class='bx bx-refresh'></i> Split Gambar Lain
            </button>
          </div>
        </div>
        <div id="resultsGrid" class="results-grid mt-5"></div>
      </section>
    </div>
  </div>

  <footer class="mt-14 text-center text-xs text-slate-600">
    Powered by Aguphia
  </footer>
</div>

<script src="app.js"></script>
</body>
</html>