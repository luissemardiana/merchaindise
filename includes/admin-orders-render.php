<?php
require_admin();

$paymentLabels = [
    'waiting_payment' => 'Menunggu pembayaran',
    'waiting_verification' => 'Menunggu verifikasi',
    'approved' => 'Pembayaran disetujui',
    'rejected' => 'Pembayaran ditolak',
];
$productionLabels = [
    'created' => 'Pesanan dibuat',
    'processing' => 'Sedang diproses',
    'completed' => 'Selesai',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $payment = $_POST['payment_status'] ?? '';
    $production = $_POST['production_status'] ?? '';
    if (isset($paymentLabels[$payment], $productionLabels[$production])) {
        db()->prepare('UPDATE orders SET payment_status=?,production_status=? WHERE id=?')->execute([$payment, $production, $id]);
        if (in_array($payment, ['approved', 'rejected'], true)) {
            db()->prepare('UPDATE payments SET status=?,verified_at=NOW() WHERE order_id=? AND status="waiting_verification"')->execute([$payment, $id]);
        }
        flash('success', 'Status pesanan diperbarui.');
    }
    redirect('admin/orders.php');
}

$orders = db()->query('SELECT o.*,c.full_name,c.whatsapp,(SELECT proof_path FROM payments WHERE order_id=o.id ORDER BY id DESC LIMIT 1) proof FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC')->fetchAll();
$adminTitle = 'Order & payment management';
require __DIR__ . '/../admin/_header.php';
?>
<table class="data-table"><tr><th>Order</th><th>Customer</th><th>Total</th><th>Proof</th><th>Status</th><th>Update</th></tr>
<?php foreach ($orders as $order): ?>
  <?php $paymentText = !empty($order['payment_expired_at']) ? 'Dibatalkan: waktu habis' : ($paymentLabels[$order['payment_status']] ?? $order['payment_status']); ?>
  <tr>
    <td><b><?= e($order['order_number']) ?></b><br><small><?= e($order['category_key']) ?></small></td>
    <td><?= e($order['full_name']) ?><br><small><?= e($order['whatsapp']) ?></small></td>
    <td><?= money((float) $order['total']) ?></td>
    <td><?php if ($order['proof']): ?><a class="text-link" target="_blank" rel="noopener" href="<?= url($order['proof']) ?>">LIHAT BUKTI</a><?php else: ?><small>—</small><?php endif; ?></td>
    <td><span class="status"><?= e($paymentText) ?></span><br><span class="status"><?= e($productionLabels[$order['production_status']] ?? $order['production_status']) ?></span></td>
    <td><form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><select name="payment_status" class="text-xs"><?php foreach ($paymentLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $order['payment_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><select name="production_status" class="text-xs"><?php foreach ($productionLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $order['production_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="text-link border-0 bg-transparent cursor-pointer mt-2">SIMPAN</button></form></td>
  </tr>
<?php endforeach; ?>
</table>
<?php require __DIR__ . '/../admin/_footer.php'; ?>
