<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/surat_helper.php';

$id = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? '';
if ($id <= 0) {
    echo 'ID pengajuan tidak valid.';
    exit;
}

try {
    $stmt = $koneksi->prepare('SELECT * FROM pengajuan_surat WHERE id_pengajuan = :id');
    $stmt->execute([':id' => $id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Gagal mengambil data pengajuan: ' . $e->getMessage();
    exit;
}

if (!$s) {
    echo 'Pengajuan tidak ditemukan.';
    exit;
}

// Tentukan tipe template dari nama jenis_surat (sesuai dengan logika kunci sistem di admin_template_surat.php)
$jenis = strtolower($s['jenis_surat'] ?? '');
if (strpos($jenis, 'usaha') !== false || strpos($jenis, 'sku') !== false) {
    $type = 'sku';
} elseif (strpos($jenis, 'tidak mampu') !== false || strpos($jenis, 'sktm') !== false) {
    $type = 'sktm';
} elseif (strpos($jenis, 'domisili') !== false || strpos($jenis, 'skd') !== false) {
    $type = 'domisili';
} elseif (strpos($jenis, 'nikah') !== false) {
    $type = 'nikah';
} else {
    $type = preg_replace('/[^a-z0-9]/', '', $jenis);
}

$indonesian_months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$tgl_lahir_formatted = '';
if (!empty($s['tanggal_lahir'])) {
    $t = strtotime($s['tanggal_lahir']);
    if ($t) {
        $day = date('d', $t);
        $month = date('m', $t);
        $year = date('Y', $t);
        $month_name = $indonesian_months[$month] ?? '';
        $tgl_lahir_formatted = "$day $month_name $year";
    }
}

$tempat_tgl_lahir = '';
if (!empty($s['tempat_lahir'])) {
    $tempat_tgl_lahir = $s['tempat_lahir'];
    if (!empty($tgl_lahir_formatted)) {
        $tempat_tgl_lahir .= ', ' . $tgl_lahir_formatted;
    }
} else {
    $tempat_tgl_lahir = $tgl_lahir_formatted;
}

$data = [
    'nama' => $s['nama_pemohon'] ?? '',
    'nik' => $s['nik'] ?? '',
    'alamat' => $s['alamat'] ?? $s['keperluan'] ?? '',
    'keperluan' => $s['keperluan'] ?? '',
    'nama_usaha' => $s['nama_usaha'] ?? '',
    'lokasi_usaha' => $s['lokasi_usaha'] ?? '',
    'keterangan' => $s['keperluan'] ?? $s['keterangan'] ?? '',
    'status_tinggal' => $s['status_tinggal'] ?? '',
    'tempat_lahir' => $s['tempat_lahir'] ?? '',
    'tanggal_lahir' => $tgl_lahir_formatted,
    'tempat_tgl_lahir' => $tempat_tgl_lahir,
    'jenis_kelamin' => $s['jenis_kelamin'] ?? '',
    'agama' => $s['agama'] ?? '',
    'pekerjaan' => $s['pekerjaan'] ?? '',
    'kewarganegaraan' => $s['kewarganegaraan'] ?? 'WNI',
    'nomor_registrasi' => $s['nomor_registrasi'] ?? ''
];

$draft = generateSuratDraft($type, $data);

if ($format === 'word') {
    $filename = 'surat_' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($s['nama_pemohon'] ?? 'surat')) . '_' . date('YmdHis') . '.doc';
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Surat - ' . htmlspecialchars($s['nama_pemohon'] ?? '') . '</title></head><body>';
    echo $draft;
    echo '</body></html>';
    exit;
}

?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Surat - <?= htmlspecialchars($s['nama_pemohon']) ?></title>
    <!-- Bootstrap Icons for Floating Bar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Desain Pratinjau Kertas di Layar Komputer */
        html, body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
        }

        .print-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        /* Simulasi Kertas A4 */
        .sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 15mm 20mm 20mm 20mm; /* Margin Standar A4 (Kop surat lebih tinggi) */
            box-sizing: border-box;
            position: relative;
            margin-bottom: 20px;
        }

        /* Mengabaikan double wrapper margin dari draf helper */
        .sheet > div {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            font-family: inherit !important;
        }

        /* Batang kontrol mengambang layar */
        .helper-bar {
            background: #0f172a;
            color: #fff;
            padding: 12px 24px;
            border-radius: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 13px;
        }

        .helper-bar button, .helper-bar a {
            color: #fff;
            text-decoration: none;
            background: #10b981;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }

        .helper-bar a.btn-secondary {
            background: #475569;
        }

        .helper-bar button:hover {
            background: #059669;
        }

        .sheet table {
            border-collapse: collapse;
            margin-top: 0;
            margin-bottom: 0;
        }

        /* Tabel data (field-field biodata) tampil full width */
        .sheet table:not([style*="border-spacing"]) {
            width: 100%;
            margin-top: 6px;
            margin-bottom: 6px;
        }

        .sheet td {
            padding: 1px 2px;
            vertical-align: top;
        }

        .sheet p {
            margin-top: 0;
            margin-bottom: 6px;
            text-align: justify;
        }

        /* Kop p tidak boleh ada margin */
        .sheet table[style*="border-spacing"] p,
        .sheet table[style*="border-spacing"] td {
            margin: 0 !important;
            padding: 2px 10px !important;
        }

        .sheet hr {
            border: none;
            border-top: 3px double #000;
            height: 3px;
            margin: 15px 0;
            opacity: 1;
        }

        /* Gambar umum - tidak membatasi ukuran agar logo kop tampil sesuai inline style */
        .sheet img {
            display: inline-block;
        }

        /* Gaya Khusus Saat Mencetak Fisik */
        @media print {
            html, body {
                background: #fff;
                margin: 0;
                padding: 0;
            }
            
            @page {
                size: A4;
                margin: 0; /* Menghilangkan info default browser (URL, Tanggal, Judul) di pojok kertas */
            }
            
            .no-print, .helper-bar {
                display: none !important;
            }
            
            .print-preview-container {
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .sheet {
                width: 210mm;
                height: 297mm;
                box-shadow: none;
                margin: 0 !important;
                padding: 15mm 20mm 20mm 20mm !important; /* Margin A4 hemat ruang atas */
                box-sizing: border-box;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="print-preview-container">
        
        <!-- Bar Kontrol Mengambang (Hanya terlihat di layar) -->
        <div class="helper-bar no-print">
            <span><i class="bi bi-info-circle-fill text-warning me-1"></i> Mode Pratinjau Kertas A4</span>
            <button onclick="window.print();"><i class="bi bi-printer-fill me-1"></i> Cetak Surat</button>
            <a href="admin.php" class="btn-secondary">Tutup</a>
        </div>

        <!-- Selembar Kertas A4 -->
        <div class="sheet">
            <?php echo $draft; ?>
        </div>
        
    </div>

    <script>
        // Buka otomatis dialog cetak setelah halaman dimuat
        window.addEventListener('load', function(){ 
            setTimeout(function(){ 
                window.print(); 
            }, 300); 
        });
    </script>
</body>
</html>
