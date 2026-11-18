<?php
require __DIR__ . '/koneksi.php';

$produk = [];
$res_p = $conn->query("SELECT * FROM produk ORDER BY id ASC");
while ($row = $res_p->fetch_assoc()) {
    $produk[] = $row;
}

$items_by_trx = [];
$res_d = $conn->query("SELECT * FROM detail_transaksi ORDER BY id ASC");
while ($row = $res_d->fetch_assoc()) {
    $items_by_trx[(int) $row['transaksi_id']][] = $row;
}

$transaksi = [];
$res_t = $conn->query("SELECT * FROM transaksi ORDER BY id ASC");
while ($row = $res_t->fetch_assoc()) {
    $row['items'] = $items_by_trx[(int) $row['id']] ?? [];
    $transaksi[] = $row;
}

$data = [
    'produk' => $produk,
    'transaksi' => $transaksi,
];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="backup_printta_pos_' . date('Y-m-d') . '.json"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit();
