/* ==========================================================================
   Split Image Carousel — app.js
   Tools sekali pakai: tidak menyimpan gambar, semua diproses lalu dibuang.
   ========================================================================== */

(function ($) {
  'use strict';

  const MAX_FILE_MB = 40;
  const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  const state = {
    file: null,
    naturalWidth: 0,
    naturalHeight: 0,
    direction: 'vertical', // vertical | horizontal | grid
    mode: 'quantity',      // quantity | size
    quantity: 2,
    blockSize: 500,
    rows: 3,
    cols: 3,
    overlapEnabled: false,
    overlap: 20,
    format: 'same',
    quality: 100,
    lastResults: null,
  };

  const $dropzone = $('#dropzone');
  const $fileInput = $('#fileInput');
  const $dropzoneEmpty = $('#dropzoneEmpty');
  const $dropzoneFilled = $('#dropzoneFilled');
  const $previewThumb = $('#previewThumb');
  const $settingsCard = $('#settingsCard');
  const $previewEmptyState = $('#previewEmptyState');
  const $previewImageWrap = $('#previewImageWrap');
  const $previewMain = $('#previewMain');
  const $overlayLines = $('#overlayLines');
  const $btnSplit = $('#btnSplit');
  const $resultsSection = $('#resultsSection');
  const $resultsGrid = $('#resultsGrid');

  // ---------------------------------------------------------------------
  // Toast helper
  // ---------------------------------------------------------------------
  function toast(message, type = 'info') {
    const palettes = {
      success: 'linear-gradient(135deg,#10b981,#059669)',
      error: 'linear-gradient(135deg,#f43f5e,#e11d48)',
      info: 'linear-gradient(135deg,#8b5cf6,#6366f1)',
    };
    Toastify({
      text: message,
      duration: 3800,
      gravity: 'top',
      position: 'right',
      close: true,
      style: { background: palettes[type] || palettes.info },
    }).showToast();
  }

  function setStep(n) {
    $('.step-pill').each(function () {
      const step = parseInt($(this).data('step'), 10);
      $(this).removeClass('active done');
      if (step < n) $(this).addClass('done');
      if (step === n) $(this).addClass('active');
    });
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }

  // ---------------------------------------------------------------------
  // Upload handling
  // ---------------------------------------------------------------------
  $dropzoneEmpty.on('click', () => $fileInput.trigger('click'));

  ['dragenter', 'dragover'].forEach((evt) => {
    $dropzone.on(evt, function (e) {
      e.preventDefault(); e.stopPropagation();
      $dropzone.addClass('drag-over');
    });
  });
  ['dragleave', 'drop'].forEach((evt) => {
    $dropzone.on(evt, function (e) {
      e.preventDefault(); e.stopPropagation();
      $dropzone.removeClass('drag-over');
    });
  });
  $dropzone.on('drop', function (e) {
    const dt = e.originalEvent.dataTransfer;
    if (dt && dt.files && dt.files.length) handleFile(dt.files[0]);
  });

  $fileInput.on('change', function () {
    if (this.files && this.files[0]) handleFile(this.files[0]);
  });

  $('#btnRemoveImage').on('click', function (e) {
    e.stopPropagation();
    resetAll();
  });

  function handleFile(file) {
    if (!ALLOWED_TYPES.includes(file.type)) {
      toast('Format tidak didukung. Gunakan JPG, PNG, atau WEBP.', 'error');
      return;
    }
    if (file.size > MAX_FILE_MB * 1024 * 1024) {
      toast(`Ukuran file maksimal ${MAX_FILE_MB}MB.`, 'error');
      return;
    }

    state.file = file;
    const reader = new FileReader();
    reader.onload = function (e) {
      const img = new Image();
      img.onload = function () {
        state.naturalWidth = img.naturalWidth;
        state.naturalHeight = img.naturalHeight;

        $previewThumb.attr('src', e.target.result);
        $previewMain.attr('src', e.target.result);

        $('#metaDims').text(`${img.naturalWidth} × ${img.naturalHeight}px`);
        $('#metaSize').text(formatBytes(file.size));
        $('#metaType').text(file.type.replace('image/', '').toUpperCase());

        $dropzoneEmpty.addClass('hidden');
        $dropzoneFilled.removeClass('hidden');
        $settingsCard.removeClass('disabled-card');
        $previewEmptyState.addClass('hidden');
        $previewImageWrap.removeClass('hidden');
        $btnSplit.prop('disabled', false);

        setStep(2);
        // tunggu image benar-benar ter-render sebelum menghitung overlay
        requestAnimationFrame(() => requestAnimationFrame(renderOverlay));
        toast('Gambar berhasil dimuat. Atur pengaturan potongan di kiri.', 'success');
      };
      img.onerror = function () {
        toast('Gagal membaca gambar. Coba file lain.', 'error');
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  // ---------------------------------------------------------------------
  // Settings: direction
  // ---------------------------------------------------------------------
  $('.dir-btn').on('click', function () {
    $('.dir-btn').removeClass('active');
    $(this).addClass('active');
    state.direction = $(this).data('direction');

    const isGrid = state.direction === 'grid';
    $('#modeWrap').toggleClass('hidden', isGrid);
    $('#gridWrap').toggleClass('hidden', !isGrid);

    if (isGrid) {
      $('#quantityWrap').addClass('hidden');
      $('#blockSizeWrap').addClass('hidden');
    } else {
      $('#quantityWrap').toggleClass('hidden', state.mode !== 'quantity');
      $('#blockSizeWrap').toggleClass('hidden', state.mode !== 'size');
      updateAxisLabels();
    }
    renderOverlay();
  });

  function updateAxisLabels() {
    const isVertical = state.direction === 'vertical';
    $('#quantityLabel').text('Jumlah potongan');
    $('#blockSizeLabel').text(isVertical ? 'Tinggi tiap blok (px)' : 'Lebar tiap blok (px)');
  }

  // ---------------------------------------------------------------------
  // Settings: mode (quantity vs size)
  // ---------------------------------------------------------------------
  $('.mode-btn').on('click', function () {
    $('.mode-btn').removeClass('active');
    $(this).addClass('active');
    state.mode = $(this).data('mode');
    $('#quantityWrap').toggleClass('hidden', state.mode !== 'quantity');
    $('#blockSizeWrap').toggleClass('hidden', state.mode !== 'size');
    renderOverlay();
  });

  // ---------------------------------------------------------------------
  // Steppers: quantity, rows, cols
  // ---------------------------------------------------------------------
  function bindStepper(minusId, plusId, inputId, key, min, max) {
    const $input = $('#' + inputId);
    function clampSet(val) {
      val = Math.max(min, Math.min(max, val || min));
      $input.val(val);
      state[key] = val;
      renderOverlay();
    }
    $('#' + minusId).on('click', () => clampSet((state[key] || min) - 1));
    $('#' + plusId).on('click', () => clampSet((state[key] || min) + 1));
    $input.on('input', function () { clampSet(parseInt($(this).val(), 10)); });
  }
  bindStepper('qtyMinus', 'qtyPlus', 'quantityInput', 'quantity', 2, 10);
  bindStepper('rowsMinus', 'rowsPlus', 'rowsInput', 'rows', 1, 8);
  bindStepper('colsMinus', 'colsPlus', 'colsInput', 'cols', 1, 8);

  $('#blockSizeInput').on('input', function () {
    state.blockSize = Math.max(10, parseInt($(this).val(), 10) || 10);
    renderOverlay();
  });

  // ---------------------------------------------------------------------
  // Overlap
  // ---------------------------------------------------------------------
  $('#overlapToggle').on('change', function () {
    state.overlapEnabled = this.checked;
    $('#overlapInputWrap').toggleClass('hidden', !this.checked);
    renderOverlay();
  });
  $('#overlapInput').on('input', function () {
    state.overlap = Math.max(0, parseInt($(this).val(), 10) || 0);
    renderOverlay();
  });

  // ---------------------------------------------------------------------
  // Output format & quality
  // ---------------------------------------------------------------------
  $('.fmt-btn').on('click', function () {
    $('.fmt-btn').removeClass('active');
    $(this).addClass('active');
    state.format = $(this).data('format');
    const showQuality = state.format === 'jpg' || state.format === 'webp' ||
      (state.format === 'same' && /jpeg|webp/i.test(state.file ? state.file.type : ''));
    $('#qualityWrap').toggleClass('hidden', !showQuality);
  });

  $('#qualityRange').on('input', function () {
    state.quality = parseInt($(this).val(), 10);
    $('#qualityValue').text(state.quality);
    $(this)[0].style.setProperty('--fill', state.quality + '%');
  });

  // ---------------------------------------------------------------------
  // Live overlay preview (garis potongan di atas gambar)
  // ---------------------------------------------------------------------
  function computeSegments(total, mode, quantity, blockSize) {
    const segments = [];
    if (mode === 'size') {
      let start = 0;
      const size = Math.min(blockSize, total);
      while (start < total) {
        const len = Math.min(size, total - start);
        segments.push([start, len]);
        start += size;
      }
    } else {
      const base = Math.floor(total / quantity);
      const remainder = total % quantity;
      let cursor = 0;
      for (let i = 0; i < quantity; i++) {
        const len = base + (i < remainder ? 1 : 0);
        segments.push([cursor, len]);
        cursor += len;
      }
    }
    return segments;
  }

  function renderOverlay() {
    if (!state.naturalWidth) return;
    $overlayLines.empty();

    const imgEl = $previewMain[0];
    if (!imgEl.complete || !imgEl.offsetWidth) return;

    // Posisikan container overlay persis di atas bounding box gambar
    $overlayLines.css({
      left: imgEl.offsetLeft + 'px',
      top: imgEl.offsetTop + 'px',
      width: imgEl.offsetWidth + 'px',
      height: imgEl.offsetHeight + 'px',
    });

    let totalPieces = 0;

    if (state.direction === 'vertical') {
      const segs = computeSegments(state.naturalHeight, state.mode, state.quantity, state.blockSize);
      totalPieces = segs.length;
      let cum = 0;
      segs.forEach((seg, i) => {
        cum += seg[1];
        if (i < segs.length - 1) {
          const pct = (cum / state.naturalHeight) * 100;
          $('<div class="overlay-line horizontal-line"></div>').css('top', pct + '%').appendTo($overlayLines);
        }
      });
      addPieceBadges(segs.length, 'vertical');
    } else if (state.direction === 'horizontal') {
      const segs = computeSegments(state.naturalWidth, state.mode, state.quantity, state.blockSize);
      totalPieces = segs.length;
      let cum = 0;
      segs.forEach((seg, i) => {
        cum += seg[1];
        if (i < segs.length - 1) {
          const pct = (cum / state.naturalWidth) * 100;
          $('<div class="overlay-line vertical-line"></div>').css('left', pct + '%').appendTo($overlayLines);
        }
      });
      addPieceBadges(segs.length, 'horizontal');
    } else { // grid
      const rowSegs = computeSegments(state.naturalHeight, 'quantity', state.rows, 0);
      const colSegs = computeSegments(state.naturalWidth, 'quantity', state.cols, 0);
      totalPieces = rowSegs.length * colSegs.length;
      let cumY = 0;
      rowSegs.forEach((seg, i) => {
        cumY += seg[1];
        if (i < rowSegs.length - 1) {
          const pct = (cumY / state.naturalHeight) * 100;
          $('<div class="overlay-line horizontal-line"></div>').css('top', pct + '%').appendTo($overlayLines);
        }
      });
      let cumX = 0;
      colSegs.forEach((seg, i) => {
        cumX += seg[1];
        if (i < colSegs.length - 1) {
          const pct = (cumX / state.naturalWidth) * 100;
          $('<div class="overlay-line vertical-line"></div>').css('left', pct + '%').appendTo($overlayLines);
        }
      });
    }

    $('#pieceCountBadge').removeClass('hidden');
    $('#pieceCountText').text(totalPieces + (totalPieces === 1 ? ' potongan' : ' potongan'));
  }

  function addPieceBadges(count, direction) {
    // Nomor kecil 1..n di setiap segmen agar user paham urutan slice
    const segs = direction === 'vertical'
      ? computeSegments(state.naturalHeight, state.mode, state.quantity, state.blockSize)
      : computeSegments(state.naturalWidth, state.mode, state.quantity, state.blockSize);
    const total = direction === 'vertical' ? state.naturalHeight : state.naturalWidth;
    let cum = 0;
    segs.forEach((seg, i) => {
      const start = cum;
      cum += seg[1];
      const centerPct = ((start + seg[1] / 2) / total) * 100;
      const $badge = $('<div class="overlay-badge"></div>').text(i + 1);
      if (direction === 'vertical') {
        $badge.css({ top: centerPct + '%', left: '6px', transform: 'translateY(-50%)' });
      } else {
        $badge.css({ left: centerPct + '%', top: '6px', transform: 'translateX(-50%)' });
      }
      $overlayLines.append($badge);
    });
  }

  $(window).on('resize', () => renderOverlay());

  // ---------------------------------------------------------------------
  // Split action (AJAX)
  // ---------------------------------------------------------------------
  $btnSplit.on('click', function () {
    if (!state.file) {
      toast('Upload gambar terlebih dahulu.', 'error');
      return;
    }

    // Guard jumlah potongan maksimal (selaras dengan batas server)
    let totalPieces = 0;
    if (state.direction === 'grid') {
      totalPieces = state.rows * state.cols;
    } else {
      const total = state.direction === 'vertical' ? state.naturalHeight : state.naturalWidth;
      totalPieces = computeSegments(total, state.mode, state.quantity, state.blockSize).length;
    }
    if (totalPieces > 60) {
      toast('Jumlah potongan terlalu banyak (maks. 60). Kurangi jumlah/perbesar ukuran blok.', 'error');
      return;
    }
    if (totalPieces < 1) {
      toast('Pengaturan tidak valid, cek kembali jumlah potongan.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('image', state.file);
    formData.append('direction', state.direction);
    formData.append('mode', state.mode);
    formData.append('quantity', state.quantity);
    formData.append('block_size', state.blockSize);
    formData.append('rows', state.rows);
    formData.append('cols', state.cols);
    formData.append('overlap', state.overlapEnabled ? state.overlap : 0);
    formData.append('format', state.format);
    formData.append('quality', state.quality);

    setLoading(true);

    $.ajax({
      url: 'split.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      xhr: function () {
        const xhr = $.ajaxSettings.xhr();
        if (xhr.upload) {
          xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
              const pct = Math.min(95, Math.round((e.loaded / e.total) * 100));
              $('.btn-progress').css('width', pct + '%');
            }
          });
        }
        return xhr;
      },
      success: function (res) {
        $('.btn-progress').css('width', '100%');
        if (!res || res.ok !== true) {
          toast((res && res.error) || 'Terjadi kesalahan saat memproses gambar.', 'error');
          setLoading(false);
          return;
        }
        state.lastResults = res;
        renderResults(res);
        setLoading(false);
        setStep(3);
        toast(`Berhasil! Gambar dipotong jadi ${res.count} bagian.`, 'success');
        document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
      },
      error: function (xhr, status, errorThrown) {
        setLoading(false);
        let msg = 'Gagal terhubung ke server.';
        
        if (xhr.status === 413) {
          msg = 'Ukuran file terlalu besar untuk konfigurasi server (PHP post_max_size).';
        } else if (xhr.responseText) {
          try {
            const parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.error) msg = parsed.error;
          } catch (e) {
            msg = `Server Error (${xhr.status}): ${xhr.statusText || errorThrown}`;
          }
        }
        toast(msg, 'error');
      },
    });
  });

  function setLoading(isLoading) {
    $btnSplit.prop('disabled', isLoading).toggleClass('is-loading', isLoading);
    $btnSplit.find('.btn-content i').attr('class', isLoading ? 'bx bx-loader-alt text-lg' : 'bx bx-cut text-lg');
    $btnSplit.find('.btn-content span').text(isLoading ? 'Memproses gambar...' : 'Split Gambar Sekarang');
    if (!isLoading) {
      setTimeout(() => $('.btn-progress').css('width', '0%'), 400);
    }
  }

  // ---------------------------------------------------------------------
  // Render results
  // ---------------------------------------------------------------------
  function renderResults(res) {
    $resultsGrid.empty();
    $('#resultsCount').text(`(${res.count} file · ${res.format.toUpperCase()})`);

    res.pieces.forEach((piece) => {
      const $item = $(`
        <div class="result-item">
          <div class="thumb-wrap"><img src="${piece.dataUrl}" alt="${piece.filename}"></div>
          <div class="item-footer">
            <div>
              <div class="item-label">Slice ${piece.index + 1}</div>
              <div class="item-dims">${piece.width}×${piece.height}px</div>
            </div>
            <a class="dl-btn" href="${piece.dataUrl}" download="${piece.filename}" title="Download slice ini">
              <i class='bx bx-download'></i>
            </a>
          </div>
        </div>
      `);
      $resultsGrid.append($item);
    });

    $resultsSection.removeClass('hidden');
  }

  // ---------------------------------------------------------------------
  // Download all as ZIP (dibuat langsung di browser, tidak lewat server)
  // ---------------------------------------------------------------------
  $('#btnDownloadZip').on('click', async function () {
    if (!state.lastResults) return;
    const $btn = $(this);
    const originalHtml = $btn.html();
    $btn.html("<i class='bx bx-loader-alt bx-spin'></i> Menyiapkan ZIP...").prop('disabled', true);

    try {
      const zip = new JSZip();
      state.lastResults.pieces.forEach((piece) => {
        const base64 = piece.dataUrl.split(',')[1];
        zip.file(piece.filename, base64, { base64: true });
      });
      const blob = await zip.generateAsync({ type: 'blob' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'carousel-slices.zip';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      toast('ZIP berhasil diunduh.', 'success');
    } catch (err) {
      toast('Gagal membuat file ZIP.', 'error');
    } finally {
      $btn.html(originalHtml).prop('disabled', false);
    }
  });

  // ---------------------------------------------------------------------
  // Reset
  // ---------------------------------------------------------------------
  $('#btnResetAll').on('click', resetAll);

  function resetAll() {
    state.file = null;
    state.naturalWidth = 0;
    state.naturalHeight = 0;
    state.lastResults = null;

    $fileInput.val('');
    $dropzoneEmpty.removeClass('hidden');
    $dropzoneFilled.addClass('hidden');
    $settingsCard.addClass('disabled-card');
    $previewEmptyState.removeClass('hidden');
    $previewImageWrap.addClass('hidden');
    $overlayLines.empty();
    $('#pieceCountBadge').addClass('hidden');
    $resultsSection.addClass('hidden');
    $resultsGrid.empty();
    $btnSplit.prop('disabled', true);

    setStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // Init
  $('#qualityRange').trigger('input');
})(jQuery);