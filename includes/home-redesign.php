<?php
require_once __DIR__ . '/../config.php';
$featured = db()->query("SELECT p.*, c.name category_name, (SELECT image_url FROM product_images WHERE product_id=p.id ORDER BY sort_order LIMIT 1) image FROM products p JOIN categories c ON c.id=p.category_id WHERE is_featured=1 ORDER BY p.id LIMIT 3")->fetchAll();
$title = 'Wear Your Identity';
$customMessage = "Halo Admin TRIWEAR, saya ingin konsultasi Custom Apparel.\n\nJenis produk: \nJumlah kebutuhan: \nUkuran: \nWarna: \nDesain/logo: \nTarget selesai: \n\nTerima kasih.";
$customWhatsappUrl = 'https://wa.me/' . rawurlencode(setting('whatsapp_number')) . '?text=' . rawurlencode($customMessage);
$customOrderUrl = url('custom-order.php');
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="<?= url('assets/css/redesign.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/home-cleanup.css') ?>">
<script>
document.addEventListener('DOMContentLoaded', function () {
  const heroImage = document.querySelector('.new-hero-art img');
  if (heroImage) { heroImage.src = '<?= url('assets/images/hmp-ugm-work-jacket.png') ?>'; heroImage.alt = 'Jaket HMP UGM'; }
  const heroSection = document.querySelector('.new-hero');
  if (heroSection) heroSection.classList.add('hero-text-only');
  document.querySelector('.new-hero-art')?.remove();
  document.querySelector('.new-stamp')?.remove();
  const heroNote = document.querySelector('.new-note');
  if (heroNote) heroNote.textContent = 'Crafted for campus life';
  const heroEyebrow = document.querySelector('.new-hero-copy .new-eyebrow');
  if (heroEyebrow) heroEyebrow.textContent = heroEyebrow.textContent.replace(/\s*2026\s*$/, '');
  const heroDescription = document.querySelector('.new-hero-copy > p:not(.new-eyebrow)');
  if (heroDescription) heroDescription.textContent = heroDescription.textContent.replace(/—/g, ' ');
  document.querySelector('.new-actions .light')?.remove();
  document.querySelectorAll('[data-lucide^="arrow-"]').forEach(function (icon) { icon.remove(); });
  const cards = document.querySelectorAll('.new-card');
  const batikCard = cards[1];
  if (batikCard) {
    const image = batikCard.querySelector('img');
    if (image) { image.src = '<?= url('assets/images/batik-couple.png') ?>'; image.alt = 'Pasangan mengenakan koleksi batik'; }
  }
  const customCard = cards[2];
  if (customCard) {
    customCard.classList.add('custom-apparel-card');
    const link = <?= json_encode($customOrderUrl, JSON_UNESCAPED_SLASHES) ?>;
    const image = customCard.querySelector('img');
    const imageLink = customCard.querySelector('.new-image');
    if (image) { image.src = '<?= url('assets/images/custom-apparel.png') ?>'; image.alt = 'Layanan custom apparel'; }
    if (imageLink) imageLink.href = link;
    const category = customCard.querySelector('small'); if (category) category.textContent = 'CUSTOM APPAREL';
    const title = customCard.querySelector('h3'); if (title) title.textContent = 'Hubungi Admin untuk Custom Baju';
    const footer = customCard.querySelector('footer');
    if (footer) footer.innerHTML = '<a class="custom-wa-link" href="' + link + '" aria-label="Pilih jenis custom apparel" title="Pilih jenis custom apparel">Pilih</a>';
  }
});
</script>
<style>.new-stamp{display:none!important}.new-card.custom-apparel-card h3{font-size:clamp(17px,1.25vw,21px)!important;line-height:1.15!important;letter-spacing:-.025em!important;white-space:nowrap}.new-card footer:has(.custom-wa-link){display:flex!important;justify-content:flex-end!important;padding-top:14px}.new-card footer .custom-wa-link{display:grid!important;place-items:center!important;width:46px!important;height:38px!important;min-height:38px!important;margin:0!important;padding:0!important;background:#101b35!important;color:#fff!important;font:800 9px/1 Inter,Arial,sans-serif!important;letter-spacing:.05em!important;text-decoration:none!important;text-transform:uppercase!important;transform:none!important;transition:transform .25s ease,background .25s ease,box-shadow .25s ease!important;animation:custom-pulse 2.6s ease-in-out infinite}.new-card footer .custom-wa-link:hover{background:#c8a96b!important;transform:translateY(-4px) scale(1.08)!important;box-shadow:0 10px 19px #101b3548}@keyframes custom-pulse{0%,100%{box-shadow:0 0 0 0 #101b3538}50%{box-shadow:0 0 0 7px #101b3500}}</style>
<main class="home-redesign">
  <section class="new-hero" aria-labelledby="hero-title">
    <div class="new-hero-copy"><p class="new-eyebrow">TRIWEAR / CAMPUS COLLECTION 2026</p><h1 id="hero-title">Wear what<br><em>moves</em> you.</h1><p>Tiga essential pieces untuk membawa identitas kampusmu—dibuat dengan material yang terasa istimewa dan desain yang tidak cepat usang.</p><div class="new-actions"><a class="new-btn gold" href="#products">Lihat koleksi <i data-lucide="arrow-down-right"></i></a><a class="new-btn light" href="<?= url('custom-order.php') ?>">Buat custom</a></div></div>
    <div class="new-hero-art"><div class="new-stamp">EST.<br>2026</div><img src="https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1200&q=88" alt="Jaket varsity TRIWEAR"><div class="new-note">— Crafted for campus life</div></div>
  </section>
  <section id="products" class="new-products" aria-labelledby="products-title"><div class="new-heading"><div><p class="new-eyebrow">THE EDIT / 03 PIECES</p><h2 id="products-title">The essential<br>campus uniform.</h2></div></div><div class="new-grid">
    <?php foreach ($featured as $index => $p): $isHmpJacket = $p['slug'] === 'varsity-triwear-26'; $cardImage = $isHmpJacket ? url('assets/images/hmp-ugm-work-jacket.png') : $p['image']; $cardName = $isHmpJacket ? 'Jaket HMP UGM' : $p['name']; $cardPrice = $isHmpJacket ? 250000 : $p['price']; ?><article class="new-card"><a class="new-image" href="<?= url('product.php?slug=' . $p['slug']) ?>"><span>0<?= $index + 1 ?></span><img src="<?= e($cardImage) ?>" alt="<?= e($cardName) ?>"><b>Lihat detail <i data-lucide="arrow-up-right"></i></b></a><div><small><?= e($p['category_name']) ?></small><h3><?= e($cardName) ?></h3><footer><strong><?= money((float)$cardPrice) ?></strong><a href="<?= url('product.php?slug=' . $p['slug']) ?>" aria-label="Lihat <?= e($cardName) ?>"><i data-lucide="plus"></i></a></footer></div></article><?php endforeach; ?>
  </div></section>
  <section class="new-custom"><div><p class="new-eyebrow">FOR YOUR COLLECTIVE</p><h2>Your story.<br>Your <em>uniform.</em></h2></div><div><p>Punya desain sendiri untuk organisasi, himpunan, atau angkatan? Konsultasikan dengan tim kami dan buat koleksimu terasa personal.</p><a class="new-btn gold" href="<?= url('custom-order.php') ?>">Mulai custom order <i data-lucide="message-circle"></i></a></div></section>
</main>
<?php require __DIR__ . '/footer.php'; ?>
