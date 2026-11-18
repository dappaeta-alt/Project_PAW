<?php
require __DIR__ . '/koneksi.php';

function badge_status(string $status): string
{
    $map = [
        'antri' => 'bg-amber-100 text-amber-700',
        'proses' => 'bg-blue-100 text-blue-700',
        'selesai' => 'bg-green-100 text-green-700',
        'diambil' => 'bg-gray-200 text-gray-600',
    ];
    $class = $map[$status] ?? 'bg-gray-100 text-gray-600';

    return '<span class="px-2 py-1 rounded text-xs font-semibold ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function badge_bayar(string $status): string
{
    $map = [
        'lunas' => 'bg-green-100 text-green-700',
        'dp' => 'bg-amber-100 text-amber-700',
        'belum' => 'bg-red-100 text-red-700',
    ];
    $class = $map[$status] ?? 'bg-gray-100 text-gray-600';
    $label = $status === 'dp' ? 'DP' : ucfirst($status);

    return '<span class="px-2 py-1 rounded text-xs font-semibold ' . $class . '">' . e($label) . '</span>';
}

$status_list = ['antri', 'proses', 'selesai', 'diambil'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_status'])) {
    if (!csrf_check()) {
        set_flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!in_array($status, $status_list, true)) {
            set_flash('error', 'Status pesanan tidak valid.');
        } else {
            try {
                $stmt = $conn->prepare("UPDATE transaksi SET status_pesanan = ? WHERE id = ?");
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    set_flash('success', 'Status pesanan diperbarui menjadi ' . ucfirst($status) . '.');
                } else {
                    set_flash('error', 'Pesanan tidak ditemukan atau status tidak berubah.');
                }
            } catch (mysqli_sql_exception $ex) {
                set_flash('error', 'Gagal memperbarui status pesanan.');
            }
        }
    }

    header('Location: antrian.php');
    exit();
}

$filter = $_GET['status'] ?? 'aktif';
if (!in_array($filter, array_merge(['aktif', 'semua'], $status_list), true)) {
    $filter = 'aktif';
}

if ($filter === 'semua') {
    $transaksi_q = $conn->query("SELECT * FROM transaksi ORDER BY id DESC");
} elseif ($filter === 'aktif') {
    $transaksi_q = $conn->query("SELECT * FROM transaksi WHERE status_pesanan IN ('antri', 'proses') ORDER BY status_pesanan ASC, id ASC");
} else {
    $stmt = $conn->prepare("SELECT * FROM transaksi WHERE status_pesanan = ? ORDER BY id ASC");
    $stmt->bind_param('s', $filter);
    $stmt->execute();
    $transaksi_q = $stmt->get_result();
}

$counts = ['antri' => 0, 'proses' => 0, 'selesai' => 0, 'diambil' => 0];
$res_counts = $conn->query("SELECT status_pesanan, COUNT(*) AS total FROM transaksi GROUP BY status_pesanan");
while ($row = $res_counts->fetch_assoc()) {
    $counts[$row['status_pesanan']] = (int) $row['total'];
}
$total_aktif = $counts['antri'] + $counts['proses'];

$items_by_trx = [];
$res_items = $conn->query("SELECT transaksi_id, produk_nama, catatan, qty, harga_satuan FROM detail_transaksi ORDER BY id ASC");
while ($item = $res_items->fetch_assoc()) {
    $items_by_trx[(int) $item['transaksi_id']][] = $item;
}

function tab_link(string $value, string $label, string $filter, int $count = -1): string
{
    $active = $filter === $value
        ? 'bg-blue-600 text-white'
        : 'bg-white text-gray-600 hover:bg-blue-50 border border-gray-200';
    $suffix = $count >= 0 ? ' (' . $count . ')' : '';

    return '<a href="antrian.php?status=' . e($value) . '" class="px-3 py-1.5 rounded-lg text-sm font-semibold ' . $active . '">' . e($label) . $suffix . '</a>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Antrian Pesanan - PRINT'TA POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#F8FAFC] text-[#1E293B] h-screen flex overflow-hidden">
    <aside class="w-64 bg-white shadow-md flex flex-col">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-xl font-bold text-blue-600"><i class="fa-solid fa-print mr-2"></i>PRINT'TA POS</h1>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-house w-6"></i> Dashboard</a>
            <a href="tambah_produk.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-plus w-6"></i> Tambah Produk</a>
            <a href="transaksi.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-cart-shopping w-6"></i> Transaksi Baru</a>
            <a href="antrian.php" class="block p-3 rounded-lg bg-blue-50 text-blue-600 font-medium"><i class="fa-solid fa-clock w-6"></i> Antrian Pesanan</a>
            <a href="riwayat.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-chart-simple w-6"></i> Riwayat Transaksi</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto p-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Antrian Pesanan</h2>
            <span class="text-sm text-gray-500">Pesanan aktif: <span class="font-bold text-blue-600"><?= $total_aktif ?></span></span>
        </div>

        <?= flash_html() ?>

        <div class="flex flex-wrap gap-2 mb-6">
            <?= tab_link('aktif', 'Aktif', $filter, $total_aktif) ?>
            <?= tab_link('antri', 'Antri', $filter, $counts['antri']) ?>
            <?= tab_link('proses', 'Proses', $filter, $counts['proses']) ?>
            <?= tab_link('selesai', 'Selesai', $filter, $counts['selesai']) ?>
            <?= tab_link('diambil', 'Diambil', $filter, $counts['diambil']) ?>
            <?= tab_link('semua', 'Semua', $filter) ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-sm">
                    <tr>
                        <th class="p-4">Kode / Pelanggan</th>
                        <th class="p-4">Item</th>
                        <th class="p-4">Estimasi</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Pembayaran</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($transaksi_q->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="p-6 text-center text-sm text-gray-400 italic">Tidak ada pesanan pada filter ini.</td>
                        </tr>
                    <?php endif; ?>
                    <?php while ($trx = $transaksi_q->fetch_assoc()): ?>
                        <?php $t_id = (int) $trx['id']; ?>
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="p-4">
                                <div class="font-semibold text-blue-600"><?= e($trx['trx_code']) ?></div>
                                <div class="font-bold text-gray-800 text-sm"><?= e($trx['nama_pembeli']) ?></div>
                                <?php if ($trx['no_hp']): ?>
                                    <div class="text-xs text-gray-500"><i class="fa-brands fa-whatsapp"></i> <?= e($trx['no_hp']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-sm">
                                <ul class="space-y-1">
                                    <?php foreach ($items_by_trx[$t_id] ?? [] as $item): ?>
                                        <li>
                                            <span class="font-medium"><?= e($item['produk_nama']) ?></span>
                                            <span class="text-gray-500">(<?= (int) $item['qty'] ?>x)</span>
                                            <?php if ($item['catatan']): ?>
                                                <div class="text-xs text-amber-600">- <?= e($item['catatan']) ?></div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($trx['catatan']): ?>
                                    <div class="mt-2 text-xs text-gray-500 whitespace-pre-line"><i class="fa-solid fa-note-sticky"></i> <?= e($trx['catatan']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-sm text-gray-600">
                                <?php if ($trx['estimasi_selesai']): ?>
                                    <?= e(date('d/m/Y H:i', strtotime($trx['estimasi_selesai']))) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4"><?= badge_status($trx['status_pesanan']) ?></td>
                            <td class="p-4 text-sm">
                                <div><?= badge_bayar($trx['status_bayar']) ?></div>
                                <div class="mt-1 font-semibold"><?= rupiah($trx['total']) ?></div>
                                <div class="text-xs text-gray-500"><?= e(strtoupper($trx['metode_bayar'])) ?></div>
                            </td>
                            <td class="p-4">
                                <form method="POST" class="flex flex-col gap-2 items-center">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $t_id ?>">
                                    <select name="status" class="p-1 border border-gray-300 rounded text-sm">
                                        <?php foreach ($status_list as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $trx['status_pesanan'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="ubah_status" value="1" class="bg-blue-600 text-white px-3 py-1 rounded text-xs font-semibold hover:bg-blue-700">Simpan</button>
                                    <a href="nota.php?id=<?= $t_id ?>" class="text-blue-600 text-xs font-semibold hover:underline">Lihat Nota</a>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
