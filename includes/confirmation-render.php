<?php
$ids = $_SESSION['confirmation_orders'] ?? [];
if (!$ids) redirect('index.php');

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$statement = db()->prepare("SELECT o.*,b.bank_name,b.account_name,b.account_number FROM orders o LEFT JOIN bank_accounts b ON b.is_active=1 WHERE o.id IN ($placeholders)");
$statement->execute($ids);
$orders = $statement->fetchAll();
unset($_SESSION['confirmation_orders']);

$title = 'Order Confirmed';
require __DIR__ . '/header.php';
?>
<main class="track-wrap">
  <div class="bg-white p-8 md:p-12">
    <div class="eyebrow">ORDER CREATED</div>
    <h1 class="serif text-5xl mt-2">Thank you.</h1>
    <p class="text-slate-600 leading-7 mt-5">Pesanan Anda berhasil dibuat. Selesaikan pembayaran transfer untuk mengirim pesanan ke proses verifikasi.</p>
    <?php foreach ($orders as $order): ?>
      <section class="border-t mt-6 pt-5">
        <div class="flex justify-between"><div><span class="text-xs font-bold text-slate-500">ORDER NUMBER</span><h2 class="serif text-3xl m-0"><?= e($order['order_number']) ?></h2></div><b><?= money((float) $order['total']) ?></b></div>
        <div class="bg-slate-50 mt-4 p-4 text-sm">
          <p class="m-0">Transfer ke <b><?= e($order['bank_name']) ?> — <?= e($order['account_number']) ?></b><br>a.n. <?= e($order['account_name']) ?></p>
          <div class="flex gap-2 mt-3"><button class="btn outline" type="button" onclick="navigator.clipboard.writeText('<?= e($order['account_number']) ?>')">COPY ACCOUNT</button><button class="btn outline" type="button" onclick="navigator.clipboard.writeText('<?= e((string) $order['total']) ?>')">COPY AMOUNT</button><a class="btn gold" href="<?= url('payment-proof.php?order=' . $order['order_number']) ?>">UPLOAD PROOF</a></div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
