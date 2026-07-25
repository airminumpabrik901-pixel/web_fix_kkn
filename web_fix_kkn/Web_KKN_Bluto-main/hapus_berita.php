<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['csrf_token'])) {
    $id_berita = (int)$_GET['id'];
    $csrf_token = $_GET['csrf_token'];
    
    // Validate CSRF token
    if ($csrf_token === $_SESSION['csrf_token']) {
        // Delete file if it exists in assets/img/
        try {
            $stmtFile = $koneksi->prepare("SELECT gambar_cover FROM berita WHERE id_berita = :id LIMIT 1");
            $stmtFile->execute([':id' => $id_berita]);
            $berita_file = $stmtFile->fetchColumn();
            if (!empty($berita_file)) {
                $file_path = './assets/img/' . $berita_file;
                if (file_exists($file_path) && is_file($file_path)) {
                    @unlink($file_path);
                }
            }
        } catch (Exception $ex) {
            // Ignore error
        }

        $stmt = $koneksi->prepare("DELETE FROM berita WHERE id_berita = :id");
        $stmt->bindParam(':id', $id_berita);
        $stmt->execute();
    }
}

header("Location: admin.php");
exit;
?>
