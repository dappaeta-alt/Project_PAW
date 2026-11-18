<?php
require_once __DIR__ . '/functions.php';

$config = require __DIR__ . '/config.php';

date_default_timezone_set('Asia/Jakarta');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['db']);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('Koneksi database gagal. Periksa konfigurasi pada config.php.');
}
