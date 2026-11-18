<?php
require __DIR__ . '/koneksi.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    set_flash('error', 'Produk tidak ditemukan.');
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (!csrf_check()) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $nama = trim($_POST['nama'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Print';
    $satuan = $_POST['satuan'] ?? 'lembar';
    $h1 = (int) ($_POST['harga_1'] ?? 0);
    $h2 = (int) ($_POST['harga_2'] ?? 0);
    $h3 = (int) ($_POST['harga_3'] ?? 0);
    $h_tetap = (int) ($_POST['harga_tetap'] ?? 0);

    if ($nama === '') {
        $errors[] = 'Nama produk wajib diisi.';
    }
    if (strlen($nama) > 100) {
        $errors[] = 'Nama produk maksimal 100 karakter.';
    }
    if (!in_array($kategori, ['Print', 'Jasa', 'Barang'], true)) {
        $errors[] = 'Kategori tidak valid.';
    }
    if (!in_array($satuan, ['lembar', 'pcs', 'langsung'], true)) {
        $errors[] = 'Satuan tidak valid.';
    }
    foreach ([$h1, $h2, $h3, $h_tetap] as $harga) {
        if ($harga < 0) {
            $errors[] = 'Harga tidak boleh negatif.';
            break;
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        $p = array_merge($p, [
            'nama' => $nama,
            'kategori' => $kategori,
            'satuan' => $satuan,
            'harga_1' => $h1,
            'harga_2' => $h2,
            'harga_3' => $h3,
            'harga_tetap' => $h_tetap,
        ]);
    } else {
        try {
            $stmt = $conn->prepare("UPDATE produk SET nama = ?, kategori = ?, satuan = ?, harga_1 = ?, harga_2 = ?, harga_3 = ?, harga_tetap = ? WHERE id = ?");
            $stmt->bind_param('sssiiiii', $nama, $kategori, $satuan, $h1, $h2, $h3, $h_tetap, $id);
            $stmt->execute();

            set_flash('success', 'Produk berhasil diperbarui.');
            header('Location: index.php');
            exit();
        } catch (mysqli_sql_exception $ex) {
            set_flash('error', 'Gagal memperbarui produk. Silakan coba lagi.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Produk - PRINT'TA POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#F8FAFC] text-[#1E293B] h-screen flex overflow-hidden">
    <main class="flex-1 overflow-y-auto p-8 max-w-2xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">Edit Produk</h2>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <?= flash_html() ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Nama Produk</label>
                    <input type="text" name="nama" value="<?= e($p['nama']) ?>" required class="w-full p-2 border border-gray-300 rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Kategori</label>
                    <select name="kategori" class="w-full p-2 border border-gray-300 rounded">
                        <option value="Print" <?= $p['kategori'] === 'Print' ? 'selected' : '' ?>>Print</option>
                        <option value="Jasa" <?= $p['kategori'] === 'Jasa' ? 'selected' : '' ?>>Jasa</option>
                        <option value="Barang" <?= $p['kategori'] === 'Barang' ? 'selected' : '' ?>>Barang</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Satuan</label>
                    <div class="flex gap-4">
                        <label><input type="radio" name="satuan" value="lembar" <?= $p['satuan'] === 'lembar' ? 'checked' : '' ?> onclick="toggleInput('lembar')"> Lembar</label>
                        <label><input type="radio" name="satuan" value="pcs" <?= $p['satuan'] === 'pcs' ? 'checked' : '' ?> onclick="toggleInput('pcs')"> PCS</label>
                        <label><input type="radio" name="satuan" value="langsung" <?= $p['satuan'] === 'langsung' ? 'checked' : '' ?> onclick="toggleInput('langsung')"> Langsung</label>
                    </div>
                </div>

                <div id="input-lembar" class="bg-gray-50 p-4 rounded-lg mb-4 <?= $p['satuan'] !== 'lembar' ? 'hidden' : '' ?>">
                    <h4 class="font-semibold mb-3 text-sm text-gray-600">Skema Harga Berjenjang</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs mb-1">Harga 1 Lembar</label>
                            <input type="number" name="harga_1" min="0" class="w-full p-2 border rounded" value="<?= (int) $p['harga_1'] ?>">
                        </div>
                        <div>
                            <label class="block text-xs mb-1">Harga 2-100 Lembar</label>
                            <input type="number" name="harga_2" min="0" class="w-full p-2 border rounded" value="<?= (int) $p['harga_2'] ?>">
                        </div>
                        <div>
                            <label class="block text-xs mb-1">Harga 100+ Lembar</label>
                            <input type="number" name="harga_3" min="0" class="w-full p-2 border rounded" value="<?= (int) $p['harga_3'] ?>">
                        </div>
                    </div>
                </div>

                <div id="input-tetap" class="bg-gray-50 p-4 rounded-lg mb-4 <?= $p['satuan'] === 'lembar' ? 'hidden' : '' ?>">
                    <label class="block text-sm font-semibold mb-2" id="label-tetap"><?= $p['satuan'] === 'pcs' ? 'Harga per PCS' : 'Harga Tetap' ?></label>
                    <input type="number" name="harga_tetap" min="0" class="w-full p-2 border rounded" value="<?= (int) $p['harga_tetap'] ?>">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700">Update Produk</button>
                    <a href="index.php" class="bg-gray-200 px-6 py-2 rounded font-semibold text-gray-700 hover:bg-gray-300">Batal</a>
                </div>
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
