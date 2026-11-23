<?php
$orderId = (int) ($_SESSION['payment_confirmation_order'] ?? 0);
if (!$orderId) redirect('index.php');

$orderStatement = db()->prepare('SELECT o.*, c.full_name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.id=?');
$orderStatement->execute([$orderId]);
$order = $orderStatement->fetch();
if (!$order) redirect('index.php');

$paymentStatement = db()->prepare('SELECT proof_path FROM payments WHERE order_id=? ORDER BY submitted_at DESC, id DESC LIMIT 1');
$paymentStatement->execute([$orderId]);
$proofPath = (string) $paymentStatement->fetchColumn();
$proofUrl = $proofPath ? url($proofPath) : '';

$itemsStatement = db()->prepare('SELECT product_name, quantity, selected_size_name, selected_variant, selected_motif FROM order_items WHERE order_id=?');
$itemsStatement->execute([$orderId]);
$items = $itemsStatement->fetchAll();
$lines = [];
foreach ($items as $item) {
    $details = ['Ukuran ' . $item['selected_size_name']];
    if ($item['selected_variant']) $details[] = 'Warna/varian ' . $item['selected_variant'];
    if ($item['selected_motif']) $details[] = 'Motif ' . $item['selected_motif'];
    $lines[] = '- ' . $item['product_name'] . ' (' . implode(', ', $details) . ') x' . $item['quantity'];
}

$rawAdminNumber = preg_replace('/\D/', '', setting('whatsapp_number'));
$adminNumber = str_starts_with($rawAdminNumber, '0') ? '62' . substr($rawAdminNumber, 1) : $rawAdminNumber;
$proofLine = $proofUrl ? "\n\nBukti pembayaran: {$proofUrl}" : '';
$message = "Halo Admin TRIWEAR, saya sudah mengirim bukti pembayaran untuk pesanan berikut.\n\nNomor pesanan: {$order['order_number']}\nNama: {$order['full_name']}\n\nRincian pesanan:\n" . implode("\n", $lines) . "\n\nTotal: " . money((float) $order['total']) . $proofLine . "\n\nMohon konfirmasi dan informasi proses selanjutnya. Terima kasih.";
$whatsappUrl = 'https://wa.me/' . rawurlencode($adminNumber) . '?text=' . rawurlencode($message);
$questionUrl = 'https://wa.me/' . rawurlencode($adminNumber) . '?text=' . rawurlencode("Halo Admin TRIWEAR, saya ingin menanyakan pesanan {$order['order_number']}.\n\nTerima kasih.");
$alreadyConfirmed = !empty($order['admin_confirmed_at']);

$title = 'Payment Submitted';
require __DIR__ . '/header.php';
?>
<main class="payment-page">
  <section class="payment-card payment-success-card">
    <p class="eyebrow">PAYMENT SUBMITTED</p>
    <h1>Bukti pembayaran terkirim.</h1>
    <p class="payment-order">Pesanan <strong><?= e($order['order_number']) ?></strong><span>•</span><?= money((float) $order['total']) ?></p>
    <div class="payment-approved"><strong>Upload berhasil.</strong><span>Silakan konfirmasi pesanan ke admin melalui WhatsApp agar pesanan dapat diproses.</span></div>
    <?php if ($alreadyConfirmed): ?>
      <a class="btn whatsapp-confirm payment-submit" href="<?= e($questionUrl) ?>">JIKA ADA PERTANYAAN, HUBUNGI ADMIN</a>
    <?php else: ?>
      <a class="btn whatsapp-confirm payment-submit" data-order-confirm data-order-id="<?= (int) $order['id'] ?>" data-confirm-url="<?= url('confirm-order.php') ?>" data-csrf="<?= csrf_token() ?>" href="<?= e($whatsappUrl) ?>">KONFIRMASI PESANAN KE ADMIN</a>
    <?php endif; ?>
  </section>
</main>
<style>.payment-page{width:100%;max-width:680px;margin:42px auto;padding:0 18px}.payment-card{width:100%;background:#fff;padding:clamp(22px,6vw,42px);box-shadow:0 12px 32px #101b3510}.payment-card h1{margin:10px 0;font:600 clamp(36px,10vw,56px)/.95 'Playfair Display',serif;letter-spacing:-.05em}.payment-order{margin:0 0 26px;color:#46536a;font-size:15px}.payment-order span{margin:0 7px}.payment-approved{display:grid;gap:4px;margin-top:22px;padding:16px;border-left:3px solid #2f7d50;background:#eaf6ee;color:#155b35}.payment-submit{display:flex;align-items:center;justify-content:center;width:100%;min-height:52px;margin-top:22px;touch-action:manipulation}.whatsapp-confirm{background:#1d6f45;border-color:#1d6f45}@media(max-width:480px){.payment-page{margin:28px auto;padding:0 14px}.payment-card{padding:24px 18px}}</style>
<script>document.querySelectorAll('[data-order-confirm]').forEach(link=>link.addEventListener('click',()=>{fetch(link.dataset.confirmUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:link.dataset.csrf,order_id:link.dataset.orderId}),keepalive:true});}));</script>
<?php require __DIR__ . '/footer.php'; ?>
