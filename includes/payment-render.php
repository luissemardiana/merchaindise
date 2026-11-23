<?php
require_once __DIR__ . '/../config.php';

$number = $_GET['order'] ?? '';
$statement = db()->prepare('SELECT * FROM orders WHERE order_number=?');
$statement->execute([$number]);
$order = $statement->fetch();
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $file = $_FILES['proof'] ?? null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $mime = $file && $file['error'] === UPLOAD_ERR_OK ? mime_content_type($file['tmp_name']) : '';
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !isset($allowed[$mime])) {
        $error = 'File harus JPG, JPEG, PNG, atau PDF dengan ukuran maksimal 5MB.';
    } else {
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $directory = __DIR__ . '/../uploads/payment-proofs';
        if (!is_dir($directory)) mkdir($directory, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
            $error = 'Upload gagal disimpan. Silakan coba lagi.';
        } else {
            db()->prepare('INSERT INTO payments(order_id,amount,proof_path) VALUES(?,?,?)')->execute([$order['id'], $order['total'], 'uploads/payment-proofs/' . $name]);
            db()->prepare("UPDATE orders SET payment_status='waiting_verification' WHERE id=?")->execute([$order['id']]);
            flash('success', 'Bukti pembayaran terkirim dan sedang diverifikasi admin.');
            redirect('track.php');
        }
    }
}

$title = 'Upload Payment Proof';
require __DIR__ . '/header.php';
?>
<main class="payment-page"><section class="payment-card"><p class="eyebrow">PAYMENT VERIFICATION</p><h1>Upload bukti pembayaran.</h1><p class="payment-order">Pesanan <strong><?= e($order['order_number']) ?></strong><span>•</span><?= money((float) $order['total']) ?></p>
  <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label class="proof-label" for="proof">Bukti transfer <small>JPG, PNG, atau PDF · maks. 5MB</small></label><input required id="proof" name="proof" type="file" accept="image/jpeg,image/png,application/pdf"><div class="proof-preview" id="proof-preview" hidden><img id="proof-image" alt="Preview bukti pembayaran"><span id="proof-file-name"></span></div><button class="btn gold payment-submit" type="submit">KIRIM BUKTI PEMBAYARAN</button></form></section></main>
<style>html,body{overflow-x:hidden}.payment-page{width:100%;max-width:680px;margin:42px auto;padding:0 18px}.payment-card{width:100%;background:#fff;padding:clamp(22px,6vw,42px);box-shadow:0 12px 32px #101b3510}.payment-card h1{margin:10px 0;font:600 clamp(36px,10vw,56px)/.95 'Playfair Display',serif;letter-spacing:-.05em}.payment-order{margin:0 0 26px;color:#46536a;font-size:15px}.payment-order span{margin:0 7px}.proof-label{display:grid;gap:5px;margin-bottom:9px;font-size:14px;font-weight:800}.proof-label small{font-weight:500;color:#667085}input[type=file]{display:block;width:100%;max-width:100%;padding:10px;border:1px solid #ccd2dd;background:#fff;font:inherit;font-size:14px}.proof-preview{display:grid;grid-template-columns:72px minmax(0,1fr);align-items:center;gap:12px;margin-top:14px;padding:10px;border:1px solid #d9dde4;background:#f7f8fa;font-size:13px;overflow:hidden}.proof-preview img{width:72px;height:72px;object-fit:contain;background:#fff}.proof-preview span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.payment-submit{width:100%;min-height:52px;margin-top:22px;touch-action:manipulation}@media(max-width:480px){.payment-page{margin:28px auto;padding:0 14px}.payment-card{padding:24px 18px}.payment-order{font-size:14px}}</style>
<script>document.getElementById('proof').addEventListener('change',function(){const file=this.files&&this.files[0],box=document.getElementById('proof-preview'),image=document.getElementById('proof-image'),name=document.getElementById('proof-file-name');if(!file){box.hidden=true;return}name.textContent=file.name;box.hidden=false;if(file.type.startsWith('image/')){image.src=URL.createObjectURL(file);image.hidden=false}else{image.removeAttribute('src');image.hidden=true}});</script>
<?php require __DIR__ . '/footer.php'; ?>
