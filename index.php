<?php
require __DIR__ . '/koneksi.php';

// Hapus produk (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_produk'])) {
    if (!csrf_check()) {
        set_flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    } else {
        $id = (int) $_POST['hapus_produk'];
        try {
            $stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                set_flash('success', 'Produk berhasil dihapus.');
            } else {
                set_flash('error', 'Produk tidak ditemukan.');
            }
        } catch (mysqli_sql_exception $ex) {
            set_flash('error', 'Gagal menghapus produk.');
        }
    }

    header('Location: index.php');
    exit();
}

// Ambil statistik
$omset = 0;
$total_trx = 0;
$total_prod = 0;

$res = $conn->query("SELECT COALESCE(SUM(total), 0) AS omset FROM transaksi WHERE DATE(tanggal) = CURDATE()");
if ($row = $res->fetch_assoc()) {
    $omset = (int) $row['omset'];
}

$res = $conn->query("SELECT COUNT(*) AS total FROM transaksi WHERE DATE(tanggal) = CURDATE()");
if ($row = $res->fetch_assoc()) {
    $total_trx = (int) $row['total'];
}

$res = $conn->query("SELECT COUNT(*) AS total FROM produk");
if ($row = $res->fetch_assoc()) {
    $total_prod = (int) $row['total'];
}

$total_aktif = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM transaksi WHERE status_pesanan IN ('antri', 'proses')");
if ($row = $res->fetch_assoc()) {
    $total_aktif = (int) $row['total'];
}

$produk_list = $conn->query("SELECT * FROM produk ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - PRINT'TA POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#F8FAFC] text-[#1E293B] h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-md flex flex-col">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-xl font-bold text-blue-600"><i class="fa-solid fa-print mr-2"></i>PRINT'TA POS</h1>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="block p-3 rounded-lg bg-blue-50 text-blue-600 font-medium"><i class="fa-solid fa-house w-6"></i> Dashboard</a>
            <a href="tambah_produk.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-plus w-6"></i> Tambah Produk</a>
            <a href="transaksi.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-cart-shopping w-6"></i> Transaksi Baru</a>
            <a href="antrian.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-clock w-6"></i> Antrian Pesanan</a>
            <a href="riwayat.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-chart-simple w-6"></i> Riwayat Transaksi</a>
        </nav>
        <div class="p-4 border-t border-gray-200">
            <a href="backup.php" class="block p-2 text-sm text-gray-600 hover:text-blue-600"><i class="fa-solid fa-download w-5"></i> Backup / Export Data</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8">
        <h2 class="text-2xl font-bold mb-6">Dashboard</h2>

        <?= flash_html() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-gray-500 text-sm font-semibold">Pendapatan Hari Ini</p>
                <h3 class="text-3xl font-bold text-green-500 mt-2"><?= rupiah($omset) ?></h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-gray-500 text-sm font-semibold">Total Transaksi Hari Ini</p>
                <h3 class="text-3xl font-bold text-blue-600 mt-2"><?= $total_trx ?></h3>
            </div>
            <a href="antrian.php" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:border-amber-300">
                <p class="text-gray-500 text-sm font-semibold">Pesanan Aktif</p>
                <h3 class="text-3xl font-bold text-amber-500 mt-2"><?= $total_aktif ?></h3>
            </a>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-gray-500 text-sm font-semibold">Total Produk</p>
                <h3 class="text-3xl font-bold text-amber-500 mt-2"><?= $total_prod ?></h3>
            </div>
        </div>

        <h3 class="text-xl font-bold mb-4">Daftar Produk (Bisa Diedit/Dihapus)</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php if ($produk_list->num_rows === 0): ?>
                <p class="text-sm text-gray-400 italic">Belum ada produk. Silakan tambah produk terlebih dahulu.</p>
            <?php endif; ?>
            <?php while ($p = $produk_list->fetch_assoc()): ?>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 relative flex flex-col justify-between">
                    <div>
                        <span class="absolute top-2 right-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded"><?= e(strtoupper($p['satuan'])) ?></span>
                        <h4 class="font-bold text-gray-800 mt-1"><?= e($p['nama']) ?></h4>
                        <p class="text-xs text-gray-400 mb-2"><?= e($p['kategori']) ?></p>

                        <?php if ($p['satuan'] === 'lembar'): ?>
                            <div class="text-sm space-y-1 text-gray-600">
                                <div>1 = <?= rupiah($p['harga_1']) ?></div>
                                <div>2-100 = <?= rupiah($p['harga_2']) ?></div>
                                <div>100+ = <?= rupiah($p['harga_3']) ?></div>
                            </div>
                        <?php else: ?>
                            <div class="text-sm font-semibold text-blue-600 mt-2"><?= rupiah($p['harga_tetap']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-2 mt-4 pt-3 border-t border-gray-100">
                        <a href="edit_produk.php?id=<?= (int) $p['id'] ?>" class="flex-1 bg-amber-50 text-amber-600 text-center py-1 rounded text-sm font-semibold hover:bg-amber-100"><i class="fa-solid fa-pen"></i> Edit</a>
                        <form method="POST" action="index.php" class="flex-1" onsubmit="return confirm('Yakin hapus produk ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="hapus_produk" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="w-full bg-red-50 text-red-600 text-center py-1 rounded text-sm font-semibold hover:bg-red-100"><i class="fa-solid fa-trash"></i> Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </main>
</body>
</html>
