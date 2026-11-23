<?php
require_once __DIR__ . '/../config.php';

$orders = [];
$searched = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $number = strtoupper(trim($_POST['order_number'] ?? ''));
    $whatsapp = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $statement = db()->prepare('SELECT o.*, c.full_name, c.whatsapp FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.order_number=? AND c.whatsapp=?');
    $statement->execute([$number, $whatsapp]);
    $orders = $statement->fetchAll();
    foreach ($orders as $order) $_SESSION['tracked_orders'][(int) $order['id']] = true;
    $searched = true;
}

$rawAdminNumber = preg_replace('/\D/', '', setting('whatsapp_number'));
$adminNumber = str_starts_with($rawAdminNumber, '0') ? '62' . substr($rawAdminNumber, 1) : $rawAdminNumber;
$itemStatement = db()->prepare('SELECT product_name, quantity, selected_size_name, selected_variant, selected_motif FROM order_items WHERE order_id=?');
$title = 'Track Order';
require __DIR__ . '/header.php';
?>
<main class="track-wrap"><div class="eyebrow">ORDER STATUS</div><h1 class="serif text-5xl">Cek pesanan.</h1>
  <form method="post" class="bg-white p-7 mt-7"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><div class="two-col"><div class="field"><label>Nomor pesanan</label><input required name="order_number" placeholder="J001"></div><div class="field"><label>Nomor WhatsApp</label><input required name="whatsapp" placeholder="628..."></div></div><button class="btn gold">CEK PESANAN</button></form>
  <?php if ($searched && !$orders): ?><p class="error mt-4">Pesanan tidak ditemukan. Periksa nomor pesanan dan WhatsApp Anda.</p><?php endif; ?>
  <?php foreach ($orders as $order): $itemStatement->execute([$order['id']]); $items = $itemStatement->fetchAll(); $lines = []; foreach ($items as $item) { $detail = 'Ukuran ' . $item['selected_size_name']; if ($item['selected_variant']) $detail .= ', Varian/Warna ' . $item['selected_variant']; if ($item['selected_motif']) $detail .= ', Motif ' . $item['selected_motif']; $lines[] = '- ' . $item['product_name'] . ' (' . $detail . ') x' . $item['quantity']; } $message = "Halo Admin TRIWEAR, pembayaran pesanan saya sudah berhasil diverifikasi.\n\nNomor pesanan: {$order['order_number']}\nNama: {$order['full_name']}\n\nRincian pesanan:\n" . implode("\n", $lines) . "\n\nTotal: " . money((float) $order['total']) . "\n\nMohon konfirmasi dan informasi proses produksi. Terima kasih."; $waUrl = 'https://wa.me/' . rawurlencode($adminNumber) . '?text=' . rawurlencode($message); $questionUrl = 'https://wa.me/' . rawurlencode($adminNumber) . '?text=' . rawurlencode("Halo Admin TRIWEAR, saya ingin menanyakan pesanan {$order['order_number']}.\n\nTerima kasih."); $alreadyConfirmed = !empty($order['admin_confirmed_at']); ?>
    <article class="bg-white p-7 mt-5"><div class="flex justify-between"><div><div class="eyebrow">ORDER NUMBER</div><h2 class="serif text-3xl m-0"><?= e($order['order_number']) ?></h2></div><strong><?= money((float) $order['total']) ?></strong></div><div class="grid md:grid-cols-2 gap-4 mt-5 text-sm"><p>Payment: <span class="status"><?= e(str_replace('_', ' ', $order['payment_status'])) ?></span></p><p>Production: <span class="status"><?= e(str_replace('_', ' ', $order['production_status'])) ?></span></p></div>
      <?php if ($order['payment_status'] === 'approved'): ?><div class="payment-approved" role="status"><strong>Pembayaran berhasil diverifikasi.</strong><span>Pesanan Anda siap masuk proses produksi.</span></div><?php if ($alreadyConfirmed): ?><a class="btn whatsapp-confirm mt-5" target="_blank" rel="noopener" href="<?= e($questionUrl) ?>">JIKA ADA PERTANYAAN, HUBUNGI ADMIN</a><?php else: ?><a class="btn whatsapp-confirm mt-5" target="_blank" rel="noopener" data-order-confirm data-order-id="<?= (int) $order['id'] ?>" data-confirm-url="<?= url('confirm-order.php') ?>" data-csrf="<?= csrf_token() ?>" href="<?= e($waUrl) ?>">KONFIRMASI PESANAN VIA WHATSAPP</a><?php endif; ?><?php elseif ($order['payment_status'] === 'waiting_payment'): ?><a class="btn gold mt-5" href="<?= url('payment-proof.php?order=' . $order['order_number']) ?>">UPLOAD BUKTI PEMBAYARAN</a><?php elseif ($order['payment_status'] === 'waiting_verification'): ?><p class="payment-pending">Bukti pembayaran sudah diterima dan sedang diverifikasi admin.</p><?php elseif ($order['payment_status'] === 'rejected'): ?><p class="error mt-5">Bukti pembayaran perlu diperiksa kembali. Silakan hubungi admin.</p><?php endif; ?></article>
  <?php endforeach; ?>
</main>
<script>document.querySelectorAll('[data-order-confirm]').forEach(link=>{link.target='_self';link.addEventListener('click',()=>{fetch(link.dataset.confirmUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:link.dataset.csrf,order_id:link.dataset.orderId}),keepalive:true});link.textContent='JIKA ADA PERTANYAAN, HUBUNGI ADMIN';link.removeAttribute('data-order-confirm');});});</script>
<style>.payment-approved{display:grid;gap:4px;margin-top:22px;padding:16px;border-left:3px solid #2f7d50;background:#eaf6ee;color:#155b35}.payment-pending{margin-top:22px;padding:14px;background:#f1f4f7;color:#536074;font-size:13px}.whatsapp-confirm{background:#1d6f45;border-color:#1d6f45}</style>
<?php require __DIR__ . '/footer.php'; ?>
