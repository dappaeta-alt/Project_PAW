<?php
require __DIR__ . '/koneksi.php';

// Hapus riwayat (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_semua'])) {
    if (!csrf_check()) {
        set_flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    } else {
        try {
            $conn->begin_transaction();
            $conn->query("DELETE FROM detail_transaksi");
            $conn->query("DELETE FROM transaksi");
            $conn->commit();
            set_flash('success', 'Seluruh riwayat transaksi berhasil dihapus.');
        } catch (Throwable $ex) {
            $conn->rollback();
            set_flash('error', 'Gagal menghapus riwayat transaksi.');
        }
    }

    header('Location: riwayat.php');
    exit();
}

$transaksi_q = $conn->query("SELECT * FROM transaksi ORDER BY id DESC");

$items_by_trx = [];
$res_items = $conn->query("SELECT transaksi_id, produk_nama, catatan, qty, harga_satuan FROM detail_transaksi ORDER BY id ASC");
while ($item = $res_items->fetch_assoc()) {
    $items_by_trx[(int) $item['transaksi_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - PRINT'TA POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            aside, button, .no-print { display: none !important; }
            body { background: white; }
            main { padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-[#1E293B] h-screen flex overflow-hidden">
    <aside class="w-64 bg-white shadow-md flex flex-col no-print">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-xl font-bold text-blue-600"><i class="fa-solid fa-print mr-2"></i>PRINT'TA POS</h1>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-house w-6"></i> Dashboard</a>
            <a href="tambah_produk.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-plus w-6"></i> Tambah Produk</a>
            <a href="transaksi.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-cart-shopping w-6"></i> Transaksi Baru</a>
            <a href="antrian.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-clock w-6"></i> Antrian Pesanan</a>
            <a href="riwayat.php" class="block p-3 rounded-lg bg-blue-50 text-blue-600 font-medium"><i class="fa-solid fa-chart-simple w-6"></i> Riwayat Transaksi</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto p-8">
        <div class="flex justify-between items-center mb-6 no-print">
            <h2 class="text-2xl font-bold">Riwayat Transaksi</h2>
            <div class="space-x-2">
                <button onclick="window.print()" class="bg-gray-200 px-4 py-2 rounded text-sm font-semibold hover:bg-gray-300"><i class="fa-solid fa-print"></i> Cetak Struk</button>
                <form method="POST" action="riwayat.php" class="inline" onsubmit="return confirm('Yakin ingin menghapus seluruh riwayat?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="hapus_semua" value="1">
                    <button type="submit" class="bg-red-100 text-red-600 px-4 py-2 rounded text-sm font-semibold hover:bg-red-200">Hapus Semua Riwayat</button>
                </form>
            </div>
        </div>

        <?= flash_html() ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-sm">
                    <tr>
                        <th class="p-4">ID Transaksi</th>
                        <th class="p-4">Nama Pembeli</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Detail Item</th>
                        <th class="p-4">Layanan / Ongkir</th>
                        <th class="p-4">Total</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($transaksi_q->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-sm text-gray-400 italic">Belum ada transaksi.</td>
                        </tr>
                    <?php endif; ?>
                    <?php while ($trx = $transaksi_q->fetch_assoc()): ?>
                        <?php
                            $t_id = (int) $trx['id'];
                            $status_class = [
                                'antri' => 'bg-amber-100 text-amber-700',
                                'proses' => 'bg-blue-100 text-blue-700',
                                'selesai' => 'bg-green-100 text-green-700',
                                'diambil' => 'bg-gray-200 text-gray-600',
                            ][$trx['status_pesanan']] ?? 'bg-gray-100 text-gray-600';
                            $bayar_class = [
                                'lunas' => 'bg-green-100 text-green-700',
                                'dp' => 'bg-amber-100 text-amber-700',
                                'belum' => 'bg-red-100 text-red-700',
                            ][$trx['status_bayar']] ?? 'bg-gray-100 text-gray-600';
                            $bayar_label = $trx['status_bayar'] === 'dp' ? 'DP' : ucfirst($trx['status_bayar']);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-4">
                                <div class="font-semibold text-blue-600"><?= e($trx['trx_code']) ?></div>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-semibold <?= e($status_class) ?>"><?= e(ucfirst($trx['status_pesanan'])) ?></span>
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-gray-800"><?= e($trx['nama_pembeli']) ?></div>
                                <?php if ($trx['no_hp']): ?>
                                    <div class="text-xs text-gray-500"><?= e($trx['no_hp']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-sm text-gray-600"><?= e($trx['tanggal']) ?></td>
                            <td class="p-4 text-sm">
                                <ul class="list-disc list-inside">
                                    <?php foreach ($items_by_trx[$t_id] ?? [] as $item): ?>
                                        <li>
                                            <?= e($item['produk_nama']) ?> (<?= (int) $item['qty'] ?>x @<?= number_format((int) $item['harga_satuan'], 0, ',', '.') ?>)
                                            <?php if (!empty($item['catatan'])): ?>
                                                <div class="text-xs text-amber-600">- <?= e($item['catatan']) ?></div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="p-4 text-sm">
                                <span class="capitalize font-semibold"><?= e($trx['layanan']) ?></span><br>
                                <span class="text-gray-500">Ongkir: <?= rupiah($trx['ongkir']) ?></span>
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-sm"><?= rupiah($trx['total']) ?></div>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-semibold <?= e($bayar_class) ?>"><?= e($bayar_label) ?></span>
                            </td>
                            <td class="p-4 text-center">
                                <a href="nota.php?id=<?= $t_id ?>" class="bg-blue-50 text-blue-600 px-3 py-1 rounded text-xs font-semibold hover:bg-blue-100">Lihat Nota</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
                                                