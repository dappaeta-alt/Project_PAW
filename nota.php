<?php
require __DIR__ . '/koneksi.php';

$id = (int) ($_GET['id'] ?? 0);
$thermal = ($_GET['format'] ?? '') === 'thermal';

$stmt = $conn->prepare("SELECT * FROM transaksi WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    set_flash('error', 'Transaksi tidak ditemukan.');
    header('Location: riwayat.php');
    exit();
}

$stmt_items = $conn->prepare("SELECT * FROM detail_transaksi WHERE transaksi_id = ? ORDER BY id ASC");
$stmt_items->bind_param('i', $id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

$status_pesanan_class = [
    'antri' => 'bg-amber-100 text-amber-700',
    'proses' => 'bg-blue-100 text-blue-700',
    'selesai' => 'bg-green-100 text-green-700',
    'diambil' => 'bg-gray-200 text-gray-600',
][$trx['status_pesanan']] ?? 'bg-gray-100 text-gray-600';

$status_bayar_class = [
    'lunas' => 'bg-green-100 text-green-700',
    'dp' => 'bg-amber-100 text-amber-700',
    'belum' => 'bg-red-100 text-red-700',
][$trx['status_bayar']] ?? 'bg-gray-100 text-gray-600';
$status_bayar_label = $trx['status_bayar'] === 'dp' ? 'DP' : ucfirst($trx['status_bayar']);

$tanggal = date('d/m/Y H:i', strtotime($trx['tanggal']));
$estimasi = $trx['estimasi_selesai'] ? date('d/m/Y H:i', strtotime($trx['estimasi_selesai'])) : '';

$wa_link = '';
$wa_digits = wa_number($trx['no_hp'] ?? '');
if ($wa_digits !== '') {
    $lines = [];
    $lines[] = "*PRINT'TA POS*";
    $lines[] = 'No: ' . $trx['trx_code'];
    $lines[] = 'Nama: ' . $trx['nama_pembeli'];
    $lines[] = 'Status: ' . ucfirst($trx['status_pesanan']);
    if ($estimasi !== '') {
        $lines[] = 'Estimasi: ' . $estimasi;
    }
    $lines[] = '----------------';
    foreach ($items as $item) {
        $line = $item['produk_nama'] . ' ' . (int) $item['qty'] . 'x = ' . rupiah($item['subtotal_item']);
        if (!empty($item['catatan'])) {
            $line .= ' (' . $item['catatan'] . ')';
        }
        $lines[] = $line;
    }
    $lines[] = '----------------';
    $lines[] = 'Total: ' . rupiah($trx['total']);
    $lines[] = 'Bayar: ' . ucfirst($trx['metode_bayar']) . ' (' . $status_bayar_label . ')';
    $lines[] = 'Terima kasih.';
    $wa_link = 'https://wa.me/' . $wa_digits . '?text=' . rawurlencode(implode("\n", $lines));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi - <?= e($trx['trx_code']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .nota-card { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; border-radius: 0 !important; }
        }
        <?php if ($thermal): ?>
        @page { size: 80mm auto; margin: 3mm; }
        body { width: 80mm; margin: 0 auto; background: #fff; font-size: 12px; }
        .nota-card { width: 100% !important; max-width: 80mm !important; padding: 2mm !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; }
        .thermal-sm { font-size: 11px; }
        <?php endif; ?>
    </style>
</head>
<body class="bg-gray-100 flex flex-col items-center min-h-screen py-10">

    <!-- Tombol Navigasi / Aksi -->
    <div class="mb-6 space-x-2 no-print">
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold shadow hover:bg-blue-700">
            <i class="fa-solid fa-print mr-2"></i> Cetak Nota
        </button>
        <a href="nota.php?id=<?= (int) $trx['id'] ?>&amp;format=thermal" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold shadow hover:bg-indigo-700">
            <i class="fa-solid fa-receipt mr-2"></i> Struk 80mm
        </a>
        <?php if ($wa_link !== ''): ?>
            <a href="<?= e($wa_link) ?>" target="_blank" rel="noopener" class="bg-green-600 text-white px-4 py-2 rounded-lg font-semibold shadow hover:bg-green-700">
                <i class="fa-brands fa-whatsapp mr-2"></i> Kirim WA
            </a>
        <?php endif; ?>
        <a href="antrian.php" class="bg-amber-500 text-white px-4 py-2 rounded-lg font-semibold shadow hover:bg-amber-600">
            <i class="fa-solid fa-clock mr-2"></i> Antrian
        </a>
        <a href="transaksi.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg font-semibold shadow hover:bg-gray-700">
            <i class="fa-solid fa-cart-plus mr-2"></i> Transaksi Baru
        </a>
    </div>

    <!-- Kotak Nota Pembelian -->
    <div class="nota-card bg-white w-full max-w-md p-6 rounded-xl shadow-md border border-gray-200">
        <div class="text-center border-b pb-4 mb-4">
            <h1 class="text-xl font-bold text-blue-600"><i class="fa-solid fa-print mr-1"></i> PRINT'TA POS</h1>
            <p class="text-xs text-gray-500">Layanan Printing & Digital Printing</p>
        </div>

        <div class="mb-4 text-sm space-y-1 thermal-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">No. Transaksi:</span>
                <span class="font-semibold text-blue-600"><?= e($trx['trx_code']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Tanggal:</span>
                <span><?= e($tanggal) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Nama Pembeli:</span>
                <span class="font-bold text-gray-800"><?= e($trx['nama_pembeli']) ?></span>
            </div>
            <?php if ($trx['no_hp']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-500">No. HP:</span>
                    <span><?= e($trx['no_hp']) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <span class="text-gray-500">Layanan:</span>
                <span class="uppercase font-semibold text-gray-700"><?= e($trx['layanan']) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-500">Status Pesanan:</span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold <?= e($status_pesanan_class) ?>"><?= e(ucfirst($trx['status_pesanan'])) ?></span>
            </div>
            <?php if ($estimasi !== ''): ?>
                <div class="flex justify-between">
                    <span class="text-gray-500">Estimasi Selesai:</span>
                    <span><?= e($estimasi) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Daftar Item Pembelian -->
        <div class="border-t border-b border-gray-200 py-3 mb-4 space-y-2">
            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider grid grid-cols-12">
                <span class="col-span-6">Item</span>
                <span class="col-span-3 text-center">Qty/Harga</span>
                <span class="col-span-3 text-right">Subtotal</span>
            </div>
            <?php foreach ($items as $item): ?>
                <div class="grid grid-cols-12 text-sm items-center thermal-sm">
                    <div class="col-span-6 font-medium text-gray-800">
                        <?= e($item['produk_nama']) ?>
                        <?php if (!empty($item['catatan'])): ?>
                            <div class="text-xs text-amber-600 font-normal">- <?= e($item['catatan']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-span-3 text-center text-xs text-gray-500"><?= (int) $item['qty'] ?>x @<?= number_format((int) $item['harga_satuan'], 0, ',', '.') ?></div>
                    <div class="col-span-3 text-right font-semibold"><?= rupiah($item['subtotal_item']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Ringkasan Total -->
        <div class="space-y-1 text-sm thermal-sm">
            <div class="flex justify-between text-gray-600">
                <span>Subtotal</span>
                <span><?= rupiah($trx['subtotal']) ?></span>
            </div>
            <?php if ((int) $trx['diskon'] > 0): ?>
                <div class="flex justify-between text-gray-600">
                    <span>Diskon</span>
                    <span>-<?= rupiah($trx['diskon']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int) $trx['pajak'] > 0): ?>
                <div class="flex justify-between text-gray-600">
                    <span>Pajak</span>
                    <span><?= rupiah($trx['pajak']) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between text-gray-600">
                <span>Ongkir</span>
                <span><?= rupiah($trx['ongkir']) ?></span>
            </div>
            <div class="flex justify-between font-bold text-lg text-gray-900 border-t pt-2 mt-2">
                <span>TOTAL</span>
                <span class="text-green-600"><?= rupiah($trx['total']) ?></span>
            </div>
            <div class="flex justify-between text-gray-600 pt-2">
                <span>Dibayar (<?= e(strtoupper($trx['metode_bayar'])) ?>)</span>
                <span><?= rupiah($trx['uang_diterima']) ?></span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Kembalian</span>
                <span><?= rupiah($trx['kembalian']) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Status Bayar</span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold <?= e($status_bayar_class) ?>"><?= e($status_bayar_label) ?></span>
            </div>
        </div>

        <?php if ($trx['catatan']): ?>
            <div class="mt-4 p-3 bg-amber-50 border border-amber-100 rounded text-xs text-amber-800 whitespace-pre-line">
                <span class="font-semibold">Catatan:</span> <?= e($trx['catatan']) ?>
            </div>
        <?php endif; ?>

        <div class="text-center mt-6 pt-4 border-t border-dashed text-xs text-gray-400">
            <p>Terima kasih telah menggunakan jasa kami!</p>
            <p>Simpan nota ini sebagai bukti pembayaran yang sah.</p>
        </div>
    </div>

    <?php if ($thermal): ?>
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>
</html>
