<?php
if (!function_exists('revibe_render_product_card')) {
function revibe_render_product_card(array $p): void { ?>
<article class="product-card revibe-product-card">
  <a href="<?= htmlspecialchars(function_exists('revibe_app_url') ? revibe_app_url('pages/detail.php?id='.(int)($p['id'] ?? 0)) : 'detail.php?id='.(int)($p['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
    <img loading="lazy" src="<?= htmlspecialchars(function_exists('revibe_public_file_url') ? revibe_public_file_url($p['image'] ?? 'default.png', 'products') : ('uploads/products/' . ($p['image'] ?? 'default.png')), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($p['name'] ?? 'Produk ReVibe'), ENT_QUOTES, 'UTF-8') ?>">
    <h3><?= htmlspecialchars((string)($p['name'] ?? 'Produk'), ENT_QUOTES, 'UTF-8') ?></h3>
  </a>
  <p class="price"><?= function_exists('money') ? money((int)($p['price'] ?? 0)) : 'Rp ' . number_format((int)($p['price'] ?? 0),0,',','.') ?></p>
  <span class="status-pill"><?= htmlspecialchars((string)($p['condition_status'] ?? $p['condition'] ?? 'Preloved'), ENT_QUOTES, 'UTF-8') ?></span>
</article>
<?php }} ?>
