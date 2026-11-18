<?php
require __DIR__ . '/koneksi.php';

function hitung_harga_satuan(array $produk, int $qty): int
{
    if ($produk['satuan'] === 'lembar') {
        if ($qty === 1) {
            return (int) $produk['harga_1'];
        }
        if ($qty <= 100) {
            return (int) $produk['harga_2'];
        }

        return (int) $produk['harga_3'];
    }

    return (int) $produk['harga_tetap'];
}

function generate_trx_code(mysqli $conn): string
{
    $prefix = 'TRX-' . date('Ymd') . '-';

    $stmt = $conn->prepare("SELECT trx_code FROM transaksi WHERE trx_code LIKE ? ORDER BY trx_code DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $stmt->bind_result($last_code);
    $last = $stmt->fetch() ? $last_code : null;
    $stmt->close();

    $next = 1;
    if ($last !== null) {
        $next = (int) substr($last, strlen($prefix)) + 1;
    }

    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

$products = [];
$res = $conn->query("SELECT * FROM produk ORDER BY nama ASC");
while ($row = $res->fetch_assoc()) {
    $products[(int) $row['id']] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!csrf_check()) {
        set_flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
        header('Location: transaksi.php');
        exit();
    }

    $nama_pembeli = trim($_POST['nama_pembeli'] ?? '');
    if ($nama_pembeli === '') {
        $nama_pembeli = 'Umum';
    }
    if (strlen($nama_pembeli) > 100) {
        set_flash('error', 'Nama pembeli maksimal 100 karakter.');
        header('Location: transaksi.php');
        exit();
    }

    $tanggal = date('Y-m-d H:i:s');
    $tanggal_input = trim($_POST['tanggal'] ?? '');
    if ($tanggal_input !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $tanggal_input);
        if ($dt) {
            $tanggal = $dt->format('Y-m-d H:i:s');
        }
    }

    $no_hp = preg_replace('/[^0-9+\- ]/', '', trim($_POST['no_hp'] ?? ''));
    $no_hp = substr($no_hp, 0, 20);

    $catatan = trim($_POST['catatan'] ?? '');

    $layanan = in_array($_POST['layanan'] ?? '', ['ambil', 'antar'], true) ? $_POST['layanan'] : 'ambil';
    $ongkir = max(0, (int) ($_POST['ongkir'] ?? 0));
    if ($layanan === 'ambil') {
        $ongkir = 0;
    }

    $metode_bayar = in_array($_POST['metode_bayar'] ?? '', ['cash', 'transfer', 'qris'], true)
        ? $_POST['metode_bayar']
        : 'cash';

    $estimasi_selesai = null;
    $estimasi_input = trim($_POST['estimasi_selesai'] ?? '');
    if ($estimasi_input !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $estimasi_input);
        if ($dt) {
            $estimasi_selesai = $dt->format('Y-m-d H:i:s');
        }
    }

    $cart_raw = json_decode($_POST['cart_data'] ?? '', true);
    $items = [];

    if (is_array($cart_raw)) {
        foreach ($cart_raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $product_id = (int) ($item['product_id'] ?? 0);
            if (!isset($products[$product_id])) {
                continue;
            }

            $catatan_item = trim((string) ($item['catatan'] ?? ''));
            if (function_exists('mb_substr')) {
                $catatan_item = mb_substr($catatan_item, 0, 255);
            } else {
                $catatan_item = substr($catatan_item, 0, 255);
            }

            $produk = $products[$product_id];
            $qty = $produk['satuan'] === 'langsung' ? 1 : (int) ($item['qty'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $harga = hitung_harga_satuan($produk, $qty);
            $items[] = [
                'nama' => $produk['nama'],
                'catatan' => $catatan_item,
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => $harga * $qty,
            ];
        }
    }

    if (empty($items)) {
        set_flash('error', 'Keranjang masih kosong atau item tidak valid.');
        header('Location: transaksi.php');
        exit();
    }

    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['subtotal'];
    }

    $diskon = max(0, (int) ($_POST['diskon'] ?? 0));
    if ($diskon > $subtotal) {
        $diskon = $subtotal;
    }

    $pajak = max(0, (int) ($_POST['pajak'] ?? 0));
    $total = max(0, $subtotal - $diskon + $pajak + $ongkir);

    // Jika kolom pembayaran tidak diisi, anggap dibayar penuh (lunas).
    $uang_input = $_POST['uang_diterima'] ?? '';
    if ($uang_input === '') {
        $uang_diterima = $total;
    } else {
        $uang_diterima = max(0, (int) $uang_input);
    }

    if ($uang_diterima >= $total) {
        $status_bayar = 'lunas';
    } elseif ($uang_diterima > 0) {
        $status_bayar = 'dp';
    } else {
        $status_bayar = 'belum';
    }
    $kembalian = $uang_diterima > $total ? $uang_diterima - $total : 0;

    $conn->begin_transaction();

    try {
        $transaksi_id = null;

        // Retry bila kode transaksi bentrok (unique index)
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $trx_code = generate_trx_code($conn);

            try {
                $stmt = $conn->prepare("INSERT INTO transaksi (trx_code, nama_pembeli, tanggal, no_hp, catatan, layanan, status_pesanan, estimasi_selesai, ongkir, metode_bayar, status_bayar, uang_diterima, kembalian, subtotal, diskon, pajak, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $status_pesanan = 'antri';
                $stmt->bind_param(
                    'ssssssssissiiiiii',
                    $trx_code,
                    $nama_pembeli,
                    $tanggal,
                    $no_hp,
                    $catatan,
                    $layanan,
                    $status_pesanan,
                    $estimasi_selesai,
                    $ongkir,
                    $metode_bayar,
                    $status_bayar,
                    $uang_diterima,
                    $kembalian,
                    $subtotal,
                    $diskon,
                    $pajak,
                    $total
                );
                $stmt->execute();
                $transaksi_id = (int) $stmt->insert_id;
                break;
            } catch (mysqli_sql_exception $ex) {
                if ((int) $ex->getCode() === 1062 && $attempt < 4) {
                    continue;
                }
                throw $ex;
            }
        }

        if (!$transaksi_id) {
            throw new RuntimeException('Gagal membuat kode transaksi.');
        }

        $stmt_detail = $conn->prepare("INSERT INTO detail_transaksi (transaksi_id, produk_nama, catatan, qty, harga_satuan, subtotal_item) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $p_nama = $item['nama'];
            $p_catatan = $item['catatan'];
            $p_qty = $item['qty'];
            $p_harga = $item['harga'];
            $p_subtotal = $item['subtotal'];
            $stmt_detail->bind_param('issiii', $transaksi_id, $p_nama, $p_catatan, $p_qty, $p_harga, $p_subtotal);
            $stmt_detail->execute();
        }

        $conn->commit();

        header('Location: nota.php?id=' . $transaksi_id);
        exit();
    } catch (Throwable $ex) {
        $conn->rollback();
        set_flash('error', 'Gagal menyimpan transaksi. Silakan coba lagi.');
        header('Location: transaksi.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi Baru - PRINT'TA POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        const productsData = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
</head>
<body class="bg-[#F8FAFC] text-[#1E293B] h-screen flex overflow-hidden">
    <aside class="w-64 bg-white shadow-md flex flex-col">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-xl font-bold text-blue-600"><i class="fa-solid fa-print mr-2"></i>PRINT'TA POS</h1>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-house w-6"></i> Dashboard</a>
            <a href="tambah_produk.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-plus w-6"></i> Tambah Produk</a>
            <a href="transaksi.php" class="block p-3 rounded-lg bg-blue-50 text-blue-600 font-medium"><i class="fa-solid fa-cart-shopping w-6"></i> Transaksi Baru</a>
            <a href="antrian.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-clock w-6"></i> Antrian Pesanan</a>
            <a href="riwayat.php" class="block p-3 rounded-lg hover:bg-blue-50 text-gray-700 font-medium"><i class="fa-solid fa-chart-simple w-6"></i> Riwayat Transaksi</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto p-8">
        <h2 class="text-2xl font-bold mb-6">Transaksi Baru</h2>

        <?= flash_html() ?>

        <form method="POST" id="form-checkout">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-2 space-y-6">
                    <!-- Informasi Transaksi (wajib: nama & tanggal) -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold">Informasi Transaksi</h3>
                            <span class="text-xs text-gray-400">Cukup isi nama & tanggal</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Nama Pembeli</label>
                                <input type="text" name="nama_pembeli" placeholder="Contoh: Budi / Kantor XYZ" required autofocus class="w-full p-2 border border-gray-300 rounded focus:ring-blue-600">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Tanggal</label>
                                <input type="datetime-local" name="tanggal" value="<?= date('Y-m-d\TH:i') ?>" class="w-full p-2 border border-gray-300 rounded">
                            </div>
                        </div>

                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-blue-600 select-none">Detail Tambahan (opsional)</summary>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                <div>
                                    <label class="block text-sm font-semibold mb-2">No. HP / WhatsApp</label>
                                    <input type="text" name="no_hp" placeholder="08xxxxxxxxxx" maxlength="20" class="w-full p-2 border border-gray-300 rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2">Estimasi Selesai</label>
                                    <input type="datetime-local" name="estimasi_selesai" class="w-full p-2 border border-gray-300 rounded">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold mb-2">Catatan Pesanan</label>
                                    <textarea name="catatan" rows="2" placeholder="Contoh: jilid spiral, kertas HVS 80gr, ambil besok sore" class="w-full p-2 border border-gray-300 rounded"></textarea>
                                </div>
                            </div>
                        </details>
                    </div>

                    <!-- Tambah Item -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="font-bold mb-4">Tambah Item</h3>
                        <div class="flex gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-semibold mb-2">Pilih Produk</label>
                                <select id="trx-produk" class="w-full p-2 border border-gray-300 rounded" onchange="cekSatuanTrx()">
                                    <option value="">-- Pilih Produk --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nama']) ?> (<?= e(strtoupper($p['satuan'])) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-24" id="trx-qty-container">
                                <label class="block text-sm font-semibold mb-2">Jumlah</label>
                                <input type="number" id="trx-qty" value="1" min="1" class="w-full p-2 border border-gray-300 rounded">
                            </div>
                            <button type="button" onclick="tambahKeKeranjang()" class="bg-blue-600 text-white px-4 py-2 rounded font-semibold hover:bg-blue-700">Tambah</button>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-semibold mb-2">Catatan Item (opsional)</label>
                            <input type="text" id="trx-catatan" maxlength="255" placeholder="Contoh: warna biru, ukuran A4" class="w-full p-2 border border-gray-300 rounded">
                        </div>
                    </div>

                    <!-- Layanan & Pembayaran (opsional) -->
                    <details class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <summary class="cursor-pointer font-bold select-none">Layanan & Pembayaran (opsional)</summary>
                        <p class="text-xs text-gray-400 mt-1">Kosongkan saja jika tidak perlu — pembayaran otomatis dianggap lunas.</p>

                        <div class="mt-4">
                            <h4 class="font-semibold text-sm mb-2">Layanan Pengiriman</h4>
                            <div class="flex gap-6 mb-3">
                                <label><input type="radio" name="layanan" value="ambil" checked onchange="toggleOngkir()"> Ambil Sendiri</label>
                                <label><input type="radio" name="layanan" value="antar" onchange="toggleOngkir()"> Diantar</label>
                            </div>
                            <div id="input-ongkir" class="hidden w-1/2">
                                <label class="block text-sm font-semibold mb-2">Ongkir</label>
                                <input type="number" id="trx-ongkir" value="0" min="0" oninput="updateTotal()" class="w-full p-2 border border-gray-300 rounded">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Metode Bayar</label>
                                <select name="metode_bayar" class="w-full p-2 border border-gray-300 rounded">
                                    <option value="cash">Cash</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Jumlah Dibayar</label>
                                <input type="number" name="uang_diterima" id="trx-bayar" value="0" min="0" oninput="bayarTouched = true; updatePayment()" class="w-full p-2 border border-gray-300 rounded">
                            </div>
                        </div>
                        <div class="mt-3 flex justify-between text-sm">
                            <span class="text-gray-600">Status Pembayaran: <span id="txt-status-bayar" class="font-semibold text-red-600">Belum Bayar</span></span>
                            <span class="text-gray-600">Kembalian: <span id="txt-kembalian" class="font-semibold text-gray-800">Rp0</span></span>
                        </div>
                    </details>
                </div>

                <!-- Keranjang -->
                <div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 sticky top-0">
                        <h3 class="font-bold mb-4 border-b pb-2 text-center">Keranjang Belanja</h3>
                        <div id="keranjang-list" class="space-y-3 mb-6 min-h-[150px]">
                            <p class="text-sm text-gray-400 text-center italic">Keranjang kosong</p>
                        </div>

                        <div class="border-t pt-4 space-y-2">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Subtotal</span>
                                <span id="txt-subtotal">Rp0</span>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-600 gap-2">
                                <span>Diskon (Rp)</span>
                                <input type="number" name="diskon" id="trx-diskon" value="0" min="0" oninput="updateTotal()" class="w-24 p-1 border border-gray-300 rounded text-right">
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-600 gap-2">
                                <span>Pajak (Rp)</span>
                                <input type="number" name="pajak" id="trx-pajak" value="0" min="0" oninput="updateTotal()" class="w-24 p-1 border border-gray-300 rounded text-right">
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Ongkir</span>
                                <span id="txt-ongkir">Rp0</span>
                            </div>
                            <div class="flex justify-between font-bold text-lg text-gray-800 pt-2 border-t mt-2">
                                <span>TOTAL</span>
                                <span id="txt-total">Rp0</span>
                            </div>
                        </div>

                        <input type="hidden" name="cart_data" id="cart_data">
                        <input type="hidden" name="ongkir" id="hidden_ongkir" value="0">

                        <button type="submit" name="checkout" onclick="return prepareCheckout()" class="w-full bg-green-500 text-white font-bold py-3 rounded-lg mt-6 hover:bg-green-600 shadow-md">Proses Pembayaran</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        let cart = [];
        let subtotal = 0;
        let currentTotal = 0;
        let bayarTouched = false;

        function esc(s) {
            return String(s).replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        }

        function cekSatuanTrx() {
            const id = document.getElementById('trx-produk').value;
            const qtyContainer = document.getElementById('trx-qty-container');
            if (!id) return;
            const prod = productsData[id];

            if (prod.satuan === 'langsung') {
                qtyContainer.classList.add('hidden');
                document.getElementById('trx-qty').value = 1;
            } else {
                qtyContainer.classList.remove('hidden');
            }
        }

        function tambahKeKeranjang() {
            const id = document.getElementById('trx-produk').value;
            const qty = parseInt(document.getElementById('trx-qty').value) || 1;
            if (!id) return alert('Pilih produk dulu!');

            const prod = productsData[id];
            const catatan = document.getElementById('trx-catatan').value.trim();
            let hargaSatuan = 0;

            if (prod.satuan === 'lembar') {
                if (qty === 1) hargaSatuan = parseInt(prod.harga_1);
                else if (qty >= 2 && qty <= 100) hargaSatuan = parseInt(prod.harga_2);
                else hargaSatuan = parseInt(prod.harga_3);
            } else {
                hargaSatuan = parseInt(prod.harga_tetap);
            }

            const totalItem = hargaSatuan * (prod.satuan === 'langsung' ? 1 : qty);

            cart.push({
                product_id: parseInt(id),
                nama: prod.nama,
                catatan: catatan,
                qty: prod.satuan === 'langsung' ? 1 : qty,
                hargaSatuan: hargaSatuan,
                total: totalItem,
                satuan: prod.satuan
            });

            document.getElementById('trx-catatan').value = '';
            renderKeranjang();
        }

        function renderKeranjang() {
            const list = document.getElementById('keranjang-list');
            list.innerHTML = '';
            subtotal = 0;

            if (cart.length === 0) {
                list.innerHTML = '<p class="text-sm text-gray-400 text-center italic">Keranjang kosong</p>';
            }

            cart.forEach((item) => {
                subtotal += item.total;
                const qtyText = item.satuan === 'langsung' ? 'Layanan Tetap' : `${item.qty} x Rp${item.hargaSatuan.toLocaleString('id-ID')}`;
                const catatanHtml = item.catatan ? `<div class="text-xs text-amber-600 mt-1">Catatan: ${esc(item.catatan)}</div>` : '';
                list.innerHTML += `
                    <div class="flex justify-between items-start bg-gray-50 p-2 rounded">
                        <div>
                            <div class="font-semibold text-sm">${esc(item.nama)}</div>
                            <div class="text-xs text-gray-500">${qtyText}</div>
                            ${catatanHtml}
                        </div>
                        <div class="font-bold text-sm">Rp${item.total.toLocaleString('id-ID')}</div>
                    </div>
                `;
            });

            updateTotal();
        }

        function toggleOngkir() {
            const layanan = document.querySelector('input[name="layanan"]:checked').value;
            const inputOngkir = document.getElementById('input-ongkir');

            if (layanan === 'antar') {
                inputOngkir.classList.remove('hidden');
            } else {
                inputOngkir.classList.add('hidden');
                document.getElementById('trx-ongkir').value = 0;
            }
            updateTotal();
        }

        function updateTotal() {
            const ongkir = parseInt(document.getElementById('trx-ongkir').value) || 0;
            const diskon = Math.min(parseInt(document.getElementById('trx-diskon').value) || 0, subtotal);
            const pajak = parseInt(document.getElementById('trx-pajak').value) || 0;
            currentTotal = Math.max(0, subtotal - diskon + pajak + ongkir);

            document.getElementById('txt-subtotal').innerText = `Rp${subtotal.toLocaleString('id-ID')}`;
            document.getElementById('txt-ongkir').innerText = `Rp${ongkir.toLocaleString('id-ID')}`;
            document.getElementById('txt-total').innerText = `Rp${currentTotal.toLocaleString('id-ID')}`;
            document.getElementById('hidden_ongkir').value = ongkir;

            // Otomatis isi jumlah dibayar = total selama belum diubah manual.
            if (!bayarTouched) {
                document.getElementById('trx-bayar').value = currentTotal;
            }

            updatePayment();
        }

        function updatePayment() {
            const bayar = parseInt(document.getElementById('trx-bayar').value) || 0;
            const statusEl = document.getElementById('txt-status-bayar');
            const kembaliEl = document.getElementById('txt-kembalian');

            let status = 'Belum Bayar';
            let cls = 'text-red-600';

            if (bayar >= currentTotal) {
                status = 'Lunas';
                cls = 'text-green-600';
            } else if (bayar > 0) {
                status = 'DP / Sebagian';
                cls = 'text-amber-600';
            }

            statusEl.innerText = status;
            statusEl.className = 'font-semibold ' + cls;
            kembaliEl.innerText = `Rp${Math.max(0, bayar - currentTotal).toLocaleString('id-ID')}`;
        }

        function prepareCheckout() {
            if (cart.length === 0) {
                alert('Keranjang masih kosong!');
                return false;
            }
            updateTotal();
            document.getElementById('cart_data').value = JSON.stringify(cart);
            return true;
        }

        document.getElementById('trx-qty').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                tambahKeKeranjang();
            }
        });

        document.getElementById('trx-catatan').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                tambahKeKeranjang();
            }
        });
    </script>
</body>
</html>
