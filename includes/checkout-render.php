<?php
$items = $_SESSION['cart'] ?? [];
if (!$items) redirect('cart.php');
$total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $items));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $whatsapp = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name || strlen($whatsapp) < 8 || !$address) {
        $error = 'Lengkapi data pelanggan dengan benar.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            // Email is retained only for compatibility with the existing database schema.
            $customer = $pdo->prepare('INSERT INTO customers(full_name,email,whatsapp,address) VALUES(?,?,?,?)');
            $customer->execute([$name, '', $whatsapp, $address]);
            $customerId = (int) $pdo->lastInsertId();
            $code = 'TX-' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->prepare('INSERT INTO checkout_transactions(transaction_code,customer_id,total) VALUES(?,?,?)')->execute([$code, $customerId, $total]);
            $transactionId = (int) $pdo->lastInsertId();

            $groups = [];
            foreach ($items as $item) {
                $categoryQuery = $pdo->prepare('SELECT c.slug,c.name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?');
                $categoryQuery->execute([$item['product_id']]);
                $category = $categoryQuery->fetch();
                $groups[$category['slug']]['name'] = $category['name'];
                $groups[$category['slug']]['items'][] = $item;
            }

            $orderIds = [];
            foreach ($groups as $slug => $group) {
                $prefix = ['jaket' => 'J', 'batik' => 'B', 'kaos' => 'K'][$slug] ?? 'C';
                $pdo->prepare('UPDATE order_sequences SET last_number=LAST_INSERT_ID(last_number+1) WHERE category_key=?')->execute([$slug]);
                $number = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
                $orderNumber = $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $sum = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $group['items']));
                $pdo->prepare('INSERT INTO orders(checkout_transaction_id,order_number,category_key,customer_id,total,customer_note) VALUES(?,?,?,?,?,?)')->execute([$transactionId, $orderNumber, $slug, $customerId, $sum, trim($_POST['note'] ?? '')]);
                $orderId = (int) $pdo->lastInsertId();
                $orderIds[] = $orderId;
                $line = $pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,category_name,unit_price,quantity,selected_size_code,selected_size_name,selected_variant,selected_motif) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $productStock = $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
                $sizeStock = $pdo->prepare('UPDATE product_sizes ps JOIN sizes s ON s.id=ps.size_id SET ps.stock=ps.stock-? WHERE ps.product_id=? AND s.code=? AND ps.stock>=?');
                foreach ($group['items'] as $item) {
                    $quantity = (int) $item['quantity'];
                    $productStock->execute([$quantity, $item['product_id'], $quantity]);
                    if ($productStock->rowCount() !== 1) throw new RuntimeException('Stok produk sudah tidak mencukupi.');
                    $sizeCode = $item['size_code'] === '4XL' ? 'XXXL' : $item['size_code'];
                    $sizeStock->execute([$quantity, $item['product_id'], $sizeCode, $quantity]);
                    if ($sizeStock->rowCount() !== 1) throw new RuntimeException('Stok ukuran yang dipilih sudah tidak mencukupi.');
                    $line->execute([$orderId, $item['product_id'], $item['name'], $group['name'], $item['price'], $item['quantity'], $item['size_code'], $item['size_name'], $item['variant'] ?: null, $item['motif'] ?: null]);
                }
            }
            $pdo->commit();
            unset($_SESSION['cart']);
            $_SESSION['confirmation_orders'] = $orderIds;
            redirect('confirmation.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Pesanan belum dapat disimpan. Silakan coba lagi.';
        }
    }
}

$title = 'Checkout';
require __DIR__ . '/header.php';
?>
<main class="checkout-wrap">
  <div class="eyebrow">SECURE CHECKOUT</div>
  <h1 class="serif text-5xl mt-2">Complete your order.</h1>
  <?php if (!empty($error)): ?><p class="error mt-5"><?= e($error) ?></p><?php endif; ?>
  <div class="grid md:grid-cols-2 gap-10 mt-8">
    <form method="post" class="bg-white p-7">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <h2 class="serif text-2xl">Delivery details</h2>
      <div class="field"><label>Nama lengkap</label><input required name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>"></div>
      <div class="field"><label>Nomor WhatsApp <small>Pastikan nomor telepon sudah benar agar admin dapat menghubungi terkait pesanan.</small></label><input required name="whatsapp" inputmode="tel" placeholder="628..." value="<?= e($_POST['whatsapp'] ?? '') ?>"></div>
      <div class="field"><label>Alamat pengiriman <small>(Jika diambil di kampus, tulis: di kampus)</small></label><textarea required name="address" rows="4" placeholder="Tulis alamat lengkap atau: di kampus"><?= e($_POST['address'] ?? '') ?></textarea></div>
      <div class="field"><label>Catatan pesanan (opsional)</label><textarea name="note" rows="2"></textarea></div>
      <button class="btn gold w-full mt-3">PLACE ORDER</button>
    </form>
    <aside class="summary m-0 max-w-none">
      <h2 class="serif text-2xl">Order summary</h2>
      <?php foreach ($items as $item): ?>
        <div class="flex gap-3 py-4 border-b"><img class="w-14 h-16 object-cover" src="<?= e($item['image']) ?>" alt=""><div class="flex-1 text-sm"><b><?= e($item['name']) ?></b><p class="m-0 text-slate-500"><?= e($item['size_name']) ?> · <?= $item['quantity'] ?> pcs</p></div><b class="text-sm"><?= money($item['price'] * $item['quantity']) ?></b></div>
      <?php endforeach; ?>
      <div class="line text-lg mt-5"><strong>Total</strong><strong><?= money($total) ?></strong></div>
    </aside>
  </div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
