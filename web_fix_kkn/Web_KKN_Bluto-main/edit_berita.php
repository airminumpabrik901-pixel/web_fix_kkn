<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmtKat = $koneksi->query("SELECT * FROM kategori_berita");
    $listKategori = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listKategori = [];
}

$error_msg = '';

$id_berita = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_berita <= 0) {
    header("Location: admin.php");
    exit;
}

try {
    $stmt = $koneksi->prepare("SELECT * FROM berita WHERE id_berita = :id LIMIT 1");
    $stmt->execute([':id' => $id_berita]);
    $berita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$berita) {
        header("Location: admin.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Validasi CSRF gagal! Token tidak cocok.");
    }

    $judul = trim($_POST['judul']);
    $id_kategori = isset($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : 0;
    $isi = trim($_POST['isi']);
    $new_kategori = isset($_POST['new_kategori']) ? trim($_POST['new_kategori']) : '';
    
    if ($new_kategori !== '') {
        try {
            $stmtExisting = $koneksi->prepare("SELECT id_kategori FROM kategori_berita WHERE LOWER(nama_kategori) = LOWER(:nama) LIMIT 1");
            $stmtExisting->execute([':nama' => $new_kategori]);
            $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $id_kategori = $existing['id_kategori'];
            } else {
                $stmtInsertKat = $koneksi->prepare("INSERT INTO kategori_berita (nama_kategori) VALUES (:nama)");
                $stmtInsertKat->execute([':nama' => $new_kategori]);
                $id_kategori = $koneksi->lastInsertId();
            }
        } catch (PDOException $e) {
            $error_msg = 'Gagal menyimpan kategori baru: ' . $e->getMessage();
        }
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul)));

    if ($judul !== '' && $isi !== '' && $id_kategori > 0 && empty($error_msg)) {
        $gambar_cover = $berita['gambar_cover']; // Default to old image

        // Process File Upload if new file is selected
        if (isset($_FILES['gambar_cover']) && $_FILES['gambar_cover']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['gambar_cover']['tmp_name'];
            $fileName = $_FILES['gambar_cover']['name'];
            $fileSize = $_FILES['gambar_cover']['size'];
            $fileType = $_FILES['gambar_cover']['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions) && $fileSize <= 2 * 1024 * 1024) {
                $mimeType = mime_content_type($fileTmpPath);
                if (strpos($mimeType, 'image/') === 0) {
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $uploadFileDir = './assets/img/';
                    $dest_path = $uploadFileDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Delete old file if it exists and was uploaded
                        if (!empty($berita['gambar_cover'])) {
                            $old_file = $uploadFileDir . $berita['gambar_cover'];
                            if (file_exists($old_file) && is_file($old_file)) {
                                @unlink($old_file);
                            }
                        }
                        $gambar_cover = $newFileName;
                    } else {
                        $error_msg = "Gagal memindahkan file yang diunggah.";
                    }
                } else {
                    $error_msg = "Berkas yang diunggah bukan gambar yang valid.";
                }
            } else {
                $error_msg = "Unggahan gagal. Format harus JPG/PNG/WEBP dan ukuran < 2MB.";
            }
        }

        if (empty($error_msg)) {
            try {
                $query = "UPDATE berita 
                          SET id_kategori = :kategori, judul = :judul, slug = :slug, isi = :isi, gambar_cover = :gambar_cover 
                          WHERE id_berita = :id";
                $stmtUpdate = $koneksi->prepare($query);
                $stmtUpdate->execute([
                    ':kategori' => $id_kategori,
                    ':judul' => $judul,
                    ':slug' => $slug,
                    ':isi' => $isi,
                    ':gambar_cover' => $gambar_cover,
                    ':id' => $id_berita
                ]);

                header("Location: admin.php");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal menyimpan perubahan: " . $e->getMessage();
            }
        }
    } else {
        $error_msg = "Harap isi semua kolom formulir dengan benar!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Berita - Admin Desa</title>
    
    <link rel="shortcut icon" href="assets/img/logo_sumenep.png" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --navy-dark: #0f172a;
            --navy-medium: #1e293b;
            --emerald-primary: #10b981;
            --emerald-hover: #059669;
            --bg-light-gray: #f8fafc;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light-gray);
            color: #1e293b;
        }

        .navbar-admin {
            background-color: var(--navy-dark);
            border-bottom: 3px solid var(--emerald-primary);
        }

        .form-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .form-control-custom {
            border: 1px solid #cbd5e1;
            padding: 12px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .form-control-custom:focus {
            border-color: var(--emerald-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
        }
        
        .btn-emerald {
            background-color: var(--emerald-primary);
            color: white;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-emerald:hover {
            background-color: var(--emerald-hover);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body class="pb-5">

    
    <nav class="navbar navbar-dark navbar-admin py-3 shadow-sm mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 fw-bold d-flex align-items-center">
                <i class="bi bi-pencil-square text-success me-2"></i>Edit Berita Desa
            </span>
            <a href="admin.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
            </a>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        
        
        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 px-4 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?= $error_msg ?>
            </div>
        <?php endif; ?>

        
        <div class="card form-card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-4 p-md-5">
                
                <form action="edit_berita.php?id=<?= $id_berita ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1.5">Judul Berita</label>
                        <input type="text" name="judul" class="form-control form-control-custom" 
                               value="<?= htmlspecialchars($berita['judul']) ?>" placeholder="Masukkan judul berita yang menarik" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1.5">Kategori Berita</label>
                        <select name="id_kategori" class="form-select form-control-custom">
                            <option value="">-- Pilih Kategori yang Ada --</option>
                            <?php foreach ($listKategori as $kat): ?>
                                <option value="<?= $kat['id_kategori'] ?>" <?= ($berita['id_kategori'] == $kat['id_kategori']) ? 'selected' : ''; ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted small mt-1.5">Pilih kategori yang sudah tersedia atau tambahkan kategori baru di bawah.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1.5">Tambah Kategori Baru (opsional)</label>
                        <input type="text" name="new_kategori" class="form-control form-control-custom" placeholder="Contoh: Agenda Desa" value="<?= htmlspecialchars($_POST['new_kategori'] ?? '') ?>">
                        <div class="form-text text-muted small mt-1.5">Jika kategori belum ada, ketik nama baru lalu simpan perubahan beritanya.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1.5">Gambar Cover Berita</label>
                        <input type="file" name="gambar_cover" class="form-control form-control-custom" accept="image/*">
                        <?php if (!empty($berita['gambar_cover'])): ?>
                            <div class="form-text text-success small mt-1">Gambar saat ini: <strong><?= htmlspecialchars($berita['gambar_cover']) ?></strong></div>
                        <?php endif; ?>
                        <div class="form-text text-muted small mt-1.5">Biarkan kosong jika tidak ingin mengubah gambar. Format: JPG/PNG/WEBP. Maksimal 2MB.</div>
                    </div>
                    
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted mb-1.5">Isi Berita</label>
                        <textarea name="isi" class="form-control form-control-custom" rows="12" 
                                  placeholder="Ketik isi berita lengkap di sini..." required><?= htmlspecialchars($berita['isi']) ?></textarea>
                        <div class="form-text text-muted small mt-1.5">Gunakan tombol Enter untuk memisahkan antar paragraf.</div>
                    </div>
                    
                    
                    <button type="submit" class="btn btn-emerald w-100 py-2.5">
                        <i class="bi bi-save-fill me-1"></i> Simpan Perubahan Berita
                    </button>
                    
                </form>
                
            </div>
        </div>
        
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
