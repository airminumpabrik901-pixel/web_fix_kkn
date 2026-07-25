<?php

$alert_sukses = false;
$alert_error = '';
$layanan_pilihan = '';
$nomor_reg = '';

require_once __DIR__ . '/../config/surat_helper.php';

// Helper function untuk pencocokan kunci tipe_surat
if (!function_exists('getTipeKeyLayanan')) {
    function getTipeKeyLayanan($name) {
        $label = strtolower($name);
        if (strpos($label, 'usaha') !== false || stripos($label, 'sku') !== false) return 'sku';
        if (strpos($label, 'tidak mampu') !== false || stripos($label, 'sktm') !== false) return 'sktm';
        if (strpos($label, 'domisili') !== false || stripos($label, 'skd') !== false) return 'domisili';
        if (strpos($label, 'nikah') !== false) return 'nikah';
        if (strpos($label, 'skck') !== false) return 'skck';
        return preg_replace('/[^a-z0-9]/', '', $label);
    }
}

// Ambil daftar jenis surat dari database
$jenisList = [];
try {
    $stmtJenis = $koneksi->query("SELECT nama FROM jenis_surat ORDER BY id");
    $jenisList = $stmtJenis->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $jenisList = [];
}

// Ambil semua template HTML dari database (template_surat)
$templateHtmls = [];
try {
    $stmtTemp = $koneksi->query("SELECT tipe_surat, template_html FROM template_surat");
    while ($row = $stmtTemp->fetch(PDO::FETCH_ASSOC)) {
        $templateHtmls[$row['tipe_surat']] = $row['template_html'];
    }
} catch (PDOException $e) {
    // ignore
}

// Pindai variabel yang dibutuhkan oleh setiap template surat (secara dinamis dari admin_template_surat)
$templateVariables = [];
$allTypesToScan = array_unique(array_merge($jenisList, ['sku', 'sktm', 'domisili', 'nikah', 'skck', 'Surat Keterangan Usaha (SKU)', 'Surat Keterangan Tidak Mampu (SKTM)', 'Surat Keterangan Domisili (SKD)', 'Surat Keterangan Nikah', 'Surat Keterangan Pengantar SKCK']));

foreach ($allTypesToScan as $jName) {
    $key = getTipeKeyLayanan($jName);
    
    // Ambil HTML template: dari DB jika ada, atau dari draf standar surat_helper jika belum ada di DB
    $html = $templateHtmls[$jName] ?? $templateHtmls[$key] ?? '';
    if (empty($html)) {
        $html = generateSuratDraft($key, [
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
    }
    
    $vars = [];
    if (stripos($html, '{nik}') !== false) $vars[] = 'nik';
    if (stripos($html, '{alamat}') !== false) $vars[] = 'alamat';
    if (stripos($html, '{nama_usaha}') !== false) $vars[] = 'nama_usaha';
    if (stripos($html, '{lokasi_usaha}') !== false) $vars[] = 'lokasi_usaha';
    if (stripos($html, '{status_tinggal}') !== false) $vars[] = 'status_tinggal';
    if (stripos($html, '{pekerjaan}') !== false) $vars[] = 'pekerjaan';
    if (stripos($html, '{agama}') !== false) $vars[] = 'agama';
    if (stripos($html, '{tempat_tgl_lahir}') !== false || stripos($html, '{tempat_lahir}') !== false || stripos($html, '{tanggal_lahir}') !== false) {
        $vars[] = 'tempat_lahir';
        $vars[] = 'tanggal_lahir';
    }
    if (stripos($html, '{jenis_kelamin}') !== false) $vars[] = 'jenis_kelamin';
    if (stripos($html, '{keperluan}') !== false || stripos($html, '{keterangan}') !== false) $vars[] = 'keperluan';
    
    // Simpan ke berbagai kunci (nama persis, key pendek, dan lowercase) agar pencarian di JS/PHP pasti ketemu!
    $templateVariables[$jName] = $vars;
    $templateVariables[$key] = $vars;
    $templateVariables[strtolower($jName)] = $vars;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajukan_layanan'])) {
    $jenis_surat = htmlspecialchars($_POST['jenis_surat'] ?? '');
    $nik = preg_replace('/[^0-9]/', '', $_POST['nik'] ?? '');
    $nama_pemohon = htmlspecialchars($_POST['nama_pemohon'] ?? '');
    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $alamat = htmlspecialchars($_POST['alamat'] ?? '');
    
    // Field Opsional / Dinamis
    $keperluan = htmlspecialchars($_POST['keperluan'] ?? '');
    $tempat_lahir = htmlspecialchars($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = htmlspecialchars($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin = htmlspecialchars($_POST['jenis_kelamin'] ?? '');
    $agama = htmlspecialchars($_POST['agama'] ?? '');
    $pekerjaan = htmlspecialchars($_POST['pekerjaan'] ?? '');
    $nama_usaha = htmlspecialchars($_POST['nama_usaha'] ?? '');
    $lokasi_usaha = htmlspecialchars($_POST['lokasi_usaha'] ?? '');
    $status_tinggal = htmlspecialchars($_POST['status_tinggal'] ?? '');
    
    // Cek kolom apa saja yang wajib untuk template ini
    $key = getTipeKeyLayanan($jenis_surat);
    $required_fields = $templateVariables[$jenis_surat] ?? $templateVariables[$key] ?? $templateVariables[strtolower($jenis_surat)] ?? [];
    
    $errors = [];
    if (empty($jenis_surat)) $errors[] = 'Jenis Surat';
    if (empty($nama_pemohon)) $errors[] = 'Nama Pemohon';
    if (empty($no_hp)) $errors[] = 'Nomor HP';
    
    // Validasi NIK HANYA jika variabel {nik} ada dalam template surat
    if (in_array('nik', $required_fields)) {
        if (empty($nik)) {
            $errors[] = 'NIK';
        } elseif (strlen($nik) !== 16) {
            $alert_error = 'Nomor Induk Kependudukan (NIK) harus berupa 16 digit angka!';
        }
    }

    // Validasi Alamat HANYA jika variabel {alamat} ada dalam template surat
    if (in_array('alamat', $required_fields) && empty($alamat)) {
        $errors[] = 'Alamat';
    }
    
    // Validasi field opsional dinamis lainnya
    foreach ($required_fields as $field) {
        if ($field === 'tempat_lahir' && empty($tempat_lahir)) $errors[] = 'Tempat Lahir';
        if ($field === 'tanggal_lahir' && empty($tanggal_lahir)) $errors[] = 'Tanggal Lahir';
        if ($field === 'jenis_kelamin' && empty($jenis_kelamin)) $errors[] = 'Jenis Kelamin';
        if ($field === 'agama' && empty($agama)) $errors[] = 'Agama';
        if ($field === 'pekerjaan' && empty($pekerjaan)) $errors[] = 'Pekerjaan';
        if ($field === 'nama_usaha' && empty($nama_usaha)) $errors[] = 'Nama Usaha';
        if ($field === 'lokasi_usaha' && empty($lokasi_usaha)) $errors[] = 'Lokasi Usaha';
        if ($field === 'status_tinggal' && empty($status_tinggal)) $errors[] = 'Status Tinggal';
        if ($field === 'keperluan' && empty($keperluan)) $errors[] = 'Keperluan';
    }
    
    if (!empty($errors)) {
        $alert_error = 'Kolom berikut wajib diisi: ' . implode(', ', $errors);
    } elseif (empty($alert_error) && in_array('nik', $required_fields) && strlen($nik) !== 16) {
        $alert_error = 'Nomor Induk Kependudukan (NIK) harus berupa 16 digit angka!';
    } elseif (empty($alert_error) && (strlen($no_hp) < 10 || strlen($no_hp) > 15)) {
        $alert_error = 'Nomor HP / WhatsApp harus berupa angka (10-15 digit)!';
    } elseif (empty($alert_error)) {
        $nomor_reg = 'REG/BLUTO/' . date('Ymd') . '/' . rand(100, 999);
        
        try {
            $stmtInsert = $koneksi->prepare("INSERT INTO pengajuan_surat (nama_pemohon, nik, no_hp, jenis_surat, keperluan, nomor_registrasi, tempat_lahir, tanggal_lahir, jenis_kelamin, agama, pekerjaan, alamat, nama_usaha, lokasi_usaha, status_tinggal) VALUES (:nama, :nik, :no_hp, :jenis, :keperluan, :reg, :tempat_lahir, :tanggal_lahir, :jenis_kelamin, :agama, :pekerjaan, :alamat, :nama_usaha, :lokasi_usaha, :status_tinggal)");
            $stmtInsert->execute([
                ':nama' => $nama_pemohon,
                ':nik' => $nik,
                ':no_hp' => $no_hp,
                ':jenis' => $jenis_surat,
                ':keperluan' => $keperluan,
                ':reg' => $nomor_reg,
                ':tempat_lahir' => $tempat_lahir,
                ':tanggal_lahir' => $tanggal_lahir,
                ':jenis_kelamin' => $jenis_kelamin,
                ':agama' => $agama,
                ':pekerjaan' => $pekerjaan,
                ':alamat' => $alamat,
                ':nama_usaha' => $nama_usaha,
                ':lokasi_usaha' => $lokasi_usaha,
                ':status_tinggal' => $status_tinggal
            ]);
            
            $alert_sukses = true;
            $layanan_pilihan = $jenis_surat;
        } catch (PDOException $e) {
            $alert_error = "Error database: " . $e->getMessage();
        }
    }

    // Bersihkan riwayat form POST di browser agar F5 (Refresh) tidak memicu kirim ulang POST
    echo "<script>if(window.history.replaceState){window.history.replaceState(null,null,window.location.href);}</script>";
}
?>

<div class="container mt-2 mb-5">
    
    <div class="bg-success text-white py-4 px-4 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, var(--emerald-primary) 0%, #115c3a 100%);">
        <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2"></i>Layanan Publik Mandiri</h2>
        <p class="mb-0 text-white-50 small">Ajukan surat-surat keterangan administrasi secara mandiri dan cepat melalui portal desa.</p>
    </div>

    
    <?php if ($alert_error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm p-4 mb-4" role="alert">
            <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Pengajuan Gagal!</h5>
            <p class="mb-0 small text-danger fw-semibold"><?= $alert_error ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($alert_sukses): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm p-4 mb-4" role="alert">
            <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-check-circle-fill me-2"></i>Pengajuan Berhasil Dikirim!</h5>
            <p class="mb-0 small text-muted">Pengajuan untuk <strong><?= htmlspecialchars($layanan_pilihan) ?></strong> telah terdaftar di sistem. Silakan simpan nomor registrasi pengajuan Anda: <strong class="text-success"><?= htmlspecialchars($nomor_reg) ?></strong>. Petugas Balai Desa Bluto akan memverifikasi berkas Anda dan menghubungi via WhatsApp.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php $layanan_pilihan = ''; // Reset pilihan di dropdown agar kembali ke "-- Pilih Surat Keterangan --" ?>
    <?php endif; ?>

    <div class="row g-4">
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">
                        <i class="bi bi-list-check me-2"></i>Jenis Layanan yang Tersedia
                    </h5>
                    
                    <div class="accordion accordion-flush" id="accordionPersyaratan">
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#flush-sku">
                                    <i class="bi bi-shop me-2 text-success"></i> Surat Keterangan Usaha (SKU)
                                </button>
                            </h2>
                            <div id="flush-sku" class="accordion-collapse collapse" data-bs-parent="#accordionPersyaratan">
                                <div class="accordion-body text-muted small">
                                    <strong>Persyaratan Dokumen:</strong>
                                    <ul class="ps-3 mt-1 mb-0">
                                        <li>Fotokopi KTP Pemohon</li>
                                        <li>Fotokopi Kartu Keluarga (KK)</li>
                                        <li>Surat Pengantar RT/RW setempat</li>
                                        <li>Foto tempat usaha / jenis barang dagangan</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#flush-sktm">
                                    <i class="bi bi-heart-pulse-fill me-2 text-success"></i> Surat Keterangan Tidak Mampu (SKTM)
                                </button>
                            </h2>
                            <div id="flush-sktm" class="accordion-collapse collapse" data-bs-parent="#accordionPersyaratan">
                                <div class="accordion-body text-muted small">
                                    <strong>Persyaratan Dokumen:</strong>
                                    <ul class="ps-3 mt-1 mb-0">
                                        <li>Fotokopi KTP & KK</li>
                                        <li>Surat Pengantar RT/RW (menerangkan kondisi ekonomi)</li>
                                        <li>Surat pernyataan tidak mampu bermaterai 10.000</li>
                                        <li>Foto rumah tinggal pemohon tampak depan</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#flush-skd">
                                    <i class="bi bi-geo-alt-fill me-2 text-success"></i> Surat Keterangan Domisili (SKD)
                                </button>
                            </h2>
                            <div id="flush-skd" class="accordion-collapse collapse" data-bs-parent="#accordionPersyaratan">
                                <div class="accordion-body text-muted small">
                                    <strong>Persyaratan Dokumen:</strong>
                                    <ul class="ps-3 mt-1 mb-0">
                                        <li>Fotokopi KTP asli</li>
                                        <li>Fotokopi KK</li>
                                        <li>Surat Pengantar RT/RW domisili setempat</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <div class="card border-0 shadow-sm rounded-4 bg-success bg-opacity-5">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-info-circle-fill me-2"></i>Alur Pelayanan Mandiri</h5>
                    <ol class="small text-muted ps-3 mb-0">
                        <li class="mb-2">Isi formulir pengajuan di sebelah kanan dengan lengkap dan benar.</li>
                        <li class="mb-2">Sistem akan memproses draf administrasi dan memberikan Kode Registrasi Pengajuan.</li>
                        <li class="mb-2">Petugas pelayanan balai desa akan melakukan verifikasi data dalam 1x24 jam kerja.</li>
                        <li>Ambil cetak fisik surat di loket pelayanan Balai Desa Bluto dengan membawa persyaratan asli.</li>
                    </ol>
                </div>
            </div>
        </div>

        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-4">
                        <i class="bi bi-pencil-square me-2"></i>Formulir Pengajuan Surat
                    </h5>
                    
                    <form action="index.php?page=layanan" method="POST">
                        <input type="hidden" name="ajukan_layanan" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Pilih Jenis Surat Keterangan</label>
                            <select name="jenis_surat" id="select_jenis_surat" class="form-select rounded-3" required>
                                <option value="" disabled selected>-- Pilih Surat Keterangan --</option>
                                <?php if (!empty($jenisList)): ?>
                                    <?php foreach ($jenisList as $j): ?>
                                        <option value="<?= htmlspecialchars($j) ?>" <?= ($layanan_pilihan === $j) ? 'selected' : '' ?>><?= htmlspecialchars($j) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="Surat Keterangan Usaha (SKU)">Surat Keterangan Usaha (SKU)</option>
                                    <option value="Surat Keterangan Tidak Mampu (SKTM)">Surat Keterangan Tidak Mampu (SKTM)</option>
                                    <option value="Surat Keterangan Domisili (SKD)">Surat Keterangan Domisili (SKD)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div id="group_nik" class="mb-3 d-none">
                            <label class="form-label small fw-semibold">Nomor Induk Kependudukan (NIK)</label>
                            <input type="text" name="nik" id="input_nik" class="form-control rounded-3" placeholder="Masukkan 16 digit NIK Anda" pattern="\d{16}" title="Harus 16 digit angka" oninput="this.value = this.value.replace(/[^0-9]/g, '');" maxlength="16" inputmode="numeric">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Lengkap (Sesuai KTP)</label>
                            <input type="text" name="nama_pemohon" class="form-control rounded-3" placeholder="Masukkan nama lengkap" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nomor HP / WhatsApp (Aktif)</label>
                            <input type="text" name="no_hp" class="form-control rounded-3" placeholder="Contoh: 081234567890" pattern="\d{10,15}" title="Harus 10-15 digit angka" oninput="this.value = this.value.replace(/[^0-9]/g, '');" maxlength="15" inputmode="numeric" required>
                        </div>

                        <!-- Data Tempat & Tanggal Lahir -->
                        <div id="row_birth" class="row g-2 mb-3 d-none">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" id="input_tempat_lahir" class="form-control rounded-3" placeholder="Contoh: Sumenep">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" id="input_tanggal_lahir" class="form-control rounded-3">
                            </div>
                        </div>

                        <!-- Data Jenis Kelamin, Agama, Pekerjaan -->
                        <div class="row g-2 mb-3">
                            <div id="group_jenis_kelamin" class="col-md-4 d-none">
                                <label class="form-label small fw-semibold">Jenis Kelamin</label>
                                <select name="jenis_kelamin" id="input_jenis_kelamin" class="form-select rounded-3">
                                    <option value="" disabled selected>Pilih</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div id="group_agama" class="col-md-4 d-none">
                                <label class="form-label small fw-semibold">Agama</label>
                                <select name="agama" id="input_agama" class="form-select rounded-3">
                                    <option value="" disabled selected>Pilih</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Protestan">Protestan</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Khonghucu">Khonghucu</option>
                                </select>
                            </div>
                            <div id="group_pekerjaan" class="col-md-4 d-none">
                                <label class="form-label small fw-semibold">Pekerjaan</label>
                                <input type="text" name="pekerjaan" id="input_pekerjaan" class="form-control rounded-3" placeholder="Contoh: Pelajar/Tani">
                            </div>
                        </div>

                        <!-- Data Usaha Tambahan -->
                        <div id="row_usaha" class="row g-2 mb-3 d-none">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nama Usaha</label>
                                <input type="text" name="nama_usaha" id="input_nama_usaha" class="form-control rounded-3" placeholder="Contoh: Toko Barokah">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Lokasi Usaha</label>
                                <input type="text" name="lokasi_usaha" id="input_lokasi_usaha" class="form-control rounded-3" placeholder="Contoh: Jl. Raya Bluto No. 12">
                            </div>
                        </div>

                        <!-- Status Tinggal Tambahan -->
                        <div id="group_status_tinggal" class="mb-3 d-none">
                            <label class="form-label small fw-semibold">Status Tinggal</label>
                            <select name="status_tinggal" id="input_status_tinggal" class="form-select rounded-3">
                                <option value="Tetap" selected>Tetap</option>
                                <option value="Kontrak">Kontrak / Sewa</option>
                                <option value="Sementara">Sementara</option>
                            </select>
                        </div>

                        <div id="group_alamat" class="mb-3 d-none">
                            <label class="form-label small fw-semibold">Alamat Lengkap (Sesuai KTP)</label>
                            <textarea name="alamat" id="input_alamat" class="form-control rounded-3" rows="2" placeholder="Contoh: Dusun Tajjan RT 013 RW 006 Desa Bluto"></textarea>
                        </div>

                        <div id="group_keperluan" class="mb-4 d-none">
                            <label class="form-label small fw-semibold">Keperluan (Tujuan Pembuatan Surat)</label>
                            <input type="text" name="keperluan" id="input_keperluan" class="form-control rounded-3" placeholder="Contoh: Pendaftaran TNI, Melamar Pekerjaan">
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2.5 rounded-pill fw-bold">
                            <i class="bi bi-file-earmark-plus-fill me-2"></i> Ajukan Permohonan Surat
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectSurat = document.getElementById('select_jenis_surat');
    const templateVars = <?php echo json_encode($templateVariables ?? []); ?>;

    const groupNik = document.getElementById('group_nik');
    const inputNik = document.getElementById('input_nik');

    const groupAlamat = document.getElementById('group_alamat');
    const inputAlamat = document.getElementById('input_alamat');

    const rowBirth = document.getElementById('row_birth');
    const inputTempatLahir = document.getElementById('input_tempat_lahir');
    const inputTanggalLahir = document.getElementById('input_tanggal_lahir');

    const groupJenisKelamin = document.getElementById('group_jenis_kelamin');
    const inputJenisKelamin = document.getElementById('input_jenis_kelamin');

    const groupAgama = document.getElementById('group_agama');
    const inputAgama = document.getElementById('input_agama');

    const groupPekerjaan = document.getElementById('group_pekerjaan');
    const inputPekerjaan = document.getElementById('input_pekerjaan');

    const rowUsaha = document.getElementById('row_usaha');
    const inputNamaUsaha = document.getElementById('input_nama_usaha');
    const inputLokasiUsaha = document.getElementById('input_lokasi_usaha');

    const groupStatusTinggal = document.getElementById('group_status_tinggal');
    const inputStatusTinggal = document.getElementById('input_status_tinggal');

    const groupKeperluan = document.getElementById('group_keperluan');
    const inputKeperluan = document.getElementById('input_keperluan');

    function toggleFields() {
        const val = selectSurat.value || '';

        // Kebutuhan variabel dinamis dari template_surat
        let required = [];
        if (val) {
            required = templateVars[val] || templateVars[val.toLowerCase()];
            if (!required) {
                const lower = val.toLowerCase();
                let key = '';
                if (lower.includes('usaha') || lower.includes('sku')) key = 'sku';
                else if (lower.includes('tidak mampu') || lower.includes('sktm')) key = 'sktm';
                else if (lower.includes('domisili') || lower.includes('skd')) key = 'skd';
                else if (lower.includes('nikah')) key = 'nikah';
                else if (lower.includes('skck')) key = 'skck';
                else key = lower.replace(/[^a-z0-9]/g, '');

                required = templateVars[key] || [];
            }
        }

        // Toggle NIK
        if (required.includes('nik')) {
            groupNik.classList.remove('d-none');
            if (inputNik) inputNik.required = true;
        } else {
            groupNik.classList.add('d-none');
            if (inputNik) {
                inputNik.required = false;
                inputNik.value = '';
            }
        }

        // Toggle Alamat
        if (required.includes('alamat')) {
            groupAlamat.classList.remove('d-none');
            if (inputAlamat) inputAlamat.required = true;
        } else {
            groupAlamat.classList.add('d-none');
            if (inputAlamat) {
                inputAlamat.required = false;
                inputAlamat.value = '';
            }
        }

        // Toggle Tempat & Tanggal Lahir
        if (required.includes('tempat_lahir')) {
            rowBirth.classList.remove('d-none');
            if (inputTempatLahir) inputTempatLahir.required = true;
            if (inputTanggalLahir) inputTanggalLahir.required = true;
        } else {
            rowBirth.classList.add('d-none');
            if (inputTempatLahir) {
                inputTempatLahir.required = false;
                inputTempatLahir.value = '';
            }
            if (inputTanggalLahir) {
                inputTanggalLahir.required = false;
                inputTanggalLahir.value = '';
            }
        }

        // Toggle Jenis Kelamin
        if (required.includes('jenis_kelamin')) {
            groupJenisKelamin.classList.remove('d-none');
            if (inputJenisKelamin) inputJenisKelamin.required = true;
        } else {
            groupJenisKelamin.classList.add('d-none');
            if (inputJenisKelamin) {
                inputJenisKelamin.required = false;
                inputJenisKelamin.value = '';
            }
        }

        // Toggle Agama
        if (required.includes('agama')) {
            groupAgama.classList.remove('d-none');
            if (inputAgama) inputAgama.required = true;
        } else {
            groupAgama.classList.add('d-none');
            if (inputAgama) {
                inputAgama.required = false;
                inputAgama.value = '';
            }
        }

        // Toggle Pekerjaan
        if (required.includes('pekerjaan')) {
            groupPekerjaan.classList.remove('d-none');
            if (inputPekerjaan) inputPekerjaan.required = true;
        } else {
            groupPekerjaan.classList.add('d-none');
            if (inputPekerjaan) {
                inputPekerjaan.required = false;
                inputPekerjaan.value = '';
            }
        }

        // Toggle Usaha (Nama Usaha & Lokasi Usaha)
        if (required.includes('nama_usaha') || required.includes('lokasi_usaha')) {
            rowUsaha.classList.remove('d-none');
            if (inputNamaUsaha) inputNamaUsaha.required = true;
            if (inputLokasiUsaha) inputLokasiUsaha.required = true;
        } else {
            rowUsaha.classList.add('d-none');
            if (inputNamaUsaha) {
                inputNamaUsaha.required = false;
                inputNamaUsaha.value = '';
            }
            if (inputLokasiUsaha) {
                inputLokasiUsaha.required = false;
                inputLokasiUsaha.value = '';
            }
        }

        // Toggle Status Tinggal
        if (required.includes('status_tinggal')) {
            groupStatusTinggal.classList.remove('d-none');
            if (inputStatusTinggal) inputStatusTinggal.required = true;
        } else {
            groupStatusTinggal.classList.add('d-none');
            if (inputStatusTinggal) {
                inputStatusTinggal.required = false;
                inputStatusTinggal.value = 'Tetap';
            }
        }

        // Toggle Keperluan
        if (required.includes('keperluan')) {
            groupKeperluan.classList.remove('d-none');
            if (inputKeperluan) inputKeperluan.required = true;
        } else {
            groupKeperluan.classList.add('d-none');
            if (inputKeperluan) {
                inputKeperluan.required = false;
                inputKeperluan.value = '';
            }
        }
    }

    selectSurat.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
