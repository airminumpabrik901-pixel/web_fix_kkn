<?php
require_once 'config/database.php';

$query = <<<'SQL'
CREATE TABLE IF NOT EXISTS admin (
    id_admin INTEGER PRIMARY KEY AUTOINCREMENT, 
    username TEXT, 
    password TEXT, 
    nama_lengkap TEXT
);

CREATE TABLE IF NOT EXISTS profil_desa (
    id_profil INTEGER PRIMARY KEY DEFAULT 1, 
    nama_desa TEXT, 
    kepala_desa TEXT, 
    foto_kades TEXT, 
    visi TEXT,
    misi TEXT,
    masa_jabatan TEXT
);

CREATE TABLE IF NOT EXISTS kategori_berita (
    id_kategori INTEGER PRIMARY KEY AUTOINCREMENT, 
    nama_kategori TEXT
);

CREATE TABLE IF NOT EXISTS berita (
    id_berita INTEGER PRIMARY KEY AUTOINCREMENT, 
    id_kategori INTEGER, 
    judul TEXT, 
    slug TEXT, 
    isi TEXT, 
    gambar_cover TEXT, 
    tanggal_publikasi DATETIME DEFAULT CURRENT_TIMESTAMP, 
    id_admin INTEGER
);

CREATE TABLE IF NOT EXISTS pengajuan_surat (
    id_pengajuan INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_pemohon TEXT,
    nik TEXT,
    no_hp TEXT,
    jenis_surat TEXT,
    keperluan TEXT,
    status_pengajuan TEXT DEFAULT 'Menunggu',
    tanggal_pengajuan DATETIME DEFAULT CURRENT_TIMESTAMP,
    nomor_registrasi TEXT,
    tempat_lahir TEXT,
    tanggal_lahir TEXT,
    jenis_kelamin TEXT,
    agama TEXT,
    pekerjaan TEXT,
    alamat TEXT,
    kewarganegaraan TEXT DEFAULT 'WNI',
    nama_usaha TEXT,
    lokasi_usaha TEXT,
    status_tinggal TEXT
);

CREATE TABLE IF NOT EXISTS umkm (
    id_produk INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT,
    kategori TEXT,
    harga INTEGER,
    penjual TEXT,
    gambar TEXT,
    no_wa TEXT
);

CREATE TABLE IF NOT EXISTS galeri (
    id_galeri INTEGER PRIMARY KEY AUTOINCREMENT,
    judul TEXT,
    gambar TEXT,
    tanggal TEXT
);

CREATE TABLE IF NOT EXISTS aspirasi (
    id_aspirasi INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_lengkap TEXT,
    kontak TEXT,
    isi_pesan TEXT,
    tanggal_kirim DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS visitor_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT,
    user_agent TEXT,
    page TEXT,
    referer TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS jenis_surat (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT UNIQUE
);

CREATE TABLE IF NOT EXISTS template_surat (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipe_surat TEXT UNIQUE NOT NULL,
    template_html TEXT NOT NULL,
    deskripsi TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS struktur_pemerintahan (
    id_struktur INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT,
    jabatan TEXT,
    foto TEXT,
    urutan INTEGER DEFAULT 0
);

INSERT OR IGNORE INTO admin (id_admin, username, password, nama_lengkap) 
VALUES (1, 'admin', 'rahasia123', 'Admin KKN UTM 42');

INSERT OR IGNORE INTO profil_desa (id_profil, nama_desa, kepala_desa, visi, misi, masa_jabatan) 
VALUES (1, 'Desa Bluto', 'Bapak Kepala Desa', 'Mewujudkan Desa Bluto yang Maju dan Sejahtera.', 'Meningkatkan kualitas tata kelola pemerintahan desa yang bersih, transparan, dan berorientasi pada pelayanan masyarakat secara digital.
Mengoptimalkan potensi pertanian, kelautan, dan industri kreatif melalui pemberdayaan produk lokal (UMKM Dapur Lokal).
Membangun sarana dan prasarana infrastruktur desa yang merata guna mempercepat akses perekonomian warga.
Meningkatkan kerja sama pemuda, mahasiswa (KKN), tokoh masyarakat, dan pemerintah desa untuk mewujudkan inovasi desa.', '2021 - 2027');

INSERT OR IGNORE INTO kategori_berita (id_kategori, nama_kategori) 
VALUES (1, 'Pengumuman'), (2, 'Kegiatan KKN');

INSERT OR IGNORE INTO berita (id_kategori, judul, slug, isi, id_admin) 
VALUES (2, 'Kerja Bakti KKN UTM', 'kerja-bakti-kkn-utm', 'Kegiatan pembersihan balai desa.', 1);

INSERT OR IGNORE INTO umkm (id_produk, nama, kategori, harga, penjual, gambar, no_wa) VALUES
(1, 'Keripik Singkong Balado', 'Makanan Ringan', 15000, 'Ibu Siti (Dusun Utara)', 'https://images.unsplash.com/photo-1621939514649-280e2ee25f60?auto=format&fit=crop&q=60&w=500', '6281234567890'),
(2, 'Kopi Bubuk Asli Bluto', 'Minuman', 25000, 'Pak Budi M.', 'https://images.unsplash.com/photo-1559525839-b184a4d698c7?auto=format&fit=crop&q=60&w=500', '6281234567890'),
(3, 'Kerajinan Anyaman Bambu', 'Kriya & Kerajinan', 45000, 'Kelompok Tani Mekar', 'https://images.unsplash.com/photo-1606760227091-3dd870d97f1d?auto=format&fit=crop&q=60&w=500', '6281234567890'),
(4, 'Sambal Teri Pedas Nampol', 'Bahan Pokok', 20000, 'Dapur Bu Rina', 'https://images.unsplash.com/photo-1596649299486-4cdea56fd59d?auto=format&fit=crop&q=60&w=500', '6281234567890');

INSERT OR IGNORE INTO galeri (id_galeri, judul, gambar, tanggal) VALUES
(1, 'Panen Raya Kelompok Tani "Maju Jaya"', 'https://images.unsplash.com/photo-1574323347407-f5e1ad6d020b?auto=format&fit=crop&q=80&w=600&h=400', '12 Juni 2026'),
(2, 'Kerja Bakti Rutin Warga Dusun 1', 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&q=80&w=600&h=400', '20 Mei 2026'),
(3, 'Musyawarah Perencanaan Pembangunan (Musrenbangdes)', 'https://images.unsplash.com/photo-1592861214088-7a5ceb1551a8?auto=format&fit=crop&q=80&w=600&h=400', '15 April 2026'),
(4, 'Kegiatan Posyandu Balita & Lansia Dusun Barat', 'https://images.unsplash.com/photo-1605000797499-95a51c5269ae?auto=format&fit=crop&q=80&w=600&h=400', '5 April 2026'),
(5, 'Pentas Seni Tari Tradisional KKN UTM', 'https://images.unsplash.com/photo-1589923188900-85dae523342b?auto=format&fit=crop&q=80&w=600&h=400', '17 Agustus 2025'),
(6, 'Pemandangan Sawah Terasing di Pagi Hari', 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&q=80&w=600&h=400', 'Dokumentasi KKN');

INSERT OR IGNORE INTO jenis_surat (id, nama) VALUES
(1, 'Surat Keterangan Usaha (SKU)'),
(2, 'Surat Keterangan Tidak Mampu (SKTM)'),
(3, 'Surat Keterangan Domisili (SKD)');

INSERT OR IGNORE INTO struktur_pemerintahan (id_struktur, nama, jabatan, foto, urutan) VALUES
(1, 'Bapak H. Akhmad', 'Kepala Desa Bluto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100&h=100', 1),
(2, 'Ahmad Dani', 'Ketua KKN UTM Kelompok 42', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=100&h=100', 2),
(3, 'Ibu Rina Astuti', 'Sekretaris Desa', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100&h=100', 3);
SQL;

try {
    $koneksi->exec($query);
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h1 style='color: green;'>SUKSES!</h1>";
    echo "<p>Database SQLite beserta tabelnya berhasil dibuat.</p>";
    echo "<a href='index.php' style='padding: 10px 20px; background-color: #198754; color: white; text-decoration: none; border-radius: 5px;'>Kembali ke Beranda</a>";
    echo "</div>";
} catch (PDOException $e) {
    echo "Gagal: " . $e->getMessage();
}
?>
