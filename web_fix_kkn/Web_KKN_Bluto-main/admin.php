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

$success_msg = '';
$error_msg = '';

try {
    $koneksi->exec("CREATE TABLE IF NOT EXISTS struktur_pemerintahan (
        id_struktur INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT,
        jabatan TEXT,
        foto TEXT,
        urutan INTEGER DEFAULT 0
    )");
} catch (PDOException $e) {
    // Abaikan jika tabel sudah ada
}

try {
    $stmtStrukturList = $koneksi->query("SELECT * FROM struktur_pemerintahan ORDER BY urutan ASC, id_struktur ASC");
    $strukturList = $stmtStrukturList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $strukturList = [];
}

// --- Kelola jenis_surat (hanya untuk admin) ---
try {
    $koneksi->exec("CREATE TABLE IF NOT EXISTS jenis_surat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT UNIQUE
    )");
} catch (PDOException $e) {
    // abaikan
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_jenis_surat'])) {
    $nama_baru = trim($_POST['nama_jenis'] ?? '');
    if ($nama_baru !== '') {
        try {
            $stmtInsJenis = $koneksi->prepare("INSERT OR IGNORE INTO jenis_surat (nama) VALUES (:nama)");
            $stmtInsJenis->execute([':nama' => $nama_baru]);
            $success_msg = 'Jenis surat baru berhasil ditambahkan.';
        } catch (PDOException $e) {
            $error_msg = 'Gagal menambahkan jenis surat: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_jenis_surat'])) {
    $id_hapus = (int)($_POST['id_jenis'] ?? 0);
    if ($id_hapus > 0) {
        try {
            $stmtDelJenis = $koneksi->prepare("DELETE FROM jenis_surat WHERE id = :id");
            $stmtDelJenis->execute([':id' => $id_hapus]);
            $success_msg = 'Jenis surat berhasil dihapus.';
        } catch (PDOException $e) {
            $error_msg = 'Gagal menghapus jenis surat: ' . $e->getMessage();
        }
    }
}

// --- end jenis_surat ---

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama_desa = trim($_POST['nama_desa']);
    $kepala_desa = trim($_POST['kepala_desa']);
    $visi = trim($_POST['visi']);
    $misi = trim($_POST['misi'] ?? '');
    $masa_jabatan = trim($_POST['masa_jabatan'] ?? '');

    try {
        $stmtCurrent = $koneksi->prepare("SELECT foto_kades FROM profil_desa WHERE id_profil = 1");
        $stmtCurrent->execute();
        $currentProfil = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        $currentFoto = $currentProfil['foto_kades'] ?? '';
    } catch (PDOException $e) {
        $currentFoto = '';
    }

    $foto_kades = $currentFoto;

    if (isset($_FILES['foto_kades']) && $_FILES['foto_kades']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName = basename($_FILES['foto_kades']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($fileExt, $allowedExt, true)) {
            $error_msg = 'Format foto Kepala Desa tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        } elseif ($_FILES['foto_kades']['size'] > $maxSize) {
            $error_msg = 'Ukuran foto Kepala Desa terlalu besar. Maksimal 2 MB.';
        } else {
            $uploadDir = __DIR__ . '/assets/img/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['foto_kades']['tmp_name'], $targetPath)) {
                if (!empty($currentFoto) && file_exists($uploadDir . $currentFoto)) {
                    @unlink($uploadDir . $currentFoto);
                }
                $foto_kades = $newFileName;
            } else {
                $error_msg = 'Gagal mengunggah foto Kepala Desa.';
            }
        }
    } elseif (isset($_FILES['foto_kades']) && $_FILES['foto_kades']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_msg = 'Terjadi kesalahan saat mengunggah foto Kepala Desa.';
    }

    if ($error_msg === '') {
        try {
            $stmtUpdate = $koneksi->prepare("UPDATE profil_desa SET nama_desa = :nama_desa, kepala_desa = :kepala_desa, foto_kades = :foto_kades, visi = :visi, misi = :misi, masa_jabatan = :masa_jabatan WHERE id_profil = 1");
            $stmtUpdate->execute([
                ':nama_desa' => $nama_desa,
                ':kepala_desa' => $kepala_desa,
                ':foto_kades' => $foto_kades,
                ':visi' => $visi,
                ':misi' => $misi,
                ':masa_jabatan' => $masa_jabatan
            ]);
            $success_msg = "Profil desa berhasil diperbarui!";
        } catch (PDOException $e) {
            $error_msg = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_struktur'])) {
    $strukturIds = array_keys($_POST['struktur_nama'] ?? []);
    $fotoLama = [];
    foreach ($strukturList as $item) {
        $fotoLama[$item['id_struktur']] = $item['foto'] ?? '';
    }

    foreach ($strukturIds as $id_struktur) {
        $id = (int)$id_struktur;
        $nama = trim($_POST['struktur_nama'][$id_struktur] ?? '');
        $jabatan = trim($_POST['struktur_jabatan'][$id_struktur] ?? '');
        $foto = $fotoLama[$id] ?? '';

        if (isset($_FILES['struktur_foto']['name'][$id_struktur]) && $_FILES['struktur_foto']['error'][$id_struktur] === UPLOAD_ERR_OK) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $fileName = basename($_FILES['struktur_foto']['name'][$id_struktur]);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $maxSize = 2 * 1024 * 1024;

            if (!in_array($fileExt, $allowedExt, true)) {
                $error_msg = 'Format foto struktur tidak didukung. Gunakan JPG, PNG, atau WEBP.';
                continue;
            }

            if ($_FILES['struktur_foto']['size'][$id_struktur] > $maxSize) {
                $error_msg = 'Ukuran foto struktur terlalu besar. Maksimal 2 MB.';
                continue;
            }

            $uploadDir = __DIR__ . '/assets/img/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['struktur_foto']['tmp_name'][$id_struktur], $targetPath)) {
                $foto = $newFileName;
            } else {
                $error_msg = 'Gagal mengunggah foto struktur.';
                continue;
            }
        }

        if ($nama !== '' && $jabatan !== '') {
            try {
                $stmtUpdateStruktur = $koneksi->prepare("UPDATE struktur_pemerintahan SET nama = :nama, jabatan = :jabatan, foto = :foto WHERE id_struktur = :id");
                $stmtUpdateStruktur->execute([
                    ':nama' => $nama,
                    ':jabatan' => $jabatan,
                    ':foto' => $foto,
                    ':id' => $id
                ]);
            } catch (PDOException $e) {
                $error_msg = 'Gagal memperbarui struktur pemerintahan: ' . $e->getMessage();
            }
        }
    }

    if ($error_msg === '') {
        $success_msg = 'Data struktur pemerintahan berhasil diperbarui!';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_struktur'])) {
    $nama = trim($_POST['new_nama'] ?? '');
    $jabatan = trim($_POST['new_jabatan'] ?? '');
    $foto = '';

    if (isset($_FILES['new_foto']) && $_FILES['new_foto']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName = basename($_FILES['new_foto']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($fileExt, $allowedExt, true)) {
            $error_msg = 'Format foto struktur tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        } elseif ($_FILES['new_foto']['size'] > $maxSize) {
            $error_msg = 'Ukuran foto struktur terlalu besar. Maksimal 2 MB.';
        } else {
            $uploadDir = __DIR__ . '/assets/img/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['new_foto']['tmp_name'], $targetPath)) {
                $foto = $newFileName;
            } else {
                $error_msg = 'Gagal mengunggah foto struktur.';
            }
        }
    } elseif (isset($_FILES['new_foto']) && $_FILES['new_foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_msg = 'Terjadi kesalahan saat mengunggah foto struktur.';
    }

    if ($nama !== '' && $jabatan !== '' && $error_msg === '') {
        try {
            $stmtInsertStruktur = $koneksi->prepare("INSERT INTO struktur_pemerintahan (nama, jabatan, foto, urutan) VALUES (:nama, :jabatan, :foto, 0)");
            $stmtInsertStruktur->execute([
                ':nama' => $nama,
                ':jabatan' => $jabatan,
                ':foto' => $foto
            ]);
            $success_msg = 'Anggota struktur pemerintahan berhasil ditambahkan!';
        } catch (PDOException $e) {
            $error_msg = 'Gagal menambahkan anggota struktur: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_struktur'])) {
    $id_struktur = (int)$_POST['id_struktur'];
    try {
        $stmtFoto = $koneksi->prepare("SELECT foto FROM struktur_pemerintahan WHERE id_struktur = :id");
        $stmtFoto->execute([':id' => $id_struktur]);
        $fotoData = $stmtFoto->fetch(PDO::FETCH_ASSOC);
        if ($fotoData && !empty($fotoData['foto'])) {
            $filePath = __DIR__ . '/assets/img/' . $fotoData['foto'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $stmtDelStruktur = $koneksi->prepare("DELETE FROM struktur_pemerintahan WHERE id_struktur = :id");
        $stmtDelStruktur->execute([':id' => $id_struktur]);
        $success_msg = 'Anggota struktur pemerintahan berhasil dihapus!';
    } catch (PDOException $e) {
        $error_msg = 'Gagal menghapus anggota struktur: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['save_struktur']) || isset($_POST['tambah_struktur']) || isset($_POST['hapus_struktur']))) {
    try {
        $stmtStrukturList = $koneksi->query("SELECT * FROM struktur_pemerintahan ORDER BY urutan ASC, id_struktur ASC");
        $strukturList = $stmtStrukturList->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $strukturList = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $password_lama = trim($_POST['password_lama']);
    $password_baru = trim($_POST['password_baru']);
    $password_konfirmasi = trim($_POST['password_konfirmasi']);

    if ($password_baru !== $password_konfirmasi) {
        $error_msg = "Konfirmasi password baru tidak cocok!";
    } else {
        try {
            $stmtAdmin = $koneksi->prepare("SELECT * FROM admin WHERE id_admin = 1 LIMIT 1");
            $stmtAdmin->execute();
            $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($admin && (password_verify($password_lama, $admin['password']) || $password_lama === $admin['password'])) {
                $new_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmtUpdatePass = $koneksi->prepare("UPDATE admin SET password = :password WHERE id_admin = 1");
                $stmtUpdatePass->execute([':password' => $new_hash]);
                
                session_destroy();
                header("Location: login.php?msg=pass_changed");
                exit;
            } else {
                $error_msg = "Password lama yang Anda masukkan salah!";
            }
        } catch (PDOException $e) {
            $error_msg = "Gagal memperbarui password: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $new_settings = [
        'statistik' => [
            'total_penduduk' => (int)$_POST['total_penduduk'],
            'laki_laki' => (int)$_POST['laki_laki'],
            'perempuan' => (int)$_POST['perempuan']
        ],
        'kontak' => [
            'alamat' => trim($_POST['alamat']),
            'email' => trim($_POST['email']),
            'telepon' => trim($_POST['telepon'])
        ]
    ];

    try {
        file_put_contents('config/web_settings.json', json_encode($new_settings, JSON_PRETTY_PRINT));
        $success_msg = "Pengaturan landing page berhasil diperbarui!";
    } catch (Exception $e) {
        $error_msg = "Gagal menulis file konfigurasi: " . $e->getMessage();
    }
}

$settings_file = 'config/web_settings.json';
if (file_exists($settings_file)) {
    $web_settings = json_decode(file_get_contents($settings_file), true);
} else {
    $web_settings = [
        'statistik' => ['total_penduduk' => 4321, 'laki_laki' => 2204, 'perempuan' => 2117],
        'kontak' => [
            'alamat' => 'Jl. Raya Bluto, Kec. Bluto, Kab. Sumenep, Jawa Timur, 69466',
            'email' => 'pemdes@bluto.desa.id',
            'telepon' => '081234567890'
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status_surat'])) {
    $id_pengajuan = (int)$_POST['id_pengajuan'];
    $status_baru = $_POST['status_baru'];
    
    try {
        $stmtSurat = $koneksi->prepare("UPDATE pengajuan_surat SET status_pengajuan = :status WHERE id_pengajuan = :id");
        $stmtSurat->execute([
            ':status' => $status_baru,
            ':id' => $id_pengajuan
        ]);
        $success_msg = "Status pengajuan surat berhasil diperbarui!";
    } catch (PDOException $e) {
        $error_msg = "Gagal memperbarui status: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_pengajuan_surat'])) {
    $id_pengajuan = (int)($_POST['id_pengajuan'] ?? 0);

    if ($id_pengajuan > 0) {
        try {
            $stmtDeleteSurat = $koneksi->prepare("DELETE FROM pengajuan_surat WHERE id_pengajuan = :id");
            $stmtDeleteSurat->execute([':id' => $id_pengajuan]);
            $success_msg = "Pengajuan surat berhasil dihapus.";
        } catch (PDOException $e) {
            $error_msg = "Gagal menghapus pengajuan surat: " . $e->getMessage();
        }
    }
}

try {
    $stmtSuratCount = $koneksi->query("SELECT COUNT(*) FROM pengajuan_surat WHERE status_pengajuan = 'Menunggu'");
    $totalSuratMenunggu = $stmtSuratCount->fetchColumn();
} catch (PDOException $e) {
    $totalSuratMenunggu = 0;
}

try {
    $stmtAllSurat = $koneksi->query("SELECT * FROM pengajuan_surat ORDER BY tanggal_pengajuan DESC, id_pengajuan DESC");
    $suratList = $stmtAllSurat->fetchAll(PDO::FETCH_ASSOC);
    foreach ($suratList as &$row) {
        $row['nama_pemohon'] = trim((string)($row['nama_pemohon'] ?? ''));
        $row['nik'] = trim((string)($row['nik'] ?? ''));
        $row['jenis_surat'] = trim((string)($row['jenis_surat'] ?? ''));
        $row['keperluan'] = trim((string)($row['keperluan'] ?? ''));
        $row['status_pengajuan'] = trim((string)($row['status_pengajuan'] ?? 'Menunggu'));
        if ($row['status_pengajuan'] === '') {
            $row['status_pengajuan'] = 'Menunggu';
        }
    }
    unset($row);
} catch (PDOException $e) {
    $suratList = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_produk'])) {
    $nama = trim($_POST['nama']);
    $kategori = trim($_POST['kategori']);
    $harga = (int)$_POST['harga'];
    $penjual = trim($_POST['penjual']);
    $gambar = '';
    $no_wa = trim($_POST['no_wa']);

    $no_wa = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $no_wa));

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName = basename($_FILES['gambar']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($fileExt, $allowedExt, true)) {
            $error_msg = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        } elseif ($_FILES['gambar']['size'] > $maxSize) {
            $error_msg = 'Ukuran gambar terlalu besar. Maksimal 2 MB.';
        } else {
            $uploadDir = __DIR__ . '/assets/img/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
                $gambar = $newFileName;
            } else {
                $error_msg = 'Gagal mengunggah gambar produk.';
            }
        }
    } elseif (!empty($_FILES['gambar']['name'])) {
        $error_msg = 'Terjadi kesalahan saat mengunggah gambar.';
    } else {
        $error_msg = 'Silakan pilih gambar produk untuk diunggah.';
    }

    if ($nama !== '' && $kategori !== '' && $harga > 0 && $penjual !== '' && $no_wa !== '' && $error_msg === '') {
        try {
            $stmtAddP = $koneksi->prepare("INSERT INTO umkm (nama, kategori, harga, penjual, gambar, no_wa) VALUES (:nama, :kategori, :harga, :penjual, :gambar, :no_wa)");
            $stmtAddP->execute([
                ':nama' => $nama,
                ':kategori' => $kategori,
                ':harga' => $harga,
                ':penjual' => $penjual,
                ':gambar' => $gambar,
                ':no_wa' => $no_wa
            ]);
            $success_msg = 'Produk UMKM berhasil ditambahkan!';
        } catch (PDOException $e) {
            $error_msg = 'Gagal menambah produk: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_produk'])) {
    $id_produk = (int)$_POST['id_produk'];
    try {
        $stmtDelP = $koneksi->prepare("DELETE FROM umkm WHERE id_produk = :id");
        $stmtDelP->execute([':id' => $id_produk]);
        $success_msg = "Produk UMKM berhasil dihapus!";
    } catch (PDOException $e) {
        $error_msg = "Gagal menghapus produk: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_galeri'])) {
    $judul = trim($_POST['judul']);
    $gambar = '';
    $tanggal = trim($_POST['tanggal']);

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName = basename($_FILES['gambar']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($fileExt, $allowedExt, true)) {
            $error_msg = 'Format gambar galeri tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        } elseif ($_FILES['gambar']['size'] > $maxSize) {
            $error_msg = 'Ukuran gambar galeri terlalu besar. Maksimal 2 MB.';
        } else {
            $uploadDir = __DIR__ . '/assets/img/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
                $gambar = $newFileName;
            } else {
                $error_msg = 'Gagal mengunggah gambar galeri.';
            }
        }
    } elseif (!empty($_FILES['gambar']['name'])) {
        $error_msg = 'Terjadi kesalahan saat mengunggah gambar galeri.';
    } else {
        $error_msg = 'Silakan pilih gambar galeri untuk diunggah.';
    }

    if ($judul !== '' && $tanggal !== '' && $gambar !== '' && $error_msg === '') {
        try {
            $stmtAddG = $koneksi->prepare("INSERT INTO galeri (judul, gambar, tanggal) VALUES (:judul, :gambar, :tanggal)");
            $stmtAddG->execute([
                ':judul' => $judul,
                ':gambar' => $gambar,
                ':tanggal' => $tanggal
            ]);
            $success_msg = 'Foto dokumentasi berhasil ditambahkan ke galeri!';
        } catch (PDOException $e) {
            $error_msg = 'Gagal menambah foto galeri: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_galeri'])) {
    $id_galeri = (int)$_POST['id_galeri'];
    try {
        $stmtDelG = $koneksi->prepare("DELETE FROM galeri WHERE id_galeri = :id");
        $stmtDelG->execute([':id' => $id_galeri]);
        $success_msg = "Foto galeri berhasil dihapus!";
    } catch (PDOException $e) {
        $error_msg = "Gagal menghapus foto galeri: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_aspirasi'])) {
    $id_aspirasi = (int)$_POST['id_aspirasi'];
    try {
        $stmtDelA = $koneksi->prepare("DELETE FROM aspirasi WHERE id_aspirasi = :id");
        $stmtDelA->execute([':id' => $id_aspirasi]);
        $success_msg = "Pesan aspirasi berhasil dihapus!";
    } catch (PDOException $e) {
        $error_msg = "Gagal menghapus aspirasi: " . $e->getMessage();
    }
}

// --- PAGINATION & DATA QUERIES ---
$limit = 10;

// 1. News (Berita) Pagination
$p_berita = isset($_GET['p_berita']) ? (int)$_GET['p_berita'] : 1;
if ($p_berita < 1) $p_berita = 1;
$offset_berita = ($p_berita - 1) * $limit;
try {
    $stmtCount = $koneksi->query("SELECT COUNT(*) FROM berita");
    $totalBerita = $stmtCount->fetchColumn();
} catch (PDOException $e) {
    $totalBerita = 0;
}
$totalPagesBerita = ceil($totalBerita / $limit);
try {
    $stmtAll = $koneksi->query("SELECT berita.*, kategori_berita.nama_kategori 
                               FROM berita 
                               JOIN kategori_berita ON berita.id_kategori = kategori_berita.id_kategori 
                               ORDER BY tanggal_publikasi DESC LIMIT $limit OFFSET $offset_berita");
    $beritaList = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $beritaList = [];
}

// 2. Surat Pagination
$p_surat = isset($_GET['p_surat']) ? (int)$_GET['p_surat'] : 1;
if ($p_surat < 1) $p_surat = 1;
$offset_surat = ($p_surat - 1) * $limit;
try {
    $stmtSuratCountAll = $koneksi->query("SELECT COUNT(*) FROM pengajuan_surat");
    $totalSurat = $stmtSuratCountAll->fetchColumn();
} catch (PDOException $e) {
    $totalSurat = 0;
}
$totalPagesSurat = ceil($totalSurat / $limit);
try {
    $stmtAllSurat = $koneksi->query("SELECT * FROM pengajuan_surat ORDER BY tanggal_pengajuan DESC, id_pengajuan DESC LIMIT $limit OFFSET $offset_surat");
    $suratList = $stmtAllSurat->fetchAll(PDO::FETCH_ASSOC);
    foreach ($suratList as &$row) {
        $row['nama_pemohon'] = trim((string)($row['nama_pemohon'] ?? ''));
        $row['nik'] = trim((string)($row['nik'] ?? ''));
        $row['jenis_surat'] = trim((string)($row['jenis_surat'] ?? ''));
        $row['keperluan'] = trim((string)($row['keperluan'] ?? ''));
        $row['status_pengajuan'] = trim((string)($row['status_pengajuan'] ?? 'Menunggu'));
        if ($row['status_pengajuan'] === '') {
            $row['status_pengajuan'] = 'Menunggu';
        }
    }
    unset($row);
} catch (PDOException $e) {
    $suratList = [];
}

// 3. UMKM Pagination
$p_umkm = isset($_GET['p_umkm']) ? (int)$_GET['p_umkm'] : 1;
if ($p_umkm < 1) $p_umkm = 1;
$offset_umkm = ($p_umkm - 1) * $limit;
try {
    $stmtUMKMCountAll = $koneksi->query("SELECT COUNT(*) FROM umkm");
    $totalUMKM = $stmtUMKMCountAll->fetchColumn();
} catch (PDOException $e) {
    $totalUMKM = 0;
}
$totalPagesUMKM = ceil($totalUMKM / $limit);
try {
    $stmtUMKMList = $koneksi->query("SELECT * FROM umkm ORDER BY id_produk DESC LIMIT $limit OFFSET $offset_umkm");
    $umkmList = $stmtUMKMList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $umkmList = [];
}

// 4. Galeri Pagination
$p_galeri = isset($_GET['p_galeri']) ? (int)$_GET['p_galeri'] : 1;
if ($p_galeri < 1) $p_galeri = 1;
$offset_galeri = ($p_galeri - 1) * $limit;
try {
    $stmtGaleriCountAll = $koneksi->query("SELECT COUNT(*) FROM galeri");
    $totalGaleri = $stmtGaleriCountAll->fetchColumn();
} catch (PDOException $e) {
    $totalGaleri = 0;
}
$totalPagesGaleri = ceil($totalGaleri / $limit);
try {
    $stmtGaleriList = $koneksi->query("SELECT * FROM galeri ORDER BY id_galeri DESC LIMIT $limit OFFSET $offset_galeri");
    $galeriList = $stmtGaleriList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $galeriList = [];
}

// 5. Aspirasi Pagination
$p_aspirasi = isset($_GET['p_aspirasi']) ? (int)$_GET['p_aspirasi'] : 1;
if ($p_aspirasi < 1) $p_aspirasi = 1;
$offset_aspirasi = ($p_aspirasi - 1) * $limit;
try {
    $stmtAspirasiCountAll = $koneksi->query("SELECT COUNT(*) FROM aspirasi");
    $totalAspirasi = $stmtAspirasiCountAll->fetchColumn();
} catch (PDOException $e) {
    $totalAspirasi = 0;
}
$totalPagesAspirasi = ceil($totalAspirasi / $limit);
try {
    $stmtAspirasiList = $koneksi->query("SELECT * FROM aspirasi ORDER BY tanggal_kirim DESC LIMIT $limit OFFSET $offset_aspirasi");
    $aspirasiList = $stmtAspirasiList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $aspirasiList = [];
}

// 6. Jenis Surat Pagination
$p_jenis = isset($_GET['p_jenis']) ? (int)$_GET['p_jenis'] : 1;
if ($p_jenis < 1) $p_jenis = 1;
$offset_jenis = ($p_jenis - 1) * $limit;
try {
    $stmtJenisCountAll = $koneksi->query("SELECT COUNT(*) FROM jenis_surat");
    $totalJenisSurat = $stmtJenisCountAll->fetchColumn();
} catch (PDOException $e) {
    $totalJenisSurat = 0;
}
$totalPagesJenisSurat = ceil($totalJenisSurat / $limit);
try {
    $stmtJenisAdmin = $koneksi->query("SELECT id, nama FROM jenis_surat ORDER BY id ASC LIMIT $limit OFFSET $offset_jenis");
    $jenisSuratList = $stmtJenisAdmin->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $jenisSuratList = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_kategori_berita'])) {
    $id_hapus = (int)($_POST['id_kategori'] ?? 0);
    if ($id_hapus > 0) {
        try {
            $stmtCheck = $koneksi->prepare("SELECT COUNT(*) FROM berita WHERE id_kategori = :id");
            $stmtCheck->execute([':id' => $id_hapus]);
            $countBerita = (int)$stmtCheck->fetchColumn();

            if ($countBerita > 0) {
                $error_msg = 'Kategori masih digunakan oleh berita. Hapus atau ubah kategori berita terkait terlebih dahulu.';
            } else {
                $stmtDelKat = $koneksi->prepare("DELETE FROM kategori_berita WHERE id_kategori = :id");
                $stmtDelKat->execute([':id' => $id_hapus]);
                $success_msg = 'Kategori berita berhasil dihapus.';
            }
        } catch (PDOException $e) {
            $error_msg = 'Gagal menghapus kategori berita: ' . $e->getMessage();
        }
    }
}

try {
    $stmtVisitorCount = $koneksi->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_logs");
    $totalVisitors = $stmtVisitorCount->fetchColumn();
} catch (PDOException $e) {
    $totalVisitors = 0;
}

try {
    $stmtKategoriList = $koneksi->query("SELECT * FROM kategori_berita ORDER BY nama_kategori ASC");
    $kategoriList = $stmtKategoriList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kategoriList = [];
}

try {
    $stmtProfil = $koneksi->query("SELECT * FROM profil_desa WHERE id_profil = 1");
    $profil = $stmtProfil->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profil = [
        'nama_desa' => 'Desa Bluto',
        'kepala_desa' => 'Bapak Kepala Desa',
        'visi' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Desa Bluto</title>
    
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
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

        
        .sidebar-admin {
            background-color: var(--navy-dark) !important;
            height: 100vh;
            width: 280px;
            z-index: 1020;
            border-right: 1px solid #1e293b;
            display: flex;
            flex-direction: column;
        }

        .bg-navy-dark {
            background-color: #0f172a !important;
        }

        .nav-link-sidebar {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 14px 18px;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            transition: all 0.2s ease;
            text-align: left;
            text-decoration: none;
            margin-bottom: 6px;
        }

        .nav-link-sidebar:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .nav-link-sidebar.active {
            background-color: var(--emerald-primary) !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        
        @media (min-width: 992px) {
            #main-content-area {
                margin-left: 280px;
            }
            .sidebar-admin {
                position: fixed;
                top: 0;
                left: 0;
            }
        }

        
        .form-label-custom {
            font-weight: 700;
            font-size: 14px;
            color: var(--navy-dark);
            margin-bottom: 6px;
        }

        .form-control-custom {
            border: 2px solid #cbd5e1; 
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 500;
            background-color: white;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            border-color: var(--emerald-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            outline: none;
        }

        
        .btn-action-lg {
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        
        .dashboard-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }

        
        .table-custom {
            background-color: white;
        }

        .table-custom thead {
            background-color: #f1f5f9;
            color: var(--navy-dark);
            border-bottom: 2px solid #cbd5e1;
        }

        .table-custom th {
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px;
            color: var(--navy-dark);
        }

        .table-custom td {
            padding: 14px;
            font-size: 14px;
            color: var(--navy-dark);
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<div class="d-flex min-vh-100 flex-column flex-lg-row">
    
    <div class="offcanvas-lg offcanvas-start text-white sidebar-admin flex-shrink-0" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
        <div class="offcanvas-header bg-navy-dark border-bottom border-secondary d-lg-none p-3">
            <h5 class="offcanvas-title text-white fw-bold d-flex align-items-center" id="adminSidebarLabel">
                <i class="bi bi-shield-lock-fill text-success fs-3 me-2"></i> Panel Desa Bluto
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body flex-column p-4 h-100 bg-navy-dark justify-content-between">
            <div class="w-100">
                
                <div class="brand mb-4 d-none d-lg-flex align-items-center">
                    <i class="bi bi-shield-lock-fill text-success fs-3 me-2"></i>
                    <div>
                        <h5 class="fw-bold mb-0 text-white">Panel Desa</h5>
                        <small class="text-white-50" style="font-size: 10px;">Admin Desa Bluto</small>
                    </div>
                </div>

                
                <ul class="nav flex-column gap-1 border-0" id="adminTab" role="tablist">
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar active" id="berita-tab" data-bs-toggle="tab" data-bs-target="#berita-pane" type="button" role="tab" aria-controls="berita-pane" aria-selected="true">
                            <i class="bi bi-newspaper me-2"></i> Kelola Berita
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="surat-tab" data-bs-toggle="tab" data-bs-target="#surat-pane" type="button" role="tab" aria-controls="surat-pane" aria-selected="false">
                            <i class="bi bi-file-earmark-text me-2"></i> Pengajuan Surat
                            <?php if ($totalSuratMenunggu > 0): ?>
                                <span class="badge bg-warning text-dark float-end rounded-pill font-semibold ms-2" style="font-size: 10px;"><?= $totalSuratMenunggu ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="umkm-tab" data-bs-toggle="tab" data-bs-target="#umkm-pane" type="button" role="tab" aria-controls="umkm-pane" aria-selected="false">
                            <i class="bi bi-shop me-2"></i> Dapur Lokal (UMKM)
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="galeri-tab" data-bs-toggle="tab" data-bs-target="#galeri-pane" type="button" role="tab" aria-controls="galeri-pane" aria-selected="false">
                            <i class="bi bi-images me-2"></i> Galeri Kegiatan
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="aspirasi-tab" data-bs-toggle="tab" data-bs-target="#aspirasi-pane" type="button" role="tab" aria-controls="aspirasi-pane" aria-selected="false">
                            <i class="bi bi-chat-left-text me-2"></i> Aspirasi Warga
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="profil-tab" data-bs-toggle="tab" data-bs-target="#profil-pane" type="button" role="tab" aria-controls="profil-pane" aria-selected="false">
                            <i class="bi bi-building me-2"></i> Profil Desa
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab" aria-controls="settings-pane" aria-selected="false">
                            <i class="bi bi-sliders me-2"></i> Pengaturan Web
                        </button>
                    </li>
                    <li class="nav-item w-100" role="presentation">
                        <button class="nav-link-sidebar" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button" role="tab" aria-controls="password-pane" aria-selected="false">
                            <i class="bi bi-key me-2"></i> Ganti Password
                        </button>
                    </li>
                </ul>
            </div>

            
            <div class="mt-4 pt-3 border-top border-secondary w-100">
                <a href="index.php" target="_blank" class="btn btn-sm btn-outline-light w-100 rounded-pill mb-2 py-2"><i class="bi bi-globe me-1"></i> Buka Web Utama</a>
                <a href="logout.php" class="btn btn-sm btn-danger w-100 rounded-pill py-2" data-confirm="Apakah Anda yakin ingin keluar dari panel admin dan mengakhiri sesi Anda?"><i class="bi bi-box-arrow-right me-1"></i> Keluar</a>
            </div>
        </div>
    </div>

    
    <div class="flex-grow-1 d-flex flex-column min-vh-100 bg-light" id="main-content-area">
        
        
        <header class="bg-navy-dark text-white py-3 px-4 d-flex align-items-center justify-content-between sticky-top d-lg-none" style="z-index: 1010;">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-light me-3 d-lg-none border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <span class="fw-bold text-white mb-0">Panel Admin</span>
            </div>
            <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3"><i class="bi bi-box-arrow-right"></i></a>
        </header>

        
        <main class="p-4 flex-grow-1">
            
            
            <div class="row mb-4 align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Pusat Pengendali</h2>
                    <p class="text-muted small mb-0">Kelola berita, profil, pengajuan permohonan surat warga, katalog Dapur Lokal, dan dokumentasi secara langsung.</p>
                </div>
                
                <div class="col-md-4 text-md-end d-none d-md-block">
                    <span class="badge bg-white text-dark border p-2 px-3 rounded-pill shadow-sm">
                        <i class="bi bi-person-circle text-success me-1"></i> <?= htmlspecialchars($_SESSION['nama_admin']) ?>
                    </span>
                </div>
            </div>

            
            <?php if($success_msg): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 px-4 mb-4">
                    <i class="bi bi-check-circle-fill me-2 text-success"></i><?= $success_msg ?>
                </div>
            <?php endif; ?>
            <?php if($error_msg): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 px-4 mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?= $error_msg ?>
                </div>
            <?php endif; ?>

            
            <div class="row mb-4 g-3">
                <div class="col-md-4">
                    <div class="card dashboard-card bg-white border-start border-success border-4 h-100 shadow-sm">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase mb-1">Total Berita</h6>
                                <h2 class="fw-bold mb-0 text-success"><?= $totalBerita ?></h2>
                            </div>
                            <h1 class="text-success opacity-25 mb-0"><i class="bi bi-newspaper"></i></h1>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card dashboard-card bg-white border-start border-primary border-4 h-100 shadow-sm">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase mb-1">Pengunjung Web</h6>
                                <h2 class="fw-bold mb-0 text-primary"><?= (int)$totalVisitors ?></h2>
                                <small class="text-muted">Berdasarkan Alamat IP Unik (Unique IP)</small>
                            </div>
                            <h1 class="text-primary opacity-25 mb-0"><i class="bi bi-eye"></i></h1>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card dashboard-card bg-white border-start border-warning border-4 h-100 shadow-sm">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase mb-1">Pengajuan Surat</h6>
                                <h2 class="fw-bold mb-0 text-warning"><?= $totalSuratMenunggu ?></h2>
                                <small class="text-muted">*Menunggu proses</small>
                            </div>
                            <h1 class="text-warning opacity-25 mb-0"><i class="bi bi-file-earmark-text"></i></h1>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-body p-4">
                    <div class="tab-content" id="adminTabContent">
                        
                        
                        <div class="tab-pane fade show active" id="berita-pane" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-list-task me-1 text-success"></i>Daftar Berita Desa</h5>
                                <a href="tambah_berita.php" class="btn btn-success rounded-pill px-4 py-2 fw-semibold">
                                    <i class="bi bi-plus-lg me-1"></i> Tulis Berita Baru
                                </a>
                            </div>
                            
                            <div class="table-responsive rounded-3 border">
                                <table class="table table-custom align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4 py-3" style="width: 50%;">Judul Berita</th>
                                            <th class="py-3">Kategori</th>
                                            <th class="py-3">Tanggal Publikasi</th>
                                            <th class="text-center py-3" style="width: 25%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($beritaList) > 0): ?>
                                            <?php foreach($beritaList as $b): ?>
                                            <tr>
                                                <td class="ps-4 fw-medium text-dark"><?= htmlspecialchars($b['judul']) ?></td>
                                                <td>
                                                    <span class="badge bg-success bg-opacity-10 text-success font-semibold">
                                                        <?= htmlspecialchars($b['nama_kategori']) ?>
                                                    </span>
                                                </td>
                                                <td class="small text-muted"><?= date('d M Y, H:i', strtotime($b['tanggal_publikasi'])) ?> WIB</td>
                                                <td class="text-center">
                                                    <div class="d-flex gap-2 justify-content-center">
                                                        
                                                        <a href="edit_berita.php?id=<?= $b['id_berita'] ?>" class="btn btn-action-lg btn-outline-primary shadow-sm" title="Edit">
                                                            <i class="bi bi-pencil-square"></i> Edit
                                                        </a>
                                                        
                                                        <a href="hapus_berita.php?id=<?= $b['id_berita'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-action-lg btn-danger shadow-sm" title="Hapus" data-confirm="Apakah Anda yakin ingin menghapus berita ini?">
                                                            <i class="bi bi-trash"></i> Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-5 text-muted small">
                                                    <i class="bi bi-info-circle fs-3 d-block mb-2"></i> Belum ada berita yang ditulis.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($totalPagesBerita > 1): ?>
                                <nav class="mt-3">
                                    <ul class="pagination pagination-sm justify-content-end mb-0">
                                        <li class="page-item <?= ($p_berita <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="admin.php?p_berita=<?= $p_berita - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPagesBerita; $i++): ?>
                                            <li class="page-item <?= ($p_berita === $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="admin.php?p_berita=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($p_berita >= $totalPagesBerita) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="admin.php?p_berita=<?= $p_berita + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                            <div class="card border rounded-3 overflow-hidden mt-4">
                                <div class="card-header bg-light py-3 px-3 fw-bold text-dark">
                                    <i class="bi bi-tags me-1 text-success"></i>Kelola Kategori Berita
                                </div>
                                <div class="card-body p-3">
                                    <p class="text-muted small mb-3">Hapus kategori berita yang tidak lagi dibutuhkan. Kategori hanya dapat dihapus jika tidak digunakan oleh berita apa pun.</p>

                                    <?php if (!empty($kategoriList)): ?>
                                        <div class="list-group mb-3">
                                            <?php foreach ($kategoriList as $kat): ?>
                                                <div class="d-flex justify-content-between align-items-center list-group-item">
                                                    <div class="small text-dark"><?= htmlspecialchars($kat['nama_kategori']) ?></div>
                                                    <form action="admin.php" method="POST">
                                                         <input type="hidden" name="hapus_kategori_berita" value="1">
                                                         <input type="hidden" name="id_kategori" value="<?= (int)$kat['id_kategori'] ?>">
                                                         <button type="submit" class="btn btn-action-lg btn-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menghapus kategori berita ini?"><i class="bi bi-trash"></i> Hapus</button>
                                                     </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-light small text-muted">Belum ada kategori berita terdaftar.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="profil-pane" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-pencil-square text-success me-1"></i>Kelola Profil Instansi</h5>
                                <p class="text-muted small mb-0">Perbarui identitas desa, visi, dan nama kepala desa secara langsung ke halaman depan.</p>
                            </div>
                            
                            <form action="admin.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="update_profil" value="1">
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Nama Instansi Desa</label>
                                        <input type="text" name="nama_desa" class="form-control form-control-custom" value="<?= htmlspecialchars($profil['nama_desa']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Nama Kepala Desa</label>
                                        <input type="text" name="kepala_desa" class="form-control form-control-custom" value="<?= htmlspecialchars($profil['kepala_desa']) ?>" required>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Masa Jabatan Kepala Desa</label>
                                        <input type="text" name="masa_jabatan" class="form-control form-control-custom" value="<?= htmlspecialchars($profil['masa_jabatan'] ?? '2021 - 2027') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Foto Kepala Desa</label>
                                        <input type="file" name="foto_kades" class="form-control form-control-custom" accept="image/*">
                                        <?php if (!empty($profil['foto_kades'])): ?>
                                            <?php
                                                $fotoKadesAdmin = preg_match('#^https?://#i', $profil['foto_kades']) ? $profil['foto_kades'] : 'assets/img/' . $profil['foto_kades'];
                                            ?>
                                            <img src="<?= htmlspecialchars($fotoKadesAdmin) ?>" alt="Foto Kepala Desa" class="img-thumbnail mt-2" style="max-height: 100px;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label-custom">Visi Desa</label>
                                    <textarea name="visi" class="form-control form-control-custom" rows="4" required><?= htmlspecialchars($profil['visi']) ?></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label-custom">Misi Desa</label>
                                    <textarea name="misi" class="form-control form-control-custom" rows="6" placeholder="Pisahkan setiap poin misi dengan baris baru"><?= htmlspecialchars($profil['misi'] ?? '') ?></textarea>
                                    <small class="text-muted">Setiap baris yang Anda tulis akan muncul sebagai poin misi di halaman profil.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold py-2">
                                    <i class="bi bi-save me-1"></i> Simpan Pembaruan Profil
                                </button>
                            </form>

                            <div class="card border rounded-3 overflow-hidden mt-4">
                                <div class="card-header bg-light py-3 px-3 fw-bold text-dark">
                                    <i class="bi bi-people-fill me-1 text-success"></i>Kelola Struktur Pemerintahan
                                </div>
                                <div class="card-body p-3">
                                    <p class="text-muted small mb-3">Edit nama, jabatan, dan foto profil anggota struktur pemerintahan yang tampil di halaman profil desa.</p>

                                    <form action="admin.php" method="POST" enctype="multipart/form-data" class="mb-4">
                                        <input type="hidden" name="save_struktur" value="1">
                                        <?php if (!empty($strukturList)): ?>
                                            <?php foreach ($strukturList as $struktur): ?>
                                                <div class="border rounded-3 p-3 mb-3">
                                                    <div class="row g-3 align-items-end">
                                                        <div class="col-md-4">
                                                            <label class="form-label-custom">Nama</label>
                                                            <input type="text" name="struktur_nama[<?= (int)$struktur['id_struktur'] ?>]" class="form-control form-control-custom" value="<?= htmlspecialchars($struktur['nama']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label-custom">Jabatan</label>
                                                            <input type="text" name="struktur_jabatan[<?= (int)$struktur['id_struktur'] ?>]" class="form-control form-control-custom" value="<?= htmlspecialchars($struktur['jabatan']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label-custom">Foto Profil</label>
                                                            <input type="file" name="struktur_foto[<?= (int)$struktur['id_struktur'] ?>]" class="form-control form-control-custom" accept="image/*">
                                                            <?php if (!empty($struktur['foto'])): ?>
                                                                <?php
                                                                    $fotoStrukturAdmin = preg_match('#^https?://#i', $struktur['foto']) ? $struktur['foto'] : 'assets/img/' . $struktur['foto'];
                                                                ?>
                                                                <img src="<?= htmlspecialchars($fotoStrukturAdmin) ?>" alt="Foto struktur" class="img-thumbnail mt-2" style="max-height: 80px;">
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3 mt-3">
                                                        <div class="col-12 text-end">
                                                            <button type="button" class="btn btn-danger btn-sm rounded-pill btn-hapus-struktur" data-form-id="hapus-struktur-<?= (int)$struktur['id_struktur'] ?>" data-confirm="Apakah Anda yakin ingin menghapus anggota struktur pemerintahan ini?">
                                                                <i class="bi bi-trash me-1"></i> Hapus Anggota
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="alert alert-light border rounded-3 small text-muted mb-0">Belum ada data struktur pemerintahan. Tambahkan data baru di bawah ini.</div>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold py-2 mt-2">
                                            <i class="bi bi-save me-1"></i> Simpan Perubahan Struktur
                                        </button>
                                    </form>

                                    <div class="d-none">
                                        <?php foreach ($strukturList as $struktur): ?>
                                            <form id="hapus-struktur-<?= (int)$struktur['id_struktur'] ?>" action="admin.php" method="POST">
                                                <input type="hidden" name="hapus_struktur" value="1">
                                                <input type="hidden" name="id_struktur" value="<?= (int)$struktur['id_struktur'] ?>">
                                            </form>
                                        <?php endforeach; ?>
                                    </div>

                                    <form action="admin.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="tambah_struktur" value="1">
                                        <div class="border rounded-3 p-3">
                                            <h6 class="fw-bold mb-3">Tambah Anggota Baru</h6>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label-custom">Nama</label>
                                                    <input type="text" name="new_nama" class="form-control form-control-custom" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-custom">Jabatan</label>
                                                    <input type="text" name="new_jabatan" class="form-control form-control-custom" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-custom">Foto Profil</label>
                                                    <input type="file" name="new_foto" class="form-control form-control-custom" accept="image/*">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-outline-success rounded-pill px-4 fw-semibold py-2 mt-3">
                                                <i class="bi bi-plus-circle me-1"></i> Tambah Anggota
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="settings-pane" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-sliders text-success me-1"></i>Kelola Statistik & Anggaran Landing Page</h5>
                                <p class="text-muted small mb-0">Ubah data jumlah penduduk dan informasi kontak dinamis desa.</p>
                            </div>
                            
                            <form action="admin.php" method="POST">
                                <input type="hidden" name="update_settings" value="1">
                                
                                
                                <div class="card border mb-4 rounded-3 overflow-hidden">
                                    <div class="card-header bg-light py-2 px-3 fw-bold text-dark">
                                        <i class="bi bi-bar-chart-line-fill me-1 text-success"></i>Statistik Demografi
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label-custom">Total Penduduk</label>
                                                <input type="number" name="total_penduduk" class="form-control form-control-custom" value="<?= (int)$web_settings['statistik']['total_penduduk'] ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label-custom">Laki-Laki (Jiwa)</label>
                                                <input type="number" name="laki_laki" class="form-control form-control-custom" value="<?= (int)$web_settings['statistik']['laki_laki'] ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label-custom">Perempuan (Jiwa)</label>
                                                <input type="number" name="perempuan" class="form-control form-control-custom" value="<?= (int)$web_settings['statistik']['perempuan'] ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                
                                
                                
                                <div class="card border mb-4 rounded-3 overflow-hidden">
                                    <div class="card-header bg-light py-2 px-3 fw-bold text-dark">
                                        <i class="bi bi-telephone-fill me-1 text-success"></i>Kontak Resmi Desa
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label-custom">Alamat Kantor Balai Desa</label>
                                            <input type="text" name="alamat" class="form-control form-control-custom" value="<?= htmlspecialchars($web_settings['kontak']['alamat']) ?>" required>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label-custom">Email Resmi</label>
                                                <input type="email" name="email" class="form-control form-control-custom" value="<?= htmlspecialchars($web_settings['kontak']['email']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-custom">Telepon / WhatsApp</label>
                                                <input type="text" name="telepon" class="form-control form-control-custom" value="<?= htmlspecialchars($web_settings['kontak']['telepon']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold py-2">
                                    <i class="bi bi-save me-1"></i> Simpan Pengaturan Web
                                </button>
                            </form>
                        </div>

                        
                        <div class="tab-pane fade" id="surat-pane" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-text text-success me-1"></i>Pengajuan Surat Warga</h5>
                                <p class="text-muted small mb-0">Verifikasi dan tindak lanjuti berkas permohonan surat keterangan yang diajukan warga secara mandiri.</p>
                            </div>

                            <div class="card border rounded-3 overflow-hidden mb-4">
                                <div class="card-header bg-light py-3 px-3 fw-bold text-dark">
                                    <i class="bi bi-file-earmark-text me-1 text-success"></i>Kelola Jenis Surat Keterangan
                                </div>
                                <div class="card-body p-3">
                                    <p class="text-muted small mb-3">Tambahkan atau hapus jenis surat yang dapat dipilih oleh warga pada form pengajuan.</p>

                                    <?php if (!empty($jenisSuratList)): ?>
                                        <div class="list-group mb-3">
                                            <?php foreach ($jenisSuratList as $jenis): 
                                                    // mapping sederhana dari label ke tipe yang dikenali oleh admin_template_surat.php
                                                    $label = strtolower($jenis['nama']);
                                                    $typeKey = '';
                                                    if (strpos($label, 'usaha') !== false || stripos($label, 'sku') !== false) {
                                                        $typeKey = 'sku';
                                                    } elseif (strpos($label, 'tidak mampu') !== false || stripos($label, 'sktm') !== false) {
                                                        $typeKey = 'sktm';
                                                    } elseif (strpos($label, 'domisili') !== false || stripos($label, 'skd') !== false) {
                                                        $typeKey = 'domisili';
                                                    } elseif (strpos($label, 'nikah') !== false) {
                                                        $typeKey = 'nikah';
                                                    }
                                            ?>
                                                <div class="d-flex justify-content-between align-items-center list-group-item">
                                                    <div class="small text-dark"><?= htmlspecialchars($jenis['nama']) ?></div>
                                                     <div class="d-flex gap-2">
                                                         <?php if (!empty($typeKey)): ?>
                                                             <a href="admin_template_surat.php?action=edit&type=<?= urlencode($typeKey) ?>" class="btn btn-action-lg btn-outline-primary shadow-sm"><i class="bi bi-pencil-square"></i> Edit Format</a>
                                                         <?php else: ?>
                                                             <a href="admin_template_surat.php?action=edit&type=<?= urlencode($jenis['id']) ?>" class="btn btn-action-lg btn-outline-primary shadow-sm"><i class="bi bi-pencil-square"></i> Edit Format</a>
                                                         <?php endif; ?>
                                                         <form action="admin.php" method="POST">
                                                             <input type="hidden" name="hapus_jenis_surat" value="1">
                                                             <input type="hidden" name="id_jenis" value="<?= (int)$jenis['id'] ?>">
                                                             <button type="submit" class="btn btn-action-lg btn-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menghapus jenis surat keterangan ini?"><i class="bi bi-trash"></i> Hapus</button>
                                                         </form>
                                                     </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                         <?php if ($totalPagesJenisSurat > 1): ?>
                                             <nav class="mt-2 mb-3">
                                                 <ul class="pagination pagination-sm justify-content-end mb-0">
                                                     <li class="page-item <?= ($p_jenis <= 1) ? 'disabled' : ''; ?>">
                                                         <a class="page-link" href="admin.php?p_jenis=<?= $p_jenis - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                                     </li>
                                                     <?php for ($i = 1; $i <= $totalPagesJenisSurat; $i++): ?>
                                                         <li class="page-item <?= ($p_jenis === $i) ? 'active' : ''; ?>">
                                                             <a class="page-link" href="admin.php?p_jenis=<?= $i ?>"><?= $i ?></a>
                                                         </li>
                                                     <?php endfor; ?>
                                                     <li class="page-item <?= ($p_jenis >= $totalPagesJenisSurat) ? 'disabled' : ''; ?>">
                                                         <a class="page-link" href="admin.php?p_jenis=<?= $p_jenis + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                                     </li>
                                                 </ul>
                                             </nav>
                                         <?php endif; ?>
                                     <?php else: ?>
                                         <div class="alert alert-light small text-muted">Belum ada jenis surat terdaftar.</div>
                                     <?php endif; ?>

                                      <form action="admin.php" method="POST" class="row g-2 align-items-center">
                                          <input type="hidden" name="tambah_jenis_surat" value="1">
                                          <div class="col-9">
                                              <input type="text" name="nama_jenis" class="form-control form-control-custom" placeholder="Nama jenis surat baru" required>
                                          </div>
                                          <div class="col-3 text-end">
                                              <button type="submit" class="btn btn-action-lg btn-success shadow-sm w-100 justify-content-center"><i class="bi bi-plus-lg"></i> Tambah</button>
                                          </div>
                                      </form>
                                 </div>
                             </div>

                             <div class="table-responsive rounded-3 border">
                                 <table class="table table-custom align-middle mb-0">
                                     <thead>
                                         <tr>
                                             <th class="ps-4 py-3">Nama Pemohon / NIK</th>
                                             <th class="py-3">Jenis Surat</th>
                                             <th class="py-3">No. HP</th>
                                             <th class="py-3">Keperluan / Alamat</th>
                                             <th class="py-3">Tanggal</th>
                                             <th class="py-3">Status</th>
                                             <th class="text-center py-3" style="width: 12%;">Cetak</th>
                                             <th class="text-center py-3" style="width: 25%;">Aksi</th>
                                         </tr>
                                     </thead>
                                     <tbody>
                                         <?php if(count($suratList) > 0): ?>
                                             <?php foreach($suratList as $s): ?>
                                             <tr>
                                                 <td class="ps-4">
                                                     <div class="fw-bold text-dark mb-0.5"><?= htmlspecialchars($s['nama_pemohon']) ?></div>
                                                     <small class="text-muted">NIK: <?= htmlspecialchars($s['nik']) ?></small>
                                                 </td>
                                                 <td class="fw-medium text-dark small"><?= htmlspecialchars($s['jenis_surat']) ?></td>
                                                 <td>
                                                     <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $s['no_hp'])) ?>" 
                                                        class="btn btn-action-lg btn-outline-success" target="_blank">
                                                         <i class="bi bi-whatsapp"></i> Hubungi
                                                     </a>
                                                 </td>
                                                 <td class="small text-dark" style="max-width: 250px;"><?= htmlspecialchars($s['keperluan']) ?></td>
                                                 <td class="small text-muted"><?= date('d M Y, H:i', strtotime($s['tanggal_pengajuan'])) ?> WIB</td>
                                                 <td>
                                                     <?php if($s['status_pengajuan'] == 'Menunggu'): ?>
                                                         <span class="badge bg-warning bg-opacity-10 text-warning font-semibold">Menunggu</span>
                                                     <?php elseif($s['status_pengajuan'] == 'Selesai' || $s['status_pengajuan'] == 'Disetujui'): ?>
                                                         <span class="badge bg-success bg-opacity-10 text-success font-semibold">Disetujui</span>
                                                     <?php else: ?>
                                                         <span class="badge bg-danger bg-opacity-10 text-danger font-semibold">Ditolak</span>
                                                     <?php endif; ?>
                                                 </td>
                                                 <td class="text-center">
                                                     <div class="d-flex gap-2 justify-content-center align-items-center flex-wrap">
                                                         <a href="edit_pengajuan_surat.php?id=<?= $s['id_pengajuan'] ?>" class="btn btn-action-lg btn-outline-secondary shadow-sm" target="_blank" rel="noopener noreferrer" title="Edit pengajuan surat">
                                                             <i class="bi bi-pencil-square"></i> EDIT
                                                         </a>
                                                         <?php if($s['status_pengajuan'] == 'Selesai' || $s['status_pengajuan'] == 'Disetujui'): ?>
                                                             <a href="admin_print_surat.php?id=<?= $s['id_pengajuan'] ?>&format=word" class="btn btn-action-lg btn-outline-secondary shadow-sm" target="_blank" rel="noopener noreferrer" title="Download surat Word" style="background:#16a34a;border-color:#16a34a;color:#fff; font-weight:700;">
                                                                 <i class="bi bi-file-earmark-word"></i> WORD
                                                             </a>
                                                             <a href="admin_print_surat.php?id=<?= $s['id_pengajuan'] ?>" class="btn btn-action-lg btn-primary shadow-sm" target="_blank" rel="noopener noreferrer" title="Cetak surat" style="background:#2563eb;border-color:#2563eb;color:#fff; font-weight:700;">
                                                                 <i class="bi bi-printer"></i> CETAK
                                                             </a>
                                                         <?php else: ?>
                                                             <span class="text-muted small">Belum bisa dicetak</span>
                                                         <?php endif; ?>
                                                     </div>
                                                 </td>
                                                 <td class="text-center">
                                                      <div class="d-flex gap-2 justify-content-center align-items-center flex-wrap">
                                                          <?php if($s['status_pengajuan'] == 'Menunggu'): ?>
                                                              <form action="admin.php" method="POST" class="d-inline">
                                                                  <input type="hidden" name="update_status_surat" value="1">
                                                                  <input type="hidden" name="id_pengajuan" value="<?= $s['id_pengajuan'] ?>">
                                                                  <input type="hidden" name="status_baru" value="Selesai">
                                                                  <button type="submit" class="btn btn-action-lg btn-success shadow-sm" data-confirm="Apakah Anda yakin ingin menyetujui dan menandai pengajuan surat ini selesai?" data-confirm-btn="Ya, Setuju">
                                                                      <i class="bi bi-check-lg"></i> Setuju
                                                                  </button>
                                                              </form>
                                                              <form action="admin.php" method="POST" class="d-inline">
                                                                  <input type="hidden" name="update_status_surat" value="1">
                                                                  <input type="hidden" name="status_baru" value="Ditolak">
                                                                  <input type="hidden" name="id_pengajuan" value="<?= $s['id_pengajuan'] ?>">
                                                                  <button type="submit" class="btn btn-action-lg btn-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menolak pengajuan surat ini?" data-confirm-btn="Ya, Tolak">
                                                                      <i class="bi bi-x-lg"></i> Tolak
                                                                  </button>
                                                              </form>
                                                          <?php endif; ?>

                                                         <form action="admin.php" method="POST" class="d-inline">
                                                             <input type="hidden" name="hapus_pengajuan_surat" value="1">
                                                             <input type="hidden" name="id_pengajuan" value="<?= $s['id_pengajuan'] ?>">
                                                             <button type="submit" class="btn btn-action-lg btn-outline-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menghapus pengajuan surat ini dari riwayat?">
                                                                 <i class="bi bi-trash"></i> Hapus
                                                             </button>
                                                         </form>
                                                     </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5 text-muted small">
                                                    <i class="bi bi-file-earmark-text fs-3 d-block mb-2"></i> Belum ada pengajuan surat dari warga.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($totalPagesSurat > 1): ?>
                                <nav class="mt-3">
                                    <ul class="pagination pagination-sm justify-content-end mb-0">
                                        <li class="page-item <?= ($p_surat <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="admin.php?p_surat=<?= $p_surat - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPagesSurat; $i++): ?>
                                            <li class="page-item <?= ($p_surat === $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="admin.php?p_surat=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($p_surat >= $totalPagesSurat) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="admin.php?p_surat=<?= $p_surat + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>

                        
                        <div class="tab-pane fade" id="umkm-pane" role="tabpanel">
                            <div class="row g-4">
                                
                                <div class="col-lg-4">
                                    <div class="card border rounded-4 p-4">
                                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-plus-circle text-success me-1"></i>Tambah Produk UMKM</h5>
                                        <form action="admin.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="tambah_produk" value="1">
                                            
                                            <div class="mb-2">
                                                <label class="form-label-custom">Nama Produk</label>
                                                <input type="text" name="nama" class="form-control form-control-custom" placeholder="Contoh: Kripik Singkong" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label-custom">Kategori</label>
                                                <select name="kategori" class="form-select form-control-custom" required>
                                                    <option value="Makanan Ringan">Makanan Ringan</option>
                                                    <option value="Minuman">Minuman</option>
                                                    <option value="Kriya & Kerajinan">Kriya & Kerajinan</option>
                                                    <option value="Bahan Pokok">Bahan Pokok</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label-custom">Harga (Rupiah)</label>
                                                <input type="number" name="harga" class="form-control form-control-custom" placeholder="Contoh: 15000" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label-custom">Nama Penjual</label>
                                                <input type="text" name="penjual" class="form-control form-control-custom" placeholder="Contoh: Ibu Siti" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label-custom">Unggah Gambar Produk</label>
                                                <input type="file" name="gambar" class="form-control form-control-custom" accept="image/*" required>
                                                <div class="form-text small text-muted mt-1">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label-custom">Nomor WhatsApp Pembelian</label>
                                                <input type="text" name="no_wa" class="form-control form-control-custom" placeholder="Contoh: 081234567890" required>
                                            </div>
                                            <button type="submit" class="btn btn-success w-100 rounded-pill py-2.5 fw-semibold">
                                                <i class="bi bi-save me-1"></i> Tambah Produk
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                
                                <div class="col-lg-8">
                                    <div class="table-responsive rounded-3 border">
                                        <table class="table table-custom align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-3 py-3">Produk</th>
                                                    <th class="py-3">Harga</th>
                                                    <th class="py-3">Penjual</th>
                                                    <th class="py-3">WA</th>
                                                    <th class="text-center py-3" style="width: 20%;">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(count($umkmList) > 0): ?>
                                                    <?php foreach($umkmList as $u): ?>
                                                    <tr>
                                                        <td class="ps-3">
                                                            <div class="d-flex align-items-center">
                                                                <?php
                                                                    $gambarProduk = '';
                                                                    if (!empty($u['gambar'])) {
                                                                        $gambarProduk = preg_match('#^https?://#i', $u['gambar']) ? $u['gambar'] : 'assets/img/' . $u['gambar'];
                                                                    } else {
                                                                        $gambarProduk = 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&q=80&w=300';
                                                                    }
                                                                ?>
                                                                <img src="<?= htmlspecialchars($gambarProduk) ?>" class="rounded object-fit-cover me-2" style="width: 45px; height: 45px; border: 1px solid #cbd5e1;">
                                                                <div>
                                                                    <div class="fw-bold text-dark lh-1 small mb-1"><?= htmlspecialchars($u['nama']) ?></div>
                                                                    <span class="badge bg-warning text-dark font-semibold" style="font-size: 9px;"><?= htmlspecialchars($u['kategori']) ?></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="small fw-semibold text-success">Rp <?= number_format($u['harga'], 0, ',', '.') ?></td>
                                                        <td class="small text-dark"><?= htmlspecialchars($u['penjual']) ?></td>
                                                        <td class="small">
                                                            <a href="https://wa.me/<?= $u['no_wa'] ?>" class="text-success text-decoration-none fw-bold" target="_blank">
                                                                <i class="bi bi-whatsapp"></i> Chat Penjual
                                                            </a>
                                                        </td>
                                                        <td class="text-center">
                                                            <form action="admin.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="hapus_produk" value="1">
                                                                <input type="hidden" name="id_produk" value="<?= $u['id_produk'] ?>">
                                                                <button type="submit" class="btn btn-action-lg btn-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menghapus produk UMKM ini?">
                                                                    <i class="bi bi-trash"></i> Hapus
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-5 text-muted small">
                                                            <i class="bi bi-shop fs-3 d-block mb-2"></i> Belum ada produk UMKM terdaftar.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if ($totalPagesUMKM > 1): ?>
                                        <nav class="mt-3">
                                            <ul class="pagination pagination-sm justify-content-end mb-0">
                                                <li class="page-item <?= ($p_umkm <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="admin.php?p_umkm=<?= $p_umkm - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                                </li>
                                                <?php for ($i = 1; $i <= $totalPagesUMKM; $i++): ?>
                                                    <li class="page-item <?= ($p_umkm === $i) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="admin.php?p_umkm=<?= $i ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                <li class="page-item <?= ($p_umkm >= $totalPagesUMKM) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="admin.php?p_umkm=<?= $p_umkm + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                                </li>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        
                        <div class="tab-pane fade" id="galeri-pane" role="tabpanel">
                            <div class="row g-4">
                                
                                <div class="col-lg-4">
                                    <div class="card border rounded-4 p-4">
                                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-plus-circle text-success me-1"></i>Unggah Foto Galeri</h5>
                                        <form action="admin.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="tambah_galeri" value="1">
                                            
                                            <div class="mb-2">
                                                <label class="form-label-custom">Keterangan / Judul Foto</label>
                                                <input type="text" name="judul" class="form-control form-control-custom" placeholder="Contoh: Kerja Bakti Dusun" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label-custom">Unggah Gambar Galeri</label>
                                                <input type="file" name="gambar" class="form-control form-control-custom" accept="image/*" required>
                                                <div class="form-text small text-muted mt-1">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label-custom">Tanggal Kegiatan</label>
                                                <input type="text" name="tanggal" class="form-control form-control-custom" placeholder="Contoh: 12 Juni 2026" required>
                                            </div>
                                            <button type="submit" class="btn btn-success w-100 rounded-pill py-2.5 fw-semibold">
                                                <i class="bi bi-save me-1"></i> Tambah ke Galeri
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                
                                <div class="col-lg-8">
                                    <div class="table-responsive rounded-3 border">
                                        <table class="table table-custom align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-3 py-3">Foto & Judul Dokumentasi</th>
                                                    <th class="py-3">Tanggal</th>
                                                    <th class="text-center py-3" style="width: 20%;">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(count($galeriList) > 0): ?>
                                                    <?php foreach($galeriList as $g): ?>
                                                    <tr>
                                                        <td class="ps-3">
                                                            <div class="d-flex align-items-center">
                                                                <?php
                                                                    $gambarGaleri = '';
                                                                    if (!empty($g['gambar'])) {
                                                                        $gambarGaleri = preg_match('#^https?://#i', $g['gambar']) ? $g['gambar'] : 'assets/img/' . $g['gambar'];
                                                                    } else {
                                                                        $gambarGaleri = 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&q=80&w=600&h=400';
                                                                    }
                                                                ?>
                                                                <img src="<?= htmlspecialchars($gambarGaleri) ?>" class="rounded object-fit-cover me-2" style="width: 60px; height: 40px; border: 1px solid #cbd5e1;">
                                                                <div class="fw-bold text-dark small text-truncate" style="max-width: 300px;"><?= htmlspecialchars($g['judul']) ?></div>
                                                            </div>
                                                        </td>
                                                        <td class="small text-muted"><?= htmlspecialchars($g['tanggal']) ?></td>
                                                        <td class="text-center">
                                                            <form action="admin.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="hapus_galeri" value="1">
                                                                <input type="hidden" name="id_galeri" value="<?= $g['id_galeri'] ?>">
                                                                <button type="submit" class="btn btn-action-lg btn-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menghapus foto ini dari galeri kegiatan?">
                                                                    <i class="bi bi-trash"></i> Hapus
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-5 text-muted small">
                                                            <i class="bi bi-images fs-3 d-block mb-2"></i> Belum ada foto di galeri.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if ($totalPagesGaleri > 1): ?>
                                        <nav class="mt-3">
                                            <ul class="pagination pagination-sm justify-content-end mb-0">
                                                <li class="page-item <?= ($p_galeri <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="admin.php?p_galeri=<?= $p_galeri - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                                </li>
                                                <?php for ($i = 1; $i <= $totalPagesGaleri; $i++): ?>
                                                    <li class="page-item <?= ($p_galeri === $i) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="admin.php?p_galeri=<?= $i ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                <li class="page-item <?= ($p_galeri >= $totalPagesGaleri) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="admin.php?p_galeri=<?= $p_galeri + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                                </li>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        
                        <div class="tab-pane fade" id="aspirasi-pane" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-chat-left-text text-success me-1"></i>Kotak Aspirasi Warga</h5>
                                <p class="text-muted small mb-0">Baca masukan, aduan, saran, atau aduan masyarakat yang dikirimkan warga melalui form aspirasi.</p>
                            </div>
                            
                            <div class="table-responsive rounded-3 border">
                                <table class="table table-custom align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-3 py-3">Pengirim</th>
                                            <th class="py-3">Kontak WA/Email</th>
                                            <th class="py-3" style="width: 40%;">Isi Aspirasi</th>
                                            <th class="py-3">Waktu Kirim</th>
                                            <th class="text-center py-3" style="width: 20%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($aspirasiList) > 0): ?>
                                            <?php foreach($aspirasiList as $a): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark small"><?= htmlspecialchars($a['nama_lengkap']) ?></td>
                                                <td class="small">
                                                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $a['kontak'])) ?>" class="btn btn-action-lg btn-outline-success" target="_blank">
                                                        <i class="bi bi-whatsapp"></i> Hubungi
                                                    </a>
                                                </td>
                                                <td class="small text-dark" style="white-space: pre-line;"><?= htmlspecialchars($a['isi_pesan']) ?></td>
                                                <td class="small text-muted"><?= date('d M Y, H:i', strtotime($a['tanggal_kirim'])) ?> WIB</td>
                                                <td class="text-center">
                                                    <form action="admin.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="hapus_aspirasi" value="1">
                                                        <input type="hidden" name="id_aspirasi" value="<?= $a['id_aspirasi'] ?>">
                                                        <button type="submit" class="btn btn-action-lg btn-danger shadow-sm" data-confirm="Apakah Anda yakin ingin menghapus aduan/aspirasi warga ini?">
                                                            <i class="bi bi-trash"></i> Hapus
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted small">
                                                    <i class="bi bi-chat-left-text fs-3 d-block mb-2"></i> Kotak aspirasi kosong. Belum ada aduan warga.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($totalPagesAspirasi > 1): ?>
                                <nav class="mt-3">
                                    <ul class="pagination pagination-sm justify-content-end mb-0">
                                        <li class="page-item <?= ($p_aspirasi <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="admin.php?p_aspirasi=<?= $p_aspirasi - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPagesAspirasi; $i++): ?>
                                            <li class="page-item <?= ($p_aspirasi === $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="admin.php?p_aspirasi=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($p_aspirasi >= $totalPagesAspirasi) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="admin.php?p_aspirasi=<?= $p_aspirasi + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="password-pane" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-key text-success me-1"></i>Ganti Password Admin</h5>
                                <p class="text-muted small mb-0">Ubah kata sandi akun administrator Anda. Setelah berhasil diubah, Anda akan diminta login kembali.</p>
                            </div>
                            
                            <form action="admin.php" method="POST">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3" style="max-width: 400px;">
                                    <label class="form-label-custom">Password Lama</label>
                                    <input type="password" name="password_lama" class="form-control form-control-custom" required>
                                </div>
                                
                                <div class="mb-3" style="max-width: 400px;">
                                    <label class="form-label-custom">Password Baru</label>
                                    <input type="password" name="password_baru" class="form-control form-control-custom" required>
                                </div>

                                <div class="mb-4" style="max-width: 400px;">
                                    <label class="form-label-custom">Konfirmasi Password Baru</label>
                                    <input type="password" name="password_konfirmasi" class="form-control form-control-custom" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold py-2">
                                    <i class="bi bi-save me-1"></i> Perbarui Password
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Modal Konfirmasi Kustom Premium -->
<div class="modal fade" id="customConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2050;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Konfirmasi Tindakan</h5>
                <p id="customConfirmMessage" class="text-muted small mb-0 px-2">Apakah Anda yakin ingin melanjutkan tindakan ini?</p>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-center gap-2 pb-4 pt-0">
                <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="customConfirmYesBtn" class="btn btn-danger rounded-pill px-4 fw-semibold">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Persist active tab across pagination/page refreshes
    const activeTab = localStorage.getItem('adminActiveTab');
    if (activeTab) {
        const tabTrigger = document.querySelector(`[data-bs-target="${activeTab}"]`);
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(trigger => {
        trigger.addEventListener('shown.bs.tab', event => {
            const target = event.target.getAttribute('data-bs-target');
            localStorage.setItem('adminActiveTab', target);
        });
    });

    // Custom confirm modal handler
    let pendingAction = null;
    const confirmModalEl = document.getElementById('customConfirmModal');
    if (confirmModalEl) {
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const confirmMessage = document.getElementById('customConfirmMessage');
        const confirmYesBtn = document.getElementById('customConfirmYesBtn');
        const confirmIconContainer = confirmModalEl.querySelector('.d-inline-flex');
        const confirmIcon = confirmIconContainer.querySelector('i');

        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-confirm]');
            if (target) {
                e.preventDefault();
                pendingAction = target;
                
                // Ambil pesan konfirmasi
                confirmMessage.textContent = target.getAttribute('data-confirm');
                
                // Sesuaikan desain modal berdasarkan tombol aksi
                const customBtnText = target.getAttribute('data-confirm-btn');
                if (target.classList.contains('btn-success')) {
                    confirmIconContainer.className = 'd-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3';
                    confirmIcon.className = 'bi bi-check-circle-fill fs-2';
                    confirmYesBtn.className = 'btn btn-success rounded-pill px-4 fw-semibold';
                    confirmYesBtn.textContent = customBtnText || 'Ya, Setuju';
                } else if (target.classList.contains('btn-primary')) {
                    confirmIconContainer.className = 'd-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3';
                    confirmIcon.className = 'bi bi-question-circle-fill fs-2';
                    confirmYesBtn.className = 'btn btn-primary rounded-pill px-4 fw-semibold';
                    confirmYesBtn.textContent = customBtnText || 'Ya, Lanjutkan';
                } else {
                    confirmIconContainer.className = 'd-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3';
                    confirmIcon.className = 'bi bi-exclamation-triangle-fill fs-2';
                    confirmYesBtn.className = 'btn btn-danger rounded-pill px-4 fw-semibold';
                    confirmYesBtn.textContent = customBtnText || (target.textContent.trim().toLowerCase().includes('tolak') ? 'Ya, Tolak' : 'Ya, Hapus');
                }

                confirmModal.show();
            }
        });

        confirmYesBtn.addEventListener('click', function() {
            if (pendingAction) {
                confirmModal.hide();
                
                if (pendingAction.type === 'submit' || pendingAction.tagName === 'BUTTON') {
                    const form = pendingAction.closest('form');
                    if (form) {
                        if (pendingAction.name) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = pendingAction.name;
                            hidden.value = pendingAction.value || '1';
                            form.appendChild(hidden);
                        }
                        form.submit();
                    }
                } else if (pendingAction.classList.contains('btn-hapus-struktur')) {
                    const formId = pendingAction.getAttribute('data-form-id');
                    const form = document.getElementById(formId);
                    if (form) {
                        form.submit();
                    }
                } else if (pendingAction.tagName === 'A') {
                    window.location.href = pendingAction.href;
                }
            }
        });
    }
});
</script>
</body>
</html>
