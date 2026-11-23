<?php
require_once __DIR__ . '/../config.php';

function expire_payment_order(array $order): bool {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $expire = $pdo->prepare("UPDATE orders SET payment_status='rejected', payment_expired_at=NOW() WHERE id=? AND payment_status='waiting_payment' AND payment_expired_at IS NULL");
        $expire->execute([(int) $order['id']]);
        if ($expire->rowCount() !== 1) {
            $pdo->commit();
            return false;
        }

        $items = $pdo->prepare('SELECT product_id, quantity, selected_size_code FROM order_items WHERE order_id=?');
        $items->execute([(int) $order['id']]);
        $restoreProduct = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');
        $restoreSize = $pdo->prepare('UPDATE product_sizes ps JOIN sizes s ON s.id=ps.size_id SET ps.stock=ps.stock+? WHERE ps.product_id=? AND s.code=?');
        foreach ($items->fetchAll() as $item) {
            $quantity = (int) $item['quantity'];
            $restoreProduct->execute([$quantity, (int) $item['product_id']]);
            $sizeCode = $item['selected_size_code'] === '4XL' ? 'XXXL' : $item['selected_size_code'];
            $restoreSize->execute([$quantity, (int) $item['product_id'], $sizeCode]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

$number = $_GET['order'] ?? '';
$statement = db()->prepare('SELECT o.*, UNIX_TIMESTAMP(DATE_ADD(o.created_at, INTERVAL 20 MINUTE)) AS payment_deadline_epoch FROM orders o WHERE o.order_number=?');
$statement->execute([$number]);
$order = $statement->fetch();
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }

$deadline = (int) $order['payment_deadline_epoch'];
$expired = !empty($order['payment_expired_at']) || ($order['payment_status'] === 'waiting_payment' && time() >= $deadline);
if ($expired && empty($order['payment_expired_at'])) {
    expire_payment_order($order);
    $order['payment_status'] = 'rejected';
    $order['payment_expired_at'] = date('Y-m-d H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$expired) {
    verify_csrf();
    if ($order['payment_status'] !== 'waiting_payment') {
        $error = 'Bukti pembayaran untuk pesanan ini sudah dikirim atau tidak dapat diproses.';
    } elseif (time() >= $deadline) {
        expire_payment_order($order);
        $expired = true;
        $order['payment_status'] = 'rejected';
        $order['payment_expired_at'] = date('Y-m-d H:i:s');
    } else {
        $file = $_FILES['proof'] ?? null;
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic', 'image/heif' => 'heif', 'application/pdf' => 'pdf'];
        $mime = $file && $file['error'] === UPLOAD_ERR_OK ? mime_content_type($file['tmp_name']) : '';
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !isset($allowed[$mime])) {
            $error = 'File harus JPG, JPEG, PNG, HEIC, HEIF, atau PDF dengan ukuran maksimal 5MB.';
        } else {
            $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            $directory = __DIR__ . '/../uploads/payment-proofs';
            if (!is_dir($directory)) mkdir($directory, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
                $error = 'Upload gagal disimpan. Silakan coba lagi.';
            } elseif (time() >= $deadline) {
                @unlink($directory . '/' . $name);
                expire_payment_order($order);
                $expired = true;
                $order['payment_status'] = 'rejected';
                $order['payment_expired_at'] = date('Y-m-d H:i:s');
            } else {
                db()->prepare('INSERT INTO payments(order_id,amount,proof_path) VALUES(?,?,?)')->execute([$order['id'], $order['total'], 'uploads/payment-proofs/' . $name]);
                db()->prepare("UPDATE orders SET payment_status='waiting_verification' WHERE id=? AND payment_status='waiting_payment'")->execute([$order['id']]);
                $_SESSION['payment_confirmation_order'] = (int) $order['id'];
                redirect('payment-confirmation.php');
            }
        }
    }
}

$title = 'Upload Payment Proof';
require __DIR__ . '/header.php';
?>
<main class="payment-page"><section class="payment-card">
  <p class="eyebrow">PAYMENT VERIFICATION</p><h1>Upload bukti pembayaran.</h1>
  <p class="payment-order">Pesanan <strong><?= e($order['order_number']) ?></strong><span>•</span><?= money((float) $order['total']) ?></p>
  <?php if ($expired): ?>
    <div class="payment-expired" role="alert"><strong>Pesanan dibatalkan.</strong><span>Waktu pembayaran sudah selesai. Stok pesanan telah dikembalikan.</span></div>
  <?php else: ?>
    <div class="payment-timer"><span>Selesaikan pembayaran dalam</span><strong id="payment-countdown" data-deadline="<?= $deadline * 1000 ?>">20:00</strong></div>
    <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label class="proof-label" for="proof">Bukti transfer <small>Screenshot/foto: JPG, PNG, HEIC, HEIF, atau PDF · maks. 5MB</small></label><input required id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/heic,image/heif,.heic,.heif,application/pdf"><div class="proof-preview" id="proof-preview" hidden><img id="proof-image" alt="Preview bukti pembayaran"><span id="proof-file-name"></span></div><button class="btn gold payment-submit" type="submit">KIRIM BUKTI PEMBAYARAN</button></form>
  <?php endif; ?>
</section></main>
<style>html,body{overflow-x:hidden}.payment-page{width:100%;max-width:680px;margin:42px auto;padding:0 18px}.payment-card{width:100%;background:#fff;padding:clamp(22px,6vw,42px);box-shadow:0 12px 32px #101b3510}.payment-card h1{margin:10px 0;font:600 clamp(36px,10vw,56px)/.95 'Playfair Display',serif;letter-spacing:-.05em}.payment-order{margin:0 0 20px;color:#46536a;font-size:15px}.payment-order span{margin:0 7px}.payment-timer{display:flex;justify-content:space-between;align-items:center;margin:0 0 26px;padding:14px 16px;border:1px solid #e8d7ad;background:#fffaf0;color:#765b20}.payment-timer strong{font-size:22px;letter-spacing:.06em}.payment-expired{display:grid;gap:4px;margin-top:22px;padding:16px;border-left:3px solid #a03333;background:#fff1f1;color:#842525}.proof-label{display:grid;gap:5px;margin-bottom:9px;font-size:14px;font-weight:800}.proof-label small{font-weight:500;color:#667085}input[type=file]{display:block;width:100%;max-width:100%;padding:10px;border:1px solid #ccd2dd;background:#fff;font:inherit;font-size:14px}.proof-preview{display:grid;grid-template-columns:72px minmax(0,1fr);align-items:center;gap:12px;margin-top:14px;padding:10px;border:1px solid #d9dde4;background:#f7f8fa;font-size:13px;overflow:hidden}.proof-preview img{width:72px;height:72px;object-fit:contain;background:#fff}.proof-preview span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.payment-submit{width:100%;min-height:52px;margin-top:22px;touch-action:manipulation}@media(max-width:480px){.payment-page{margin:28px auto;padding:0 14px}.payment-card{padding:24px 18px}.payment-order{font-size:14px}.payment-timer{align-items:flex-start;gap:12px;flex-direction:column}}</style>
<?php if (!$expired): ?><script>const proof=document.getElementById('proof');proof.addEventListener('change',function(){const file=this.files&&this.files[0],box=document.getElementById('proof-preview'),image=document.getElementById('proof-image'),name=document.getElementById('proof-file-name');if(!file){box.hidden=true;return}name.textContent=file.name;box.hidden=false;if(file.type.startsWith('image/')){image.src=URL.createObjectURL(file);image.hidden=false}else{image.removeAttribute('src');image.hidden=true}});const counter=document.getElementById('payment-countdown'),deadline=Number(counter.dataset.deadline);const tick=()=>{const remaining=Math.max(0,deadline-Date.now()),minutes=Math.floor(remaining/60000),seconds=Math.floor(remaining%60000/1000);counter.textContent=String(minutes).padStart(2,'0')+':'+String(seconds).padStart(2,'0');if(!remaining){clearInterval(timer);location.reload();}};tick();const timer=setInterval(tick,1000);</script><?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
