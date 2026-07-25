<?php
if (!function_exists('fieldRow')) {
    function fieldRow($label, $value) {
        return "<tr>
      <td style=\"width:200px;padding:1px 0;vertical-align:top;font-family:'Times New Roman',Times,serif;font-size:12pt;\">{$label}</td>
      <td style=\"width:14px;padding:1px 4px;vertical-align:top;font-family:'Times New Roman',Times,serif;font-size:12pt;\">:</td>
      <td style=\"padding:1px 0;vertical-align:top;font-family:'Times New Roman',Times,serif;font-size:12pt;\">{$value}</td>
    </tr>";
    }
}

function generateSuratDraft($type, $data) {
    $indonesian_months = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $month_name = $indonesian_months[$month] ?? 'Juli';
    $tanggal_sekarang = "$day $month_name $year";

    $nama = htmlspecialchars($data['nama'] ?? '');
    $nik = htmlspecialchars($data['nik'] ?? '');
    $alamat = htmlspecialchars($data['alamat'] ?? '');
    $tempat_lahir = htmlspecialchars($data['tempat_lahir'] ?? '');
    $tanggal_lahir = htmlspecialchars($data['tanggal_lahir'] ?? '');
    $tempat_tgl_lahir = htmlspecialchars($data['tempat_tgl_lahir'] ?? '');
    $jenis_kelamin = htmlspecialchars($data['jenis_kelamin'] ?? '');
    $agama = htmlspecialchars($data['agama'] ?? '');
    $pekerjaan = htmlspecialchars($data['pekerjaan'] ?? '');
    $kewarganegaraan = htmlspecialchars($data['kewarganegaraan'] ?? 'WNI');
    $keperluan = htmlspecialchars($data['keperluan'] ?? '');
    $nama_usaha = htmlspecialchars($data['nama_usaha'] ?? '-');
    $lokasi_usaha = htmlspecialchars($data['lokasi_usaha'] ?? $data['alamat'] ?? '-');
    $keterangan = htmlspecialchars($data['keterangan'] ?? 'Tidak mampu secara ekonomi');
    $status_tinggal = htmlspecialchars($data['status_tinggal'] ?? 'Tetap');
    $nomor = htmlspecialchars($data['nomor_registrasi'] ?? '');
    $nomorLine = "<p style=\"margin:0 0 12px 0;text-align:center;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Nomor : " . ($nomor !== '' ? $nomor : '................................................') . "</p>";

    // Default wilayah
    $nama_kabupaten = 'SUMENEP';
    $nama_kecamatan = 'Bluto';
    $nama_desa = 'Desa Bluto';
    $kepala_desa = 'Ibu Heni Susilowati';
    $logo_path = '';

    // Ambil dari database profil_desa jika tersedia
    $custom_template = null;
    $dbFile = __DIR__ . '/database.php';
    if (file_exists($dbFile)) {
        try {
            global $koneksi;
            require_once $dbFile;
            if (isset($koneksi)) {
                try {
                    $stmt = $koneksi->query("SELECT * FROM profil_desa WHERE id_profil = 1 LIMIT 1");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        if (!empty($row['nama_desa'])) $nama_desa = $row['nama_desa'];
                        if (!empty($row['kepala_desa'])) $kepala_desa = $row['kepala_desa'];
                        if (!empty($row['foto'])) $logo_path = $row['foto'];
                        $settingsPath = __DIR__ . '/web_settings.json';
                        if (file_exists($settingsPath)) {
                            $ws = json_decode(file_get_contents($settingsPath), true) ?: [];
                            $alamat_kontak = $ws['kontak']['alamat'] ?? '';
                            if (stripos($alamat_kontak, 'Sumenep') !== false) $nama_kabupaten = 'SUMENEP';
                            if (preg_match('/Kec\.\s*([^,]+)/i', $alamat_kontak, $m)) $nama_kecamatan = trim($m[1]);
                        }
                    }

                    // Coba cari custom template
                    try {
                        $stmt = $koneksi->prepare("SELECT template_html FROM template_surat WHERE tipe_surat = ?");
                        $stmt->execute([$type]);
                        $template_row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($template_row) $custom_template = $template_row['template_html'];
                    } catch (Exception $e) {}

                } catch (Exception $e) {}
            }
        } catch (Exception $e) {}
    }

    // Jika ada custom template, gunakan dan replace variabelnya
    if ($custom_template) {
        $html = $custom_template;
        $html = preg_replace_callback('/\{([^{}]+)\}/', function($m) {
            return '{' . trim(strip_tags($m[1])) . '}';
        }, $html);
        $html = str_replace(['{nama}','{nik}','{alamat}','{tanggal}','{nama_usaha}','{lokasi_usaha}',
                             '{keterangan}','{keperluan}','{status_tinggal}','{tempat_lahir}',
                             '{tanggal_lahir}','{tempat_tgl_lahir}','{jenis_kelamin}','{agama}',
                             '{pekerjaan}','{kewarganegaraan}','{kepala_desa}','{nama_desa}',
                             '{nama_kecamatan}','{nama_kabupaten}'],
                            [$nama,$nik,$alamat,$tanggal_sekarang,$nama_usaha,$lokasi_usaha,
                             $keterangan,$keperluan,$status_tinggal,$tempat_lahir,
                             $tanggal_lahir,$tempat_tgl_lahir,$jenis_kelamin,$agama,
                             $pekerjaan,$kewarganegaraan,$kepala_desa,$nama_desa,
                             $nama_kecamatan,$nama_kabupaten],
                            $html);
        return $html;
    }

    // ─── Kop Surat Standar (identik format Word) ────────────────────────────────
    // Logo diambil dari profil desa atau aset logo desa/kabupaten
    $logo_img = '';
    if (!empty($logo_path) && file_exists(__DIR__ . '/../' . $logo_path)) {
        $logo_img = "<img src=\"{$logo_path}\" style=\"width:82px;height:102px;display:block;margin:0 auto;object-fit:contain;\" alt=\"Logo\">";
    } elseif (file_exists(__DIR__ . '/../assets/img/logo_sumenep.png')) {
        $logo_img = "<img src=\"assets/img/logo_sumenep.png\" style=\"width:82px;height:102px;display:block;margin:0 auto;object-fit:contain;\" alt=\"Logo\">";
    } elseif (file_exists(__DIR__ . '/../assets/img/logo.png')) {
        $logo_img = "<img src=\"assets/img/logo.png\" style=\"width:82px;height:102px;display:block;margin:0 auto;object-fit:contain;\" alt=\"Logo\">";
    } elseif (file_exists(__DIR__ . '/../assets/img/logo_desa.png')) {
        $logo_img = "<img src=\"assets/img/logo_desa.png\" style=\"width:82px;height:102px;display:block;margin:0 auto;object-fit:contain;\" alt=\"Logo\">";
    } else {
        $logo_img = "<div style=\"width:82px;height:102px;border:1px dashed #999;display:flex;align-items:center;justify-content:center;font-size:9pt;color:#999;text-align:center;\">Logo<br>Desa</div>";
    }

    $nama_kabupaten_upper = strtoupper($nama_kabupaten);
    $nama_kecamatan_upper = strtoupper($nama_kecamatan);
    $nama_desa_upper = strtoupper($nama_desa);

    $kop = "
<table style=\"width:100%;border-collapse:collapse;border-spacing:0;margin-bottom:6px;\">
<tr>
  <td style=\"width:95px;padding:4px 6px;vertical-align:middle;border-top:none;border-left:none;border-right:none;border-bottom:1.5pt solid #000000;\">
    {$logo_img}
  </td>
  <td style=\"vertical-align:middle;padding:4px 10px;border-top:none;border-left:none;border-right:none;border-bottom:1.5pt solid #000000;line-height:1.3;\">
    <p style=\"margin:0;padding:0;text-align:center;font-size:13pt;font-weight:bold;font-family:'Bookman Old Style','Georgia',serif;text-transform:uppercase;\">PEMERINTAH KABUPATEN {$nama_kabupaten_upper}</p>
    <p style=\"margin:0;padding:0;text-align:center;font-size:13pt;font-weight:bold;font-family:'Bookman Old Style','Georgia',serif;text-transform:uppercase;\">KECAMATAN {$nama_kecamatan_upper}</p>
    <p style=\"margin:0;padding:0;text-align:center;font-size:17pt;font-weight:bold;font-family:'Bookman Old Style','Georgia',serif;text-transform:uppercase;\">KEPALA {$nama_desa_upper}</p>
    <p style=\"margin:0;padding:0;text-align:center;font-size:11pt;font-family:'Bookman Old Style','Georgia',serif;\">Jalan Safari No. 18 - {$nama_kecamatan} Sumenep &#9742; (0328) ______________</p>
    <p style=\"margin:0;padding:0;text-align:center;font-size:14pt;font-weight:bold;font-family:'Bookman Old Style','Georgia',serif;letter-spacing:3px;text-transform:uppercase;\">{$nama_kecamatan_upper}</p>
  </td>
</tr>
</table>";

    // ─── Tanda Tangan Standar ────────────────────────────────────────────────────
    $ttd = "
<table style=\"width:100%;border-collapse:collapse;margin-top:40px;\">
<tr>
  <td style=\"width:60%;\">&nbsp;</td>
  <td style=\"text-align:center;font-family:'Times New Roman',Times,serif;font-size:12pt;\">
    <p style=\"margin:0;\">Sumenep, {$tanggal_sekarang}</p>
    <p style=\"margin:0;\">{$kepala_desa} {$nama_desa}</p>
    <br><br><br><br>
    <p style=\"margin:0;\">({$kepala_desa})</p>
  </td>
</tr>
</table>";

    // ════════════════════════════════════════════════════════════════════════════
    // Template per Jenis Surat
    // ════════════════════════════════════════════════════════════════════════════

    // ── SKTM ────────────────────────────────────────────────────────────────────
    if ($type === 'sktm' || stripos($type, 'tidak mampu') !== false) {
        return "
{$kop}
<p style=\"margin:10px 0 4px 0;text-align:center;font-size:12pt;font-weight:bold;font-family:'Times New Roman',Times,serif;text-decoration:underline;\">SURAT KETERANGAN TIDAK MAMPU (SKTM)</p>
{$nomorLine}

<p style=\"margin:0 0 8px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Yang bertanda tangan di bawah ini, {$kepala_desa} {$nama_desa} Kecamatan {$nama_kecamatan} Kabupaten Sumenep menerangkan dengan sebenarnya bahwa :</p>

<table style=\"width:100%;border-collapse:collapse;margin:6px 0;\">
  " . fieldRow('Nama', $nama) . "
  " . fieldRow('Nomor KTP', $nik) . "
  " . fieldRow('Tempat dan Tanggal Lahir', $tempat_tgl_lahir) . "
  " . fieldRow('Jenis Kelamin', $jenis_kelamin) . "
  " . fieldRow('Agama', $agama) . "
  " . fieldRow('Pekerjaan', $pekerjaan) . "
  " . fieldRow('Alamat', $alamat) . "
  " . fieldRow('Keterangan', $keterangan) . "
</table>

<p style=\"margin:10px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Surat keterangan ini dibuat untuk keperluan pengajuan bantuan dan/atau program sosial sesuai ketentuan yang berlaku.</p>
<p style=\"margin:6px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

{$ttd}";
    }

    // ── SKD (Domisili) ───────────────────────────────────────────────────────────
    if ($type === 'skd' || stripos($type, 'domisili') !== false) {
        return "
{$kop}
<p style=\"margin:10px 0 4px 0;text-align:center;font-size:12pt;font-weight:bold;font-family:'Times New Roman',Times,serif;text-decoration:underline;\">SURAT KETERANGAN DOMISILI</p>
{$nomorLine}

<p style=\"margin:0 0 8px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Yang bertanda tangan di bawah ini, {$kepala_desa} {$nama_desa} Kecamatan {$nama_kecamatan} Kabupaten Sumenep menerangkan dengan sebenarnya bahwa :</p>

<table style=\"width:100%;border-collapse:collapse;margin:6px 0;\">
  " . fieldRow('Nama', $nama) . "
  " . fieldRow('Nomor KTP', $nik) . "
  " . fieldRow('Tempat dan Tanggal Lahir', $tempat_tgl_lahir) . "
  " . fieldRow('Jenis Kelamin', $jenis_kelamin) . "
  " . fieldRow('Agama', $agama) . "
  " . fieldRow('Pekerjaan', $pekerjaan) . "
  " . fieldRow('Alamat', $alamat) . "
  " . fieldRow('Status Tinggal', $status_tinggal) . "
</table>

<p style=\"margin:10px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Surat keterangan domisili ini dibuat untuk melengkapi persyaratan administrasi yang diperlukan oleh yang bersangkutan.</p>
<p style=\"margin:6px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

{$ttd}";
    }

    // ── SKU (Usaha) ──────────────────────────────────────────────────────────────
    if ($type === 'sku' || stripos($type, 'usaha') !== false) {
        return "
{$kop}
<p style=\"margin:10px 0 4px 0;text-align:center;font-size:12pt;font-weight:bold;font-family:'Times New Roman',Times,serif;text-decoration:underline;\">SURAT KETERANGAN USAHA (SKU)</p>
{$nomorLine}

<p style=\"margin:0 0 8px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Yang bertanda tangan di bawah ini, {$kepala_desa} {$nama_desa} Kecamatan {$nama_kecamatan} Kabupaten Sumenep menerangkan dengan sebenarnya bahwa :</p>

<table style=\"width:100%;border-collapse:collapse;margin:6px 0;\">
  " . fieldRow('Nama', $nama) . "
  " . fieldRow('Nomor KTP', $nik) . "
  " . fieldRow('Tempat dan Tanggal Lahir', $tempat_tgl_lahir) . "
  " . fieldRow('Jenis Kelamin', $jenis_kelamin) . "
  " . fieldRow('Agama', $agama) . "
  " . fieldRow('Pekerjaan', $pekerjaan) . "
  " . fieldRow('Alamat', $alamat) . "
  " . fieldRow('Nama Usaha', $nama_usaha) . "
  " . fieldRow('Lokasi Usaha', $lokasi_usaha) . "
</table>

<p style=\"margin:10px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Surat keterangan ini diberikan untuk keperluan administrasi usaha dan tidak dapat dipergunakan sebagai dokumen legal lain.</p>
<p style=\"margin:6px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

{$ttd}";
    }

    // ── Nikah ────────────────────────────────────────────────────────────────────
    if ($type === 'nikah' || stripos($type, 'nikah') !== false || stripos($type, 'perkawinan') !== false) {
        return "
{$kop}
<p style=\"margin:10px 0 4px 0;text-align:center;font-size:12pt;font-weight:bold;font-family:'Times New Roman',Times,serif;text-decoration:underline;\">SURAT KETERANGAN NIKAH</p>
{$nomorLine}

<p style=\"margin:0 0 8px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Yang bertanda tangan di bawah ini, {$kepala_desa} {$nama_desa} Kecamatan {$nama_kecamatan} Kabupaten Sumenep menerangkan dengan sebenarnya bahwa :</p>

<table style=\"width:100%;border-collapse:collapse;margin:6px 0;\">
  " . fieldRow('Nama', $nama) . "
  " . fieldRow('Nomor KTP', $nik) . "
  " . fieldRow('Tempat dan Tanggal Lahir', $tempat_tgl_lahir) . "
  " . fieldRow('Jenis Kelamin', $jenis_kelamin) . "
  " . fieldRow('Agama', $agama) . "
  " . fieldRow('Pekerjaan', $pekerjaan) . "
  " . fieldRow('Alamat', $alamat) . "
</table>

<p style=\"margin:10px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Surat keterangan ini dibuat untuk keperluan pernikahan dan berdasarkan keterangan yang diberikan oleh yang bersangkutan.</p>
<p style=\"margin:6px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

{$ttd}";
    }

    // ── SKCK (Pengantar) ─────────────────────────────────────────────────────────
    if ($type === 'skck' || stripos($type, 'skck') !== false || stripos($type, 'pengantar') !== false) {
        return "
{$kop}
<p style=\"margin:10px 0 4px 0;text-align:center;font-size:12pt;font-weight:bold;font-family:'Times New Roman',Times,serif;text-decoration:underline;\">SURAT KETERANGAN PENGANTAR SKCK</p>
{$nomorLine}

<p style=\"margin:0 0 8px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Yang bertanda tangan di bawah ini, {$kepala_desa} {$nama_desa} Kecamatan {$nama_kecamatan} Kabupaten Sumenep menerangkan dengan sebenarnya bahwa :</p>

<table style=\"width:100%;border-collapse:collapse;margin:6px 0;\">
  " . fieldRow('Nama', $nama) . "
  " . fieldRow('Nomor KTP', $nik) . "
  " . fieldRow('Jenis Kelamin', $jenis_kelamin) . "
  " . fieldRow('Tempat dan Tanggal Lahir', $tempat_tgl_lahir) . "
  " . fieldRow('Agama', $agama) . "
  " . fieldRow('Pekerjaan', $pekerjaan) . "
  " . fieldRow('Alamat', $alamat) . "
</table>

<p style=\"margin:10px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Setelah diadakan penelitian dan pemeriksaan hingga saat dikeluarkannya surat ini ternyata yang bersangkutan berkelakuan baik dan tidak sedang tersangkut perkara pidana dan atau gerakan terlarang.</p>
<p style=\"margin:6px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Surat keterangan ini diberikan kepada yang bersangkutan untuk keperluan <strong><u>{$keperluan}</u></strong></p>
<p style=\"margin:6px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Demikian surat keterangan ini dibuat untuk mendapatkan Rekomendasi catatan Kriminal dari Polsek {$nama_kecamatan} dan diberikan kepada yang bersangkutan untuk dipergunakan sebagaimana mestinya.</p>

{$ttd}";
    }

    // ── Keperluan Umum / Default ─────────────────────────────────────────────────
    return "
{$kop}
<p style=\"margin:10px 0 4px 0;text-align:center;font-size:12pt;font-weight:bold;font-family:'Times New Roman',Times,serif;text-decoration:underline;\">SURAT KETERANGAN</p>
{$nomorLine}

<p style=\"margin:0 0 8px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Yang bertanda tangan di bawah ini, {$kepala_desa} {$nama_desa} Kecamatan {$nama_kecamatan} Kabupaten Sumenep menerangkan dengan sebenarnya bahwa :</p>

<table style=\"width:100%;border-collapse:collapse;margin:6px 0;\">
  " . fieldRow('Nama', $nama) . "
  " . fieldRow('Nomor KTP', $nik) . "
  " . fieldRow('Tempat dan Tanggal Lahir', $tempat_tgl_lahir) . "
  " . fieldRow('Jenis Kelamin', $jenis_kelamin) . "
  " . fieldRow('Agama', $agama) . "
  " . fieldRow('Pekerjaan', $pekerjaan) . "
  " . fieldRow('Alamat', $alamat) . "
</table>

<p style=\"margin:10px 0;text-indent:36pt;text-align:justify;font-family:'Times New Roman',Times,serif;font-size:12pt;\">Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

{$ttd}";
}
?>
