<?php
$root = dirname(__DIR__);
$pages = ['index.php','pages/cart.php','pages/checkout.php','pages/payment.php','pages/invoice.php','pages/buyer_orders.php','pages/seller_center.php','pages/admin/index.php'];
$result = ['success'=>true,'pages'=>[]];
foreach ($pages as $page) {
    $path = $root . '/' . $page;
    $html = is_file($path) ? file_get_contents($path) : '';
    $checks = [
        'uses_style_css' => str_contains($html, 'assets/css/style.css') || str_contains($html, '../assets/css/style.css') || str_contains($html, '../../assets/css/style.css'),
        'uses_loader' => str_contains($html, 'rv-page-loader'),
        'uses_navbar' => str_contains($html, 'navbar'),
        'uses_php' => str_contains($html, '<?php'),
    ];
    $checks['ok'] = !in_array(false, $checks, true);
    if (!$checks['ok']) $result['success'] = false;
    $result['pages'][$page] = $checks;
}
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
