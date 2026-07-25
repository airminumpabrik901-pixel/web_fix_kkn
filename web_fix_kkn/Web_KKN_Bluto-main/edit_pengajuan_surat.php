<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID pengajuan tidak valid.');
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pengajuan'])) {
    $nama_pemohon = trim($_POST['nama_pemohon'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $jenis_surat = trim($_POST['jenis_surat'] ?? '');
    $nomor_registrasi = trim($_POST['nomor_registrasi'] ?? '');
    $keperluan = trim($_POST['keperluan'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $agama = trim($_POST['agama'] ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $nama_usaha = trim($_POST['nama_usaha'] ?? '');
    $lokasi_usaha = trim($_POST['lokasi_usaha'] ?? '');
    $status_tinggal = trim($_POST['status_tinggal'] ?? '');

    try {
        $stmt = $koneksi->prepare("UPDATE pengajuan_surat SET
            nama_pemohon = :nama_pemohon,
            nik = :nik,
            no_hp = :no_hp,
            jenis_surat = :jenis_surat,
            nomor_registrasi = :nomor_registrasi,
            keperluan = :keperluan,
            alamat = :alamat,
            tempat_lahir = :tempat_lahir,
            tanggal_lahir = :tanggal_lahir,
            jenis_kelamin = :jenis_kelamin,
            agama = :agama,
            pekerjaan = :pekerjaan,
            nama_usaha = :nama_usaha,
            lokasi_usaha = :lokasi_usaha,
            status_tinggal = :status_tinggal
            WHERE id_pengajuan = :id");
        $stmt->execute([
            ':nama_pemohon' => $nama_pemohon,
            ':nik' => $nik,
            ':no_hp' => $no_hp,
            ':jenis_surat' => $jenis_surat,
            ':nomor_registrasi' => $nomor_registrasi,
            ':keperluan' => $keperluan,
            ':alamat' => $alamat,
            ':tempat_lahir' => $tempat_lahir,
            ':tanggal_lahir' => $tanggal_lahir,
            ':jenis_kelamin' => $jenis_kelamin,
            ':agama' => $agama,
            ':pekerjaan' => $pekerjaan,
            ':nama_usaha' => $nama_usaha,
            ':lokasi_usaha' => $lokasi_usaha,
            ':status_tinggal' => $status_tinggal,
            ':id' => $id
        ]);

        $success_msg = 'Data pengajuan surat berhasil diperbarui.';
    } catch (PDOException $e) {
        $error_msg = 'Gagal memperbarui pengajuan: ' . $e->getMessage();
    }
}

try {
    $stmt = $koneksi->prepare('SELECT * FROM pengajuan_surat WHERE id_pengajuan = :id');
    $stmt->execute([':id' => $id]);
    $surat = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Gagal mengambil data pengajuan: ' . $e->getMessage());
}

if (!$surat) {
    die('Pengajuan surat tidak ditemukan.');
}

function old_value($surat, $field) {
    return htmlspecialchars($surat[$field] ?? '');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Pengajuan Surat</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 980px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 24px; }
        h1 { margin-top: 0; font-size: 28px; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e7f8ec; color: #176b30; }
        .alert-danger { background: #f8e5e5; color: #8b1b1b; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #243447; }
        input[type="text"], input[type="date"], textarea, select { width: 100%; padding: 12px 14px; border: 1px solid #d1d9e6; border-radius: 10px; background: #fff; font-size: 14px; color: #1e293b; }
        textarea { min-height: 120px; resize: vertical; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .btn { border: none; border-radius: 10px; cursor: pointer; padding: 12px 18px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-light { background: #f1f5f9; color: #0f172a; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="bi bi-pencil-square"></i> Edit Pengajuan Surat</h1>
            <p>Periksa dan perbarui data pemohon sebelum mencetak surat.</p>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="save_pengajuan" value="1">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Nama Pemohon</label>
                        <input type="text" name="nama_pemohon" value="<?php echo old_value($surat, 'nama_pemohon'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>NIK</label>
                        <input type="text" name="nik" value="<?php echo old_value($surat, 'nik'); ?>" maxlength="16" pattern="\d*">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>No. HP</label>
                        <input type="text" name="no_hp" value="<?php echo old_value($surat, 'no_hp'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nomor Surat</label>
                        <input type="text" name="nomor_registrasi" value="<?php echo old_value($surat, 'nomor_registrasi'); ?>">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Jenis Surat</label>
                        <input type="text" name="jenis_surat" value="<?php echo old_value($surat, 'jenis_surat'); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Keperluan / Alamat</label>
                    <textarea name="keperluan"><?php echo old_value($surat, 'keperluan'); ?></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat"><?php echo old_value($surat, 'alamat'); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" value="<?php echo old_value($surat, 'tempat_lahir'); ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="<?php echo old_value($surat, 'tanggal_lahir'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <input type="text" name="jenis_kelamin" value="<?php echo old_value($surat, 'jenis_kelamin'); ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Agama</label>
                        <input type="text" name="agama" value="<?php echo old_value($surat, 'agama'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Pekerjaan</label>
                        <input type="text" name="pekerjaan" value="<?php echo old_value($surat, 'pekerjaan'); ?>">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Nama Usaha</label>
                        <input type="text" name="nama_usaha" value="<?php echo old_value($surat, 'nama_usaha'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Lokasi Usaha</label>
                        <input type="text" name="lokasi_usaha" value="<?php echo old_value($surat, 'lokasi_usaha'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Status Tinggal</label>
                    <input type="text" name="status_tinggal" value="<?php echo old_value($surat, 'status_tinggal'); ?>">
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
                    <a href="admin.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Pengajuan</a>
                    <a href="admin_print_surat.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-light"><i class="bi bi-printer"></i> Lihat / Cetak</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
