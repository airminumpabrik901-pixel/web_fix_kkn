<?php
session_start();

// Cek if admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Validasi CSRF gagal! Token tidak cocok atau tidak tersedia.");
    }
}

// Secara otomatis menyisipkan token CSRF ke seluruh form POST di halaman ini
ob_start(function($buffer) {
    if (isset($_SESSION['csrf_token'])) {
        $input = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
        $buffer = preg_replace_callback('/(<form\b[^>]*>)/i', function($matches) use ($input) {
            return $matches[1] . $input;
        }, $buffer);
    }
    return $buffer;
});

$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';

// Handle form submission for updating template
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $tipe_surat = $_POST['tipe_surat'] ?? '';
    $template_html = $_POST['template_html'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';

    if (!empty($tipe_surat) && !empty($template_html)) {
        try {
            // Check if template exists
            $stmt = $koneksi->prepare("SELECT id FROM template_surat WHERE tipe_surat = ?");
            $stmt->execute([$tipe_surat]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing template
                $stmt = $koneksi->prepare("UPDATE template_surat SET template_html = ?, deskripsi = ?, updated_at = CURRENT_TIMESTAMP WHERE tipe_surat = ?");
                $stmt->execute([$template_html, $deskripsi, $tipe_surat]);
                $message = "Template format surat berhasil diperbarui!";
                $message_type = "success";
            } else {
                // Insert new template
                $stmt = $koneksi->prepare("INSERT INTO template_surat (tipe_surat, template_html, deskripsi) VALUES (?, ?, ?)");
                $stmt->execute([$tipe_surat, $template_html, $deskripsi]);
                $message = "Template format surat berhasil disimpan!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error database: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Tipe surat dan template tidak boleh kosong!";
        $message_type = "danger";
    }
}

// Get all available letter types dynamically from jenis_surat table
$letter_types = [];
try {
    $stmtJenis = $koneksi->query("SELECT nama FROM jenis_surat ORDER BY id ASC");
    $jenisDB = $stmtJenis->fetchAll(PDO::FETCH_COLUMN);
    foreach ($jenisDB as $name) {
        $label = strtolower($name);
        $key = '';
        if (strpos($label, 'usaha') !== false || stripos($label, 'sku') !== false) {
            $key = 'sku';
        } elseif (strpos($label, 'tidak mampu') !== false || stripos($label, 'sktm') !== false) {
            $key = 'sktm';
        } elseif (strpos($label, 'domisili') !== false || stripos($label, 'skd') !== false) {
            $key = 'domisili';
        } elseif (strpos($label, 'nikah') !== false) {
            $key = 'nikah';
        } else {
            $key = preg_replace('/[^a-z0-9]/', '', $label);
        }
        $letter_types[$key] = $name;
    }
} catch (PDOException $e) {
    // fallback
    $letter_types = [
        'nikah' => 'Surat Keterangan Nikah',
        'sku' => 'Surat Keterangan Usaha (SKU)',
        'sktm' => 'Surat Keterangan Tidak Mampu (SKTM)',
        'domisili' => 'Surat Keterangan Domisili (SKD)'
    ];
}

if (empty($letter_types)) {
    $letter_types = [
        'nikah' => 'Surat Keterangan Nikah',
        'sku' => 'Surat Keterangan Usaha (SKU)',
        'sktm' => 'Surat Keterangan Tidak Mampu (SKTM)',
        'domisili' => 'Surat Keterangan Domisili (SKD)'
    ];
}

// Get current template if editing
$current_template = null;
if (!empty($type) && isset($letter_types[$type])) {
    try {
        $stmt = $koneksi->prepare("SELECT * FROM template_surat WHERE tipe_surat = ?");
        $stmt->execute([$type]);
        $current_template = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore error
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor Format Surat - Desa Bluto</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- CKEditor 4 -->
    <script src="https://cdn.ckeditor.com/4.21.0/standard/ckeditor.js"></script>
    
    <style>
        :root {
            --navy-dark: #0f172a;
            --navy-medium: #1e293b;
            --navy-light: #475569;
            --emerald-primary: #10b981;
            --emerald-hover: #059669;
            --bg-light-gray: #f8fafc;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light-gray);
            color: var(--text-dark);
        }

        .card-custom {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.025);
            background-color: #fff;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--navy-light);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            background: #fff;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .nav-link-custom:hover {
            background-color: #f1f5f9;
            color: var(--navy-dark);
            padding-left: 20px;
        }

        .nav-link-custom.active {
            background-color: rgba(16, 185, 129, 0.08);
            color: var(--emerald-primary);
            border-left-color: var(--emerald-primary);
        }

        .btn-action-lg {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .variable-badge {
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            font-size: 12px;
            font-weight: 600;
        }

        .variable-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .form-control-custom {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control-custom:focus {
            border-color: var(--emerald-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            outline: none;
        }

        .form-label-custom {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy-light);
            margin-bottom: 6px;
        }
    </style>
</head>
<body class="py-4">
    <div class="container" style="max-width: 1300px;">
        
        <!-- Header Panel -->
        <div class="card-custom bg-dark text-white p-4 mb-4 position-relative overflow-hidden shadow-sm" style="background: linear-gradient(135deg, var(--navy-dark) 0%, #1e293b 100%);">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-richtext text-emerald-primary me-2"></i>Editor Format Surat (WYSIWYG)</h2>
                    <p class="text-white-50 small mb-0">Ubah draf dan tata letak variabel pencetakan surat otomatis dengan editor interaktif.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="admin.php" class="btn btn-action-lg btn-light border-0 shadow-sm"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show card-custom p-4 mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-<?= ($message_type === 'success') ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> fs-4 me-3"></i>
                    <div>
                        <strong class="d-block mb-0.5"><?= ($message_type === 'success') ? 'Sukses!' : 'Gagal!'; ?></strong>
                        <span class="small text-muted"><?= htmlspecialchars($message); ?></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Sidebar Kolom Kiri - Jenis Surat -->
            <div class="col-lg-3">
                <div class="card-custom p-3">
                    <h6 class="fw-bold text-dark border-bottom pb-2.5 mb-3 px-2">
                        <i class="bi bi-list-task me-1.5 text-success"></i>Jenis Surat Keterangan
                    </h6>
                    <nav class="nav flex-column">
                        <?php foreach ($letter_types as $key => $label): ?>
                            <a href="?action=edit&type=<?= $key; ?>" class="nav-link-custom <?= ($type === $key) ? 'active' : ''; ?>">
                                <i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>

            <!-- Kolom Kanan - WYSIWYG Editor -->
            <div class="col-lg-9">
                <div class="card-custom p-4 p-md-5">
                    <?php if (!empty($type) && isset($letter_types[$type])): ?>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                            <h4 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square text-success me-2"></i><?= htmlspecialchars($letter_types[$type]); ?></h4>
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill font-semibold small">Mode Editor Aktif</span>
                        </div>

                        <form method="POST">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Tipe Surat (Kunci Sistem)</label>
                                    <input type="hidden" name="tipe_surat" id="tipe_surat" value="<?= htmlspecialchars($type); ?>">
                                    <input type="text" class="form-control form-control-custom bg-light" value="<?= htmlspecialchars($type); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Deskripsi Singkat (Opsional)</label>
                                    <input type="text" name="deskripsi" id="deskripsi" 
                                           class="form-control form-control-custom"
                                           value="<?= htmlspecialchars($current_template['deskripsi'] ?? ''); ?>"
                                           placeholder="Contoh: Format SKU standar TTD Kepala Desa">
                                </div>
                            </div>



                            <!-- Variables Helper Section -->
                            <div class="card bg-light border-0 p-3 mb-4 rounded-3">
                                <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-lightning-fill text-warning me-1"></i>Klik Variabel untuk Menyisipkan ke Kursor Editor:</h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{nama}')">{nama}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{nik}')">{nik}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{alamat}')">{alamat}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{tempat_lahir}')">{tempat_lahir}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{tanggal_lahir}')">{tanggal_lahir}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{tempat_tgl_lahir}')">{tempat_tgl_lahir}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{jenis_kelamin}')">{jenis_kelamin}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{agama}')">{agama}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{pekerjaan}')">{pekerjaan}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{kewarganegaraan}')">{kewarganegaraan}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{tanggal}')">{tanggal}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{nama_usaha}')">{nama_usaha}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{lokasi_usaha}')">{lokasi_usaha}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{keperluan}')">{keperluan}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{keterangan}')">{keterangan}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{kepala_desa}')">{kepala_desa}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{nama_desa}')">{nama_desa}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{nama_kecamatan}')">{nama_kecamatan}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{nama_kabupaten}')">{nama_kabupaten}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 variable-badge font-monospace" onclick="insertVariable('{status_tinggal}')">{status_tinggal}</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label-custom">Kertas Surat (Kop & Badan Surat)</label>
                                <textarea name="template_html" id="template_html" required><?php 
                                    if ($current_template) {
                                        echo htmlspecialchars($current_template['template_html']);
                                    } else {
                                        require_once __DIR__ . '/config/surat_helper.php';
                                        $default = generateSuratDraft($type, [
                                            'nama'            => '{nama}',
                                            'nik'             => '{nik}',
                                            'alamat'          => '{alamat}',
                                            'tempat_lahir'    => '{tempat_lahir}',
                                            'tanggal_lahir'   => '{tanggal_lahir}',
                                            'tempat_tgl_lahir'=> '{tempat_tgl_lahir}',
                                            'jenis_kelamin'   => '{jenis_kelamin}',
                                            'agama'           => '{agama}',
                                            'pekerjaan'       => '{pekerjaan}',
                                            'kewarganegaraan' => '{kewarganegaraan}',
                                            'keperluan'       => '{keperluan}',
                                            'nama_usaha'      => '{nama_usaha}',
                                            'lokasi_usaha'    => '{lokasi_usaha}',
                                            'keterangan'      => '{keterangan}',
                                            'status_tinggal'  => '{status_tinggal}',
                                        ]);
                                        echo htmlspecialchars($default);
                                    }
                                ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="save_template" class="btn btn-action-lg btn-success shadow-sm">
                                    <i class="bi bi-floppy"></i> Simpan Format
                                </button>
                                <a href="admin_template_surat.php" class="btn btn-action-lg btn-light border">Batal</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-richtext display-3 text-muted d-block mb-3"></i>
                            <h5 class="fw-bold">Pilih Jenis Surat Terlebih Dahulu</h5>
                            <p class="small mb-0 text-muted">Klik salah satu jenis surat di panel kiri untuk membuka format dan mulai menyunting.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripting CKEditor & Insertion -->
    <script>
        window.addEventListener('load', function() {
            if (document.getElementById('template_html')) {
                CKEDITOR.replace('template_html', {
                    height: 600,
                    removePlugins: 'exportpdf,heading',
                    allowedContent: true,
                    versionCheck: false,
                    contentsStyle: `
                        body {
                            font-family: "Times New Roman", Times, serif;
                            font-size: 16px;
                            line-height: 1.5;
                            padding: 20mm; /* Simulasi margin kertas A4 di editor */
                            color: #000;
                            background-color: #fff;
                            max-width: 800px;
                            margin: 0 auto;
                        }
                        p {
                            margin-top: 0;
                            margin-bottom: 8px;
                            text-align: justify;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                            margin-bottom: 10px;
                        }
                        td {
                            padding: 4px 6px;
                            vertical-align: top;
                        }
                        hr {
                            border: none;
                            border-top: 3px double #000;
                            height: 3px;
                            margin: 15px 0;
                            opacity: 1;
                        }
                        img {
                            max-width: 120px;
                            height: auto;
                            display: inline-block;
                        }
                        table:has(img) td {
                            vertical-align: middle !important;
                        }
                        table:has(img) td:last-of-type {
                            text-align: center !important;
                        }
                        table:has(img) td:last-of-type p {
                            text-align: center !important;
                        }
                    `
                });
                
                const form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        for (var name in CKEDITOR.instances) {
                            CKEDITOR.instances[name].updateElement();
                        }
                    });
                }
            }
        });

        function insertVariable(variable) {
            var inst = CKEDITOR.instances['template_html'];
            if (inst) {
                inst.insertText(variable);
                inst.focus();
            } else {
                var ta = document.getElementById('template_html');
                if (ta) {
                    ta.value = ta.value + variable;
                    ta.focus();
                }
            }
        }



        // Fungsi Notifikasi Kustom Premium (Menggantikan alert browser standard)
        function showCustomAlert(title, message, type = 'success') {
            const modalEl = document.getElementById('customAlertModal');
            if (!modalEl) return;
            const modal = new bootstrap.Modal(modalEl);
            
            const titleEl = document.getElementById('customAlertTitle');
            const msgEl = document.getElementById('customAlertMessage');
            const iconContainer = document.getElementById('customAlertIconContainer');
            const icon = document.getElementById('customAlertIcon');
            
            titleEl.textContent = title;
            msgEl.textContent = message;
            
            if (type === 'success') {
                iconContainer.className = 'd-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3';
                icon.className = 'bi bi-check-circle-fill fs-2';
            } else if (type === 'warning') {
                iconContainer.className = 'd-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3';
                icon.className = 'bi bi-exclamation-circle-fill fs-2';
            } else { // danger/error
                iconContainer.className = 'd-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3';
                icon.className = 'bi bi-exclamation-triangle-fill fs-2';
            }
            
            modal.show();
        }
    </script>

    <!-- Modal Notifikasi Kustom Premium -->
    <div class="modal fade" id="customAlertModal" tabindex="-1" aria-hidden="true" style="z-index: 2050;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-body p-4 text-center">
                    <div id="customAlertIconContainer" class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i id="customAlertIcon" class="bi bi-exclamation-triangle-fill fs-2"></i>
                    </div>
                    <h5 id="customAlertTitle" class="fw-bold text-dark mb-2">Notifikasi</h5>
                    <p id="customAlertMessage" class="text-muted small mb-0 px-2">Pesan notifikasi di sini.</p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-center pb-4 pt-0">
                    <button type="button" class="btn btn-dark rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Mengerti</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
