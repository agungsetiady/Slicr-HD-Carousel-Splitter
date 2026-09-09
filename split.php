<?php
/**
 * split.php
 * Menerima gambar via AJAX (multipart/form-data), memotongnya sesuai opsi
 * (vertikal / horizontal / grid) menggunakan GD, lalu mengembalikan setiap
 * potongan sebagai base64 JSON. Tidak ada file yang disimpan permanen ke disk;
 * file upload sementara PHP langsung dihapus setelah diproses.
 */

header('Content-Type: application/json; charset=utf-8');

// ---- Batas ukuran & error handling dasar ---------------------------------
ini_set('display_errors', '0');
ini_set('memory_limit', '512M'); // Menaikkan batas memori untuk pemrosesan GD HD
error_reporting(E_ALL);

// Tangkap Fatal Error (seperti OOM atau Undefined Function) agar mengembalikan JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false, 
            'error' => 'Server Error: ' . $error['message']
        ]);
    }
});

function respond_error(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('Metode tidak diizinkan. Gunakan POST.', 405);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $map = [
        UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas server (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form.',
        UPLOAD_ERR_PARTIAL   => 'File hanya terunggah sebagian, coba lagi.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diunggah.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk temporary.',
        UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP.',
    ];
    respond_error($map[$uploadErr] ?? 'Gagal mengunggah file.');
}

$tmpPath = $_FILES['image']['tmp_name'];

// Validasi tipe gambar sesungguhnya (bukan hanya dari nama file)
$imageInfo = @getimagesize($tmpPath);
if ($imageInfo === false) {
    @unlink($tmpPath);
    respond_error('File yang diunggah bukan gambar yang valid.');
}

$origWidth  = $imageInfo[0];
$origHeight = $imageInfo[1];
$imageType  = $imageInfo[2];

$allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if (!in_array($imageType, $allowedTypes, true)) {
    @unlink($tmpPath);
    respond_error('Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.');
}

// ---- Ambil opsi dari request ----------------------------------------------
$direction   = $_POST['direction']   ?? 'vertical';   // vertical | horizontal | grid
$mode        = $_POST['mode']        ?? 'quantity';   // quantity | size
$quantity    = max(1, min(20, (int)($_POST['quantity'] ?? 2)));
$blockSize   = max(1, (int)($_POST['block_size'] ?? 500));
$rows        = max(1, min(10, (int)($_POST['rows'] ?? 3)));
$cols        = max(1, min(10, (int)($_POST['cols'] ?? 3)));
$overlap     = max(0, min(500, (int)($_POST['overlap'] ?? 0)));
$outputFmt   = $_POST['format'] ?? 'same'; // same | png | jpg | webp
$quality     = max(1, min(100, (int)($_POST['quality'] ?? 100)));

if (!in_array($direction, ['vertical', 'horizontal', 'grid'], true)) {
    $direction = 'vertical';
}
if (!in_array($mode, ['quantity', 'size'], true)) {
    $mode = 'quantity';
}

// ---- Muat gambar sumber ke GD (lossless, resource sepenuhnya di memori) --
switch ($imageType) {
    case IMAGETYPE_JPEG:
        $srcImage = @imagecreatefromjpeg($tmpPath);
        $srcExt = 'jpg';
        break;
    case IMAGETYPE_PNG:
        $srcImage = @imagecreatefrompng($tmpPath);
        $srcExt = 'png';
        break;
    case IMAGETYPE_WEBP:
        $srcImage = @imagecreatefromwebp($tmpPath);
        $srcExt = 'webp';
        break;
    default:
        $srcImage = false;
        $srcExt = 'png';
}

// File sementara sudah tidak dibutuhkan lagi setelah dimuat ke memori
@unlink($tmpPath);

if (!$srcImage) {
    respond_error('Gagal memproses gambar. File mungkin rusak atau ekstensi GD server belum mendukung format ini.');
}

// Pastikan transparansi (PNG/WEBP) tetap terjaga saat proses copy
imagesavealpha($srcImage, true);

// ---- Hitung batas-batas potongan ------------------------------------------
/**
 * Mengembalikan array pasangan [start, length] sepanjang satu sumbu.
 */
function compute_segments(int $total, string $mode, int $quantity, int $blockSize, int $overlap): array {
    $segments = [];
    if ($mode === 'size') {
        $blockSize = min($blockSize, $total);
        $start = 0;
        while ($start < $total) {
            $len = min($blockSize, $total - $start);
            $segStart = max(0, $start - ($start > 0 ? (int)floor($overlap / 2) : 0));
            $segEnd   = min($total, $start + $len + (int)floor($overlap / 2));
            $segments[] = [$segStart, $segEnd - $segStart];
            $start += $blockSize;
        }
    } else {
        $quantity = max(1, $quantity);
        $base = (int)floor($total / $quantity);
        $remainder = $total % $quantity;
        $cursor = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $len = $base + ($i < $remainder ? 1 : 0);
            $segStart = max(0, $cursor - ($i > 0 ? (int)floor($overlap / 2) : 0));
            $segEnd   = min($total, $cursor + $len + ($i < $quantity - 1 ? (int)floor($overlap / 2) : 0));
            $segments[] = [$segStart, $segEnd - $segStart];
            $cursor += $len;
        }
    }
    return $segments;
}

$pieces = []; // setiap elemen: ['x'=>, 'y'=>, 'w'=>, 'h'=>, 'row'=>, 'col'=>]

if ($direction === 'vertical') {
    // Potong berdasarkan tinggi (tumpukan baris), lebar penuh
    $segs = compute_segments($origHeight, $mode, $quantity, $blockSize, $overlap);
    foreach ($segs as $i => $seg) {
        $pieces[] = ['x' => 0, 'y' => $seg[0], 'w' => $origWidth, 'h' => $seg[1], 'row' => $i, 'col' => 0];
    }
} elseif ($direction === 'horizontal') {
    // Potong berdasarkan lebar (kolom berdampingan), tinggi penuh
    $segs = compute_segments($origWidth, $mode, $quantity, $blockSize, $overlap);
    foreach ($segs as $i => $seg) {
        $pieces[] = ['x' => $seg[0], 'y' => 0, 'w' => $seg[1], 'h' => $origHeight, 'row' => 0, 'col' => $i];
    }
} else { // grid
    $rowSegs = compute_segments($origHeight, 'quantity', $rows, 0, $overlap);
    $colSegs = compute_segments($origWidth, 'quantity', $cols, 0, $overlap);
    foreach ($rowSegs as $r => $rseg) {
        foreach ($colSegs as $c => $cseg) {
            $pieces[] = ['x' => $cseg[0], 'y' => $rseg[0], 'w' => $cseg[1], 'h' => $rseg[1], 'row' => $r, 'col' => $c];
        }
    }
}

if (count($pieces) === 0) {
    imagedestroy($srcImage);
    respond_error('Tidak ada potongan yang dihasilkan, cek kembali parameter.');
}

if (count($pieces) > 60) {
    imagedestroy($srcImage);
    respond_error('Jumlah potongan terlalu banyak (maksimal 60 sekaligus).');
}

// ---- Tentukan format & ekstensi output ------------------------------------
$fmt = $outputFmt === 'same' ? $srcExt : $outputFmt;
switch ($fmt) {
    case 'png':
        $mime = 'image/png';
        break;
    case 'webp':
        $mime = 'image/webp';
        break;
    default:
        $mime = 'image/jpeg';
        break;
}

// ---- Lakukan pemotongan & encode ke base64 (semua di memori) --------------
$results = [];
foreach ($pieces as $index => $p) {
    $w = max(1, (int)$p['w']);
    $h = max(1, (int)$p['h']);

    $piece = imagecreatetruecolor($w, $h);

    // Jaga transparansi untuk PNG/WEBP agar kualitas & alpha channel tetap utuh
    if ($fmt === 'png' || $fmt === 'webp') {
        imagealphablending($piece, false);
        imagesavealpha($piece, true);
        $transparent = imagecolorallocatealpha($piece, 0, 0, 0, 127);
        imagefilledrectangle($piece, 0, 0, $w, $h, $transparent);
    } else {
        // JPEG tidak mendukung alpha, isi latar putih agar area transparan tidak jadi hitam
        $white = imagecolorallocate($piece, 255, 255, 255);
        imagefilledrectangle($piece, 0, 0, $w, $h, $white);
    }

    // imagecopy (bukan resample) => tidak ada resizing/interpolasi = tanpa kehilangan kualitas
    imagecopy($piece, $srcImage, 0, 0, (int)$p['x'], (int)$p['y'], $w, $h);

    ob_start();
    switch ($fmt) {
        case 'png':
            imagepng($piece, null, 0); // 0 = tanpa kompresi tambahan yang merusak, PNG selalu lossless
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                imagewebp($piece, null, $quality);
            } else {
                // Fallback jika PHP GD di server lama belum dicompile dengan libwebp
                $mime = 'image/jpeg';
                $fmt = 'jpg';
                imagejpeg($piece, null, $quality);
            }
            break;
        default:
            imagejpeg($piece, null, $quality);
            break;
    }
    $binary = ob_get_clean();
    imagedestroy($piece);

    $results[] = [
        'index'    => $index,
        'row'      => $p['row'],
        'col'      => $p['col'],
        'width'    => $w,
        'height'   => $h,
        'filename' => sprintf('slice-%02d.%s', $index + 1, $fmt),
        'dataUrl'  => 'data:' . $mime . ';base64,' . base64_encode($binary),
    ];
}

imagedestroy($srcImage);

echo json_encode([
    'ok'        => true,
    'direction' => $direction,
    'original'  => ['width' => $origWidth, 'height' => $origHeight],
    'format'    => $fmt,
    'count'     => count($results),
    'pieces'    => $results,
]);