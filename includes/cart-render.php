<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'buy') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $sizeCode = trim($_POST['size_code'] ?? '');
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $lookupSizeCode = $sizeCode === '4XL' ? 'XXXL' : $sizeCode;
        $query = db()->prepare('SELECT p.*, s.display_name FROM products p JOIN product_sizes ps ON ps.product_id=p.id JOIN sizes s ON s.id=ps.size_id WHERE p.id=? AND s.code=?');
        $query->execute([$productId, $lookupSizeCode]);
        $product = $query->fetch();
        if (!$product) { flash('error', 'Ukuran produk tidak valid.'); redirect('index.php'); }

        $isHmpJacket = $product['slug'] === 'varsity-triwear-26';
        $isBigSize = $isHmpJacket && in_array($sizeCode, ['XXL', 'XXXL', '4XL'], true);
        $unitPrice = $isHmpJacket ? ($isBigSize ? 260000 : 250000) : (float) $product['price'];
        $name = $isHmpJacket ? 'Jaket HMP UGM' : $product['name'];
        $image = $isHmpJacket ? url('assets/images/hmp-ugm-work-jacket.png') : '';
        if ($image === '') { $imageQuery = db()->prepare('SELECT image_url FROM product_images WHERE product_id=? ORDER BY sort_order LIMIT 1'); $imageQuery->execute([$productId]); $image = $imageQuery->fetchColumn() ?: ''; }
        $key = $productId . '|' . $sizeCode;
        $_SESSION['cart'][$key] = ['product_id' => $productId, 'name' => $name, 'price' => $unitPrice, 'image' => $image, 'size_code' => $sizeCode, 'size_name' => $sizeCode === '4XL' ? '4XL' : $product['display_name'], 'variant' => '', 'motif' => '', 'quantity' => $quantity, 'price_note' => $isBigSize ? 'Big size (+Rp10.000)' : 'Standard size'];
        if ($action === 'buy') redirect('checkout.php');
        flash('success', 'Produk ditambahkan ke keranjang.'); redirect('cart.php');
    }
    if ($action === 'update') { foreach ($_POST['qty'] ?? [] as $key => $quantity) { if (isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key]['quantity'] = max(1, (int) $quantity); } flash('success', 'Keranjang diperbarui.'); redirect('cart.php'); }
    if ($action === 'remove') { unset($_SESSION['cart'][$_POST['key'] ?? '']); flash('success', 'Produk dihapus dari keranjang.'); redirect('cart.php'); }
}

foreach ($_SESSION['cart'] ?? [] as &$item) {
    if ((int) ($item['product_id'] ?? 0) === 1) {
        $bigSize = in_array($item['size_code'] ?? '', ['XXL', 'XXXL', '4XL'], true);
        $item['name'] = 'Jaket HMP UGM';
        $item['image'] = url('assets/images/hmp-ugm-work-jacket.png');
        $item['price'] = $bigSize ? 260000 : 250000;
        $item['price_note'] = $bigSize ? 'Big size (+Rp10.000)' : 'Standard size';
    }
}
unset($item);
$items = $_SESSION['cart'] ?? [];
$total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $items));
$title = 'Shopping Cart';
require __DIR__ . '/header.php';
?>
<main class="cart-wrap"><div class="eyebrow">YOUR SELECTION</div><h1 class="serif text-5xl mt-2">Shopping cart.</h1>
<?php if (!$items): ?><div class="bg-white p-10 mt-6 text-center"><p>Keranjangmu masih kosong.</p><a class="btn mt-4" href="<?= url('index.php') ?>">KEMBALI KE HOME</a></div>
<?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="key">
<?php foreach ($items as $key => $item): ?><article class="cart-row"><img src="<?= e($item['image']) ?>" alt=""><div><small><?= e($item['size_name']) ?> · <?= e($item['price_note'] ?? '') ?></small><h3 class="serif text-xl mt-1"><?= e($item['name']) ?></h3><b><?= money($item['price']) ?></b></div><div class="quantity"><button type="button" onclick="this.nextElementSibling.stepDown()">−</button><input name="qty[<?= e($key) ?>]" type="number" min="1" value="<?= $item['quantity'] ?>"><button type="button" onclick="this.previousElementSibling.stepUp()">+</button></div><strong class="subtotal"><?= money($item['price'] * $item['quantity']) ?></strong><button class="remove text-red-700" formaction="<?= url('cart.php') ?>" formmethod="post" name="action" value="remove" onclick="this.form.querySelector('[name=key]').value='<?= e($key) ?>'">×</button></article><?php endforeach; ?><button class="text-link border-0 bg-transparent cursor-pointer mt-3" type="submit">UPDATE CART</button></form><aside class="summary"><div class="line"><span>Subtotal</span><strong><?= money($total) ?></strong></div><div class="line"><span>Pengiriman</span><span>Dihitung kemudian</span></div><hr class="my-4"><div class="line text-lg"><strong>Total</strong><strong><?= money($total) ?></strong></div><a class="btn gold w-full mt-4" href="<?= url('checkout.php') ?>">CHECKOUT</a></aside><?php endif; ?></main>
<?php if ($items): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const formatRupiah = function (amount) { return 'Rp ' + Math.round(amount).toLocaleString('id-ID'); };
  const parseRupiah = function (text) { return Number(String(text).replace(/[^0-9]/g, '')) || 0; };
  const recalculate = function () {
    let total = 0;
    document.querySelectorAll('.cart-row').forEach(function (row) {
      const quantityInput = row.querySelector('input[name^="qty"]');
      const unitPriceElement = row.querySelector('div:nth-child(2) b');
      const subtotalElement = row.querySelector('.subtotal');
      if (!quantityInput || !unitPriceElement || !subtotalElement) return;
      const quantity = Math.max(1, Number(quantityInput.value) || 1);
      const subtotal = parseRupiah(unitPriceElement.textContent) * quantity;
      subtotalElement.textContent = formatRupiah(subtotal);
      total += subtotal;
    });
    const summaryLines = document.querySelectorAll('.summary .line');
    if (summaryLines[0]) summaryLines[0].querySelector('strong').textContent = formatRupiah(total);
    if (summaryLines.length > 1) {
      const totalValue = summaryLines[summaryLines.length - 1].querySelector('strong:last-child');
      if (totalValue) totalValue.textContent = formatRupiah(total);
    }
  };
  document.querySelectorAll('input[name^="qty"]').forEach(function (input) { input.addEventListener('input', recalculate); input.addEventListener('change', recalculate); });
  document.querySelectorAll('.cart-row .quantity button').forEach(function (button) { button.addEventListener('click', function () { setTimeout(recalculate, 0); }); });
  recalculate();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
