<?php
require __DIR__ . '/koneksi.php';

$form = [
    'nama' => '',
    'kategori' => 'Print',
    'satuan' => 'lembar',
    'harga_1' => 0,
    'harga_2' => 0,
    'harga_3' => 0,
    'harga_tetap' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (!csrf_check()) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['nama'] = trim($_POST['nama'] ?? '');
    $form['kategori'] = $_POST['kategori'] ?? 'Print';
    $form['satuan'] = $_POST['satuan'] ?? 'lembar';
    $form['harga_1'] = (int) ($_POST['harga_1'] ?? 0);
    $form['harga_2'] = (int) ($_POST['harga_2'] ?? 0);
    $form['harga_3'] = (int) ($_POST['harga_3'] ?? 0);
    $form['harga_tetap'] = (int) ($_POST['harga_tetap'] ?? 0);

    if ($form['nama'] === '') {
        $errors[] = 'Nama produk wajib diisi.';
    }
    if (strlen($form['nama']) > 100) {
        $errors[] = 'Nama produk maksimal 100 karakter.';
    }
    if (!in_array($form['kategori'], ['Print', 'Jasa', 'Barang'], true)) {
        $errors[] = 'Kategori tidak valid.';
    }
    if (!in_array($form['satuan'], ['lembar', 'pcs', 'langsung'], true)) {
        $errors[] = 'Satuan tidak valid.';
    }
    foreach (['harga_1', 'harga_2', 'harga_3', 'harga_tetap'] as $key) {
        if ($form[$key] < 0) {
            $errors[] = 'Harga tidak boleh negatif.';
            break;
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO produk (nama, kategori, satuan, harga_1, harga_2, harga_3, harga_tetap) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $nama = $form['nama'];
            $kategori = $form['kategori'];
            $satuan = $form['satuan'];
            $h1 = $form['harga_1'];
            $h2 = $form['harga_2'];
            $h3 = $form['harga_3'];
            $h_tetap = $form['harga_tetap'];
            $stmt->bind_param('sssiiii', $nama, $kategori, $satuan, $h1, $h2, $h3, $h_tetap);
            $stmt->execute();

            set_flash('success', 'Produk berhasil ditambahkan.');
            header('Location: index.php');
            exit();
        } catch (mysqli_sql_exception $ex) {
            set_flash('error', 'Gagal menyimpan produk. Silakan coba lagi.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk - PRINT'TA POS</title>
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
            <a href="tambah_produk.php" class="block p-3 rounded-lg bg-blue-50 text-blue-600 font-medium"><i class="fa-solid fa-plus w-6"></i> Tambah Produk</a>
            <a href="transaksi.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-cart-shopping w-6"></i> Transaksi Baru</a>
            <a href="antrian.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-clock w-6"></i> Antrian Pesanan</a>
            <a href="riwayat.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-chart-simple w-6"></i> Riwayat Transaksi</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto p-8">
        <h2 class="text-2xl font-bold mb-6">Tambah Produk Baru</h2>
        <div class="bg-white p-6 rounded-xl shadow-sm max-w-2xl border border-gray-100">
            <?= flash_html() ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Nama Produk</label>
                    <input type="text" name="nama" value="<?= e($form['nama']) ?>" required class="w-full p-2 border border-gray-300 rounded focus:ring-blue-600">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Kategori</label>
                    <select name="kategori" class="w-full p-2 border border-gray-300 rounded">
                        <option value="Print" <?= $form['kategori'] === 'Print' ? 'selected' : '' ?>>Print</option>
                        <option value="Jasa" <?= $form['kategori'] === 'Jasa' ? 'selected' : '' ?>>Jasa</option>
                        <option value="Barang" <?= $form['kategori'] === 'Barang' ? 'selected' : '' ?>>Barang</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Satuan</label>
                    <div class="flex gap-4">
                        <label><input type="radio" name="satuan" value="lembar" <?= $form['satuan'] === 'lembar' ? 'checked' : '' ?> onclick="toggleInput('lembar')"> Lembar</label>
                        <label><input type="radio" name="satuan" value="pcs" <?= $form['satuan'] === 'pcs' ? 'checked' : '' ?> onclick="toggleInput('pcs')"> PCS</label>
                        <label><input type="radio" name="satuan" value="langsung" <?= $form['satuan'] === 'langsung' ? 'checked' : '' ?> onclick="toggleInput('langsung')"> Langsung</label>
                    </div>
                </div>

                <div id="input-lembar" class="bg-gray-50 p-4 rounded-lg mb-4 <?= $form['satuan'] === 'lembar' ? '' : 'hidden' ?>">
                    <h4 class="font-semibold mb-3 text-sm text-gray-600">Skema Harga Berjenjang</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs mb-1">Harga 1 Lembar</label>
                            <input type="number" name="harga_1" min="0" class="w-full p-2 border rounded" value="<?= (int) $form['harga_1'] ?>">
                        </div>
                        <div>
                            <label class="block text-xs mb-1">Harga 2-100 Lembar</label>
                            <input type="number" name="harga_2" min="0" class="w-full p-2 border rounded" value="<?= (int) $form['harga_2'] ?>">
                        </div>
                        <div>
                            <label class="block text-xs mb-1">Harga 100+ Lembar</label>
                            <input type="number" name="harga_3" min="0" class="w-full p-2 border rounded" value="<?= (int) $form['harga_3'] ?>">
                        </div>
                    </div>
                </div>

                <div id="input-tetap" class="bg-gray-50 p-4 rounded-lg mb-4 <?= $form['satuan'] === 'lembar' ? 'hidden' : '' ?>">
                    <label class="block text-sm font-semibold mb-2" id="label-tetap"><?= $form['satuan'] === 'pcs' ? 'Harga per PCS' : 'Harga Tetap' ?></label>
                    <input type="number" name="harga_tetap" min="0" class="w-full p-2 border rounded" value="<?= (int) $form['harga_tetap'] ?>">
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700">Simpan Produk</button>
            </form>
        </div>
    </main>

    <script>
        function toggleInput(tipe) {
            const lembar = document.getElementById('input-lembar');
            const tetap = document.getElementById('input-tetap');
            const labelTetap = document.getElementById('label-tetap');

            if (tipe === 'lembar') {
                lembar.classList.remove('hidden');
                tetap.classList.add('hidden');
            } else {
                lembar.classList.add('hidden');
                tetap.classList.remove('hidden');
                labelTetap.innerText = tipe === 'pcs' ? 'Harga per PCS' : 'Harga Tetap';
            }
        }
    </script>
</body>
</html>
