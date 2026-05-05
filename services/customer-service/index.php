<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'message' => 'Customer Service API',
    'version' => '1.0.0',
    'endpoints' => [
        'POST /api/customers/register.php' => 'Register new customer with OTP',
        'POST /api/customers/verify-otp.php' => 'Verify OTP and activate account',
        'POST /api/customers/login.php' => 'Login with email and password',
        'POST /api/customers/logout.php' => 'Logout (clear session)',
        'GET /api/customers/{id}/profile.php' => 'Get customer profile',
        'PUT /api/customers/{id}/profile.php' => 'Update customer profile',
        'PUT /api/customers/{id}/password.php' => 'Change password',
        'GET /api/customers/{id}/cart.php' => 'Get shopping cart items',
        'POST /api/customers/{id}/cart/add.php' => 'Add item to cart',
        'POST /api/customers/{id}/cart/remove.php' => 'Remove item from cart',
        'POST /api/customers/{id}/cart/update-qty.php' => 'Update item quantity',
        'POST /api/customers/{id}/cart/clear.php' => 'Clear entire cart',
        'GET /api/customers/{id}/addresses.php' => 'Get customer addresses',
        'POST /api/customers/{id}/addresses.php' => 'Add new address',
        'PUT /api/customers/{id}/addresses/{addr_id}.php' => 'Update address',
        'DELETE /api/customers/{id}/addresses/{addr_id}.php' => 'Delete address',
        'POST /api/customers/{id}/addresses/{addr_id}/set-default.php' => 'Set default address',
        'GET /api/customers/{id}/wishlist.php' => 'Get wishlist items',
        'POST /api/customers/{id}/wishlist/add.php' => 'Add product to wishlist',
        'POST /api/customers/{id}/wishlist/remove.php' => 'Remove product from wishlist',
        'GET /api/customers/{id}/orders.php' => 'Get customer orders',
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
