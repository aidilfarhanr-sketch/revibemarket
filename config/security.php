<?php

return [
    'roles' => ['guest', 'user', 'admin'],
    'permissions' => [
        'guest' => ['view_products', 'register', 'login'],
        'user' => [
            'view_products', 'edit_profile', 'sell_product', 'manage_own_products',
            'checkout', 'upload_payment_proof', 'view_own_orders', 'review_order',
            'open_complaint', 'chat', 'wishlist', 'request_withdrawal'
        ],
        'admin' => ['*'],
    ],
];
