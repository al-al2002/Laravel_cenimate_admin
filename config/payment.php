<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Methods Configuration
    |--------------------------------------------------------------------------
    |
    | Configure payment methods with their QR codes and contact numbers
    |
    */

    'methods' => [
        'gcash' => [
            'name' => 'GCash',
            'enabled' => true,
            'qr_code_url' => env('GCASH_QR_CODE_URL', 'https://your-supabase-url/storage/v1/object/public/payment-qr/gcash-qr.png'),
            'account_number' => env('GCASH_NUMBER', '09123456789'),
            'account_name' => env('GCASH_ACCOUNT_NAME', 'Cinema Admin'),
        ],
        'maya' => [
            'name' => 'Maya',
            'enabled' => true,
            'qr_code_url' => env('MAYA_QR_CODE_URL', 'https://your-supabase-url/storage/v1/object/public/payment-qr/maya-qr.png'),
            'account_number' => env('MAYA_NUMBER', '09123456789'),
            'account_name' => env('MAYA_ACCOUNT_NAME', 'Cinema Admin'),
        ],
        'card' => [
            'name' => 'Credit/Debit Card',
            'enabled' => false, // Disabled for now, requires payment gateway
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reservation Settings
    |--------------------------------------------------------------------------
    */

    'reservation_expiry_minutes' => env('RESERVATION_EXPIRY_MINUTES', 15),
    'reference_number_prefix' => env('PAYMENT_REF_PREFIX', 'PAY'),
    'ticket_number_prefix' => env('TICKET_NUMBER_PREFIX', 'TKT'),
];
